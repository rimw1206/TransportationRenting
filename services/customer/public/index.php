<?php
// services/customer/public/index.php
header('Content-Type: application/json');

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Parse the URI and remove query string
$uri = parse_url($requestUri, PHP_URL_PATH);

// CRITICAL FIX: Strip /auth prefix if present (gateway forwards with prefix)
// Gateway sends: /auth/login → Customer service handles: /login
if (strpos($uri, '/auth') === 0) {
    $uri = substr($uri, strlen('/auth'));
    if ($uri === '') $uri = '/';
}

// Also strip /users prefix for user management routes
if (strpos($uri, '/users') === 0) {
    $uri = substr($uri, strlen('/users'));
    if ($uri === '') $uri = '/';
}

// Route: GET /health -> service health check
if ($uri === '/health' && $requestMethod === 'GET') {
    require_once __DIR__ . '/health.php';
    exit;
}

// ==================== AUTH ROUTES ====================
// Route: POST /login
if ($uri === '/login' && $requestMethod === 'POST') {
    require_once __DIR__ . '/../api/auth/login.php';
    exit;
}

// Route: POST /register
if ($uri === '/register' && $requestMethod === 'POST') {
    require_once __DIR__ . '/../api/auth/register.php';
    exit;
}

// Route: POST /refresh-token
if ($uri === '/refresh-token' && $requestMethod === 'POST') {
    require_once __DIR__ . '/../api/auth/refresh-token.php';
    exit;
}

// Route: POST /logout
if ($uri === '/logout' && $requestMethod === 'POST') {
    require_once __DIR__ . '/../api/auth/logout.php';
    exit;
}

// ==================== PROFILE ROUTES ====================
// Route: GET /profile (get current user profile)
if ($uri === '/profile' && $requestMethod === 'GET') {
    require_once __DIR__ . '/../api/profile/get.php';
    exit;
}

// Route: PUT /profile (update current user profile)
if ($uri === '/profile' && $requestMethod === 'PUT') {
    require_once __DIR__ . '/../api/profile/update.php';
    exit;
}

// ==================== USER MANAGEMENT ROUTES ====================
// Route: GET / (list all users - after /users prefix is stripped)
if ($uri === '/' && $requestMethod === 'GET') {
    require_once __DIR__ . '/../api/users/list.php';
    exit;
}

// Route: GET /{id} (get specific user)
if (preg_match('#^/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
    $_GET['user_id'] = $matches[1];
    require_once __DIR__ . '/../api/users/get.php';
    exit;
}

// Route: PUT /{id} (update user)
if (preg_match('#^/(\d+)$#', $uri, $matches) && $requestMethod === 'PUT') {
    $_GET['user_id'] = $matches[1];
    require_once __DIR__ . '/../api/users/update.php';
    exit;
}

// Route: DELETE /{id} (delete user)
if (preg_match('#^/(\d+)$#', $uri, $matches) && $requestMethod === 'DELETE') {
    $_GET['user_id'] = $matches[1];
    require_once __DIR__ . '/../api/users/delete.php';
    exit;
}

// ==================== KYC ROUTES ====================
// Route: POST /kyc (submit KYC)
if ($uri === '/kyc' && $requestMethod === 'POST') {
    require_once __DIR__ . '/../api/kyc/submit.php';
    exit;
}

// Route: GET /kyc (get KYC status)
if ($uri === '/kyc' && $requestMethod === 'GET') {
    require_once __DIR__ . '/../api/kyc/get.php';
    exit;
}

// Route: PUT /kyc/verify (admin verifies KYC)
if ($uri === '/kyc/verify' && $requestMethod === 'PUT') {
    require_once __DIR__ . '/../api/kyc/verify.php';
    exit;
}

// ==================== PAYMENT METHOD ROUTES ====================
// Route: GET /payment-methods (list payment methods)
if ($uri === '/payment-methods' && $requestMethod === 'GET') {
    require_once __DIR__ . '/../api/payment-methods/list.php';
    exit;
}

// Route: POST /payment-methods (add payment method)
if ($uri === '/payment-methods' && $requestMethod === 'POST') {
    require_once __DIR__ . '/../api/payment-methods/create.php';
    exit;
}

// Route: DELETE /payment-methods/{id} (delete payment method)
if (preg_match('#^/payment-methods/(\d+)$#', $uri, $matches) && $requestMethod === 'DELETE') {
    $_GET['method_id'] = $matches[1];
    require_once __DIR__ . '/../api/payment-methods/delete.php';
    exit;
}

// ==================== RENTAL HISTORY ROUTES ====================
// Route: GET /rental-history (user's rental history)
if ($uri === '/rental-history' && $requestMethod === 'GET') {
    require_once __DIR__ . '/../api/rental-history/list.php';
    exit;
}

// ==================== DEFAULT: NOT FOUND ====================
http_response_code(404);
echo json_encode([
    'success' => false,
    'error' => 'Route not found',
    'requested_uri' => $requestUri,
    'parsed_uri' => $uri,
    'method' => $requestMethod,
    'debug' => [
        'original_uri' => $requestUri,
        'parsed_uri' => $uri,
        'after_prefix_strip' => $uri
    ]
]);