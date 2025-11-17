<?php
// ============================================
// services/customer/api/auth/change-password.php
// Change password endpoint
// ============================================

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../../../../env-bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Only PUT is supported.']);
    exit();
}

require_once __DIR__ . '/../../classes/Customer.php';
require_once __DIR__ . '/../../../../shared/classes/JWTHandler.php';

try {
    // Get token from Authorization header
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
        echo json_encode(['success' => false, 'message' => 'Authorization token required']);
        exit();
    }
    
    // Verify token
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    if ($decoded === false || !is_array($decoded)) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit();
    }
    
    if (!isset($decoded['user_id'])) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token payload']);
        exit();
    }
    
    $userId = (int)$decoded['user_id'];
    
    // Get request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data === null) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit();
    }
    
    // Validate required fields
    if (empty($data['current_password'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is required']);
        exit();
    }
    
    if (empty($data['new_password'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password is required']);
        exit();
    }
    
    // Validate new password length
    if (strlen($data['new_password']) < 6) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
        exit();
    }
    
    // Get user
    $userModel = new User();
    $user = $userModel->getById($userId);
    
    if (!$user) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Verify current password
    if (!password_verify($data['current_password'], $user['password'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }
    
    // Don't allow same password
    if (password_verify($data['new_password'], $user['password'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must be different from current password']);
        exit();
    }
    
    // Update password
    $result = $userModel->updatePassword($userId, $data['new_password']);
    
    if (!$result) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update password']);
        exit();
    }
    
    // Log the password change
    error_log("Password changed successfully for user_id: $userId");
    
    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
    exit();
    
} catch (PDOException $e) {
    error_log('PUT /auth/change-password - Database error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
    
} catch (Exception $e) {
    error_log('PUT /auth/change-password - Exception: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
    exit();
}