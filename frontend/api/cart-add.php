<?php
// ========================================
// frontend/api/cart-add.php
// Path: TransportationRenting/frontend/api/cart-add.php
// URL: http://localhost/api/cart-add.php
// ========================================
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['catalog_id', 'start_time', 'end_time', 'pickup_location', 'quantity'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

// Validate quantity
if ($input['quantity'] < 1 || $input['quantity'] > 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Số lượng không hợp lệ (1-10)']);
    exit;
}

// Validate dates
if (strtotime($input['end_time']) <= strtotime($input['start_time'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ngày kết thúc phải sau ngày bắt đầu']);
    exit;
}

// Validate dates not in past
if (strtotime($input['start_time']) < time()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ngày bắt đầu không được ở quá khứ']);
    exit;
}

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check if item already exists in cart
$exists = false;
foreach ($_SESSION['cart'] as $index => &$item) {
    if ($item['catalog_id'] == $input['catalog_id']) {
        // Update existing item
        $item['quantity'] = (int)$input['quantity'];
        $item['start_time'] = $input['start_time'];
        $item['end_time'] = $input['end_time'];
        $item['pickup_location'] = $input['pickup_location'];
        $item['updated_at'] = date('Y-m-d H:i:s');
        $exists = true;
        break;
    }
}

// Add new item if not exists
if (!$exists) {
    $_SESSION['cart'][] = [
        'catalog_id' => (int)$input['catalog_id'],
        'start_time' => $input['start_time'],
        'end_time' => $input['end_time'],
        'pickup_location' => $input['pickup_location'],
        'quantity' => (int)$input['quantity'],
        'added_at' => date('Y-m-d H:i:s')
    ];
}

echo json_encode([
    'success' => true,
    'message' => $exists ? 'Đã cập nhật giỏ hàng' : 'Đã thêm vào giỏ hàng',
    'cart_count' => count($_SESSION['cart'])
]);