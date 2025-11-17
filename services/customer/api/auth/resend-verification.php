<?php
/**
 * services/customer/api/auth/resend-verification.php
 * Endpoint để gửi lại email verification
 */

while (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../../../../env-bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    require_once __DIR__ . '/../../services/AuthService.php';
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (empty($data['email'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email không được để trống']);
        exit();
    }
    
    $authService = new AuthService();
    $result = $authService->resendVerification($data['email']);
    
    if ($result['success']) {
        ob_end_clean();
        http_response_code(200);
        echo json_encode($result);
        exit();
    } else {
        ob_end_clean();
        http_response_code(400);
        echo json_encode($result);
        exit();
    }
    
} catch (Exception $e) {
    error_log('Resend verification error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
    exit();
}