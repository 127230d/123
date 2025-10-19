<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once 'dssssssssb.php';

header('Content-Type: application/json');

$response = ['success' => false];

try {
    $username = $_SESSION["username"];
    
    // Get user data
    $user_query = mysqli_prepare($con, "SELECT username, points, subscription FROM login WHERE username = ?");
    mysqli_stmt_bind_param($user_query, "s", $username);
    mysqli_stmt_execute($user_query);
    $user_result = mysqli_stmt_get_result($user_query);
    $user_data = mysqli_fetch_assoc($user_result);
    
    if ($user_data) {
        // Get additional stats
        $files_count_query = mysqli_prepare($con, "SELECT COUNT(*) as count FROM shared_files WHERE original_owner_id = ?");
        mysqli_stmt_bind_param($files_count_query, "s", $username);
        mysqli_stmt_execute($files_count_query);
        $files_count_result = mysqli_stmt_get_result($files_count_query);
        $files_count_data = mysqli_fetch_assoc($files_count_result);
        
        $purchases_count_query = mysqli_prepare($con, "SELECT COUNT(*) as count FROM file_purchases WHERE buyer_username = ?");
        mysqli_stmt_bind_param($purchases_count_query, "s", $username);
        mysqli_stmt_execute($purchases_count_query);
        $purchases_count_result = mysqli_stmt_get_result($purchases_count_query);
        $purchases_count_data = mysqli_fetch_assoc($purchases_count_result);
        
        $sales_count_query = mysqli_prepare($con, "SELECT COUNT(*) as count FROM file_purchases WHERE seller_username = ?");
        mysqli_stmt_bind_param($sales_count_query, "s", $username);
        mysqli_stmt_execute($sales_count_query);
        $sales_count_result = mysqli_stmt_get_result($sales_count_query);
        $sales_count_data = mysqli_fetch_assoc($sales_count_result);
        
        $response['success'] = true;
        $response['username'] = $user_data['username'];
        $response['points'] = intval($user_data['points']);
        $response['subscription'] = $user_data['subscription'];
        $response['stats'] = [
            'files_count' => intval($files_count_data['count']),
            'purchases_count' => intval($purchases_count_data['count']),
            'sales_count' => intval($sales_count_data['count'])
        ];
    } else {
        $response['message'] = 'User data not found';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error fetching user data: ' . $e->getMessage();
    error_log("Get user data error: " . $e->getMessage());
}

echo json_encode($response);
?>
