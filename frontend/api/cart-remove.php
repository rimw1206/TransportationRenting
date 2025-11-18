<?php
// ========================================
// frontend/api/cart-remove.php
// Path: TransportationRenting/frontend/api/cart-remove.php
// URL: http://localhost/api/cart-remove.php
// ========================================
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['catalog_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'catalog_id required']);
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Remove item from cart
$_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($input) {
    return $item['catalog_id'] != $input['catalog_id'];
});

// Re-index array
$_SESSION['cart'] = array_values($_SESSION['cart']);

echo json_encode([
    'success' => true,
    'message' => 'Đã xóa khỏi giỏ hàng',
    'cart_count' => count($_SESSION['cart'])
]);

?>