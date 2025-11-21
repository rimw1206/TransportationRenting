<?php
/**
 * ================================================
 * public/api/cart-add.php
 * FIXED: Properly store quantity and selected units
 * ================================================
 */
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('vehicle', 'http://localhost:8002');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid request data');
    }
    
    // Validate required fields
    $required = ['catalog_id', 'start_time', 'end_time', 'pickup_location', 'quantity'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Field '{$field}' is required");
        }
    }
    
    $catalogId = (int)$input['catalog_id'];
    $startTime = $input['start_time'];
    $endTime = $input['end_time'];
    $location = $input['pickup_location'];
    $quantity = (int)$input['quantity'];
    
    // Validate dates
    $start = new DateTime($startTime);
    $end = new DateTime($endTime);
    $now = new DateTime();
    
    if ($start < $now) {
        throw new Exception('Start time must be in the future');
    }
    
    if ($end <= $start) {
        throw new Exception('End time must be after start time');
    }
    
    // ✅ CRITICAL: Check real-time availability via Vehicle API
    $availabilityUrl = "/units/available?catalog_id={$catalogId}" .
                       "&location=" . urlencode($location) .
                       "&start=" . urlencode($startTime) .
                       "&end=" . urlencode($endTime);
    
    error_log("Checking availability: " . $availabilityUrl);
    
    $response = $apiClient->get('vehicle', $availabilityUrl);
    
    if ($response['status_code'] !== 200) {
        throw new Exception('Failed to check vehicle availability');
    }
    
    $availData = json_decode($response['raw_response'], true);
    
    if (!$availData || !$availData['success']) {
        throw new Exception('Invalid availability response');
    }
    
    $availableUnits = $availData['data'];
    $availableCount = count($availableUnits);
    
    error_log("Available units: {$availableCount}, Requested: {$quantity}");
    
    if ($availableCount < $quantity) {
        throw new Exception("Only {$availableCount} vehicles available, you requested {$quantity}");
    }
    
    // Get catalog details for pricing
    $catalogResponse = $apiClient->get('vehicle', '/catalogs/' . $catalogId);
    
    if ($catalogResponse['status_code'] !== 200) {
        throw new Exception('Vehicle not found');
    }
    
    $catalogData = json_decode($catalogResponse['raw_response'], true);
    
    if (!$catalogData || !$catalogData['success']) {
        throw new Exception('Failed to get vehicle details');
    }
    
    $vehicle = $catalogData['data'];
    
    // Calculate cost
    $days = max(1, $end->diff($start)->days);
    $dailyRate = $vehicle['daily_rate'];
    $itemTotalCost = $days * $dailyRate;
    
    // Initialize cart if needed
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // ✅ FIX: Add ONE cart item with quantity and list of unit_ids
    $selectedUnitIds = array_column(array_slice($availableUnits, 0, $quantity), 'unit_id');
    
    $cartItem = [
        'catalog_id' => $catalogId,
        'vehicle_name' => $vehicle['brand'] . ' ' . $vehicle['model'],
        'vehicle_type' => $vehicle['type'],
        'start_time' => $startTime,
        'end_time' => $endTime,
        'pickup_location' => $location,
        'dropoff_location' => $location,
        'days' => $days,
        'daily_rate' => $dailyRate,
        'quantity' => $quantity, // ✅ CRITICAL: Store quantity
        'unit_ids' => $selectedUnitIds, // ✅ Store selected unit IDs
        'item_total' => $itemTotalCost * $quantity, // Total for this item
        'added_at' => date('Y-m-d H:i:s')
    ];
    
    $_SESSION['cart'][] = $cartItem;
    
    error_log("Added 1 cart item with quantity {$quantity}");
    
    echo json_encode([
        'success' => true,
        'message' => "Added {$quantity} vehicle(s) to cart",
        'cart_count' => count($_SESSION['cart']),
        'total_vehicles' => array_sum(array_column($_SESSION['cart'], 'quantity'))
    ]);
    
} catch (Exception $e) {
    error_log('Cart add error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}