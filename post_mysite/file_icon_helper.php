<?php
/**
 * دوال مساعدة لأيقونات الملفات
 * File Icon Helper Functions
 */

// حماية ضد إعادة التعريف
if (!function_exists('getFileIcon')) {

/**
 * التحقق من نوع الملف وتصنيفه
 */
function getFileType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $types = [
        // وثائق ومستندات
        'documents' => ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt'],
        // صور
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'tiff', 'ico'],
        // ملفات مضغوطة
        'archives' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'],
        // جداول بيانات
        'spreadsheets' => ['xls', 'xlsx', 'csv', 'ods'],
        // عروض تقديمية
        'presentations' => ['ppt', 'pptx', 'odp'],
        // فيديو
        'videos' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', '3gp', 'm4v'],
        // صوت
        'audio' => ['mp3', 'wav', 'flac', 'aac', 'ogg', 'wma', 'm4a'],
        // ملفات برمجية
        'code' => ['html', 'htm', 'css', 'js', 'php', 'py', 'java', 'cpp', 'c', 'sql', 'json', 'xml', 'yaml', 'yml'],
        // ملفات تنفيذية
        'executables' => ['exe', 'msi', 'deb', 'rpm', 'dmg', 'apk', 'ipa'],
        // خطوط
        'fonts' => ['ttf', 'otf', 'woff', 'woff2']
    ];
    
    foreach ($types as $type => $extensions) {
        if (in_array($ext, $extensions)) {
            return $type;
        }
    }
    
    return 'other';
}

/**
 * الحصول على الوصف العربي لنوع الملف
 */
function getFileTypeArabic($type) {
    $translations = [
        'documents' => 'مستند',
        'images' => 'صورة',
        'archives' => 'ملف مضغوط',
        'spreadsheets' => 'جدول بيانات',
        'presentations' => 'عرض تقديمي',
        'videos' => 'فيديو',
        'audio' => 'ملف صوتي',
        'code' => 'ملف برمجي',
        'executables' => 'ملف تنفيذي',
        'fonts' => 'خط',
        'other' => 'نوع آخر'
    ];
    
    return $translations[$type] ?? 'نوع آخر';
}

/**
 * الحصول على أيقونة الملف حسب نوعه
 */
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        // المستندات
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word', 
        'docx' => 'fas fa-file-word',
        'txt' => 'fas fa-file-alt',
        'rtf' => 'fas fa-file-word',
        
        // الصور
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'gif' => 'fas fa-file-image',
        'bmp' => 'fas fa-file-image',
        'svg' => 'fas fa-file-image',
        'webp' => 'fas fa-file-image',
        'tiff' => 'fas fa-file-image',
        'ico' => 'fas fa-file-image',
        
        // أرشيف ومضغوط
        'zip' => 'fas fa-file-zipper',
        'rar' => 'fas fa-file-zipper',
        '7z' => 'fas fa-file-zipper',
        'tar' => 'fas fa-file-zipper',
        'gz' => 'fas fa-file-zipper',
        'bz2' => 'fas fa-file-zipper',
        
        // جداول البيانات
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'csv' => 'fas fa-file-csv',
        'ods' => 'fas fa-file-excel',
        
        // عروض تقديمية  
        'ppt' => 'fas fa-file-powerpoint',
        'pptx' => 'fas fa-file-powerpoint',
        'odp' => 'fas fa-file-powerpoint',
        
        // فيديو
        'mp4' => 'fas fa-file-video',
        'avi' => 'fas fa-file-video', 
        'mov' => 'fas fa-file-video',
        'wmv' => 'fas fa-file-video',
        'flv' => 'fas fa-file-video',
        'mkv' => 'fas fa-file-video',
        'webm' => 'fas fa-file-video',
        '3gp' => 'fas fa-file-video',
        'm4v' => 'fas fa-file-video',
        
        // صوت
        'mp3' => 'fas fa-file-audio',
        'wav' => 'fas fa-file-audio',
        'flac' => 'fas fa-file-audio',
        'aac' => 'fas fa-file-audio',
        'ogg' => 'fas fa-file-audio',
        'wma' => 'fas fa-file-audio',
        'm4a' => 'fas fa-file-audio',
        
        // برمجة وكود
        'html' => 'fas fa-file-code',
        'htm' => 'fas fa-file-code',
        'css' => 'fas fa-file-code', 
        'js' => 'fas fa-file-code',
        'php' => 'fas fa-file-code',
        'py' => 'fas fa-file-code',
        'java' => 'fas fa-file-code',
        'cpp' => 'fas fa-file-code',
        'c' => 'fas fa-file-code',
        'sql' => 'fas fa-database',
        'json' => 'fas fa-file-code',
        'xml' => 'fas fa-file-code',
        'yaml' => 'fas fa-file-code',
        'yml' => 'fas fa-file-code',
        
        // تنفيذي وتطبيقات
        'exe' => 'fas fa-file-arrow-down',
        'msi' => 'fas fa-file-arrow-down', 
        'deb' => 'fas fa-file-arrow-down',
        'rpm' => 'fas fa-file-arrow-down',
        'dmg' => 'fas fa-file-arrow-down',
        'apk' => 'fas fa-mobile-alt',
        'ipa' => 'fas fa-mobile-alt',
        
        // خطوط
        'ttf' => 'fas fa-font',
        'otf' => 'fas fa-font',
        'woff' => 'fas fa-font',
        'woff2' => 'fas fa-font',
        'eot' => 'fas fa-font',
        
        // ملفات خاصة
        'iso' => 'fas fa-compact-disc',
        'dmg' => 'fas fa-compact-disc',
        'torrent' => 'fas fa-share-alt',
    ];
    return $icons[$ext] ?? 'fas fa-file';
}

} // نهاية حماية getFileIcon

