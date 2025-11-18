<?php
// ========================================
// frontend/api/cart-clear.php (BONUS)
// Path: TransportationRenting/frontend/api/cart-clear.php
// URL: http://localhost/api/cart-clear.php
// ========================================
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$_SESSION['cart'] = [];

echo json_encode([
    'success' => true,
    'message' => 'Đã xóa toàn bộ giỏ hàng'
]);