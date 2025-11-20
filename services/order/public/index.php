<?php
/**
 * ================================================
 * services/order/public/index.php
 * Order Service Router
 * ================================================
 */

require_once __DIR__ . '/../../../env-bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../classes/Order.php';
require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($requestUri, PHP_URL_PATH);

// Remove service prefix
$uri = preg_replace('#^/services/order#', '', $uri);
if ($uri === '') $uri = '/';

error_log("Order Service: {$requestMethod} {$uri}");

// Health check
if ($uri === '/health') {
    echo json_encode([
        'service' => 'order-service',
        'status' => 'ok',
        'timestamp' => date('c')
    ]);
    exit;
}

// Get auth token
$headers = getallheaders();
$token = null;
foreach ($headers as $key => $value) {
    if (strtolower($key) === 'authorization') {
        $token = str_replace('Bearer ', '', $value);
        break;
    }
}

$orderModel = new Order();

try {
    // Get request body
    $requestBody = null;
    if (in_array($requestMethod, ['POST', 'PUT', 'PATCH'])) {
        $body = file_get_contents('php://input');
        $requestBody = json_decode($body, true);
    }
    
    // ===== ROUTES =====
    
    // Create order from rental (POST /orders)
    if ($uri === '/orders' && $requestMethod === 'POST') {
        if (!$token) {
            ApiResponse::unauthorized('Token required');
        }
        
        $jwtHandler = new JWTHandler();
        $decoded = $jwtHandler->decode($token);
        if (!$decoded) {
            ApiResponse::unauthorized('Invalid token');
        }
        
        $rentalId = $requestBody['rental_id'] ?? null;
        $userId = $requestBody['user_id'] ?? $decoded['user_id'];
        
        if (!$rentalId) {
            ApiResponse::badRequest('rental_id is required');
        }
        
        // Check if order already exists
        $existing = $orderModel->getByRentalId($rentalId);
        if ($existing) {
            ApiResponse::conflict('Order already exists for this rental');
        }
        
        $orderId = $orderModel->createFromRental($rentalId, $userId);
        
        ApiResponse::created([
            'order_id' => $orderId,
            'rental_id' => $rentalId,
            'status' => 'Pending'
        ], 'Order created successfully');
    }
    
    // Get order by rental_id (GET /orders/rental/{rental_id})
    if (preg_match('#^/orders/rental/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
        $rentalId = (int)$matches[1];
        
        $order = $orderModel->getByRentalId($rentalId);
        
        if (!$order) {
            ApiResponse::notFound('Order not found for this rental');
        }
        
        ApiResponse::success($order, 'Order retrieved');
    }
    
    // Get order with tracking (GET /orders/{id})
    if (preg_match('#^/orders/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
        $orderId = (int)$matches[1];
        
        $order = $orderModel->getWithTracking($orderId);
        
        if (!$order) {
            ApiResponse::notFound('Order not found');
        }
        
        ApiResponse::success($order, 'Order retrieved');
    }
    
    // Update order status (PUT /orders/{id}/status)
    if (preg_match('#^/orders/(\d+)/status$#', $uri, $matches) && $requestMethod === 'PUT') {
        if (!$token) {
            ApiResponse::unauthorized('Token required');
        }
        
        $orderId = (int)$matches[1];
        $status = $requestBody['status'] ?? null;
        $note = $requestBody['note'] ?? null;
        
        if (!$status) {
            ApiResponse::badRequest('status is required');
        }
        
        $orderModel->updateStatus($orderId, $status, $note);
        
        ApiResponse::success([
            'order_id' => $orderId,
            'status' => $status
        ], 'Order status updated');
    }
    
    // Get user orders (GET /orders/user/{user_id})
    if (preg_match('#^/orders/user/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
        $userId = (int)$matches[1];
        
        $orders = $orderModel->getUserOrders($userId);
        
        ApiResponse::success($orders, 'Orders retrieved', ['count' => count($orders)]);
    }
    
    // Not found
    ApiResponse::notFound('Order endpoint not found');
    
} catch (Exception $e) {
    error_log('Order API error: ' . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}