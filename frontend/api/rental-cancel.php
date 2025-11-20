<?php
/**
 * ================================================
 * frontend/api/rental-cancel.php
 * ✅ FIXED: Update vehicle status when cancelling
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
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');

try {
    error_log("=== CANCELLING RENTAL #{$rentalId} ===");
    
    // 1. Get rental details first
    $rentalResponse = $apiClient->get('rental', "/rentals/{$rentalId}", [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($rentalResponse['status_code'] !== 200) {
        throw new Exception('Rental not found');
    }
    
    $rentalData = json_decode($rentalResponse['raw_response'], true);
    $rental = $rentalData['data']['rental'] ?? $rentalData['data'];
    
    // Verify ownership
    if ($rental['user_id'] != $user['user_id']) {
        throw new Exception('You can only cancel your own rentals');
    }
    
    // Check if can cancel
    if (!in_array($rental['status'], ['Pending', 'Ongoing'])) {
        throw new Exception('Cannot cancel rental with status: ' . $rental['status']);
    }
    
    $vehicleId = $rental['vehicle_id'];
    error_log("Rental vehicle_id: {$vehicleId}");
    
    // 2. Cancel rental
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
    
    if ($cancelResponse['status_code'] !== 200) {
        throw new Exception('Failed to cancel rental');
    }
    
    // 3. ✅ Update vehicle status back to Available
    error_log("=== Updating vehicle #{$vehicleId} status to Available ===");
    
    $vehicleUpdateResponse = $apiClient->request(
        'vehicle',
        "/units/{$vehicleId}/status",
        'PUT',
        ['status' => 'Available'],
        ['Content-Type: application/json']
    );
    
    error_log("Vehicle update response: " . $vehicleUpdateResponse['status_code']);
    
    if ($vehicleUpdateResponse['status_code'] === 200) {
        error_log("✅ Vehicle status updated to Available");
    } else {
        error_log("⚠️ Failed to update vehicle status: " . $vehicleUpdateResponse['raw_response']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Rental cancelled successfully',
        'data' => [
            'rental_id' => $rentalId,
            'status' => 'Cancelled',
            'vehicle_updated' => $vehicleUpdateResponse['status_code'] === 200
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