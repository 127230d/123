<?php
/**
 * Enhanced File Sharing System - File Details Handler
 * Provides comprehensive file metadata and details
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/File.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Purchase.php';
require_once __DIR__ . '/classes/RatingSystem.php';

class FileDetails {
    private $db;
    private $file_model;
    private $user_model;
    private $purchase_model;
    private $rating_system;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->file_model = new File();
        $this->user_model = new User();
        $this->purchase_model = new Purchase();
        $this->rating_system = new RatingSystem();
    }

    public function getDetailedFileInfo($file_id, $user_id = null) {
        $file = $this->file_model->getWithDetails($file_id);

        if (!$file) {
            return [
                'success' => false,
                'message' => 'File not found'
            ];
        }

        // Check if user can view this file
        if (!$this->canUserViewFile($file, $user_id)) {
            return [
                'success' => false,
                'message' => 'Access denied'
            ];
        }

        $detailed_info = $this->buildDetailedInfo($file, $user_id);

        return [
            'success' => true,
            'file' => $detailed_info
        ];
    }

    private function canUserViewFile($file, $user_id) {
        // File must be available
        if (!$file['is_available']) {
            return false;
        }

        // If file is free, anyone can view
        if ($file['price'] == 0) {
            return true;
        }

        // Owner can always view
        if ($user_id && $file['original_owner_id'] == $user_id) {
            return true;
        }

        // Check if user has purchased the file
        if ($user_id) {
            return $this->purchase_model->exists([
                'file_id' => $file['id'],
                'buyer_username' => $user_id,
                'payment_status' => 'completed'
            ]);
        }

        return false;
    }

    private function buildDetailedInfo($file, $user_id) {
        $file_info = $file;

        // Add comprehensive metadata
        $file_info['file_size_formatted'] = $this->formatFileSize($file['file_size']);
        $file_info['created_at_formatted'] = $this->formatDate($file['created_at']);
        $file_info['updated_at_formatted'] = $this->formatDate($file['updated_at']);

        // Add file type information
        $file_info['file_type_info'] = $this->getFileTypeInfo($file['file_extension']);
        $file_info['is_image'] = in_array($file['file_extension'], ['jpg', 'jpeg', 'png', 'gif']);
        $file_info['is_video'] = in_array($file['file_extension'], ['mp4', 'avi', 'mov']);
        $file_info['is_audio'] = in_array($file['file_extension'], ['mp3', 'wav']);
        $file_info['is_document'] = in_array($file['file_extension'], ['pdf', 'doc', 'docx', 'txt']);
        $file_info['is_archive'] = in_array($file['file_extension'], ['zip', 'rar', '7z']);

        // Add seller information
        $seller = $this->user_model->findByUsername($file['original_owner_id']);
        $file_info['seller'] = [
            'username' => $seller['username'] ?? 'Unknown',
            'first_name' => $seller['first_name'] ?? '',
            'last_name' => $seller['last_name'] ?? '',
            'avatar' => $seller['avatar'] ?? null,
            'country' => $seller['country'] ?? null,
            'rating' => $this->getUserRating($file['original_owner_id']),
            'files_sold' => $this->getUserFilesSold($file['original_owner_id'])
        ];

        // Add purchase information if user has purchased
        if ($user_id) {
            $purchase = $this->purchase_model->findBy([
                'file_id' => $file['id'],
                'buyer_username' => $user_id
            ]);

            if ($purchase) {
                $file_info['user_purchase'] = [
                    'purchase_id' => $purchase['id'],
                    'purchase_date' => $purchase['purchase_date'],
                    'paid_price' => $purchase['price'],
                    'payment_status' => $purchase['payment_status'],
                    'download_count' => $purchase['download_count'],
                    'can_download' => $this->purchase_model->canDownload($purchase['id'], $user_id)
                ];
            }
        }

        // Add rating information
        $file_info['rating_summary'] = $this->rating_system->getFileRatingSummary($file['id']);
        $file_info['rating_stats'] = $this->rating_system->getRatingStats($file['id']);

        // Add user's rating if exists
        if ($user_id) {
            $user_rating = $this->rating_system->getUserRating($file['id'], $user_id);
            $file_info['user_rating'] = $user_rating;
        }

        // Add comments preview (recent 5 comments)
        $file_info['recent_comments'] = $this->getRecentComments($file['id'], 5);

        // Add related files (same category, similar price range)
        $file_info['related_files'] = $this->getRelatedFiles($file, 6);

        // Add download information
        $file_info['download_info'] = [
            'total_downloads' => $file['download_count'],
            'can_download' => $user_id ? $this->canDownloadFile($file, $user_id) : false,
            'download_url' => $user_id && $this->canDownloadFile($file, $user_id) ? "download.php?id={$file['id']}" : null
        ];

        // Add SEO information
        $file_info['seo'] = [
            'title' => $file['seo_title'] ?: $file['filename'],
            'description' => $file['seo_description'] ?: substr($file['description'] ?: '', 0, 160),
            'keywords' => $file['seo_keywords'] ?: $this->generateKeywords($file)
        ];

        // Add sharing information
        $file_info['sharing'] = [
            'share_url' => $this->generateShareUrl($file['id']),
            'embed_code' => $this->generateEmbedCode($file['id'])
        ];

        return $file_info;
    }

    private function getFileTypeInfo($extension) {
        $type_info = [
            'pdf' => ['name' => 'PDF Document', 'icon' => 'fas fa-file-pdf', 'color' => '#dc3545'],
            'doc' => ['name' => 'Word Document', 'icon' => 'fas fa-file-word', 'color' => '#007bff'],
            'docx' => ['name' => 'Word Document', 'icon' => 'fas fa-file-word', 'color' => '#007bff'],
            'txt' => ['name' => 'Text File', 'icon' => 'fas fa-file-alt', 'color' => '#6c757d'],
            'jpg' => ['name' => 'JPEG Image', 'icon' => 'fas fa-file-image', 'color' => '#28a745'],
            'jpeg' => ['name' => 'JPEG Image', 'icon' => 'fas fa-file-image', 'color' => '#28a745'],
            'png' => ['name' => 'PNG Image', 'icon' => 'fas fa-file-image', 'color' => '#28a745'],
            'gif' => ['name' => 'GIF Image', 'icon' => 'fas fa-file-image', 'color' => '#28a745'],
            'zip' => ['name' => 'ZIP Archive', 'icon' => 'fas fa-file-archive', 'color' => '#6f42c1'],
            'rar' => ['name' => 'RAR Archive', 'icon' => 'fas fa-file-archive', 'color' => '#6f42c1'],
            '7z' => ['name' => '7-Zip Archive', 'icon' => 'fas fa-file-archive', 'color' => '#6f42c1'],
            'json' => ['name' => 'JSON File', 'icon' => 'fas fa-file-code', 'color' => '#fd7e14'],
            'csv' => ['name' => 'CSV File', 'icon' => 'fas fa-file-csv', 'color' => '#ffc107'],
            'mp3' => ['name' => 'MP3 Audio', 'icon' => 'fas fa-file-audio', 'color' => '#e83e8c'],
            'mp4' => ['name' => 'MP4 Video', 'icon' => 'fas fa-file-video', 'color' => '#dc3545'],
            'html' => ['name' => 'HTML File', 'icon' => 'fas fa-file-code', 'color' => '#fd7e14'],
            'css' => ['name' => 'CSS File', 'icon' => 'fas fa-file-code', 'color' => '#fd7e14'],
            'js' => ['name' => 'JavaScript File', 'icon' => 'fas fa-file-code', 'color' => '#fd7e14'],
            'php' => ['name' => 'PHP Script', 'icon' => 'fas fa-file-code', 'color' => '#fd7e14'],
            'py' => ['name' => 'Python Script', 'icon' => 'fas fa-file-code', 'color' => '#fd7e14'],
            'java' => ['name' => 'Java File', 'icon' => 'fas fa-file-code', 'color' => '#fd7e14'],
        ];

        return $type_info[$extension] ?? ['name' => strtoupper($extension) . ' File', 'icon' => 'fas fa-file', 'color' => '#6c757d'];
    }

    private function getUserRating($username) {
        // Calculate user's average rating as a seller
        $stats = $this->db->selectOne("
            SELECT
                AVG(r.rating) as avg_rating,
                COUNT(r.id) as rating_count
            FROM file_ratings r
            JOIN shared_files f ON r.file_id = f.id
            WHERE f.original_owner_id = ?
        ", [$username]);

        return [
            'average' => round($stats['avg_rating'] ?? 0, 2),
            'count' => $stats['rating_count'] ?? 0
        ];
    }

    private function getUserFilesSold($username) {
        return $this->db->rowCount(
            "SELECT COUNT(*) FROM shared_files WHERE original_owner_id = ? AND is_available = 1",
            [$username]
        );
    }

    private function canDownloadFile($file, $user_id) {
        // Owner can always download
        if ($file['original_owner_id'] == $user_id) {
            return true;
        }

        // Check if user has purchased the file
        return $this->purchase_model->exists([
            'file_id' => $file['id'],
            'buyer_username' => $user_id,
            'payment_status' => 'completed'
        ]);
    }

    private function getRecentComments($file_id, $limit) {
        return $this->db->select("
            SELECT
                c.*,
                u.username,
                u.first_name,
                u.last_name,
                u.avatar
            FROM file_comments c
            JOIN login u ON c.user_id = u.username
            WHERE c.file_id = ? AND c.is_approved = 1
            ORDER BY c.created_at DESC
            LIMIT ?
        ", [$file_id, $limit]);
    }

    private function getRelatedFiles($file, $limit) {
        // Find files in same category with similar price range
        $price_range = $file['price'] * 0.5; // ±50% price range
        $min_price = max(0, $file['price'] - $price_range);
        $max_price = $file['price'] + $price_range;

        return $this->db->select("
            SELECT
                f.*,
                u.username as owner_username,
                c.name as category_name
            FROM shared_files f
            LEFT JOIN login u ON f.original_owner_id = u.username
            LEFT JOIN file_categories c ON f.category_id = c.id
            WHERE f.id != ? AND f.is_available = 1
            AND f.category_id = ?
            AND f.price BETWEEN ? AND ?
            ORDER BY RAND()
            LIMIT ?
        ", [$file['id'], $file['category_id'], $min_price, $max_price, $limit]);
    }

    private function generateShareUrl($file_id) {
        $base_url = config('app.url');
        return "{$base_url}/file.php?id={$file_id}";
    }

    private function generateEmbedCode($file_id) {
        return '<iframe src="' . $this->generateShareUrl($file_id) . '" width="600" height="400" frameborder="0"></iframe>';
    }

    private function generateKeywords($file) {
        $keywords = [];

        // Add file extension
        $keywords[] = $file['file_extension'];

        // Add category if available
        if ($file['category_name']) {
            $keywords[] = $file['category_name'];
        }

        // Extract words from filename and description
        $text = strtolower($file['filename'] . ' ' . $file['description']);
        $words = preg_split('/\W+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Filter and add meaningful words
        foreach ($words as $word) {
            if (strlen($word) > 3 && !in_array($word, $keywords)) {
                $keywords[] = $word;
            }
        }

        return implode(', ', array_slice($keywords, 0, 10));
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

    private function formatDate($date) {
        return date('M j, Y \a\t g:i A', strtotime($date));
    }

    public function getFileMetadata($file_id) {
        $file = $this->file_model->find($file_id);

        if (!$file) {
            return [];
        }

        return [
            'basic' => [
                'id' => $file['id'],
                'filename' => $file['filename'],
                'original_filename' => $file['original_filename'],
                'file_size' => $file['file_size'],
                'file_size_formatted' => $this->formatFileSize($file['file_size']),
                'file_extension' => $file['file_extension'],
                'mime_type' => $file['mime_type'],
                'file_hash' => $file['file_hash']
            ],
            'ownership' => [
                'owner_id' => $file['original_owner_id'],
                'created_at' => $file['created_at'],
                'updated_at' => $file['updated_at']
            ],
            'pricing' => [
                'price' => $file['price'],
                'is_available' => $file['is_available'],
                'is_featured' => $file['is_featured'],
                'is_premium' => $file['is_premium']
            ],
            'statistics' => [
                'view_count' => $file['view_count'],
                'download_count' => $file['download_count'],
                'average_rating' => $file['average_rating'],
                'rating_count' => $file['rating_count']
            ],
            'seo' => [
                'title' => $file['seo_title'],
                'description' => $file['seo_description'],
                'keywords' => $file['seo_keywords']
            ]
        ];
    }

    public function updateFileMetadata($file_id, $metadata) {
        $allowed_fields = ['description', 'category_id', 'tags', 'seo_title', 'seo_description', 'seo_keywords'];

        $update_data = [];
        foreach ($metadata as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $update_data[$key] = $value;
            }
        }

        if (!empty($update_data)) {
            $update_data['updated_at'] = date('Y-m-d H:i:s');
            return $this->file_model->update($file_id, $update_data);
        }

        return false;
    }

    public function getFileActivity($file_id, $limit = 50) {
        $activity = [];

        // Get purchase history
        $purchases = $this->purchase_model->getPurchases($file_id);
        foreach ($purchases as $purchase) {
            $activity[] = [
                'type' => 'purchase',
                'user_id' => $purchase['buyer_username'],
                'timestamp' => $purchase['purchase_date'],
                'description' => 'File purchased'
            ];
        }

        // Get rating activity
        $ratings = $this->rating_system->getFileRatingSummary($file_id);
        if ($ratings['total_ratings'] > 0) {
            $activity[] = [
                'type' => 'rating',
                'user_id' => null,
                'timestamp' => date('Y-m-d H:i:s'),
                'description' => "Received {$ratings['total_ratings']} ratings"
            ];
        }

        // Sort by timestamp
        usort($activity, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($activity, 0, $limit);
    }
}
