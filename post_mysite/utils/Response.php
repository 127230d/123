<?php

class Response {

    public static function success($message, $data = []) {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    public static function error($message, $code = 400) {
        http_response_code($code);
        self::json([
            'success' => false,
            'error' => $message
        ]);
    }

    public static function json($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function redirect($url, $message = null) {
        if ($message) {
            $_SESSION['flash_message'] = $message;
        }
        header('Location: ' . $url);
        exit;
    }
}
