<?php
/**
 * ================================================
 * services/payment/public/verify.php - ENHANCED
 * Tự động tạo Order sau khi thanh toán thành công
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
    
    // Get transaction
    $stmt = $conn->prepare("
        SELECT transaction_id, rental_id, user_id, amount, status, payment_method
        FROM Transactions 
        WHERE transaction_id = ?
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
    
    // Check if already verified
    if ($transaction['status'] === 'Success') {
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
    
    // ✅ AUTO CREATE ORDER after payment success
    try {
        $apiClient = new ApiClient();
        $apiClient->setServiceUrl('order', 'http://localhost:8004');
        
        // Check if order already exists
        $checkResponse = $apiClient->get(
            'order', 
            "/orders/rental/{$transaction['rental_id']}"
        );
        
        $orderExists = false;
        if ($checkResponse['status_code'] === 200) {
            $checkData = json_decode($checkResponse['raw_response'], true);
            if ($checkData && $checkData['success']) {
                $orderExists = true;
                error_log("Order already exists for rental {$transaction['rental_id']}");
            }
        }
        
        // Create order if not exists
        if (!$orderExists) {
            $orderResponse = $apiClient->post(
                'order',
                '/orders',
                [
                    'rental_id' => $transaction['rental_id'],
                    'user_id' => $transaction['user_id']
                ],
                ['Authorization: Bearer ' . $token]
            );
            
            if ($orderResponse['status_code'] === 201) {
                $orderData = json_decode($orderResponse['raw_response'], true);
                error_log("✅ Order created: " . json_encode($orderData));
                
                $transaction['order_created'] = true;
                $transaction['order_id'] = $orderData['data']['order_id'] ?? null;
            } else {
                error_log("⚠️ Failed to create order: " . $orderResponse['raw_response']);
                $transaction['order_created'] = false;
            }
        } else {
            $transaction['order_created'] = false;
            $transaction['order_exists'] = true;
        }
        
    } catch (Exception $e) {
        error_log("Order creation error: " . $e->getMessage());
        $transaction['order_error'] = $e->getMessage();
    }
    
    // Update rental status to Ongoing
    try {
        $apiClient = new ApiClient();
        $apiClient->setServiceUrl('rental', 'http://localhost:8003');
        
        $rentalResponse = $apiClient->put(
            'rental',
            "/rentals/{$transaction['rental_id']}/status",
            ['status' => 'Ongoing'],
            ['Authorization: Bearer ' . $token]
        );
        
        if ($rentalResponse['status_code'] === 200) {
            error_log("✅ Rental status updated to Ongoing");
        }
        
    } catch (Exception $e) {
        error_log("Rental update error: " . $e->getMessage());
    }
    
    // Return success with complete transaction data
    $transaction['status'] = 'Success';
    
    // Get complete transaction data again to ensure all fields are present
    $stmt = $conn->prepare("
        SELECT transaction_id, rental_id, user_id, amount, 
               payment_method, payment_gateway, transaction_code, 
               transaction_date, status
        FROM Transactions 
        WHERE transaction_id = ?
    ");
    $stmt->execute([$transactionId]);
    $completeTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add order info to response
    if (isset($transaction['order_created'])) {
        $completeTransaction['order_created'] = $transaction['order_created'];
    }
    if (isset($transaction['order_id'])) {
        $completeTransaction['order_id'] = $transaction['order_id'];
    }
    if (isset($transaction['order_exists'])) {
        $completeTransaction['order_exists'] = $transaction['order_exists'];
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully',
        'data' => $completeTransaction
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