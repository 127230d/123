<?php
/**
 * Enhanced File Sharing System - File Upload Handler
 * Improved file upload with validation, security, and database integration
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/File.php';
require_once __DIR__ . '/models/User.php';

class FileUpload {
    private $config;
    private $db;
    private $file_model;
    private $user_model;
    private $errors = [];
    private $warnings = [];

    public function __construct() {
        $this->config = config('upload');
        $this->db = Database::getInstance();
        $this->file_model = new File();
        $this->user_model = new User();
    }

    public function handleUpload($username, $file_data) {
        // Validate input data
        if (!$this->validateUploadData($file_data)) {
            return ['success' => false, 'errors' => $this->errors];
        }

        // Check user limits
        if (!$this->checkUserLimits($username)) {
            return ['success' => false, 'errors' => $this->errors];
        }

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = 'No file uploaded or upload error occurred';
            return ['success' => false, 'errors' => $this->errors];
        }

        // Validate file
        if (!$this->validateFile($file)) {
            return ['success' => false, 'errors' => $this->errors];
        }

        // Process the upload
        $result = $this->processUpload($username, $file, $file_data);

        if ($result['success']) {
            // Log successful upload
            $this->logUpload($username, $result['file_id'], 'success');
        }

        return $result;
    }

    private function validateUploadData($data) {
        if (empty($data['filename'])) {
            $this->errors[] = 'File name is required';
            return false;
        }

        if (strlen($data['filename']) > 255) {
            $this->errors[] = 'File name is too long (max 255 characters)';
            return false;
        }

        if (!empty($data['description']) && strlen($data['description']) > config('limits.max_file_description_length')) {
            $this->errors[] = 'Description is too long';
            return false;
        }

        if (!empty($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
            $this->errors[] = 'Invalid price format';
            return false;
        }

        return true;
    }

    private function checkUserLimits($username) {
        $user_files_count = $this->file_model->count(['original_owner_id' => $username]);

        if ($user_files_count >= config('limits.max_files_per_user')) {
            $this->errors[] = 'You have reached the maximum number of files allowed';
            return false;
        }

        return true;
    }

    private function validateFile($file) {
        // Check file size
        if ($file['size'] > $this->config['max_file_size']) {
            $max_size_mb = round($this->config['max_file_size'] / 1024 / 1024, 1);
            $this->errors[] = "File size exceeds maximum allowed size ({$max_size_mb}MB)";
            return false;
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->config['allowed_extensions'])) {
            $this->errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $this->config['allowed_extensions']);
            return false;
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Validate MIME type matches extension
        if (!$this->isValidMimeType($extension, $mime_type)) {
            $this->errors[] = 'File type does not match file extension';
            return false;
        }

        // Security scan
        if (!$this->securityScan($file['tmp_name'])) {
            $this->errors[] = 'File failed security scan';
            return false;
        }

        return true;
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

    private function securityScan($file_path) {
        // Basic security checks
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }

        // Check for suspicious file signatures (basic check)
        $handle = fopen($file_path, 'rb');
        if (!$handle) return false;

        $header = fread($handle, 16);
        fclose($handle);

        // Check for common malicious signatures
        $malicious_signatures = [
            '4D5A', // Windows executable (MZ header)
            '7F454C46', // Linux ELF
            'CAFEBABE', // Java class file
            '504B0304', // ZIP file (should be allowed)
        ];

        foreach ($malicious_signatures as $signature) {
            if ($signature === '504B0304') continue; // Allow ZIP files

            if (strpos(bin2hex($header), $signature) === 0) {
                return false; // Potentially malicious file
            }
        }

        return true;
    }

    private function processUpload($username, $file, $file_data) {
        // Generate unique filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $unique_id = uniqid();
        $new_filename = $unique_id . '.' . $extension;

        // Create upload directories if they don't exist
        $this->createUploadDirectories();

        // Move uploaded file
        $upload_path = $this->config['upload_path'] . $new_filename;

        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            $this->errors[] = 'Failed to save uploaded file';
            return ['success' => false, 'errors' => $this->errors];
        }

        // Generate file hash
        $file_hash = $this->file_model->generateFileHash($upload_path);

        // Check for duplicate files
        $existing_file = $this->db->selectOne(
            "SELECT id FROM shared_files WHERE file_hash = ? AND original_owner_id = ?",
            [$file_hash, $username]
        );

        if ($existing_file) {
            // Remove uploaded file since it's a duplicate
            unlink($upload_path);
            $this->errors[] = 'This file has already been uploaded by you';
            return ['success' => false, 'errors' => $this->errors];
        }

        // Save to database
        $file_record = [
            'original_owner_id' => $username,
            'filename' => $file_data['filename'],
            'original_filename' => $file['name'],
            'file_path' => $new_filename,
            'file_size' => $file['size'],
            'file_extension' => $extension,
            'mime_type' => $this->file_model->getMimeType($upload_path),
            'file_hash' => $file_hash,
            'description' => $file_data['description'] ?? '',
            'preview_type' => $file_data['preview_type'] ?? 'text',
            'preview_text' => $file_data['preview_text'] ?? null,
            'preview_image' => $file_data['preview_image'] ?? null,
            'price' => $file_data['price'] ?? 0,
            'is_available' => 0, // Requires review/approval
            'category_id' => $file_data['category_id'] ?? null,
            'tags' => $file_data['tags'] ?? null,
            'seo_title' => $file_data['filename'],
            'seo_description' => substr($file_data['description'] ?? '', 0, 160),
        ];

        $file_id = $this->file_model->create($file_record);

        if (!$file_id) {
            // Clean up uploaded file if database insert failed
            unlink($upload_path);
            $this->errors[] = 'Failed to save file information to database';
            return ['success' => false, 'errors' => $this->errors];
        }

        // Process tags if provided
        if (!empty($file_data['tags'])) {
            $this->file_model->addTags($file_id, $file_data['tags']);
        }

        // Generate thumbnail if it's an image
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $this->generateThumbnail($upload_path, $file_id);
        }

        return [
            'success' => true,
            'file_id' => $file_id,
            'filename' => $file_data['filename'],
            'file_path' => $new_filename,
            'warnings' => $this->warnings
        ];
    }

    private function createUploadDirectories() {
        $directories = [
            $this->config['upload_path'],
            $this->config['temp_path'],
            $this->config['upload_path'] . 'thumbnails/',
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    private function generateThumbnail($image_path, $file_id) {
        $extension = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            return false;
        }

        $thumbnail_size = config('preview.thumbnail_size');
        $thumbnail_path = $this->config['upload_path'] . 'thumbnails/thumb_' . basename($image_path);

        try {
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($image_path);
                    break;
                case 'png':
                    $image = imagecreatefrompng($image_path);
                    break;
                case 'gif':
                    $image = imagecreatefromgif($image_path);
                    break;
                default:
                    return false;
            }

            if (!$image) return false;

            $width = imagesx($image);
            $height = imagesy($image);

            // Calculate thumbnail dimensions (maintain aspect ratio)
            if ($width > $height) {
                $new_width = $thumbnail_size;
                $new_height = floor($height * ($thumbnail_size / $width));
            } else {
                $new_height = $thumbnail_size;
                $new_width = floor($width * ($thumbnail_size / $height));
            }

            $thumbnail = imagecreatetruecolor($new_width, $new_height);

            // Preserve transparency for PNG/GIF
            if ($extension === 'png' || $extension === 'gif') {
                imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
            }

            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

            // Save thumbnail
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($thumbnail, $thumbnail_path, 80);
                    break;
                case 'png':
                    imagepng($thumbnail, $thumbnail_path);
                    break;
                case 'gif':
                    imagegif($thumbnail, $thumbnail_path);
                    break;
            }

            imagedestroy($image);
            imagedestroy($thumbnail);

            // Update file record with thumbnail path
            $this->file_model->update($file_id, [
                'thumbnail_path' => 'thumbnails/thumb_' . basename($image_path)
            ]);

            return true;

        } catch (Exception $e) {
            $this->warnings[] = 'Failed to generate thumbnail: ' . $e->getMessage();
            return false;
        }
    }

    private function logUpload($username, $file_id, $status) {
        $this->db->insert('audit_logs', [
            'user_id' => $username,
            'action' => 'file_upload',
            'record_id' => $file_id,
            'new_values' => json_encode([
                'status' => $status,
                'timestamp' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ])
        ]);
    }

    public function getUploadProgress($upload_id) {
        if (isset($_SESSION['uploads'][$upload_id])) {
            return $_SESSION['uploads'][$upload_id];
        }
        return null;
    }

    public function cleanupTempFiles() {
        $temp_path = $this->config['temp_path'];
        if (!file_exists($temp_path)) return;

        $files = scandir($temp_path);
        $now = time();

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $file_path = $temp_path . $file;
            if (is_file($file_path) && ($now - filemtime($file_path)) > 3600) { // 1 hour old
                unlink($file_path);
            }
        }
    }

    public function getAllowedExtensions() {
        return $this->config['allowed_extensions'];
    }

    public function getMaxFileSize() {
        return $this->config['max_file_size'];
    }

    public function formatFileSize($bytes) {
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
