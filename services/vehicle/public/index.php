<?php
/**
 * ========================================
 * services/vehicle/public/index.php
 * Vehicle Service API - CLEANED VERSION
 * Port: 8002
 * ========================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/vehicle_errors.log');

if (!is_dir(__DIR__ . '/../../logs')) {
    mkdir(__DIR__ . '/../../logs', 0777, true);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';
require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../classes/Vehicle.php';
require_once __DIR__ . '/../services/VehicleService.php';

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Parse URL
$uri = parse_url($requestUri, PHP_URL_PATH);

// Remove service prefix if exists
$prefix = '/services/vehicle';
if (strpos($uri, $prefix) === 0) {
    $uri = substr($uri, strlen($prefix));
    if ($uri === '') $uri = '/';
}

error_log("Vehicle API Request: $requestMethod $uri");

$segments = array_values(array_filter(explode('/', $uri)));
$queryParams = $_GET;

// ===== HEALTH CHECK =====
if ($uri === '/health' || (isset($segments[0]) && $segments[0] === 'health')) {
    try {
        $db = DatabaseManager::getInstance('vehicle');
        $stmt = $db->query("SELECT COUNT(*) as count FROM VehicleCatalog");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'service' => 'Vehicle-service',
            'status' => 'ok',
            'database' => 'connected',
            'total_catalogs' => (int)$result['count'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'service' => 'Vehicle-service',
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// ===== UNIT ENDPOINTS =====

// GET /units/{id}
if (preg_match('#^/units/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
    try {
        $vehicle = new Vehicle();
        $unit = $vehicle->getUnitById((int)$matches[1]);
        
        if (!$unit) {
            ApiResponse::notFound('Vehicle unit not found');
        }
        
        ApiResponse::success($unit, 'Vehicle unit retrieved');
    } catch (Exception $e) {
        error_log("Get unit error: " . $e->getMessage());
        ApiResponse::error('Failed to get vehicle unit', 500);
    }
    exit;
}

// GET /units/{id}/available?start=...&end=...
if (preg_match('#^/units/(\d+)/available$#', $uri, $matches) && $requestMethod === 'GET') {
    $startTime = $_GET['start'] ?? null;
    $endTime = $_GET['end'] ?? null;
    
    if (!$startTime || !$endTime) {
        ApiResponse::badRequest('start and end time are required');
    }
    
    try {
        $vehicle = new Vehicle();
        $unit = $vehicle->getUnitById((int)$matches[1]);
        
        if (!$unit) {
            ApiResponse::notFound('Vehicle unit not found');
        }
        
        $isAvailable = $vehicle->isUnitAvailable((int)$matches[1], $startTime, $endTime);
        
        ApiResponse::success([
            'unit_id' => (int)$matches[1],
            'available' => $isAvailable,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'unit' => $unit
        ], 'Availability checked');
    } catch (Exception $e) {
        error_log("Check availability error: " . $e->getMessage());
        ApiResponse::error('Failed to check availability', 500);
    }
    exit;
}

// GET /units/available (with filters)
if (preg_match('#^/units/available$#', $uri) && $requestMethod === 'GET') {
    require_once __DIR__ . '/get-available-units.php';
    exit;
}

// ===== CATALOG ENDPOINTS =====

// GET /catalogs/{id}
if (preg_match('#^/catalogs/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
    $_GET['catalog_id'] = $matches[1];
    require_once __DIR__ . '/get-catalog.php';
    exit;
}

// ===== MAIN ROUTES =====

$vehicleService = new VehicleService();

try {
    // GET request body for POST/PUT
    $requestBody = null;
    if (in_array($requestMethod, ['POST', 'PUT', 'PATCH'])) {
        $body = file_get_contents('php://input');
        $requestBody = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE && !empty($body)) {
            ApiResponse::badRequest('Invalid JSON in request body');
        }
    }
    
    // ===== GET ROUTES =====
    if ($requestMethod === 'GET') {
        
        // GET /vehicles/all - Main endpoint with filters
        if ($uri === '/vehicles/all') {
            $filters = [
                'type' => $queryParams['type'] ?? null,
                'brand' => $queryParams['brand'] ?? null,
                'search' => $queryParams['search'] ?? null,
                'min_price' => $queryParams['min_price'] ?? null,
                'max_price' => $queryParams['max_price'] ?? null,
                'limit' => isset($queryParams['limit']) ? (int)$queryParams['limit'] : 50,
                'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0
            ];
            
            // Remove null/empty values
            $filters = array_filter($filters, function($v) { 
                return $v !== null && $v !== ''; 
            });
            
            error_log("GET /vehicles/all with filters: " . json_encode($filters));
            
            $result = $vehicleService->getAllVehicles($filters);
            
            if ($result['success']) {
                ApiResponse::success(
                    $result['data'],
                    "Vehicles retrieved successfully",
                    [
                        'total' => $result['total'],
                        'pagination' => $result['pagination'] ?? null
                    ]
                );
            } else {
                ApiResponse::error($result['message'], 500);
            }
            exit;
        }
        
        // GET /search?q=...
        if ($uri === '/search') {
            $searchQuery = $queryParams['q'] ?? '';
            
            if (empty($searchQuery)) {
                ApiResponse::badRequest('Search query (q) is required');
            }
            
            $filters = [
                'type' => $queryParams['type'] ?? null,
                'min_price' => $queryParams['min_price'] ?? null,
                'max_price' => $queryParams['max_price'] ?? null,
                'limit' => isset($queryParams['limit']) ? (int)$queryParams['limit'] : 50,
                'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0
            ];
            
            $filters = array_filter($filters, function($v) { 
                return $v !== null && $v !== ''; 
            });
            
            error_log("GET /search with query: '$searchQuery'");
            
            $result = $vehicleService->searchVehicles($searchQuery, $filters);
            
            if ($result['success']) {
                ApiResponse::success(
                    $result['data'],
                    "Search results for: '$searchQuery'",
                    [
                        'total' => $result['total'],
                        'search_term' => $searchQuery
                    ]
                );
            } else {
                ApiResponse::error($result['message'], 500);
            }
            exit;
        }
        
        // GET / or /vehicles - Default to /vehicles/all
        if (empty($segments) || $uri === '/vehicles') {
            $filters = [
                'type' => $queryParams['type'] ?? null,
                'brand' => $queryParams['brand'] ?? null,
                'search' => $queryParams['search'] ?? null,
                'min_price' => $queryParams['min_price'] ?? null,
                'max_price' => $queryParams['max_price'] ?? null,
                'limit' => isset($queryParams['limit']) ? (int)$queryParams['limit'] : 50,
                'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0
            ];
            
            $filters = array_filter($filters, function($v) { 
                return $v !== null && $v !== ''; 
            });
            
            $result = $vehicleService->getAllVehicles($filters);
            
            if ($result['success']) {
                ApiResponse::success($result['data'], 'Vehicles retrieved', [
                    'total' => $result['total'],
                    'pagination' => $result['pagination'] ?? null
                ]);
            } else {
                ApiResponse::error($result['message'], 500);
            }
            exit;
        }
        
        // GET /available
        if ($segments[0] === 'available') {
            $filters = [
                'type' => $queryParams['type'] ?? null,
                'brand' => $queryParams['brand'] ?? null,
                'search' => $queryParams['search'] ?? null,
                'min_price' => $queryParams['min_price'] ?? null,
                'max_price' => $queryParams['max_price'] ?? null,
                'limit' => isset($queryParams['limit']) ? (int)$queryParams['limit'] : 50,
                'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0
            ];
            
            $filters = array_filter($filters, function($v) { 
                return $v !== null && $v !== ''; 
            });
            
            $result = $vehicleService->getAvailableVehicles($filters);
            
            if ($result['success']) {
                ApiResponse::success($result['data'], 'Available vehicles retrieved', [
                    'total' => $result['total']
                ]);
            } else {
                ApiResponse::error($result['message'], 500);
            }
            exit;
        }
        
        // GET /stats
        if ($segments[0] === 'stats') {
            $result = $vehicleService->getStatistics();
            
            if ($result['success']) {
                ApiResponse::success($result['data'], 'Statistics retrieved');
            } else {
                ApiResponse::error($result['message'], 500);
            }
            exit;
        }
        
        // GET /{id} - Get catalog by ID
        if (is_numeric($segments[0])) {
            $result = $vehicleService->getVehicleDetails((int)$segments[0]);
            
            if ($result['success']) {
                ApiResponse::success($result['data'], 'Vehicle details retrieved');
            } else {
                ApiResponse::notFound($result['message']);
            }
            exit;
        }
        
        ApiResponse::notFound('Endpoint not found');
    }
    
    // ===== POST ROUTES =====
    if ($requestMethod === 'POST') {
        
        // POST /vehicles - Create vehicle
        if (empty($segments)) {
            if (!$requestBody) {
                ApiResponse::badRequest('Request body is required');
            }
            
            $result = $vehicleService->createVehicle($requestBody);
            
            if ($result['success']) {
                ApiResponse::created($result['data'], $result['message']);
            } else {
                ApiResponse::badRequest($result['message']);
            }
            exit;
        }
        
        // POST /reserve
        if ($segments[0] === 'reserve') {
            if (!$requestBody || !isset($requestBody['catalog_id']) || !isset($requestBody['quantity'])) {
                ApiResponse::badRequest('catalog_id and quantity are required');
            }
            
            $result = $vehicleService->reserveVehicleUnit(
                (int)$requestBody['catalog_id'],
                (int)$requestBody['quantity']
            );
            
            if ($result['success']) {
                ApiResponse::success($result['data'], $result['message']);
            } else {
                ApiResponse::badRequest($result['message']);
            }
            exit;
        }
        
        ApiResponse::notFound('Endpoint not found');
    }
    
    // ===== PUT/PATCH ROUTES =====
    if (in_array($requestMethod, ['PUT', 'PATCH'])) {
        
        // PUT /{unit_id}/status
        if (is_numeric($segments[0]) && isset($segments[1]) && $segments[1] === 'status') {
            if (!$requestBody || !isset($requestBody['status'])) {
                ApiResponse::badRequest('Status is required');
            }
            
            $result = $vehicleService->updateUnitStatus((int)$segments[0], $requestBody['status']);
            
            if ($result['success']) {
                ApiResponse::success($result['data'], $result['message']);
            } else {
                ApiResponse::badRequest($result['message']);
            }
            exit;
        }
        
        ApiResponse::notFound('Endpoint not found');
    }
    
    ApiResponse::methodNotAllowed('Method not allowed');
    
} catch (Exception $e) {
    error_log('Vehicle API Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    ApiResponse::error('Internal server error: ' . $e->getMessage(), 500);
}