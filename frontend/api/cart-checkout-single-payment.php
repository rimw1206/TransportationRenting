<?php
/**
 * ================================================
 * frontend/api/cart-checkout-single-payment.php
 * ✅ FIXED: Proper discount calculation & storage
 * ================================================
 */

session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Validate session
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Giỏ hàng trống']);
    exit;
}

$user = $_SESSION['user'];
$token = $_SESSION['token'];

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body']);
    exit;
}

$promoCode = $data['promo_code'] ?? null;
$paymentMethodId = $data['payment_method_id'] ?? null;

error_log("=== CART CHECKOUT START ===");
error_log("User ID: " . $user['user_id']);
error_log("Cart Items: " . count($_SESSION['cart']));
error_log("Payment Method ID: " . ($paymentMethodId ?? 'NULL'));
error_log("Promo Code: " . ($promoCode ?? 'none'));

if (!$paymentMethodId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng chọn phương thức thanh toán']);
    exit;
}

require_once __DIR__ . '/../../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('customer', 'http://localhost:8001');
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
$apiClient->setServiceUrl('rental', 'http://localhost:8003');
$apiClient->setServiceUrl('payment', 'http://localhost:8005');

try {
    $createdRentals = [];
    $totalAmount = 0;
    $totalDiscount = 0;
    $promoApplied = null;
    $promoDiscountPercent = 0;
    
    // ===== 1. VALIDATE PAYMENT METHOD =====
    error_log("Validating payment method #$paymentMethodId...");
    
    try {
        $pmCheckResponse = $apiClient->get('customer', '/payment-methods', [
            'Authorization: Bearer ' . $token
        ]);
        
        if ($pmCheckResponse['status_code'] !== 200) {
            throw new Exception('Không thể kết nối Customer Service');
        }
        
        $pmCheckData = json_decode($pmCheckResponse['raw_response'], true);
        
        if (!$pmCheckData || !$pmCheckData['success'] || empty($pmCheckData['data'])) {
            throw new Exception('Không có phương thức thanh toán');
        }
        
        $paymentMethodFound = false;
        foreach ($pmCheckData['data'] as $pm) {
            if ($pm['method_id'] == $paymentMethodId) {
                $paymentMethodFound = true;
                error_log("✅ Payment method validated: " . $pm['type']);
                break;
            }
        }
        
        if (!$paymentMethodFound) {
            throw new Exception('Phương thức thanh toán không tồn tại');
        }
        
    } catch (Exception $e) {
        throw new Exception('Không thể kiểm tra phương thức thanh toán: ' . $e->getMessage());
    }
    
    // ===== 2. VALIDATE PROMO CODE =====
    if ($promoCode) {
        error_log("Validating promo code: $promoCode");
        
        try {
            $promoResponse = $apiClient->get('rental', '/promotions/validate?code=' . urlencode($promoCode));
            
            error_log("Promo API response: HTTP " . $promoResponse['status_code']);
            error_log("Promo API body: " . $promoResponse['raw_response']);
            
            if ($promoResponse['status_code'] === 200) {
                $promoResult = json_decode($promoResponse['raw_response'], true);
                
                if ($promoResult && $promoResult['success']) {
                    $promoDiscountPercent = floatval($promoResult['data']['discount_percent'] ?? 0);
                    $promoApplied = [
                        'code' => $promoCode,
                        'discount_percent' => $promoDiscountPercent
                    ];
                    error_log("✅ Promo validated: {$promoCode} = {$promoDiscountPercent}%");
                } else {
                    error_log("⚠️ Invalid promo: " . ($promoResult['message'] ?? 'Unknown'));
                    $promoCode = null;
                }
            } else {
                error_log("⚠️ Promo validation failed: HTTP " . $promoResponse['status_code']);
                $promoCode = null;
            }
        } catch (Exception $e) {
            error_log("⚠️ Promo error (non-critical): " . $e->getMessage());
            $promoCode = null;
        }
    }
    
    // ===== 3. CALCULATE TOTALS WITH PROMO =====
    error_log("Calculating totals with promo discount: {$promoDiscountPercent}%");
    
    $cartTotalBeforeDiscount = 0;
    
    foreach ($_SESSION['cart'] as $cartItem) {
        $catalogResponse = $apiClient->get('vehicle', '/catalogs/' . $cartItem['catalog_id']);
        
        if ($catalogResponse['status_code'] !== 200) {
            throw new Exception("Không tìm thấy loại xe");
        }
        
        $catalogData = json_decode($catalogResponse['raw_response'], true);
        $catalog = $catalogData['data'];
        
        $start = new DateTime($cartItem['start_time']);
        $end = new DateTime($cartItem['end_time']);
        $days = max(1, $end->diff($start)->days);
        
        $itemCost = $days * $catalog['daily_rate'] * $cartItem['quantity'];
        $cartTotalBeforeDiscount += $itemCost;
    }
    
    // ✅ Apply promo discount to TOTAL
    $cartTotalDiscount = 0;
    if ($promoDiscountPercent > 0) {
        $cartTotalDiscount = floor($cartTotalBeforeDiscount * $promoDiscountPercent / 100);
    }
    
    $cartTotalAfterDiscount = $cartTotalBeforeDiscount - $cartTotalDiscount;
    
    error_log("Cart total BEFORE discount: {$cartTotalBeforeDiscount}");
    error_log("Cart total DISCOUNT ({$promoDiscountPercent}%): {$cartTotalDiscount}");
    error_log("Cart total AFTER discount: {$cartTotalAfterDiscount}");
    
    // ===== 4. CREATE ALL RENTALS =====
    error_log("Creating rentals for " . count($_SESSION['cart']) . " items");
    
    foreach ($_SESSION['cart'] as $index => $cartItem) {
        $catalogResponse = $apiClient->get('vehicle', '/catalogs/' . $cartItem['catalog_id']);
        $catalogData = json_decode($catalogResponse['raw_response'], true);
        $catalog = $catalogData['data'];
        
        $start = new DateTime($cartItem['start_time']);
        $end = new DateTime($cartItem['end_time']);
        $days = max(1, $end->diff($start)->days);
        
        $itemTotal = $days * $catalog['daily_rate'] * $cartItem['quantity'];
        
        // ✅ Calculate proportional discount
        $itemDiscountShare = 0;
        if ($cartTotalBeforeDiscount > 0 && $cartTotalDiscount > 0) {
            $itemDiscountShare = floor(($itemTotal / $cartTotalBeforeDiscount) * $cartTotalDiscount);
        }
        
        $itemFinalCost = $itemTotal - $itemDiscountShare;
        
        error_log("Item #{$index}: original={$itemTotal}, discount={$itemDiscountShare}, final={$itemFinalCost}");
        
        // Get available units
        $unitsResponse = $apiClient->get('vehicle', 
            '/units/available?catalog_id=' . $cartItem['catalog_id'] . 
            '&location=' . urlencode($cartItem['pickup_location']) .
            '&start=' . urlencode($cartItem['start_time']) .
            '&end=' . urlencode($cartItem['end_time'])
        );
        
        if ($unitsResponse['status_code'] !== 200) {
            error_log("❌ Units API error: " . $unitsResponse['raw_response']);
            throw new Exception("Không thể kiểm tra xe có sẵn cho " . $catalog['brand'] . ' ' . $catalog['model']);
        }
        
        $unitsData = json_decode($unitsResponse['raw_response'], true);
        
        if (!$unitsData || !$unitsData['success']) {
            throw new Exception("Lỗi kiểm tra xe có sẵn: " . ($unitsData['message'] ?? 'Unknown'));
        }
        
        $availableUnits = $unitsData['data'] ?? [];
        
        if (count($availableUnits) < $cartItem['quantity']) {
            throw new Exception(
                "Không đủ xe " . $catalog['brand'] . ' ' . $catalog['model'] . 
                " (cần {$cartItem['quantity']}, có " . count($availableUnits) . ")"
            );
        }
        
        // ✅ FIX: Calculate cost per unit AFTER discount
        $costPerUnit = round($itemFinalCost / $cartItem['quantity'], 2);
        
        // Create rental for each unit
        for ($i = 0; $i < $cartItem['quantity']; $i++) {
            $unit = $availableUnits[$i];
            
            $rentalPayload = [
                'vehicle_id' => $unit['unit_id'],
                'start_time' => $cartItem['start_time'],
                'end_time' => $cartItem['end_time'],
                'pickup_location' => $cartItem['pickup_location'],
                'dropoff_location' => $cartItem['pickup_location'],
                'total_cost' => $costPerUnit, // ✅ Use discounted cost per unit
                'promo_code' => $promoCode
            ];
            
            error_log("Creating rental with payload: " . json_encode($rentalPayload));
            
            $rentalResponse = $apiClient->post('rental', '/rentals', $rentalPayload, [
                'Authorization: Bearer ' . $token
            ]);
            
            error_log("Rental API response: HTTP " . $rentalResponse['status_code']);
            error_log("Rental API body: " . $rentalResponse['raw_response']);
            
            if ($rentalResponse['status_code'] === 201 || $rentalResponse['status_code'] === 200) {
                $rentalResult = json_decode($rentalResponse['raw_response'], true);
                
                if ($rentalResult && $rentalResult['success'] && isset($rentalResult['data']['rental_id'])) {
                    $createdRentals[] = [
                        'rental_id' => $rentalResult['data']['rental_id'],
                        'unit_id' => $unit['unit_id'],
                        'license_plate' => $unit['license_plate'],
                        'vehicle' => $catalog['brand'] . ' ' . $catalog['model'],
                        'cost' => $costPerUnit
                    ];
                    
                    error_log("✅ Rental created: ID=" . $rentalResult['data']['rental_id']);
                } else {
                    throw new Exception("Rental API returned success but no rental_id: " . json_encode($rentalResult));
                }
            } else {
                $errorBody = json_decode($rentalResponse['raw_response'], true);
                $errorMsg = $errorBody['message'] ?? 'Unknown error';
                error_log("❌ Rental creation failed: " . $errorMsg);
                throw new Exception("Lỗi tạo đơn thuê: " . $errorMsg);
            }
        }
        
        $totalAmount += $itemTotal;
        $totalDiscount += $itemDiscountShare;
    }
    
    if (empty($createdRentals)) {
        throw new Exception('Không thể tạo đơn thuê xe');
    }
    
    $totalFinal = $totalAmount - $totalDiscount;
    
    error_log("✅ Final totals: original={$totalAmount}, discount={$totalDiscount}, final={$totalFinal}");
    
    // ===== 5. CREATE ONE PAYMENT WITH METADATA =====
    $rentalIds = array_column($createdRentals, 'rental_id');
    
    $paymentPayload = [
        'rental_id' => $rentalIds[0],
        'amount' => $totalFinal,
        'payment_method_id' => $paymentMethodId,
        'metadata' => [
            'rental_ids' => $rentalIds,
            'rental_count' => count($rentalIds),
            'cart_checkout' => true,
            'promo_code' => $promoCode,
            'original_amount' => $totalAmount,
            'discount_amount' => $totalDiscount
        ]
    ];
    
    error_log("Creating payment with metadata: " . json_encode($paymentPayload['metadata']));
    
    $paymentResponse = $apiClient->post('payment', '/payments/process', $paymentPayload, [
        'Authorization: Bearer ' . $token
    ]);
    
    if ($paymentResponse['status_code'] !== 200) {
        error_log("❌ Payment failed: " . $paymentResponse['raw_response']);
        
        // Rollback: Cancel all created rentals
        foreach ($createdRentals as $rental) {
            try {
                $apiClient->put('rental', '/rentals/' . $rental['rental_id'] . '/cancel', [], [
                    'Authorization: Bearer ' . $token
                ]);
            } catch (Exception $e) {
                error_log("Rollback error: " . $e->getMessage());
            }
        }
        
        $errorBody = json_decode($paymentResponse['raw_response'], true);
        throw new Exception('Không thể tạo thanh toán: ' . ($errorBody['message'] ?? 'Unknown'));
    }
    
    $paymentResult = json_decode($paymentResponse['raw_response'], true);
    
    if (!$paymentResult || !$paymentResult['success']) {
        throw new Exception('Lỗi thanh toán: ' . ($paymentResult['message'] ?? 'Unknown'));
    }
    
    error_log("✅ Payment created: " . $paymentResult['data']['transaction_id']);
    
    // ===== 6. CLEAR CART =====
    $_SESSION['cart'] = [];
    
    // ===== 7. SUCCESS RESPONSE =====
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Đặt xe thành công!',
        'data' => [
            'rentals' => $createdRentals,
            'payment' => [
                'transaction_id' => $paymentResult['data']['transaction_id'],
                'transaction_code' => $paymentResult['data']['transaction_code'],
                'payment_method' => $paymentResult['data']['payment_method'],
                'amount' => $totalFinal,
                'status' => $paymentResult['data']['status'],
                'qr_code_url' => $paymentResult['data']['qr_code_url'] ?? null,
                'rental_count' => count($rentalIds),
                'rental_ids' => $rentalIds
            ],
            'promo_applied' => $promoApplied,
            'summary' => [
                'total_rentals' => count($createdRentals),
                'original_amount' => $totalAmount,
                'discount_amount' => $totalDiscount,
                'final_amount' => $totalFinal
            ]
        ]
    ]);
    
    error_log("=== CHECKOUT SUCCESS ===");
    
} catch (Exception $e) {
    error_log('=== CART CHECKOUT ERROR ===');
    error_log('Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}