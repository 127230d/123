<?php
session_start();
require_once 'dssssssssb.php';

// Only handle AJAX requests - no HTML page
if (!isset($_GET['ajax']) || $_GET['ajax'] != '1') {
    http_response_code(404);
    die('Not Found');
}
header('Content-Type: application/json; charset=utf-8');
// Check if user is logged in
if (!isset($_SESSION['username'])) {
    // For testing purposes, allow access with default admin user
    $_SESSION['username'] = 'admin';
    session_write_close(); // Save session
}

if (!isset($_GET['file_id']) || !is_numeric($_GET['file_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف الملف غير صحيح']);
    exit;
}

$file_id = intval($_GET['file_id']);
$username = $_SESSION['username'];

// Get file details including preview_text
$file_query = "
    SELECT sf.*, l.username as owner_name 
    FROM shared_files sf 
    LEFT JOIN login l ON sf.original_owner_id = l.username 
    WHERE sf.id = ? AND (sf.is_available = 1 OR sf.original_owner_id = ?)
";

$stmt = mysqli_prepare($con, $file_query);
mysqli_stmt_bind_param($stmt, "is", $file_id, $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$file = mysqli_fetch_assoc($result);

if (!$file) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'الملف غير موجود أو غير متاح']);
    exit;
}

// Get file extension
$file_extension = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));

// Generate preview content
$preview_content = generatePreviewContent($file, $file_extension);

// Return response
echo json_encode([
    'success' => true,
    'file' => [
        'id' => $file['id'],
        'filename' => $file['filename'],
        'file_size' => $file['file_size'],
        'file_type' => strtoupper($file_extension),
        'price' => $file['price'],
        'owner_name' => $file['owner_name']
    ],
    'preview' => $preview_content
]);
exit;

/**
 * Generate preview content
 */
function generatePreviewContent($file, $file_extension) {
    // Use stored preview_text if available
    if (!empty($file['preview_text'])) {
        $preview_text = htmlspecialchars($file['preview_text']);
        $preview_text = nl2br($preview_text);
        return '<div class="preview-text" style="text-align: right; direction: rtl; padding: 10px; line-height: 1.6; color: #cccccc;">' . $preview_text . '</div>';
    }
    
    // Fallback to default previews
    switch ($file_extension) {
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'webp':
            return '<div class="preview-placeholder" style="text-align: center; padding: 20px; color: #cccccc;">
                <i class="fas fa-image" style="font-size: 3rem; color: #28a745; margin-bottom: 15px;"></i>
                <h4 style="color: #00ff00; margin: 10px 0;">معاينة صورة</h4>
                <p>صورة جميلة جاهزة للعرض</p>
            </div>';
            
        case 'pdf':
            return '<div class="preview-placeholder" style="text-align: center; padding: 20px; color: #cccccc;">
                <i class="fas fa-file-pdf" style="font-size: 3rem; color: #dc3545; margin-bottom: 15px;"></i>
                <h4 style="color: #00ff00; margin: 10px 0;">مستند PDF</h4>
                <p>مستند مفيد جاهز للتحميل والقراءة</p>
            </div>';
            
        case 'doc':
        case 'docx':
            return '<div class="preview-placeholder" style="text-align: center; padding: 20px; color: #cccccc;">
                <i class="fas fa-file-word" style="font-size: 3rem; color: #2b5797; margin-bottom: 15px;"></i>
                <h4 style="color: #00ff00; margin: 10px 0;">مستند Word</h4>
                <p>مستند مفيد ومنسق جاهز للاستخدام</p>
            </div>';
            
        case 'txt':
        case 'log':
        case 'md':
        case 'csv':
        case 'sql':
        case 'json':
            return tryReadTextFile($file, $file_extension);
            
        default:
            return '<div class="preview-placeholder" style="text-align: center; padding: 20px; color: #cccccc;">
                <i class="fas fa-file" style="font-size: 3rem; color: #6c757d; margin-bottom: 15px;"></i>
                <h4 style="color: #00ff00; margin: 10px 0;">ملف مفيد</h4>
                <p>نوع الملف: ' . strtoupper($file_extension) . '</p>
                <p>ملف قيم جاهز للتحميل</p>
            </div>';
    }
}

/**
 * Try to read text files
 */
function tryReadTextFile($file, $ext) {
    // First, try to find the file in the uploads directory
    $base_upload_dir = __DIR__ . '/uploads/';
    $file_path = null;

    // Check if file_path exists and points to a valid file
    if (!empty($file['file_path']) && file_exists($base_upload_dir . $file['file_path'])) {
        $file_path = $base_upload_dir . $file['file_path'];
    }
    // Fallback to filename in uploads directory
    elseif (file_exists($base_upload_dir . $file['filename'])) {
        $file_path = $base_upload_dir . $file['filename'];
    }
    // Try shared_files directory as another fallback
    elseif (file_exists(__DIR__ . '/shared_files/' . $file['file_path'])) {
        $file_path = __DIR__ . '/shared_files/' . $file['file_path'];
    }

    if ($file_path && file_exists($file_path) && is_readable($file_path)) {
        $content = file_get_contents($file_path, false, null, 0, 800);
        if ($content !== false) {
            $content = htmlspecialchars(trim($content));
            if (!empty($content)) {
                return '<div class="preview-text" style="text-align: right; direction: rtl; padding: 10px; line-height: 1.6; color: #cccccc; font-family: \'Courier New\', monospace;">' . nl2br($content) . '</div>';
            }
        }
    }

    // Return a more informative placeholder if file not found
    return '<div class="preview-placeholder" style="text-align: center; padding: 20px; color: #cccccc;">
        <i class="fas fa-file-alt" style="font-size: 3rem; color: #17a2b8; margin-bottom: 15px;"></i>
        <h4 style="color: #00ff00; margin: 10px 0;">ملف نصي</h4>
        <p>ملف نصي مفيد جاهز للقراءة</p>
        <small style="color: #888;">File: ' . htmlspecialchars($file['filename']) . '</small>
    </div>';
}

