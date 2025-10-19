<?php
/**
 * Enhanced File Sharing System - Advanced Search and Filtering
 * Optimized search with caching and better performance
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/File.php';

class AdvancedSearch {
    private $db;
    private $file_model;
    private $cache_dir;
    private $cache_ttl;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->file_model = new File();
        $this->cache_dir = __DIR__ . '/cache/search/';
        $this->cache_ttl = config('cache.ttl');

        $this->ensureCacheDirectory();
    }

    public function search($criteria, $user_id = null) {
        // Generate cache key
        $cache_key = $this->generateCacheKey($criteria, $user_id);

        // Check cache first
        $cached_results = $this->getCachedResults($cache_key);
        if ($cached_results) {
            return $cached_results;
        }

        // Build search query
        $query = $this->buildSearchQuery($criteria, $user_id);
        $params = $this->buildSearchParams($criteria);

        // Execute search
        $results = $this->db->select($query, $params);

        // Process results
        $processed_results = $this->processSearchResults($results, $criteria);

        // Cache results
        $this->cacheResults($cache_key, $processed_results);

        return $processed_results;
    }

    public function getFilters() {
        return [
            'categories' => $this->getCategories(),
            'price_ranges' => $this->getPriceRanges(),
            'file_types' => $this->getFileTypes(),
            'sort_options' => $this->getSortOptions(),
            'date_ranges' => $this->getDateRanges()
        ];
    }

    public function getSuggestions($query, $limit = 10) {
        if (strlen($query) < 2) {
            return [];
        }

        $cache_key = 'suggestions_' . md5($query);
        $cached = $this->getCachedResults($cache_key);

        if ($cached) {
            return $cached;
        }

        // Get suggestions from different sources
        $suggestions = [];

        // Filename suggestions
        $filename_suggestions = $this->db->select("
            SELECT DISTINCT filename as suggestion, 'filename' as type
            FROM shared_files
            WHERE is_available = 1 AND filename LIKE ?
            ORDER BY filename
            LIMIT ?
        ", ['%' . $query . '%', $limit]);

        $suggestions = array_merge($suggestions, $filename_suggestions);

        // Description suggestions
        $description_suggestions = $this->db->select("
            SELECT DISTINCT description as suggestion, 'description' as type
            FROM shared_files
            WHERE is_available = 1 AND description LIKE ?
            ORDER BY description
            LIMIT ?
        ", ['%' . $query . '%', $limit]);

        $suggestions = array_merge($suggestions, $description_suggestions);

        // Tag suggestions
        $tag_suggestions = $this->db->select("
            SELECT DISTINCT name as suggestion, 'tag' as type
            FROM file_tags
            WHERE usage_count > 0 AND name LIKE ?
            ORDER BY usage_count DESC, name
            LIMIT ?
        ", ['%' . $query . '%', $limit]);

        $suggestions = array_merge($suggestions, $tag_suggestions);

        // Remove duplicates and limit
        $unique_suggestions = [];
        $seen = [];
        foreach ($suggestions as $suggestion) {
            if (!isset($seen[$suggestion['suggestion']])) {
                $unique_suggestions[] = $suggestion;
                $seen[$suggestion['suggestion']] = true;
            }
        }

        $result = array_slice($unique_suggestions, 0, $limit);

        $this->cacheResults($cache_key, $result);

        return $result;
    }

    public function getPopularSearches($limit = 20) {
        return $this->db->select("
            SELECT
                search_term,
                search_count,
                last_searched
            FROM search_analytics
            WHERE last_searched >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY search_count DESC
            LIMIT ?
        ", [$limit]);
    }

    public function recordSearch($query, $result_count, $user_id = null) {
        if (empty(trim($query))) {
            return;
        }

        $this->db->query("
            INSERT INTO search_analytics (search_term, search_count, last_searched, user_id)
            VALUES (?, 1, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                search_count = search_count + 1,
                last_searched = NOW()
        ", [$query, $user_id]);
    }

    private function buildSearchQuery($criteria, $user_id) {
        $where_conditions = ["f.is_available = 1"];
        $joins = [];
        $group_by = "GROUP BY f.id";
        $order_by = "ORDER BY ";
        $having_conditions = [];

        // Exclude user's own files if specified
        if (isset($criteria['exclude_user']) && $criteria['exclude_user'] && $user_id) {
            $where_conditions[] = "f.original_owner_id != ?";
        }

        // Search term
        if (!empty($criteria['query'])) {
            $search_term = '%' . $criteria['query'] . '%';
            $where_conditions[] = "(f.filename LIKE ? OR f.description LIKE ? OR f.tags LIKE ?)";
        }

        // Category filter
        if (!empty($criteria['category_id'])) {
            $where_conditions[] = "f.category_id = ?";
        }

        // Price range
        if (isset($criteria['min_price']) || isset($criteria['max_price'])) {
            if (isset($criteria['min_price'])) {
                $where_conditions[] = "f.price >= ?";
            }
            if (isset($criteria['max_price'])) {
                $where_conditions[] = "f.price <= ?";
            }
        }

        // Rating filter
        if (isset($criteria['min_rating'])) {
            $having_conditions[] = "AVG(r.rating) >= ?";
        }

        // Date range
        if (!empty($criteria['date_range'])) {
            switch ($criteria['date_range']) {
                case 'today':
                    $where_conditions[] = "DATE(f.created_at) = CURDATE()";
                    break;
                case 'week':
                    $where_conditions[] = "f.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $where_conditions[] = "f.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                case 'year':
                    $where_conditions[] = "f.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
                    break;
            }
        }

        // File type filter
        if (!empty($criteria['file_type'])) {
            $where_conditions[] = "f.file_extension = ?";
        }

        // Featured files only
        if (isset($criteria['featured_only']) && $criteria['featured_only']) {
            $where_conditions[] = "f.is_featured = 1";
        }

        // Build JOINs
        if (isset($criteria['min_rating'])) {
            $joins[] = "LEFT JOIN file_ratings r ON f.id = r.file_id";
        }

        // Build ORDER BY clause
        $sort_by = $criteria['sort_by'] ?? 'newest';
        switch ($sort_by) {
            case 'newest':
                $order_by .= "f.created_at DESC";
                break;
            case 'oldest':
                $order_by .= "f.created_at ASC";
                break;
            case 'price_low':
                $order_by .= "f.price ASC";
                break;
            case 'price_high':
                $order_by .= "f.price DESC";
                break;
            case 'popular':
                $order_by .= "f.download_count DESC, f.view_count DESC";
                break;
            case 'rating':
                $order_by .= "f.average_rating DESC, f.rating_count DESC";
                break;
            case 'name':
                $order_by .= "f.filename ASC";
                break;
            default:
                $order_by .= "f.created_at DESC";
        }

        // Build complete query
        $sql = "SELECT f.*, u.username as owner_username, c.name as category_name";
        $sql .= " FROM shared_files f";
        $sql .= " LEFT JOIN login u ON f.original_owner_id = u.username";
        $sql .= " LEFT JOIN file_categories c ON f.category_id = c.id";

        if (!empty($joins)) {
            $sql .= " " . implode(" ", $joins);
        }

        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }

        if (!empty($having_conditions)) {
            $sql .= " HAVING " . implode(" AND ", $having_conditions);
        }

        if (!empty($group_by)) {
            $sql .= " " . $group_by;
        }

        if (!empty($order_by)) {
            $sql .= " " . $order_by;
        }

        // Add pagination
        if (isset($criteria['limit'])) {
            $sql .= " LIMIT " . (int)$criteria['limit'];
        }

        if (isset($criteria['offset'])) {
            $sql .= " OFFSET " . (int)$criteria['offset'];
        }

        return $sql;
    }

    private function buildSearchParams($criteria) {
        $params = [];

        if (isset($criteria['exclude_user']) && $criteria['exclude_user'] && isset($criteria['user_id'])) {
            $params[] = $criteria['user_id'];
        }

        if (!empty($criteria['query'])) {
            $search_term = '%' . $criteria['query'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if (!empty($criteria['category_id'])) {
            $params[] = $criteria['category_id'];
        }

        if (isset($criteria['min_price'])) {
            $params[] = $criteria['min_price'];
        }

        if (isset($criteria['max_price'])) {
            $params[] = $criteria['max_price'];
        }

        if (isset($criteria['min_rating'])) {
            $params[] = $criteria['min_rating'];
        }

        if (!empty($criteria['file_type'])) {
            $params[] = $criteria['file_type'];
        }

        return $params;
    }

    private function processSearchResults($results, $criteria) {
        $processed = [];

        foreach ($results as $result) {
            $processed[] = [
                'id' => $result['id'],
                'filename' => $result['filename'],
                'description' => $result['description'],
                'file_size' => $result['file_size'],
                'file_extension' => $result['file_extension'],
                'mime_type' => $result['mime_type'],
                'price' => $result['price'],
                'owner_username' => $result['owner_username'],
                'category_name' => $result['category_name'],
                'created_at' => $result['created_at'],
                'download_count' => $result['download_count'],
                'view_count' => $result['view_count'],
                'average_rating' => $result['average_rating'],
                'rating_count' => $result['rating_count'],
                'is_featured' => $result['is_featured'],
                'preview_type' => $result['preview_type'],
                'preview_text' => $result['preview_text'],
                'thumbnail_path' => $result['thumbnail_path'] ?? null,
                // Add relevance score if search query exists
                'relevance_score' => $this->calculateRelevance($result, $criteria)
            ];
        }

        return $processed;
    }

    private function calculateRelevance($result, $criteria) {
        if (empty($criteria['query'])) {
            return 1;
        }

        $query = strtolower($criteria['query']);
        $score = 0;

        // Title match gets highest score
        if (stripos($result['filename'], $query) !== false) {
            $score += 10;
        }

        // Description match
        if (stripos($result['description'], $query) !== false) {
            $score += 5;
        }

        // Tag match
        if (stripos($result['tags'], $query) !== false) {
            $score += 3;
        }

        // Boost score based on rating and popularity
        if ($result['average_rating'] >= 4.0) {
            $score += 2;
        }

        if ($result['download_count'] > 10) {
            $score += 1;
        }

        return $score;
    }

    private function generateCacheKey($criteria, $user_id) {
        $key_data = $criteria;
        $key_data['user_id'] = $user_id;
        $key_data['timestamp'] = floor(time() / $this->cache_ttl); // Cache per time window

        return 'search_' . md5(serialize($key_data));
    }

    private function getCachedResults($cache_key) {
        $cache_file = $this->cache_dir . $cache_key . '.json';

        if (file_exists($cache_file)) {
            $cache_time = filemtime($cache_file);

            if ((time() - $cache_time) < $this->cache_ttl) {
                return json_decode(file_get_contents($cache_file), true);
            } else {
                unlink($cache_file); // Remove expired cache
            }
        }

        return false;
    }

    private function cacheResults($cache_key, $results) {
        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }

        $cache_file = $this->cache_dir . $cache_key . '.json';
        file_put_contents($cache_file, json_encode($results));
    }

    private function ensureCacheDirectory() {
        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }

    private function getCategories() {
        return $this->db->select(
            "SELECT id, name, slug, icon, color FROM file_categories WHERE is_active = 1 ORDER BY sort_order"
        );
    }

    private function getPriceRanges() {
        return [
            ['min' => 0, 'max' => 50, 'label' => '0 - 50 Points'],
            ['min' => 50, 'max' => 100, 'label' => '50 - 100 Points'],
            ['min' => 100, 'max' => 200, 'label' => '100 - 200 Points'],
            ['min' => 200, 'max' => null, 'label' => '200+ Points']
        ];
    }

    private function getFileTypes() {
        return $this->db->select("
            SELECT file_extension as extension, COUNT(*) as count
            FROM shared_files
            WHERE is_available = 1
            GROUP BY file_extension
            ORDER BY count DESC
            LIMIT 20
        ");
    }

    private function getSortOptions() {
        return [
            ['value' => 'newest', 'label' => 'Newest First'],
            ['value' => 'oldest', 'label' => 'Oldest First'],
            ['value' => 'price_low', 'label' => 'Price: Low to High'],
            ['value' => 'price_high', 'label' => 'Price: High to Low'],
            ['value' => 'popular', 'label' => 'Most Popular'],
            ['value' => 'rating', 'label' => 'Highest Rated'],
            ['value' => 'name', 'label' => 'Name: A-Z']
        ];
    }

    private function getDateRanges() {
        return [
            ['value' => 'today', 'label' => 'Today'],
            ['value' => 'week', 'label' => 'This Week'],
            ['value' => 'month', 'label' => 'This Month'],
            ['value' => 'year', 'label' => 'This Year']
        ];
    }

    public function clearCache() {
        $files = glob($this->cache_dir . '*.json');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function getSearchAnalytics($days = 30) {
        return $this->db->select("
            SELECT
                search_term,
                SUM(search_count) as total_searches,
                MAX(last_searched) as last_search,
                COUNT(DISTINCT user_id) as unique_users
            FROM search_analytics
            WHERE last_searched >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY search_term
            ORDER BY total_searches DESC
            LIMIT 50
        ", [$days]);
    }

    public function optimizeSearchIndex() {
        // Create or update full-text search index
        $this->db->query("
            ALTER TABLE shared_files
            ADD FULLTEXT INDEX idx_search (filename, description, tags)
        ");

        // Optimize table
        $this->db->query("OPTIMIZE TABLE shared_files");
    }

    public function getTrendingSearches($limit = 10) {
        return $this->db->select("
            SELECT
                search_term,
                search_count as count,
                last_searched
            FROM search_analytics
            WHERE last_searched >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY search_count DESC
            LIMIT ?
        ", [$limit]);
    }
}
