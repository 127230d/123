<?php
/**
 * Enhanced File Sharing System - Security Manager
 * Comprehensive security with input validation, CSRF protection, and file scanning
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';

class SecurityManager {
    private $db;
    private $config;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = config('security');
    }

    public function validateInput($data, $rules = []) {
        $errors = [];
        $sanitized = [];

        foreach ($data as $field => $value) {
            // Skip validation if no rules defined for this field
            if (!isset($rules[$field])) {
                $sanitized[$field] = $this->sanitizeValue($value);
                continue;
            }

            $field_rules = $rules[$field];
            $field_errors = [];

            // Sanitize first
            $clean_value = $this->sanitizeValue($value);

            // Apply validation rules
            foreach ($field_rules as $rule => $rule_value) {
                switch ($rule) {
                    case 'required':
                        if (empty($clean_value) && $clean_value !== '0' && $clean_value !== 0) {
                            $field_errors[] = "Field '{$field}' is required";
                        }
                        break;

                    case 'min_length':
                        if (strlen($clean_value) < $rule_value) {
                            $field_errors[] = "Field '{$field}' must be at least {$rule_value} characters";
                        }
                        break;

                    case 'max_length':
                        if (strlen($clean_value) > $rule_value) {
                            $field_errors[] = "Field '{$field}' must be no more than {$rule_value} characters";
                        }
                        break;

                    case 'email':
                        if (!empty($clean_value) && !filter_var($clean_value, FILTER_VALIDATE_EMAIL)) {
                            $field_errors[] = "Field '{$field}' must be a valid email address";
                        }
                        break;

                    case 'url':
                        if (!empty($clean_value) && !filter_var($clean_value, FILTER_VALIDATE_URL)) {
                            $field_errors[] = "Field '{$field}' must be a valid URL";
                        }
                        break;

                    case 'numeric':
                        if (!empty($clean_value) && !is_numeric($clean_value)) {
                            $field_errors[] = "Field '{$field}' must be numeric";
                        }
                        break;

                    case 'integer':
                        if (!empty($clean_value) && !filter_var($clean_value, FILTER_VALIDATE_INT)) {
                            $field_errors[] = "Field '{$field}' must be an integer";
                        }
                        break;

                    case 'alpha':
                        if (!empty($clean_value) && !ctype_alpha($clean_value)) {
                            $field_errors[] = "Field '{$field}' must contain only letters";
                        }
                        break;

                    case 'alphanumeric':
                        if (!empty($clean_value) && !ctype_alnum($clean_value)) {
                            $field_errors[] = "Field '{$field}' must contain only letters and numbers";
                        }
                        break;

                    case 'min':
                        if (!empty($clean_value) && $clean_value < $rule_value) {
                            $field_errors[] = "Field '{$field}' must be at least {$rule_value}";
                        }
                        break;

                    case 'max':
                        if (!empty($clean_value) && $clean_value > $rule_value) {
                            $field_errors[] = "Field '{$field}' must be no more than {$rule_value}";
                        }
                        break;

                    case 'in':
                        if (!empty($clean_value) && !in_array($clean_value, (array)$rule_value)) {
                            $field_errors[] = "Field '{$field}' must be one of: " . implode(', ', (array)$rule_value);
                        }
                        break;

                    case 'regex':
                        if (!empty($clean_value) && !preg_match($rule_value, $clean_value)) {
                            $field_errors[] = "Field '{$field}' format is invalid";
                        }
                        break;

                    case 'file_type':
                        if (!empty($_FILES[$field])) {
                            $file = $_FILES[$field];
                            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                            if (!in_array($extension, (array)$rule_value)) {
                                $field_errors[] = "File type not allowed. Allowed types: " . implode(', ', (array)$rule_value);
                            }
                        }
                        break;

                    case 'file_size':
                        if (!empty($_FILES[$field])) {
                            $file = $_FILES[$field];

                            if ($file['size'] > $rule_value) {
                                $max_size_mb = round($rule_value / 1024 / 1024, 1);
                                $field_errors[] = "File size must be no more than {$max_size_mb}MB";
                            }
                        }
                        break;

                    case 'no_html':
                        if (strip_tags($clean_value) !== $clean_value) {
                            $field_errors[] = "Field '{$field}' cannot contain HTML";
                        }
                        break;

                    case 'no_script':
                        if (preg_match('/<script|javascript:|on\w+\s*=/i', $clean_value)) {
                            $field_errors[] = "Field '{$field}' cannot contain script tags or JavaScript";
                        }
                        break;
                }
            }

            if (!empty($field_errors)) {
                $errors[$field] = $field_errors;
            } else {
                $sanitized[$field] = $clean_value;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $sanitized
        ];
    }

    private function sanitizeValue($value) {
        if (is_array($value)) {
            return array_map([$this, 'sanitizeValue'], $value);
        }

        if (is_string($value)) {
            // Remove null bytes
            $value = str_replace("\0", '', $value);

            // Remove control characters except newlines and tabs
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

            // Trim whitespace
            return trim($value);
        }

        return $value;
    }

    public function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
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

        // Check token age (max 1 hour)
        if (isset($_SESSION['csrf_token_time'])) {
            if ((time() - $_SESSION['csrf_token_time']) > 3600) {
                $this->invalidateCSRFToken();
                return false;
            }
        }

        // Generate new token for next request
        $this->invalidateCSRFToken();

        return true;
    }

    public function invalidateCSRFToken() {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
    }

    public function scanFile($file_path) {
        if (!file_exists($file_path)) {
            return ['safe' => false, 'reason' => 'File not found'];
        }

        $scan_results = ['safe' => true, 'threats' => []];

        // Check file size (prevent zip bombs)
        $file_size = filesize($file_path);
        if ($file_size > 100 * 1024 * 1024) { // 100MB limit
            $scan_results['safe'] = false;
            $scan_results['threats'][] = 'File too large';
        }

        // Check file extension against actual content
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);

        if (!$this->isValidMimeType($extension, $mime_type)) {
            $scan_results['safe'] = false;
            $scan_results['threats'][] = 'MIME type mismatch';
        }

        // Scan for malicious signatures
        $malicious_patterns = [
            // PHP webshells
            '/<\?php.*eval.*\$_/i',
            '/<\?php.*system.*\$_/i',
            '/<\?php.*exec.*\$_/i',
            '/<\?php.*shell_exec.*\$_/i',
            '/<\?php.*passthru.*\$_/i',

            // JavaScript malware
            '/<script.*eval.*</i',
            '/javascript:.*eval/i',

            // Common backdoor patterns
            '/base64_decode.*eval/i',
            '/gzinflate.*eval/i',
            '/str_rot13.*eval/i',

            // File inclusion vulnerabilities
            '/include.*\$_/i',
            '/require.*\$_/i',
            '/file_get_contents.*http/i',
        ];

        if (is_readable($file_path)) {
            $content = file_get_contents($file_path, false, null, 0, 1024 * 1024); // First 1MB

            foreach ($malicious_patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $scan_results['safe'] = false;
                    $scan_results['threats'][] = 'Suspicious content detected';
                    break;
                }
            }
        }

        // Check file entropy (potential encryption/packing)
        $entropy = $this->calculateFileEntropy($file_path);
        if ($entropy > 7.5) {
            $scan_results['threats'][] = 'High entropy detected (possible encryption)';
        }

        return $scan_results;
    }

    private function isValidMimeType($extension, $mime_type) {
        $valid_mime_types = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'txt' => ['text/plain'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'rar' => ['application/x-rar-compressed'],
            '7z' => ['application/x-7z-compressed'],
            'json' => ['application/json'],
            'csv' => ['text/csv', 'text/plain'],
            'mp3' => ['audio/mpeg'],
            'mp4' => ['video/mp4'],
        ];

        return isset($valid_mime_types[$extension]) && in_array($mime_type, $valid_mime_types[$extension]);
    }

    private function calculateFileEntropy($file_path) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return 0;
        }

        $content = file_get_contents($file_path, false, null, 0, 65536); // First 64KB for entropy calculation

        if (!$content) {
            return 0;
        }

        $byte_counts = array_count_values(str_split($content));
        $total_bytes = strlen($content);

        $entropy = 0;
        foreach ($byte_counts as $count) {
            $probability = $count / $total_bytes;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }

    public function checkRateLimit($identifier, $max_attempts = 100, $window_seconds = 3600) {
        $key = 'rate_limit_' . md5($identifier);
        $now = time();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'window_start' => $now,
                'blocked_until' => null
            ];
        }

        $data = $_SESSION[$key];

        // Check if currently blocked
        if ($data['blocked_until'] && $data['blocked_until'] > $now) {
            return [
                'allowed' => false,
                'reason' => 'Temporarily blocked due to too many attempts',
                'blocked_until' => $data['blocked_until']
            ];
        }

        // Reset window if expired
        if (($now - $data['window_start']) > $window_seconds) {
            $data = [
                'attempts' => 0,
                'window_start' => $now,
                'blocked_until' => null
            ];
            $_SESSION[$key] = $data;
        }

        $data['attempts']++;

        // Check if rate limit exceeded
        if ($data['attempts'] > $max_attempts) {
            // Block for increasing periods based on violations
            $block_minutes = min($data['attempts'] - $max_attempts, 60); // Max 1 hour block
            $data['blocked_until'] = $now + ($block_minutes * 60);

            $_SESSION[$key] = $data;

            return [
                'allowed' => false,
                'reason' => 'Rate limit exceeded',
                'blocked_until' => $data['blocked_until']
            ];
        }

        $_SESSION[$key] = $data;

        return [
            'allowed' => true,
            'attempts_remaining' => $max_attempts - $data['attempts']
        ];
    }

    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    public function encrypt($data, $key = null) {
        $key = $key ?: config('app.key');

        if (strpos($key, 'base64:') === 0) {
            $key = base64_decode(substr($key, 7));
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    public function decrypt($data, $key = null) {
        $key = $key ?: config('app.key');

        if (strpos($key, 'base64:') === 0) {
            $key = base64_decode(substr($key, 7));
        }

        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    public function checkFilePermissions($file_path) {
        if (!file_exists($file_path)) {
            return ['allowed' => false, 'reason' => 'File not found'];
        }

        // Check if file is in allowed directory
        $allowed_dirs = [
            __DIR__ . '/uploads/',
            __DIR__ . '/uploads/thumbnails/',
            __DIR__ . '/uploads/temp/'
        ];

        $real_path = realpath($file_path);
        $in_allowed_dir = false;

        foreach ($allowed_dirs as $dir) {
            $real_dir = realpath($dir);
            if (strpos($real_path, $real_dir) === 0) {
                $in_allowed_dir = true;
                break;
            }
        }

        if (!$in_allowed_dir) {
            return ['allowed' => false, 'reason' => 'File not in allowed directory'];
        }

        // Check file permissions (should not be executable)
        if (is_executable($file_path)) {
            return ['allowed' => false, 'reason' => 'File should not be executable'];
        }

        return ['allowed' => true];
    }

    public function sanitizeFileName($filename) {
        // Remove path information
        $filename = basename($filename);

        // Remove or replace dangerous characters
        $filename = preg_replace('/[<>:"/\\\\|?*\x00-\x1f]/', '_', $filename);

        // Limit length
        $filename = substr($filename, 0, 255);

        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'file_' . time() . '.txt';
        }

        return $filename;
    }

    public function logSecurityEvent($event_type, $user_id = null, $details = []) {
        try {
            $this->db->insert('security_events', [
                'event_type' => $event_type,
                'user_id' => $user_id,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'details' => json_encode($details),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return true;
        } catch (Exception $e) {
            error_log('Security event logging failed: ' . $e->getMessage());
            return false;
        }
    }

    public function checkSuspiciousActivity($user_id) {
        $recent_events = $this->db->select("
            SELECT event_type, COUNT(*) as count
            FROM security_events
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY event_type
        ", [$user_id]);

        $suspicious_patterns = [
            'failed_login' => 5,      // 5 failed logins in 1 hour
            'csrf_violation' => 3,    // 3 CSRF violations in 1 hour
            'rate_limit' => 10,       // 10 rate limit hits in 1 hour
            'file_scan_fail' => 3,    // 3 file scan failures in 1 hour
        ];

        foreach ($recent_events as $event) {
            if (isset($suspicious_patterns[$event['event_type']]) &&
                $event['count'] >= $suspicious_patterns[$event['event_type']]) {

                $this->logSecurityEvent('suspicious_activity_detected', $user_id, [
                    'pattern' => $event['event_type'],
                    'count' => $event['count']
                ]);

                return true;
            }
        }

        return false;
    }

    public function validateSession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }

        // Check if session exists in database
        $session = $this->db->selectOne("
            SELECT * FROM user_sessions
            WHERE user_id = ? AND session_token = ? AND expires_at > NOW()
        ", [$_SESSION['user_id'], $_SESSION['session_token']]);

        if (!$session) {
            $this->destroySession();
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();

        return true;
    }

    public function destroySession() {
        if (isset($_SESSION['session_token'])) {
            $this->db->delete('user_sessions', 'session_token = ?', [$_SESSION['session_token']]);
        }

        session_destroy();
    }

    public function checkIPWhitelist($ip = null) {
        $ip = $ip ?: $_SERVER['REMOTE_ADDR'];

        // Add your IP whitelist logic here
        $whitelist = [
            '127.0.0.1',
            '::1',
            // Add more IPs as needed
        ];

        return in_array($ip, $whitelist);
    }

    public function checkIPBlacklist($ip = null) {
        $ip = $ip ?: $_SERVER['REMOTE_ADDR'];

        // Check against blacklist (implement your own logic)
        return false; // Placeholder
    }

    public function generateCaptcha() {
        $code = substr(str_shuffle('23456789ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 6);

        $_SESSION['captcha'] = [
            'code' => $code,
            'timestamp' => time()
        ];

        return $code;
    }

    public function validateCaptcha($input) {
        if (!isset($_SESSION['captcha'])) {
            return false;
        }

        // Check if captcha is not too old (5 minutes)
        if ((time() - $_SESSION['captcha']['timestamp']) > 300) {
            unset($_SESSION['captcha']);
            return false;
        }

        $valid = hash_equals($_SESSION['captcha']['code'], $input);

        if ($valid) {
            unset($_SESSION['captcha']);
        }

        return $valid;
    }
}

// Global security helper functions
function validate($data, $rules = []) {
    $security = new SecurityManager();
    return $security->validateInput($data, $rules);
}

function sanitize($value) {
    $security = new SecurityManager();
    return $security->sanitizeValue($value);
}

function csrfToken() {
    $security = new SecurityManager();
    return $security->generateCSRFToken();
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function checkRateLimit($identifier, $max_attempts = 100, $window_seconds = 3600) {
    $security = new SecurityManager();
    return $security->checkRateLimit($identifier, $max_attempts, $window_seconds);
}

function scanFile($file_path) {
    $security = new SecurityManager();
    return $security->scanFile($file_path);
}

function hashPassword($password) {
    $security = new SecurityManager();
    return $security->hashPassword($password);
}

function verifyPassword($password, $hash) {
    $security = new SecurityManager();
    return $security->verifyPassword($password, $hash);
}

function auditSecurity($event, $user_id = null, $details = []) {
    $security = new SecurityManager();
    return $security->logSecurityEvent($event, $user_id, $details);
}

function secureToken($length = 32) {
    $security = new SecurityManager();
    return $security->generateSecureToken($length);
}
