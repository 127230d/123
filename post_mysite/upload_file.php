<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once 'dssssssssb.php';

// التحقق من وجود حقل review_status وإضافته إذا لم يكن موجوداً
$check_column = mysqli_query($con, "SHOW COLUMNS FROM shared_files LIKE 'review_status'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN review_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
    mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN rejection_reason TEXT NULL");
    mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN rejection_date DATETIME NULL");
    mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN reviewer_name VARCHAR(100) NULL");
}

// Function to generate secure filename
function generateSecureFilename($originalName) {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    return $timestamp . '_' . $random . '.' . $ext;
}

// Function to validate file type
function isAllowedFileType($filename) {
    $allowedExtensions = [
        'pdf', 'doc', 'docx', 'txt', 'rtf',
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
        'zip', 'rar', '7z', 'tar', 'gz',
        'sql', 'json', 'xml', 'csv',
        'xls', 'xlsx', 'ppt', 'pptx',
        'mp3', 'wav', 'mp4', 'avi', 'mov',
        'exe', 'msi', 'apk', 'deb', 'rpm'
    ];
    
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowedExtensions);
}

// Function to validate file size (max 50MB)
function isValidFileSize($fileSize) {
    $maxSize = 50 * 1024 * 1024; // 50MB
    return $fileSize <= $maxSize && $fileSize > 0;
}

/**
 * الحصول على الوصف العربي لنوع الملف
 */
function getArabicFileType($mime_type) {
    $type_map = [
        'image/' => 'صورة',
        'video/' => 'فيديو',
        'audio/' => 'ملف صوتي',
        'text/' => 'ملف نصي',
        'application/pdf' => 'ملف PDF',
        'application/msword' => 'مستند Word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'مستند Word',
        'application/vnd.ms-excel' => 'جدول Excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'جدول Excel',
        'application/zip' => 'ملف مضغوط',
        'application/x-rar-compressed' => 'ملف مضغوط',
        'application/x-7z-compressed' => 'ملف مضغوط',
        'application/x-msdownload' => 'ملف تنفيذي',
        'application/vnd.android.package-archive' => 'تطبيق Android'
    ];
    
    foreach ($type_map as $pattern => $arabic) {
        if (strpos($mime_type, $pattern) === 0) {
            return $arabic;
        }
    }
    
    return 'ملف';
}

