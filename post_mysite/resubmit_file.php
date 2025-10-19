<?php
session_start();
require_once 'dssssssssb.php';

// Only handle AJAX requests
if (!isset($_POST['ajax']) || $_POST['ajax'] != '1') {
    http_response_code(404);
    die('Not Found');
}

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مسموح']);
    exit;
}

if (!isset($_POST['file_id']) || !is_numeric($_POST['file_id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف الملف غير صحيح']);
    exit;
}

$file_id = intval($_POST['file_id']);
$username = $_SESSION['username'];

try {
    // التحقق من أن الملف ملك للمستخدم ومرفوض
    $check_query = "SELECT * FROM shared_files WHERE id = ? AND original_owner_id = ? AND review_status = 'rejected'";
    $check_stmt = mysqli_prepare($con, $check_query);
    mysqli_stmt_bind_param($check_stmt, "is", $file_id, $username);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['success' => false, 'message' => 'الملف غير موجود أو لا يمكن إعادة رفعه']);
        exit;
    }
    
    // التحقق من الأعمدة الموجودة أولاً
    $columns_check = mysqli_query($con, "SHOW COLUMNS FROM shared_files");
    $existing_columns = [];
    while ($row = mysqli_fetch_assoc($columns_check)) {
        $existing_columns[] = $row['Field'];
    }
    
    // بناء الاستعلام بناءً على الأعمدة المتاحة
    $update_fields = "review_status = 'pending', rejection_reason = NULL";
    
    if (in_array('rejection_date', $existing_columns)) {
        $update_fields .= ", rejection_date = NULL";
    }
    if (in_array('reviewer_name', $existing_columns)) {
        $update_fields .= ", reviewer_name = NULL";
    }
    if (in_array('reviewed_at', $existing_columns)) {
        $update_fields .= ", reviewed_at = NULL";
    }
    if (in_array('updated_at', $existing_columns)) {
        $update_fields .= ", updated_at = NOW()";
    }
    
    // إعادة تعيين حالة الملف إلى "في المراجعة"
    $update_query = "UPDATE shared_files SET $update_fields WHERE id = ? AND original_owner_id = ?";
    
    $update_stmt = mysqli_prepare($con, $update_query);
    mysqli_stmt_bind_param($update_stmt, "is", $file_id, $username);
    
    if (mysqli_stmt_execute($update_stmt)) {
        if (mysqli_stmt_affected_rows($update_stmt) > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'تم إعادة إرسال الملف للمراجعة بنجاح'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'لم يتم تحديث حالة الملف']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث حالة الملف: ' . mysqli_error($con)]);
    }
    
} catch (Exception $e) {
    error_log("Resubmit file error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في النظام']);
}
?>
