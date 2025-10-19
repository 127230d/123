<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

requireLogin();

try {
    $file_id = intval($_GET['file_id'] ?? 0);
    $username = getCurrentUsername();

    if ($file_id <= 0) {
        die('معرف الملف غير صالح');
    }

    $file_query = "SELECT * FROM files WHERE file_id = ?";
    $file_stmt = $db->query($file_query, [$file_id]);
    $file = $file_stmt->get_result()->fetch_assoc();

    if (!$file) {
        die('الملف غير موجود');
    }

    $can_download = false;

    if ($file['owner_id'] === $username) {
        $can_download = true;
    } else {
        $purchase_check = "SELECT purchase_id FROM file_purchases WHERE file_id = ? AND buyer_id = ?";
        $purchase_stmt = $db->query($purchase_check, [$file_id, $username]);
        if ($purchase_stmt->get_result()->num_rows > 0) {
            $can_download = true;
        }
    }

    if (!$can_download) {
        die('ليس لديك صلاحية تحميل هذا الملف');
    }

    $file_path = __DIR__ . '/../' . $file['file_path'];

    if (!file_exists($file_path)) {
        die('الملف غير موجود على الخادم');
    }

    $log_download = "INSERT INTO activity_logs (user_id, action_type, action_details)
                    VALUES (?, 'file_download', ?)";
    $db->query($log_download, [$username, json_encode([
        'file_id' => $file_id,
        'title' => $file['title']
    ])]);

    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private');
    header('Pragma: private');

    readfile($file_path);
    exit;

} catch (Exception $e) {
    die('حدث خطأ: ' . $e->getMessage());
}
