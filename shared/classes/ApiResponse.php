<?php
class ApiResponse {
    public static function success($data = [], $message = 'OK', $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'status_code' => $status
        ]);
        exit;
    }

    public static function error($message = 'Error', $status = 400) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'status_code' => $status
        ]);
        exit;
    }

    public static function notFound($message = 'Not found') {
        self::error($message, 404);
    }

    public static function serverError($message = 'Internal Server Error') {
        self::error($message, 500);
    }

    // Dùng cho OPTIONS preflight request (CORS)
    public static function handleOptions() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }
}
