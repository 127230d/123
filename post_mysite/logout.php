<?php
require_once __DIR__ . '/config/session.php';

if (isLoggedIn()) {
    $username = getCurrentUsername();

    require_once __DIR__ . '/config/database.php';
    $log_activity = "INSERT INTO activity_logs (user_id, action_type, action_details)
                    VALUES (?, 'logout', ?)";
    $db->query($log_activity, [$username, json_encode(['ip' => $_SERVER['REMOTE_ADDR']])]);
}

clearUserSession();

header('Location: /login.php');
exit;
