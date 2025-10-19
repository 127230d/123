<?php
/**
 * Enhanced File Sharing System - File Model
 * Handles file-related database operations
 */

require_once __DIR__ . '/BaseModel.php';

class File extends BaseModel {
    protected $table = 'shared_files';
    protected $primary_key = 'id';
    protected $fillable = [
        'original_owner_id', 'filename', 'original_filename', 'file_path',
        'file_size', 'file_extension', 'mime_type', 'file_hash', 'description',
        'preview_type', 'preview_text', 'preview_image', 'thumbnail_path',
        'category_id', 'tags', 'price', 'is_available', 'is_featured',
        'is_premium', 'expires_at', 'metadata', 'seo_title', 'seo_description',
        'seo_keywords', 'download_count', 'view_count', 'average_rating', 'rating_count'
    ];

    public function __construct() {
        parent::__construct();
    }

    public function findByOwner($owner_id) {
        return $this->db->select(
            "SELECT * FROM {$this->table} WHERE original_owner_id = ? ORDER BY created_at DESC",
            [$owner_id]
        );
    }

    public function findAvailable() {
        return $this->db->select(
            "SELECT * FROM {$this->table} WHERE is_available = 1 ORDER BY created_at DESC"
        );
    }

    public function findFeatured() {
        return $this->db->select(
            "SELECT * FROM {$this->table} WHERE is_available = 1 AND is_featured = 1 ORDER BY created_at DESC"
        );
    }

