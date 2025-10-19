<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../utils/Response.php';

header('Content-Type: application/json');

try {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $category = $_GET['category'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $sort = $_GET['sort'] ?? 'newest';
    $min_price = floatval($_GET['min_price'] ?? 0);
    $max_price = floatval($_GET['max_price'] ?? 10000);

    $where_conditions = ["f.is_active = 1", "f.review_status = 'approved'"];
    $params = [];

    if (!empty($category)) {
        $where_conditions[] = "f.category = ?";
        $params[] = $category;
    }

    if (!empty($search)) {
        $where_conditions[] = "(f.title LIKE ? OR f.description LIKE ? OR f.tags LIKE ?)";
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if ($min_price > 0) {
        $where_conditions[] = "f.final_price >= ?";
        $params[] = $min_price;
    }

    if ($max_price < 10000) {
        $where_conditions[] = "f.final_price <= ?";
        $params[] = $max_price;
    }

    $where_clause = implode(' AND ', $where_conditions);

    $order_by = match($sort) {
        'price_low' => 'f.final_price ASC',
        'price_high' => 'f.final_price DESC',
        'rating' => 'f.average_rating DESC, f.total_ratings DESC',
        'popular' => 'f.total_sales DESC, f.total_views DESC',
        'oldest' => 'f.created_at ASC',
        default => 'f.created_at DESC'
    };

    $count_query = "SELECT COUNT(*) as total FROM files f WHERE $where_clause";
    $count_stmt = $db->query($count_query, $params);
    $total_files = $count_stmt->get_result()->fetch_assoc()['total'];

    $files_query = "SELECT
        f.file_id,
        f.title,
        f.description,
        f.category,
        f.tags,
        f.preview_type,
        f.preview_text,
        f.preview_image,
        f.price,
        f.final_price,
        f.discount_percentage,
        f.file_type,
        f.file_extension,
        f.file_size,
        f.owner_id,
        f.total_downloads,
        f.total_views,
        f.total_sales,
        f.average_rating,
        f.total_ratings,
        f.total_reviews,
        f.is_featured,
        f.created_at,
        u.full_name as owner_name,
        u.profile_image as owner_image
        FROM files f
        LEFT JOIN users u ON f.owner_id = u.username
        WHERE $where_clause
        ORDER BY $order_by
        LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;

    $files_stmt = $db->query($files_query, $params);
    $files = [];

    while ($row = $files_stmt->get_result()->fetch_assoc()) {
        if (isLoggedIn()) {
            $username = getCurrentUsername();
            $purchase_check = "SELECT purchase_id FROM file_purchases WHERE file_id = ? AND buyer_id = ?";
            $purchase_stmt = $db->query($purchase_check, [$row['file_id'], $username]);
            $row['user_purchased'] = $purchase_stmt->get_result()->num_rows > 0;
            $row['is_owner'] = $row['owner_id'] === $username;
        } else {
            $row['user_purchased'] = false;
            $row['is_owner'] = false;
        }

        $files[] = $row;
    }

    $total_pages = ceil($total_files / $limit);

    Response::success('تم جلب الملفات بنجاح', [
        'files' => $files,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_files' => $total_files,
            'per_page' => $limit,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        ]
    ]);

} catch (Exception $e) {
    Response::error($e->getMessage());
}
