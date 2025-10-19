<?php

class FileValidator {

    private $allowedExtensions = [
        'pdf', 'doc', 'docx', 'txt', 'rtf', 'odt',
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',
        'zip', 'rar', '7z', 'tar', 'gz',
        'sql', 'json', 'xml', 'csv', 'yml', 'yaml',
        'xls', 'xlsx', 'ppt', 'pptx',
        'mp3', 'wav', 'ogg', 'flac',
        'mp4', 'avi', 'mov', 'mkv', 'wmv',
        'exe', 'msi', 'apk', 'deb', 'rpm',
        'js', 'css', 'html', 'php', 'py', 'java', 'cpp', 'c', 'h',
        'psd', 'ai', 'sketch', 'fig'
    ];

    private $maxFileSize = 100 * 1024 * 1024;

    public function validateFile($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('ملف غير صالح');
        }

        $fileSize = $file['size'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];

        if ($fileSize <= 0) {
            throw new Exception('الملف فارغ');
        }

        if ($fileSize > $this->maxFileSize) {
            throw new Exception('حجم الملف كبير جداً (الحد الأقصى 100 ميجابايت)');
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($extension, $this->allowedExtensions)) {
            throw new Exception('نوع الملف غير مدعوم');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmpName);
        finfo_close($finfo);

        if (!$this->isValidMimeType($mimeType, $extension)) {
            throw new Exception('نوع الملف غير مطابق للامتداد');
        }

        return true;
    }

    private function isValidMimeType($mimeType, $extension) {
        $validMimeTypes = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'txt' => ['text/plain'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'rar' => ['application/x-rar-compressed', 'application/vnd.rar'],
            'mp3' => ['audio/mpeg'],
            'mp4' => ['video/mp4'],
        ];

        if (isset($validMimeTypes[$extension])) {
            return in_array($mimeType, $validMimeTypes[$extension]);
        }

        return true;
    }

    public function validatePreviewImage($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('صورة معاينة غير صالحة');
        }

        $fileSize = $file['size'];
        $maxPreviewSize = 5 * 1024 * 1024;

        if ($fileSize > $maxPreviewSize) {
            throw new Exception('حجم صورة المعاينة كبير جداً (الحد الأقصى 5 ميجابايت)');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mimeType, $allowedImageTypes)) {
            throw new Exception('صورة المعاينة يجب أن تكون JPG أو PNG أو GIF أو WEBP');
        }

        return true;
    }
}
