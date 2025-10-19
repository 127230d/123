<?php
/**
 * Enhanced File Sharing System - Performance Optimizer
 * Comprehensive performance optimization with caching and lazy loading
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';

class PerformanceOptimizer {
    private $cache;
    private $db;
    private $config;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = config('cache');
        $this->initializeCache();
    }

    private function initializeCache() {
        switch ($this->config['driver']) {
            case 'redis':
                $this->cache = new RedisCache();
                break;
            case 'memcached':
                $this->cache = new MemcachedCache();
                break;
            case 'file':
            default:
                $this->cache = new FileCache();
                break;
        }
    }

    public function cacheQuery($key, $query, $params = [], $ttl = null) {
        if (!$this->config['enabled']) {
            return $this->db->select($query, $params);
        }

        $cache_key = 'query_' . md5($key . $query . serialize($params));

        if ($this->cache->has($cache_key)) {
            return $this->cache->get($cache_key);
        }

        $result = $this->db->select($query, $params);
        $this->cache->set($cache_key, $result, $ttl ?: $this->config['ttl']);

        return $result;
    }

    public function cacheData($key, $data, $ttl = null) {
        if (!$this->config['enabled']) {
            return $data;
        }

        $this->cache->set($key, $data, $ttl ?: $this->config['ttl']);
        return $data;
    }

    public function getCachedData($key, $default = null) {
        if (!$this->config['enabled']) {
            return $default;
        }

        return $this->cache->get($key, $default);
    }

    public function invalidateCache($pattern = null) {
        if (!$this->config['enabled']) {
            return;
        }

        if ($pattern) {
            $this->cache->deleteByPattern($pattern);
        } else {
            $this->cache->clear();
        }
    }

    public function optimizeDatabaseQueries() {
        // Analyze slow queries
        $this->analyzeSlowQueries();

        // Optimize table indexes
        $this->optimizeIndexes();

        // Clean up old data
        $this->cleanupOldData();
    }

    private function analyzeSlowQueries() {
        if (!$this->db->getQueryLog()) {
            return;
        }

        $slow_queries = [];
        foreach ($this->db->getQueryLog() as $query) {
            if ($query['time'] > 1.0) { // Queries taking more than 1 second
                $slow_queries[] = [
                    'sql' => $query['sql'],
                    'params' => $query['params'],
                    'time' => $query['time']
                ];
            }
        }

        if (!empty($slow_queries)) {
            $this->logSlowQueries($slow_queries);
        }
    }

    private function logSlowQueries($queries) {
        $log_file = __DIR__ . '/logs/slow_queries.log';
        $this->ensureLogDirectory();

        $log_entry = sprintf(
            "[%s] Found %d slow queries:\n",
            date('Y-m-d H:i:s'),
            count($queries)
        );

        foreach ($queries as $query) {
            $log_entry .= sprintf(
                "  Time: %.4fs | SQL: %s | Params: %s\n",
                $query['time'],
                $query['sql'],
                json_encode($query['params'])
            );
        }

        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function optimizeIndexes() {
        // Add missing indexes based on common query patterns
        $indexes_to_add = [
            'shared_files' => [
                'idx_files_category_price' => '(category_id, price)',
                'idx_files_rating_downloads' => '(average_rating, download_count)',
                'idx_files_featured_available' => '(is_featured, is_available)',
                'idx_files_owner_created' => '(original_owner_id, created_at)',
            ],
            'file_purchases' => [
                'idx_purchases_buyer_date' => '(buyer_username, purchase_date)',
                'idx_purchases_seller_date' => '(seller_username, purchase_date)',
                'idx_purchases_file_date' => '(file_id, purchase_date)',
            ],
            'file_ratings' => [
                'idx_ratings_file_user' => '(file_id, user_id)',
                'idx_ratings_user_date' => '(user_id, created_at)',
                'idx_ratings_file_rating' => '(file_id, rating)',
            ]
        ];

        foreach ($indexes_to_add as $table => $indexes) {
            foreach ($indexes as $index_name => $columns) {
                $this->addIndexIfNotExists($table, $index_name, $columns);
            }
        }
    }

    private function addIndexIfNotExists($table, $index_name, $columns) {
        try {
            // Check if index exists
            $existing_indexes = $this->db->select(
                "SHOW INDEX FROM {$table} WHERE Key_name = ?",
                [$index_name]
            );

            if (empty($existing_indexes)) {
                $this->db->query("CREATE INDEX {$index_name} ON {$table} {$columns}");
            }
        } catch (Exception $e) {
            // Index might already exist or have issues - log but don't fail
            error_log("Could not create index {$index_name}: " . $e->getMessage());
        }
    }

    private function cleanupOldData() {
        // Clean up old cache files
        $this->cleanupOldCacheFiles();

        // Clean up expired share links
        $this->db->delete('file_share_links', 'expires_at < NOW()');

        // Clean up old audit logs (keep last 90 days)
        $this->db->delete('audit_logs', 'created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');

        // Clean up old search analytics (keep last 30 days)
        $this->db->delete('search_analytics', 'last_searched < DATE_SUB(NOW(), INTERVAL 30 DAY)');

        // Clean up expired user sessions
        $this->db->delete('user_sessions', 'expires_at < NOW()');

        // Archive old transaction data (keep last 2 years in main table)
        $this->archiveOldTransactions();
    }

    private function cleanupOldCacheFiles() {
        $cache_dir = $this->config['path'];

        if (!file_exists($cache_dir)) {
            return;
        }

        $files = scandir($cache_dir);
        $now = time();
        $max_age = $this->config['ttl'] * 2; // Remove files older than 2x TTL

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $file_path = $cache_dir . $file;
            if (is_file($file_path) && ($now - filemtime($file_path)) > $max_age) {
                unlink($file_path);
            }
        }
    }

    private function archiveOldTransactions() {
        // Move transactions older than 2 years to archive table
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-2 years'));

        // Create archive table if it doesn't exist
        $this->db->query("
            CREATE TABLE IF NOT EXISTS file_purchases_archive LIKE file_purchases
        ");

        // Move old records
        $this->db->query("
            INSERT INTO file_purchases_archive
            SELECT * FROM file_purchases
            WHERE purchase_date < ?
        ", [$cutoff_date]);

        // Remove from main table
        $this->db->delete('file_purchases', 'purchase_date < ?', [$cutoff_date]);
    }

    public function lazyLoadImages() {
        // Return JavaScript for lazy loading images
        return "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const lazyImages = document.querySelectorAll('img[data-src]');

                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });

                lazyImages.forEach(img => imageObserver.observe(img));
            });
        </script>";
    }

    public function compressOutput($buffer) {
        if (config('app.environment') === 'production') {
            // Remove comments
            $buffer = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $buffer);

            // Remove whitespace between tags
            $buffer = preg_replace('/>\s+</', '><', $buffer);

            // Remove whitespace at the beginning and end of lines
            $buffer = preg_replace('/\s+$/m', '', $buffer);
        }

        return $buffer;
    }

    public function getPerformanceMetrics() {
        return [
            'cache_enabled' => $this->config['enabled'],
            'cache_driver' => $this->config['driver'],
            'cache_size' => $this->getCacheSize(),
            'database_queries' => count($this->db->getQueryLog()),
            'average_query_time' => $this->getAverageQueryTime(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ];
    }

    private function getCacheSize() {
        $cache_dir = $this->config['path'];

        if (!file_exists($cache_dir)) {
            return 0;
        }

        $size = 0;
        $files = scandir($cache_dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $file_path = $cache_dir . $file;
            if (is_file($file_path)) {
                $size += filesize($file_path);
            }
        }

        return $size;
    }

    private function getAverageQueryTime() {
        $queries = $this->db->getQueryLog();

        if (empty($queries)) {
            return 0;
        }

        $total_time = array_sum(array_column($queries, 'time'));
        return $total_time / count($queries);
    }

    private function ensureLogDirectory() {
        $log_dir = __DIR__ . '/logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }

    public function preloadCriticalResources() {
        $critical_css = [
            '/css/enhanced-ui.css',
            '/css/smart-card.css'
        ];

        $critical_js = [
            '/js/enhanced-ui.js'
        ];

        $preload_links = '';

        foreach ($critical_css as $css) {
            $preload_links .= '<link rel="preload" href="' . $css . '" as="style">' . "\n";
        }

        foreach ($critical_js as $js) {
            $preload_links .= '<link rel="preload" href="' . $js . '" as="script">' . "\n";
        }

        return $preload_links;
    }

    public function generateSitemap() {
        $base_url = config('app.url');

        // Get all public files
        $files = $this->db->select("
            SELECT id, updated_at FROM shared_files
            WHERE is_available = 1
            ORDER BY updated_at DESC
        ");

        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Add homepage
        $sitemap .= '  <url>' . "\n";
        $sitemap .= '    <loc>' . $base_url . '/</loc>' . "\n";
        $sitemap .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
        $sitemap .= '    <changefreq>daily</changefreq>' . "\n";
        $sitemap .= '    <priority>1.0</priority>' . "\n";
        $sitemap .= '  </url>' . "\n";

        // Add file pages
        foreach ($files as $file) {
            $sitemap .= '  <url>' . "\n";
            $sitemap .= '    <loc>' . $base_url . '/file.php?id=' . $file['id'] . '</loc>' . "\n";
            $sitemap .= '    <lastmod>' . date('Y-m-d', strtotime($file['updated_at'])) . '</lastmod>' . "\n";
            $sitemap .= '    <changefreq>weekly</changefreq>' . "\n";
            $sitemap .= '    <priority>0.8</priority>' . "\n";
            $sitemap .= '  </url>' . "\n";
        }

        $sitemap .= '</urlset>';

        return $sitemap;
    }
}

// Cache interface
interface CacheInterface {
    public function get($key, $default = null);
    public function set($key, $value, $ttl = null);
    public function has($key);
    public function delete($key);
    public function clear();
    public function deleteByPattern($pattern);
}

// File-based cache implementation
class FileCache implements CacheInterface {
    private $cache_dir;
    private $default_ttl;

    public function __construct() {
        $this->cache_dir = config('cache.path');
        $this->default_ttl = config('cache.ttl');
        $this->ensureCacheDirectory();
    }

    public function get($key, $default = null) {
        $file = $this->getCacheFile($key);

        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));

        if ($data['expires'] < time()) {
            unlink($file);
            return $default;
        }

        return $data['value'];
    }

    public function set($key, $value, $ttl = null) {
        $file = $this->getCacheFile($key);
        $ttl = $ttl ?: $this->default_ttl;

        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];

        file_put_contents($file, serialize($data), LOCK_EX);
    }

    public function has($key) {
        return $this->get($key) !== null;
    }

    public function delete($key) {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function clear() {
        $files = glob($this->cache_dir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function deleteByPattern($pattern) {
        $files = glob($this->cache_dir . $pattern . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    private function getCacheFile($key) {
        $filename = md5($key) . '.cache';
        return $this->cache_dir . $filename;
    }

    private function ensureCacheDirectory() {
        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
}

// Redis cache implementation (if available)
class RedisCache implements CacheInterface {
    private $redis;

    public function __construct() {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    public function get($key, $default = null) {
        $value = $this->redis->get($key);
        return $value !== false ? unserialize($value) : $default;
    }

    public function set($key, $value, $ttl = null) {
        $serialized = serialize($value);
        if ($ttl) {
            $this->redis->setex($key, $ttl, $serialized);
        } else {
            $this->redis->set($key, $serialized);
        }
    }

    public function has($key) {
        return $this->redis->exists($key);
    }

    public function delete($key) {
        $this->redis->del($key);
    }

    public function clear() {
        $this->redis->flushAll();
    }

    public function deleteByPattern($pattern) {
        $keys = $this->redis->keys($pattern);
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }
}

// Memcached cache implementation (if available)
class MemcachedCache implements CacheInterface {
    private $memcached;

    public function __construct() {
        $this->memcached = new Memcached();
        $this->memcached->addServer('127.0.0.1', 11211);
    }

    public function get($key, $default = null) {
        $value = $this->memcached->get($key);
        return $value !== false ? $value : $default;
    }

    public function set($key, $value, $ttl = null) {
        $this->memcached->set($key, $value, $ttl ?: 3600);
    }

    public function has($key) {
        $this->memcached->get($key);
        return $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND;
    }

    public function delete($key) {
        $this->memcached->delete($key);
    }

    public function clear() {
        $this->memcached->flush();
    }

    public function deleteByPattern($pattern) {
        // Memcached doesn't support pattern deletion easily
        // This would need a more sophisticated implementation
    }
}

// Global helper functions
function cache($key, $value = null, $ttl = null) {
    static $optimizer;

    if (!$optimizer) {
        $optimizer = new PerformanceOptimizer();
    }

    if ($value === null) {
        return $optimizer->getCachedData($key);
    } else {
        return $optimizer->cacheData($key, $value, $ttl);
    }
}

function cachedQuery($key, $query, $params = [], $ttl = null) {
    static $optimizer;

    if (!$optimizer) {
        $optimizer = new PerformanceOptimizer();
    }

    return $optimizer->cacheQuery($key, $query, $params, $ttl);
}

function invalidateCache($pattern = null) {
    static $optimizer;

    if (!$optimizer) {
        $optimizer = new PerformanceOptimizer();
    }

    $optimizer->invalidateCache($pattern);
}

function getPerformanceMetrics() {
    static $optimizer;

    if (!$optimizer) {
        $optimizer = new PerformanceOptimizer();
    }

    return $optimizer->getPerformanceMetrics();
}
