<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json');

try {
    $file_id = intval($_GET['file_id'] ?? 0);

    if ($file_id <= 0) {
        throw new Exception('معرف الملف غير صالح');
    }

    $file_query = "SELECT
        f.*,
        u.full_name as owner_name,
        u.profile_image as owner_image
        FROM files f
        LEFT JOIN users u ON f.owner_id = u.username
        WHERE f.file_id = ?";

    $file_stmt = $db->query($file_query, [$file_id]);
    $file = $file_stmt->get_result()->fetch_assoc();

    if (!$file) {
        throw new Exception('الملف غير موجود');
    }

    if ($file['review_status'] !== 'approved' || !$file['is_active']) {
        if (!isLoggedIn() || (getCurrentUsername() !== $file['owner_id'] && !isAdmin())) {
            throw new Exception('هذا الملف غير متاح حالياً');
        }
    }

    $has_purchased = false;
    $user_rating = null;
    $user_review = null;

    if (isLoggedIn()) {
        $username = getCurrentUsername();

        $purchase_check = "SELECT purchase_id, purchased_at FROM file_purchases
                          WHERE file_id = ? AND buyer_id = ?";
        $purchase_stmt = $db->query($purchase_check, [$file_id, $username]);
        $purchase = $purchase_stmt->get_result()->fetch_assoc();
        $has_purchased = !empty($purchase);

        $rating_query = "SELECT rating_value, created_at FROM file_ratings
                        WHERE file_id = ? AND user_id = ?";
        $rating_stmt = $db->query($rating_query, [$file_id, $username]);
        $user_rating = $rating_stmt->get_result()->fetch_assoc();

        $review_query = "SELECT review_text, created_at, updated_at FROM file_reviews
                        WHERE file_id = ? AND user_id = ? AND review_status = 'visible'";
        $review_stmt = $db->query($review_query, [$file_id, $username]);
        $user_review = $review_stmt->get_result()->fetch_assoc();

        if ($file['owner_id'] !== $username && !$has_purchased) {
            $update_views = "UPDATE files SET total_views = total_views + 1 WHERE file_id = ?";
            $db->query($update_views, [$file_id]);
        }
    }

    $ratings_query = "SELECT
        rating_value,
        COUNT(*) as count,
        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM file_ratings WHERE file_id = ?)) as percentage
        FROM file_ratings
        WHERE file_id = ?
        GROUP BY rating_value
        ORDER BY rating_value DESC";

    $ratings_stmt = $db->query($ratings_query, [$file_id, $file_id]);
    $ratings_breakdown = [];
    while ($row = $ratings_stmt->get_result()->fetch_assoc()) {
        $ratings_breakdown[$row['rating_value']] = [
            'count' => $row['count'],
            'percentage' => round($row['percentage'], 1)
        ];
    }

    for ($i = 5; $i >= 1; $i--) {
        if (!isset($ratings_breakdown[$i])) {
            $ratings_breakdown[$i] = ['count' => 0, 'percentage' => 0];
        }
    }

    $reviews_query = "SELECT
        r.review_id,
        r.review_text,
        r.is_verified_purchase,
        r.helpful_count,
        r.created_at,
        r.updated_at,
        u.username,
        u.full_name,
        u.profile_image,
        fr.rating_value
        FROM file_reviews r
        LEFT JOIN users u ON r.user_id = u.username
        LEFT JOIN file_ratings fr ON r.file_id = fr.file_id AND r.user_id = fr.user_id
        WHERE r.file_id = ? AND r.review_status = 'visible'
        ORDER BY r.created_at DESC
        LIMIT 20";

    $reviews_stmt = $db->query($reviews_query, [$file_id]);
    $reviews = [];
    while ($row = $reviews_stmt->get_result()->fetch_assoc()) {
        $reviews[] = $row;
    }

    Response::success('تم جلب تفاصيل الملف بنجاح', [
        'file' => $file,
        'has_purchased' => $has_purchased,
        'user_rating' => $user_rating,
        'user_review' => $user_review,
        'ratings_breakdown' => $ratings_breakdown,
        'reviews' => $reviews,
        'is_owner' => isLoggedIn() && getCurrentUsername() === $file['owner_id']
    ]);

} catch (Exception $e) {
    Response::error($e->getMessage());
}
