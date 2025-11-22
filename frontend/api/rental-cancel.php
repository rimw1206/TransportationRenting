<?php
/**
 * ================================================
 * frontend/api/rental-cancel.php
 * ✅ FIXED: NO vehicle status update when cancelling
 * Vehicle status is managed separately from rental status
 * ================================================
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'];

$data = json_decode(file_get_contents('php://input'), true);
$rentalId = $data['rental_id'] ?? null;

if (!$rentalId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'rental_id is required']);
    exit;
}

require_once __DIR__ . '/../../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');

try {
    error_log("=== CANCELLING RENTAL #{$rentalId} ===");
    
    // 1. Get rental details first to verify ownership
    $rentalResponse = $apiClient->get('rental', "/rentals/{$rentalId}", [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($rentalResponse['status_code'] !== 200) {
        throw new Exception('Không tìm thấy đơn thuê');
    }
    
    $rentalData = json_decode($rentalResponse['raw_response'], true);
    $rental = $rentalData['data']['rental'] ?? $rentalData['data'];
    
    // Verify ownership
    if ($rental['user_id'] != $user['user_id']) {
        throw new Exception('Bạn chỉ có thể hủy đơn thuê của mình');
    }
    
    // Check if can cancel
    if ($rental['status'] === 'Completed') {
        throw new Exception('Không thể hủy đơn đã hoàn thành');
    }
    
    if ($rental['status'] === 'Cancelled') {
        throw new Exception('Đơn này đã bị hủy trước đó');
    }
    
    error_log("Rental status: {$rental['status']}, vehicle_id: {$rental['vehicle_id']}");
    
    // 2. Cancel rental via Rental Service
    // ✅ The rental service will ONLY update rental status
    // ✅ NO vehicle status update is needed
    $cancelResponse = $apiClient->request(
        'rental',
        "/rentals/{$rentalId}/cancel",
        'PUT',
        [],
        [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    );
    
    error_log("Cancel response: " . $cancelResponse['status_code']);
    error_log("Cancel body: " . $cancelResponse['raw_response']);
    
    if ($cancelResponse['status_code'] !== 200) {
        $errorData = json_decode($cancelResponse['raw_response'], true);
        $errorMsg = $errorData['message'] ?? 'Không thể hủy đơn thuê';
        throw new Exception($errorMsg);
    }
    
    $cancelData = json_decode($cancelResponse['raw_response'], true);
    
    // ✅ Success - NO vehicle status update needed
    // Vehicle availability is determined by checking Rentals table
    // not by Vehicle status field
    
    error_log("✅ Rental #{$rentalId} cancelled successfully");
    
    echo json_encode([
        'success' => true,
        'message' => 'Hủy đơn thuê thành công',
        'data' => [
            'rental_id' => $rentalId,
            'status' => 'Cancelled',
            'note' => 'Vehicle availability is managed via rental bookings'
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Cancel rental error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}