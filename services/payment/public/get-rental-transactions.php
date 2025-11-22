<?php
/**
 * ================================================
 * services/payment/public/get-rental-transactions.php
 * GET /payments/rental/{rental_id}/transactions
 * ✅ Returns full transaction data for a rental
 * ================================================
 */

require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';

header('Content-Type: application/json');

try {
    // Get Authorization header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Missing Authorization header'
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
    
    // Extract user_id
    if (is_array($decoded)) {
        $userId = $decoded['user_id'] ?? null;
    } else {
        $userId = $decoded->user_id ?? null;
    }
    
    if (!$userId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'User ID not found in token'
        ]);
        exit;
    }
    
    // Get rental_id from URL
    $rentalId = $_GET['rental_id'] ?? null;
    
    if (!$rentalId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Rental ID is required'
        ]);
        exit;
    }
    
    error_log("=== GET RENTAL TRANSACTIONS: Rental #{$rentalId} | User #{$userId} ===");
    
    $conn = DatabaseManager::getInstance('payment');
    
    // Query transactions via RentalPayments junction table
    $sql = "
        SELECT 
            t.transaction_id,
            t.user_id,
            t.amount as total_amount,
            t.payment_method,
            t.payment_gateway,
            t.transaction_code,
            t.qr_code_url,
            t.transaction_date,
            t.status as payment_status,
            t.metadata,
            rp.amount as rental_portion
        FROM Transactions t
        INNER JOIN RentalPayments rp ON t.transaction_id = rp.transaction_id
        WHERE rp.rental_id = :rental_id AND t.user_id = :user_id
        ORDER BY t.transaction_date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'rental_id' => $rentalId,
        'user_id' => $userId
    ]);
    
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        error_log("⚠️ No transactions found for rental #{$rentalId}");
        
        // Return empty array instead of error (not all rentals have payments yet)
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'rental_id' => $rentalId,
                'transactions' => [],
                'total' => 0
            ]
        ]);
        exit;
    }
    
    // Enrich with metadata
    foreach ($transactions as &$txn) {
        if (!empty($txn['metadata'])) {
            $metadata = json_decode($txn['metadata'], true);
            $txn['rental_count'] = $metadata['rental_count'] ?? 1;
            $txn['is_cart_checkout'] = $metadata['cart_checkout'] ?? false;
            $txn['promo_code'] = $metadata['promo_code'] ?? null;
            $txn['original_amount'] = $metadata['original_amount'] ?? $txn['total_amount'];
            $txn['discount_amount'] = $metadata['discount_amount'] ?? 0;
        }
        
        // Add aliases for consistency
        $txn['status'] = $txn['payment_status'];
        $txn['amount'] = $txn['total_amount'];
    }
    
    error_log("✅ Found " . count($transactions) . " transaction(s) for rental #{$rentalId}");
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'rental_id' => $rentalId,
            'transactions' => $transactions,
            'total' => count($transactions)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("=== GET RENTAL TRANSACTIONS ERROR ===");
    error_log("Error: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}