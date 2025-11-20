<?php
/**
 * ================================================
 * services/payment/public/get-refund.php
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
    $refundId = $_GET['refund_id'] ?? null;
    
    if (!$refundId) {
        ApiResponse::badRequest('Refund ID is required');
    }
    
    $paymentService = new PaymentService();
    $refund = $paymentService->getRefund($refundId, $userId);
    
    ApiResponse::success($refund, 'Refund details retrieved successfully');
    
} catch (Exception $e) {
    error_log('Get refund error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}
?>