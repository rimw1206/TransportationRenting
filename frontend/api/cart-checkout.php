<?php
// ========================================
// frontend/api/cart-checkout.php - FIXED VERSION
// Path: TransportationRenting/frontend/api/cart-checkout.php
// URL: http://localhost/api/cart-checkout.php
// ========================================
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (empty($_SESSION['cart'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Giỏ hàng trống']);
    exit;
}

require_once __DIR__ . '/../../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
$apiClient->setServiceUrl('rental', 'http://localhost:8003');

$token = $_SESSION['token'] ?? '';
$userId = $_SESSION['user']['user_id'];

$successfulRentals = [];
$failedRentals = [];

// Process each cart item
foreach ($_SESSION['cart'] as $cartItem) {
    try {
        // 1. Check availability - FIX: Use correct endpoint /vehicles/{id}
        $vehicleResponse = $apiClient->get('vehicle', '/vehicles/' . $cartItem['catalog_id']);
        
        if ($vehicleResponse['status_code'] !== 200) {
            $failedRentals[] = [
                'catalog_id' => $cartItem['catalog_id'],
                'reason' => 'Không thể kiểm tra xe'
            ];
            continue;
        }
        
        $vehicleData = json_decode($vehicleResponse['raw_response'], true);
        if (!$vehicleData['success']) {
            $failedRentals[] = [
                'catalog_id' => $cartItem['catalog_id'],
                'reason' => 'Không tìm thấy xe'
            ];
            continue;
        }
        
        $vehicle = $vehicleData['data'];
        
        // Check if enough vehicles available
        if (($vehicle['available_count'] ?? 0) < $cartItem['quantity']) {
            $failedRentals[] = [
                'catalog_id' => $cartItem['catalog_id'],
                'reason' => "Chỉ còn " . ($vehicle['available_count'] ?? 0) . " xe"
            ];
            continue;
        }
        
        // 2. Reserve units - FIX: Use correct endpoint
        $reserveResponse = $apiClient->post('vehicle', '/vehicles/reserve', [
            'catalog_id' => $cartItem['catalog_id'],
            'quantity' => $cartItem['quantity']
        ]);
        
        if ($reserveResponse['status_code'] !== 200) {
            $failedRentals[] = [
                'catalog_id' => $cartItem['catalog_id'],
                'reason' => 'Không thể đặt xe'
            ];
            continue;
        }
        
        $reserveData = json_decode($reserveResponse['raw_response'], true);
        if (!$reserveData['success']) {
            $failedRentals[] = [
                'catalog_id' => $cartItem['catalog_id'],
                'reason' => $reserveData['message'] ?? 'Không thể đặt xe'
            ];
            continue;
        }
        
        $reservedUnits = $reserveData['data']['reserved_units'] ?? [];
        
        // 3. Create rental for each unit
        foreach ($reservedUnits as $unit) {
            // Calculate days
            $start = new DateTime($cartItem['start_time']);
            $end = new DateTime($cartItem['end_time']);
            $days = max(1, $end->diff($start)->days);
            $totalCost = $days * $vehicle['daily_rate'];
            
            $rentalData = [
                'user_id' => $userId,
                'vehicle_id' => $unit['unit_id'],
                'start_time' => $cartItem['start_time'],
                'end_time' => $cartItem['end_time'],
                'pickup_location' => $cartItem['pickup_location'],
                'dropoff_location' => $cartItem['pickup_location'], // Same as pickup for now
                'total_cost' => $totalCost
            ];
            
            // Call rental API with auth header
            $rentalResponse = $apiClient->post('rental', '/rentals', $rentalData, [
                'Authorization' => 'Bearer ' . $token
            ]);
            
            if ($rentalResponse['status_code'] === 201 || $rentalResponse['status_code'] === 200) {
                $rentalResult = json_decode($rentalResponse['raw_response'], true);
                
                $successfulRentals[] = [
                    'catalog_id' => $cartItem['catalog_id'],
                    'unit_id' => $unit['unit_id'],
                    'license_plate' => $unit['license_plate'],
                    'rental_id' => $rentalResult['data']['rental_id'] ?? null,
                    'total_cost' => $totalCost
                ];
                
                // Update unit status to Rented
                $apiClient->put('vehicle', '/vehicles/' . $unit['unit_id'] . '/status', [
                    'status' => 'Rented'
                ]);
            } else {
                error_log('Failed to create rental for unit ' . $unit['unit_id'] . ': ' . $rentalResponse['raw_response']);
                // Don't add to failed array as reservation already happened
            }
        }
        
    } catch (Exception $e) {
        error_log('Checkout error: ' . $e->getMessage());
        $failedRentals[] = [
            'catalog_id' => $cartItem['catalog_id'],
            'reason' => 'Lỗi hệ thống: ' . $e->getMessage()
        ];
    }
}

// Clear cart if all successful
if (empty($failedRentals)) {
    $_SESSION['cart'] = [];
    echo json_encode([
        'success' => true,
        'message' => 'Đặt ' . count($successfulRentals) . ' xe thành công!',
        'rentals' => $successfulRentals
    ]);
} else {
    // Partial success - remove successful items from cart
    if (!empty($successfulRentals)) {
        $successfulCatalogIds = array_unique(array_column($successfulRentals, 'catalog_id'));
        $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($successfulCatalogIds) {
            return !in_array($item['catalog_id'], $successfulCatalogIds);
        });
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
    
    http_response_code(207); // Multi-Status
    echo json_encode([
        'success' => false,
        'message' => count($successfulRentals) . ' xe đặt thành công, ' . count($failedRentals) . ' xe thất bại',
        'successful' => $successfulRentals,
        'failed' => $failedRentals
    ]);
}