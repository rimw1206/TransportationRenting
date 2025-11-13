<?php
/**
 * ============================================
 * services/customer/api/auth/login.php
 * API endpoint for user login
 * ============================================
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/customer_api_errors.log');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    // Load required files
    require_once __DIR__ . '/../../../../shared/classes/DatabaseManager.php';
    require_once __DIR__ . '/../../../../shared/classes/ApiResponse.php';
    require_once __DIR__ . '/../../../../shared/classes/Cache.php';
    require_once __DIR__ . '/../../services/AuthService.php';
    
    // Load .env
    $envFile = __DIR__ . '/../../../../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value, '"\'');
                putenv(trim($key) . '=' . trim($value, '"\''));
            }
        }
    }
    
    // Get request body
    $requestBody = file_get_contents('php://input');
    
    // Log raw request for debugging
    error_log("=== LOGIN API REQUEST ===");
    error_log("Raw body: " . $requestBody);
    
    if (empty($requestBody)) {
        ApiResponse::error('Request body is empty', 400);
    }
    
    $data = json_decode($requestBody, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        ApiResponse::error('Invalid JSON format: ' . json_last_error_msg(), 400);
    }
    
    // Log parsed data
    error_log("Parsed data: " . json_encode($data));
    
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    
    error_log("Username: " . $username);
    error_log("Password length: " . strlen($password));
    
    if (empty($username) || empty($password)) {
        ApiResponse::error('Username và password không được để trống', 400);
    }
    
    // Call AuthService
    $authService = new AuthService();
    $result = $authService->login($username, $password);
    
    // Log result
    error_log("AuthService result: " . json_encode($result));
    
    if ($result['success']) {
        ApiResponse::success([
            'user' => $result['user'],
            'token' => $result['token'],
            'refresh_token' => $result['refresh_token'] ?? null
        ], 'Đăng nhập thành công', 200);
    } else {
        ApiResponse::error($result['message'], 401);
    }
    
} catch (Exception $e) {
    error_log('Login API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    ApiResponse::error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}