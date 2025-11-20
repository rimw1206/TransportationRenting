<?php
/**
 * ================================================
 * services/payment/public/get-invoice.php
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
    $invoiceId = $_GET['invoice_id'] ?? null;
    
    if (!$invoiceId) {
        ApiResponse::badRequest('Invoice ID is required');
    }
    
    $paymentService = new PaymentService();
    $invoice = $paymentService->getInvoice($invoiceId, $userId);
    
    ApiResponse::success($invoice, 'Invoice retrieved successfully');
    
} catch (Exception $e) {
    error_log('Get invoice error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}
?>