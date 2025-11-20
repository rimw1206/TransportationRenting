<?php
/**
 * ================================================
 * services/payment/public/request-refund.php
 * REFACTORED - Using PaymentService
 * ================================================
 */

require_once __DIR__ . '/../../../env-bootstrap.php';
require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../services/PaymentService.php';

ApiResponse::handleOptions();

$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    ApiResponse::unauthorized('Token is required');
}

try {
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    if (!$decoded) {
        ApiResponse::unauthorized('Invalid token');
    }
    
    $userId = $decoded['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    
    $paymentService = new PaymentService();
    $result = $paymentService->requestRefund($userId, $data);
    
    ApiResponse::success($result, 'Refund request created successfully');
    
} catch (Exception $e) {
    error_log('Request refund error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}
?>