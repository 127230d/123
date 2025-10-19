<?php
/**
 * Enhanced File Sharing System - File Sharing Manager
 * Handles file sharing with improved permissions and access control
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/File.php';
require_once __DIR__ . '/models/User.php';

class FileSharing {
    private $db;
    private $file_model;
    private $user_model;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->file_model = new File();
        $this->user_model = new User();
    }

    public function generateShareLink($file_id, $user_id, $options = []) {
        $file = $this->file_model->find($file_id);

        if (!$file) {
            return [
                'success' => false,
                'message' => 'File not found'
            ];
        }

        // Check permissions
        if (!$this->canShareFile($file, $user_id)) {
            return [
                'success' => false,
                'message' => 'Permission denied'
            ];
        }

        // Generate unique share token
        $share_token = bin2hex(random_bytes(16));
        $expires_at = isset($options['expires_days'])
            ? date('Y-m-d H:i:s', time() + ($options['expires_days'] * 24 * 3600))
            : null;

        // Store share link in database
        $this->db->insert('file_share_links', [
            'file_id' => $file_id,
            'shared_by' => $user_id,
            'share_token' => $share_token,
            'access_level' => $options['access_level'] ?? 'preview',
            'expires_at' => $expires_at,
            'max_views' => $options['max_views'] ?? null,
            'max_downloads' => $options['max_downloads'] ?? null,
            'password' => $options['password'] ?? null,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $share_url = config('app.url') . "/shared.php?token={$share_token}";

        return [
            'success' => true,
            'share_url' => $share_url,
            'share_token' => $share_token,
            'expires_at' => $expires_at
        ];
    }

    public function accessSharedFile($share_token, $password = null) {
        $share_link = $this->getShareLink($share_token);

        if (!$share_link) {
            return [
                'success' => false,
                'message' => 'Invalid or expired share link'
            ];
        }

        // Check if link is active
        if (!$share_link['is_active']) {
            return [
                'success' => false,
                'message' => 'Share link has been deactivated'
            ];
        }

        // Check expiration
        if ($share_link['expires_at'] && strtotime($share_link['expires_at']) < time()) {
            return [
                'success' => false,
                'message' => 'Share link has expired'
            ];
        }

        // Check password protection
        if ($share_link['password'] && $share_link['password'] !== $password) {
            return [
                'success' => false,
                'message' => 'Invalid password',
                'requires_password' => true
            ];
        }

        // Check view limits
        if ($share_link['max_views'] && $share_link['view_count'] >= $share_link['max_views']) {
            return [
                'success' => false,
                'message' => 'View limit exceeded'
            ];
        }

        // Get file information
        $file = $this->file_model->getWithDetails($share_link['file_id']);

        if (!$file || !$file['is_available']) {
            return [
                'success' => false,
                'message' => 'File not available'
            ];
        }

        // Increment view count
        $this->incrementShareViewCount($share_token);

        return [
            'success' => true,
            'file' => $file,
            'share_info' => [
                'access_level' => $share_link['access_level'],
                'can_download' => $share_link['access_level'] === 'download',
                'can_preview' => in_array($share_link['access_level'], ['preview', 'download']),
                'remaining_views' => $share_link['max_views'] ? max(0, $share_link['max_views'] - $share_link['view_count'] - 1) : null,
                'expires_at' => $share_link['expires_at']
            ]
        ];
    }

    public function downloadSharedFile($share_token, $password = null) {
        $access_result = $this->accessSharedFile($share_token, $password);

        if (!$access_result['success']) {
            return $access_result;
        }

        if (!$access_result['share_info']['can_download']) {
            return [
                'success' => false,
                'message' => 'Download not permitted for this share link'
            ];
        }

        $file = $access_result['file'];
        $file_path = __DIR__ . '/uploads/' . $file['file_path'];

        if (!file_exists($file_path)) {
            return [
                'success' => false,
                'message' => 'File not found on server'
            ];
        }

        // Increment download count in share link
        $this->incrementShareDownloadCount($share_token);

        // Increment file download count
        $this->file_model->incrementDownloads($file['id']);

        return [
            'success' => true,
            'file_path' => $file_path,
            'filename' => $file['original_filename'],
            'mime_type' => $file['mime_type']
        ];
    }

    public function getShareLinks($file_id, $user_id) {
        $file = $this->file_model->find($file_id);

        if (!$file || $file['original_owner_id'] !== $user_id) {
            return [];
        }

        return $this->db->select("
            SELECT * FROM file_share_links
            WHERE file_id = ?
            ORDER BY created_at DESC
        ", [$file_id]);
    }

    public function deactivateShareLink($share_token, $user_id) {
        $share_link = $this->getShareLink($share_token);

        if (!$share_link) {
            return [
                'success' => false,
                'message' => 'Share link not found'
            ];
        }

        // Check if user owns the file
        $file = $this->file_model->find($share_link['file_id']);
        if ($file['original_owner_id'] !== $user_id) {
            return [
                'success' => false,
                'message' => 'Permission denied'
            ];
        }

        $this->db->update(
            'file_share_links',
            ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')],
            'share_token = ?',
            [$share_token]
        );

        return [
            'success' => true,
            'message' => 'Share link deactivated successfully'
        ];
    }

    public function getShareStats($file_id, $user_id) {
        $file = $this->file_model->find($file_id);

        if (!$file || $file['original_owner_id'] !== $user_id) {
            return [];
        }

        return $this->db->selectOne("
            SELECT
                COUNT(*) as total_links,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_links,
                SUM(view_count) as total_views,
                SUM(download_count) as total_downloads,
                MAX(created_at) as last_shared
            FROM file_share_links
            WHERE file_id = ?
        ", [$file_id]);
    }

    public function createEmbedCode($file_id, $user_id, $options = []) {
        $file = $this->file_model->find($file_id);

        if (!$file || $file['original_owner_id'] !== $user_id) {
            return [
                'success' => false,
                'message' => 'Permission denied'
            ];
        }

        $width = $options['width'] ?? 600;
        $height = $options['height'] ?? 400;
        $show_download = $options['show_download'] ?? false;

        $embed_code = sprintf(
            '<iframe src="%s/shared.php?token=%s&embed=1" width="%d" height="%d" frameborder="0" allowfullscreen></iframe>',
            config('app.url'),
            $this->generateEmbedToken($file_id),
            $width,
            $height
        );

        return [
            'success' => true,
            'embed_code' => $embed_code,
            'preview_url' => config('app.url') . "/shared.php?token=" . $this->generateEmbedToken($file_id) . "&embed=1"
        ];
    }

    private function canShareFile($file, $user_id) {
        // Only owner can share their files
        return $file['original_owner_id'] === $user_id;
    }

    private function getShareLink($share_token) {
        return $this->db->selectOne("
            SELECT * FROM file_share_links WHERE share_token = ?
        ", [$share_token]);
    }

    private function incrementShareViewCount($share_token) {
        $this->db->query(
            "UPDATE file_share_links SET view_count = view_count + 1 WHERE share_token = ?",
            [$share_token]
        );
    }

    private function incrementShareDownloadCount($share_token) {
        $this->db->query(
            "UPDATE file_share_links SET download_count = download_count + 1 WHERE share_token = ?",
            [$share_token]
        );
    }

    private function generateEmbedToken($file_id) {
        // Generate a simple token for embedding (less secure but simpler)
        return substr(md5($file_id . config('app.key')), 0, 16);
    }

    public function cleanupExpiredLinks() {
        $this->db->delete('file_share_links', 'expires_at < NOW() OR (max_views IS NOT NULL AND view_count >= max_views)');
    }

    public function getSharedFilePreview($share_token) {
        $share_link = $this->getShareLink($share_token);

        if (!$share_link || !$share_link['is_active']) {
            return [
                'success' => false,
                'message' => 'Invalid share link'
            ];
        }

        $file = $this->file_model->getWithDetails($share_link['file_id']);

        if (!$file || !$file['is_available']) {
            return [
                'success' => false,
                'message' => 'File not available'
            ];
        }

        // Check if requires password
        if ($share_link['password']) {
            return [
                'success' => false,
                'message' => 'Password required',
                'requires_password' => true
            ];
        }

        return [
            'success' => true,
            'file' => [
                'id' => $file['id'],
                'filename' => $file['filename'],
                'description' => $file['description'],
                'file_size' => $file['file_size'],
                'file_type' => $file['file_type'],
                'owner_name' => $file['owner_name'],
                'created_at' => $file['created_at'],
                'preview_type' => $file['preview_type'],
                'preview_text' => $file['preview_text'],
                'preview_image' => $file['preview_image']
            ],
            'share_info' => [
                'access_level' => $share_link['access_level'],
                'expires_at' => $share_link['expires_at'],
                'view_count' => $share_link['view_count']
            ]
        ];
    }

    public function validateShareOptions($options) {
        $errors = [];

        if (isset($options['expires_days']) && (!is_numeric($options['expires_days']) || $options['expires_days'] < 1 || $options['expires_days'] > 365)) {
            $errors[] = 'Expiration days must be between 1 and 365';
        }

        if (isset($options['max_views']) && (!is_numeric($options['max_views']) || $options['max_views'] < 1)) {
            $errors[] = 'Maximum views must be greater than 0';
        }

        if (isset($options['max_downloads']) && (!is_numeric($options['max_downloads']) || $options['max_downloads'] < 1)) {
            $errors[] = 'Maximum downloads must be greater than 0';
        }

        if (isset($options['access_level']) && !in_array($options['access_level'], ['preview', 'download'])) {
            $errors[] = 'Invalid access level';
        }

        if (isset($options['password']) && strlen($options['password']) < 4) {
            $errors[] = 'Password must be at least 4 characters long';
        }

        return $errors;
    }

    public function getPopularSharedFiles($limit = 20) {
        return $this->db->select("
            SELECT
                f.*,
                u.username as owner_username,
                c.name as category_name,
                SUM(ssl.view_count) as total_views,
                SUM(ssl.download_count) as total_downloads,
                COUNT(ssl.id) as share_count
            FROM shared_files f
            LEFT JOIN login u ON f.original_owner_id = u.username
            LEFT JOIN file_categories c ON f.category_id = c.id
            LEFT JOIN file_share_links ssl ON f.id = ssl.file_id
            WHERE f.is_available = 1
            GROUP BY f.id
            HAVING total_views > 0
            ORDER BY total_views DESC, total_downloads DESC
            LIMIT ?
        ", [$limit]);
    }

    public function getShareAnalytics($file_id, $user_id) {
        $file = $this->file_model->find($file_id);

        if (!$file || $file['original_owner_id'] !== $user_id) {
            return [];
        }

        return $this->db->select("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as links_created,
                SUM(view_count) as total_views,
                SUM(download_count) as total_downloads
            FROM file_share_links
            WHERE file_id = ?
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ", [$file_id]);
    }
}
