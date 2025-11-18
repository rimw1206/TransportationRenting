<?php
// frontend/api/promo-validate.php - Validate promo code FIXED
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($data['code'] ?? ''));

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã khuyến mãi']);
    exit;
}

// Connect to Rental Service to validate promo
require_once __DIR__ . '/../../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');

try {
    // Call Rental API to validate
    $response = $apiClient->get('rental', '/promotions/validate?code=' . urlencode($code));
    
    // Log for debugging
    error_log("=== Promo Validate ===");
    error_log("Code: {$code}");
    error_log("Status: {$response['status_code']}");
    error_log("Response: {$response['raw_response']}");
    
    if ($response['status_code'] === 200) {
        $result = json_decode($response['raw_response'], true);
        
        if ($result && isset($result['success']) && $result['success'] && isset($result['data'])) {
            // ✅ API trả về data trong result['data']
            $promo = $result['data'];
            
            // Validate promo có đủ thông tin không
            if (!isset($promo['code']) || !isset($promo['discount_percent'])) {
                error_log("ERROR: Missing promo fields - " . json_encode($promo));
                echo json_encode([
                    'success' => false,
                    'message' => 'Dữ liệu khuyến mãi không hợp lệ'
                ]);
                exit;
            }
            
            // Return success với format đơn giản
            echo json_encode([
                'success' => true,
                'message' => 'Mã khuyến mãi hợp lệ',
                'discount' => floatval($promo['discount_percent']),
                'description' => $promo['description'] ?? ''
            ]);
            exit;
            
        } else {
            // API returned success=false
            $message = isset($result['message']) ? $result['message'] : 'Mã khuyến mãi không hợp lệ';
            error_log("ERROR: API returned success=false - {$message}");
            
            echo json_encode([
                'success' => false,
                'message' => $message
            ]);
            exit;
        }
        
    } elseif ($response['status_code'] === 400) {
        // Bad request from API
        $result = json_decode($response['raw_response'], true);
        $message = isset($result['message']) ? $result['message'] : 'Mã khuyến mãi không hợp lệ';
        
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
        
    } else {
        // Other HTTP errors
        error_log("ERROR: HTTP Status {$response['status_code']}");
        
        echo json_encode([
            'success' => false,
            'message' => 'Không thể kết nối đến hệ thống khuyến mãi (HTTP ' . $response['status_code'] . ')'
        ]);
        exit;
    }
    
} catch (Exception $e) {
    error_log('=== Exception ===');
    error_log('Message: ' . $e->getMessage());
    error_log('File: ' . $e->getFile());
    error_log('Line: ' . $e->getLine());
    error_log('Trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
    exit;
}