$response = ['success' => false, 'message' => ''];
$username = $_SESSION["username"];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (empty($_POST['filename']) || empty($_POST['price'])) {
            throw new Exception('يرجى ملء جميع الحقول المطلوبة');
        }

        // Validate preview data (mandatory)
        if (empty($_POST['preview_type']) || !in_array($_POST['preview_type'], ['text', 'image'])) {
            throw new Exception('يرجى اختيار نوع المعاينة');
        }

        $preview_type = $_POST['preview_type'];
        $preview_text = null;
        $preview_image = null;

        if ($preview_type === 'text') {
            if (empty($_POST['preview_text']) || strlen(trim($_POST['preview_text'])) < 10) {
                throw new Exception('يرجى إدخال نص معاينة لا يقل عن 10 أحرف');
            }
            $preview_text = trim($_POST['preview_text']);
            if (strlen($preview_text) > 500) {
                throw new Exception('نص المعاينة يجب أن يكون أقل من 500 حرف');
            }
        } else {
            // Handle image preview upload
            if (!isset($_FILES['preview_image']) || $_FILES['preview_image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('يرجى اختيار صورة المعاينة');
            }

            $previewImageFile = $_FILES['preview_image'];
            $previewImageSize = $previewImageFile['size'];
            $previewImageType = $previewImageFile['type'];

            // Validate preview image
            if ($previewImageSize > 2 * 1024 * 1024) { // 2MB max
                throw new Exception('حجم صورة المعاينة يجب أن يكون أقل من 2 ميجابايت');
            }

            if (!in_array($previewImageType, ['image/jpeg', 'image/png', 'image/gif'])) {
                throw new Exception('نوع صورة المعاينة غير مدعوم (JPG, PNG, GIF فقط)');
            }
        }

        // Validate file upload
        if (!isset($_FILES['fileUpload']) || $_FILES['fileUpload']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('يرجى اختيار ملف صالح');
        }

        $uploadedFile = $_FILES['fileUpload'];
        $originalFilename = $uploadedFile['name'];
        $tempPath = $uploadedFile['tmp_name'];
        $fileSize = $uploadedFile['size'];

        // Validate file type
        if (!isAllowedFileType($originalFilename)) {
            throw new Exception('نوع الملف غير مسموح');
        }

        // Validate file size
        if (!isValidFileSize($fileSize)) {
            throw new Exception('حجم الملف كبير جداً (الحد الأقصى 50 ميجابايت)');
        }

        // Validate price
        $price = intval($_POST['price']);
        if ($price < 1 || $price > 10000) {
            throw new Exception('السعر يجب أن يكون بين 1 و 10000 نقطة');
        }

        // Sanitize inputs
        $original_filename = trim($_POST['filename']);
        $description = trim($_POST['description'] ?? '');
        $custom_preview = trim($_POST['custom_preview'] ?? '');
        
        // التحقق من اسم الملف وتنظيفه
        $file_info = pathinfo($original_filename);
        $extension = isset($file_info['extension']) ? '.' . $file_info['extension'] : '';
        $filename_only = $file_info['filename'];
        
        // استخدام اسم الملف كما هو بدون إضافة اسم المستخدم
        $display_filename = $filename_only . $extension;
        
        if (strlen($display_filename) > 255) {
            throw new Exception('اسم الملف طويل جداً');
        }

        if (strlen($description) > 1000) {
            throw new Exception('وصف الملف طويل جداً');
        }

        // Generate secure filename
        $secureFilename = generateSecureFilename($originalFilename);
        
        // Create upload directories if they don't exist
        $baseUploadDir = __DIR__ . '/shared_files/';
        $userUploadDir = __DIR__ . '/uploads/' . $username . '/';
        $previewImagesDir = __DIR__ . '/preview_images/';
        
        foreach ([$baseUploadDir, $userUploadDir, $previewImagesDir] as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    error_log("Failed to create directory: $dir");
                    throw new Exception('فشل في إنشاء مجلد الرفع');
                }
                chmod($dir, 0777);
            }
        }

        // Handle preview image upload if type is image
        if ($preview_type === 'image') {
            $previewImageExt = pathinfo($previewImageFile['name'], PATHINFO_EXTENSION);
            $previewImageName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $previewImageExt;
            $previewImagePath = $previewImagesDir . $previewImageName;
            
            if (!move_uploaded_file($previewImageFile['tmp_name'], $previewImagePath)) {
                throw new Exception('فشل في رفع صورة المعاينة');
            }
            
            $preview_image = $previewImageName;
        }

        // Use the shared_files directory as primary storage
        $targetPath = $baseUploadDir . $secureFilename;

        // Move uploaded file
        if (!move_uploaded_file($tempPath, $targetPath)) {
            throw new Exception('فشل في رفع الملف');
        }

        // Remove old preview generation logic since preview is now mandatory

        // تحديد نوع الملف
        $file_type = mime_content_type($targetPath);
        if (!$file_type) {
            // إذا فشل mime_content_type، نستخدم الامتداد لتحديد النوع
            $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            $mime_types = [
                // Images
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
                'webp' => 'image/webp',
                // Documents
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'txt' => 'text/plain',
                // Archives
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                '7z' => 'application/x-7z-compressed',
                // Audio
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                // Video
                'mp4' => 'video/mp4',
                'avi' => 'video/x-msvideo',
                'mov' => 'video/quicktime',
                // Others
                'exe' => 'application/x-msdownload',
                'apk' => 'application/vnd.android.package-archive'
            ];
            $file_type = $mime_types[$ext] ?? 'application/octet-stream';
        }

        // Insert file record into database with preview data
        $insert_query = "
            INSERT INTO shared_files 
            (filename, description, preview_type, preview_text, preview_image, file_path, file_size, file_type, price, 
             original_owner_id, current_owner_id, is_available, review_status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'pending', NOW())
        ";
        
        $stmt = mysqli_prepare($con, $insert_query);
        mysqli_stmt_bind_param($stmt, "ssssssissss", 
            $display_filename, $description, $preview_type, $preview_text, $preview_image, 
            $secureFilename, $fileSize, $file_type, $price, $username, $username
        );

        if (mysqli_stmt_execute($stmt)) {
            $file_id = mysqli_insert_id($con);
            
            // Log the upload activity
            $log_query = "INSERT INTO activity_log (user_id, action, details, timestamp) VALUES (?, 'file_upload', ?, NOW())";
            $log_stmt = mysqli_prepare($con, $log_query);
            $log_details = json_encode([
                'file_id' => $file_id,
                'filename' => $display_filename,
                'price' => $price,
                'size' => $fileSize
            ]);
            mysqli_stmt_bind_param($log_stmt, "ss", $username, $log_details);
            mysqli_stmt_execute($log_stmt);
            
            $response['success'] = true;
            $response['message'] = 'تم رفع الملف بنجاح! في انتظار مراجعة الأدمن للنشر.';
            $response['file_id'] = $file_id;
        } else {
            // Delete uploaded file if database insert failed
            unlink($targetPath);
            throw new Exception('فشل في حفظ بيانات الملف في قاعدة البيانات');
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("File upload error for user $username: " . $e->getMessage());
    }
}

