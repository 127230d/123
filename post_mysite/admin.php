<?php
session_start();
require_once 'dssssssssb.php';
require_once 'file_icon_helper.php';

// Function to format text with line breaks
function format_text($text) {
    return str_replace(["\r\n", "\r", "\n"], "<br>", htmlspecialchars($text));
}

// تم نقل دالة formatFileSize إلى file_icon_helper.php

// Check if user is admin
if (!isset($_SESSION["username"]) || !isset($_SESSION['subscription']) || $_SESSION['subscription'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['comment_id'])) {
    $comment_id = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'];
    $admin_username = $_SESSION["username"];
    
    if ($comment_id) {
        if ($action === 'approve') {
            $stmt = mysqli_prepare($con, "UPDATE comments SET is_approved = 1 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $comment_id);
            mysqli_stmt_execute($stmt);
            $message = "تم الموافقة على التعليق بنجاح";
        } elseif ($action === 'reject') {
            $rejection_reason = trim($_POST['comment_rejection_reason'] ?? 'لم يتم تحديد سبب الرفض');
            
            // Delete the comment and its ratings with rejection reason
            mysqli_begin_transaction($con);
            
            try {
                // حفظ ملاحظة الرفض أولاً
                $save_rejection = mysqli_prepare($con, "INSERT INTO file_rejections (file_id, admin_username, rejection_reason, item_type) VALUES (?, ?, ?, 'comment')");
                mysqli_stmt_bind_param($save_rejection, "iss", $comment_id, $admin_username, $rejection_reason);
                mysqli_stmt_execute($save_rejection);
                mysqli_stmt_close($save_rejection);
                
                // تحديث التعليق بحالة الرفض
                $update_comment = mysqli_prepare($con, "UPDATE comments SET rejection_reason = ?, rejected_by = ?, rejected_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($update_comment, "ssi", $rejection_reason, $admin_username, $comment_id);
                mysqli_stmt_execute($update_comment);
                mysqli_stmt_close($update_comment);
                
                // Delete ratings first
                $stmt1 = mysqli_prepare($con, "DELETE FROM comment_ratings WHERE comment_id = ?");
                mysqli_stmt_bind_param($stmt1, "i", $comment_id);
                mysqli_stmt_execute($stmt1);
                
                // Delete comment
                $stmt2 = mysqli_prepare($con, "DELETE FROM comments WHERE id = ?");
                mysqli_stmt_bind_param($stmt2, "i", $comment_id);
                mysqli_stmt_execute($stmt2);
                
                mysqli_commit($con);
                $message = "تم رفض وحذف التعليق مع حفظ ملاحظة الرفض بنجاح";
            } catch (Exception $e) {
                mysqli_rollback($con);
                $message = "حدث خطأ أثناء حذف التعليق";
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle file approval/rejection for shared files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_action'], $_POST['file_id'])) {
    $file_id = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
    $file_action = $_POST['file_action'];
    $admin_username = $_SESSION["username"];

    if ($file_id) {
        if ($file_action === 'approve_file') {
            // التحقق من وجود حقول المراجعة وإضافتها إذا لزم الأمر
            $check_column = mysqli_query($con, "SHOW COLUMNS FROM shared_files LIKE 'review_status'");
            if (mysqli_num_rows($check_column) == 0) {
                mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN review_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
                mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN rejection_reason TEXT NULL");
                mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN rejection_date DATETIME NULL");
                mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN reviewer_name VARCHAR(100) NULL");
            }
            
            $stmtFile = mysqli_prepare($con, "UPDATE shared_files SET 
                is_available = 1, 
                review_status = 'approved',
                reviewer_name = ?,
                reviewed_at = NOW()
                WHERE id = ?");
            mysqli_stmt_bind_param($stmtFile, "si", $admin_username, $file_id);
            mysqli_stmt_execute($stmtFile);
            mysqli_stmt_close($stmtFile);
            $message = "تمت الموافقة على الملف بنجاح";
        } elseif ($file_action === 'reject_file') {
            $rejection_reason = trim($_POST['rejection_reason'] ?? 'لم يتم تحديد سبب الرفض');
            
            // بدء معاملة آمنة
            mysqli_begin_transaction($con);
            
            try {
                // حفظ ملاحظة الرفض أولاً
                $save_rejection = mysqli_prepare($con, "INSERT INTO file_rejections (file_id, admin_username, rejection_reason, item_type) VALUES (?, ?, ?, 'file')");
                mysqli_stmt_bind_param($save_rejection, "iss", $file_id, $admin_username, $rejection_reason);
                mysqli_stmt_execute($save_rejection);
                mysqli_stmt_close($save_rejection);
                
                // تحديث الملف بحالة الرفض
                $update_file = mysqli_prepare($con, "UPDATE shared_files SET rejection_reason = ?, rejected_by = ?, rejected_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($update_file, "ssi", $rejection_reason, $admin_username, $file_id);
                mysqli_stmt_execute($update_file);
                mysqli_stmt_close($update_file);
                
                // حذف سجلات المشتريات المرتبطة أولاً (إن وجدت)
                $delete_purchases = mysqli_prepare($con, "DELETE FROM file_purchases WHERE file_id = ?");
                if ($delete_purchases) {
                    mysqli_stmt_bind_param($delete_purchases, "i", $file_id);
                    mysqli_stmt_execute($delete_purchases);
                    mysqli_stmt_close($delete_purchases);
                }
                
                // حذف ملفات المعاينة (إن وجدت)
                $delete_previews = mysqli_prepare($con, "DELETE FROM file_previews WHERE file_id = ?");
                if ($delete_previews) {
                    mysqli_stmt_bind_param($delete_previews, "i", $file_id);
                    mysqli_stmt_execute($delete_previews);
                    mysqli_stmt_close($delete_previews);
                }
                
                // التحقق من وجود حقل review_status وإضافته إذا لزم الأمر
                $check_column = mysqli_query($con, "SHOW COLUMNS FROM shared_files LIKE 'review_status'");
                if (mysqli_num_rows($check_column) == 0) {
                    mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN review_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved'");
                    mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN rejection_reason TEXT NULL");
                    mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN rejection_date DATETIME NULL");
                    mysqli_query($con, "ALTER TABLE shared_files ADD COLUMN reviewer_name VARCHAR(100) NULL");
                }
                
                // تحديث حالة الملف إلى مرفوض بدلاً من حذفه
                $update_status = mysqli_prepare($con, "UPDATE shared_files SET 
                    review_status = 'rejected',
                    rejection_reason = ?,
                    rejection_date = NOW(),
                    reviewer_name = ?
                    WHERE id = ?");
                mysqli_stmt_bind_param($update_status, "ssi", $rejection_reason, $admin_username, $file_id);
                mysqli_stmt_execute($update_status);
                mysqli_stmt_close($update_status);
                
                // تأكيد المعاملة
                mysqli_commit($con);
                
                $message = "تم رفض الملف بنجاح. المستخدم يمكنه إعادة رفعه أو حذفه";
                
            } catch (Exception $e) {
                // إلغاء المعاملة في حالة وجود خطأ
                mysqli_rollback($con);
                $message = "حدث خطأ أثناء رفض الملف: " . $e->getMessage();
            }
        }
    }
}

// Fetch pending comments with detailed user information
$pending_query = "SELECT c.*, 
                  u.username,
                  u.points AS user_points,
                  u.subscription AS user_subscription,
                  u.created_at AS user_registered,
                  (SELECT COUNT(*) FROM comments WHERE user_id = c.user_id) as total_user_comments,
                  (SELECT COUNT(*) FROM comments WHERE user_id = c.user_id AND is_approved = 1) as approved_user_comments
                  FROM comments c 
                  LEFT JOIN login u ON CAST(c.user_id AS CHAR CHARACTER SET utf8) = CAST(u.username AS CHAR CHARACTER SET utf8)
                  WHERE is_approved = 0 
                  ORDER BY created_at DESC";
$pending_result = mysqli_query($con, $pending_query);

// Get file type filter if set
$file_type_filter = isset($_GET['file_type']) ? $_GET['file_type'] : '';

// Fetch pending shared files with detailed user information
$pending_files_query = "SELECT sf.*, 
                        u.username AS owner_username,
                        u.points AS owner_points,
                        u.subscription AS owner_subscription,
                        u.created_at AS user_registered,
                        (SELECT COUNT(*) FROM shared_files WHERE original_owner_id = sf.original_owner_id) as total_user_files,
                        (SELECT COUNT(*) FROM shared_files WHERE original_owner_id = sf.original_owner_id AND (review_status = 'approved' OR is_available = 1)) as approved_user_files
                        FROM shared_files sf
                        LEFT JOIN login u ON u.username = sf.current_owner_id
                        WHERE (sf.review_status = 'pending' OR (sf.is_available = 0 AND sf.review_status IS NULL))";

// Add file type filter if selected
if (!empty($file_type_filter)) {
    $pending_files_query .= " AND sf.file_type LIKE ?";
}

$pending_files_query .= " ORDER BY sf.created_at DESC";

// Prepare and execute the query with potential file type filter
if (!empty($file_type_filter)) {
    $stmt = mysqli_prepare($con, $pending_files_query);
    $filter_pattern = "%{$file_type_filter}%";
    mysqli_stmt_bind_param($stmt, "s", $filter_pattern);
    mysqli_stmt_execute($stmt);
    $pending_files_result = mysqli_stmt_get_result($stmt);
} else {
    $pending_files_result = mysqli_query($con, $pending_files_query);
}
$pending_files_result = mysqli_query($con, $pending_files_query);

// Fetch statistics
$stats_query = "SELECT 
                    (SELECT COUNT(*) FROM comments WHERE is_approved = 0) as pending_count,
                    (SELECT COUNT(*) FROM comments WHERE is_approved = 1) as approved_count,
                    (SELECT COUNT(*) FROM comments) as total_count,
                    (SELECT COUNT(*) FROM shared_files WHERE review_status = 'pending' OR (is_available = 0 AND review_status IS NULL)) as pending_files_count";
$stats_result = mysqli_query($con, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Fetch recent approved comments for monitoring
$recent_query = "SELECT c.*, u.username,
                 (SELECT COUNT(*) FROM comment_ratings WHERE comment_id = c.id AND rating = 1) as likes,
                 (SELECT COUNT(*) FROM comment_ratings WHERE comment_id = c.id AND rating = -1) as dislikes
                 FROM comments c 
                 LEFT JOIN login u ON CAST(c.user_id AS CHAR CHARACTER SET utf8) = CAST(u.username AS CHAR CHARACTER SET utf8)
                 WHERE is_approved = 1 
                 ORDER BY created_at DESC 
                 LIMIT 5";
$recent_result = mysqli_query($con, $recent_query);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>إدارة التعليقات</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* CSS Variables for consistent theming */
        :root {
            --primary-green: #00ff00;
            --primary-green-glow: #00ff0033;
            --dark-green: #006400;
            --background-black: #050505;
            --card-black: #0c0c0c;
            --text-gray: #888888;
            --text-light: #cccccc;
            --grid-black: #080808;
            --hover-green: #00800022;
            --gradient-dark: linear-gradient(145deg, #0a0a0a, #151515);
            --gradient-glow: linear-gradient(145deg, rgba(0, 255, 0, 0.05), transparent);
            --border-glow: rgba(0, 255, 0, 0.15);
            --border-color: rgba(0, 255, 0, 0.2);
        }

        /* Reset default styles */
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

        /* Text formatting utility */
        .format-text {
            white-space: pre-line;
            line-height: 1.6;
            color: var(--text-light);
        }

        /* Container fade in animation */
        .container {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        /* Tab content fade animation */
        .tab-content {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .tab-content.active {
            opacity: 1;
        }

        /* Enhanced Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--background-black);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, var(--border-glow), var(--primary-green));
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(45deg, var(--primary-green), var(--dark-green));
            box-shadow: 0 0 10px var(--primary-green-glow);
        }

        ::-webkit-scrollbar-corner {
            background: var(--background-black);
        }
        
        /* Main Container - Desktop Layout */
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            overflow-x: hidden;
        }
        
        /* تنسيق فلتر أنواع الملفات */
        .file-type-filter {
            margin-top: 20px;
            text-align: center;
        }
        
        .filter-form {
            display: inline-block;
        }
        
        .file-type-select {
            padding: 8px 15px;
            border-radius: 8px;
            background: var(--card-black);
            color: var(--primary-green);
            border: 1px solid var(--border-glow);
            font-size: 14px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-type-select:hover {
            border-color: var(--primary-green);
            box-shadow: 0 0 10px var(--primary-green-glow);
        }
        
        .file-type-select option {
            background: var(--background-black);
            color: var(--primary-green);
            padding: 5px;
        }
        
        /* تنسيق معلومات نوع الملف */
        .file-type-info {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            background: var(--gradient-dark);
            border: 1px solid var(--border-glow);
            border-radius: 4px;
            font-size: 12px;
            color: var(--text-light);
        }
        
        .file-type-icon {
            color: var(--primary-green);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
        }
        
        /* Admin Header with Dark Theme */
        .admin-header {
            text-align: center;
            margin-bottom: 25px;
            padding: 25px;
            background: var(--gradient-dark);
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 255, 0, 0.1);
            border: 1px solid var(--border-glow);
            position: relative;
            overflow: hidden;
            transform: translateY(0);
            transition: all 0.3s ease;
        }
        
        .admin-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-green), var(--dark-green));
        }
        
        .admin-header:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 255, 0, 0.15);
        }
        
        .admin-header h1 {
            color: var(--primary-green);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        /* Stats Grid with Dark Theme */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        /* Stat Cards with Dark Theme */
        .stat-card {
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            background: var(--gradient-dark);
            border: 1px solid var(--border-glow);
            box-shadow: 0 10px 20px rgba(0, 255, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient-glow);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 255, 0, 0.2);
            border-color: var(--primary-green);
        }
        
        .stat-pending {
            border-color: #ffa500;
        }
        
        .stat-pending .stat-number {
            color: #ffa500;
        }
        
        .stat-approved {
            border-color: var(--primary-green);
        }
        
        .stat-approved .stat-number {
            color: var(--primary-green);
        }
        
        .stat-total {
            border-color: #6a5acd;
        }
        
        .stat-total .stat-number {
            color: #6a5acd;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .stat-card div:last-child {
            color: var(--text-gray);
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        /* Navigation with Dark Theme */
        .navigation {
            margin-bottom: 30px;
            text-align: center;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .navigation {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        /* Navigation Buttons with Dark Theme */
        .nav-btn {
            padding: 12px 25px;
            margin: 0;
            width: auto;
            background: var(--gradient-dark);
            border: 1px solid var(--border-glow);
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            color: var(--primary-green);
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .nav-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient-glow);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .nav-btn:hover::before {
            opacity: 1;
        }
        
        .nav-btn.primary:hover {
            background: var(--primary-green);
            color: var(--background-black);
            border-color: var(--primary-green);
            transform: translateY(-2px);
        }
        
        .nav-btn.success:hover {
            background: var(--dark-green);
            color: white;
            border-color: var(--dark-green);
            transform: translateY(-2px);
        }
        
        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 255, 0, 0.2);
        }
        
        /* Comments and Files Sections with Dark Theme */
        .comments-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.4rem;
            margin-bottom: 25px;
            padding: 20px;
            background: var(--gradient-dark);
            border: 1px solid var(--border-glow);
            border-left: 4px solid var(--primary-green);
            border-radius: 10px;
            color: var(--primary-green);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Comment/File Items with Dark Theme */
        .comment-item {
            padding: 20px;
            margin-bottom: 20px;
            background: var(--gradient-dark);
            border: 1px solid var(--border-glow);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
        }
        
        @media (max-width: 768px) {
            .comment-item {
                padding: 16px;
                margin-bottom: 16px;
            }
        }
        
        .comment-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 255, 0, 0.15);
            border-color: var(--primary-green);
        }
        
        .pending-comment {
            border-right: 4px solid #ffa500;
            background: var(--card-black);
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-glow);
        }
        
        .username {
            font-weight: 600;
            color: var(--primary-green);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .date {
            color: var(--text-gray);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .comment-content {
            margin-bottom: 15px;
            line-height: 1.6;
            color: var(--text-light);
        }
        
        .attachment {
            margin-top: 15px;
            padding: 15px;
            background: var(--card-black);
            border: 1px solid var(--border-glow);
            border-radius: 8px;
            color: var(--text-light);
        }
        
        .attachment a {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
        }
        
        /* New Small Action Buttons */
        .btn-small {
            padding: 6px 8px;
            border: 1px solid var(--border-glow);
            background: var(--card-black);
            color: var(--text-light);
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 30px;
            height: 30px;
        }
        
        .btn-small:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 255, 0, 0.2);
        }
        
        .btn-preview {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-color: #3498db;
            color: white;
        }
        
        .btn-preview:hover {
            background: linear-gradient(135deg, #2980b9, #1f4e79);
            border-color: #2980b9;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
        }
        
        .btn-toggle {
            background: var(--card-black);
            border-color: var(--border-glow);
            color: var(--text-light);
        }
        
        .btn-toggle:hover {
            background: var(--primary-green);
            border-color: var(--primary-green);
            color: var(--background-black);
        }
        
        /* File Type Badges */
        .file-type-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }
        
        /* Collapsible Details Animation */
        .collapsible-details {
            overflow: hidden;
        }
        
        /* Stat Items with Enhanced Styling */
        .stat-item {
            transition: all 0.2s ease;
        }
        
        .stat-item:hover {
            transform: scale(1.02);
        }
        
        /* Detail Items */
        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .detail-item:hover {
            transform: translateX(2px);
            box-shadow: 0 2px 8px rgba(46, 204, 113, 0.2);
        }
        
        .attachment a:hover {
            color: var(--dark-green);
            transform: translateX(3px);
        }
        
        .comment-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        /* Button Styles with Dark Theme */
        .btn {
            padding: 10px 18px;
            background: var(--gradient-dark);
            border: 1px solid var(--border-glow);
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-green);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient-glow);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .btn-success {
            border-color: var(--primary-green);
        }
        
        .btn-success:hover {
            background: var(--primary-green);
            color: var(--background-black);
            border-color: var(--primary-green);
        }
        
        .btn-danger {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        .btn-danger:hover {
            background: #ff4444;
            color: white;
            border-color: #ff4444;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 255, 0, 0.3);
        }
        
        .btn:hover::before {
            opacity: 1;
        }
        
        .rating-info {
            margin-top: 15px;
            padding: 15px;
            background: var(--card-black);
            border: 1px solid var(--border-glow);
            border-radius: 10px;
            font-size: 14px;
            color: var(--text-light);
            box-shadow: inset 0 2px 5px rgba(0, 255, 0, 0.05);
        }
        
        .no-comments {
            text-align: center;
            padding: 50px;
            color: var(--text-gray);
            font-style: italic;
            background: var(--gradient-dark);
            border: 1px solid var(--border-glow);
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.05);
            margin: 20px 0;
        }
        
        /* Alert Messages with Dark Theme */
        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            background: var(--gradient-dark);
            border: 1px solid var(--border-glow);
            color: var(--primary-green);
            animation: slideDown 0.5s ease-out;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .alert-success {
            border-color: var(--primary-green);
            color: var(--primary-green);
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.2);
        }
        
        .alert-danger {
            border-color: #ff4444;
            color: #ff4444;
            box-shadow: 0 5px 15px rgba(255, 68, 68, 0.2);
        }
        
        /* Tabs with Dark Theme */
        .tabs {
            display: flex;
            margin-bottom: 25px;
            border-bottom: none;
            background: var(--gradient-dark);
            border: 1px solid var(--border-glow);
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.05);
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
        }
        
        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
                padding: 5px;
            }
        }
        
        .tab {
            padding: 12px 20px;
            flex: 1;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s ease;
            margin: 0 5px;
            color: var(--text-gray);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 150px;
        }
        
        .tab:hover {
            background: var(--hover-green);
            color: var(--primary-green);
        }
        
        .tab.active {
            background: var(--primary-green);
            color: var(--background-black);
            font-weight: 600;
            box-shadow: 0 5px 15px var(--primary-green-glow);
        }
        
        .tab::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient-glow);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .tab:hover::before {
            opacity: 1;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1><i class="fas fa-shield-alt"></i> لوحة التحكم الإدارية</h1>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-success">
                ✅ <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card stat-pending">
                <div class="stat-number"><?php echo $stats['pending_count']; ?></div>
                <div><i class="fas fa-clock"></i> في انتظار الموافقة</div>
            </div>
            <div class="stat-card stat-approved">
                <div class="stat-number"><?php echo $stats['approved_count']; ?></div>
                <div><i class="fas fa-check-circle"></i> تم الموافقة عليها</div>
            </div>
            <div class="stat-card stat-total">
                <div class="stat-number"><?php echo $stats['total_count']; ?></div>
                <div><i class="fas fa-chart-bar"></i> إجمالي التعليقات</div>
            </div>
            <div class="stat-card stat-pending">
                <div class="stat-number"><?php echo isset($stats['pending_files_count']) ? $stats['pending_files_count'] : 0; ?></div>
                <div><i class="fas fa-file-upload"></i> ملفات معلقة</div>
            </div>
        </div>
        
        <div class="navigation">
            <a href="index.php" class="nav-btn primary"><i class="fas fa-home"></i> العودة للرئيسية</a>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('pending')">
                <i class="fas fa-clock"></i> التعليقات المعلقة (<?php echo $stats['pending_count']; ?>)
            </div>
            <div class="tab" onclick="switchTab('recent')">
                <i class="fas fa-list"></i> التعليقات المنشورة مؤخراً
            </div>
            <div class="tab" onclick="switchTab('files')">
                <i class="fas fa-file-upload"></i> الملفات المعلقة (<?php echo isset($stats['pending_files_count']) ? $stats['pending_files_count'] : 0; ?>)
            </div>
        </div>
        
        <!-- Pending Comments Tab -->
        <div id="pending-tab" class="tab-content active">
            <div class="comments-section">
                <div class="section-title">
                    <i class="fas fa-clock"></i> التعليقات في انتظار الموافقة
                </div>
                
                <?php if (mysqli_num_rows($pending_result) == 0): ?>
                    <div class="no-comments">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--primary-green); margin-bottom: 15px;"></i>
                        <h4 style="color: var(--primary-green);">لا توجد تعليقات في انتظار الموافقة</h4>
                        <p>جميع التعليقات تمت مراجعتها!</p>
                    </div>
                <?php else: ?>
                    <?php while ($comment = mysqli_fetch_assoc($pending_result)): ?>
                        <div class="comment-item pending-comment" id="comment-<?php echo $comment['id']; ?>">
                            <!-- Comment Header with Toggle Button -->
                            <div class="comment-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                    <span class="username"><i class="fas fa-user"></i> <?php echo htmlspecialchars($comment['username']); ?></span>
                                    <span class="date"><i class="fas fa-clock"></i> <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></span>
                                    <span style="background: #ffa500; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                        تعليق
                                    </span>
                                </div>
                                <div class="quick-actions" style="display: flex; gap: 8px; align-items: center;">
                                    <!-- Toggle Details Button -->
                                    <button onclick="toggleCommentDetails(<?php echo $comment['id']; ?>)" 
                                            class="btn-small btn-toggle" id="toggle-comment-btn-<?php echo $comment['id']; ?>" title="عرض/إخفاء التفاصيل">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Comment Basic Info (Always Visible) -->
                            <div class="comment-content">
                                <div class="comment-basic-info" style="margin-bottom: 15px;">
                                    <p class="format-text" style="margin-bottom: 8px;"><?php echo htmlspecialchars($comment['content']); ?></p>
                                    
                                    <?php if ($comment['file_path']): ?>
                                        <div class="attachment" style="margin-top: 10px;">
                                            <a href="<?php echo htmlspecialchars($comment['file_path']); ?>" target="_blank">
                                                <i class="fas fa-paperclip"></i> عرض المرفق
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Collapsible Comment Details Section -->
                                <div class="collapsible-details" id="comment-details-<?php echo $comment['id']; ?>" style="display: none; opacity: 0; max-height: 0; overflow: hidden; transition: all 0.3s ease;">
                                    
                                    <!-- User Details Section -->
                                    <div class="user-details" style="margin: 15px 0; padding: 15px; background: var(--card-black); border: 1px solid var(--border-glow); border-radius: 8px;">
                                        <h5 style="color: var(--primary-green); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-user-circle"></i> معلومات وإحصائيات المستخدم
                                        </h5>
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 13px;">
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(46, 204, 113, 0.05)); border-radius: 8px;">
                                                <div style="color: var(--primary-green); font-size: 18px; font-weight: bold;"><?php echo intval($comment['user_points'] ?? 0); ?></div>
                                                <div style="color: var(--text-light); font-size: 11px;">نقاط المستخدم</div>
                                            </div>
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.05)); border-radius: 8px;">
                                                <div style="color: #3498db; font-size: 18px; font-weight: bold;"><?php echo htmlspecialchars($comment['user_subscription'] ?? 'عادي'); ?></div>
                                                <div style="color: var(--text-light); font-size: 11px;">نوع الاشتراك</div>
                                            </div>
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(155, 89, 182, 0.1), rgba(155, 89, 182, 0.05)); border-radius: 8px;">
                                                <div style="color: #9b59b6; font-size: 18px; font-weight: bold;"><?php echo intval($comment['total_user_comments'] ?? 0); ?></div>
                                                <div style="color: var(--text-light); font-size: 11px;">إجمالي التعليقات</div>
                                            </div>
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(46, 204, 113, 0.05)); border-radius: 8px;">
                                                <div style="color: var(--primary-green); font-size: 18px; font-weight: bold;"><?php echo intval($comment['approved_user_comments'] ?? 0); ?></div>
                                                <div style="color: var(--text-light); font-size: 11px;">المعتمدة</div>
                                            </div>
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(230, 126, 34, 0.1), rgba(230, 126, 34, 0.05)); border-radius: 8px;">
                                                <div style="color: #e67e22; font-size: 18px; font-weight: bold;">
                                                    <?php 
                                                    $total = intval($comment['total_user_comments'] ?? 0);
                                                    $approved = intval($comment['approved_user_comments'] ?? 0);
                                                    $rate = $total > 0 ? round(($approved / $total) * 100) : 0;
                                                    echo $rate . '%';
                                                    ?>
                                                </div>
                                                <div style="color: var(--text-light); font-size: 11px;">معدل القبول</div>
                                            </div>
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(52, 73, 94, 0.1), rgba(52, 73, 94, 0.05)); border-radius: 8px;">
                                                <div style="color: #34495e; font-size: 18px; font-weight: bold;"><?php echo $comment['user_registered'] ? date('Y-m-d', strtotime($comment['user_registered'])) : 'غير محدد'; ?></div>
                                                <div style="color: var(--text-light); font-size: 11px;">تاريخ التسجيل</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Comment Analysis Section -->
                                    <div class="comment-analysis" style="margin: 15px 0; padding: 15px; background: var(--card-black); border: 1px solid var(--border-glow); border-radius: 8px;">
                                        <h5 style="color: var(--primary-green); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-chart-line"></i> تحليل سريع للتعليق
                                        </h5>
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px; font-size: 12px;">
                                            <div style="padding: 8px; border-right: 3px solid var(--primary-green); background: rgba(46, 204, 113, 0.05);">
                                                <strong style="color: var(--primary-green);">تقييم المخاطر:</strong>
                                                <span style="color: var(--text-light); margin-right: 8px;">
                                                    <?php 
                                                    $risk_score = 'منخفض ✅';
                                                    if ($rate < 50) $risk_score = 'عالي ⚠️';
                                                    elseif ($rate < 80) $risk_score = 'متوسط ⚡';
                                                    echo $risk_score;
                                                    ?>
                                                </span>
                                            </div>
                                            <div style="padding: 8px; border-right: 3px solid #3498db; background: rgba(52, 152, 219, 0.05);">
                                                <strong style="color: #3498db;">حالة المستخدم:</strong>
                                                <span style="color: var(--text-light); margin-right: 8px;">
                                                    <?php 
                                                    $user_status = 'عضو جديد';
                                                    if ($total > 10) $user_status = 'عضو نشط';
                                                    if ($total > 50) $user_status = 'عضو متقدم';
                                                    echo $user_status;
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="comment-actions">
                                <form method="post" class="inline-form" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <button type="submit" name="action" value="approve" class="btn btn-success" 
                                            onclick="return confirm('هل أنت متأكد من الموافقة على هذا التعليق؟')">
                                        <i class="fas fa-check"></i> موافقة
                                    </button>
                                    
                                    <!-- Rejection Section -->
                                    <div style="display: flex; flex-direction: column; gap: 8px; min-width: 250px;">
                                        <label for="comment_rejection_reason_<?php echo $comment['id']; ?>" style="color: var(--primary-green); font-size: 12px; font-weight: 600;">
                                            <i class="fas fa-comment-slash"></i> ملاحظة الرفض:
                                        </label>
                                        <div style="display: flex; gap: 8px;">
                                            <textarea name="comment_rejection_reason" id="comment_rejection_reason_<?php echo $comment['id']; ?>" 
                                                    placeholder="اكتب سبب رفض التعليق..."
                                                    style="flex: 1; min-height: 40px; max-height: 80px; padding: 8px; background: var(--card-black); border: 1px solid var(--border-glow); border-radius: 6px; color: var(--text-light); font-size: 12px; resize: vertical;"
                                                    rows="2"></textarea>
                                            <button type="submit" name="action" value="reject" class="btn btn-danger" 
                                                    style="height: fit-content; padding: 8px 12px;"
                                                    onclick="return confirm('هل أنت متأكد من رفض وحذف هذا التعليق؟\n\nسبب الرفض: ' + document.getElementById('comment_rejection_reason_<?php echo $comment['id']; ?>').value)">
                                                <i class="fas fa-times"></i> رفض
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Files Tab -->
        <div id="files-tab" class="tab-content">
            <div class="comments-section">
                <div class="section-title">
                    <i class="fas fa-file-upload"></i> الملفات في انتظار الموافقة
                </div>

                <?php if (!isset($pending_files_result) || mysqli_num_rows($pending_files_result) == 0): ?>
                    <div class="no-comments">
                        <h4>لا توجد ملفات في انتظار الموافقة</h4>
                    </div>
                <?php else: ?>
                    <?php while ($file = mysqli_fetch_assoc($pending_files_result)): ?>
                        <div class="comment-item pending-comment" id="file-<?php echo $file['id']; ?>">
                            <!-- File Header with Quick Actions -->
                            <div class="comment-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                    <span class="username"><i class="fas fa-user"></i> <?php echo htmlspecialchars($file['owner_username'] ?? 'مستخدم'); ?></span>
                                    <span class="date"><i class="fas fa-clock"></i> <?php echo date('Y-m-d H:i', strtotime($file['created_at'])); ?></span>
                                    <span class="file-type-badge" style="background: var(--primary-green); color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                        <?php echo strtoupper($file['file_type'] ?? 'FILE'); ?>
                                    </span>
                                </div>
                                <div class="quick-actions" style="display: flex; gap: 8px; align-items: center;">
                                    <!-- File Preview Button -->
                                    <?php if (!empty($file['file_path'])): ?>
                                        <button onclick="previewFile('<?php echo addslashes($file['file_path']); ?>', '<?php echo addslashes($file['file_type'] ?? ''); ?>', '<?php echo addslashes($file['file_name'] ?? 'ملف'); ?>')" 
                                                class="btn-small btn-preview" title="معاينة الملف">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="download.php?file_id=<?php echo $file['id']; ?>" class="btn-small" title="تحميل الملف (أدمن)" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    <?php endif; ?>
                                    <!-- Toggle Details Button -->
                                    <button onclick="toggleFileDetails(<?php echo $file['id']; ?>)" 
                                            class="btn-small btn-toggle" id="toggle-btn-<?php echo $file['id']; ?>" title="عرض/إخفاء التفاصيل">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- File Basic Info (Always Visible) -->
                            <div class="comment-content">
                                <div class="file-basic-info" style="margin-bottom: 15px;">
                                    <p class="format-text" style="margin-bottom: 8px;">
                                        <?php echo htmlspecialchars($file['description'] ?? 'لا يوجد وصف'); ?>
                                    </p>
                                    <div style="display: flex; gap: 20px; flex-wrap: wrap; font-size: 13px; color: var(--text-light); margin-top: 8px;">
                                        <span><strong>الاسم:</strong> <?php echo htmlspecialchars($file['file_name'] ?? 'غير محدد'); ?></span>
                                        <span><strong>السعر:</strong> <?php echo (int)$file['price']; ?> نقطة</span>
                                        <span><strong>الحجم:</strong> <?php echo $file['file_size'] ? formatFileSize($file['file_size']) : 'غير محدد'; ?></span>
                                    </div>
                                </div>
                                
                                <!-- Collapsible Details Section -->
                                <div class="collapsible-details" id="details-<?php echo $file['id']; ?>" style="display: none; opacity: 0; max-height: 0; overflow: hidden; transition: all 0.3s ease;">
                                    
                                    <!-- File Details Section -->
                                    <div class="file-details" style="margin: 15px 0; padding: 15px; background: var(--card-black); border: 1px solid var(--border-glow); border-radius: 8px;">
                                        <h5 style="color: var(--primary-green); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-file-alt"></i> تفاصيل تقنية متقدمة
                                        </h5>
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; font-size: 13px;">
                                            <div class="detail-item" style="padding: 8px; background: rgba(46, 204, 113, 0.1); border-radius: 6px;">
                                                <strong style="color: var(--primary-green);">معرف الملف:</strong> 
                                                <span style="color: var(--text-light); font-family: monospace;">#<?php echo $file['id']; ?></span>
                                            </div>
                                            <div class="detail-item" style="padding: 8px; background: rgba(46, 204, 113, 0.1); border-radius: 6px;">
                                                <strong style="color: var(--primary-green);">نوع الملف:</strong> 
                                                <span style="color: var(--text-light);"><?php echo htmlspecialchars($file['file_type'] ?? 'غير محدد'); ?></span>
                                            </div>
                                            <div class="detail-item" style="padding: 8px; background: rgba(46, 204, 113, 0.1); border-radius: 6px;">
                                                <strong style="color: var(--primary-green);">تاريخ الرفع:</strong> 
                                                <span style="color: var(--text-light);"><?php echo date('Y-m-d H:i:s', strtotime($file['created_at'])); ?></span>
                                            </div>
                                            <div class="detail-item" style="padding: 8px; background: rgba(46, 204, 113, 0.1); border-radius: 6px;">
                                                <strong style="color: var(--primary-green);">حالة الملف:</strong> 
                                                <span style="color: #ffa500; font-weight: bold;">في انتظار المراجعة ⚠️</span>
                                            </div>
                                            <?php if (!empty($file['file_path'])): ?>
                                            <div class="detail-item" style="padding: 8px; background: rgba(46, 204, 113, 0.1); border-radius: 6px; grid-column: 1 / -1;">
                                                <strong style="color: var(--primary-green);">مسار الملف:</strong> 
                                                <div style="margin-top: 5px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                                    <code style="background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 4px; color: var(--text-light); font-size: 11px; word-break: break-all; flex: 1;"><?php echo htmlspecialchars($file['file_path']); ?></code>
                                                    <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" style="color: var(--primary-green); text-decoration: none; white-space: nowrap;">
                                                        <i class="fas fa-external-link-alt"></i> فتح مباشر
                                                    </a>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- User Details Section -->
                                    <div class="user-details" style="margin: 15px 0; padding: 15px; background: var(--card-black); border: 1px solid var(--border-glow); border-radius: 8px;">
                                        <h5 style="color: var(--primary-green); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-user-circle"></i> معلومات صاحب الملف وإحصائياته
                                        </h5>
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 13px;">
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(46, 204, 113, 0.05)); border-radius: 8px;">
                                                <div style="color: var(--primary-green); font-size: 18px; font-weight: bold;"><?php echo intval($file['owner_points'] ?? 0); ?></div>
                                                <div style="color: var(--text-light); font-size: 11px;">النقاط الحالية</div>
                                            </div>
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.05)); border-radius: 8px;">
                                                <div style="color: #3498db; font-size: 18px; font-weight: bold;"><?php echo htmlspecialchars($file['owner_subscription'] ?? 'عادي'); ?></div>
                                                <div style="color: var(--text-light); font-size: 11px;">نوع الاشتراك</div>
                                            </div>
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(155, 89, 182, 0.1), rgba(155, 89, 182, 0.05)); border-radius: 8px;">
                                                <div style="color: #9b59b6; font-size: 18px; font-weight: bold;"><?php echo intval($file['total_user_files'] ?? 0); ?></div>
                                                <div style="color: var(--text-light); font-size: 11px;">إجمالي الملفات</div>
                                            </div>
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(46, 204, 113, 0.05)); border-radius: 8px;">
                                                <div style="color: var(--primary-green); font-size: 18px; font-weight: bold;"><?php echo intval($file['approved_user_files'] ?? 0); ?></div>
                                                <div style="color: var(--text-light); font-size: 11px;">المعتمدة</div>
                                            </div>
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(230, 126, 34, 0.1), rgba(230, 126, 34, 0.05)); border-radius: 8px;">
                                                <div style="color: #e67e22; font-size: 18px; font-weight: bold;">
                                                    <?php 
                                                    $total_files = intval($file['total_user_files'] ?? 0);
                                                    $approved_files = intval($file['approved_user_files'] ?? 0);
                                                    $file_rate = $total_files > 0 ? round(($approved_files / $total_files) * 100) : 0;
                                                    echo $file_rate . '%';
                                                    ?>
                                                </div>
                                                <div style="color: var(--text-light); font-size: 11px;">معدل القبول</div>
                                            </div>
                                            <div class="stat-item" style="text-align: center; padding: 10px; background: linear-gradient(135deg, rgba(52, 73, 94, 0.1), rgba(52, 73, 94, 0.05)); border-radius: 8px;">
                                                <div style="color: #34495e; font-size: 18px; font-weight: bold;"><?php echo $file['user_registered'] ? date('Y-m-d', strtotime($file['user_registered'])) : 'غير محدد'; ?></div>
                                                <div style="color: var(--text-light); font-size: 11px;">تاريخ التسجيل</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- File Analysis Section -->
                                    <div class="file-analysis" style="margin: 15px 0; padding: 15px; background: var(--card-black); border: 1px solid var(--border-glow); border-radius: 8px;">
                                        <h5 style="color: var(--primary-green); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-chart-line"></i> تحليل سريع للملف
                                        </h5>
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px; font-size: 12px;">
                                            <div style="padding: 8px; border-right: 3px solid var(--primary-green); background: rgba(46, 204, 113, 0.05);">
                                                <strong style="color: var(--primary-green);">تقييم المخاطر:</strong>
                                                <span style="color: var(--text-light); margin-right: 8px;">
                                                    <?php 
                                                    $risk_score = 'منخفض ✅';
                                                    if ($file_rate < 50) $risk_score = 'عالي ⚠️';
                                                    elseif ($file_rate < 80) $risk_score = 'متوسط ⚡';
                                                    echo $risk_score;
                                                    ?>
                                                </span>
                                            </div>
                                            <div style="padding: 8px; border-right: 3px solid #3498db; background: rgba(52, 152, 219, 0.05);">
                                                <strong style="color: #3498db;">حالة المستخدم:</strong>
                                                <span style="color: var(--text-light); margin-right: 8px;">
                                                    <?php 
                                                    $user_status = 'عضو جديد';
                                                    if ($total_files > 10) $user_status = 'عضو نشط';
                                                    if ($total_files > 50) $user_status = 'عضو متقدم';
                                                    echo $user_status;
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <div class="comment-actions">
                                <form method="post" class="inline-form" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                                    <input type="hidden" name="file_id" value="<?php echo (int)$file['id']; ?>">
                                    <button type="submit" name="file_action" value="approve_file" class="btn btn-success" 
                                            onclick="return confirm('قبول هذا الملف للنشر؟')">
                                        <i class="fas fa-check"></i> قبول
                                    </button>
                                    
                                    <!-- File Rejection Section -->
                                    <div style="display: flex; flex-direction: column; gap: 8px; min-width: 250px;">
                                        <label for="rejection_reason_<?php echo $file['id']; ?>" style="color: var(--primary-green); font-size: 12px; font-weight: 600;">
                                            <i class="fas fa-file-excel"></i> ملاحظة الرفض:
                                        </label>
                                        <div style="display: flex; gap: 8px;">
                                            <textarea name="rejection_reason" id="rejection_reason_<?php echo $file['id']; ?>" 
                                                    placeholder="اكتب سبب رفض الملف (مطلوب)..."
                                                    style="flex: 1; min-height: 40px; max-height: 80px; padding: 8px; background: var(--card-black); border: 1px solid var(--border-glow); border-radius: 6px; color: var(--text-light); font-size: 12px; resize: vertical;"
                                                    rows="2" required></textarea>
                                            <button type="submit" name="file_action" value="reject_file" class="btn btn-danger" 
                                                    style="height: fit-content; padding: 8px 12px;"
                                                    onclick="var reason = document.getElementById('rejection_reason_<?php echo $file['id']; ?>').value.trim(); if (!reason) { alert('يرجى كتابة سبب الرفض قبل المتابعة'); return false; } return confirm('هل أنت متأكد من رفض هذا الملف؟\n
