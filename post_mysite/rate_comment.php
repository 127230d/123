<?php
session_start();
require_once 'dssssssssb.php';

// Set response headers
header('Content-Type: application/json; charset=utf-8');

// Create ratings table if it doesn't exist
$createTable = "CREATE TABLE IF NOT EXISTS comment_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    rating TINYINT NOT NULL,  -- 1 for like, -1 for dislike
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rating (comment_id, user_id),
    INDEX idx_comment_id (comment_id),
    INDEX idx_user_id (user_id)
)";

mysqli_query($con, $createTable);

// Create admin logs table if it doesn't exist
$createLogsTable = "CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    user_id VARCHAR(255),
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
)";

mysqli_query($con, $createLogsTable);

$response = ['success' => false, 'message' => '', 'likes' => 0, 'dislikes' => 0, 'user_rating' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is logged in
    if (!isset($_SESSION["username"])) {
        $response['message'] = 'يجب تسجيل الدخول أولاً';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Get input data from POST
    $comment_id = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $user_id = $_SESSION["username"];
    
    // Validate input
    if (!$comment_id || !in_array($rating, [1, -1])) {
        $response['message'] = 'بيانات غير صحيحة';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Check if comment exists and is approved
    $comment_check = mysqli_prepare($con, "SELECT id, user_id FROM comments WHERE id = ? AND is_approved = 1");
    mysqli_stmt_bind_param($comment_check, "i", $comment_id);
    mysqli_stmt_execute($comment_check);
    $comment_result = mysqli_stmt_get_result($comment_check);
    $comment_data = mysqli_fetch_assoc($comment_result);
    
    if (!$comment_data) {
        $response['message'] = 'التعليق غير موجود أو غير منشور';
        mysqli_stmt_close($comment_check);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Check if user is trying to rate their own comment
    if ($comment_data['user_id'] === $user_id) {
        $response['message'] = 'لا يمكنك تقييم تعليقك الخاص';
        mysqli_stmt_close($comment_check);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    mysqli_stmt_close($comment_check);
    
    // Start transaction
    mysqli_begin_transaction($con);
    
    try {
        // Check if user already rated this comment
        $check = mysqli_prepare($con, "SELECT id, rating FROM comment_ratings WHERE comment_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($check, "is", $comment_id, $user_id);
        mysqli_stmt_execute($check);
        $check_result = mysqli_stmt_get_result($check);
        $existing_rating = mysqli_fetch_assoc($check_result);
        
        if ($existing_rating) {
            // Check if trying to set same rating
            if ($existing_rating['rating'] == $rating) {
                // Remove the rating (toggle off)
                $stmt = mysqli_prepare($con, "DELETE FROM comment_ratings WHERE comment_id = ? AND user_id = ?");
                mysqli_stmt_bind_param($stmt, "is", $comment_id, $user_id);
                $action = 'removed_rating';
                $response['user_rating'] = 0;
            } else {
                // Update existing rating
                $stmt = mysqli_prepare($con, "UPDATE comment_ratings SET rating = ?, created_at = NOW() WHERE comment_id = ? AND user_id = ?");
                mysqli_stmt_bind_param($stmt, "iis", $rating, $comment_id, $user_id);
                $action = 'updated_rating';
                $response['user_rating'] = $rating;
            }
        } else {
            // Insert new rating
            $stmt = mysqli_prepare($con, "INSERT INTO comment_ratings (comment_id, user_id, rating, created_at) VALUES (?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, "isi", $comment_id, $user_id, $rating);
            $action = 'new_rating';
            $response['user_rating'] = $rating;
        }
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('خطأ في تنفيذ العملية');
        }
        
        mysqli_stmt_close($check);
        mysqli_stmt_close($stmt);
        
        // Get updated counts
        $counts_query = "SELECT 
                            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as likes,
                            SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) as dislikes
                         FROM comment_ratings 
                         WHERE comment_id = ?";
        $counts_stmt = mysqli_prepare($con, $counts_query);
        mysqli_stmt_bind_param($counts_stmt, "i", $comment_id);
        mysqli_stmt_execute($counts_stmt);
        $counts_result = mysqli_stmt_get_result($counts_stmt);
        $counts = mysqli_fetch_assoc($counts_result);
        mysqli_stmt_close($counts_stmt);
        
        // Log the action
        $log_stmt = mysqli_prepare($con, "INSERT INTO admin_logs (action, user_id, details, created_at) VALUES (?, ?, CONCAT('Comment ID: ', ?, ', Rating: ', ?), NOW())");
        if ($log_stmt) {
            mysqli_stmt_bind_param($log_stmt, "ssii", $action, $user_id, $comment_id, $rating);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        }
        
        // Commit transaction
        mysqli_commit($con);
        
        $response['success'] = true;
        $response['message'] = 'تم حفظ التقييم بنجاح';
        $response['likes'] = (int)($counts['likes'] ?? 0);
        $response['dislikes'] = (int)($counts['dislikes'] ?? 0);
        
    } catch (Exception $e) {
        // Rollback transaction
        mysqli_rollback($con);
        $response['message'] = 'حدث خطأ أثناء حفظ التقييم: ' . $e->getMessage();
    }
    
} else {
    $response['message'] = 'طريقة الطلب غير صحيحة';
}

// Send response
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>