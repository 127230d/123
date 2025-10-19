<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once 'dssssssssb.php';

header('Content-Type: application/json');

// Function to create directories if they don't exist
function ensureDirectoryExists($dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    return is_writable($dir);
}

// Function to get file icon class
function getFileIconClass($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'fas fa-file-pdf text-danger',
        'doc' => 'fas fa-file-word text-primary',
        'docx' => 'fas fa-file-word text-primary',
        'txt' => 'fas fa-file-alt',
        'jpg' => 'fas fa-file-image text-success',
        'jpeg' => 'fas fa-file-image text-success',
        'png' => 'fas fa-file-image text-success',
        'gif' => 'fas fa-file-image text-success',
        'zip' => 'fas fa-file-archive text-warning',
        'rar' => 'fas fa-file-archive text-warning',
        '7z' => 'fas fa-file-archive text-warning',
        'sql' => 'fas fa-database text-info',
        'json' => 'fas fa-code text-info',
        'csv' => 'fas fa-file-csv text-success',
        'xls' => 'fas fa-file-excel text-success',
        'xlsx' => 'fas fa-file-excel text-success',
        'ppt' => 'fas fa-file-powerpoint text-danger',
        'pptx' => 'fas fa-file-powerpoint text-danger',
        'mp3' => 'fas fa-file-audio text-primary',
        'mp4' => 'fas fa-file-video text-primary',
    ];
    return $icons[$ext] ?? 'fas fa-file';
}

$response = ['success' => false];
$username = $_SESSION["username"];

