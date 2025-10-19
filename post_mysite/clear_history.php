<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once 'dssssssssb.php';

// Function to log activity (with better error handling)
function logActivity($username, $action, $details) {
    global $con;
    try {
        // Check if activity_log table exists first
        $check_table = "SHOW TABLES LIKE 'activity_log'";
        $table_result = mysqli_query($con, $check_table);
        
        if (mysqli_num_rows($table_result) > 0) {
            $log_query = "INSERT INTO activity_log (user_id, action, details, timestamp) VALUES (?, ?, ?, NOW())";
            $log_stmt = mysqli_prepare($con, $log_query);
            if ($log_stmt) {
                mysqli_stmt_bind_param($log_stmt, "sss", $username, $action, $details);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);
            }
        }
        // If table doesn't exist, just skip logging (don't fail the main operation)
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
        // Don't throw exception - just log the error
    }
}

$response = ['success' => false, 'message' => ''];
$username = $_SESSION["username"];

// Handle AJAX requests only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // Add debugging
    error_log("Clear history request from user: $username");
    
    try {
        if (!isset($_POST['action']) || $_POST['action'] !== 'clear_transaction_history') {
            error_log("Invalid action: " . ($_POST['action'] ?? 'null'));
            throw new Exception('إجراء غير صالح');
        }

        // بداية المعاملة لضمان تنفيذ آمن
        if (!mysqli_begin_transaction($con)) {
            throw new Exception('فشل في بدء المعاملة: ' . mysqli_error($con));
        }
        
        // عد المعاملات قبل الحذف للإحصائيات
        $count_query = "
            SELECT 
                (SELECT COUNT(*) FROM file_purchases WHERE buyer_username = ?) as purchases,
                (SELECT COUNT(*) FROM file_purchases WHERE seller_username = ?) as sales
        ";
        $count_stmt = mysqli_prepare($con, $count_query);
        if (!$count_stmt) {
            throw new Exception('فشل في إعداد استعلام العد: ' . mysqli_error($con));
        }
        
        mysqli_stmt_bind_param($count_stmt, "ss", $username, $username);
        if (!mysqli_stmt_execute($count_stmt)) {
            throw new Exception('فشل في تنفيذ استعلام العد: ' . mysqli_stmt_error($count_stmt));
        }
        
        $count_result = mysqli_stmt_get_result($count_stmt);
        $counts = mysqli_fetch_assoc($count_result);
        mysqli_stmt_close($count_stmt);
        
        $total_deleted = $counts['purchases'] + $counts['sales'];
        error_log("Transactions to delete for user $username: purchases={$counts['purchases']}, sales={$counts['sales']}, total=$total_deleted");
        
        if ($total_deleted == 0) {
            mysqli_rollback($con);
            throw new Exception('لا توجد معاملات للحذف');
        }
        
        // حذف سجلات المشتريات (كمشتري)
        if ($counts['purchases'] > 0) {
            $delete_purchases = "DELETE FROM file_purchases WHERE buyer_username = ?";
            $purchases_stmt = mysqli_prepare($con, $delete_purchases);
            if (!$purchases_stmt) {
                throw new Exception('فشل في إعداد استعلام حذف المشتريات: ' . mysqli_error($con));
            }
            
            mysqli_stmt_bind_param($purchases_stmt, "s", $username);
            if (!mysqli_stmt_execute($purchases_stmt)) {
                mysqli_stmt_close($purchases_stmt);
                throw new Exception('فشل في حذف سجلات المشتريات: ' . mysqli_stmt_error($purchases_stmt));
            }
            
            $purchases_affected = mysqli_stmt_affected_rows($purchases_stmt);
            mysqli_stmt_close($purchases_stmt);
            error_log("Deleted $purchases_affected purchase records for user $username");
        }
        
        // حذف سجلات المبيعات (كبائع)
        if ($counts['sales'] > 0) {
            $delete_sales = "DELETE FROM file_purchases WHERE seller_username = ?";
            $sales_stmt = mysqli_prepare($con, $delete_sales);
            if (!$sales_stmt) {
                throw new Exception('فشل في إعداد استعلام حذف المبيعات: ' . mysqli_error($con));
            }
            
            mysqli_stmt_bind_param($sales_stmt, "s", $username);
            if (!mysqli_stmt_execute($sales_stmt)) {
                mysqli_stmt_close($sales_stmt);
                throw new Exception('فشل في حذف سجلات المبيعات: ' . mysqli_stmt_error($sales_stmt));
            }
            
            $sales_affected = mysqli_stmt_affected_rows($sales_stmt);
            mysqli_stmt_close($sales_stmt);
            error_log("Deleted $sales_affected sales records for user $username");
        }
        
        // تسجيل العملية في سجل الأنشطة
        $activity_details = json_encode([
            'action' => 'clear_transaction_history',
            'deleted_purchases' => $counts['purchases'],
            'deleted_sales' => $counts['sales'],
            'total_deleted' => $total_deleted,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        logActivity($username, 'clear_transaction_history', $activity_details);
        
        // تأكيد المعاملة
        mysqli_commit($con);
        
        $response['success'] = true;
        $response['message'] = "تم حذف $total_deleted سجل معاملة بنجاح";
        $response['deleted_count'] = $total_deleted;
        $response['purchases_deleted'] = $counts['purchases'];
        $response['sales_deleted'] = $counts['sales'];
        
    } catch (Exception $e) {
        // إلغاء المعاملة في حالة الخطأ
        mysqli_rollback($con);
        $response['message'] = $e->getMessage();
        error_log("Clear history error for user $username: " . $e->getMessage());
        
        // تسجيل فشل العملية
        logActivity($username, 'clear_history_failed', $e->getMessage());
    }
    
} else {
    $response['message'] = 'طلب غير صالح';
}

// إرجاع الاستجابة كـ JSON
echo json_encode($response);
exit;
?>
