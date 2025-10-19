<?php
session_start();
require_once 'dssssssssb.php';

// Set response headers
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    if (!isset($_SESSION["username"])) {
        $response['message'] = 'يجب تسجيل الدخول أولاً';
        echo json_encode($response);
        exit();
    }
    
    // Validate and sanitize input
    $content = trim(filter_input(INPUT_POST, 'content', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $user_id = $_SESSION["username"];
    
    // Check content length
    if (empty($content)) {
        $response['message'] = 'يرجى كتابة محتوى التعليق';
        echo json_encode($response);
        exit();
    }
    
    if (strlen($content) > 1000) {
        $response['message'] = 'محتوى التعليق طويل جداً (الحد الأقصى 1000 حرف)';
        echo json_encode($response);
        exit();
    }
    
    // Handle file upload
    $file_path = '';
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar', '7z', 'sql', 'json', 'csv', 'xls', 'xlsx', 'ppt', 'pptx', 'mp3', 'mp4', 'exe', 'dll'];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/';
        
        // Create uploads directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $response['message'] = 'خطأ في إنشاء مجلد الملفات';
                echo json_encode($response);
                exit();
            }
        }
        
        $file_info = pathinfo($_FILES['file']['name']);
        $file_extension = strtolower($file_info['extension']);
        $file_size = $_FILES['file']['size'];
        
        // Validate file extension
        if (!in_array($file_extension, $allowed_extensions)) {
            $response['message'] = 'نوع الملف غير مسموح. الأنواع المسموحة: ' . implode(', ', $allowed_extensions);
            echo json_encode($response);
            exit();
        }
        
        // Validate file size
        if ($file_size > $max_file_size) {
            $response['message'] = 'حجم الملف كبير جداً. الحد الأقصى 5MB';
            echo json_encode($response);
            exit();
        }
        
        // Generate unique filename
        $file_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;
        $relative_path = 'uploads/' . $file_name;
        
        // Move uploaded file
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
            $response['message'] = 'خطأ في رفع الملف';
            echo json_encode($response);
            exit();
        }
        
        $file_path = $relative_path;
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle upload errors
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $response['message'] = 'حجم الملف كبير جداً';
                break;
            case UPLOAD_ERR_PARTIAL:
                $response['message'] = 'لم يتم رفع الملف بالكامل';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $response['message'] = 'مجلد الملفات المؤقتة غير موجود';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $response['message'] = 'خطأ في كتابة الملف';
                break;
            default:
                $response['message'] = 'خطأ غير معروف في رفع الملف';
        }
        echo json_encode($response);
        exit();
    }
    
    // Check for spam (simple rate limiting)
    $spam_check = mysqli_prepare($con, "SELECT COUNT(*) as count FROM comments WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    mysqli_stmt_bind_param($spam_check, "s", $user_id);
    mysqli_stmt_execute($spam_check);
    $spam_result = mysqli_stmt_get_result($spam_check);
    $spam_data = mysqli_fetch_assoc($spam_result);
    
    if ($spam_data['count'] >= 3) {
        $response['message'] = 'يرجى الانتظار قبل إرسال تعليق آخر (حد أقصى 3 تعليقات في الدقيقة)';
        echo json_encode($response);
        exit();
    }
    mysqli_stmt_close($spam_check);
    
    // Insert comment into database
    $stmt = mysqli_prepare($con, "INSERT INTO comments (user_id, content, file_path, is_approved, created_at) VALUES (?, ?, ?, 0, NOW())");
    
    if (!$stmt) {
        $response['message'] = 'خطأ في إعداد قاعدة البيانات: ' . mysqli_error($con);
        echo json_encode($response);
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "sss", $user_id, $content, $file_path);
    
    if (mysqli_stmt_execute($stmt)) {
        $comment_id = mysqli_insert_id($con);
        
        // Log the action for admin monitoring
        $log_stmt = mysqli_prepare($con, "INSERT INTO admin_logs (action, user_id, details, created_at) VALUES ('new_comment', ?, CONCAT('Comment ID: ', ?), NOW())");
        if ($log_stmt) {
            mysqli_stmt_bind_param($log_stmt, "si", $user_id, $comment_id);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        }
        
        $response['success'] = true;
        $response['message'] = 'تم إرسال التعليق بنجاح! سيتم مراجعته من قبل المشرف قبل النشر.';
        $response['comment_id'] = $comment_id;
    } else {
        // If database insert failed and file was uploaded, delete the file
        if ($file_path && file_exists($upload_dir . basename($file_path))) {
            unlink($upload_dir . basename($file_path));
        }
        
        $response['message'] = 'خطأ في حفظ التعليق: ' . mysqli_error($con);
    }
    
    mysqli_stmt_close($stmt);
} else {
    $response['message'] = 'طريقة الطلب غير صحيحة';
}

// Send response
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>