<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../utils/Response.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Invalid request method');
}

try {
    $file_id = intval($_POST['file_id'] ?? 0);
    $buyer_username = getCurrentUsername();

    if ($file_id <= 0) {
        throw new Exception('معرف الملف غير صالح');
    }

    $file_query = "SELECT * FROM files WHERE file_id = ? AND is_active = 1 AND review_status = 'approved'";
    $file_stmt = $db->query($file_query, [$file_id]);
    $file = $file_stmt->get_result()->fetch_assoc();

    if (!$file) {
        throw new Exception('الملف غير موجود أو غير متاح');
    }

    if ($file['owner_id'] === $buyer_username) {
        throw new Exception('لا يمكنك شراء ملفك الخاص');
    }

    $check_purchase = "SELECT purchase_id FROM file_purchases WHERE file_id = ? AND buyer_id = ?";
    $check_stmt = $db->query($check_purchase, [$file_id, $buyer_username]);
    if ($check_stmt->get_result()->num_rows > 0) {
        throw new Exception('لقد قمت بشراء هذا الملف مسبقاً');
    }

    $buyer_query = "SELECT balance FROM users WHERE username = ?";
    $buyer_stmt = $db->query($buyer_query, [$buyer_username]);
    $buyer = $buyer_stmt->get_result()->fetch_assoc();

    if (!$buyer) {
        throw new Exception('المشتري غير موجود');
    }

    $purchase_price = $file['final_price'];

    if ($buyer['balance'] < $purchase_price) {
        throw new Exception('رصيدك غير كافٍ لإتمام عملية الشراء');
    }

    $db->beginTransaction();

    try {
        $commission_rate = 0.10;
        $commission_amount = $purchase_price * $commission_rate;
        $seller_amount = $purchase_price - $commission_amount;

        $transaction_ref = 'PUR_' . time() . '_' . bin2hex(random_bytes(8));

        $purchase_sql = "INSERT INTO file_purchases (
            file_id, buyer_id, seller_id, purchase_price, commission_amount,
            seller_amount, transaction_reference, payment_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')";

        $db->query($purchase_sql, [
            $file_id,
            $buyer_username,
            $file['owner_id'],
            $purchase_price,
            $commission_amount,
            $seller_amount,
            $transaction_ref
        ]);

        $purchase_id = $db->lastInsertId();

        $new_buyer_balance = $buyer['balance'] - $purchase_price;
        $update_buyer = "UPDATE users SET balance = ?, total_purchases = total_purchases + 1 WHERE username = ?";
        $db->query($update_buyer, [$new_buyer_balance, $buyer_username]);

        $buyer_transaction = "INSERT INTO transactions (
            transaction_reference, user_id, transaction_type, amount,
            balance_before, balance_after, related_file_id, related_purchase_id,
            description
        ) VALUES (?, ?, 'purchase', ?, ?, ?, ?, ?, ?)";

        $db->query($buyer_transaction, [
            $transaction_ref . '_BUY',
            $buyer_username,
            -$purchase_price,
            $buyer['balance'],
            $new_buyer_balance,
            $file_id,
            $purchase_id,
            'شراء ملف: ' . $file['title']
        ]);

        $seller_query = "SELECT balance FROM users WHERE username = ?";
        $seller_stmt = $db->query($seller_query, [$file['owner_id']]);
        $seller = $seller_stmt->get_result()->fetch_assoc();

        $new_seller_balance = $seller['balance'] + $seller_amount;
        $update_seller = "UPDATE users SET balance = ?, total_sales = total_sales + 1 WHERE username = ?";
        $db->query($update_seller, [$new_seller_balance, $file['owner_id']]);

        $seller_transaction = "INSERT INTO transactions (
            transaction_reference, user_id, transaction_type, amount,
            balance_before, balance_after, related_file_id, related_purchase_id,
            description
        ) VALUES (?, ?, 'sale', ?, ?, ?, ?, ?, ?)";

        $db->query($seller_transaction, [
            $transaction_ref . '_SELL',
            $file['owner_id'],
            $seller_amount,
            $seller['balance'],
            $new_seller_balance,
            $file_id,
            $purchase_id,
            'بيع ملف: ' . $file['title']
        ]);

        $update_file = "UPDATE files SET
            total_sales = total_sales + 1,
            total_downloads = total_downloads + 1,
            total_revenue = total_revenue + ?
            WHERE file_id = ?";
        $db->query($update_file, [$purchase_price, $file_id]);

        $log_buyer = "INSERT INTO activity_logs (user_id, action_type, action_details)
                      VALUES (?, 'file_purchase', ?)";
        $db->query($log_buyer, [$buyer_username, json_encode([
            'file_id' => $file_id,
            'title' => $file['title'],
            'price' => $purchase_price
        ])]);

        $log_seller = "INSERT INTO activity_logs (user_id, action_type, action_details)
                       VALUES (?, 'file_sold', ?)";
        $db->query($log_seller, [$file['owner_id'], json_encode([
            'file_id' => $file_id,
            'title' => $file['title'],
            'amount' => $seller_amount,
            'buyer' => $buyer_username
        ])]);

        $db->commit();

        updateSessionBalance($new_buyer_balance);

        Response::success('تم شراء الملف بنجاح! يمكنك تحميله الآن', [
            'purchase_id' => $purchase_id,
            'new_balance' => $new_buyer_balance,
            'download_url' => '/api/download.php?file_id=' . $file_id
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    Response::error($e->getMessage());
}
