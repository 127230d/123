<?php
/**
 * Enhanced File Sharing System - User Model
 * Handles user-related database operations
 */

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel {
    protected $table = 'login';
    protected $primary_key = 'id';
    protected $fillable = [
        'username', 'email', 'password', 'first_name', 'last_name',
        'avatar', 'phone', 'country', 'bio', 'website', 'points',
        'subscription', 'is_active', 'is_verified'
    ];
    protected $hidden = ['password'];

    public function __construct() {
        parent::__construct();
    }

    public function findByUsername($username) {
        return $this->findBy('username', $username);
    }

    public function findByEmail($email) {
        return $this->findBy('email', $email);
    }

    public function getProfile($username) {
        $user = $this->findByUsername($username);
        if (!$user) return null;

        // Get additional statistics
        $stats = $this->db->selectOne("
            SELECT
                (SELECT COUNT(*) FROM shared_files WHERE original_owner_id = ?) as files_uploaded,
                (SELECT COUNT(*) FROM file_purchases WHERE buyer_username = ?) as files_purchased,
                (SELECT COUNT(*) FROM file_purchases WHERE seller_username = ?) as files_sold,
                (SELECT COALESCE(SUM(price), 0) FROM file_purchases WHERE seller_username = ?) as total_earned,
                (SELECT COUNT(*) FROM file_ratings WHERE user_id = ?) as ratings_given,
                (SELECT AVG(rating) FROM file_ratings WHERE user_id = ?) as avg_rating_given
        ", [$username, $username, $username, $username, $username, $username]);

        return array_merge($user, $stats ?: []);
    }

    public function updatePoints($username, $points) {
        return $this->db->update(
            $this->table,
            ['points' => $points, 'updated_at' => date('Y-m-d H:i:s')],
            'username = ?',
            [$username]
        );
    }

    public function addPoints($username, $points_to_add) {
        $current_user = $this->findByUsername($username);
        if (!$current_user) return false;

        $new_points = max(0, $current_user['points'] + $points_to_add);
        return $this->updatePoints($username, $new_points);
    }

    public function deductPoints($username, $points_to_deduct) {
        $current_user = $this->findByUsername($username);
        if (!$current_user || $current_user['points'] < $points_to_deduct) {
            return false;
        }

        $new_points = $current_user['points'] - $points_to_deduct;
        return $this->updatePoints($username, $new_points);
    }

    public function getFavorites($username) {
        return $this->db->select("
            SELECT f.*, uf.created_at as favorited_at
            FROM shared_files f
            JOIN user_favorites uf ON f.id = uf.file_id
            WHERE uf.user_id = ?
            ORDER BY uf.created_at DESC
        ", [$username]);
    }

    public function addFavorite($username, $file_id) {
        if ($this->isFavorite($username, $file_id)) {
            return false;
        }

        return $this->db->insert('user_favorites', [
            'user_id' => $username,
            'file_id' => $file_id
        ]);
    }

    public function removeFavorite($username, $file_id) {
        return $this->db->delete('user_favorites', 'user_id = ? AND file_id = ?', [$username, $file_id]);
    }

    public function isFavorite($username, $file_id) {
        return $this->db->exists('user_favorites', 'user_id = ? AND file_id = ?', [$username, $file_id]);
    }

    public function getPurchases($username) {
        return $this->db->select("
            SELECT
                f.*,
                p.purchase_date,
                p.price as paid_price,
                p.payment_status,
                p.download_count as purchase_downloads
            FROM shared_files f
            JOIN file_purchases p ON f.id = p.file_id
            WHERE p.buyer_username = ?
            ORDER BY p.purchase_date DESC
        ", [$username]);
    }

    public function getSales($username) {
        return $this->db->select("
            SELECT
                f.*,
                p.purchase_date,
                p.price,
                p.buyer_username,
                p.payment_status
            FROM shared_files f
            JOIN file_purchases p ON f.id = p.file_id
            WHERE p.seller_username = ?
            ORDER BY p.purchase_date DESC
        ", [$username]);
    }

    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public function createSession($username, $ip_address = null, $user_agent = null) {
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + config('security.session_lifetime'));

        $this->db->insert('user_sessions', [
            'user_id' => $username,
            'session_token' => $token,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'expires_at' => $expires_at
        ]);

        return $token;
    }

    public function validateSession($token) {
        $session = $this->db->selectOne("
            SELECT * FROM user_sessions
            WHERE session_token = ? AND expires_at > NOW()
        ", [$token]);

        return $session ?: false;
    }

    public function destroySession($token) {
        return $this->db->delete('user_sessions', 'session_token = ?', [$token]);
    }

    public function cleanupExpiredSessions() {
        return $this->db->delete('user_sessions', 'expires_at <= NOW()');
    }
}
