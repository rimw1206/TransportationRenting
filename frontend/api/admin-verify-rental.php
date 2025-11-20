<?php
/**
 * ================================================
 * frontend/api/admin-verify-rental.php - FIXED
 * ✅ Ensures user has payment method before creating transaction
 * ✅ Creates default VNPayQR if user has no payment methods
 * ================================================
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$token = $_SESSION['token'];
$data = json_decode(file_get_contents('php://input'), true);

$rentalId = $data['rental_id'] ?? null;
$action = $data['action'] ?? null;

if (!$rentalId || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'rental_id and action required']);
    exit;
}

require_once __DIR__ . '/../../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');
$apiClient->setServiceUrl('payment', 'http://localhost:8005');
$apiClient->setServiceUrl('order', 'http://localhost:8004');
$apiClient->setServiceUrl('customer', 'http://localhost:8001');

try {
    // 1. Get rental details
    error_log("=== STEP 1: Getting rental details for ID: {$rentalId} ===");
    
    $rentalResponse = $apiClient->get('rental', "/rentals/{$rentalId}", [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($rentalResponse['status_code'] !== 200) {
        throw new Exception('Rental not found');
    }
    
    $rentalData = json_decode($rentalResponse['raw_response'], true);
    $rental = $rentalData['data']['rental'] ?? $rentalData['data'];
    
    error_log("Rental current status: " . $rental['status']);
    error_log("Rental user_id: " . $rental['user_id']);
    
    if ($rental['status'] !== 'Pending') {
        throw new Exception('Rental is not in Pending status. Current: ' . $rental['status']);
    }
    
    if ($action === 'approve') {
        // 2. Update rental status to Ongoing
        error_log("=== STEP 2: Updating rental status to Ongoing ===");
        
        $updateResponse = $apiClient->request(
            'rental',
            "/rentals/{$rentalId}/status",
            'PUT',
            ['status' => 'Ongoing'],
            [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]
        );
        
        error_log("Update Response Code: " . $updateResponse['status_code']);
        
        if ($updateResponse['status_code'] !== 200) {
            throw new Exception('Failed to update rental status');
        }
        
        error_log("✅ Rental status → Ongoing");
        
        // 3. Ensure user has payment method
        error_log("=== STEP 3: Ensuring user has payment method ===");
        
        // Create a temporary admin token for user operations
        require_once __DIR__ . '/../../shared/classes/JWTHandler.php';
        $jwtHandler = new JWTHandler();
        $userToken = $jwtHandler->encode([
            'user_id' => $rental['user_id'],
            'role' => 'user',
            'exp' => time() + 600 // 10 minutes
        ]);
        
        // Get user's payment methods
        $pmResponse = $apiClient->get('customer', '/payment-methods', [
            'Authorization: Bearer ' . $userToken
        ]);
        
        $paymentMethods = [];
        $defaultMethod = null;
        
        if ($pmResponse['status_code'] === 200) {
            $pmData = json_decode($pmResponse['raw_response'], true);
            if ($pmData && $pmData['success']) {
                $paymentMethods = $pmData['data'] ?? [];
                
                // Find default or first method
                foreach ($paymentMethods as $method) {
                    if ($method['is_default']) {
                        $defaultMethod = $method;
                        break;
                    }
                }
                
                if (!$defaultMethod && !empty($paymentMethods)) {
                    $defaultMethod = $paymentMethods[0];
                }
            }
        }
        
        // ✅ If no payment method, create default VNPayQR
        if (!$defaultMethod) {
            error_log("⚠️ User has no payment method, creating default VNPayQR...");
            
            $createPMResponse = $apiClient->post('customer', '/payment-methods', [
                'type' => 'VNPayQR',
                'provider' => 'VNPay',
                'is_default' => true
            ], [
                'Authorization: Bearer ' . $userToken,
                'Content-Type: application/json'
            ]);
            
            if ($createPMResponse['status_code'] === 201) {
                $createPMData = json_decode($createPMResponse['raw_response'], true);
                if ($createPMData && $createPMData['success']) {
                    $defaultMethod = $createPMData['data'];
                    error_log("✅ Created default VNPayQR method: " . $defaultMethod['method_id']);
                }
            }
            
            // If still no method, fallback to COD
            if (!$defaultMethod) {
                error_log("⚠️ Failed to create VNPayQR, creating COD...");
                
                $createCODResponse = $apiClient->post('customer', '/payment-methods', [
                    'type' => 'COD',
                    'is_default' => true
                ], [
                    'Authorization: Bearer ' . $userToken,
                    'Content-Type: application/json'
                ]);
                
                if ($createCODResponse['status_code'] === 201) {
                    $createCODData = json_decode($createCODResponse['raw_response'], true);
                    if ($createCODData && $createCODData['success']) {
                        $defaultMethod = $createCODData['data'];
                        error_log("✅ Created default COD method: " . $defaultMethod['method_id']);
                    }
                }
            }
        }
        
        if (!$defaultMethod) {
            // Rollback rental status
            $apiClient->request(
                'rental',
                "/rentals/{$rentalId}/status",
                'PUT',
                ['status' => 'Pending'],
                ['Authorization: Bearer ' . $token, 'Content-Type: application/json']
            );
            throw new Exception('Cannot create payment method for user');
        }
        
        error_log("Payment method: " . $defaultMethod['type'] . " (ID: " . $defaultMethod['method_id'] . ")");
        
        // 4. Create Transaction
        error_log("=== STEP 4: Creating transaction ===");
        
        $transactionPayload = [
            'rental_id' => $rentalId,
            'amount' => $rental['total_cost'],
            'payment_method_id' => $defaultMethod['method_id']
        ];
        
        $transactionResponse = $apiClient->post('payment', '/payments/process', $transactionPayload, [
            'Authorization: Bearer ' . $userToken,
            'Content-Type: application/json'
        ]);
        
        error_log("Transaction Response Code: " . $transactionResponse['status_code']);
        error_log("Transaction Response Body: " . $transactionResponse['raw_response']);
        
        if ($transactionResponse['status_code'] !== 200) {
            // Rollback rental status
            error_log("Transaction failed, rolling back...");
            $apiClient->request(
                'rental',
                "/rentals/{$rentalId}/status",
                'PUT',
                ['status' => 'Pending'],
                ['Authorization: Bearer ' . $token, 'Content-Type: application/json']
            );
            throw new Exception('Failed to create transaction');
        }
        
        $transactionData = json_decode($transactionResponse['raw_response'], true);
        $transaction = $transactionData['data'] ?? [];
        
        error_log("✅ Transaction created: " . ($transaction['transaction_id'] ?? 'unknown'));
        
        // 5. If COD → Create Order immediately
        if ($defaultMethod['type'] === 'COD') {
            error_log("=== STEP 5: Creating order (COD) ===");
            
            $orderPayload = [
                'rental_id' => $rentalId,
                'user_id' => $rental['user_id']
            ];
            
            $orderResponse = $apiClient->post('order', '/orders', $orderPayload, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            
            error_log("Order Response Code: " . $orderResponse['status_code']);
            
            if ($orderResponse['status_code'] === 201) {
                $orderData = json_decode($orderResponse['raw_response'], true);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Rental approved (COD) - Order created',
                    'data' => [
                        'rental_id' => $rentalId,
                        'rental_status' => 'Ongoing',
                        'payment_method' => 'COD',
                        'transaction_id' => $transaction['transaction_id'] ?? null,
                        'order_id' => $orderData['data']['order_id'] ?? null
                    ]
                ]);
            } else {
                error_log("Order creation failed for COD");
                echo json_encode([
                    'success' => true,
                    'message' => 'Rental approved but order creation failed',
                    'data' => [
                        'rental_id' => $rentalId,
                        'rental_status' => 'Ongoing',
                        'payment_method' => 'COD',
                        'transaction_id' => $transaction['transaction_id'] ?? null,
                        'warning' => 'Order not created'
                    ]
                ]);
            }
            
        } else {
            // VNPayQR → Wait for user payment
            error_log("=== VNPayQR: Waiting for user payment ===");
            
            echo json_encode([
                'success' => true,
                'message' => 'Rental approved - Awaiting VNPay payment',
                'data' => [
                    'rental_id' => $rentalId,
                    'rental_status' => 'Ongoing',
                    'payment_method' => 'VNPayQR',
                    'transaction_id' => $transaction['transaction_id'] ?? null,
                    'transaction_code' => $transaction['transaction_code'] ?? null,
                    'note' => 'User must complete VNPay payment before order is created'
                ]
            ]);
        }
        
    } elseif ($action === 'reject') {
        error_log("=== Rejecting rental ===");
        
        $cancelResponse = $apiClient->request(
            'rental',
            "/rentals/{$rentalId}/cancel",
            'PUT',
            [],
            [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]
        );
        
        if ($cancelResponse['status_code'] !== 200) {
            throw new Exception('Failed to cancel rental');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Rental rejected',
            'data' => [
                'rental_id' => $rentalId,
                'rental_status' => 'Cancelled'
            ]
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log('=== EXCEPTION CAUGHT ===');
    error_log('Error: ' . $e->getMessage());
    error_log('Trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}