if (!function_exists('getFileIconColor')) {
/**
 * الحصول على لون أيقونة الملف
 */
function getFileIconColor($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $colors = [
        // المستندات - أحمر
        'pdf' => '#dc3545',
        'doc' => '#2b579a', 'docx' => '#2b579a', 'rtf' => '#2b579a',
        'txt' => '#6c757d',
        
        // الصور - أخضر
        'jpg' => '#28a745', 'jpeg' => '#28a745', 'png' => '#28a745', 
        'gif' => '#28a745', 'bmp' => '#28a745', 'svg' => '#28a745', 
        'webp' => '#28a745', 'tiff' => '#28a745', 'ico' => '#28a745',
        
        // أرشيف - برتقالي
        'zip' => '#fd7e14', 'rar' => '#fd7e14', '7z' => '#fd7e14',
        'tar' => '#fd7e14', 'gz' => '#fd7e14', 'bz2' => '#fd7e14',
        
        // جداول - أخضر داكن
        'xls' => '#198754', 'xlsx' => '#198754', 'csv' => '#198754', 'ods' => '#198754',
        
        // عروض - أحمر فاتح 
        'ppt' => '#d63384', 'pptx' => '#d63384', 'odp' => '#d63384',
        
        // فيديو - أزرق
        'mp4' => '#0d6efd', 'avi' => '#0d6efd', 'mov' => '#0d6efd',
        'wmv' => '#0d6efd', 'flv' => '#0d6efd', 'mkv' => '#0d6efd',
        'webm' => '#0d6efd', '3gp' => '#0d6efd', 'm4v' => '#0d6efd',
        
        // صوت - بنفسجي
        'mp3' => '#6f42c1', 'wav' => '#6f42c1', 'flac' => '#6f42c1',
        'aac' => '#6f42c1', 'ogg' => '#6f42c1', 'wma' => '#6f42c1', 'm4a' => '#6f42c1',
        
        // برمجة - سماوي
        'html' => '#20c997', 'htm' => '#20c997', 'css' => '#20c997', 'js' => '#20c997',
        'php' => '#20c997', 'py' => '#20c997', 'java' => '#20c997',
        'cpp' => '#20c997', 'c' => '#20c997', 'json' => '#20c997', 'xml' => '#20c997',
        'yaml' => '#20c997', 'yml' => '#20c997',
        'sql' => '#17a2b8',
        
        // تنفيذي - أحمر داكن
        'exe' => '#dc3545', 'msi' => '#dc3545', 'deb' => '#dc3545',
        'rpm' => '#dc3545', 'dmg' => '#dc3545',
        'apk' => '#28a745', 'ipa' => '#28a745',
        
        // خطوط - ذهبي
        'ttf' => '#ffc107', 'otf' => '#ffc107', 'woff' => '#ffc107', 'woff2' => '#ffc107', 'eot' => '#ffc107',
        
        // ملفات خاصة
        'iso' => '#6c757d', 'torrent' => '#dc3545',
    ];
    return $colors[$ext] ?? 'var(--primary-green)';
}

} // نهاية حماية getFileIconColor

