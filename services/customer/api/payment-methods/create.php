<?php
/**
 * services/customer/api/payment-methods/create.php
 * Create Payment Method - SIMPLIFIED (No account_number needed)
 */

require_once __DIR__ . '/../../../../env-bootstrap.php';
require_once __DIR__ . '/../../classes/PaymentMethod.php';
require_once __DIR__ . '/../../../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../../../../shared/classes/ApiResponse.php';

header('Content-Type: application/json; charset=UTF-8');

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
    $decoded = $jwtHandler->decode($token);
    
    if (!$decoded || !is_array($decoded)) {
        ApiResponse::unauthorized('Invalid token');
    }
    
    if (!isset($decoded['user_id'])) {
        ApiResponse::unauthorized('Invalid token payload');
    }
    
    $userId = $decoded['user_id'];
    
    // ✅ VALIDATE PAYMENT TYPE
    if (!isset($data['type']) || empty($data['type'])) {
        ApiResponse::error('Payment type is required', 400);
    }
    
    $validTypes = ['COD', 'VNPayQR'];
    if (!in_array($data['type'], $validTypes)) {
        ApiResponse::error('Invalid payment type. Only COD and VNPayQR are supported', 400);
    }
    
    // ✅ SIMPLIFIED: Both types don't need account_number
    // COD: Pay cash when receiving vehicle
    // VNPayQR: QR code generated at checkout (no phone needed)
    $data['provider'] = null;
    
    if ($data['type'] === 'VNPayQR') {
        $data['provider'] = 'VNPay';
    }
    
    // Prepare data
    $paymentData = [
        'user_id' => $userId,
        'type' => $data['type'],
        'provider' => $data['provider'],
        'is_default' => isset($data['is_default']) ? (bool)$data['is_default'] : false
    ];
    
    // Create payment method
    $paymentModel = new PaymentMethod();
    $result = $paymentModel->create($paymentData);
    
    if ($result) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Payment method created successfully',
            'data' => $result
        ]);
    } else {
        ApiResponse::error('Failed to create payment method', 500);
    }
    
} catch (Exception $e) {
    error_log('Create payment method error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    ApiResponse::error($e->getMessage(), 500);
}