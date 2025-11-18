<?php
// ========================================
// frontend/api/cart-count.php
// Path: TransportationRenting/frontend/api/cart-count.php
// URL: http://localhost/api/cart-count.php
// ========================================
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

echo json_encode([
    'success' => true,
    'count' => count($_SESSION['cart'])
]);