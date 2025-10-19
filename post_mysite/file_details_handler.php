<?php
/**
 * Enhanced File Sharing System - File Details Handler
 * Provides comprehensive file details and metadata
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/FileDetails.php';

// Start session for user authentication
require_once __DIR__ . '/includes/session.php';

header('Content-Type: application/json');

try {
    $file_details = new FileDetails();

    // Handle different file details actions
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'get_details':
                    handleGetDetails($file_details);
                    break;

                case 'get_metadata':
                    handleGetMetadata($file_details);
                    break;

                case 'get_activity':
                    handleGetActivity($file_details);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } else {
            handleGetDetails($file_details);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_metadata':
                    handleUpdateMetadata($file_details);
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

function handleGetDetails($file_details) {
    $file_id = (int)($_GET['file_id'] ?? 0);

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    $user_id = isset($_SESSION['username']) ? $_SESSION['username'] : null;

    $result = $file_details->getDetailedFileInfo($file_id, $user_id);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(404);
        echo json_encode($result);
    }
}

function handleGetMetadata($file_details) {
    $file_id = (int)($_GET['file_id'] ?? 0);

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    $metadata = $file_details->getFileMetadata($file_id);

    echo json_encode([
        'success' => true,
        'metadata' => $metadata
    ]);
}

function handleGetActivity($file_details) {
    $file_id = (int)($_GET['file_id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 50);

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    $activity = $file_details->getFileActivity($file_id, $limit);

    echo json_encode([
        'success' => true,
        'activity' => $activity
    ]);
}

function handleUpdateMetadata($file_details) {
    $file_id = (int)($_POST['file_id'] ?? 0);
    $metadata = $_POST['metadata'] ?? [];

    if (!$file_id) {
        throw new Exception('File ID is required');
    }

    // Check if user is the owner
    if (!isset($_SESSION['username'])) {
        throw new Exception('Authentication required');
    }

    $file_model = new File();
    $file = $file_model->find($file_id);

    if (!$file || $file['original_owner_id'] !== $_SESSION['username']) {
        throw new Exception('Permission denied');
    }

    $result = $file_details->updateFileMetadata($file_id, $metadata);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Metadata updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update metadata');
    }
}
