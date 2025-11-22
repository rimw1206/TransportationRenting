<?php
/**
 * ================================================
 * frontend/api/transaction-cancel.php
 * ✅ Cancel ALL rentals in a transaction
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
$transactionId = $data['transaction_id'] ?? null;

if (!$transactionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'transaction_id is required']);
    exit;
}

require_once __DIR__ . '/../../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');
$apiClient->setServiceUrl('payment', 'http://localhost:8005');

try {
    error_log("=== CANCELLING TRANSACTION #{$transactionId} ===");
    
    // 1. Get transaction details
    $txnResponse = $apiClient->get('payment', "/payments/transactions/{$transactionId}", [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($txnResponse['status_code'] !== 200) {
        throw new Exception('Không tìm thấy giao dịch');
    }
    
    $txnData = json_decode($txnResponse['raw_response'], true);
    $transaction = $txnData['data'];
    
    // Verify ownership
    if ($transaction['user_id'] != $user['user_id']) {
        throw new Exception('Bạn chỉ có thể hủy giao dịch của mình');
    }
    
    // Check payment status - can't cancel if already paid
    if ($transaction['status'] === 'Success') {
        throw new Exception('Không thể hủy giao dịch đã thanh toán. Vui lòng liên hệ hỗ trợ để được hoàn tiền.');
    }
    
    // Parse metadata to get rental_ids
    $metadata = !empty($transaction['metadata']) ? json_decode($transaction['metadata'], true) : [];
    $rentalIds = $metadata['rental_ids'] ?? [];
    
    if (empty($rentalIds)) {
        throw new Exception('Không tìm thấy đơn thuê trong giao dịch này');
    }
    
    error_log("Transaction has " . count($rentalIds) . " rentals: " . implode(', ', $rentalIds));
    
    // 2. Cancel each rental
    $cancelledRentals = [];
    $failedRentals = [];
    
    foreach ($rentalIds as $rentalId) {
        try {
            // Get rental to check status
            $rentalResponse = $apiClient->get('rental', "/rentals/{$rentalId}", [
                'Authorization: Bearer ' . $token
            ]);
            
            if ($rentalResponse['status_code'] !== 200) {
                $failedRentals[] = [
                    'rental_id' => $rentalId,
                    'reason' => 'Không tìm thấy'
                ];
                continue;
            }
            
            $rentalData = json_decode($rentalResponse['raw_response'], true);
            $rental = $rentalData['data']['rental'] ?? $rentalData['data'];
            
            // Skip if already cancelled or completed
            if ($rental['status'] === 'Cancelled') {
                $cancelledRentals[] = $rentalId; // Already cancelled, count as success
                continue;
            }
            
            if ($rental['status'] === 'Completed') {
                $failedRentals[] = [
                    'rental_id' => $rentalId,
                    'reason' => 'Đã hoàn thành'
                ];
                continue;
            }
            
            // Cancel the rental
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
            
            if ($cancelResponse['status_code'] === 200) {
                $cancelledRentals[] = $rentalId;
                error_log("✅ Rental #{$rentalId} cancelled");
            } else {
                $errorData = json_decode($cancelResponse['raw_response'], true);
                $failedRentals[] = [
                    'rental_id' => $rentalId,
                    'reason' => $errorData['message'] ?? 'Lỗi không xác định'
                ];
                error_log("❌ Failed to cancel rental #{$rentalId}");
            }
            
        } catch (Exception $e) {
            $failedRentals[] = [
                'rental_id' => $rentalId,
                'reason' => $e->getMessage()
            ];
        }
    }
    
    // 3. Update transaction status if all rentals cancelled
    if (count($cancelledRentals) === count($rentalIds)) {
        // All rentals cancelled - update transaction to Failed/Cancelled
        try {
            $updateResponse = $apiClient->request(
                'payment',
                "/payments/transactions/{$transactionId}/status",
                'PUT',
                ['status' => 'Failed'],
                [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ]
            );
            
            error_log("Transaction status update: " . $updateResponse['status_code']);
        } catch (Exception $e) {
            error_log("Warning: Could not update transaction status: " . $e->getMessage());
        }
    }
    
    // 4. Return result
    $allSuccess = empty($failedRentals);
    $message = $allSuccess 
        ? 'Hủy toàn bộ đơn thuê thành công' 
        : 'Đã hủy ' . count($cancelledRentals) . '/' . count($rentalIds) . ' đơn thuê';
    
    echo json_encode([
        'success' => $allSuccess,
        'message' => $message,
        'data' => [
            'transaction_id' => $transactionId,
            'total_rentals' => count($rentalIds),
            'cancelled_rentals' => $cancelledRentals,
            'failed_rentals' => $failedRentals
        ]
    ]);
    
    error_log("=== TRANSACTION CANCEL COMPLETE ===");
    error_log("Cancelled: " . count($cancelledRentals) . ", Failed: " . count($failedRentals));
    
} catch (Exception $e) {
    error_log('Cancel transaction error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}