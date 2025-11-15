<?php 

require_once __DIR__ . '/../../../..//env-bootstrap.php';
require_once __DIR__ . '/../../classes/PaymentMethod.php';
require_once __DIR__ . '/../../../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../../../../shared/classes/ApiResponse.php';

// Get token from header
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
}

if (!$token) {
    ApiResponse::unauthorized('Token is required');
}

// Get request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    ApiResponse::error('Invalid request data', 400);
}

try {
    // Verify token
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token); // Changed from verify()
    
    if (!$decoded) {
        ApiResponse::unauthorized('Invalid token');
    }
    
    $userId = $decoded['user_id']; // Changed from $decode->user_id
    
    // Validate required fields
    $required = ['type', 'provider', 'account_number'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            ApiResponse::error("Field '{$field}' is required", 400);
        }
    }
    
    // Add user_id to data
    $data['user_id'] = $userId;
    
    // Create payment method
    $paymentModel = new PaymentMethod();
    $result = $paymentModel->create($data);
    
    if ($result) {
        ApiResponse::success($result, 'Payment method created successfully', 201);
    } else {
        ApiResponse::error('Failed to create payment method', 500);
    }
    
} catch (Exception $e) {
    error_log('Create payment method error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}
