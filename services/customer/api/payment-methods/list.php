<?php
/**
 * services/customer/api/payment-methods/list.php
 * List payment methods endpoint - FIXED VERSION
 */

// ✅ CRITICAL: No output before this point
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, only log them
ini_set('log_errors', 1);

// ✅ Set headers FIRST, before any output
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Validate method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Load dependencies - AFTER headers
require_once __DIR__ . '/../../../../env-bootstrap.php';
require_once __DIR__ . '/../../classes/PaymentMethod.php';
require_once __DIR__ . '/../../../../shared/classes/JWTHandler.php';

try {
    // Get token (case-insensitive)
    $token = null;
    $headers = getallheaders();
    
    // ✅ LOG ALL HEADERS
    error_log("=== LIST PAYMENT METHODS DEBUG ===");
    error_log("All headers: " . json_encode($headers));
    
    if (!$headers) {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        error_log("Reconstructed headers: " . json_encode($headers));
    }
    
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                $token = trim($matches[1]);
            } else {
                $token = trim($value);
            }
            error_log("Found Authorization header: " . substr($token, 0, 20) . "...");
            break;
        }
    }
    
    if (!$token) {
        error_log("❌ No token found in headers");
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authorization token is required',
            'debug_headers' => array_keys($headers)
        ]);
        exit();
    }
    
    // Verify token
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    error_log("Decoded token: " . json_encode($decoded));
    
    if ($decoded === false || !is_array($decoded)) {
        error_log("❌ Token decode failed");
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token'
        ]);
        exit();
    }
    
    if (!isset($decoded['user_id'])) {
        error_log("❌ Token missing user_id");
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid token payload - missing user_id'
        ]);
        exit();
    }
    
    $userId = (int)$decoded['user_id'];
    error_log("✅ User ID from token: {$userId}");
    
    // Get payment methods
    $paymentModel = new PaymentMethod();
    $methods = $paymentModel->getByUserId($userId);
    
    error_log("✅ Found " . count($methods) . " payment methods");
    error_log("Payment methods data: " . json_encode($methods));
    
    // Always return array, even if empty
    if ($methods === false || $methods === null) {
        $methods = [];
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment methods retrieved successfully',
        'data' => $methods,
        'count' => count($methods),
        'user_id' => $userId // Debug info
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    error_log('❌ GET /payment-methods - Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}