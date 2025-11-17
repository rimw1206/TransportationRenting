<?php
// ============================================
// services/customer/api/auth/register.php
// User registration endpoint
// ============================================

// Clear output buffers
while (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../../../../env-bootstrap.php';

// Suppress errors from output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    require_once __DIR__ . '/../../services/AuthService.php';
    
    // Get request body
    $input = file_get_contents('php://input');
    
    error_log("=== REGISTER API REQUEST ===");
    error_log("Raw body: " . $input);
    
    if (empty($input)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Request body is empty']);
        exit();
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit();
    }
    
    error_log("Parsed data: " . json_encode($data));
    
    // Validate required fields
    $required = ['username', 'password', 'name', 'email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => "Trường '{$field}' không được để trống"
            ]);
            exit();
        }
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
        exit();
    }
    
    // Validate password length
    if (strlen($data['password']) < 6) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự']);
        exit();
    }
    
    // Validate username (alphanumeric + underscore only)
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data['username'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Username chỉ được chứa chữ cái, số và dấu gạch dưới (3-50 ký tự)'
        ]);
        exit();
    }
    
    // Validate phone if provided
    if (!empty($data['phone']) && !preg_match('/^[0-9]{10,11}$/', $data['phone'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Số điện thoại không hợp lệ (10-11 số)']);
        exit();
    }
    
    // Validate birthdate if provided
    if (!empty($data['birthdate'])) {
        $birthdate = strtotime($data['birthdate']);
        if (!$birthdate) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ngày sinh không hợp lệ']);
            exit();
        }
        
        // Check age (must be at least 18)
        $age = floor((time() - $birthdate) / (365 * 24 * 60 * 60));
        if ($age < 18) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Bạn phải từ 18 tuổi trở lên để đăng ký']);
            exit();
        }
    }
    
    // Call AuthService register
    $authService = new AuthService();
    $result = $authService->register($data);
    
    error_log("Register result: " . json_encode($result));
    
    if ($result['success']) {
        ob_end_clean();
        http_response_code(201); // Created
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'user_id' => $result['user_id']
        ]);
        exit();
    } else {
        ob_end_clean();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
        exit();
    }
    
} catch (PDOException $e) {
    error_log('Register - Database error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
    exit();
    
} catch (Exception $e) {
    error_log('Register - Exception: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
    exit();
}