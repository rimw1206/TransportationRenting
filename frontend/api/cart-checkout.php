<?php
// frontend/api/cart-checkout.php - UPDATED VERSION WITH PROMO
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

if (empty($_SESSION['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Giỏ hàng trống']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$promoCode = $data['promo_code'] ?? null;

require_once __DIR__ . '/../../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
$apiClient->setServiceUrl('rental', 'http://localhost:8003');

$token = $_SESSION['token'] ?? '';
$userId = $_SESSION['user']['user_id'];

try {
    $successfulRentals = [];
    $failedRentals = [];
    $totalOriginalCost = 0;
    $totalDiscount = 0;
    $promoInfo = null;
    
    // Validate promo code if provided
    if ($promoCode) {
        $response = $apiClient->get('rental', '/promotions/validate?code=' . urlencode($promoCode));
        
        if ($response['status_code'] === 200) {
            $result = json_decode($response['raw_response'], true);
            
            if ($result['success']) {
                $promoInfo = $result['data'];
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
                exit;
            }
        }
    }
    
    // Process each cart item
    foreach ($_SESSION['cart'] as $cartItem) {
        try {
            // Get vehicle details
            $vehicleResponse = $apiClient->get('vehicle', '/' . $cartItem['catalog_id']);
            
            if ($vehicleResponse['status_code'] !== 200) {
                $failedRentals[] = [
                    'vehicle_id' => $cartItem['catalog_id'],
                    'reason' => 'Không thể lấy thông tin xe'
                ];
                continue;
            }
            
            $vehicleData = json_decode($vehicleResponse['raw_response'], true);
            if (!$vehicleData['success']) {
                $failedRentals[] = [
                    'vehicle_id' => $cartItem['catalog_id'],
                    'reason' => 'Xe không tồn tại'
                ];
                continue;
            }
            
            $vehicle = $vehicleData['data'];
            
            // Calculate cost
            $start = new DateTime($cartItem['start_time']);
            $end = new DateTime($cartItem['end_time']);
            $days = $end->diff($start)->days;
            if ($days < 1) $days = 1;
            
            $itemCost = $days * $vehicle['daily_rate'] * $cartItem['quantity'];
            $totalOriginalCost += $itemCost;
            
            // Apply promo discount if available
            $finalCost = $itemCost;
            if ($promoInfo) {
                $discount = round($itemCost * $promoInfo['discount_percent'] / 100, 2);
                $totalDiscount += $discount;
                $finalCost = $itemCost - $discount;
            }
            
            // Create rental via API
            $rentalData = [
                'user_id' => $userId,
                'vehicle_id' => $cartItem['catalog_id'],
                'start_time' => $cartItem['start_time'],
                'end_time' => $cartItem['end_time'],
                'pickup_location' => $cartItem['pickup_location'],
                'dropoff_location' => $cartItem['pickup_location'],
                'total_cost' => $finalCost,
                'status' => 'Pending'
            ];
            
            $rentalResponse = $apiClient->post('rental', '', $rentalData, [
                'Authorization: Bearer ' . $token
            ]);
            
            if ($rentalResponse['status_code'] === 201) {
                $rentalResult = json_decode($rentalResponse['raw_response'], true);
                
                if ($rentalResult['success']) {
                    $successfulRentals[] = [
                        'rental_id' => $rentalResult['data']['rental_id'],
                        'vehicle_id' => $cartItem['catalog_id'],
                        'original_cost' => $itemCost,
                        'discount' => $promoInfo ? ($itemCost - $finalCost) : 0,
                        'final_cost' => $finalCost
                    ];
                } else {
                    $failedRentals[] = [
                        'vehicle_id' => $cartItem['catalog_id'],
                        'reason' => $rentalResult['message']
                    ];
                }
            } else {
                $failedRentals[] = [
                    'vehicle_id' => $cartItem['catalog_id'],
                    'reason' => 'Lỗi khi tạo đơn thuê'
                ];
            }
            
        } catch (Exception $e) {
            error_log('Checkout item error: ' . $e->getMessage());
            $failedRentals[] = [
                'vehicle_id' => $cartItem['catalog_id'],
                'reason' => 'Lỗi không xác định'
            ];
        }
    }
    
    // Clear cart if any rental succeeded
    if (!empty($successfulRentals)) {
        $_SESSION['cart'] = [];
    }
    
    $totalFinal = $totalOriginalCost - $totalDiscount;
    
    $response = [
        'success' => !empty($successfulRentals),
        'message' => count($successfulRentals) . ' đơn đặt xe thành công' . 
                    (!empty($failedRentals) ? ', ' . count($failedRentals) . ' thất bại' : ''),
        'data' => [
            'successful' => $successfulRentals,
            'failed' => $failedRentals,
            'total_original' => $totalOriginalCost,
            'total_discount' => $totalDiscount,
            'total_final' => $totalFinal,
            'promo_applied' => $promoInfo ? [
                'code' => $promoCode,
                'discount_percent' => $promoInfo['discount_percent']
            ] : null
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Checkout error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi xử lý đơn hàng: ' . $e->getMessage()
    ]);
}