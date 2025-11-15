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

// Get method_id from URL
$methodId = $_GET['method_id'] ?? null;

if (!$methodId) {
    ApiResponse::error('Method ID is required', 400);
}

try {
    // Verify token
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token); // Changed from verify()
    
    if (!$decoded) {
        ApiResponse::unauthorized('Invalid token');
    }
    
    $userId = $decoded['user_id']; // Changed from $decode->user_id
    
    // Delete payment method
    $paymentModel = new PaymentMethod();
    $result = $paymentModel->delete($methodId, $userId);
    
    if ($result) {
        ApiResponse::success(null, 'Payment method deleted successfully');
    } else {
        ApiResponse::error('Payment method not found or unauthorized', 404);
    }
    
} catch (Exception $e) {
    error_log('Delete payment method error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}
