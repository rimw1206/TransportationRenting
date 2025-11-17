<?php
// ============================================
// services/customer/api/profile/delete.php
// Delete user account endpoint
// ============================================

// Clear output buffers
while (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../../../../env-bootstrap.php';

// Suppress errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

// Only DELETE allowed
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../../classes/Customer.php';
require_once __DIR__ . '/../../../../shared/classes/JWTHandler.php';

try {
    // Get token
    $token = null;
    $headers = getallheaders();
    
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                $token = trim($matches[1]);
            } else {
                $token = trim($value);
            }
            break;
        }
    }
    
    if (!$token) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authorization required']);
        exit();
    }
    
    // Verify token
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    if (!$decoded || !is_array($decoded) || !isset($decoded['user_id'])) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit();
    }
    
    $userId = (int)$decoded['user_id'];
    
    // Prevent deleting admin account (user_id = 1)
    if ($userId === 1) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Không thể xóa tài khoản admin'
        ]);
        exit();
    }
    
    // Optional: Require password confirmation
    // Get request body if any
    $input = file_get_contents('php://input');
    $data = $input ? json_decode($input, true) : [];
    
    // If password confirmation is sent, verify it
    if (!empty($data['password'])) {
        $userModel = new User();
        $user = $userModel->getById($userId);
        
        if (!$user) {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        
        if (!password_verify($data['password'], $user['password'])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Mật khẩu không chính xác'
            ]);
            exit();
        }
    }
    
    // Delete user
    $userModel = new User();
    $result = $userModel->delete($userId);
    
    if (!$result) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete account']);
        exit();
    }
    
    // Log deletion
    error_log("Account deleted: user_id=$userId");
    
    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Tài khoản đã được xóa thành công'
    ]);
    exit();
    
} catch (PDOException $e) {
    error_log('DELETE /profile - Database error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
    
} catch (Exception $e) {
    error_log('DELETE /profile - Exception: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal error']);
    exit();
}