try {
    // Ensure required directories exist
    $requiredDirs = [
        __DIR__ . '/shared_files',
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/' . $username
    ];

    foreach ($requiredDirs as $dir) {
        if (!ensureDirectoryExists($dir)) {
            throw new Exception("Cannot create or write to directory: $dir");
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'edit_file':
                $fileId = intval($_POST['file_id']);
                $filename = trim($_POST['filename']);
                $description = trim($_POST['description'] ?? '');
                $price = intval($_POST['price']);
                
                // Validate inputs
                if (empty($filename) || $price < 1 || $price > 10000) {
                    throw new Exception('Invalid input data');
                }
                
                // Check if user owns the file
                $check_query = mysqli_prepare($con, "SELECT id FROM shared_files WHERE id = ? AND current_owner_id = ?");
                mysqli_stmt_bind_param($check_query, "is", $fileId, $username);
                mysqli_stmt_execute($check_query);
                $check_result = mysqli_stmt_get_result($check_query);
                
                if (mysqli_num_rows($check_result) == 0) {
                    throw new Exception('File not found or access denied');
                }
                
                // Update file information
                $update_query = mysqli_prepare($con, "UPDATE shared_files SET filename = ?, description = ?, price = ? WHERE id = ? AND current_owner_id = ?");
                mysqli_stmt_bind_param($update_query, "ssiis", $filename, $description, $price, $fileId, $username);
                
                if (mysqli_stmt_execute($update_query)) {
                    $response['success'] = true;
                    $response['message'] = 'تم تحديث معلومات الملف بنجاح';
                } else {
                    throw new Exception('Failed to update file information');
                }
                break;
                
            case 'delete_file':
                $fileId = intval($_POST['file_id']);
                
                // Get file information
                $file_query = mysqli_prepare($con, "SELECT file_path, current_owner_id FROM shared_files WHERE id = ? AND current_owner_id = ?");
                mysqli_stmt_bind_param($file_query, "is", $fileId, $username);
                mysqli_stmt_execute($file_query);
                $file_result = mysqli_stmt_get_result($file_query);
                $file_data = mysqli_fetch_assoc($file_result);
                
                if (!$file_data) {
                    throw new Exception('File not found or access denied');
                }
                
                // Check if file has been sold
                $sales_check = mysqli_prepare($con, "SELECT COUNT(*) as count FROM file_purchases WHERE file_id = ?");
                mysqli_stmt_bind_param($sales_check, "i", $fileId);
                mysqli_stmt_execute($sales_check);
                $sales_result = mysqli_stmt_get_result($sales_check);
                $sales_data = mysqli_fetch_assoc($sales_result);
                
                if ($sales_data['count'] > 0) {
                    throw new Exception('Cannot delete file that has been sold');
                }
                
                // Delete physical file
                $possiblePaths = [
                    __DIR__ . '/shared_files/' . $file_data['file_path'],
                    __DIR__ . '/uploads/' . $username . '/' . $file_data['file_path'],
                    __DIR__ . '/' . $file_data['file_path']
                ];
                
                foreach ($possiblePaths as $path) {
                    if (file_exists($path)) {
                        unlink($path);
                        break;
                    }
                }
                
                // Delete from database
                $delete_query = mysqli_prepare($con, "DELETE FROM shared_files WHERE id = ? AND current_owner_id = ?");
                mysqli_stmt_bind_param($delete_query, "is", $fileId, $username);
                
                if (mysqli_stmt_execute($delete_query)) {
                    $response['success'] = true;
                    $response['message'] = 'تم حذف الملف بنجاح';
                } else {
                    throw new Exception('Failed to delete file from database');
                }
                break;
                
            case 'toggle_availability':
                $fileId = intval($_POST['file_id']);
                $newStatus = isset($_POST['status']) ? intval($_POST['status']) : 0;
                
                // Check if file exists and belongs to current user
                $check_query = mysqli_prepare($con, "SELECT id, current_owner_id FROM shared_files WHERE id = ? AND current_owner_id = ?");
                mysqli_stmt_bind_param($check_query, "is", $fileId, $username);
                mysqli_stmt_execute($check_query);
                mysqli_stmt_store_result($check_query);
                
                if (mysqli_stmt_num_rows($check_query) === 0) {
                    throw new Exception('لم يتم العثور على الملف أو ليس لديك صلاحية تعديله');
                }
                mysqli_stmt_close($check_query);
                
                // Update availability status
                $update_query = mysqli_prepare($con, "UPDATE shared_files SET is_available = ? WHERE id = ? AND current_owner_id = ?");
                if (!$update_query) {
                    throw new Exception('حدث خطأ في إعداد التحديث');
                }
                
                mysqli_stmt_bind_param($update_query, "iis", $newStatus, $fileId, $username);
                
                if (mysqli_stmt_execute($update_query)) {
                    if (mysqli_stmt_affected_rows($update_query) > 0) {
                        $response['success'] = true;
                        $response['message'] = $newStatus ? 'تم تفعيل نشر الملف بنجاح' : 'تم إيقاف نشر الملف بنجاح';
                        $response['new_status'] = $newStatus;
                    } else {
                        throw new Exception('لم يتم تغيير حالة الملف - لم يتم إجراء أي تغييرات');
                    }
                } else {
                    throw new Exception('حدث خطأ أثناء تحديث حالة الملف');
                }
                break;
                
            case 'get_file_stats':
                $fileId = intval($_POST['file_id']);
                
                // Get file statistics
                $stats_query = "
                    SELECT 
                        sf.filename,
                        sf.price,
                        sf.created_at,
                        COUNT(fp.id) as total_sales,
                        COALESCE(SUM(fp.price), 0) as total_revenue
                    FROM shared_files sf
                    LEFT JOIN file_purchases fp ON sf.id = fp.file_id
                    WHERE sf.id = ? AND sf.current_owner_id = ?
                    GROUP BY sf.id
                ";
                
                $stats_stmt = mysqli_prepare($con, $stats_query);
                mysqli_stmt_bind_param($stats_stmt, "is", $fileId, $username);
                mysqli_stmt_execute($stats_stmt);
                $stats_result = mysqli_stmt_get_result($stats_stmt);
                $stats_data = mysqli_fetch_assoc($stats_result);
                
                if ($stats_data) {
                    $response['success'] = true;
                    $response['stats'] = [
                        'filename' => $stats_data['filename'],
                        'price' => intval($stats_data['price']),
                        'total_sales' => intval($stats_data['total_sales']),
                        'total_revenue' => intval($stats_data['total_revenue']),
                        'created_at' => $stats_data['created_at']
                    ];
                } else {
                    throw new Exception('File statistics not found');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } else {
        // GET request - return file management interface data
        $response['success'] = true;
        $response['directories'] = [];
        
        foreach ($requiredDirs as $dir) {
            $response['directories'][] = [
                'path' => $dir,
                'exists' => file_exists($dir),
                'writable' => is_writable($dir)
            ];
        }
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("File management error for user $username: " . $e->getMessage());
}

echo json_encode($response);
?>
