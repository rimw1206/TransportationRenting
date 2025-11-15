<?php
require_once __DIR__ . '/../../../env-bootstrap.php';
// services/vehicle/public/index.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../services/VehicleService.php';
require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($requestUri, PHP_URL_PATH);

// Remove service prefix if exists
$prefix = '/services/vehicle';
if (strpos($uri, $prefix) === 0) {
    $uri = substr($uri, strlen($prefix));
    if ($uri === '') $uri = '/';
}

// Health check
if ($uri === '/health') {
    require_once __DIR__ . '/health.php';
    exit;
}

$vehicleService = new VehicleService();

// Parse path segments
$segments = array_values(array_filter(explode('/', $uri)));

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
                // GET /vehicles - Get all vehicles
                $filters = [
                    'status' => $queryParams['status'] ?? null,
                    'type' => $queryParams['type'] ?? null,
                    'brand' => $queryParams['brand'] ?? null,
                    'search' => $queryParams['search'] ?? null,
                    'min_price' => $queryParams['min_price'] ?? null,
                    'max_price' => $queryParams['max_price'] ?? null,
                    'order_by' => $queryParams['order_by'] ?? 'vehicle_id',
                    'order_dir' => $queryParams['order_dir'] ?? 'DESC',
                    'limit' => $queryParams['limit'] ?? null,
                    'offset' => $queryParams['offset'] ?? null
                ];
                
                $result = $vehicleService->getAllVehicles(array_filter($filters));
                ApiResponse::success($result['data'], $result['message'] ?? 'Vehicles retrieved successfully');
                
            } elseif ($segments[0] === 'available') {
                // GET /vehicles/available - Get available vehicles
                $filters = [
                    'type' => $queryParams['type'] ?? null,
                    'brand' => $queryParams['brand'] ?? null,
                    'search' => $queryParams['search'] ?? null,
                    'min_price' => $queryParams['min_price'] ?? null,
                    'max_price' => $queryParams['max_price'] ?? null,
                    'limit' => $queryParams['limit'] ?? null,
                    'offset' => $queryParams['offset'] ?? null
                ];
                
                $result = $vehicleService->getAvailableVehicles(array_filter($filters));
                ApiResponse::success($result['data'], $result['message'] ?? 'Available vehicles retrieved');
                
            } elseif ($segments[0] === 'stats') {
                // GET /vehicles/stats - Get statistics
                $result = $vehicleService->getStatistics();
                
                if ($result['success']) {
                    ApiResponse::success($result['data'], 'Statistics retrieved successfully');
                } else {
                    ApiResponse::error($result['message'], 500);
                }
                
            } elseif ($segments[0] === 'search') {
                // GET /vehicles/search?q=query
                $query = $queryParams['q'] ?? '';
                
                if (empty($query)) {
                    ApiResponse::badRequest('Search query is required');
                }
                
                $filters = [
                    'type' => $queryParams['type'] ?? null,
                    'status' => $queryParams['status'] ?? null,
                    'limit' => $queryParams['limit'] ?? null
                ];
                
                $result = $vehicleService->searchVehicles($query, array_filter($filters));
                ApiResponse::success($result['data'], 'Search results retrieved');
                
            } elseif (is_numeric($segments[0])) {
                // GET /vehicles/{id} - Get vehicle details
                $vehicleId = (int)$segments[0];
                $result = $vehicleService->getVehicleDetails($vehicleId);
                
                if ($result['success']) {
                    ApiResponse::success($result['data'], 'Vehicle details retrieved');
                } else {
                    ApiResponse::notFound($result['message']);
                }
                
            } else {
                ApiResponse::notFound('Invalid endpoint');
            }
            break;
            
        case 'POST':
            if (empty($segments)) {
                // POST /vehicles - Create vehicle
                if (!$requestBody) {
                    ApiResponse::badRequest('Request body is required');
                }
                
                $result = $vehicleService->createVehicle($requestBody);
                
                if ($result['success']) {
                    ApiResponse::created($result['data'], $result['message']);
                } else {
                    ApiResponse::badRequest($result['message']);
                }
                
            } elseif (is_numeric($segments[0]) && isset($segments[1])) {
                $vehicleId = (int)$segments[0];
                
                if ($segments[1] === 'usage') {
                    // POST /vehicles/{id}/usage - Record usage
                    if (!$requestBody) {
                        ApiResponse::badRequest('Request body is required');
                    }
                    
                    $result = $vehicleService->recordVehicleUsage(
                        $vehicleId,
                        $requestBody['rental_id'],
                        $requestBody['start_odo'],
                        $requestBody['end_odo'] ?? null,
                        $requestBody['fuel_used'] ?? null
                    );
                    
                    if ($result['success']) {
                        ApiResponse::success($result['data'], $result['message']);
                    } else {
                        ApiResponse::badRequest($result['message']);
                    }
                }
            } else {
                ApiResponse::notFound('Invalid endpoint');
            }
            break;
            
        case 'PUT':
        case 'PATCH':
            if (is_numeric($segments[0])) {
                $vehicleId = (int)$segments[0];
                
                if (isset($segments[1]) && $segments[1] === 'status') {
                    // PUT /vehicles/{id}/status - Update status
                    if (!$requestBody || !isset($requestBody['status'])) {
                        ApiResponse::badRequest('Status is required');
                    }
                    
                    $result = $vehicleService->updateVehicleStatus($vehicleId, $requestBody['status']);
                    
                    if ($result['success']) {
                        ApiResponse::success($result['data'], $result['message']);
                    } else {
                        ApiResponse::badRequest($result['message']);
                    }
                    
                } else {
                    // PUT /vehicles/{id} - Update vehicle
                    if (!$requestBody) {
                        ApiResponse::badRequest('Request body is required');
                    }
                    
                    $result = $vehicleService->updateVehicle($vehicleId, $requestBody);
                    
                    if ($result['success']) {
                        ApiResponse::success($result['data'], $result['message']);
                    } else {
                        ApiResponse::badRequest($result['message']);
                    }
                }
            } else {
                ApiResponse::notFound('Invalid endpoint');
            }
            break;
            
        case 'DELETE':
            if (is_numeric($segments[0])) {
                // DELETE /vehicles/{id} - Delete vehicle
                $vehicleId = (int)$segments[0];
                $result = $vehicleService->deleteVehicle($vehicleId);
                
                if ($result['success']) {
                    ApiResponse::success(null, $result['message']);
                } else {
                    ApiResponse::badRequest($result['message']);
                }
                
            } else {
                ApiResponse::notFound('Invalid endpoint');
            }
            break;
            
        default:
            ApiResponse::methodNotAllowed('Method not allowed');
    }
    
} catch (Exception $e) {
    error_log('Vehicle API error: ' . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}