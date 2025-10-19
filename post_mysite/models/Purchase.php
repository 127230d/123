<?php
/**
 * Enhanced File Sharing System - Purchase Model
 * Handles purchase-related database operations
 */

require_once __DIR__ . '/BaseModel.php';

class Purchase extends BaseModel {
    protected $table = 'file_purchases';
    protected $primary_key = 'id';
    protected $fillable = [
        'file_id', 'buyer_username', 'seller_username', 'price',
        'payment_method', 'transaction_id', 'payment_status',
        'download_expires_at', 'download_count', 'refund_reason',
        'refund_date', 'metadata'
    ];

    public function __construct() {
        parent::__construct();
    }

    public function createPurchase($file_id, $buyer_username, $seller_username, $price, $payment_data = []) {
        $purchase_data = [
            'file_id' => $file_id,
            'buyer_username' => $buyer_username,
            'seller_username' => $seller_username,
            'price' => $price,
            'payment_method' => $payment_data['payment_method'] ?? 'points',
            'transaction_id' => $payment_data['transaction_id'] ?? null,
            'payment_status' => $payment_data['payment_status'] ?? 'completed',
            'download_expires_at' => isset($payment_data['download_expires_days'])
                ? date('Y-m-d H:i:s', time() + ($payment_data['download_expires_days'] * 24 * 3600))
                : null,
            'metadata' => isset($payment_data['metadata']) ? json_encode($payment_data['metadata']) : null
        ];

        return $this->db->insert($this->table, $purchase_data);
    }

    public function getUserPurchases($username, $limit = null) {
        $sql = "
            SELECT
                p.*,
                f.filename,
                f.file_type,
                f.file_size,
                f.description,
                u.username as seller_username,
                u.first_name as seller_first_name,
                u.last_name as seller_last_name
            FROM {$this->table} p
            JOIN shared_files f ON p.file_id = f.id
            JOIN login u ON p.seller_username = u.username
            WHERE p.buyer_username = ?
            ORDER BY p.purchase_date DESC
        ";

        if ($limit) {
            $sql .= " LIMIT ?";
            return $this->db->select($sql, [$username, $limit]);
        }

        return $this->db->select($sql, [$username]);
    }

    public function getUserSales($username, $limit = null) {
        $sql = "
            SELECT
                p.*,
                f.filename,
                f.file_type,
                f.file_size,
                f.description,
                u.username as buyer_username,
                u.first_name as buyer_first_name,
                u.last_name as buyer_last_name
            FROM {$this->table} p
            JOIN shared_files f ON p.file_id = f.id
            JOIN login u ON p.buyer_username = u.username
            WHERE p.seller_username = ?
            ORDER BY p.purchase_date DESC
        ";

        if ($limit) {
            $sql .= " LIMIT ?";
            return $this->db->select($sql, [$username, $limit]);
        }

        return $this->db->select($sql, [$username]);
    }

