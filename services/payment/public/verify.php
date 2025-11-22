<?php
/**
 * ================================================
 * services/payment/public/verify.php - FIXED
 * ✅ Uses RentalPayments junction table instead of rental_id column
 * ================================================
 */

require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../../../shared/classes/ApiClient.php';

header('Content-Type: application/json');

try {
    // Get auth token
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Missing or invalid Authorization header'
        ]);
        exit;
    }
    
    $token = $matches[1];
    
    // Verify JWT
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token'
        ]);
        exit;
    }
    
    // Get request body
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    
    $transactionId = $data['transaction_id'] ?? null;
    
    if (!$transactionId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'transaction_id is required'
        ]);
        exit;
    }
    
    // Connect to database
    $conn = DatabaseManager::getInstance('payment');
    
    // ✅ FIXED: Get transaction WITHOUT rental_id column (it doesn't exist)
    $stmt = $conn->prepare("
        SELECT 
            t.transaction_id, 
            t.user_id, 
            t.amount, 
            t.status, 
            t.payment_method,
            t.payment_gateway,
            t.transaction_code,
            t.transaction_date,
            t.metadata
        FROM Transactions t
        WHERE t.transaction_id = ?
    ");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Transaction not found'
        ]);
        exit;
    }
    
    // ✅ FIXED: Get rental_ids from RentalPayments junction table
    $stmt = $conn->prepare("
        SELECT rental_id, amount 
        FROM RentalPayments 
        WHERE transaction_id = ?
    ");
    $stmt->execute([$transactionId]);
    $rentalPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract rental IDs
    $rentalIds = array_column($rentalPayments, 'rental_id');
    $primaryRentalId = $rentalIds[0] ?? null;
    
    // Check if already verified
    if ($transaction['status'] === 'Success') {
        // Add rental info to response
        $transaction['rental_id'] = $primaryRentalId;
        $transaction['rental_ids'] = $rentalIds;
        $transaction['rental_count'] = count($rentalIds);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Transaction already verified',
            'data' => $transaction
        ]);
        exit;
    }
    
    // Update transaction to Success
    $stmt = $conn->prepare("
        UPDATE Transactions 
        SET status = 'Success' 
        WHERE transaction_id = ?
    ");
    $stmt->execute([$transactionId]);
    
    // ✅ AUTO CREATE ORDER(s) after payment success
    $ordersCreated = [];
    $orderErrors = [];
    
    if (!empty($rentalIds)) {
        try {
            $apiClient = new ApiClient();
            $apiClient->setServiceUrl('order', 'http://localhost:8004');
            $apiClient->setServiceUrl('rental', 'http://localhost:8003');
            
            foreach ($rentalIds as $rentalId) {
                // Check if order already exists for this rental
                $checkResponse = $apiClient->get(
                    'order', 
                    "/orders/rental/{$rentalId}"
                );
                
                $orderExists = false;
                if ($checkResponse['status_code'] === 200) {
                    $checkData = json_decode($checkResponse['raw_response'], true);
                    if ($checkData && $checkData['success']) {
                        $orderExists = true;
                        error_log("Order already exists for rental {$rentalId}");
                    }
                }
                
                // Create order if not exists
                if (!$orderExists) {
                    $orderResponse = $apiClient->post(
                        'order',
                        '/orders',
                        [
                            'rental_id' => $rentalId,
                            'user_id' => $transaction['user_id']
                        ],
                        ['Authorization: Bearer ' . $token]
                    );
                    
                    if ($orderResponse['status_code'] === 201) {
                        $orderData = json_decode($orderResponse['raw_response'], true);
                        $ordersCreated[] = [
                            'rental_id' => $rentalId,
                            'order_id' => $orderData['data']['order_id'] ?? null
                        ];
                        error_log("✅ Order created for rental {$rentalId}");
                    } else {
                        $orderErrors[] = "Failed to create order for rental {$rentalId}";
                        error_log("⚠️ Failed to create order for rental {$rentalId}");
                    }
                }
                
                // Update rental status to Ongoing
                try {
                    $rentalResponse = $apiClient->put(
                        'rental',
                        "/rentals/{$rentalId}/status",
                        ['status' => 'Ongoing'],
                        ['Authorization: Bearer ' . $token]
                    );
                    
                    if ($rentalResponse['status_code'] === 200) {
                        error_log("✅ Rental {$rentalId} status updated to Ongoing");
                    }
                } catch (Exception $e) {
                    error_log("Rental update error for {$rentalId}: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            error_log("Order creation error: " . $e->getMessage());
            $orderErrors[] = $e->getMessage();
        }
    }
    
    // Build response data
    $responseData = [
        'transaction_id' => $transaction['transaction_id'],
        'transaction_code' => $transaction['transaction_code'],
        'user_id' => $transaction['user_id'],
        'amount' => $transaction['amount'],
        'payment_method' => $transaction['payment_method'],
        'payment_gateway' => $transaction['payment_gateway'],
        'transaction_date' => $transaction['transaction_date'],
        'status' => 'Success',
        'rental_id' => $primaryRentalId,
        'rental_ids' => $rentalIds,
        'rental_count' => count($rentalIds)
    ];
    
    // Add order creation info
    if (!empty($ordersCreated)) {
        $responseData['order_created'] = true;
        $responseData['orders'] = $ordersCreated;
        // For backward compatibility with single rental
        $responseData['order_id'] = $ordersCreated[0]['order_id'] ?? null;
    } else {
        $responseData['order_created'] = false;
        $responseData['order_exists'] = true;
    }
    
    if (!empty($orderErrors)) {
        $responseData['order_errors'] = $orderErrors;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully',
        'data' => $responseData
    ]);
    
} catch (Exception $e) {
    error_log("Payment verification error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}