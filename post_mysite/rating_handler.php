<?php
/**
 * Enhanced File Sharing System - Rating Handler
 * Processes rating and comment requests
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/classes/RatingSystem.php';

// Start session for user authentication
require_once __DIR__ . '/includes/session.php';
requireLogin();

header('Content-Type: application/json');

try {
    $rating_system = new RatingSystem();
    $username = $_SESSION['username'];

    // Handle different rating actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_rating':
                    handleAddRating($rating_system, $username);
                    break;

                case 'add_comment':
                    handleAddComment($rating_system, $username);
                    break;

                case 'mark_helpful':
                    handleMarkHelpful($rating_system, $username);
                    break;

                case 'report_comment':
                    handleReportComment($rating_system, $username);
                    break;

                case 'delete_rating':
                    handleDeleteRating($rating_system, $username);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } else {
            throw new Exception('Action is required');
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'get_ratings':
                    handleGetRatings($rating_system);
                    break;

                case 'get_comments':
                    handleGetComments($rating_system);
                    break;

                case 'get_rating_stats':
                    handleGetRatingStats($rating_system);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } else {
            throw new Exception('Action is required');
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

function handleAddRating($rating_system, $username) {
    $file_id = (int)($_POST['file_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = $_POST['comment'] ?? null;

    if (!$file_id || !$rating) {
        throw new Exception('File ID and rating are required');
    }

    $result = $rating_system->addRating($file_id, $username, $rating, $comment);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
}

function handleAddComment($rating_system, $username) {
    $file_id = (int)($_POST['file_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $parent_id = (int)($_POST['parent_id'] ?? 0);

    if (!$file_id || empty($comment)) {
        throw new Exception('File ID and comment are required');
    }

    $result = $rating_system->addComment($file_id, $username, $comment, $parent_id ?: null);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
}

function handleMarkHelpful($rating_system, $username) {
    $comment_id = (int)($_POST['comment_id'] ?? 0);

    if (!$comment_id) {
        throw new Exception('Comment ID is required');
    }

    $result = $rating_system->markCommentHelpful($comment_id, $username);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
}

function handleReportComment($rating_system, $username) {
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    $reason = $_POST['reason'] ?? '';

    if (!$comment_id || empty($reason)) {
        throw new Exception('Comment ID and reason are required');
    }

    $result = $rating_system->reportComment($comment_id, $username, $reason);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
}

function handleDeleteRating($rating_system, $username) {
    $file_id = (int)($_POST['file_id'] ?? 0);

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    $result = $rating_system->deleteRating($file_id, $username);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
}

function handleGetRatings($rating_system) {
    $file_id = (int)($_GET['file_id'] ?? 0);

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    $ratings = $rating_system->getFileRatingSummary($file_id);

    echo json_encode([
        'success' => true,
        'ratings' => $ratings
    ]);
}

function handleGetComments($rating_system) {
    $file_id = (int)($_GET['file_id'] ?? 0);
    $page = (int)($_GET['page'] ?? 1);
    $per_page = (int)($_GET['per_page'] ?? 20);

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    $result = $rating_system->getFileComments($file_id, $page, $per_page);

    echo json_encode([
        'success' => true,
        'comments' => $result['comments'],
        'pagination' => $result['pagination']
    ]);
}

function handleGetRatingStats($rating_system) {
    $file_id = (int)($_GET['file_id'] ?? 0);

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    $stats = $rating_system->getRatingStats($file_id);

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}
