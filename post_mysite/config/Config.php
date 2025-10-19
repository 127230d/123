<?php
/**
 * Enhanced File Sharing System - Configuration File
 * Centralized configuration management with environment support
 */

class Config {
    private static $instance = null;
    private $config = [];
    private $env_file = __DIR__ . '/.env';

    private function __construct() {
        $this->loadEnvironmentVariables();
        $this->loadConfiguration();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnvironmentVariables() {
        // Load .env file if it exists
        if (file_exists($this->env_file)) {
            $lines = file($this->env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, '"\'');
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }

    private function loadConfiguration() {
        $this->config = [
            // Database Configuration
            'database' => [
                'host' => getenv('DB_HOST') ?: 'localhost',
                'username' => getenv('DB_USERNAME') ?: 'root',
                'password' => getenv('DB_PASSWORD') ?: '',
                'database' => getenv('DB_DATABASE') ?: 'users_app',
                'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
                'port' => getenv('DB_PORT') ?: 3306,
                'prefix' => getenv('DB_PREFIX') ?: '',
            ],

            // Application Configuration
            'app' => [
                'name' => getenv('APP_NAME') ?: 'Enhanced File Sharing System',
                'version' => '2.0.0',
                'url' => getenv('APP_URL') ?: 'http://localhost',
                'environment' => getenv('APP_ENV') ?: 'development',
                'debug' => getenv('APP_DEBUG') ?: 'true',
                'key' => getenv('APP_KEY') ?: 'base64:' . base64_encode(random_bytes(32)),
            ],

            // File Upload Configuration
            'upload' => [
                'max_file_size' => (int)(getenv('MAX_FILE_SIZE') ?: 104857600), // 100MB
                'allowed_extensions' => explode(',', getenv('ALLOWED_EXTENSIONS') ?: 'pdf,doc,docx,txt,jpg,jpeg,png,gif,zip,rar,7z,json,csv,xls,xlsx,ppt,pptx,mp3,mp4'),
                'upload_path' => __DIR__ . '/uploads/',
                'temp_path' => __DIR__ . '/uploads/temp/',
                'chunk_size' => (int)(getenv('CHUNK_SIZE') ?: 1048576), // 1MB
                'max_chunks' => (int)(getenv('MAX_CHUNKS') ?: 1000),
            ],

            // Security Configuration
            'security' => [
                'csrf_protection' => getenv('CSRF_PROTECTION') !== 'false',
                'session_lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 7200), // 2 hours
                'password_min_length' => (int)(getenv('PASSWORD_MIN_LENGTH') ?: 8),
                'require_email_verification' => getenv('REQUIRE_EMAIL_VERIFICATION') === 'true',
                'enable_rate_limiting' => getenv('ENABLE_RATE_LIMITING') !== 'false',
                'max_login_attempts' => (int)(getenv('MAX_LOGIN_ATTEMPTS') ?: 5),
            ],

            // Payment Configuration
            'payment' => [
                'default_currency' => getenv('DEFAULT_CURRENCY') ?: 'points',
                'point_value' => (float)(getenv('POINT_VALUE') ?: 0.01), // USD per point
                'min_price' => (float)(getenv('MIN_PRICE') ?: 1.00),
                'max_price' => (float)(getenv('MAX_PRICE') ?: 10000.00),
            ],

            // Email Configuration
            'email' => [
                'smtp_host' => getenv('SMTP_HOST') ?: 'localhost',
                'smtp_port' => (int)(getenv('SMTP_PORT') ?: 587),
                'smtp_username' => getenv('SMTP_USERNAME') ?: '',
                'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
                'from_email' => getenv('FROM_EMAIL') ?: 'noreply@example.com',
                'from_name' => getenv('FROM_NAME') ?: 'File Sharing System',
            ],

            // Cache Configuration
            'cache' => [
                'enabled' => getenv('CACHE_ENABLED') !== 'false',
                'driver' => getenv('CACHE_DRIVER') ?: 'file',
                'ttl' => (int)(getenv('CACHE_TTL') ?: 3600), // 1 hour
                'path' => __DIR__ . '/cache/',
            ],

            // API Configuration
            'api' => [
                'rate_limit' => (int)(getenv('API_RATE_LIMIT') ?: 1000), // requests per hour
                'version' => getenv('API_VERSION') ?: 'v1',
            ],

            // File Preview Configuration
            'preview' => [
                'max_text_length' => (int)(getenv('MAX_PREVIEW_TEXT_LENGTH') ?: 500),
                'max_image_size' => (int)(getenv('MAX_PREVIEW_IMAGE_SIZE') ?: 2097152), // 2MB
                'thumbnail_size' => (int)(getenv('THUMBNAIL_SIZE') ?: 200),
                'text_extensions' => ['txt', 'md', 'json', 'csv', 'log', 'xml', 'html', 'css', 'js', 'php', 'py', 'java', 'cpp', 'c', 'h'],
            ],

            // System Limits
            'limits' => [
                'max_files_per_user' => (int)(getenv('MAX_FILES_PER_USER') ?: 100),
                'max_favorites_per_user' => (int)(getenv('MAX_FAVORITES_PER_USER') ?: 500),
                'max_comments_per_file' => (int)(getenv('MAX_COMMENTS_PER_FILE') ?: 100),
                'max_file_description_length' => (int)(getenv('MAX_DESCRIPTION_LENGTH') ?: 2000),
                'max_comment_length' => (int)(getenv('MAX_COMMENT_LENGTH') ?: 500),
            ],
        ];
    }

    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    public function set($key, $value) {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    public function has($key) {
        return $this->get($key) !== null;
    }

    public function all() {
        return $this->config;
    }

    public function getDatabaseConfig() {
        return $this->config['database'];
    }

    public function getAppConfig() {
        return $this->config['app'];
    }

    public function isProduction() {
        return $this->config['app']['environment'] === 'production';
    }

    public function isDebug() {
        return $this->config['app']['debug'] === 'true';
    }
}

// Helper functions for easy access
function config($key, $default = null) {
    return Config::getInstance()->get($key, $default);
}

function app($key = null) {
    $config = Config::getInstance()->getAppConfig();
    return $key ? ($config[$key] ?? null) : $config;
}

function db($key = null) {
    $config = Config::getInstance()->getDatabaseConfig();
    return $key ? ($config[$key] ?? null) : $config;
}
