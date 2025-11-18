<?php
// ========================================
// shared/classes/ApiResponse.php - FIXED
// ========================================

class ApiResponse {
    
    /**
     * Success response (200)
     * Fixed to accept meta parameter
     */
    public static function success($data = [], $message = 'Success', $meta = []) {
        $response = [
            'success' => true,
            'message' => $message,
            'status_code' => 200,
            'data' => $data
        ];
        
        // Merge metadata if provided
        if (!empty($meta) && is_array($meta)) {
            foreach ($meta as $key => $value) {
                $response[$key] = $value;
            }
        }
        
        // Add timestamp
        $response['timestamp'] = date('c');
        
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Created response (201)
     */
    public static function created($data = [], $message = 'Resource created successfully') {
        self::send(true, $message, $data, 201);
    }

    /**
     * Accepted response (202)
     */
    public static function accepted($data = [], $message = 'Request accepted for processing') {
        self::send(true, $message, $data, 202);
    }

    /**
     * No content response (204)
     */
    public static function noContent() {
        http_response_code(204);
        exit;
    }

    /**
     * Generic error response
     */
    public static function error($message = 'An error occurred', $status = 400, $errors = null) {
        self::send(false, $message, null, $status, $errors);
    }

    /**
     * Bad request (400)
     */
    public static function badRequest($message = 'Bad request', $errors = null) {
        self::error($message, 400, $errors);
    }

    /**
     * Unauthorized (401)
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }

    /**
     * Forbidden (403)
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }

    /**
     * Not found (404)
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }

    /**
     * Method not allowed (405)
     */
    public static function methodNotAllowed($message = 'Method not allowed') {
        self::error($message, 405);
    }

    /**
     * Conflict (409)
     */
    public static function conflict($message = 'Conflict', $errors = null) {
        self::error($message, 409, $errors);
    }

    /**
     * Unprocessable entity (422) - Validation errors
     */
    public static function validationError($message = 'Validation failed', $errors = []) {
        self::error($message, 422, $errors);
    }

    /**
     * Too many requests (429)
     */
    public static function tooManyRequests($message = 'Too many requests') {
        self::error($message, 429);
    }

    /**
     * Internal server error (500)
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }

    /**
     * Service unavailable (503)
     */
    public static function serviceUnavailable($message = 'Service temporarily unavailable') {
        self::error($message, 503);
    }

    /**
     * Gateway timeout (504)
     */
    public static function gatewayTimeout($message = 'Gateway timeout') {
        self::error($message, 504);
    }

    /**
     * Handle CORS preflight requests
     */
    public static function handleOptions() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Paginated response
     */
    public static function paginated($data, $total, $page, $perPage, $message = 'Success') {
        $totalPages = ceil($total / $perPage);
        
        self::send(true, $message, [
            'items' => $data,
            'pagination' => [
                'total' => (int)$total,
                'per_page' => (int)$perPage,
                'current_page' => (int)$page,
                'total_pages' => (int)$totalPages,
                'has_more' => $page < $totalPages
            ]
        ], 200);
    }

    /**
     * Send response helper
     */
    private static function send($success, $message, $data = null, $status = 200, $errors = null) {
        // CRITICAL FIX: Ensure $status is always an integer
        if (!is_int($status)) {
            error_log("ApiResponse::send() received non-integer status: " . print_r($status, true));
            $status = 200; // Default to 200 if invalid
        }
        
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => $success,
            'message' => $message,
            'status_code' => $status
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        // Add timestamp
        $response['timestamp'] = date('c');
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Validation helper - format validation errors
     */
    public static function formatValidationErrors($errors) {
        $formatted = [];
        foreach ($errors as $field => $messages) {
            $formatted[$field] = is_array($messages) ? $messages : [$messages];
        }
        return $formatted;
    }

    /**
     * Exception handler - convert exceptions to API responses
     */
    public static function handleException($exception) {
        error_log('API Exception: ' . $exception->getMessage());
        
        // Don't expose internal errors in production
        $message = (getenv('APP_ENV') === 'production') 
            ? 'An error occurred' 
            : $exception->getMessage();
        
        $status = method_exists($exception, 'getStatusCode') 
            ? $exception->getStatusCode() 
            : 500;
        
        self::error($message, $status);
    }

    /**
     * Set CORS headers
     */
    public static function setCorsHeaders() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400'); // 24 hours
    }

    /**
     * Rate limit exceeded response with retry info
     */
    public static function rateLimitExceeded($retryAfter = 60) {
        header("Retry-After: $retryAfter");
        self::error("Rate limit exceeded. Please try again in {$retryAfter} seconds.", 429);
    }

    /**
     * Maintenance mode response
     */
    public static function maintenance($message = 'Service under maintenance') {
        header('Retry-After: 3600'); // 1 hour
        self::error($message, 503);
    }

    /**
     * Custom response with metadata
     */
    public static function withMeta($data, $meta = [], $message = 'Success') {
        self::send(true, $message, [
            'items' => $data,
            'meta' => $meta
        ], 200);
    }

    /**
     * Redirect response (for API redirects)
     */
    public static function redirect($url, $permanent = false) {
        http_response_code($permanent ? 301 : 302);
        header("Location: $url");
        echo json_encode([
            'success' => true,
            'message' => 'Redirecting',
            'redirect_url' => $url
        ]);
        exit;
    }
}