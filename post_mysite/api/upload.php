<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../utils/FileValidator.php';
require_once __DIR__ . '/../utils/FileUploader.php';
require_once __DIR__ . '/../utils/Response.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Invalid request method');
}

try {
    $validator = new FileValidator();
    $uploader = new FileUploader();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $preview_type = $_POST['preview_type'] ?? '';

    if (empty($title)) {
        throw new Exception('عنوان الملف مطلوب');
    }

    if (strlen($title) < 3 || strlen($title) > 255) {
        throw new Exception('عنوان الملف يجب أن يكون بين 3 و 255 حرف');
    }

    if ($price < 0 || $price > 10000) {
        throw new Exception('السعر يجب أن يكون بين 0 و 10000');
    }

    if (!in_array($preview_type, ['text', 'image'])) {
        throw new Exception('نوع المعاينة غير صالح');
    }

    $preview_text = null;
    $preview_image = null;

    if ($preview_type === 'text') {
        $preview_text = trim($_POST['preview_text'] ?? '');
        if (empty($preview_text) || strlen($preview_text) < 10) {
            throw new Exception('نص المعاينة يجب أن يكون 10 أحرف على الأقل');
        }
        if (strlen($preview_text) > 1000) {
            throw new Exception('نص المعاينة يجب أن يكون أقل من 1000 حرف');
        }
    } else {
        if (!isset($_FILES['preview_image']) || $_FILES['preview_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('صورة المعاينة مطلوبة');
        }

        $preview_image_result = $uploader->uploadPreviewImage($_FILES['preview_image']);
        $preview_image = $preview_image_result['filename'];
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('الملف مطلوب');
    }

    $validator->validateFile($_FILES['file']);

    $upload_result = $uploader->uploadFile($_FILES['file']);

    $username = getCurrentUsername();

    $sql = "INSERT INTO files (
        original_filename, stored_filename, file_path, file_size, file_type,
        file_extension, mime_type, title, description, category, tags,
        preview_type, preview_text, preview_image, price, final_price,
        owner_id, review_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

    $stmt = $db->query($sql, [
        $upload_result['original_filename'],
        $upload_result['stored_filename'],
        $upload_result['file_path'],
        $upload_result['file_size'],
        $upload_result['file_type'],
        $upload_result['file_extension'],
        $upload_result['mime_type'],
        $title,
        $description,
        $category,
        $tags,
        $preview_type,
        $preview_text,
        $preview_image,
        $price,
        $price,
        $username
    ]);

    $file_id = $db->lastInsertId();

    $log_sql = "INSERT INTO activity_logs (user_id, action_type, action_details)
                VALUES (?, 'file_upload', ?)";
    $db->query($log_sql, [$username, json_encode(['file_id' => $file_id, 'title' => $title])]);

    $update_user = "UPDATE users SET total_uploads = total_uploads + 1 WHERE username = ?";
    $db->query($update_user, [$username]);

    Response::success('تم رفع الملف بنجاح! في انتظار مراجعة الإدارة', [
        'file_id' => $file_id
    ]);

} catch (Exception $e) {
    Response::error($e->getMessage());
}
