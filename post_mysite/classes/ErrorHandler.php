<?php
/**
 * Enhanced File Sharing System - Error Handler and Feedback System
 * Comprehensive error handling with user-friendly feedback
 */

require_once __DIR__ . '/config/Config.php';

class ErrorHandler {
    private static $instance = null;
    private $errors = [];
    private $warnings = [];
    private $debug_mode;

    private function __construct() {
        $this->debug_mode = config('app.debug') === 'true';
        $this->setupErrorHandling();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function setupErrorHandling() {
        // Set error reporting based on environment
        if ($this->debug_mode) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', 0);
        }

        // Set custom error handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError($errno, $errstr, $errfile, $errline) {
        $error = [
            'type' => $this->getErrorType($errno),
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'timestamp' => date('Y-m-d H:i:s'),
            'trace' => $this->debug_mode ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) : null
        ];

        $this->logError($error);

        // Don't show errors in production unless critical
        if (!$this->debug_mode && !in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            return;
        }

        $this->displayError($error);
    }

    public function handleException($exception) {
        $error = [
            'type' => 'Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'timestamp' => date('Y-m-d H:i:s'),
            'trace' => $this->debug_mode ? $exception->getTrace() : null
        ];

        $this->logError($error);

        if ($this->debug_mode) {
            $this->displayException($exception);
        } else {
            $this->displayUserFriendlyError('An unexpected error occurred. Please try again later.');
        }
    }

    public function handleShutdown() {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    private function getErrorType($errno) {
        $types = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];

        return $types[$errno] ?? 'Unknown Error';
    }

    private function logError($error) {
        $log_entry = sprintf(
            "[%s] %s: %s in %s on line %d\n",
            $error['timestamp'],
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line']
        );

        if ($error['trace']) {
            $log_entry .= "Stack trace:\n";
            foreach ($error['trace'] as $i => $trace) {
                $log_entry .= sprintf(
                    "  %d. %s%s%s() in %s on line %d\n",
                    $i + 1,
                    $trace['class'] ?? '',
                    $trace['type'] ?? '',
                    $trace['function'] ?? '',
                    $trace['file'] ?? 'unknown',
                    $trace['line'] ?? 0
                );
            }
        }

        $log_entry .= "\n";

        // Log to file
        $log_file = __DIR__ . '/logs/error.log';
        $this->ensureLogDirectory();

        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

        // Also log to system logger in production
        if (!$this->debug_mode) {
            syslog(LOG_ERR, $error['type'] . ': ' . $error['message']);
        }
    }

    private function displayError($error) {
        if (PHP_SAPI === 'cli') {
            echo sprintf(
                "PHP %s: %s in %s on line %d\n",
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
        } else {
            http_response_code(500);
            include __DIR__ . '/views/errors/500.php';
        }
    }

    private function displayException($exception) {
        if (PHP_SAPI === 'cli') {
            echo sprintf(
                "Uncaught Exception: %s in %s on line %d\nTrace:\n%s\n",
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );
        } else {
            http_response_code(500);
            include __DIR__ . '/views/errors/500.php';
        }
    }

    private function displayUserFriendlyError($message) {
        if (PHP_SAPI === 'cli') {
            echo "Error: {$message}\n";
        } else {
            http_response_code(500);
            include __DIR__ . '/views/errors/user_error.php';
        }
    }

    private function ensureLogDirectory() {
        $log_dir = __DIR__ . '/logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }

    public function addError($message, $context = null) {
        $this->errors[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    public function addWarning($message, $context = null) {
        $this->warnings[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getWarnings() {
        return $this->warnings;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }

    public function hasWarnings() {
        return !empty($this->warnings);
    }

    public function clearErrors() {
        $this->errors = [];
    }

    public function clearWarnings() {
        $this->warnings = [];
    }
}

class FeedbackSystem {
    private $error_handler;

    public function __construct() {
        $this->error_handler = ErrorHandler::getInstance();
    }

    public function handleAjaxError($message, $code = 400) {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $this->error_handler->getErrors(),
            'warnings' => $this->error_handler->getWarnings()
        ];

        http_response_code($code);
        echo json_encode($response);
        exit;
    }

    public function handleAjaxSuccess($message, $data = []) {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'warnings' => $this->error_handler->getWarnings()
        ];

        echo json_encode($response);
        exit;
    }

    public function validateInput($data, $rules) {
        $errors = [];

        foreach ($rules as $field => $field_rules) {
            $value = $data[$field] ?? null;

            foreach ($field_rules as $rule => $rule_value) {
                switch ($rule) {
                    case 'required':
                        if (empty($value)) {
                            $errors[$field][] = "Field '{$field}' is required";
                        }
                        break;

                    case 'min_length':
                        if (strlen($value) < $rule_value) {
                            $errors[$field][] = "Field '{$field}' must be at least {$rule_value} characters";
                        }
                        break;

                    case 'max_length':
                        if (strlen($value) > $rule_value) {
                            $errors[$field][] = "Field '{$field}' must be no more than {$rule_value} characters";
                        }
                        break;

                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = "Field '{$field}' must be a valid email address";
                        }
                        break;

                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field][] = "Field '{$field}' must be numeric";
                        }
                        break;

                    case 'min':
                        if ($value < $rule_value) {
                            $errors[$field][] = "Field '{$field}' must be at least {$rule_value}";
                        }
                        break;

                    case 'max':
                        if ($value > $rule_value) {
                            $errors[$field][] = "Field '{$field}' must be no more than {$rule_value}";
                        }
                        break;

                    case 'in':
                        if (!in_array($value, $rule_value)) {
                            $errors[$field][] = "Field '{$field}' must be one of: " . implode(', ', $rule_value);
                        }
                        break;

                    case 'regex':
                        if (!preg_match($rule_value, $value)) {
                            $errors[$field][] = "Field '{$field}' format is invalid";
                        }
                        break;

                    case 'file_type':
                        $extension = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));
                        if (!in_array($extension, $rule_value)) {
                            $errors[$field][] = "File type not allowed. Allowed types: " . implode(', ', $rule_value);
                        }
                        break;

                    case 'file_size':
                        if ($value['size'] > $rule_value) {
                            $max_size_mb = round($rule_value / 1024 / 1024, 1);
                            $errors[$field][] = "File size must be no more than {$max_size_mb}MB";
                        }
                        break;
                }
            }
        }

        return $errors;
    }

    public function sanitizeInput($data, $allowed_fields = null) {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Skip fields not in allowed list
            if ($allowed_fields && !in_array($key, $allowed_fields)) {
                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInput($value, $allowed_fields);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    public function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCSRFToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }

        // Generate new token for next request
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return true;
    }

    public function rateLimit($identifier, $max_attempts = 100, $window_seconds = 3600) {
        $key = 'rate_limit_' . md5($identifier);
        $now = time();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'window_start' => $now
            ];
        }

        $data = $_SESSION[$key];

        // Reset window if expired
        if (($now - $data['window_start']) > $window_seconds) {
            $data = [
                'attempts' => 0,
                'window_start' => $now
            ];
            $_SESSION[$key] = $data;
        }

        $data['attempts']++;

        if ($data['attempts'] > $max_attempts) {
            $this->error_handler->addError('Rate limit exceeded');
            return false;
        }

        $_SESSION[$key] = $data;
        return true;
    }

    public function auditLog($action, $user_id = null, $details = []) {
        try {
            $db = Database::getInstance();

            $db->insert('audit_logs', [
                'user_id' => $user_id,
                'action' => $action,
                'old_values' => isset($details['old']) ? json_encode($details['old']) : null,
                'new_values' => isset($details['new']) ? json_encode($details['new']) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return true;
        } catch (Exception $e) {
            // Don't throw error for audit logging failures
            error_log('Audit log failed: ' . $e->getMessage());
            return false;
        }
    }
}

// Global helper functions
function handleError($message, $context = null) {
    ErrorHandler::getInstance()->addError($message, $context);
}

function handleWarning($message, $context = null) {
    ErrorHandler::getInstance()->addWarning($message, $context);
}

function hasErrors() {
    return ErrorHandler::getInstance()->hasErrors();
}

function hasWarnings() {
    return ErrorHandler::getInstance()->hasWarnings();
}

function getErrors() {
    return ErrorHandler::getInstance()->getErrors();
}

function getWarnings() {
    return ErrorHandler::getInstance()->getWarnings();
}

function clearErrors() {
    ErrorHandler::getInstance()->clearErrors();
}

function clearWarnings() {
    ErrorHandler::getInstance()->clearWarnings();
}

// AJAX response helpers
function ajaxError($message, $code = 400) {
    $feedback = new FeedbackSystem();
    $feedback->handleAjaxError($message, $code);
}

function ajaxSuccess($message, $data = []) {
    $feedback = new FeedbackSystem();
    $feedback->handleAjaxSuccess($message, $data);
}

// Input validation helper
function validate($data, $rules) {
    $feedback = new FeedbackSystem();
    return $feedback->validateInput($data, $rules);
}

// Input sanitization helper
function sanitize($data, $allowed_fields = null) {
    $feedback = new FeedbackSystem();
    return $feedback->sanitizeInput($data, $allowed_fields);
}

// CSRF helpers
function csrfToken() {
    $feedback = new FeedbackSystem();
    return $feedback->generateCSRFToken();
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function validateCSRF($token) {
    $feedback = new FeedbackSystem();
    return $feedback->validateCSRFToken($token);
}

// Audit logging helper
function audit($action, $user_id = null, $details = []) {
    $feedback = new FeedbackSystem();
    return $feedback->auditLog($action, $user_id, $details);
}
