<?php
/**
 * ================================================
 * frontend/api/cart-checkout-individual.php
 * Handles checkout with INDIVIDUAL payment method per item
 * ================================================
 */

session_start();
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
$promoCode = $data['promo_code'] ?? null;
$itemPaymentMethods = $data['item_payment_methods'] ?? [];

error_log("=== CART CHECKOUT INDIVIDUAL START ===");
error_log("User ID: " . $user['user_id']);
error_log("Promo Code: " . ($promoCode ?? 'none'));
error_log("Cart Items: " . count($_SESSION['cart']));
error_log("Item Payment Methods: " . json_encode($itemPaymentMethods));

if (empty($itemPaymentMethods)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng chọn phương thức thanh toán cho tất cả các xe']);
    exit;
}

require_once __DIR__ . '/../../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
$apiClient->setServiceUrl('rental', 'http://localhost:8003');
$apiClient->setServiceUrl('payment', 'http://localhost:8005');

try {
    $createdRentals = [];
    $totalAmount = 0;
    $totalDiscount = 0;
    $promoApplied = null;
    
    // ===== 1. VALIDATE PROMO CODE (if provided) =====
    if ($promoCode) {
        error_log("Validating promo code: $promoCode");
        
        $promoResponse = $apiClient->get('rental', '/promotions/validate?code=' . urlencode($promoCode));
        
        if ($promoResponse['status_code'] === 200) {
            $promoResult = json_decode($promoResponse['raw_response'], true);
            
            if ($promoResult && $promoResult['success']) {
                $promoApplied = [
                    'code' => $promoCode,
                    'discount_percent' => floatval($promoResult['discount'])
                ];
                error_log("Promo applied: " . json_encode($promoApplied));
            }
        }
    }
    
    // ===== 2. CREATE RENTALS FOR EACH CART ITEM =====
    error_log("Creating rentals for " . count($_SESSION['cart']) . " cart items");
    
    foreach ($_SESSION['cart'] as $index => $cartItem) {
        error_log("Processing cart item #$index: catalog_id=" . $cartItem['catalog_id']);
        
        // Check if payment method is selected for this item
        if (!isset($itemPaymentMethods[$index])) {
            throw new Exception("Vui lòng chọn phương thức thanh toán cho xe #" . ($index + 1));
        }
        
        $paymentMethodId = $itemPaymentMethods[$index];
        error_log("Item $index → Payment Method: $paymentMethodId");
        
        // Get catalog details for pricing
        $catalogResponse = $apiClient->get('vehicle', '/catalogs/' . $cartItem['catalog_id']);
        
        if ($catalogResponse['status_code'] !== 200) {
            error_log("ERROR: Catalog not found for catalog_id=" . $cartItem['catalog_id']);
            throw new Exception("Không tìm thấy loại xe (ID: " . $cartItem['catalog_id'] . ")");
        }
        
        $catalogData = json_decode($catalogResponse['raw_response'], true);
        if (!$catalogData || !$catalogData['success']) {
            throw new Exception("Lỗi khi lấy thông tin loại xe");
        }
        
        $catalog = $catalogData['data'];
        error_log("Catalog loaded: " . $catalog['brand'] . ' ' . $catalog['model']);
        
        // Calculate rental days and cost
        $start = new DateTime($cartItem['start_time']);
        $end = new DateTime($cartItem['end_time']);
        $days = max(1, $end->diff($start)->days);
        
        $itemTotal = $days * $catalog['daily_rate'] * $cartItem['quantity'];
        
        error_log("Item calculation: days=$days, rate=" . $catalog['daily_rate'] . ", qty=" . $cartItem['quantity'] . ", total=$itemTotal");
        
        // Apply promo discount
        $itemDiscount = 0;
        if ($promoApplied) {
            $itemDiscount = round($itemTotal * $promoApplied['discount_percent'] / 100, 2);
            error_log("Discount applied: $itemDiscount");
        }
        
        $itemFinalCost = $itemTotal - $itemDiscount;
        
        // ===== ALLOCATE AVAILABLE UNITS =====
        $unitsResponse = $apiClient->get('vehicle', 
            '/units/available?catalog_id=' . $cartItem['catalog_id'] . 
            '&location=' . urlencode($cartItem['pickup_location']) .
            '&start=' . urlencode($cartItem['start_time']) .
            '&end=' . urlencode($cartItem['end_time'])
        );
        
        error_log("Units availability response: " . $unitsResponse['status_code']);
        
        if ($unitsResponse['status_code'] !== 200) {
            throw new Exception("Không thể kiểm tra xe có sẵn tại " . $cartItem['pickup_location']);
        }
        
        $unitsData = json_decode($unitsResponse['raw_response'], true);
        if (!$unitsData || !$unitsData['success']) {
            throw new Exception("Lỗi khi kiểm tra xe có sẵn");
        }
        
        $availableUnits = $unitsData['data'];
        error_log("Available units: " . count($availableUnits));
        
        if (count($availableUnits) < $cartItem['quantity']) {
            throw new Exception(
                "Không đủ xe " . $catalog['brand'] . ' ' . $catalog['model'] . 
                " tại " . $cartItem['pickup_location'] . 
                ". Có sẵn: " . count($availableUnits) . ", Cần: " . $cartItem['quantity']
            );
        }
        
        // Create rental for each quantity using specific units
        for ($i = 0; $i < $cartItem['quantity']; $i++) {
            $unit = $availableUnits[$i];
            
            $rentalPayload = [
                'vehicle_id' => $unit['unit_id'],
                'start_time' => $cartItem['start_time'],
                'end_time' => $cartItem['end_time'],
                'pickup_location' => $cartItem['pickup_location'],
                'dropoff_location' => $cartItem['pickup_location'],
                'total_cost' => round($itemFinalCost / $cartItem['quantity'], 2),
                'promo_code' => $promoCode
            ];
            
            error_log("Creating rental with unit #" . $unit['unit_id'] . ": " . json_encode($rentalPayload));
            
            $rentalResponse = $apiClient->post('rental', '/rentals', $rentalPayload, [
                'Authorization: Bearer ' . $token
            ]);
            
            error_log("Rental API response: status=" . $rentalResponse['status_code']);
            
            if ($rentalResponse['status_code'] === 201 || $rentalResponse['status_code'] === 200) {
                $rentalResult = json_decode($rentalResponse['raw_response'], true);
                
                if ($rentalResult && $rentalResult['success'] && isset($rentalResult['data']['rental_id'])) {
                    $createdRentals[] = [
                        'rental_id' => $rentalResult['data']['rental_id'],
                        'unit_id' => $unit['unit_id'],
                        'license_plate' => $unit['license_plate'],
                        'vehicle' => $catalog['brand'] . ' ' . $catalog['model'],
                        'cost' => $rentalPayload['total_cost'],
                        'payment_method_id' => $paymentMethodId // Store payment method for this rental
                    ];
                    error_log("✅ Rental created: ID=" . $rentalResult['data']['rental_id'] . ", Unit=" . $unit['license_plate'] . ", Payment Method=" . $paymentMethodId);
                } else {
                    $errorMsg = isset($rentalResult['message']) ? $rentalResult['message'] : 'Unknown error';
                    throw new Exception("Tạo đơn thuê thất bại: " . $errorMsg);
                }
            } else {
                $errorBody = json_decode($rentalResponse['raw_response'], true);
                $errorMsg = isset($errorBody['message']) ? $errorBody['message'] : 'HTTP ' . $rentalResponse['status_code'];
                throw new Exception("Lỗi API thuê xe: " . $errorMsg);
            }
        }
        
        $totalAmount += $itemTotal;
        $totalDiscount += $itemDiscount;
    }
    
    if (empty($createdRentals)) {
        throw new Exception('Không thể tạo đơn thuê xe. Vui lòng thử lại.');
    }
    
    $totalFinal = $totalAmount - $totalDiscount;
    
    // ===== 3. PROCESS PAYMENT FOR EACH RENTAL WITH ITS PAYMENT METHOD =====
    error_log("Processing payments for " . count($createdRentals) . " rentals with individual payment methods");
    
    $paymentResults = [];
    $paymentErrors = [];
    
    foreach ($createdRentals as $rental) {
        $paymentPayload = [
            'rental_id' => $rental['rental_id'],
            'amount' => $rental['cost'],
            'payment_method_id' => $rental['payment_method_id'] // Use individual payment method
        ];
        
        error_log("Creating payment for rental " . $rental['rental_id'] . " with payment method " . $rental['payment_method_id']);
        
        $paymentResponse = $apiClient->post('payment', '/payments/process', $paymentPayload, [
            'Authorization: Bearer ' . $token
        ]);
        
        if ($paymentResponse['status_code'] === 200) {
            $paymentResult = json_decode($paymentResponse['raw_response'], true);
            
            if ($paymentResult && $paymentResult['success']) {
                $paymentResults[] = [
                    'rental_id' => $rental['rental_id'],
                    'transaction_id' => $paymentResult['data']['transaction_id'],
                    'transaction_code' => $paymentResult['data']['transaction_code'],
                    'payment_method' => $paymentResult['data']['payment_method'],
                    'payment_method_id' => $rental['payment_method_id'],
                    'status' => $paymentResult['data']['status'],
                    'qr_code_url' => $paymentResult['data']['qr_code_url'] ?? null
                ];
                error_log("✅ Payment created: " . $paymentResult['data']['transaction_id'] . " (Method: " . $rental['payment_method_id'] . ")");
            } else {
                $paymentErrors[] = "Rental {$rental['rental_id']}: Payment failed";
            }
        } else {
            $paymentErrors[] = "Rental {$rental['rental_id']}: Payment API error";
        }
    }
    
    // ===== 4. CLEAR CART =====
    $_SESSION['cart'] = [];
    $_SESSION['cart_payment_methods'] = [];
    error_log("Cart cleared");
    
    // ===== 5. RETURN SUCCESS RESPONSE =====
    $response = [
        'success' => true,
        'message' => 'Đặt xe thành công với phương thức thanh toán riêng cho từng xe!',
        'data' => [
            'rentals' => $createdRentals,
            'payments' => $paymentResults,
            'payment_errors' => $paymentErrors,
            'promo_applied' => $promoApplied,
            'total_amount' => $totalAmount,
            'total_discount' => $totalDiscount,
            'total_final' => $totalFinal
        ]
    ];
    
    if (!empty($paymentErrors)) {
        $response['warnings'] = $paymentErrors;
    }
    
    error_log("=== CHECKOUT SUCCESS (INDIVIDUAL PAYMENTS) ===");
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('=== CART CHECKOUT INDIVIDUAL ERROR ===');
    error_log('Error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>