<?php
/**
 * ================================================
 * services/vehicle/public/get-available-units.php
 * Get available units for a catalog at location/time
 * ================================================
 */

require_once __DIR__ . '/../../../env-bootstrap.php';
require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../classes/Vehicle.php';

ApiResponse::handleOptions();

try {
    $catalogId = $_GET['catalog_id'] ?? null;
    $location = $_GET['location'] ?? null;
    $startTime = $_GET['start'] ?? null;
    $endTime = $_GET['end'] ?? null;
    
    if (!$catalogId) {
        ApiResponse::badRequest('Catalog ID is required');
    }
    
    if (!$location) {
        ApiResponse::badRequest('Location is required');
    }
    
    $vehicle = new Vehicle();
    
    // Get available units
    $units = $vehicle->getAvailableUnits($catalogId, $location, $startTime, $endTime);
    
    ApiResponse::success($units, 'Available units retrieved successfully', [
        'catalog_id' => (int)$catalogId,
        'location' => $location,
        'count' => count($units)
    ]);
    
} catch (Exception $e) {
    error_log('Get available units error: ' . $e->getMessage());
    ApiResponse::error('Failed to get available units', 500);
}
