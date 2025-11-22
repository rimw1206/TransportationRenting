<?php
/**
 * services/payment/public/process-payment.php
 * ✅ FINAL FIX: Correct Customer Service API call
 */

require_once __DIR__ . '/../../../env-bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';
require_once __DIR__ . '/../../../gateway/middleware/auth.php';
require_once __DIR__ . '/../classes/Payment.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed('Only POST method is allowed');
}

// Authenticate user
$auth = AuthMiddleware::authenticate();
if (!$auth['success']) {
    ApiResponse::unauthorized($auth['message']);
}

// Get request body
$requestBody = json_decode(file_get_contents('php://input'), true);

if (!$requestBody) {
    ApiResponse::badRequest('Request body is required');
}

// Validate required fields
$rentalId = $requestBody['rental_id'] ?? null;
$amount = $requestBody['amount'] ?? null;
$paymentMethodId = $requestBody['payment_method_id'] ?? null;
$metadata = $requestBody['metadata'] ?? [];

if (!$rentalId || !$amount || !$paymentMethodId) {
    ApiResponse::badRequest('rental_id, amount, and payment_method_id are required');
}

error_log("=== PAYMENT PROCESS START ===");
error_log("User ID: " . $auth['user_id']);
error_log("Rental ID: $rentalId");
error_log("Amount: $amount");
error_log("Payment Method ID: $paymentMethodId");

try {
    $paymentModel = new Payment();
    
    // Get payment method from Customer Service
    require_once __DIR__ . '/../../../shared/classes/ApiClient.php';
    $apiClient = new ApiClient();
    $apiClient->setServiceUrl('customer', 'http://localhost:8001');
    
    error_log("Getting payment methods from Customer Service...");
    
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    error_log("Auth header present: " . (!empty($authHeader) ? 'YES' : 'NO'));
    
    // ✅ Get ALL payment methods
    $pmResponse = $apiClient->get('customer', '/payment-methods', [
        'Authorization: ' . $authHeader
    ]);
    
    error_log("Customer Service Response: HTTP " . $pmResponse['status_code']);
    
    if ($pmResponse['status_code'] !== 200) {
        error_log("❌ Customer Service error: " . $pmResponse['raw_response']);
        ApiResponse::error('Cannot retrieve payment methods from Customer Service', 500);
    }
    
    $pmData = json_decode($pmResponse['raw_response'], true);
    
    if (!$pmData || !$pmData['success']) {
        error_log("❌ Invalid Customer Service response");
        ApiResponse::error('Invalid response from Customer Service', 500);
    }
    
    if (empty($pmData['data'])) {
        ApiResponse::badRequest('No payment methods available');
    }
    
    // Find payment method by ID
    $paymentMethod = null;
    foreach ($pmData['data'] as $pm) {
        if ($pm['method_id'] == $paymentMethodId) {
            $paymentMethod = $pm;
            break;
        }
    }
    
    if (!$paymentMethod) {
        error_log("❌ Payment method $paymentMethodId not found");
        ApiResponse::badRequest('Payment method not found');
    }
    
    error_log("✅ Payment method found: " . $paymentMethod['type']);
    
    $paymentType = $paymentMethod['type'];
    
    // Generate transaction code
    $transactionCode = strtoupper($paymentType) . '-' . date('YmdHis') . '-' . substr(md5(uniqid()), 0, 6);
    
    // Generate QR code if VNPayQR
    $qrCodeUrl = null;
    if ($paymentType === 'VNPayQR') {
        $qrCodeUrl = "https://img.vietqr.io/image/VNPay-{$transactionCode}-compact.png?amount=" . intval($amount);
    }
    
    $status = 'Pending';
    
    // Extract rental IDs
    $rentalIds = $metadata['rental_ids'] ?? [$rentalId];
    $rentalCount = count($rentalIds);
    
    error_log("Creating payment for $rentalCount rentals");
    
    // Begin transaction
    $paymentModel->beginTransaction();
    
    try {
        // Create transaction
        $transactionId = $paymentModel->createTransaction([
            'user_id' => $auth['user_id'],
            'amount' => $amount,
            'payment_method' => $paymentType,
            'payment_gateway' => $paymentMethod['provider'] ?? 'NULL',
            'transaction_code' => $transactionCode,
            'qr_code_url' => $qrCodeUrl,
            'status' => $status,
            'metadata' => [
                'rental_ids' => $rentalIds,
                'rental_count' => $rentalCount,
                'cart_checkout' => $metadata['cart_checkout'] ?? ($rentalCount > 1),
                'promo_code' => $metadata['promo_code'] ?? null,
                'original_amount' => $metadata['original_amount'] ?? $amount,
                'discount_amount' => $metadata['discount_amount'] ?? 0
            ]
        ]);
        
        error_log("✅ Transaction created: $transactionId");
        
        // Link rentals
        $amountPerRental = $amount / $rentalCount;
        $amounts = array_fill(0, $rentalCount, $amountPerRental);
        
        $paymentModel->linkRentalsToTransaction($transactionId, $rentalIds, $amounts);
        
        error_log("✅ Linked $rentalCount rentals");
        
        $paymentModel->commit();
        
        $message = $paymentType === 'COD' 
            ? 'Đặt xe thành công! Thanh toán khi nhận xe.'
            : 'Đặt xe thành công! Quét mã QR để thanh toán.';
        
        if ($rentalCount > 1) {
            $message .= " (Tổng $rentalCount đơn)";
        }
        
        ApiResponse::success([
            'transaction_id' => $transactionId,
            'transaction_code' => $transactionCode,
            'payment_method' => $paymentType,
            'amount' => $amount,
            'status' => $status,
            'qr_code_url' => $qrCodeUrl,
            'rental_count' => $rentalCount,
            'rental_ids' => $rentalIds,
            'is_cart_checkout' => ($rentalCount > 1),
            'message' => $message
        ], 'Payment created successfully');
        
    } catch (Exception $e) {
        $paymentModel->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("❌ Payment error: " . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}