<?php
/**
 * services/customer/api/payment-methods/set-default.php
 * Set Default Payment Method Endpoint
 */

require_once __DIR__ . '/../../../../env-bootstrap.php';
require_once __DIR__ . '/../../classes/PaymentMethod.php';
require_once __DIR__ . '/../../../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../../../../shared/classes/ApiResponse.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Get token from header
$headers = getallheaders();
$token = null;

foreach ($headers as $key => $value) {
    if (strtolower($key) === 'authorization') {
        if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
            $token = trim($matches[1]);
        }
        break;
    }
}

if (!$token) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authorization token is required'
    ]);
    exit();
}

// Get method_id from URL
$methodId = $_GET['method_id'] ?? null;

if (!$methodId || !is_numeric($methodId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Valid method ID is required'
    ]);
    exit();
}

try {
    // Verify token
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    if (!$decoded || !is_array($decoded)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token'
        ]);
        exit();
    }
    
    if (!isset($decoded['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid token payload'
        ]);
        exit();
    }
    
    $userId = (int)$decoded['user_id'];
    $methodId = (int)$methodId;
    
    // Set as default
    $paymentModel = new PaymentMethod();
    $result = $paymentModel->setDefault($methodId, $userId);
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Default payment method updated successfully'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Payment method not found or unauthorized'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Set default payment method error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}