<?php
// frontend/api/promo-validate.php - Validate promo code
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($data['code'] ?? ''));

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã khuyến mãi']);
    exit;
}

// Connect to Rental Service to validate promo
require_once __DIR__ . '/../../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');

try {
    $response = $apiClient->get('rental', '/promotions/validate?code=' . urlencode($code));
    
    if ($response['status_code'] === 200) {
        $result = json_decode($response['raw_response'], true);
        
        if ($result['success']) {
            $promo = $result['data'];
            
            // Check if promo is active and within date range
            $now = new DateTime();
            $validFrom = new DateTime($promo['valid_from']);
            $validTo = new DateTime($promo['valid_to']);
            
            if (!$promo['active']) {
                echo json_encode(['success' => false, 'message' => 'Mã khuyến mãi không còn hiệu lực']);
                exit;
            }
            
            if ($now < $validFrom || $now > $validTo) {
                echo json_encode(['success' => false, 'message' => 'Mã khuyến mãi đã hết hạn']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Mã khuyến mãi hợp lệ',
                'discount' => $promo['discount_percent'],
                'description' => $promo['description']
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Mã khuyến mãi không tồn tại']);
    
} catch (Exception $e) {
    error_log('Promo validation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối đến hệ thống']);
}