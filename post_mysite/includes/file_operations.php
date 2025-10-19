<?php

function transferFileOwnership($originalFilePath, $buyerUsername) {
    // إنشاء المسار الجديد للملف في مجلد المشتري
    $filename = basename($originalFilePath);
    $buyerFilePath = "uploads/{$buyerUsername}/purchased/{$filename}";
    
    // التأكد من وجود مجلد المشتري
    $buyerDir = dirname($buyerFilePath);
    if (!file_exists($buyerDir)) {
        mkdir($buyerDir, 0755, true);
    }
    
    // نسخ الملف إلى مجلد المشتري
    if (file_exists($originalFilePath)) {
        // نسخ الملف
        if (!copy($originalFilePath, $buyerFilePath)) {
            throw new Exception('فشل في نسخ الملف إلى مجلد المشتري');
        }
        
        // حذف الملف الأصلي
        if (!unlink($originalFilePath)) {
            // إذا فشل الحذف، نحاول مرة أخرى بعد التأكد من الصلاحيات
            chmod($originalFilePath, 0777);
            if (!unlink($originalFilePath)) {
                throw new Exception('فشل في حذف الملف الأصلي');
            }
        }
        
        return $buyerFilePath;
    } else {
        throw new Exception('الملف الأصلي غير موجود');
    }
}
