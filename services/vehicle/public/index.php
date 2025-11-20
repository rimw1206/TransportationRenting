<?php
// ========================================
// services/vehicle/public/index.php
// Vehicle Service API - COMPLETE VERSION
// Port: 8002
// ========================================

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/vehicle_errors.log');

// Create logs directory if not exists
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

// Load required files
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

// Log request
error_log("Vehicle API Request: $requestMethod $uri");

// Parse path segments
$segments = array_values(array_filter(explode('/', $uri)));

// Health check endpoint
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

// Get unit by ID (GET /units/{id})
if (preg_match('#^/units/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
    $unitId = (int)$matches[1];
    
    try {
        $vehicle = new Vehicle();
        $unit = $vehicle->getUnitById($unitId);
        
        if (!$unit) {
            ApiResponse::notFound('Vehicle unit not found');
        }
        
        ApiResponse::success($unit, 'Vehicle unit retrieved successfully');
    } catch (Exception $e) {
        error_log("Get unit error: " . $e->getMessage());
        ApiResponse::error('Failed to get vehicle unit', 500);
    }
    exit;
}

// Check unit availability (GET /units/{id}/available?start=...&end=...)
if (preg_match('#^/units/(\d+)/available$#', $uri, $matches) && $requestMethod === 'GET') {
    $unitId = (int)$matches[1];
    $startTime = $_GET['start'] ?? null;
    $endTime = $_GET['end'] ?? null;
    
    if (!$startTime || !$endTime) {
        ApiResponse::badRequest('start and end time are required');
    }
    
    try {
        $vehicle = new Vehicle();
        $unit = $vehicle->getUnitById($unitId);
        
        if (!$unit) {
            ApiResponse::notFound('Vehicle unit not found');
        }
        
        $isAvailable = $vehicle->isUnitAvailable($unitId, $startTime, $endTime);
        
        ApiResponse::success([
            'unit_id' => $unitId,
            'available' => $isAvailable,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'unit' => $unit
        ], 'Availability checked');
        
    } catch (Exception $e) {
        error_log("Check unit availability error: " . $e->getMessage());
        ApiResponse::error('Failed to check availability', 500);
    }
    exit;
}
if (preg_match('#^/catalogs/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
    $_GET['catalog_id'] = $matches[1];
    require_once __DIR__ . '/get-catalog.php';
    exit;
}

// Get available units
if (preg_match('#^/units/available$#', $uri) && $requestMethod === 'GET') {
    require_once __DIR__ . '/get-available-units.php';
    exit;
}
// Initialize service
$vehicleService = new VehicleService();

try {
    // Get query parameters
    $queryParams = $_GET;
    
    // Get request body for POST/PUT
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
                // GET / or /vehicles - Get all vehicles
                $filters = [
                    'type' => $queryParams['type'] ?? null,
                    'brand' => $queryParams['brand'] ?? null,
                    'search' => $queryParams['search'] ?? null,
                    'min_price' => $queryParams['min_price'] ?? null,
                    'max_price' => $queryParams['max_price'] ?? null,
                    'limit' => $queryParams['limit'] ?? 50,
                    'offset' => $queryParams['offset'] ?? 0
                ];
                
                error_log("Getting all vehicles with filters: " . json_encode($filters));
                $result = $vehicleService->getAllVehicles(array_filter($filters, function($v) { return $v !== null; }));
                
                if ($result['success']) {
                    ApiResponse::success($result['data'], $result['message'] ?? 'Vehicles retrieved', ['total' => $result['total'] ?? count($result['data'])]);
                } else {
                    ApiResponse::error($result['message'], 500);
                }
                
            } elseif ($segments[0] === 'available') {
                // GET /available - Get available vehicles
                $filters = [
                    'type' => $queryParams['type'] ?? null,
                    'brand' => $queryParams['brand'] ?? null,
                    'search' => $queryParams['search'] ?? null,
                    'min_price' => $queryParams['min_price'] ?? null,
                    'max_price' => $queryParams['max_price'] ?? null,
                    'limit' => $queryParams['limit'] ?? 50,
                    'offset' => $queryParams['offset'] ?? 0
                ];
                
                error_log("Getting available vehicles with filters: " . json_encode($filters));
                $result = $vehicleService->getAvailableVehicles(array_filter($filters, function($v) { return $v !== null; }));
                
                if ($result['success']) {
                    ApiResponse::success($result['data'], $result['message'] ?? 'Available vehicles retrieved', ['total' => $result['total'] ?? count($result['data'])]);
                } else {
                    ApiResponse::error($result['message'], 500);
                }
                
            } elseif ($segments[0] === 'stats') {
                // GET /stats - Get statistics
                $result = $vehicleService->getStatistics();
                
                if ($result['success']) {
                    ApiResponse::success($result['data'], 'Statistics retrieved');
                } else {
                    ApiResponse::error($result['message'], 500);
                }
                
            } elseif ($segments[0] === 'search') {
                // GET /search?q=query
                $query = $queryParams['q'] ?? '';
                
                if (empty($query)) {
                    ApiResponse::badRequest('Search query is required');
                }
                
                $filters = [
                    'type' => $queryParams['type'] ?? null,
                    'limit' => $queryParams['limit'] ?? 50
                ];
                
                $result = $vehicleService->searchVehicles($query, array_filter($filters, function($v) { return $v !== null; }));
                
                if ($result['success']) {
                    ApiResponse::success($result['data'], 'Search results', ['total' => $result['total'] ?? count($result['data'])]);
                } else {
                    ApiResponse::error($result['message'], 500);
                }
                
            } elseif (is_numeric($segments[0])) {
                // GET /vehicles/{id} - Get catalog details
                $catalogId = (int)$segments[0];
                $result = $vehicleService->getVehicleDetails($catalogId);
                
                if ($result['success']) {
                    ApiResponse::success($result['data'], 'Vehicle details retrieved');
                } else {
                    ApiResponse::notFound($result['message']);
                }
                
            } else {
                ApiResponse::notFound('Endpoint not found');
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
                
            } elseif ($segments[0] === 'reserve') {
                // POST /vehicles/reserve
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
                
            } else {
                ApiResponse::notFound('Endpoint not found');
            }
            break;
            
        case 'PUT':
        case 'PATCH':
            if (is_numeric($segments[0]) && isset($segments[1]) && $segments[1] === 'status') {
                // PUT /vehicles/{unit_id}/status
                $unitId = (int)$segments[0];
                
                if (!$requestBody || !isset($requestBody['status'])) {
                    ApiResponse::badRequest('Status is required');
                }
                
                $result = $vehicleService->updateUnitStatus($unitId, $requestBody['status']);
                
                if ($result['success']) {
                    ApiResponse::success($result['data'], $result['message']);
                } else {
                    ApiResponse::badRequest($result['message']);
                }
                
            } else {
                ApiResponse::notFound('Endpoint not found');
            }
            break;
            
        default:
            ApiResponse::methodNotAllowed('Method not allowed');
    }
    
} catch (Exception $e) {
    error_log('Vehicle API Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    ApiResponse::error('Internal server error: ' . $e->getMessage(), 500);
}