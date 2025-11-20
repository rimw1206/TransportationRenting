<?php
/**
 * ================================================
 * services/payment/public/process-payment.php
 * REFACTORED - Using PaymentService (FIXED)
 * ================================================
 */

require_once __DIR__ . '/../../../env-bootstrap.php';
require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../services/PaymentService.php';

ApiResponse::handleOptions();

// Get Authorization token
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
} elseif (isset($headers['authorization'])) {
    $token = str_replace('Bearer ', '', $headers['authorization']);
}

if (!$token) {
    ApiResponse::unauthorized('Token is required');
}

// Verify token
try {
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    if (!$decoded) {
        ApiResponse::unauthorized('Invalid or expired token');
    }
    
    $userId = $decoded['user_id'];
    
} catch (Exception $e) {
    error_log('Token verification error: ' . $e->getMessage());
    ApiResponse::unauthorized('Token verification failed');
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    ApiResponse::badRequest('Invalid request data');
}

try {
    $paymentService = new PaymentService();
    $result = $paymentService->processPayment($userId, $data, $token);
    
    ApiResponse::success($result, $result['message']);
    
} catch (Exception $e) {
    error_log('Process payment error: ' . $e->getMessage());
    ApiResponse::error('Payment processing failed: ' . $e->getMessage(), 500);
}
?>