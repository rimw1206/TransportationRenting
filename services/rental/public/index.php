<?php
require_once __DIR__ . '/../../../env-bootstrap.php';
// services/rental/public/index.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../services/RentalService.php';
require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../../../gateway/middleware/auth.php';

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($requestUri, PHP_URL_PATH);

// Remove service prefix if exists
$prefix = '/services/rental';
if (strpos($uri, $prefix) === 0) {
    $uri = substr($uri, strlen($prefix));
    if ($uri === '') $uri = '/';
}

// Health check
if ($uri === '/health') {
    require_once __DIR__ . '/health.php';
    exit;
}

$rentalService = new RentalService();

// Parse path segments
$segments = array_values(array_filter(explode('/', $uri)));

// Get auth token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

try {
    // Get query parameters
    $queryParams = $_GET;
    
    // Get request body
    $requestBody = null;
    if (in_array($requestMethod, ['POST', 'PUT', 'PATCH'])) {
        $body = file_get_contents('php://input');
        $requestBody = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE && !empty($body)) {
            ApiResponse::badRequest('Invalid JSON in request body');
        }
    }
    
    // Route handling
    switch ($requestMethod) {
        case 'GET':
            if (empty($segments)) {
                // GET /rentals - Get all rentals (admin only or with user filter)
                $auth = AuthMiddleware::authenticate();
                if (!$auth['success']) {
                    ApiResponse::unauthorized($auth['message']);
                }
                
                $filters = [
                    'user_id' => $queryParams['user_id'] ?? null,
                    'status' => $queryParams['status'] ?? null,
                    'vehicle_id' => $queryParams['vehicle_id'] ?? null,
                    'start_date' => $queryParams['start_date'] ?? null,
                    'end_date' => $queryParams['end_date'] ?? null,
                    'limit' => $queryParams['limit'] ?? null,
                    'offset' => $queryParams['offset'] ?? null
                ];
                
                // Non-admin users can only see their own rentals
                if ($auth['role'] !== 'admin') {
                    $filters['user_id'] = $auth['user_id'];
                }
                
                $result = $rentalService->getUserRentals($filters['user_id'], array_filter($filters));
                ApiResponse::success($result['data'], 'Rentals retrieved successfully');
                
            } elseif ($segments[0] === 'my-stats') {
                // GET /rentals/my-stats - Get user's statistics
                $auth = AuthMiddleware::authenticate();
                if (!$auth['success']) {
                    ApiResponse::unauthorized($auth['message']);
                }
                
                $result = $rentalService->getUserStatistics($auth['user_id']);
                
                if ($result['success']) {
                    ApiResponse::success($result['data'], 'Statistics retrieved successfully');
                } else {
                    ApiResponse::error($result['message'], 500);
                }
                
            } elseif ($segments[0] === 'check-availability') {
                // GET /rentals/check-availability?vehicle_id=X&start_time=Y&end_time=Z
                if (empty($queryParams['vehicle_id']) || empty($queryParams['start_time']) || empty($queryParams['end_time'])) {
                    ApiResponse::badRequest('Missing required parameters: vehicle_id, start_time, end_time');
                }
                
                $result = $rentalService->checkVehicleAvailability(
                    $queryParams['vehicle_id'],
                    $queryParams['start_time'],
                    $queryParams['end_time']
                );
                
                if ($result['success']) {
                    ApiResponse::success($result['data'], 'Availability checked');
                } else {
                    ApiResponse::error($result['message'], 500);
                }
                
            } elseif (is_numeric($segments[0])) {
                // GET /rentals/{id} - Get rental details
                $auth = AuthMiddleware::authenticate();
                if (!$auth['success']) {
                    ApiResponse::unauthorized($auth['message']);
                }
                
                $rentalId = (int)$segments[0];
                $result = $rentalService->getRentalDetails($rentalId);
                
                if ($result['success']) {
                    // Check ownership
                    if ($auth['role'] !== 'admin' && $result['data']['rental']['user_id'] != $auth['user_id']) {
                        ApiResponse::forbidden('You can only view your own rentals');
                    }
                    
                    ApiResponse::success($result['data'], 'Rental details retrieved');
                } else {
                    ApiResponse::notFound($result['message']);
                }
                
            } else {
                ApiResponse::notFound('Invalid endpoint');
            }
            break;
            
        case 'POST':
            if (empty($segments)) {
                // POST /rentals - Create rental
                $auth = AuthMiddleware::authenticate();
                if (!$auth['success']) {
                    ApiResponse::unauthorized($auth['message']);
                }
                
                if (!$requestBody) {
                    ApiResponse::badRequest('Request body is required');
                }
                
                // Set user_id from auth
                $requestBody['user_id'] = $auth['user_id'];
                
                $result = $rentalService->createRental($requestBody, $token);
                
                if ($result['success']) {
                    ApiResponse::created($result['data'], $result['message']);
                } else {
                    ApiResponse::badRequest($result['message']);
                }
                
            } else {
                ApiResponse::notFound('Invalid endpoint');
            }
            break;
            
        case 'PUT':
        case 'PATCH':
            if (is_numeric($segments[0]) && isset($segments[1]) && $segments[1] === 'cancel') {
                // PUT /rentals/{id}/cancel - Cancel rental
                $auth = AuthMiddleware::authenticate();
                if (!$auth['success']) {
                    ApiResponse::unauthorized($auth['message']);
                }
                
                $rentalId = (int)$segments[0];
                $result = $rentalService->cancelRental($rentalId, $auth['user_id']);
                
                if ($result['success']) {
                    ApiResponse::success(null, $result['message']);
                } else {
                    ApiResponse::badRequest($result['message']);
                }
                
            } else {
                ApiResponse::notFound('Invalid endpoint');
            }
            break;
            
        case 'DELETE':
            ApiResponse::methodNotAllowed('Delete not supported for rentals. Use cancel instead.');
            break;
            
        default:
            ApiResponse::methodNotAllowed('Method not allowed');
    }
    
} catch (Exception $e) {
    error_log('Rental API error: ' . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}