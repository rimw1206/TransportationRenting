<?php
/**
 * services/customer/public/index.php
 * Customer Service Main Router - UPDATED WITH SET-DEFAULT ROUTE
 */

// Enable error reporting but don't display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Load dependencies
    require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
    require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';
    require_once __DIR__ . '/../../../env-bootstrap.php';
    
    $requestUri = $_SERVER['REQUEST_URI'];
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    
    // Parse the URI and remove query string
    $uri = parse_url($requestUri, PHP_URL_PATH);
    // Remove any gateway prefix
    $uri = preg_replace('#^/TransportationRenting/gateway/api#', '', $uri);

    // Remove /auth prefix
    $uri = preg_replace('#^/auth#', '', $uri);

    // Remove /users prefix
    $uri = preg_replace('#^/users#', '', $uri);

    if ($uri === '') $uri = '/';
    // Log incoming request
    error_log("Customer Service: {$requestMethod} {$uri}");
    
    
    error_log("Customer Service: After strip -> {$uri}");
    
    // ==================== HEALTH CHECK ====================
    if ($uri === '/health' && $requestMethod === 'GET') {
        echo json_encode([
            'service' => 'customer-service',
            'status' => 'ok',
            'timestamp' => date('c'),
            'port' => 8001
        ]);
        exit;
    }
    
    // ==================== AUTH ROUTES ====================
    if ($uri === '/login' && $requestMethod === 'POST') {
        $handlerPath = __DIR__ . '/../api/auth/login.php';
        if (!file_exists($handlerPath)) {
            throw new Exception("Handler not found: auth/login.php");
        }
        require_once $handlerPath;
        exit;
    }
    
    if ($uri === '/register' && $requestMethod === 'POST') {
        $handlerPath = __DIR__ . '/../api/auth/register.php';
        if (!file_exists($handlerPath)) {
            throw new Exception("Handler not found: auth/register.php");
        }
        require_once $handlerPath;
        exit;
    }
    
    if ($uri === '/refresh-token' && $requestMethod === 'POST') {
        $handlerPath = __DIR__ . '/../api/auth/refresh-token.php';
        if (!file_exists($handlerPath)) {
            throw new Exception("Handler not found: auth/refresh-token.php");
        }
        require_once $handlerPath;
        exit;
    }
    
    if ($uri === '/logout' && $requestMethod === 'POST') {
        $handlerPath = __DIR__ . '/../api/auth/logout.php';
        if (!file_exists($handlerPath)) {
            throw new Exception("Handler not found: auth/logout.php");
        }
        require_once $handlerPath;
        exit;
    }
    
    if ($uri === '/verify-email' && $requestMethod === 'POST') {
        $handlerPath = __DIR__ . '/../api/auth/verify-email.php';
        if (!file_exists($handlerPath)) {
            throw new Exception("Handler not found: auth/verify-email.php");
        }
        require_once $handlerPath;
        exit;
    }
    
    if ($uri === '/resend-verification' && $requestMethod === 'POST') {
        $handlerPath = __DIR__ . '/../api/auth/resend-verification.php';
        if (!file_exists($handlerPath)) {
            throw new Exception("Handler not found: auth/resend-verification.php");
        }
        require_once $handlerPath;
        exit;
    }
    
    if ($uri === '/change-password' && $requestMethod === 'PUT') {
        $handlerPath = __DIR__ . '/../api/auth/change-password.php';
        if (!file_exists($handlerPath)) {
            throw new Exception("Handler not found: auth/change-password.php");
        }
        require_once $handlerPath;
        exit;
    }
    
    // ==================== PROFILE ROUTES ====================
    if ($uri === '/profile' && $requestMethod === 'GET') {
        require_once __DIR__ . '/../api/profile/get.php';
        exit;
    }
    
    if ($uri === '/profile' && $requestMethod === 'PUT') {
        require_once __DIR__ . '/../api/profile/update.php';
        exit;
    }
    
    if ($uri === '/profile' && $requestMethod === 'DELETE') {
        require_once __DIR__ . '/../api/profile/delete.php';
        exit;
    }
    
    // ==================== USER MANAGEMENT ROUTES ====================
    if ($uri === '/' && $requestMethod === 'GET') {
        require_once __DIR__ . '/../api/users/list.php';
        exit;
    }
    
    if (preg_match('#^/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
        $_GET['user_id'] = $matches[1];
        require_once __DIR__ . '/../api/users/get.php';
        exit;
    }
    
    if (preg_match('#^/(\d+)$#', $uri, $matches) && $requestMethod === 'PUT') {
        $_GET['user_id'] = $matches[1];
        require_once __DIR__ . '/../api/users/update.php';
        exit;
    }
    
    if (preg_match('#^/(\d+)$#', $uri, $matches) && $requestMethod === 'DELETE') {
        $_GET['user_id'] = $matches[1];
        require_once __DIR__ . '/../api/users/delete.php';
        exit;
    }
    
    // ==================== KYC ROUTES ====================
    if ($uri === '/kyc' && $requestMethod === 'POST') {
        require_once __DIR__ . '/../api/kyc/submit.php';
        exit;
    }
    
    if ($uri === '/kyc' && $requestMethod === 'GET') {
        require_once __DIR__ . '/../api/kyc/get.php';
        exit;
    }
    
    if ($uri === '/kyc/verify' && $requestMethod === 'PUT') {
        require_once __DIR__ . '/../api/kyc/verify.php';
        exit;
    }
    
    // ==================== PAYMENT METHOD ROUTES ====================
    if ($uri === '/payment-methods' && $requestMethod === 'GET') {
        require_once __DIR__ . '/../api/payment-methods/list.php';
        exit;
    }
    
    if ($uri === '/payment-methods' && $requestMethod === 'POST') {
        require_once __DIR__ . '/../api/payment-methods/create.php';
        exit;
    }
    
    if (preg_match('#^/payment-methods/(\d+)$#', $uri, $matches) && $requestMethod === 'DELETE') {
        $_GET['method_id'] = $matches[1];
        require_once __DIR__ . '/../api/payment-methods/delete.php';
        exit;
    }
    
    // ✅ NEW ROUTE: Set Default Payment Method
    if (preg_match('#^/payment-methods/(\d+)/set-default$#', $uri, $matches) && $requestMethod === 'PUT') {
        $_GET['method_id'] = $matches[1];
        require_once __DIR__ . '/../api/payment-methods/set-default.php';
        exit;
    }
    
    // ==================== RENTAL HISTORY ROUTES ====================
    if ($uri === '/rental-history' && $requestMethod === 'GET') {
        require_once __DIR__ . '/../api/rental-history/list.php';
        exit;
    }
    
    // ==================== NOT FOUND ====================
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Route not found',
        'requested_uri' => $requestUri,
        'parsed_uri' => $uri,
        'method' => $requestMethod,
        'available_routes' => [
            'auth' => [
                'POST /auth/register',
                'POST /auth/login',
                'POST /auth/logout',
                'POST /auth/refresh-token',
                'POST /auth/verify-email',
                'POST /auth/resend-verification',
                'PUT /auth/change-password',
            ],
            'profile' => [
                'GET /profile',
                'PUT /profile',
                'DELETE /profile',
            ],
            'users' => [
                'GET /users',
                'GET /users/{id}',
                'PUT /users/{id}',
                'DELETE /users/{id}',
            ],
            'payment_methods' => [
                'GET /payment-methods',
                'POST /payment-methods',
                'DELETE /payment-methods/{id}',
                'PUT /payment-methods/{id}/set-default',
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Customer Service Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5)
    ]);
}