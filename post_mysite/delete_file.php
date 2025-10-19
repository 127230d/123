<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once 'dssssssssb.php';

// Function to log activity
function logActivity($username, $action, $details) {
    global $con;
    try {
        $log_query = "INSERT INTO activity_log (user_id, action, details, timestamp) VALUES (?, ?, ?, NOW())";
        $log_stmt = mysqli_prepare($con, $log_query);
        if ($log_stmt) {
            mysqli_stmt_bind_param($log_stmt, "sss", $username, $action, $details);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        }
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

$response = ['success' => false, 'message' => 'طلب غير صحيح'];

// التحقق من أن الطلب POST وأنه AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['action'] === 'delete_file') {
    $file_id = intval($_POST['file_id']);
    $username = $_SESSION['username'];
    
    if ($file_id <= 0) {
        $response['message'] = 'معرف الملف غير صحيح';
    } else {
        // التحقق من أن المستخدم هو صاحب الملف
        $check_owner_query = mysqli_prepare($con, "SELECT id, filename, file_path, original_owner_id FROM shared_files WHERE id = ? AND original_owner_id = ?");
        mysqli_stmt_bind_param($check_owner_query, "is", $file_id, $username);
        mysqli_stmt_execute($check_owner_query);
        $result = mysqli_stmt_get_result($check_owner_query);
        
        if ($file_data = mysqli_fetch_assoc($result)) {
            // التحقق من عدم وجود مبيعات للملف
            $sales_check_query = mysqli_prepare($con, "SELECT COUNT(*) as sales_count FROM file_purchases WHERE file_id = ?");
            mysqli_stmt_bind_param($sales_check_query, "i", $file_id);
            mysqli_stmt_execute($sales_check_query);
            $sales_result = mysqli_stmt_get_result($sales_check_query);
            $sales_data = mysqli_fetch_assoc($sales_result);
            
            if ($sales_data['sales_count'] > 0) {
                $response['message'] = 'لا يمكن حذف ملف تم بيعه من قبل';
            } else {
                // بدء معاملة آمنة لحذف الملف
                mysqli_begin_transaction($con);
                
                try {
                    // حذف سجلات المعاينة إن وجدت
                    $delete_previews = mysqli_prepare($con, "DELETE FROM file_previews WHERE file_id = ?");
                    if ($delete_previews) {
                        mysqli_stmt_bind_param($delete_previews, "i", $file_id);
                        mysqli_stmt_execute($delete_previews);
                        mysqli_stmt_close($delete_previews);
                    }
                    
                    // حذف سجلات الرفض إن وجدت
                    $delete_rejections = mysqli_prepare($con, "DELETE FROM file_rejections WHERE file_id = ? AND item_type = 'file'");
                    if ($delete_rejections) {
                        mysqli_stmt_bind_param($delete_rejections, "i", $file_id);
                        mysqli_stmt_execute($delete_rejections);
                        mysqli_stmt_close($delete_rejections);
                    }
                    
                    // حذف الملف من قاعدة البيانات
                    $delete_file = mysqli_prepare($con, "DELETE FROM shared_files WHERE id = ?");
                    mysqli_stmt_bind_param($delete_file, "i", $file_id);
                    mysqli_stmt_execute($delete_file);
                    
                    if (mysqli_stmt_affected_rows($delete_file) > 0) {
                        // محاولة حذف الملف الفعلي من الخادم بمسارات متعددة
                        $file_deleted = false;
                        $possible_paths = [
                            __DIR__ . '/shared_files/' . $file_data['file_path'],
                            __DIR__ . '/uploads/' . $file_data['original_owner_id'] . '/' . $file_data['file_path'],
                            __DIR__ . '/uploads/' . $file_data['file_path'],
                            $file_data['file_path'] // المسار الكامل إن كان محفوظاً
                        ];
                        
                        foreach ($possible_paths as $path) {
                            if (!empty($path) && file_exists($path)) {
                                if (@unlink($path)) {
                                    $file_deleted = true;
                                    break;
                                }
                            }
                        }
                        
                        // تأكيد المعاملة
                        mysqli_commit($con);
                        
                        // تسجيل عملية الحذف في سجل الأنشطة
                        $activity_details = json_encode([
                            'file_id' => $file_id,
                            'filename' => $file_data['filename'],
                            'file_path' => $file_data['file_path'],
                            'file_deleted_from_disk' => $file_deleted,
                            'timestamp' => date('Y-m-d H:i:s'),
                            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                        ]);
                        logActivity($username, 'file_deleted', $activity_details);
                        
                        $delete_message = 'تم حذف الملف "' . htmlspecialchars($file_data['filename']) . '" من قاعدة البيانات بنجاح';
                        if ($file_deleted) {
                            $delete_message .= ' وتم حذف الملف الفعلي من الخادم';
                        } else {
                            $delete_message .= ' (تعذر العثور على الملف الفعلي للحذف)';
                        }
                        
                        $response = [
                            'success' => true, 
                            'message' => $delete_message,
                            'file_deleted' => $file_deleted
                        ];
                    } else {
                        throw new Exception('فشل في حذف الملف من قاعدة البيانات');
                    }
                    
                    mysqli_stmt_close($delete_file);
                    
                } catch (Exception $e) {
                    // إلغاء المعاملة في حالة وجود خطأ
                    mysqli_rollback($con);
                    $response['message'] = 'حدث خطأ أثناء حذف الملف: ' . $e->getMessage();
                    error_log("Delete file error for user $username (file_id: $file_id): " . $e->getMessage());
                    
                    // تسجيل فشل الحذف
                    logActivity($username, 'file_delete_failed', json_encode([
                        'file_id' => $file_id,
                        'error' => $e->getMessage(),
                        'timestamp' => date('Y-m-d H:i:s')
                    ]));
                }
            }
            
            mysqli_stmt_close($sales_check_query);
        } else {
            $response['message'] = 'الملف غير موجود أو ليس لديك صلاحية لحذفه';
        }
        
        mysqli_stmt_close($check_owner_query);
    }
}

// إرجاع الاستجابة كـ JSON
header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
