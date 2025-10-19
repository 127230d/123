<?php
/**
 * Enhanced File Sharing System - Search Handler
 * Handles search requests with advanced filtering and caching
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/AdvancedSearch.php';

// Start session for user authentication
require_once __DIR__ . '/includes/session.php';

header('Content-Type: application/json');

try {
    $search = new AdvancedSearch();
    $user_id = isset($_SESSION['username']) ? $_SESSION['username'] : null;

    // Handle different search actions
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'search':
                    handleSearch($search, $user_id);
                    break;

                case 'suggestions':
                    handleSuggestions($search);
                    break;

                case 'filters':
                    handleFilters($search);
                    break;

                case 'popular_searches':
                    handlePopularSearches($search);
                    break;

                case 'trending_searches':
                    handleTrendingSearches($search);
                    break;

                case 'clear_cache':
                    handleClearCache($search);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } else {
            handleSearch($search, $user_id);
        }
    } else {
        throw new Exception('Invalid request method');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleSearch($search, $user_id) {
    // Build search criteria from request parameters
    $criteria = [
        'query' => $_GET['q'] ?? $_GET['query'] ?? '',
        'category_id' => (int)($_GET['category_id'] ?? 0) ?: null,
        'min_price' => isset($_GET['min_price']) ? (float)$_GET['min_price'] : null,
        'max_price' => isset($_GET['max_price']) ? (float)$_GET['max_price'] : null,
        'min_rating' => isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : null,
        'date_range' => $_GET['date_range'] ?? null,
        'file_type' => $_GET['file_type'] ?? null,
        'featured_only' => isset($_GET['featured_only']) ? $_GET['featured_only'] === 'true' : false,
        'sort_by' => $_GET['sort_by'] ?? 'newest',
        'limit' => (int)($_GET['limit'] ?? 20),
        'offset' => (int)($_GET['offset'] ?? 0),
        'exclude_user' => isset($_GET['exclude_user']) ? $_GET['exclude_user'] === 'true' : true,
        'user_id' => $user_id
    ];

    // Execute search
    $results = $search->search($criteria, $user_id);

    // Record search for analytics
    if (!empty($criteria['query'])) {
        $search->recordSearch($criteria['query'], count($results), $user_id);
    }

    echo json_encode([
        'success' => true,
        'results' => $results,
        'total' => count($results), // This should be improved with actual count query
        'criteria' => $criteria,
        'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
    ]);
}

function handleSuggestions($search) {
    $query = $_GET['q'] ?? $_GET['query'] ?? '';
    $limit = (int)($_GET['limit'] ?? 10);

    $suggestions = $search->getSuggestions($query, $limit);

    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions,
        'query' => $query
    ]);
}

function handleFilters($search) {
    $filters = $search->getFilters();

    echo json_encode([
        'success' => true,
        'filters' => $filters
    ]);
}

function handlePopularSearches($search) {
    $limit = (int)($_GET['limit'] ?? 20);

    $searches = $search->getPopularSearches($limit);

    echo json_encode([
        'success' => true,
        'searches' => $searches
    ]);
}

function handleTrendingSearches($search) {
    $limit = (int)($_GET['limit'] ?? 10);

    $searches = $search->getTrendingSearches($limit);

    echo json_encode([
        'success' => true,
        'searches' => $searches
    ]);
}

function handleClearCache($search) {
    // Only admins should be able to clear cache
    if (!isset($_SESSION['username'])) {
        throw new Exception('Authentication required');
    }

    $search->clearCache();

    echo json_encode([
        'success' => true,
        'message' => 'Search cache cleared successfully'
    ]);
}