    public function search($query, $limit = 50) {
        $search_term = "%{$query}%";
        return $this->db->select("
            SELECT f.*, u.username as owner_username, c.name as category_name
            FROM {$this->table} f
            LEFT JOIN login u ON f.original_owner_id = u.username
            LEFT JOIN file_categories c ON f.category_id = c.id
            WHERE f.is_available = 1
            AND (f.filename LIKE ? OR f.description LIKE ? OR f.tags LIKE ?)
            ORDER BY f.created_at DESC
            LIMIT ?
        ", [$search_term, $search_term, $search_term, $limit]);
    }

    public function getWithDetails($id) {
        return $this->db->selectOne("
            SELECT
                f.*,
                u.username as owner_username,
                u.first_name as owner_first_name,
                u.last_name as owner_last_name,
                u.avatar as owner_avatar,
                c.name as category_name,
                (SELECT COUNT(*) FROM file_purchases WHERE file_id = f.id) as purchase_count,
                (SELECT COUNT(*) FROM file_ratings WHERE file_id = f.id) as rating_count,
                (SELECT AVG(rating) FROM file_ratings WHERE file_id = f.id) as avg_rating
            FROM {$this->table} f
            LEFT JOIN login u ON f.original_owner_id = u.username
            LEFT JOIN file_categories c ON f.category_id = c.id
            WHERE f.id = ?
        ", [$id]);
    }

    public function incrementViews($id) {
        return $this->db->query(
            "UPDATE {$this->table} SET view_count = view_count + 1 WHERE id = ?",
            [$id]
        );
    }

    public function incrementDownloads($id) {
        return $this->db->query(
            "UPDATE {$this->table} SET download_count = download_count + 1 WHERE id = ?",
            [$id]
        );
    }

    public function updateRating($id, $new_rating, $rating_count) {
        return $this->db->update(
            $this->table,
            [
                'average_rating' => $new_rating,
                'rating_count' => $rating_count,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$id]
        );
    }

    public function getPurchases($id) {
        return $this->db->select("
            SELECT
                p.*,
                u.username as buyer_username,
                u.first_name as buyer_first_name,
                u.last_name as buyer_last_name
            FROM file_purchases p
            JOIN login u ON p.buyer_username = u.username
            WHERE p.file_id = ?
            ORDER BY p.purchase_date DESC
        ", [$id]);
    }

    public function getRatings($id) {
        return $this->db->select("
            SELECT
                r.*,
                u.username as rater_username,
                u.first_name as rater_first_name,
                u.last_name as rater_last_name
            FROM file_ratings r
            JOIN login u ON r.user_id = u.username
            WHERE r.file_id = ?
            ORDER BY r.created_at DESC
        ", [$id]);
    }

    public function getComments($id) {
        return $this->db->select("
            SELECT
                c.*,
                u.username as commenter_username,
                u.first_name as commenter_first_name,
                u.last_name as commenter_last_name,
                u.avatar as commenter_avatar
            FROM file_comments c
            JOIN login u ON c.user_id = u.username
            WHERE c.file_id = ? AND c.is_approved = 1
            ORDER BY c.created_at DESC
        ", [$id]);
    }

    public function addComment($file_id, $user_id, $comment, $parent_id = null) {
        return $this->db->insert('file_comments', [
            'file_id' => $file_id,
            'user_id' => $user_id,
            'comment' => $comment,
            'parent_id' => $parent_id,
            'is_approved' => 1 // Auto-approve for now
        ]);
    }

    public function addRating($file_id, $user_id, $rating, $comment = null) {
        // Check if user already rated this file
        $existing = $this->db->selectOne(
            "SELECT id FROM file_ratings WHERE file_id = ? AND user_id = ?",
            [$file_id, $user_id]
        );

        if ($existing) {
            // Update existing rating
            $this->db->update(
                'file_ratings',
                [
                    'rating' => $rating,
                    'comment' => $comment,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$existing['id']]
            );
        } else {
            // Insert new rating
            $this->db->insert('file_ratings', [
                'file_id' => $file_id,
                'user_id' => $user_id,
                'rating' => $rating,
                'comment' => $comment,
                'is_verified_purchase' => $this->isVerifiedPurchase($file_id, $user_id)
            ]);
        }

        // Update file's average rating
        $this->updateFileRating($file_id);

        return true;
    }

    private function isVerifiedPurchase($file_id, $user_id) {
        return $this->db->exists(
            'file_purchases',
            'file_id = ? AND buyer_username = ?',
            [$file_id, $user_id]
        );
    }

    private function updateFileRating($file_id) {
        $stats = $this->db->selectOne("
            SELECT
                COUNT(*) as total_ratings,
                AVG(rating) as avg_rating
            FROM file_ratings
            WHERE file_id = ?
        ", [$file_id]);

        if ($stats && $stats['total_ratings'] > 0) {
            $this->update($file_id, [
                'average_rating' => round($stats['avg_rating'], 2),
                'rating_count' => $stats['total_ratings']
            ]);
        }
    }

    public function getCategories() {
        return $this->db->select(
            "SELECT * FROM file_categories WHERE is_active = 1 ORDER BY sort_order, name"
        );
    }

    public function getByCategory($category_id, $limit = null) {
        $sql = "
            SELECT f.*, u.username as owner_username, c.name as category_name
            FROM {$this->table} f
            LEFT JOIN login u ON f.original_owner_id = u.username
            LEFT JOIN file_categories c ON f.category_id = c.id
            WHERE f.is_available = 1 AND f.category_id = ?
            ORDER BY f.created_at DESC
        ";

        if ($limit) {
            $sql .= " LIMIT ?";
            return $this->db->select($sql, [$category_id, $limit]);
        }

        return $this->db->select($sql, [$category_id]);
    }

    public function getTags() {
        return $this->db->select(
            "SELECT * FROM file_tags WHERE usage_count > 0 ORDER BY usage_count DESC, name"
        );
    }

    public function addTags($file_id, $tags) {
        if (empty($tags)) return;

        $tag_names = is_array($tags) ? $tags : explode(',', $tags);
        $tag_names = array_map('trim', $tag_names);

        foreach ($tag_names as $tag_name) {
            if (empty($tag_name)) continue;

            // Get or create tag
            $tag = $this->db->selectOne(
                "SELECT id FROM file_tags WHERE name = ?",
                [$tag_name]
            );

            if (!$tag) {
                $tag_id = $this->db->insert('file_tags', [
                    'name' => $tag_name,
                    'slug' => strtolower(str_replace(' ', '-', $tag_name))
                ]);
            } else {
                $tag_id = $tag['id'];
            }

            // Associate tag with file
            if (!$this->db->exists('file_tag_assignments', 'file_id = ? AND tag_id = ?', [$file_id, $tag_id])) {
                $this->db->insert('file_tag_assignments', [
                    'file_id' => $file_id,
                    'tag_id' => $tag_id
                ]);
            }
        }
    }

    public function getFileTags($file_id) {
        return $this->db->select("
            SELECT t.name, t.slug
            FROM file_tags t
            JOIN file_tag_assignments fta ON t.id = fta.tag_id
            WHERE fta.file_id = ?
            ORDER BY t.name
        ", [$file_id]);
    }

    public function validateFileData($data) {
        $errors = [];

        if (empty($data['filename'])) {
            $errors[] = 'File name is required';
        }

        if (empty($data['file_path'])) {
            $errors[] = 'File path is required';
        }

        if (!isset($data['price']) || $data['price'] < 0) {
            $errors[] = 'Valid price is required';
        }

        if (isset($data['file_size']) && $data['file_size'] > config('upload.max_file_size')) {
            $errors[] = 'File size exceeds maximum allowed size';
        }

        return $errors;
    }

    public function generateFileHash($file_path) {
        if (file_exists($file_path)) {
            return hash_file('sha256', $file_path);
        }
        return null;
    }

    public function getMimeType($file_path) {
        if (function_exists('mime_content_type')) {
            return mime_content_type($file_path);
        }

        // Fallback method
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $mime_types = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'zip' => 'application/zip',
        ];

        return $mime_types[$extension] ?? 'application/octet-stream';
    }
}
