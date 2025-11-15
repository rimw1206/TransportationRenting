<?php

require_once __DIR__ . '/../../../..//env-bootstrap.php';
/**
 * ============================================
 * services/customer/api/auth/login.php
 * API endpoint for user login - FIXED VERSION
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
header('Content-Type: application/json; charset=utf-8');

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
    error_log("Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    error_log("Raw body length: " . strlen($requestBody));
    
    if (empty($requestBody)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Request body is empty'
        ]);
        exit;
    }
    
    $data = json_decode($requestBody, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON format: ' . json_last_error_msg()
        ]);
        exit;
    }
    
    // Log parsed data
    error_log("Parsed data: " . json_encode($data));
    
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    
    error_log("Login attempt - Username: " . $username);
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Username và password không được để trống'
        ]);
        exit;
    }
    
    // Call AuthService
    $authService = new AuthService();
    $result = $authService->login($username, $password);
    
    // Log result
    error_log("AuthService result success: " . ($result['success'] ? 'true' : 'false'));
    
    if ($result['success']) {
        // Make sure all required fields are present
        if (!isset($result['user']) || !isset($result['token'])) {
            error_log("ERROR: Missing user or token in successful login response");
            error_log("Result keys: " . implode(', ', array_keys($result)));
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error: incomplete response data'
            ]);
            exit;
        }
        
        // Log successful login
        error_log("Login successful for user: " . $result['user']['username']);
        error_log("Token length: " . strlen($result['token']));
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'user' => $result['user'],
            'token' => $result['token'],
            'refresh_token' => $result['refresh_token'] ?? null,
            'expires_in' => $result['expires_in'] ?? 86400
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Login failed
        error_log("Login failed: " . $result['message']);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log('Login API Exception: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra trong quá trình đăng nhập',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}