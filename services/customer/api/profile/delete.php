<?php
require_once __DIR__ . '/../../../../env-bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Only DELETE is supported.']);
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
    
    if ($userId === 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot delete admin account']);
        exit();
    }
    
    $userModel = new User();
    $result = $userModel->delete($userId);
    
    if ($result) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete account']);
    }
    
} catch (PDOException $e) {
    error_log('DELETE /profile - Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('DELETE /profile - Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}