if (!function_exists('displayFileIcon')) {
/**
 * عرض أيقونة الملف مع التنسيق الكامل
 */
function displayFileIcon($filename, $size = '1.2rem', $showShadow = true) {
    $icon = getFileIcon($filename);
    $color = getFileIconColor($filename);
    $shadowStyle = $showShadow ? "filter: drop-shadow(0 0 6px {$color}33);" : '';
    
    return "<i class=\"{$icon}\" style=\"color: {$color}; font-size: {$size}; {$shadowStyle}\"></i>";
}

} // نهاية حماية displayFileIcon

if (!function_exists('displayFileNameWithIcon')) {
/**
 * عرض أيقونة الملف مع اسم الملف
 */
function displayFileNameWithIcon($filename, $maxLength = 50) {
    $displayName = strlen($filename) > $maxLength ? substr($filename, 0, $maxLength) . '...' : $filename;
    $icon = displayFileIcon($filename);
    
    return "<span class=\"file-name-with-icon\">{$icon} {$displayName}</span>";
}

} // نهاية حماية displayFileNameWithIcon

if (!function_exists('getFileCategory')) {
/**
 * الحصول على فئة الملف (للتصنيف)
 */
function getFileCategory($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $categories = [
        'document' => ['pdf', 'doc', 'docx', 'txt', 'rtf'],
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'tiff', 'ico'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'],
        'spreadsheet' => ['xls', 'xlsx', 'csv', 'ods'],
        'presentation' => ['ppt', 'pptx', 'odp'],
        'video' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', '3gp', 'm4v'],
        'audio' => ['mp3', 'wav', 'flac', 'aac', 'ogg', 'wma', 'm4a'],
        'code' => ['html', 'htm', 'css', 'js', 'php', 'py', 'java', 'cpp', 'c', 'json', 'xml', 'yaml', 'yml', 'sql'],
        'executable' => ['exe', 'msi', 'deb', 'rpm', 'dmg', 'apk', 'ipa'],
        'font' => ['ttf', 'otf', 'woff', 'woff2', 'eot'],
        'disk' => ['iso', 'dmg'],
        'torrent' => ['torrent']
    ];
    
    foreach ($categories as $category => $extensions) {
        if (in_array($ext, $extensions)) {
            return $category;
        }
    }
    
    return 'other';
}

} // نهاية حماية getFileCategory

if (!function_exists('getFileCategoryName')) {
/**
 * الحصول على اسم فئة الملف بالعربية
 */
function getFileCategoryName($filename) {
    $category = getFileCategory($filename);
    
    $names = [
        'document' => 'مستندات',
        'image' => 'صور',
        'archive' => 'أرشيف',
        'spreadsheet' => 'جداول',
        'presentation' => 'عروض تقديمية',
        'video' => 'فيديو',
        'audio' => 'صوت',
        'code' => 'برمجة',
        'executable' => 'تطبيقات',
        'font' => 'خطوط',
        'disk' => 'أقراص',
        'torrent' => 'تورنت',
        'other' => 'أخرى'
    ];
    
    return $names[$category] ?? 'غير محدد';
}

} // نهاية حماية getFileCategoryName

if (!function_exists('getFileIconCSS')) {
/**
 * CSS لتنسيق أيقونات الملفات
 */
function getFileIconCSS() {
    return '
    <style>
    .file-name-with-icon {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
    }
    
    .file-icon-large {
        font-size: 2.5rem !important;
        margin-bottom: 10px;
    }
    
    .file-icon-medium {
        font-size: 1.8rem !important;
    }
    
    .file-icon-small {
        font-size: 1rem !important;
    }
    
    .file-category-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
    }
    </style>';
} // نهاية حماية getFileIconCSS

// إضافة دالة تنسيق حجم الملف إذا لم تكن موجودة
if (!function_exists('formatFileSize')) {
/**
 * تنسيق حجم الملف
 */
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
} // نهاية حماية formatFileSize

// إضافة دالة timeAgo إذا لم تكن موجودة
if (!function_exists('timeAgo')) {
/**
 * تنسيق الوقت بصيغة "منذ ..."
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'الآن';
    if ($time < 3600) return floor($time/60) . ' دقائق';
    if ($time < 86400) return floor($time/3600) . ' ساعات';
    if ($time < 2592000) return floor($time/86400) . ' أيام';
    if ($time < 31536000) return floor($time/2592000) . ' أشهر';
    return floor($time/31536000) . ' سنوات';
}
} // نهاية حماية timeAgo

} // نهاية جميع الحمايات
?>
