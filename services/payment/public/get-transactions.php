<?php
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';

header('Content-Type: application/json');

try {
    // Get token from header
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
    
    // ✅ FIX: Handle both array and object
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
    
    // Get filters from query params
    $rentalId = $_GET['rental_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $method = $_GET['method'] ?? null;
    
    // Connect to database
    $conn = DatabaseManager::getInstance('payment');
    
    // Build query
    $sql = "SELECT 
                transaction_id,
                rental_id,
                user_id,
                amount,
                payment_method,
                payment_gateway,
                transaction_code,
                qr_code_url,
                transaction_date,
                status
            FROM Transactions
            WHERE user_id = :user_id";
    
    $params = ['user_id' => $userId];
    
    if ($rentalId) {
        $sql .= " AND rental_id = :rental_id";
        $params['rental_id'] = $rentalId;
    }
    
    if ($status) {
        $sql .= " AND status = :status";
        $params['status'] = $status;
    }
    
    if ($method) {
        $sql .= " AND payment_method = :method";
        $params['method'] = $method;
    }
    
    $sql .= " ORDER BY transaction_date DESC";
    
    // Execute query
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'items' => $transactions,
            'total' => count($transactions)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Payment transactions error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}