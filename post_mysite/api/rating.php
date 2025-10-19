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
    $rating_value = intval($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review'] ?? '');
    $username = getCurrentUsername();

    if ($file_id <= 0) {
        throw new Exception('معرف الملف غير صالح');
    }

    if ($rating_value < 1 || $rating_value > 5) {
        throw new Exception('التقييم يجب أن يكون بين 1 و 5 نجوم');
    }

    $file_query = "SELECT owner_id FROM files WHERE file_id = ? AND is_active = 1";
    $file_stmt = $db->query($file_query, [$file_id]);
    $file = $file_stmt->get_result()->fetch_assoc();

    if (!$file) {
        throw new Exception('الملف غير موجود');
    }

    if ($file['owner_id'] === $username) {
        throw new Exception('لا يمكنك تقييم ملفك الخاص');
    }

    $purchase_check = "SELECT purchase_id FROM file_purchases WHERE file_id = ? AND buyer_id = ?";
    $purchase_stmt = $db->query($purchase_check, [$file_id, $username]);
    $has_purchased = $purchase_stmt->get_result()->num_rows > 0;

    $existing_rating = "SELECT rating_id FROM file_ratings WHERE file_id = ? AND user_id = ?";
    $existing_stmt = $db->query($existing_rating, [$file_id, $username]);
    $rating_exists = $existing_stmt->get_result()->num_rows > 0;

    $db->beginTransaction();

    try {
        if ($rating_exists) {
            $update_rating = "UPDATE file_ratings SET rating_value = ?, updated_at = NOW()
                             WHERE file_id = ? AND user_id = ?";
            $db->query($update_rating, [$rating_value, $file_id, $username]);
            $message = 'تم تحديث تقييمك بنجاح';
        } else {
            $insert_rating = "INSERT INTO file_ratings (file_id, user_id, rating_value)
                             VALUES (?, ?, ?)";
            $db->query($insert_rating, [$file_id, $username, $rating_value]);
            $message = 'تم إضافة تقييمك بنجاح';
        }

        if (!empty($review_text)) {
            if (strlen($review_text) < 10) {
                throw new Exception('التعليق يجب أن يكون 10 أحرف على الأقل');
            }

            if (strlen($review_text) > 1000) {
                throw new Exception('التعليق يجب أن يكون أقل من 1000 حرف');
            }

            $existing_review = "SELECT review_id FROM file_reviews WHERE file_id = ? AND user_id = ?";
            $review_stmt = $db->query($existing_review, [$file_id, $username]);
            $review_exists = $review_stmt->get_result()->num_rows > 0;

            if ($review_exists) {
                $update_review = "UPDATE file_reviews SET review_text = ?, updated_at = NOW()
                                 WHERE file_id = ? AND user_id = ?";
                $db->query($update_review, [$review_text, $file_id, $username]);
            } else {
                $insert_review = "INSERT INTO file_reviews
                                 (file_id, user_id, review_text, is_verified_purchase)
                                 VALUES (?, ?, ?, ?)";
                $db->query($insert_review, [$file_id, $username, $review_text, $has_purchased]);
            }

            $message = 'تم إضافة تقييمك وتعليقك بنجاح';
        }

        $stats_query = "SELECT
                        COUNT(*) as total_ratings,
                        AVG(rating_value) as avg_rating
                        FROM file_ratings
                        WHERE file_id = ?";
        $stats_stmt = $db->query($stats_query, [$file_id]);
        $stats = $stats_stmt->get_result()->fetch_assoc();

        $review_count_query = "SELECT COUNT(*) as total_reviews FROM file_reviews WHERE file_id = ?";
        $review_count_stmt = $db->query($review_count_query, [$file_id]);
        $review_count = $review_count_stmt->get_result()->fetch_assoc();

        $update_file = "UPDATE files SET
                       average_rating = ?,
                       total_ratings = ?,
                       total_reviews = ?
                       WHERE file_id = ?";
        $db->query($update_file, [
            round($stats['avg_rating'], 2),
            $stats['total_ratings'],
            $review_count['total_reviews'],
            $file_id
        ]);

        $log_activity = "INSERT INTO activity_logs (user_id, action_type, action_details)
                        VALUES (?, 'file_rating', ?)";
        $db->query($log_activity, [$username, json_encode([
            'file_id' => $file_id,
            'rating' => $rating_value,
            'has_review' => !empty($review_text)
        ])]);

        $db->commit();

        Response::success($message, [
            'new_average' => round($stats['avg_rating'], 2),
            'total_ratings' => $stats['total_ratings'],
            'total_reviews' => $review_count['total_reviews']
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    Response::error($e->getMessage());
}
