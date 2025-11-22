<?php
/**
 * ================================================
 * api/admin-verify-transaction.php - DEBUG VERSION
 * ✅ Enhanced logging to find the issue
 * ================================================
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['username'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$token = $_SESSION['token'];
$data = json_decode(file_get_contents('php://input'), true);

$transactionId = $data['transaction_id'] ?? null;
$action = $data['action'] ?? null;

if (!$transactionId || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'transaction_id and action required']);
    exit;
}

require_once __DIR__ . '/../../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('rental', 'http://localhost:8003');
$apiClient->setServiceUrl('payment', 'http://localhost:8005');
$apiClient->setServiceUrl('order', 'http://localhost:8004');

try {
    error_log("=== ADMIN VERIFY TRANSACTION: {$transactionId} | Action: {$action} ===");
    
    // 1. Get transaction details with enhanced logging
    $url = "http://localhost:8005/payments/transactions/{$transactionId}";
    error_log("Calling: " . $url);
    error_log("Token: " . substr($token, 0, 20) . "...");
    
    $txnResponse = $apiClient->get('payment', "/payments/transactions/{$transactionId}", [
        'Authorization: Bearer ' . $token
    ]);
    
    error_log("Response Status: " . $txnResponse['status_code']);
    error_log("Response Body: " . substr($txnResponse['raw_response'], 0, 500));
    
    if ($txnResponse['status_code'] !== 200) {
        $errorData = json_decode($txnResponse['raw_response'], true);
        $errorMsg = $errorData['message'] ?? 'Transaction not found';
        
        error_log("❌ API Error: " . $errorMsg);
        
        throw new Exception($errorMsg);
    }
    
    $txnData = json_decode($txnResponse['raw_response'], true);
    
    if (!$txnData || !isset($txnData['success']) || !$txnData['success']) {
        error_log("❌ Invalid API response structure");
        error_log("Response: " . print_r($txnData, true));
        throw new Exception('Invalid API response');
    }
    
    $transaction = $txnData['data'];
    error_log("✅ Transaction found: " . json_encode([
        'id' => $transaction['transaction_id'],
        'code' => $transaction['transaction_code'],
        'user_id' => $transaction['user_id'],
        'amount' => $transaction['amount']
    ]));
    
    $metadata = !empty($transaction['metadata']) ? json_decode($transaction['metadata'], true) : [];
    $rentalIds = $metadata['rental_ids'] ?? [];
    
    if (empty($rentalIds)) {
        error_log("❌ No rental_ids in metadata");
        error_log("Metadata: " . print_r($metadata, true));
        throw new Exception('No rentals found in this transaction');
    }
    
    error_log("Found " . count($rentalIds) . " rentals: " . implode(', ', $rentalIds));
    error_log("Payment method: " . $transaction['payment_method']);
    
    if ($action === 'approve') {
        $rentalsUpdated = 0;
        $ordersCreated = 0;
        $failedRentals = [];
        
        // 2. Update ALL rentals to Ongoing
        foreach ($rentalIds as $rentalId) {
            try {
                error_log("Updating rental {$rentalId} to Ongoing");
                
                $updateResponse = $apiClient->request(
                    'rental',
                    "/rentals/{$rentalId}/status",
                    'PUT',
                    ['status' => 'Ongoing'],
                    [
                        'Authorization: Bearer ' . $token,
                        'Content-Type: application/json'
                    ]
                );
                
                error_log("Rental {$rentalId} update status: " . $updateResponse['status_code']);
                
                if ($updateResponse['status_code'] === 200) {
                    $rentalsUpdated++;
                    error_log("✅ Rental {$rentalId} → Ongoing");
                    
                    // 3. If COD → Create Order immediately
                    if ($transaction['payment_method'] === 'COD') {
                        error_log("Creating order for rental {$rentalId} (COD)");
                        
                        $orderPayload = [
                            'rental_id' => $rentalId,
                            'user_id' => $transaction['user_id']
                        ];
                        
                        $orderResponse = $apiClient->post('order', '/orders', $orderPayload, [
                            'Authorization: Bearer ' . $token,
                            'Content-Type: application/json'
                        ]);
                        
                        error_log("Order creation status: " . $orderResponse['status_code']);
                        
                        if ($orderResponse['status_code'] === 201) {
                            $ordersCreated++;
                            error_log("✅ Order created for rental {$rentalId}");
                        } else {
                            error_log("⚠️ Order creation failed: " . $orderResponse['raw_response']);
                        }
                    }
                } else {
                    $failedRentals[] = $rentalId;
                    error_log("❌ Failed to update rental {$rentalId}: " . $updateResponse['raw_response']);
                }
            } catch (Exception $e) {
                $failedRentals[] = $rentalId;
                error_log("❌ Exception updating rental {$rentalId}: " . $e->getMessage());
            }
        }
        
        if ($rentalsUpdated === 0) {
            throw new Exception('Failed to update any rentals');
        }
        
        $responseData = [
            'transaction_id' => $transactionId,
            'transaction_code' => $transaction['transaction_code'],
            'rentals_total' => count($rentalIds),
            'rentals_updated' => $rentalsUpdated,
            'payment_method' => $transaction['payment_method'],
            'payment_status' => $transaction['status']
        ];
        
        if ($transaction['payment_method'] === 'COD') {
            $responseData['orders_created'] = $ordersCreated;
            $message = "Đã duyệt {$rentalsUpdated}/" . count($rentalIds) . " rentals và tạo {$ordersCreated} orders (COD)";
        } else {
            $message = "Đã duyệt {$rentalsUpdated}/" . count($rentalIds) . " rentals. Chờ user thanh toán VNPay để tạo orders.";
        }
        
        if (!empty($failedRentals)) {
            $message .= "\n⚠️ Lỗi với rentals: " . implode(', ', $failedRentals);
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $responseData
        ]);
        
    } elseif ($action === 'reject') {
        $rentalsCancelled = 0;
        
        // 4. Cancel ALL rentals
        foreach ($rentalIds as $rentalId) {
            try {
                error_log("Cancelling rental {$rentalId}");
                
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
                    $rentalsCancelled++;
                    error_log("✅ Rental {$rentalId} cancelled");
                }
            } catch (Exception $e) {
                error_log("❌ Exception cancelling rental {$rentalId}: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Đã từ chối và hủy {$rentalsCancelled}/" . count($rentalIds) . " rentals",
            'data' => [
                'transaction_id' => $transactionId,
                'transaction_code' => $transaction['transaction_code'],
                'rentals_cancelled' => $rentalsCancelled
            ]
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log('=== EXCEPTION ===');
    error_log('Error: ' . $e->getMessage());
    error_log('Trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'transaction_id' => $transactionId,
            'action' => $action
        ]
    ]);
}