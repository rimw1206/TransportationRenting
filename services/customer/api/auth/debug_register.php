<?php
/**
 * TEMPORARY DEBUG FILE - Thay thế services/customer/api/auth/register.php
 * Để kiểm tra flow và tìm lỗi
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Log mọi thứ
error_log("=== REGISTER API DEBUG START ===");
error_log("Time: " . date('Y-m-d H:i:s'));
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

try {
    // Get raw input
    $rawBody = file_get_contents('php://input');
    error_log("Raw body length: " . strlen($rawBody));
    error_log("Raw body: " . $rawBody);
    
    if (empty($rawBody)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Request body is empty']);
        exit;
    }
    
    $data = json_decode($rawBody, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit;
    }
    
    error_log("Parsed data: " . json_encode($data));
    
    // Validate required fields
    $required = ['username', 'password', 'name', 'email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field '{$field}' is required"]);
            exit;
        }
    }
    
    // Load dependencies
    require_once __DIR__ . '/../../../../shared/classes/DatabaseManager.php';
    $db = DatabaseManager::getConnection('customer');
    
    error_log("Database connected successfully");
    
    // === CRITICAL DEBUG: Check if username exists ===
    error_log("=== CHECKING USERNAME: {$data['username']} ===");
    
    // Method 1: COUNT(*)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM Users WHERE username = ?");
    $stmt->execute([$data['username']]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Method 1 (COUNT): " . $count['count']);
    
    // Method 2: fetchColumn
    $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE username = ?");
    $stmt->execute([$data['username']]);
    $countColumn = $stmt->fetchColumn();
    error_log("Method 2 (fetchColumn): " . $countColumn);
    
    // Method 3: Actual row
    $stmt = $db->prepare("SELECT user_id, username FROM Users WHERE username = ?");
    $stmt->execute([$data['username']]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Method 3 (fetch): " . ($existingUser ? "Found user_id={$existingUser['user_id']}" : "Not found"));
    
    // Method 4: All users (to see what's in DB)
    $stmt = $db->query("SELECT user_id, username, email FROM Users ORDER BY user_id DESC LIMIT 10");
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Recent users in DB: " . json_encode($allUsers));
    
    // Decision
    if ($countColumn > 0) {
        error_log("❌ DUPLICATE USERNAME DETECTED");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Tên đăng nhập đã tồn tại',
            'debug' => [
                'username' => $data['username'],
                'count' => $countColumn,
                'existing_user' => $existingUser
            ]
        ]);
        exit;
    }
    
    // Check email
    error_log("=== CHECKING EMAIL: {$data['email']} ===");
    $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $emailCount = $stmt->fetchColumn();
    error_log("Email count: " . $emailCount);
    
    if ($emailCount > 0) {
        error_log("❌ DUPLICATE EMAIL DETECTED");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email đã được sử dụng'
        ]);
        exit;
    }
    
    // All checks passed - proceed with registration
    error_log("✅ No duplicates found. Proceeding with registration...");
    
    // Load AuthService
    require_once __DIR__ . '/../../services/AuthService.php';
    $authService = new AuthService();
    $result = $authService->register($data);
    
    error_log("AuthService result: " . json_encode($result));
    
    if ($result['success']) {
        http_response_code(201);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("❌ EXCEPTION: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

error_log("=== REGISTER API DEBUG END ===");