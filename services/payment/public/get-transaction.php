<?php
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';

header('Content-Type: application/json');

try {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Missing Authorization']);
        exit;
    }
    
    $token = $matches[1];
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit;
    }
    
    // ✅ FIX: Handle both array and object
    if (is_array($decoded)) {
        $userId = $decoded['user_id'] ?? null;
    } else {
        $userId = $decoded->user_id ?? null;
    }
    
    $transactionId = $_GET['transaction_id'] ?? null;
    
    if (!$transactionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
        exit;
    }
    
    $conn = DatabaseManager::getInstance('payment');
    
    $sql = "SELECT * FROM Transactions 
            WHERE transaction_id = :transaction_id AND user_id = :user_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'transaction_id' => $transactionId,
        'user_id' => $userId
    ]);
    
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $transaction]);
    
} catch (Exception $e) {
    error_log("Get transaction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal error']);
}