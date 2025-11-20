<?php
/**
 * ================================================
 * services/vehicle/public/get-catalog.php
 * Get catalog details by catalog_id
 * ================================================
 */

require_once __DIR__ . '/../../../env-bootstrap.php';
require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../classes/Vehicle.php';

ApiResponse::handleOptions();

try {
    $catalogId = $_GET['catalog_id'] ?? null;
    
    if (!$catalogId) {
        ApiResponse::badRequest('Catalog ID is required');
    }
    
    $vehicle = new Vehicle();
    $catalog = $vehicle->getCatalogById($catalogId);
    
    if (!$catalog) {
        ApiResponse::notFound('Catalog not found');
    }
    
    ApiResponse::success($catalog, 'Catalog retrieved successfully');
    
} catch (Exception $e) {
    error_log('Get catalog error: ' . $e->getMessage());
    ApiResponse::error('Failed to get catalog', 500);
}
