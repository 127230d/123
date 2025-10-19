<?php
/**
 * Enhanced File Sharing System - Shared File Handler
 * Handles access to shared files
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/FileSharing.php';
require_once __DIR__ . '/classes/FilePreview.php';

header('Content-Type: application/json');

try {
    $file_sharing = new FileSharing();
    $file_preview = new FilePreview();

    // Handle different shared file actions
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'preview':
                    handleSharedPreview($file_sharing);
                    break;

                case 'download':
                    handleSharedDownload($file_sharing);
                    break;

                case 'embed':
                    handleSharedEmbed($file_sharing);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } else {
            handleSharedPreview($file_sharing);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle password submission for protected shares
        if (isset($_POST['password']) && isset($_GET['token'])) {
            handleSharedPreview($file_sharing, $_POST['password']);
        } else {
            throw new Exception('Password required for protected share');
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

function handleSharedPreview($file_sharing, $password = null) {
    $share_token = $_GET['token'] ?? '';

    if (!$share_token) {
        throw new Exception('Share token is required');
    }

    $result = $file_sharing->accessSharedFile($share_token, $password);

    if ($result['success']) {
        // Check if this is an embed request
        $is_embed = isset($_GET['embed']) && $_GET['embed'] == '1';

        if ($is_embed) {
            // Return embed HTML
            echo generateEmbedHTML($result['file'], $result['share_info']);
        } else {
            // Return JSON data
            http_response_code(200);
            echo json_encode($result);
        }
    } else {
        if (isset($result['requires_password']) && $result['requires_password']) {
            // Return password prompt
            echo generatePasswordPrompt($share_token);
        } else {
            http_response_code(403);
            echo json_encode($result);
        }
    }
}

function handleSharedDownload($file_sharing) {
    $share_token = $_GET['token'] ?? '';
    $password = $_POST['password'] ?? null;

    if (!$share_token) {
        throw new Exception('Share token is required');
    }

    $result = $file_sharing->downloadSharedFile($share_token, $password);

    if ($result['success']) {
        // Serve the file for download
        serveFileDownload($result['file_path'], $result['filename'], $result['mime_type']);
    } else {
        if (isset($result['requires_password']) && $result['requires_password']) {
            echo generatePasswordPrompt($share_token, 'download');
        } else {
            http_response_code(403);
            echo json_encode($result);
        }
    }
}

function handleSharedEmbed($file_sharing) {
    $share_token = $_GET['token'] ?? '';

    if (!$share_token) {
        throw new Exception('Share token is required');
    }

    $result = $file_sharing->accessSharedFile($share_token);

    if ($result['success']) {
        echo generateEmbedHTML($result['file'], $result['share_info']);
    } else {
        http_response_code(403);
        echo json_encode($result);
    }
}

function generatePasswordPrompt($share_token, $action = 'preview') {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Protected Share - Password Required</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .password-container {
                background: white;
                border-radius: 10px;
                padding: 40px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 400px;
                width: 90%;
            }
            .password-icon {
                font-size: 3rem;
                color: #667eea;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 10px;
                font-size: 1.5rem;
            }
            p {
                color: #666;
                margin-bottom: 30px;
                line-height: 1.5;
            }
            .password-form {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            .password-input {
                padding: 12px 16px;
                border: 2px solid #e1e5e9;
                border-radius: 6px;
                font-size: 16px;
                transition: border-color 0.2s;
            }
            .password-input:focus {
                outline: none;
                border-color: #667eea;
            }
            .submit-btn {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-size: 16px;
                cursor: pointer;
                transition: transform 0.2s;
            }
            .submit-btn:hover {
                transform: translateY(-1px);
            }
        </style>
    </head>
    <body>
        <div class="password-container">
            <div class="password-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h1>Protected Share</h1>
            <p>This shared file is password protected. Please enter the password to continue.</p>

            <form class="password-form" method="POST" action="">
                <input type="hidden" name="token" value="' . htmlspecialchars($share_token) . '">
                <input type="hidden" name="action" value="' . htmlspecialchars($action) . '">
                <input type="password" name="password" class="password-input" placeholder="Enter password" required autofocus>
                <button type="submit" class="submit-btn">Access File</button>
            </form>
        </div>

        <script>
            // Auto-submit form when Enter is pressed
            document.querySelector(".password-input").addEventListener("keypress", function(e) {
                if (e.key === "Enter") {
                    e.target.form.submit();
                }
            });
        </script>
    </body>
    </html>';
}

function generateEmbedHTML($file, $share_info) {
    $preview_html = '';

    if ($share_info['can_preview']) {
        // Generate preview based on file type
        $preview_handler = new FilePreview();
        $preview_result = $preview_handler->getPreview($file['id']);

        if ($preview_result['success']) {
            $preview_html = $preview_result['preview'];
        }
    }

    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($file['filename']) . ' - Shared File</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                margin: 0;
                padding: 20px;
                background: #f8f9fa;
                color: #333;
            }
            .embed-container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .embed-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                text-align: center;
            }
            .embed-header h1 {
                margin: 0;
                font-size: 1.5rem;
                font-weight: 600;
            }
            .embed-header p {
                margin: 5px 0 0 0;
                opacity: 0.9;
            }
            .embed-content {
                padding: 20px;
            }
            .file-info {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
            }
            .file-icon {
                font-size: 2rem;
                margin-right: 15px;
                color: #667eea;
            }
            .file-details h2 {
                margin: 0 0 5px 0;
                font-size: 1.2rem;
            }
            .file-meta {
                color: #666;
                font-size: 0.9rem;
            }
            .preview-container {
                margin-top: 20px;
            }
            .action-buttons {
                margin-top: 20px;
                text-align: center;
            }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 6px;
                transition: transform 0.2s;
                margin: 0 5px;
            }
            .btn:hover {
                transform: translateY(-1px);
                color: white;
                text-decoration: none;
            }
            .btn-secondary {
                background: #6c757d;
            }
        </style>
    </head>
    <body>
        <div class="embed-container">
            <div class="embed-header">
                <h1>' . htmlspecialchars($file['filename']) . '</h1>
                <p>Shared File • ' . htmlspecialchars($file['owner_name']) . '</p>
            </div>

            <div class="embed-content">
                <div class="file-info">
                    <div class="file-icon">
                        <i class="' . getFileIcon($file['file_type']) . '"></i>
                    </div>
                    <div class="file-details">
                        <h2>' . htmlspecialchars($file['filename']) . '</h2>
                        <div class="file-meta">
                            <span>' . formatFileSize($file['file_size']) . ' • ' . htmlspecialchars($file['file_type']) . '</span>
                        </div>
                    </div>
                </div>

                ' . ($file['description'] ? '<p>' . htmlspecialchars($file['description']) . '</p>' : '') . '

                <div class="preview-container">
                    ' . $preview_html . '
                </div>

                <div class="action-buttons">
                    ' . ($share_info['can_download'] ? '<a href="download.php?id=' . $file['id'] . '" class="btn">Download File</a>' : '') . '
                    <a href="' . $_SERVER['REQUEST_URI'] . '" class="btn btn-secondary">View Details</a>
                </div>
            </div>
        </div>
    </body>
    </html>';

    function getFileIcon($file_type) {
        $icons = [
            'pdf' => 'fas fa-file-pdf',
            'doc' => 'fas fa-file-word',
            'docx' => 'fas fa-file-word',
            'txt' => 'fas fa-file-alt',
            'jpg' => 'fas fa-file-image',
            'png' => 'fas fa-file-image',
            'gif' => 'fas fa-file-image',
            'zip' => 'fas fa-file-archive',
            'rar' => 'fas fa-file-archive',
            'mp3' => 'fas fa-file-audio',
            'mp4' => 'fas fa-file-video',
        ];

        return $icons[$file_type] ?? 'fas fa-file';
    }

    function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}

function serveFileDownload($file_path, $filename, $mime_type) {
    if (!file_exists($file_path)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found']);
        return;
    }

    // Set headers for file download
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    // Read and output file
    readfile($file_path);
    exit;
}
