FILE: services/customer/api/profile/update.php
============================================
<?php
require_once __DIR__ . '/../../../../env-bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Only PUT is supported.']);
    exit();
}

require_once __DIR__ . '/../../classes/Customer.php';
require_once __DIR__ . '/../../../../shared/classes/JWTHandler.php';

try {
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
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authorization token is required']);
        exit();
    }
    
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    if ($decoded === false || !is_array($decoded)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit();
    }
    
    if (!isset($decoded['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token payload']);
        exit();
    }
    
    $userId = (int)$decoded['user_id'];
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit();
    }
    
    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No data provided for update']);
        exit();
    }
    
    $allowedFields = ['name', 'email', 'phone', 'birthdate'];
    $updateData = [];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    if (empty($updateData)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
        exit();
    }
    
    $userModel = new User();
    $updatedUser = $userModel->update($userId, $updateData);
    
    if (!$updatedUser) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        exit();
    }
    
    unset($updatedUser['password']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'data' => $updatedUser
    ]);
    
} catch (PDOException $e) {
    error_log('PUT /profile - Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('PUT /profile - Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}