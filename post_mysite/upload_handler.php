<?php
/**
 * Enhanced File Sharing System - Upload Handler
 * Handles file uploads with improved validation and processing
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/File.php';
require_once __DIR__ . '/classes/FileUpload.php';

// Start session and check authentication
require_once __DIR__ . '/includes/session.php';
requireLogin();

header('Content-Type: application/json');

try {
    $user_model = new User();
    $username = $_SESSION['username'];

    // Verify user exists
    $user = $user_model->findByUsername($username);
    if (!$user) {
        throw new Exception('User not found');
    }

    $upload_handler = new FileUpload();

    // Handle different upload types
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'upload_file':
                    handleFileUpload($upload_handler, $username);
                    break;

                case 'upload_chunk':
                    handleChunkUpload($upload_handler, $username);
                    break;

                case 'finalize_upload':
                    handleFinalizeUpload($upload_handler, $username);
                    break;

                case 'cancel_upload':
                    handleCancelUpload($upload_handler, $username);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } else {
            handleFileUpload($upload_handler, $username);
        }
    } else {
        throw new Exception('Invalid request method');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'errors' => [$e->getMessage()]
    ]);
}

function handleFileUpload($upload_handler, $username) {
    // Validate required fields
    $required_fields = ['filename', 'price'];
    $file_data = [];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            throw new Exception("Field '{$field}' is required");
        }
        $file_data[$field] = $_POST[$field];
    }

    // Optional fields
    $optional_fields = ['description', 'preview_type', 'preview_text', 'preview_image', 'category_id', 'tags'];
    foreach ($optional_fields as $field) {
        $file_data[$field] = $_POST[$field] ?? null;
    }

    // Handle file upload
    $result = $upload_handler->handleUpload($username, $file_data);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully and is pending review',
            'file_id' => $result['file_id'],
            'filename' => $result['filename'],
            'warnings' => $result['warnings'] ?? []
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Upload failed',
            'errors' => $result['errors']
        ]);
    }
}

function handleChunkUpload($upload_handler, $username) {
    // Chunked upload implementation for large files
    $chunk_index = (int)$_POST['chunk_index'];
    $total_chunks = (int)$_POST['total_chunks'];
    $upload_id = $_POST['upload_id'];
    $file_name = $_POST['file_name'];

    if (!$upload_id) {
        $upload_id = uniqid('upload_');
    }

    // Initialize upload session if not exists
    if (!isset($_SESSION['uploads'][$upload_id])) {
        $_SESSION['uploads'][$upload_id] = [
            'file_name' => $file_name,
            'total_chunks' => $total_chunks,
            'received_chunks' => 0,
            'chunks' => [],
            'start_time' => time()
        ];
    }

    $upload_session = &$_SESSION['uploads'][$upload_id];

    // Save chunk
    $chunk_file = $_FILES['chunk']['tmp_name'];
    $chunk_path = $upload_handler->getTempPath() . $upload_id . '_chunk_' . $chunk_index;

    if (!move_uploaded_file($chunk_file, $chunk_path)) {
        throw new Exception('Failed to save chunk');
    }

    $upload_session['chunks'][$chunk_index] = $chunk_path;
    $upload_session['received_chunks']++;

    // Check if all chunks received
    if ($upload_session['received_chunks'] === $total_chunks) {
        // Assemble file
        $final_file_path = $upload_handler->getUploadPath() . uniqid() . '_' . $file_name;

        $final_file = fopen($final_file_path, 'wb');

        for ($i = 0; $i < $total_chunks; $i++) {
            $chunk_path = $upload_session['chunks'][$i];
            $chunk_content = file_get_contents($chunk_path);
            fwrite($final_file, $chunk_content);
            unlink($chunk_path); // Clean up chunk
        }

        fclose($final_file);

        // Update session for finalization
        $upload_session['final_file_path'] = $final_file_path;
        $upload_session['status'] = 'completed';

        echo json_encode([
            'success' => true,
            'status' => 'completed',
            'upload_id' => $upload_id,
            'message' => 'All chunks received. Ready for finalization.'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'status' => 'chunk_received',
            'upload_id' => $upload_id,
            'received_chunks' => $upload_session['received_chunks'],
            'total_chunks' => $total_chunks,
            'message' => "Chunk {$chunk_index} received. {$upload_session['received_chunks']}/{$total_chunks} chunks received."
        ]);
    }
}

function handleFinalizeUpload($upload_handler, $username) {
    $upload_id = $_POST['upload_id'];

    if (!isset($_SESSION['uploads'][$upload_id])) {
        throw new Exception('Upload session not found');
    }

    $upload_session = $_SESSION['uploads'][$upload_id];

    if ($upload_session['status'] !== 'completed') {
        throw new Exception('Upload not completed');
    }

    // Get file data
    $file_data = $_POST['file_data'];

    // Create a temporary file object for processing
    $temp_file = [
        'name' => $upload_session['file_name'],
        'tmp_name' => $upload_session['final_file_path'],
        'size' => filesize($upload_session['final_file_path']),
        'error' => UPLOAD_ERR_OK
    ];

    // Process the assembled file
    $result = $upload_handler->handleUpload($username, $file_data);

    // Clean up session
    unset($_SESSION['uploads'][$upload_id]);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'file_id' => $result['file_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Upload finalization failed',
            'errors' => $result['errors']
        ]);
    }
}

function handleCancelUpload($upload_handler, $username) {
    $upload_id = $_POST['upload_id'];

    if (isset($_SESSION['uploads'][$upload_id])) {
        $upload_session = $_SESSION['uploads'][$upload_id];

        // Clean up chunks
        foreach ($upload_session['chunks'] as $chunk_path) {
            if (file_exists($chunk_path)) {
                unlink($chunk_path);
            }
        }

        // Clean up final file if exists
        if (isset($upload_session['final_file_path']) && file_exists($upload_session['final_file_path'])) {
            unlink($upload_session['final_file_path']);
        }

        unset($_SESSION['uploads'][$upload_id]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Upload cancelled successfully'
    ]);
}
