<?php
/**
 * ================================================
 * services/payment/public/verify-payment.php
 * ✅ REFACTORED: Auto-create Order after VNPay success
 * ================================================
 */

require_once __DIR__ . '/../../../env-bootstrap.php';
require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../../../shared/classes/ApiClient.php';

ApiResponse::handleOptions();

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod === 'POST') {
    // Admin manually verifies payment
    
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    }
    
    if (!$token) {
        ApiResponse::unauthorized('Token is required');
    }
    
    try {
        $jwtHandler = new JWTHandler();
        $decoded = $jwtHandler->decode($token);
        
        if (!$decoded) {
            ApiResponse::unauthorized('Invalid token');
        }
        
    } catch (Exception $e) {
        ApiResponse::unauthorized('Token verification failed');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['transaction_id'])) {
        ApiResponse::badRequest('Transaction ID is required');
    }
    
    try {
        $paymentService = new PaymentService();
        $result = $paymentService->verifyPayment($data['transaction_id'], $token);
        
        ApiResponse::success($result, 'Payment verified successfully');
        
    } catch (Exception $e) {
        error_log('Verify payment error: ' . $e->getMessage());
        ApiResponse::error($e->getMessage(), 500);
    }
    
} elseif ($requestMethod === 'GET') {
    // ✅ VNPay callback - AUTO CREATE ORDER ON SUCCESS
    
    $vnpayData = $_GET;
    
    if (empty($vnpayData)) {
        echo json_encode([
            'RspCode' => '97',
            'Message' => 'Invalid Signature'
        ]);
        exit;
    }
    
    try {
        $paymentService = new PaymentService();
        $apiClient = new ApiClient();
        $apiClient->setServiceUrl('order', 'http://localhost:8004');
        
        // 1. Verify VNPay signature & update transaction
        $status = $paymentService->handleVNPayCallback($vnpayData);
        
        if ($status === 'success') {
            error_log("=== VNPay Payment Success ===");
            
            // 2. Get transaction details
            $transactionCode = $vnpayData['vnp_TxnRef'];
            require_once __DIR__ . '/../classes/Payment.php';
            $paymentModel = new Payment();
            $transaction = $paymentModel->getTransactionByCode($transactionCode);
            
            if ($transaction) {
                error_log("Transaction found: ID=" . $transaction['transaction_id']);
                error_log("Rental ID: " . $transaction['rental_id']);
                error_log("User ID: " . $transaction['user_id']);
                
                // 3. Create Order automatically
                error_log("=== AUTO-CREATING ORDER ===");
                
                $orderPayload = [
                    'rental_id' => $transaction['rental_id'],
                    'user_id' => $transaction['user_id']
                ];
                
                // Generate temporary admin token for order creation
                $jwtHandler = new JWTHandler();
                $adminToken = $jwtHandler->encode([
                    'user_id' => 1, // Admin user
                    'role' => 'admin',
                    'exp' => time() + 300 // 5 minutes
                ]);
                
                $orderResponse = $apiClient->post('order', '/orders', $orderPayload, [
                    'Authorization: Bearer ' . $adminToken,
                    'Content-Type: application/json'
                ]);
                
                error_log("Order API Response: " . $orderResponse['status_code']);
                error_log("Order API Body: " . $orderResponse['raw_response']);
                
                if ($orderResponse['status_code'] === 201) {
                    $orderData = json_decode($orderResponse['raw_response'], true);
                    error_log("✅ Order created: ID=" . ($orderData['data']['order_id'] ?? 'unknown'));
                } else {
                    error_log("⚠️ Order creation failed but payment successful");
                }
            }
            
            echo json_encode([
                'RspCode' => '00',
                'Message' => 'Confirm Success'
            ]);
        } else {
            error_log("=== VNPay Payment Failed ===");
            echo json_encode([
                'RspCode' => '00',
                'Message' => 'Confirm Success'
            ]);
        }
        
        exit;
        
    } catch (Exception $e) {
        error_log('VNPay callback error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        echo json_encode([
            'RspCode' => '99',
            'Message' => 'Unknown error'
        ]);
        exit;
    }
    
} else {
    ApiResponse::methodNotAllowed();
}