<?php
/**
 * Enhanced File Sharing System - File Preview Handler
 * Improved file preview with caching and better generation
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/File.php';

class FilePreview {
    private $config;
    private $db;
    private $file_model;
    private $cache_dir;

    public function __construct() {
        $this->config = config('preview');
        $this->db = Database::getInstance();
        $this->file_model = new File();
        $this->cache_dir = __DIR__ . '/cache/previews/';

        $this->ensureCacheDirectory();
    }

    public function getPreview($file_id, $user_id = null) {
        // Check if user can preview this file
        if (!$this->canPreview($file_id, $user_id)) {
            return [
                'success' => false,
                'message' => 'Preview not available'
            ];
        }

        $file = $this->file_model->getWithDetails($file_id);
        if (!$file) {
            return [
                'success' => false,
                'message' => 'File not found'
            ];
        }

        // Check cache first
        $cache_key = $this->getCacheKey($file_id, $file);
        $cached_preview = $this->getCachedPreview($cache_key);

        if ($cached_preview) {
            return [
                'success' => true,
                'preview' => $cached_preview,
                'file' => $file,
                'cached' => true
            ];
        }

        // Generate preview
        $preview_content = $this->generatePreview($file);

        if ($preview_content) {
            // Cache the preview
            $this->cachePreview($cache_key, $preview_content);

            return [
                'success' => true,
                'preview' => $preview_content,
                'file' => $file,
                'cached' => false
            ];
        }

        return [
            'success' => false,
            'message' => 'Preview generation failed'
        ];
    }

    private function canPreview($file_id, $user_id) {
        $file = $this->file_model->find($file_id);

        if (!$file) {
            return false;
        }

        // File must be available for preview
        if (!$file['is_available']) {
            return false;
        }

        // If file is free (price = 0), anyone can preview
        if ($file['price'] == 0) {
            return true;
        }

        // If user is the owner, they can preview
        if ($user_id && $file['original_owner_id'] == $user_id) {
            return true;
        }

        // Check if user has purchased the file
        if ($user_id) {
            $has_purchased = $this->db->exists(
                'file_purchases',
                'file_id = ? AND buyer_username = ?',
                [$file_id, $user_id]
            );
            return $has_purchased;
        }

        return false;
    }

    private function generatePreview($file) {
        // Use the correct uploads directory path
        $file_path = __DIR__ . '/../uploads/' . $file['file_path'];

        // If file_path doesn't exist, try with just the filename
        if (!file_exists($file_path)) {
            $file_path = __DIR__ . '/../uploads/' . $file['filename'];
        }

        if (!file_exists($file_path)) {
            return $this->generateErrorPreview('File not found on server');
        }

        $extension = strtolower($file['file_extension']);

        // Handle different file types
        switch ($extension) {
            case 'txt':
            case 'md':
            case 'csv':
            case 'json':
            case 'log':
            case 'xml':
            case 'html':
            case 'css':
            case 'js':
            case 'php':
            case 'py':
            case 'java':
            case 'cpp':
            case 'c':
            case 'h':
                return $this->generateTextPreview($file_path, $file);

            case 'pdf':
                return $this->generatePdfPreview($file_path, $file);

            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
                return $this->generateImagePreview($file_path, $file);

            case 'mp3':
            case 'wav':
                return $this->generateAudioPreview($file_path, $file);

            case 'mp4':
            case 'avi':
            case 'mov':
                return $this->generateVideoPreview($file_path, $file);

            case 'zip':
            case 'rar':
            case '7z':
                return $this->generateArchivePreview($file_path, $file);

            default:
                return $this->generateDefaultPreview($file);
        }
    }

    private function generateTextPreview($file_path, $file) {
        // Use the correct uploads directory path
        $full_path = __DIR__ . '/../uploads/' . $file['file_path'];

        // If file_path doesn't exist, try with just the filename
        if (!file_exists($full_path)) {
            $full_path = __DIR__ . '/../uploads/' . $file['filename'];
        }

        if (!file_exists($full_path)) {
            return $this->generateErrorPreview('File not found on server');
        }

        $content = file_get_contents($full_path);

        if ($content === false) {
            return $this->generateErrorPreview('Could not read text file');
        }

        // Remove null bytes and other problematic characters
        $content = preg_replace('/\x00/', '', $content);

        // Limit content length
        $max_length = $this->config['max_text_length'];
        if (strlen($content) > $max_length) {
            $content = substr($content, 0, $max_length) . '...';
        }

        // Escape HTML for security
        $escaped_content = htmlspecialchars($content);

        // Add line numbers for code-like files
        $extension = strtolower($file['file_extension']);
        $code_extensions = ['php', 'js', 'css', 'html', 'py', 'java', 'cpp', 'c', 'h'];

        if (in_array($extension, $code_extensions)) {
            $lines = explode("\n", $escaped_content);
            $numbered_content = '';
            foreach ($lines as $i => $line) {
                $line_number = $i + 1;
                $numbered_content .= '<div class="code-line">';
                $numbered_content .= '<span class="line-number">' . $line_number . '</span>';
                $numbered_content .= '<span class="line-content">' . $line . '</span>';
                $numbered_content .= '</div>';
            }
            $escaped_content = $numbered_content;
        }

        return '
            <div class="text-preview">
                <div class="preview-header">
                    <i class="fas fa-file-alt"></i>
                    <span>Text Preview</span>
                    <small>' . $this->formatFileSize($file['file_size']) . '</small>
                </div>
                <div class="preview-content">
                    <pre><code>' . $escaped_content . '</code></pre>
                </div>
                <div class="preview-footer">
                    <button onclick="downloadFile(' . $file['id'] . ')" class="btn-download">
                        <i class="fas fa-download"></i> Download Full File
                    </button>
                </div>
            </div>
        ';
    }

    private function generateImagePreview($file_path, $file) {
        // Use the correct uploads directory path
        $full_path = __DIR__ . '/../uploads/' . $file['file_path'];

        // If file_path doesn't exist, try with just the filename
        if (!file_exists($full_path)) {
            $full_path = __DIR__ . '/../uploads/' . $file['filename'];
        }

        if (!file_exists($full_path)) {
            return $this->generateErrorPreview('Image file not found on server');
        }

        $image_info = getimagesize($full_path);

        if (!$image_info) {
            return $this->generateErrorPreview('Invalid image file');
        }

        list($width, $height, $type) = $image_info;
        $image_type = image_type_to_mime_type($type);

        // Generate thumbnail if it doesn't exist
        $thumbnail_path = __DIR__ . '/../uploads/thumbnails/thumb_' . basename($full_path);

        if (!file_exists($thumbnail_path)) {
            $this->generateImageThumbnail($full_path, $thumbnail_path);
        }

        $thumbnail_url = 'uploads/thumbnails/thumb_' . basename($full_path);

        return '
            <div class="image-preview">
                <div class="preview-header">
                    <i class="fas fa-image"></i>
                    <span>Image Preview</span>
                    <small>' . $width . 'x' . $height . ' • ' . $this->formatFileSize($file['file_size']) . '</small>
                </div>
                <div class="preview-content">
                    <div class="image-container">
                        <img src="' . $thumbnail_url . '" alt="Preview" class="preview-image" onclick="showFullImage()">
                        <div class="image-overlay">
                            <button onclick="showFullImage()" class="btn-zoom">
                                <i class="fas fa-search-plus"></i> View Full Size
                            </button>
                        </div>
                    </div>
                    <div class="image-details">
                        <div class="detail-item">
                            <strong>Dimensions:</strong> ' . $width . ' x ' . $height . ' pixels
                        </div>
                        <div class="detail-item">
                            <strong>Type:</strong> ' . $image_type . '
                        </div>
                    </div>
                </div>
                <div class="preview-footer">
                    <button onclick="downloadFile(' . $file['id'] . ')" class="btn-download">
                        <i class="fas fa-download"></i> Download Original
                    </button>
                </div>
            </div>
        ';
    }

    private function generatePdfPreview($file_path, $file) {
        // Use the correct uploads directory path
        $full_path = __DIR__ . '/../uploads/' . $file['file_path'];

        // If file_path doesn't exist, try with just the filename
        if (!file_exists($full_path)) {
            $full_path = __DIR__ . '/../uploads/' . $file['filename'];
        }

        if (!file_exists($full_path)) {
            return $this->generateErrorPreview('PDF file not found on server');
        }

        // For PDF files, we'll show basic info and first page if possible
        $pdf_info = $this->getPdfInfo($full_path);

        return '
            <div class="pdf-preview">
                <div class="preview-header">
                    <i class="fas fa-file-pdf"></i>
                    <span>PDF Preview</span>
                    <small>' . $this->formatFileSize($file['file_size']) . '</small>
                </div>
                <div class="preview-content">
                    <div class="pdf-info">
                        <div class="pdf-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="pdf-details">
                            <h4>' . htmlspecialchars($file['filename']) . '</h4>
                            <div class="pdf-meta">
                                <span><strong>Pages:</strong> ' . ($pdf_info['pages'] ?? 'Unknown') . '</span>
                                <span><strong>Size:</strong> ' . $this->formatFileSize($file['file_size']) . '</span>
                            </div>
                            <p class="pdf-description">' . htmlspecialchars(substr($file['description'] ?? '', 0, 200)) . '</p>
                        </div>
                    </div>
                </div>
                <div class="preview-footer">
                    <button onclick="downloadFile(' . $file['id'] . ')" class="btn-download">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                </div>
            </div>
        ';
    }

    private function generateAudioPreview($file_path, $file) {
        // Use the correct uploads directory path
        $full_path = __DIR__ . '/../uploads/' . $file['file_path'];

        // If file_path doesn't exist, try with just the filename
        if (!file_exists($full_path)) {
            $full_path = __DIR__ . '/../uploads/' . $file['filename'];
        }

        if (!file_exists($full_path)) {
            return $this->generateErrorPreview('Audio file not found on server');
        }

        return '
            <div class="audio-preview">
                <div class="preview-header">
                    <i class="fas fa-music"></i>
                    <span>Audio Preview</span>
                    <small>' . $this->formatFileSize($file['file_size']) . '</small>
                </div>
                <div class="preview-content">
                    <div class="audio-player">
                        <audio controls preload="metadata">
                            <source src="download.php?id=' . $file['id'] . '" type="' . $file['mime_type'] . '">
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                </div>
                <div class="preview-footer">
                    <button onclick="downloadFile(' . $file['id'] . ')" class="btn-download">
                        <i class="fas fa-download"></i> Download Audio
                    </button>
                </div>
            </div>
        ';
    }

    private function generateVideoPreview($file_path, $file) {
        // Use the correct uploads directory path
        $full_path = __DIR__ . '/../uploads/' . $file['file_path'];

        // If file_path doesn't exist, try with just the filename
        if (!file_exists($full_path)) {
            $full_path = __DIR__ . '/../uploads/' . $file['filename'];
        }

        if (!file_exists($full_path)) {
            return $this->generateErrorPreview('Video file not found on server');
        }

        return '
            <div class="video-preview">
                <div class="preview-header">
                    <i class="fas fa-video"></i>
                    <span>Video Preview</span>
                    <small>' . $this->formatFileSize($file['file_size']) . '</small>
                </div>
                <div class="preview-content">
                    <video controls width="100%" height="300" preload="metadata">
                        <source src="download.php?id=' . $file['id'] . '" type="' . $file['mime_type'] . '">
                        Your browser does not support the video element.
                    </video>
                </div>
                <div class="preview-footer">
                    <button onclick="downloadFile(' . $file['id'] . ')" class="btn-download">
                        <i class="fas fa-download"></i> Download Video
                    </button>
                </div>
            </div>
        ';
    }

    private function generateArchivePreview($file_path, $file) {
        // Use the correct uploads directory path
        $full_path = __DIR__ . '/../uploads/' . $file['file_path'];

        // If file_path doesn't exist, try with just the filename
        if (!file_exists($full_path)) {
            $full_path = __DIR__ . '/../uploads/' . $file['filename'];
        }

        if (!file_exists($full_path)) {
            return $this->generateErrorPreview('Archive file not found on server');
        }

        $archive_info = $this->getArchiveInfo($full_path);

        return '
            <div class="archive-preview">
                <div class="preview-header">
                    <i class="fas fa-file-archive"></i>
                    <span>Archive Preview</span>
                    <small>' . $this->formatFileSize($file['file_size']) . '</small>
                </div>
                <div class="preview-content">
                    <div class="archive-info">
                        <div class="archive-icon">
                            <i class="fas fa-file-archive"></i>
                        </div>
                        <div class="archive-details">
                            <h4>' . htmlspecialchars($file['filename']) . '</h4>
                            <div class="archive-stats">
                                <span><strong>Files:</strong> ' . ($archive_info['file_count'] ?? 'Unknown') . '</span>
                                <span><strong>Compressed Size:</strong> ' . $this->formatFileSize($archive_info['compressed_size'] ?? $file['file_size']) . '</span>
                            </div>
                            ' . (!empty($archive_info['files']) ? '
                            <div class="archive-contents">
                                <h5>Contents:</h5>
                                <ul>' . implode('', array_map(function($file) {
                                    return '<li>' . htmlspecialchars($file) . '</li>';
                                }, array_slice($archive_info['files'], 0, 10))) . '</ul>
                            </div>' : '') . '
                        </div>
                    </div>
                </div>
                <div class="preview-footer">
                    <button onclick="downloadFile(' . $file['id'] . ')" class="btn-download">
                        <i class="fas fa-download"></i> Download Archive
                    </button>
                </div>
            </div>
        ';
    }

    private function generateDefaultPreview($file) {
        return '
            <div class="default-preview">
                <div class="preview-header">
                    <i class="fas fa-file"></i>
                    <span>File Preview</span>
                    <small>' . $this->formatFileSize($file['file_size']) . '</small>
                </div>
                <div class="preview-content">
                    <div class="file-info">
                        <div class="file-icon-large">
                            <i class="' . $this->getFileIcon($file['file_extension']) . '"></i>
                        </div>
                        <div class="file-details">
                            <h4>' . htmlspecialchars($file['filename']) . '</h4>
                            <p>' . htmlspecialchars($file['description'] ?? '') . '</p>
                            <div class="file-meta">
                                <span><strong>Type:</strong> ' . strtoupper($file['file_extension']) . '</span>
                                <span><strong>Size:</strong> ' . $this->formatFileSize($file['file_size']) . '</span>
                                <span><strong>Uploaded:</strong> ' . date('M j, Y', strtotime($file['created_at'])) . '</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="preview-footer">
                    <button onclick="downloadFile(' . $file['id'] . ')" class="btn-download">
                        <i class="fas fa-download"></i> Download File
                    </button>
                </div>
            </div>
        ';
    }

    private function generateErrorPreview($message) {
        return '
            <div class="error-preview">
                <div class="preview-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Preview Unavailable</span>
                </div>
                <div class="preview-content">
                    <p>' . htmlspecialchars($message) . '</p>
                </div>
            </div>
        ';
    }

    private function generateImageThumbnail($source_path, $thumb_path) {
        $image_info = getimagesize($source_path);

        if (!$image_info) return false;

        list($width, $height, $type) = $image_info;

        $thumb_size = $this->config['thumbnail_size'];

        // Calculate thumbnail dimensions
        if ($width > $height) {
            $new_width = $thumb_size;
            $new_height = floor($height * ($thumb_size / $width));
        } else {
            $new_height = $thumb_size;
            $new_width = floor($width * ($thumb_size / $height));
        }

        // Create thumbnail based on image type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($source_path);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($source_path);
                break;
            default:
                return false;
        }

        if (!$source) return false;

        $thumb = imagecreatetruecolor($new_width, $new_height);

        // Preserve transparency for PNG
        if ($type === IMAGETYPE_PNG) {
            imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        // Save thumbnail
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumb, $thumb_path, 80);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumb, $thumb_path);
                break;
            case IMAGETYPE_GIF:
                imagegif($thumb, $thumb_path);
                break;
        }

        imagedestroy($source);
        imagedestroy($thumb);

        return true;
    }

    private function getPdfInfo($file_path) {
        // Basic PDF info extraction (you might want to use a PDF library for better results)
        $info = [];

        if (function_exists('pdfinfo')) {
            $output = shell_exec("pdfinfo '{$file_path}' 2>/dev/null");
            if ($output) {
                preg_match('/Pages:\s+(\d+)/', $output, $matches);
                if (isset($matches[1])) {
                    $info['pages'] = $matches[1];
                }
            }
        }

        return $info;
    }

    private function getArchiveInfo($file_path) {
        $info = ['file_count' => 0, 'files' => []];

        // Try to read ZIP files
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($file_path) === true) {
                $info['file_count'] = $zip->numFiles;
                for ($i = 0; $i < min($zip->numFiles, 10); $i++) {
                    $info['files'][] = $zip->getNameIndex($i);
                }
                $zip->close();
            }
        }

        return $info;
    }

    private function getFileIcon($extension) {
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
            'json' => 'fas fa-file-code',
            'html' => 'fas fa-file-code',
            'css' => 'fas fa-file-code',
            'js' => 'fas fa-file-code',
            'php' => 'fas fa-file-code',
        ];

        return $icons[$extension] ?? 'fas fa-file';
    }

    private function getCacheKey($file_id, $file) {
        return md5($file_id . $file['updated_at'] . $file['file_size']);
    }

    private function getCachedPreview($cache_key) {
        $cache_file = $this->cache_dir . $cache_key . '.html';

        if (file_exists($cache_file)) {
            $cache_time = filemtime($cache_file);
            $max_age = config('cache.ttl');

            if ((time() - $cache_time) < $max_age) {
                return file_get_contents($cache_file);
            } else {
                unlink($cache_file); // Remove expired cache
            }
        }

        return false;
    }

    private function cachePreview($cache_key, $content) {
        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }

        $cache_file = $this->cache_dir . $cache_key . '.html';
        file_put_contents($cache_file, $content);
    }

    private function ensureCacheDirectory() {
        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }

    private function formatFileSize($bytes) {
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

    public function clearCache($file_id = null) {
        if ($file_id) {
            // Clear specific file cache
            $file = $this->file_model->find($file_id);
            if ($file) {
                $cache_key = $this->getCacheKey($file_id, $file);
                $cache_file = $this->cache_dir . $cache_key . '.html';
                if (file_exists($cache_file)) {
                    unlink($cache_file);
                }
            }
        } else {
            // Clear all cache files
            $files = glob($this->cache_dir . '*.html');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}
