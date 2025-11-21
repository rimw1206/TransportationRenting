<?php
/**
 * ================================================
 * services/vehicle/public/get-available-units.php
 * ✅ FIXED: Get available units for catalog at location/time
 * Checks against ACTUAL rentals in rental database
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
    
    error_log("=== GET AVAILABLE UNITS ===");
    error_log("Catalog ID: {$catalogId}");
    error_log("Location: {$location}");
    error_log("Start: {$startTime}");
    error_log("End: {$endTime}");
    
    $vehicle = new Vehicle();
    
    // Get available units with rental conflict check
    $units = $vehicle->getAvailableUnits($catalogId, $location, $startTime, $endTime);
    
    error_log("Found " . count($units) . " available units");
    
    ApiResponse::success($units, 'Available units retrieved successfully', [
        'catalog_id' => (int)$catalogId,
        'location' => $location,
        'count' => count($units),
        'time_range' => [
            'start' => $startTime,
            'end' => $endTime
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Get available units error: ' . $e->getMessage());
    ApiResponse::error('Failed to get available units', 500);
}