// Return JSON response for AJAX requests
if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Redirect for normal form submission
if ($response['success']) {
    header('Location: index.php?upload=success&msg=' . urlencode($response['message']));
} else {
    header('Location: index.php?upload=error&msg=' . urlencode($response['message']));
}

/**
 * Generate automatic preview text from file content
 */
function generateAutoPreview($filePath, $originalFilename) {
    $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    
    // Text file extensions that can be read
    $textExtensions = ['txt', 'log', 'md', 'json', 'sql', 'csv', 'xml', 'html', 'css', 'js', 'php', 'py', 'java', 'cpp', 'c', 'h', 'ini', 'conf'];
    
    if (in_array($extension, $textExtensions)) {
        try {
            // Read file content (limit to first 2MB to avoid memory issues)
            $content = file_get_contents($filePath, false, null, 0, 2048000);
            
            if ($content === false) {
                return getDefaultPreviewForType($extension);
            }
            
            // Clean up the content
            $content = trim($content);
            
            if (empty($content)) {
                return getDefaultPreviewForType($extension);
            }
            
            // For JSON files, try to format them
            if ($extension === 'json') {
                $jsonData = json_decode($content, true);
                if ($jsonData !== null) {
                    $content = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }
            }
            
            // Extract a random sample from the content
            $sampleLength = 800;
            if (strlen($content) > $sampleLength) {
                $maxStart = strlen($content) - $sampleLength;
                $startPos = rand(0, $maxStart);
                
                // Try to start from a line break if possible
                $newlinePos = strpos($content, "\n", $startPos);
                if ($newlinePos !== false && ($newlinePos - $startPos) < 100) {
                    $startPos = $newlinePos + 1;
                }
                
                $sample = substr($content, $startPos, $sampleLength);
                
                // Try to end at a complete line
                $lastNewline = strrpos($sample, "\n");
                if ($lastNewline !== false && $lastNewline > ($sampleLength * 0.8)) {
                    $sample = substr($sample, 0, $lastNewline);
                }
                
                return $sample . "\n\n... (عينة عشوائية من الملف)";
            } else {
                return $content;
            }
            
        } catch (Exception $e) {
            return getDefaultPreviewForType($extension);
        }
    } else {
        return getDefaultPreviewForType($extension);
    }
}

/**
 * Get default preview text for non-text files
 */
function getDefaultPreviewForType($extension) {
    switch (strtolower($extension)) {
        case 'pdf':
            return "هلا 📚 هذا ملف PDF مفيد جداً! يحتوي على معلومات قيمة ومفيدة. قم بتحميله للاطلاع على المحتوى الكامل.";
            
        case 'doc':
        case 'docx':
            return "مستند Word مفيد 📝 يحتوي على معلومات مهمة ومنظمة بشكل جيد. مناسب للقراءة والمراجعة.";
            
        case 'xls':
        case 'xlsx':
            return "جدول Excel مفيد 📈 يحتوي على بيانات مهمة ومنظمة. مناسب للتحليل والحسابات.";
            
        case 'ppt':
        case 'pptx':
            return "عرض PowerPoint مميز 🎆 يحتوي على معلومات مفيدة بشكل بصري جذاب. مناسب للعروض والتعلم.";
            
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'webp':
            return "صورة جميلة 🎨 بجودة عالية ووضوح ممتاز. مناسبة للعرض والاستخدام الشخصي أو المهني.";
            
        case 'zip':
        case 'rar':
        case '7z':
            return "ملف مضغوط 🗂️ يحتوي على مجموعة من الملفات المفيدة. قم بفك الضغط لاستكشاف المحتوى.";
            
        case 'mp3':
        case 'wav':
        case 'ogg':
            return "ملف صوتي 🎵 بجودة عالية. مناسب للاستماع والاستمتاع بالمحتوى الصوتي.";
            
        case 'mp4':
        case 'avi':
        case 'mov':
            return "فيديو ممتاز 🎥 بجودة عالية. مناسب للمشاهدة والتعلم من المحتوى البصري.";
            
        default:
            return "ملف مفيد ومفور ✨ يحتوي على معلومات وبيانات قيمة. قم بتحميله للاطلاع على المحتوى.";
    }
}

exit;
?>
