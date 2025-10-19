<?php
/**
 * Enhanced File Sharing System - Preview Handler
 * Handles file preview requests with improved caching and generation
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/File.php';
require_once __DIR__ . '/classes/FilePreview.php';

// Start session for user authentication
require_once __DIR__ . '/includes/session.php';

header('Content-Type: application/json');

try {
    $preview_handler = new FilePreview();

    // Handle different preview actions
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'preview':
                    handlePreviewRequest($preview_handler);
                    break;

                case 'clear_cache':
                    handleClearCache($preview_handler);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } else {
            handlePreviewRequest($preview_handler);
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

function handlePreviewRequest($preview_handler) {
    $file_id = (int)($_GET['file_id'] ?? 0);

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    $user_id = null;
    if (isset($_SESSION['username'])) {
        $user_id = $_SESSION['username'];
    }

    $result = $preview_handler->getPreview($file_id, $user_id);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(403);
        echo json_encode($result);
    }
}

function handleClearCache($preview_handler) {
    // Only admins can clear cache
    if (!isset($_SESSION['username'])) {
        throw new Exception('Authentication required');
    }

    $file_id = isset($_GET['file_id']) ? (int)$_GET['file_id'] : null;

    $preview_handler->clearCache($file_id);

    echo json_encode([
        'success' => true,
        'message' => 'Cache cleared successfully'
    ]);
}
