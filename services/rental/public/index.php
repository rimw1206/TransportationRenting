<?php
require_once __DIR__ . '/../../../env-bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../services/RentalService.php';
require_once __DIR__ . '/../classes/Rental.php';
require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../../../gateway/middleware/auth.php';
require_once __DIR__ . '/../classes/Promotion.php';

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($requestUri, PHP_URL_PATH);

// Remove service prefix
$prefix = '/services/rental';
if (strpos($uri, $prefix) === 0) {
    $uri = substr($uri, strlen($prefix));
    if ($uri === '') $uri = '/';
}

$queryParams = $_GET;

// Health check
if ($uri === '/health') {
    echo json_encode([
        'service' => 'rental-service',
        'status' => 'ok',
        'timestamp' => date('c')
    ]);
    exit;
}

// ===== CRITICAL: Handle /rentals/{id}/status BEFORE other routes =====
if (preg_match('#^/rentals/(\d+)/status$#', $uri, $matches) && $requestMethod === 'PUT') {
    error_log("=== RENTAL STATUS UPDATE ENDPOINT HIT ===");
    error_log("Rental ID: " . $matches[1]);
    
    $requestBody = json_decode(file_get_contents('php://input'), true);
    error_log("Request body: " . json_encode($requestBody));
    
    if (!$requestBody || !isset($requestBody['status'])) {
        error_log("ERROR: status field missing");
        ApiResponse::badRequest('status is required');
    }
    
    $rentalId = (int)$matches[1];
    $status = $requestBody['status'];
    
    error_log("Updating rental #{$rentalId} to status: {$status}");
    
    $validStatuses = ['Pending', 'Ongoing', 'Completed', 'Cancelled'];
    if (!in_array($status, $validStatuses)) {
        error_log("ERROR: Invalid status: {$status}");
        ApiResponse::badRequest('Invalid status. Must be: ' . implode(', ', $validStatuses));
    }
    
    try {
        $rental = new Rental();
        $result = $rental->updateStatus($rentalId, $status);
        
        error_log("Update successful: " . json_encode($result));
        
        ApiResponse::success([
            'rental_id' => $rentalId,
            'status' => $status,
            'updated' => true
        ], 'Rental status updated successfully');
        
    } catch (Exception $e) {
        error_log("ERROR updating status: " . $e->getMessage());
        ApiResponse::error($e->getMessage(), 500);
    }
    
    exit;
}

// ===== Handle /rentals/{id}/cancel BEFORE other routes =====
if (preg_match('#^/rentals/(\d+)/cancel$#', $uri, $matches) && $requestMethod === 'PUT') {
    error_log("=== RENTAL CANCEL ENDPOINT HIT ===");
    
    $rentalId = (int)$matches[1];
    error_log("Cancelling rental #{$rentalId}");
    
    try {
        $rental = new Rental();
        $result = $rental->cancel($rentalId);
        
        error_log("Cancel successful");
        
        ApiResponse::success([
            'rental_id' => $rentalId,
            'status' => 'Cancelled'
        ], 'Rental cancelled successfully');
        
    } catch (Exception $e) {
        error_log("ERROR cancelling: " . $e->getMessage());
        ApiResponse::error($e->getMessage(), 500);
    }
    
    exit;
}

