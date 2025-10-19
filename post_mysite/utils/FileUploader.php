<?php

class FileUploader {

    private $uploadDir;
    private $previewDir;

    public function __construct() {
        $this->uploadDir = __DIR__ . '/../storage/files/';
        $this->previewDir = __DIR__ . '/../storage/previews/';

        $this->ensureDirectoryExists($this->uploadDir);
        $this->ensureDirectoryExists($this->previewDir);
    }

    private function ensureDirectoryExists($dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function uploadFile($file) {
        $originalFilename = basename($file['name']);
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $fileSize = $file['size'];
        $tmpName = $file['tmp_name'];

        $storedFilename = $this->generateSecureFilename($extension);
        $targetPath = $this->uploadDir . $storedFilename;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new Exception('فشل رفع الملف');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $targetPath);
        finfo_close($finfo);

        $fileType = $this->determineFileType($mimeType, $extension);

        return [
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'file_path' => 'storage/files/' . $storedFilename,
            'file_size' => $fileSize,
            'file_type' => $fileType,
            'file_extension' => $extension,
            'mime_type' => $mimeType
        ];
    }

    public function uploadPreviewImage($file) {
        $validator = new FileValidator();
        $validator->validatePreviewImage($file);

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $storedFilename = $this->generateSecureFilename($extension);
        $targetPath = $this->previewDir . $storedFilename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('فشل رفع صورة المعاينة');
        }

        return [
            'filename' => $storedFilename,
            'path' => 'storage/previews/' . $storedFilename
        ];
    }

    private function generateSecureFilename($extension) {
        return time() . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
    }

    private function determineFileType($mimeType, $extension) {
        if (strpos($mimeType, 'image/') === 0) {
            return 'image';
        } elseif (strpos($mimeType, 'video/') === 0) {
            return 'video';
        } elseif (strpos($mimeType, 'audio/') === 0) {
            return 'audio';
        } elseif (strpos($mimeType, 'application/pdf') === 0) {
            return 'document';
        } elseif (in_array($extension, ['doc', 'docx', 'txt', 'rtf', 'odt'])) {
            return 'document';
        } elseif (in_array($extension, ['xls', 'xlsx', 'csv'])) {
            return 'spreadsheet';
        } elseif (in_array($extension, ['ppt', 'pptx'])) {
            return 'presentation';
        } elseif (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'])) {
            return 'archive';
        } elseif (in_array($extension, ['exe', 'msi', 'apk', 'deb', 'rpm'])) {
            return 'executable';
        } elseif (in_array($extension, ['sql', 'json', 'xml', 'csv', 'yml', 'yaml'])) {
            return 'data';
        } elseif (in_array($extension, ['js', 'css', 'html', 'php', 'py', 'java', 'cpp', 'c', 'h'])) {
            return 'code';
        } else {
            return 'other';
        }
    }

    public function deleteFile($filename) {
        $filePath = $this->uploadDir . $filename;
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }

    public function deletePreviewImage($filename) {
        $filePath = $this->previewDir . $filename;
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
}
