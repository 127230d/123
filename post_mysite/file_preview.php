<?php
require_once 'dssssssssb.php';

// Get file ID from URL parameter
$file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$file_id) {
    header('HTTP/1.0 404 Not Found');
    exit('File not found');
}

// Get file information with preview data
$file_query = "SELECT sf.*, 
               u.username AS owner_name,
               fp.preview_filename,
               fp.preview_path,
               fp.preview_size,
               fp.preview_type
               FROM shared_files sf 
               LEFT JOIN login u ON sf.current_owner_id = u.username 
               LEFT JOIN file_previews fp ON sf.id = fp.file_id
               WHERE sf.id = ? AND sf.is_available = 1 AND sf.preview_allowed = 1";

$stmt = mysqli_prepare($con, $file_query);
mysqli_stmt_bind_param($stmt, "i", $file_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$file = mysqli_fetch_assoc($result);

if (!$file) {
    header('HTTP/1.0 404 Not Found');
    exit('File not found or preview not available');
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Function to get file icon
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'txt' => 'fas fa-file-alt',
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'gif' => 'fas fa-file-image',
        'zip' => 'fas fa-file-archive',
        'rar' => 'fas fa-file-archive',
        '7z' => 'fas fa-file-archive',
        'sql' => 'fas fa-database',
        'json' => 'fas fa-code',
        'csv' => 'fas fa-file-csv',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'ppt' => 'fas fa-file-powerpoint',
        'pptx' => 'fas fa-file-powerpoint',
        'mp3' => 'fas fa-file-audio',
        'mp4' => 'fas fa-file-video',
        'exe' => 'fas fa-file-code',
    ];
    return $icons[$ext] ?? 'fas fa-file';
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معاينة الملف - <?php echo htmlspecialchars($file['original_name']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #00ff00;
            --primary-green-glow: #00ff0033;
            --dark-green: #006400;
            --background-black: #050505;
            --card-black: #0c0c0c;
            --text-gray: #888888;
            --text-light: #cccccc;
            --hover-green: #00800022;
            --gradient-dark: linear-gradient(145deg, #0a0a0a, #151515);
            --gradient-glow: linear-gradient(145deg, rgba(0, 255, 0, 0.05), transparent);
            --border-glow: rgba(0, 255, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            font-size: 14px;
            line-height: 1.6;
            color: var(--primary-green);
            background-color: var(--background-black);
            background-image:
                linear-gradient(rgba(0, 255, 0, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 0, 0.02) 1px, transparent 1px);
            background-size: 30px 30px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: var(--gradient-dark);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border-glow);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-green), var(--dark-green));
        }

        .header h1 {
            color: var(--primary-green);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .file-preview-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 25px;
            margin-bottom: 25px;
        }

        .preview-main {
            background: var(--gradient-dark);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid var(--border-glow);
        }

        .preview-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-card {
            background: var(--gradient-dark);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-glow);
        }

        .sidebar-card h3 {
            color: var(--primary-green);
            font-size: 1.2rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-icon-large {
            font-size: 4rem;
            color: var(--primary-green);
            text-align: center;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .preview-content {
            background: var(--card-black);
            border: 1px solid var(--border-glow);
            border-radius: 8px;
            padding: 20px;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-gray);
            font-style: italic;
        }

        .preview-content.has-content {
            align-items: flex-start;
            justify-content: flex-start;
            font-style: normal;
            color: var(--primary-green);
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-glow);
            font-size: 13px;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-gray);
            font-weight: 600;
        }

        .info-value {
            color: var(--primary-green);
            font-weight: 600;
        }

        .purchase-section {
            text-align: center;
            padding: 20px;
            background: var(--card-black);
            border: 1px solid var(--border-glow);
            border-radius: 8px;
            margin-top: 15px;
        }

        .price-display {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-green);
            margin-bottom: 15px;
        }

        .btn {
            background: var(--gradient-dark);
            border: 1px solid var(--border-glow);
            color: var(--primary-green);
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 600;
        }

        .btn:hover {
            background: var(--primary-green);
            color: var(--background-black);
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            color: var(--background-black);
            border-color: var(--primary-green);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--primary-green-glow);
        }

        .preview-notice {
            background: var(--card-black);
            border: 1px solid #ffa500;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ffa500;
        }

        .owner-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .owner-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--background-black);
            font-weight: 600;
            font-size: 1.2rem;
        }

        .owner-name {
            color: var(--primary-green);
            font-weight: 600;
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .file-preview-container {
                grid-template-columns: 1fr;
            }
            
            .preview-sidebar {
                order: -1;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="<?php echo getFileIcon($file['original_name']); ?>"></i>
                معاينة الملف
            </h1>
            <p>معاينة مجانية للملف قبل الشراء</p>
        </div>

        <div class="preview-notice">
            <i class="fas fa-info-circle"></i>
            <span>هذه معاينة مجانية للملف. للحصول على الملف الكامل، يرجى شراؤه.</span>
        </div>

        <div class="file-preview-container">
            <div class="preview-main">
                <div class="owner-info">
                    <div class="owner-avatar">
                        <?php echo strtoupper(substr($file['owner_name'], 0, 2)); ?>
                    </div>
                    <div>
                        <div class="owner-name"><?php echo htmlspecialchars($file['owner_name']); ?></div>
                        <small style="color: var(--text-gray);">مالك الملف</small>
                    </div>
                </div>

                <div class="file-icon-large">
                    <i class="<?php echo getFileIcon($file['original_name']); ?>"></i>
                </div>

                <div class="preview-content">
                    <?php if ($file['preview_filename']): ?>
                        <?php 
                        $preview_ext = strtolower(pathinfo($file['preview_filename'], PATHINFO_EXTENSION));
                        if (in_array($preview_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                            <img src="<?php echo htmlspecialchars($file['preview_path']); ?>" 
                                 alt="معاينة الملف" 
                                 style="max-width: 100%; height: auto; border-radius: 8px;">
                        <?php elseif ($preview_ext === 'txt'): ?>
                            <div style="text-align: right; width: 100%;">
                                <?php 
                                if (file_exists($file['preview_path'])) {
                                    $content = file_get_contents($file['preview_path']);
                                    echo '<pre style="white-space: pre-wrap; font-size: 13px; line-height: 1.6;">' . 
                                         htmlspecialchars(mb_substr($content, 0, 1000)) . 
                                         (mb_strlen($content) > 1000 ? '...' : '') . '</pre>';
                                } else {
                                    echo "معاينة غير متاحة";
                                }
                                ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center;">
                                <i class="<?php echo getFileIcon($file['preview_filename']); ?>" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.6;"></i>
                                <p>معاينة متاحة للملف</p>
                                <small>الملف: <?php echo htmlspecialchars($file['preview_filename']); ?></small>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="text-align: center;">
                            <i class="fas fa-eye-slash" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.6;"></i>
                            <p>لا توجد معاينة متاحة لهذا الملف</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="preview-sidebar">
                <div class="sidebar-card">
                    <h3><i class="fas fa-info-circle"></i> معلومات الملف</h3>
                    
                    <div class="info-item">
                        <span class="info-label">اسم الملف:</span>
                        <span class="info-value"><?php echo htmlspecialchars($file['original_name']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">الحجم:</span>
                        <span class="info-value"><?php echo formatFileSize($file['file_size']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">النوع:</span>
                        <span class="info-value"><?php echo htmlspecialchars($file['file_type']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">تاريخ الرفع:</span>
                        <span class="info-value"><?php echo date('Y-m-d', strtotime($file['created_at'])); ?></span>
                    </div>

                    <?php if ($file['description']): ?>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-glow);">
                        <strong style="color: var(--primary-green);">الوصف:</strong>
                        <p style="color: var(--text-light); margin-top: 8px; font-size: 13px; line-height: 1.5;">
                            <?php echo nl2br(htmlspecialchars($file['description'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="sidebar-card">
                    <h3><i class="fas fa-shopping-cart"></i> شراء الملف</h3>
                    
                    <div class="purchase-section">
                        <div class="price-display">
                            <?php echo number_format($file['price']); ?> نقطة
                        </div>
                        
                        <a href="index.php#file-<?php echo $file['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-shopping-cart"></i>
                            شراء الملف الآن
                        </a>
                        
                        <p style="color: var(--text-gray); font-size: 12px; margin-top: 10px;">
                            انتقل إلى الصفحة الرئيسية لإتمام عملية الشراء
                        </p>
                    </div>
                </div>

                <?php if ($file['preview_filename']): ?>
                <div class="sidebar-card">
                    <h3><i class="fas fa-eye"></i> معلومات المعاينة</h3>
                    
                    <div class="info-item">
                        <span class="info-label">ملف المعاينة:</span>
                        <span class="info-value"><?php echo htmlspecialchars($file['preview_filename']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">حجم المعاينة:</span>
                        <span class="info-value"><?php echo formatFileSize($file['preview_size']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">نوع المعاينة:</span>
                        <span class="info-value"><?php echo htmlspecialchars($file['preview_type']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="text-align: center; margin-top: 25px;">
            <a href="index.php" class="btn">
                <i class="fas fa-arrow-right"></i>
                العودة إلى الصفحة الرئيسية
            </a>
        </div>
    </div>
</body>
</html>