// ===== PROMOTION ROUTES =====
if (strpos($uri, '/promotions') === 0) {
    $promotion = new Promotion();
    $promoSegments = array_values(array_filter(explode('/', str_replace('/promotions', '', $uri))));
    
    $requestBody = null;
    if (in_array($requestMethod, ['POST', 'PUT', 'PATCH'])) {
        $body = file_get_contents('php://input');
        $requestBody = json_decode($body, true);
    }
    
    try {
        switch ($requestMethod) {
            case 'GET':
                if (empty($promoSegments)) {
                    $auth = AuthMiddleware::authenticate();
                    if (!$auth['success'] || $auth['role'] !== 'admin') {
                        ApiResponse::forbidden('Admin access required');
                    }
                    
                    $filters = ['active' => $queryParams['active'] ?? null];
                    $promos = $promotion->getAll(array_filter($filters));
                    ApiResponse::success($promos, 'Promotions retrieved');
                    
                } elseif ($promoSegments[0] === 'active') {
                    $promos = $promotion->getActive();
                    ApiResponse::success($promos, 'Active promotions retrieved');
                    
                } elseif ($promoSegments[0] === 'validate') {
                    if (empty($queryParams['code'])) {
                        ApiResponse::badRequest('Code parameter required');
                    }
                    
                    $result = $promotion->validate($queryParams['code']);
                    
                    if ($result['valid']) {
                        ApiResponse::success([
                            'code' => $result['promo']['code'],
                            'discount_percent' => $result['promo']['discount_percent'],
                            'description' => $result['promo']['description']
                        ], $result['message']);
                    } else {
                        ApiResponse::badRequest($result['message']);
                    }
                    
                } elseif (is_numeric($promoSegments[0])) {
                    $auth = AuthMiddleware::authenticate();
                    if (!$auth['success'] || $auth['role'] !== 'admin') {
                        ApiResponse::forbidden('Admin access required');
                    }
                    
                    $promo = $promotion->getById($promoSegments[0]);
                    if ($promo) {
                        ApiResponse::success($promo, 'Promotion retrieved');
                    } else {
                        ApiResponse::notFound('Promotion not found');
                    }
                } else {
                    ApiResponse::notFound('Invalid endpoint');
                }
                break;
                
            case 'POST':
                $auth = AuthMiddleware::authenticate();
                if (!$auth['success'] || $auth['role'] !== 'admin') {
                    ApiResponse::forbidden('Admin access required');
                }
                
                if (!$requestBody) {
                    ApiResponse::badRequest('Request body required');
                }
                
                $required = ['code', 'discount_percent', 'valid_from', 'valid_to'];
                foreach ($required as $field) {
                    if (empty($requestBody[$field])) {
                        ApiResponse::badRequest("Field '{$field}' is required");
                    }
                }
                
                $result = $promotion->create($requestBody);
                ApiResponse::created(['promo_id' => $result['promo_id']], 'Promotion created');
                break;
                
            case 'PUT':
            case 'PATCH':
                if (is_numeric($promoSegments[0])) {
                    $auth = AuthMiddleware::authenticate();
                    if (!$auth['success'] || $auth['role'] !== 'admin') {
                        ApiResponse::forbidden('Admin access required');
                    }
                    
                    if (!$requestBody) {
                        ApiResponse::badRequest('Request body required');
                    }
                    
                    $promo = $promotion->update($promoSegments[0], $requestBody);
                    ApiResponse::success($promo, 'Promotion updated');
                } else {
                    ApiResponse::notFound('Invalid endpoint');
                }
                break;
                
            case 'DELETE':
                if (is_numeric($promoSegments[0])) {
                    $auth = AuthMiddleware::authenticate();
                    if (!$auth['success'] || $auth['role'] !== 'admin') {
                        ApiResponse::forbidden('Admin access required');
                    }
                    
                    $promotion->delete($promoSegments[0]);
                    ApiResponse::success(null, 'Promotion deleted');
                } else {
                    ApiResponse::notFound('Invalid endpoint');
                }
                break;
                
            default:
                ApiResponse::methodNotAllowed('Method not allowed');
        }
        
    } catch (Exception $e) {
        error_log('Promotion API error: ' . $e->getMessage());
        ApiResponse::error($e->getMessage(), 500);
    }
    
    exit;
}

// ===== RENTAL ROUTES =====
$rentalService = new RentalService();
$segments = array_values(array_filter(explode('/', $uri)));

if (!empty($segments) && $segments[0] === 'rentals') {
    array_shift($segments);
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

try {
    $requestBody = null;
    if (in_array($requestMethod, ['POST', 'PUT', 'PATCH'])) {
        $body = file_get_contents('php://input');
        $requestBody = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE && !empty($body)) {
            ApiResponse::badRequest('Invalid JSON in request body');
        }
    }
    
    switch ($requestMethod) {
        case 'GET':
            if (empty($segments)) {
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
                
                if ($auth['role'] !== 'admin') {
                    $filters['user_id'] = $auth['user_id'];
                }
                
                $result = $rentalService->getUserRentals($filters['user_id'], array_filter($filters));
                ApiResponse::success($result['data'], 'Rentals retrieved successfully');
                
            } elseif ($segments[0] === 'my-stats') {
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
                $auth = AuthMiddleware::authenticate();
                if (!$auth['success']) {
                    ApiResponse::unauthorized($auth['message']);
                }
                
                $rentalId = (int)$segments[0];
                $result = $rentalService->getRentalDetails($rentalId);
                
                if ($result['success']) {
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
                $auth = AuthMiddleware::authenticate();
                if (!$auth['success']) {
                    ApiResponse::unauthorized($auth['message']);
                }
                
                if (!$requestBody) {
                    ApiResponse::badRequest('Request body is required');
                }
                
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
            // This shouldn't be reached because /status and /cancel are handled above
            ApiResponse::notFound('Invalid endpoint');
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