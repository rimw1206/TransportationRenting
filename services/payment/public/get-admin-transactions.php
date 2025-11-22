<?php
/**
 * ================================================
 * services/payment/public/get-admin-transactions.php
 * ✅ Get ALL transactions for admin (without user filter)
 * ================================================
 */

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
    
    // Handle both array and object
    if (is_array($decoded)) {
        $role = $decoded['role'] ?? null;
    } else {
        $role = $decoded->role ?? null;
    }
    
    // ✅ Check admin role                                                      
    if ($role !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Admin access required'
        ]);
        exit;
    }
    
    // Get filters from query params
    $status = $_GET['status'] ?? null;
    $method = $_GET['method'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 50)));
    
    $conn = DatabaseManager::getInstance('payment');
    
    // Base query
    $sql = "SELECT 
                transaction_id,
                user_id,
                amount,
                payment_method,
                payment_gateway,
                transaction_code,
                qr_code_url,
                transaction_date,
                status,
                metadata
            FROM Transactions
            WHERE 1=1";
    
    $params = [];
    
    // Add filters
    if ($status) {
        $sql .= " AND status = :status";
        $params['status'] = $status;
    }
    
    if ($method) {
        $sql .= " AND payment_method = :method";
        $params['method'] = $method;
    }
    
    // Count total
    $countSql = "SELECT COUNT(*) as total FROM Transactions WHERE 1=1";
    if ($status) $countSql .= " AND status = :status";
    if ($method) $countSql .= " AND payment_method = :method";
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Add pagination
    $offset = ($page - 1) * $perPage;
    $sql .= " ORDER BY transaction_date DESC LIMIT :limit OFFSET :offset";
    
    $params['limit'] = $perPage;
    $params['offset'] = $offset;
    
    // Execute query
    $stmt = $conn->prepare($sql);
    
    // Bind params properly for LIMIT/OFFSET
    foreach ($params as $key => $value) {
        if ($key === 'limit' || $key === 'offset') {
            $stmt->bindValue(":$key", $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(":$key", $value);
        }
    }
    
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse metadata for each transaction
    foreach ($transactions as &$txn) {
        if (!empty($txn['metadata'])) {
            $metadata = json_decode($txn['metadata'], true);
            $txn['rental_count'] = $metadata['rental_count'] ?? 1;
            $txn['rental_ids'] = $metadata['rental_ids'] ?? [];
            $txn['is_cart_checkout'] = $metadata['cart_checkout'] ?? false;
            $txn['promo_code'] = $metadata['promo_code'] ?? null;
            $txn['original_amount'] = $metadata['original_amount'] ?? $txn['amount'];
            $txn['discount_amount'] = $metadata['discount_amount'] ?? 0;
        }
    }
    
    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'items' => $transactions,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Admin transactions error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}