    public function getPurchase($purchase_id) {
        return $this->db->selectOne("
            SELECT
                p.*,
                f.filename,
                f.file_path,
                f.file_type,
                f.file_size,
                f.description,
                u1.username as buyer_username,
                u1.first_name as buyer_first_name,
                u1.last_name as buyer_last_name,
                u2.username as seller_username,
                u2.first_name as seller_first_name,
                u2.last_name as seller_last_name
            FROM {$this->table} p
            JOIN shared_files f ON p.file_id = f.id
            JOIN login u1 ON p.buyer_username = u1.username
            JOIN login u2 ON p.seller_username = u2.username
            WHERE p.id = ?
        ", [$purchase_id]);
    }

    public function canDownload($purchase_id, $username) {
        $purchase = $this->find($purchase_id);

        if (!$purchase || $purchase['buyer_username'] !== $username) {
            return false;
        }

        if ($purchase['payment_status'] !== 'completed') {
            return false;
        }

        if ($purchase['download_expires_at'] && strtotime($purchase['download_expires_at']) < time()) {
            return false;
        }

        return true;
    }

    public function recordDownload($purchase_id) {
        return $this->db->update(
            $this->table,
            [
                'download_count' => $this->db->selectOne("SELECT download_count FROM {$this->table} WHERE id = ?", [$purchase_id])['download_count'] + 1,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$purchase_id]
        );
    }

    public function processRefund($purchase_id, $reason, $admin_username = null) {
        $purchase = $this->find($purchase_id);
        if (!$purchase) {
            return false;
        }

        // Update purchase status
        $this->db->update(
            $this->table,
            [
                'payment_status' => 'refunded',
                'refund_reason' => $reason,
                'refund_date' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$purchase_id]
        );

        // Refund points to buyer
        $user_model = new User();
        $user_model->addPoints($purchase['buyer_username'], $purchase['price']);

        // Log the refund
        $this->logTransaction($purchase['buyer_username'], 'refund', $purchase['price'], [
            'purchase_id' => $purchase_id,
            'reason' => $reason,
            'admin' => $admin_username
        ]);

        return true;
    }

    public function getTransactionHistory($username, $type = null, $limit = 50) {
        $conditions = [];
        $params = [];

        if ($type) {
            if ($type === 'sales') {
                $conditions[] = 'p.seller_username = ?';
                $params[] = $username;
            } elseif ($type === 'purchases') {
                $conditions[] = 'p.buyer_username = ?';
                $params[] = $username;
            }
        } else {
            $conditions[] = '(p.seller_username = ? OR p.buyer_username = ?)';
            $params[] = $username;
            $params[] = $username;
        }

        $where_clause = implode(' AND ', $conditions);

        return $this->db->select("
            SELECT
                p.*,
                f.filename,
                f.file_type,
                CASE
                    WHEN p.seller_username = ? THEN 'sale'
                    ELSE 'purchase'
                END as transaction_type,
                CASE
                    WHEN p.seller_username = ? THEN u2.username
                    ELSE u1.username
                END as other_party
            FROM {$this->table} p
            JOIN shared_files f ON p.file_id = f.id
            LEFT JOIN login u1 ON p.buyer_username = u1.username
            LEFT JOIN login u2 ON p.seller_username = u2.username
            WHERE {$where_clause}
            ORDER BY p.purchase_date DESC
            LIMIT ?
        ", array_merge($params, [$username, $username, $limit]));
    }

    public function getRevenueStats($username) {
        $stats = $this->db->selectOne("
            SELECT
                COUNT(*) as total_sales,
                SUM(price) as total_revenue,
                AVG(price) as avg_sale_price,
                COUNT(DISTINCT buyer_username) as unique_buyers,
                COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as completed_sales,
                COUNT(CASE WHEN payment_status = 'refunded' THEN 1 END) as refunded_sales
            FROM {$this->table}
            WHERE seller_username = ?
        ", [$username]);

        return $stats ?: [
            'total_sales' => 0,
            'total_revenue' => 0,
            'avg_sale_price' => 0,
            'unique_buyers' => 0,
            'completed_sales' => 0,
            'refunded_sales' => 0
        ];
    }

    public function getPurchaseStats($username) {
        $stats = $this->db->selectOne("
            SELECT
                COUNT(*) as total_purchases,
                SUM(price) as total_spent,
                AVG(price) as avg_purchase_price,
                COUNT(DISTINCT seller_username) as unique_sellers,
                COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as completed_purchases,
                COUNT(CASE WHEN payment_status = 'refunded' THEN 1 END) as refunded_purchases
            FROM {$this->table}
            WHERE buyer_username = ?
        ", [$username]);

        return $stats ?: [
            'total_purchases' => 0,
            'total_spent' => 0,
            'avg_purchase_price' => 0,
            'unique_sellers' => 0,
            'completed_purchases' => 0,
            'refunded_purchases' => 0
        ];
    }

    private function logTransaction($username, $type, $amount, $metadata = []) {
        // This could be expanded to log all transactions for audit purposes
        $this->db->insert('audit_logs', [
            'user_id' => $username,
            'action' => "transaction_{$type}",
            'new_values' => json_encode([
                'amount' => $amount,
                'metadata' => $metadata,
                'timestamp' => date('Y-m-d H:i:s')
            ])
        ]);
    }

    public function validatePurchase($file_id, $buyer_username) {
        $errors = [];

        // Check if file exists and is available
        $file_model = new File();
        $file = $file_model->find($file_id);

        if (!$file) {
            $errors[] = 'File not found';
        } elseif (!$file['is_available']) {
            $errors[] = 'File is not available for purchase';
        }

        // Check if user exists and has enough points
        $user_model = new User();
        $buyer = $user_model->findByUsername($buyer_username);

        if (!$buyer) {
            $errors[] = 'Buyer not found';
        } elseif ($buyer['points'] < $file['price']) {
            $errors[] = 'Insufficient points for purchase';
        }

        // Check if user is trying to buy their own file
        if ($file && $file['original_owner_id'] === $buyer_username) {
            $errors[] = 'Cannot purchase your own file';
        }

        // Check if already purchased
        if ($file && $buyer) {
            $existing_purchase = $this->db->selectOne(
                "SELECT id FROM {$this->table} WHERE file_id = ? AND buyer_username = ?",
                [$file_id, $buyer_username]
            );

            if ($existing_purchase) {
                $errors[] = 'File already purchased';
            }
        }

        return $errors;
    }
}
