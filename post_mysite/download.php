<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once 'dssssssssb.php';

// Function to log download activity
function logDownloadActivity($username, $fileId, $filename, $success = true) {
    global $con;
    try {
        $status = $success ? 'success' : 'failed';
        $log_query = "INSERT INTO activity_log (user_id, action, details, timestamp) VALUES (?, 'file_download', ?, NOW())";
        $log_stmt = mysqli_prepare($con, $log_query);
        if ($log_stmt) {
            $log_details = json_encode([
                'file_id' => $fileId,
                'filename' => $filename,
                'status' => $status,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            mysqli_stmt_bind_param($log_stmt, "ss", $username, $log_details);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        }
    } catch (Exception $e) {
        // Silent log failure - don't interrupt download
        error_log("Download log error: " . $e->getMessage());
    }
}

// Function to get mime type
function getFileMimeType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'sql' => 'application/sql',
        'json' => 'application/json',
        'csv' => 'text/csv',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    return $mimes[$ext] ?? 'application/octet-stream';
}

// Enhanced error handling and logging
try {
    // Validate file ID
    $fileId = filter_input(INPUT_GET, 'file_id', FILTER_VALIDATE_INT);
    if (!$fileId || $fileId <= 0) {
        http_response_code(400);
        die('<div style="color: #ff4444; font-family: Arial; padding: 20px; text-align: center;"><h3>خطأ في البيانات</h3><p>معرف الملف غير صالح</p></div>');
    }

    // Get current user info
    $currentUsername = $_SESSION['username'];
    
    // Check if user is admin
    $admin_check = mysqli_prepare($con, "SELECT subscription FROM login WHERE username = ?");
    mysqli_stmt_bind_param($admin_check, "s", $currentUsername);
    mysqli_stmt_execute($admin_check);
    $admin_result = mysqli_stmt_get_result($admin_check);
    $admin_data = mysqli_fetch_assoc($admin_result);
    $isAdmin = ($admin_data['subscription'] === 'admin');
    mysqli_stmt_close($admin_check);

    // Get file information with detailed query
    $file_query = "
        SELECT 
            sf.id,
            sf.filename,
            sf.file_path,
            sf.file_size,
            sf.file_type,
            sf.current_owner_id,
            sf.original_owner_id,
            sf.is_available,
            sf.created_at,
            l.username as owner_name
        FROM shared_files sf 
        LEFT JOIN login l ON sf.current_owner_id = l.username 
        WHERE sf.id = ? 
        LIMIT 1
    ";
    
    $stmt = mysqli_prepare($con, $file_query);
    if (!$stmt) {
        throw new Exception('Database preparation error: ' . mysqli_error($con));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $fileId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $file = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Check if file exists in database
    if (!$file) {
        logDownloadActivity($currentUsername, $fileId, 'unknown', false);
        http_response_code(404);
        die('<div style="color: #ff4444; font-family: Arial; padding: 20px; text-align: center;"><h3>الملف غير موجود</h3><p>الملف المطلوب غير موجود في قاعدة البيانات</p></div>');
    }

    // تحديد صلاحيات التحميل المحسنة
    $can_download = false;
    $download_type = '';
    
    // فحص المبيعات/المشتريات
    $purchase_check = mysqli_prepare($con, "SELECT COUNT(*) as purchased FROM file_purchases WHERE file_id = ? AND buyer_username = ?");
    mysqli_stmt_bind_param($purchase_check, "is", $fileId, $currentUsername);
    mysqli_stmt_execute($purchase_check);
    $purchase_result = mysqli_stmt_get_result($purchase_check);
    $purchase_data = mysqli_fetch_assoc($purchase_result);
    $has_purchased = $purchase_data['purchased'] > 0;
    mysqli_stmt_close($purchase_check);
    
    if ($isAdmin) {
        // الأدمن يمكنه تحميل أي ملف
        $can_download = true;
        $download_type = 'admin';
    } elseif ($file['original_owner_id'] === $currentUsername) {
        // صاحب الملف الأصلي
        $can_download = true;
        $download_type = 'original_owner';
    } elseif ($has_purchased) {
        // من اشترى الملف (النظام الجديد: بيع متعدد)
        $can_download = true;
        $download_type = 'buyer';
    }
    
    if (!$can_download) {
        logDownloadActivity($currentUsername, $fileId, $file['filename'], false);
        http_response_code(403);
        die('<div style="color: #ff4444; font-family: Arial; padding: 20px; text-align: center;"><h3>غير مسموح</h3><p>ليس لديك صلاحية لتحميل هذا الملف</p><p>يجب شراء الملف أولاً</p></div>');
    }

    // Build complete file path
    $possiblePaths = [
        __DIR__ . '/shared_files/' . $file['file_path'],
        __DIR__ . '/uploads/' . $file['current_owner_id'] . '/' . $file['file_path'],
        __DIR__ . '/uploads/' . $file['file_path'],
        __DIR__ . '/' . $file['file_path'],
        $file['file_path'] // If it's already a complete path
    ];

    $filePath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            $filePath = $path;
            break;
        }
    }

    // Check if physical file exists
    if (!$filePath) {
        logDownloadActivity($currentUsername, $fileId, $file['filename'], false);
        http_response_code(404);
        die('<div style="color: #ff4444; font-family: Arial; padding: 20px; text-align: center;"><h3>الملف غير موجود</h3><p>الملف الفعلي غير موجود على الخادم</p><p>اسم الملف: ' . htmlspecialchars($file['filename']) . '</p><p>مسار الملف: ' . htmlspecialchars($file['file_path']) . '</p></div>');
    }

    // Security check: ensure file is within allowed directories
    $realPath = realpath($filePath);
    $allowedDir = realpath(__DIR__);
    if (strpos($realPath, $allowedDir) !== 0) {
        logDownloadActivity($currentUsername, $fileId, $file['filename'], false);
        http_response_code(403);
        die('<div style="color: #ff4444; font-family: Arial; padding: 20px; text-align: center;"><h3>خطأ أمني</h3><p>مسار الملف غير آمن</p></div>');
    }

    // تحديد اسم الملف للتحميل بناءً على نوع المستخدم
    $original_filename = $file['filename'];
    $actual_file_info = pathinfo($file['file_path']);
    $actual_extension = strtolower($actual_file_info['extension']);
    
    $file_info = pathinfo($original_filename);
    $filename_without_ext = $file_info['filename'];
    
    // تنظيف اسم الملف من أي امتدادات قديمة
    if (isset($file_info['extension'])) {
        $filename_without_ext = substr($filename_without_ext, 0, strrpos($filename_without_ext, $file_info['extension']));
    }
    
    // تحديد اسم الملف للتحميل
    switch ($download_type) {
        case 'admin':
            // الأدمن يحصل على الملف بالاسم الأصلي مع علامة الأدمن
            $fileName = '[ADMIN] ' . $filename_without_ext . '.' . $actual_extension;
            break;
            
        case 'original_owner':
            // صاحب الملف الأصلي يحصل على الاسم الأصلي
            $fileName = $filename_without_ext . '.' . $actual_extension;
            break;
            
        case 'buyer':
            // المشترون يحصلون على اسم نظيف بدون اسم المستخدم
            $clean_filename = $filename_without_ext;
            
            // إزالة اسم المستخدم من بداية اسم الملف إن وجد
            $owner_prefix = $file['original_owner_id'] . '_';
            if (strpos($clean_filename, $owner_prefix) === 0) {
                $clean_filename = substr($clean_filename, strlen($owner_prefix));
            }
            
            // استخدام الامتداد الفعلي للملف
            $fileName = $clean_filename . '.' . $actual_extension;
            break;
            
        default:
            $fileName = $filename_without_ext . '.' . $actual_extension;
    }
    
    // التأكد من صحة الامتداد
    if (!preg_match('/\.' . preg_quote($actual_extension, '/') . '$/i', $fileName)) {
        $fileName = $fileName . '.' . $actual_extension;
    }
    
    $fileSize = filesize($filePath);
    $mimeType = getFileMimeType($fileName);

    // Log successful download
    logDownloadActivity($currentUsername, $fileId, $fileName, true);

    // Clear any output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Set headers for file download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $fileSize);
    
    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');

    // Handle large files with chunked reading
    $chunkSize = 1024 * 1024; // 1MB chunks
    $handle = fopen($filePath, 'rb');
    
    if ($handle === false) {
        throw new Exception('Cannot open file for reading');
    }

    while (!feof($handle)) {
        $chunk = fread($handle, $chunkSize);
        echo $chunk;
        flush();
    }
    
    fclose($handle);
    exit;
    
} catch (Exception $e) {
    // Log error
    error_log("Download error for user {$currentUsername}: " . $e->getMessage());
    
    if (isset($currentUsername, $fileId, $file)) {
        logDownloadActivity($currentUsername, $fileId, $file['filename'] ?? 'unknown', false);
    }
    
    http_response_code(500);
    die('<div style="color: #ff4444; font-family: Arial; padding: 20px; text-align: center;"><h3>خطأ داخلي</h3><p>حدث خطأ أثناء تحميل الملف</p><p style="font-size: 0.9em; color: #888;">كود الخطأ: ' . htmlspecialchars($e->getMessage()) . '</p></div>');
} catch (Throwable $e) {
    // Handle any other errors
    error_log("Fatal download error: " . $e->getMessage());
    
    http_response_code(500);
    die('<div style="color: #ff4444; font-family: Arial; padding: 20px; text-align: center;"><h3>خطأ نظام</h3><p>حدث خطأ غير متوقع</p></div>');
}
