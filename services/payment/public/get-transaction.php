<?php
/**
 * ================================================
 * services/payment/public/get-transaction.php
 * ✅ FIXED: Admin can view any transaction
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
    
    // Handle both array and object
    if (is_array($decoded)) {
        $userId = $decoded['user_id'] ?? null;
        $role = $decoded['role'] ?? null;
    } else {
        $userId = $decoded->user_id ?? null;
        $role = $decoded->role ?? null;
    }
    
    if (!$userId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'User ID not found in token'
        ]);
        exit;
    }
    
    // Get transaction ID
    $transactionId = $_GET['transaction_id'] ?? null;
    
    if (!$transactionId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Transaction ID is required'
        ]);
        exit;
    }
    
    error_log("=== GET TRANSACTION: {$transactionId} | User: {$userId} | Role: " . ($role ?? 'user') . " ===");
    
    $conn = DatabaseManager::getInstance('payment');
    
    // ✅ Admin can view any transaction, user only their own
    if ($role === 'admin') {
        $sql = "SELECT * FROM Transactions WHERE transaction_id = :transaction_id";
        $params = ['transaction_id' => $transactionId];
    } else {
        $sql = "SELECT * FROM Transactions 
                WHERE transaction_id = :transaction_id AND user_id = :user_id";
        $params = [
            'transaction_id' => $transactionId,
            'user_id' => $userId
        ];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        error_log("❌ Transaction {$transactionId} not found for user {$userId}");
        
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Transaction not found'
        ]);
        exit;
    }
    
    // Parse metadata
    if (!empty($transaction['metadata'])) {
        $metadata = json_decode($transaction['metadata'], true);
        $transaction['rental_count'] = $metadata['rental_count'] ?? 1;
        $transaction['rental_ids'] = $metadata['rental_ids'] ?? [];
        $transaction['is_cart_checkout'] = $metadata['cart_checkout'] ?? false;
        $transaction['promo_code'] = $metadata['promo_code'] ?? null;
        $transaction['original_amount'] = $metadata['original_amount'] ?? $transaction['amount'];
        $transaction['discount_amount'] = $metadata['discount_amount'] ?? 0;
    }
    
    error_log("✅ Transaction found: " . json_encode([
        'id' => $transaction['transaction_id'],
        'code' => $transaction['transaction_code'],
        'user_id' => $transaction['user_id'],
        'rental_count' => $transaction['rental_count'] ?? 1
    ]));
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $transaction
    ]);
    
} catch (Exception $e) {
    error_log("=== GET TRANSACTION ERROR ===");
    error_log("Error: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}