سيتم إرسال إشعار للمستخدم ويمكنه إعادة الرفع\n\nسبب الرفض: ' + reason)">
                                                <i class="fas fa-times"></i> رفض
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Comments Tab -->
        <div id="recent-tab" class="tab-content">
            <div class="comments-section">
                <div class="section-title">
                    <i class="fas fa-list"></i> آخر التعليقات المنشورة
                </div>
                
                <?php if (mysqli_num_rows($recent_result) == 0): ?>
                    <div class="no-comments">
                        <h4>لا توجد تعليقات منشورة حتى الآن</h4>
                    </div>
                <?php else: ?>
                    <?php while ($comment = mysqli_fetch_assoc($recent_result)): ?>
                        <div class="comment-item">
                            <div class="comment-header">
                                <span class="username"><i class="fas fa-user"></i> <?php echo htmlspecialchars($comment['username']); ?></span>
                                <span class="date"><i class="fas fa-clock"></i> <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <div class="comment-content">
                                <p class="format-text"><?php echo htmlspecialchars($comment['content']); ?></p>
                                <?php if ($comment['file_path']): ?>
                                    <div class="attachment">
                                        <a href="<?php echo htmlspecialchars($comment['file_path']); ?>" target="_blank">
                                            <i class="fas fa-paperclip"></i> عرض المرفق
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="rating-info">
                                <span><i class="fas fa-chart-bar"></i> الإحصائيات: </span>
                                <span style="color: var(--primary-green);"><i class="fas fa-thumbs-up"></i> <?php echo $comment['likes']; ?> إعجاب</span>
                                <span style="margin: 0 10px; color: var(--text-gray);">|</span>
                                <span style="color: #ff6464;"><i class="fas fa-thumbs-down"></i> <?php echo $comment['dislikes']; ?> عدم إعجاب</span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents with fade effect
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.opacity = '0';
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs with smooth transition
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
                tab.style.transform = 'translateY(0)';
            });
            
            // Show selected tab content with fade in effect
            const selectedContent = document.getElementById(tabName + '-tab');
            selectedContent.classList.add('active');
            setTimeout(() => {
                selectedContent.style.opacity = '1';
            }, 50);
            
            // Add active class to clicked tab with bounce effect
            event.target.classList.add('active');
            event.target.style.transform = 'translateY(-3px)';
            setTimeout(() => {
                event.target.style.transform = 'translateY(0)';
            }, 200);
        }
        
        // Add smooth fade-in effect on page load
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelector('.container').style.opacity = '1';
        });
        
        // Auto refresh page every 30 seconds to check for new comments
        let autoRefresh = setInterval(() => {
            if (<?php echo $stats['pending_count']; ?> > 0) {
                document.querySelector('.container').style.opacity = '0';
                setTimeout(() => {
                    location.reload();
                }, 300);
            }
        }, 30000);
        
        // Add confirmation dialogs for actions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = e.submitter.value;
                let message = '';
                
                if (action === 'approve') {
                    message = 'هل أنت متأكد من الموافقة على هذا التعليق؟';
                } else if (action === 'reject') {
                    message = 'هل أنت متأكد من رفض وحذف هذا التعليق نهائياً؟';
                }
                
                if (message && !confirm(message)) {
                    e.preventDefault();
                }
            });
        });
        
        // Toggle file details functionality
        function toggleFileDetails(fileId) {
            const detailsElement = document.getElementById(`details-${fileId}`);
            const toggleBtn = document.getElementById(`toggle-btn-${fileId}`);
            const toggleIcon = toggleBtn.querySelector('i');
            
            if (detailsElement.style.display === 'none' || detailsElement.style.display === '') {
                // Show details with animation
                detailsElement.style.display = 'block';
                setTimeout(() => {
                    detailsElement.style.opacity = '1';
                    detailsElement.style.maxHeight = '2000px';
                }, 10);
                
                // Update button
                toggleIcon.className = 'fas fa-chevron-up';
                toggleBtn.title = 'إخفاء التفاصيل';
                toggleBtn.style.background = 'var(--primary-green)';
                toggleBtn.style.color = 'white';
            } else {
                // Hide details with animation
                detailsElement.style.opacity = '0';
                detailsElement.style.maxHeight = '0';
                setTimeout(() => {
                    detailsElement.style.display = 'none';
                }, 300);
                
                // Update button
                toggleIcon.className = 'fas fa-chevron-down';
                toggleBtn.title = 'عرض التفاصيل';
                toggleBtn.style.background = '';
                toggleBtn.style.color = '';
            }
        }
        
        // File preview functionality
        function previewFile(filePath, fileType, fileName) {
            // Create modal backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'preview-backdrop';
            backdrop.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;
            
            // Create modal content
            const modal = document.createElement('div');
            modal.style.cssText = `
                background: var(--card-black);
                border: 2px solid var(--border-glow);
                border-radius: 12px;
                padding: 20px;
                max-width: 90vw;
                max-height: 90vh;
                overflow: auto;
                position: relative;
                box-shadow: 0 20px 60px rgba(46, 204, 113, 0.3);
            `;
            
            // Create header
            const header = document.createElement('div');
            header.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid var(--border-glow); padding-bottom: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-eye" style="color: var(--primary-green); font-size: 18px;"></i>
                        <h3 style="margin: 0; color: var(--primary-green);">معاينة الملف</h3>
                        <span style="background: var(--primary-green); color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">${fileType.toUpperCase()}</span>
                    </div>
                    <button onclick="this.closest('.preview-backdrop').remove()" style="
                        background: #e74c3c; 
                        color: white; 
                        border: none; 
                        border-radius: 50%; 
                        width: 30px; 
                        height: 30px; 
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: all 0.2s ease;
                    " onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p style="margin: 0; color: var(--text-light); font-size: 14px;"><strong>اسم الملف:</strong> ${fileName}</p>
            `;
            
            // Create content area
            const content = document.createElement('div');
            content.style.cssText = 'margin-top: 15px; text-align: center;';
            
            // Determine file type and create appropriate preview
            const fileExtension = fileType.toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(fileExtension)) {
                // Image preview
                content.innerHTML = `
                    <div style="position: relative; display: inline-block;">
                        <img src="${filePath}" alt="${fileName}" style="
                            max-width: 100%;
                            max-height: 70vh;
                            border-radius: 8px;
                            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                        " onload="this.style.opacity='1'" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div style="display: none; padding: 40px; color: var(--text-light);">
                            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #e67e22; margin-bottom: 10px;"></i>
                            <p>تعذر تحميل الصورة</p>
                        </div>
                    </div>
                `;
            } else if (['mp4', 'webm', 'ogg', 'avi', 'mov'].includes(fileExtension)) {
                // Video preview
                content.innerHTML = `
                    <video controls style="
                        max-width: 100%;
                        max-height: 70vh;
                        border-radius: 8px;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                    ">
                        <source src="${filePath}" type="video/${fileExtension}">
                        متصفحك لا يدعم تشغيل الفيديو
                    </video>
                `;
            } else if (['mp3', 'wav', 'ogg', 'aac'].includes(fileExtension)) {
                // Audio preview
                content.innerHTML = `
                    <div style="padding: 40px; background: linear-gradient(135deg, var(--card-black), rgba(46, 204, 113, 0.1)); border-radius: 8px;">
                        <i class="fas fa-music" style="font-size: 48px; color: var(--primary-green); margin-bottom: 15px;"></i>
                        <p style="color: var(--text-light); margin-bottom: 20px;">ملف صوتي</p>
                        <audio controls style="width: 100%; max-width: 400px;">
                            <source src="${filePath}" type="audio/${fileExtension}">
                            متصفحك لا يدعم تشغيل الصوت
                        </audio>
                    </div>
                `;
            } else if (['pdf'].includes(fileExtension)) {
                // PDF preview
                content.innerHTML = `
                    <div style="width: 100%; height: 70vh; border-radius: 8px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                        <embed src="${filePath}" type="application/pdf" width="100%" height="100%" style="border-radius: 8px;">
                    </div>
                `;
            } else {
                // Generic file preview
                content.innerHTML = `
                    <div style="padding: 60px; background: linear-gradient(135deg, var(--card-black), rgba(46, 204, 113, 0.1)); border-radius: 8px;">
                        <i class="fas fa-file-alt" style="font-size: 64px; color: var(--primary-green); margin-bottom: 20px;"></i>
                        <p style="color: var(--text-light); margin-bottom: 20px; font-size: 16px;">لا يمكن معاينة هذا النوع من الملفات</p>
                        <p style="color: var(--text-gray); font-size: 14px; margin-bottom: 25px;">يمكنك تحميل الملف لعرضه</p>
                        <a href="${filePath}" target="_blank" style="
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            background: var(--primary-green);
                            color: white;
                            padding: 12px 20px;
                            border-radius: 8px;
                            text-decoration: none;
                            font-weight: 600;
                            transition: all 0.2s ease;
                        " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(46, 204, 113, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <i class="fas fa-download"></i> تحميل الملف
                        </a>
                    </div>
                `;
            }
            
            modal.appendChild(header);
            modal.appendChild(content);
            backdrop.appendChild(modal);
            document.body.appendChild(backdrop);
            
            // Animate backdrop
            setTimeout(() => {
                backdrop.style.opacity = '1';
            }, 10);
            
            // Close on backdrop click
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) {
                    backdrop.style.opacity = '0';
                    setTimeout(() => backdrop.remove(), 300);
                }
            });
            
            // Close on ESC key
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    backdrop.style.opacity = '0';
                    setTimeout(() => backdrop.remove(), 300);
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
        }
        
        // Toggle comment details functionality
        function toggleCommentDetails(commentId) {
            const detailsElement = document.getElementById(`comment-details-${commentId}`);
            const toggleBtn = document.getElementById(`toggle-comment-btn-${commentId}`);
            const toggleIcon = toggleBtn.querySelector('i');
            
            if (detailsElement.style.display === 'none' || detailsElement.style.display === '') {
                // Show details with animation
                detailsElement.style.display = 'block';
                setTimeout(() => {
                    detailsElement.style.opacity = '1';
                    detailsElement.style.maxHeight = '2000px';
                }, 10);
                
                // Update button
                toggleIcon.className = 'fas fa-chevron-up';
                toggleBtn.title = 'إخفاء التفاصيل';
                toggleBtn.style.background = 'var(--primary-green)';
                toggleBtn.style.color = 'white';
            } else {
                // Hide details with animation
                detailsElement.style.opacity = '0';
                detailsElement.style.maxHeight = '0';
                setTimeout(() => {
                    detailsElement.style.display = 'none';
                }, 300);
                
                // Update button
                toggleIcon.className = 'fas fa-chevron-down';
                toggleBtn.title = 'عرض التفاصيل';
                toggleBtn.style.background = '';
                toggleBtn.style.color = '';
            }
        }
    </script>
</body>
</html>