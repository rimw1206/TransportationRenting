<?php
// services/vehicle/services/VehicleService.php - FIXED WITH createVehicle METHOD
require_once __DIR__ . '/../classes/Vehicle.php';
require_once __DIR__ . '/../../../shared/classes/ApiClient.php';

class VehicleService {
    private $vehicle;
    private $apiClient;

    public function __construct() {
        $this->vehicle = new Vehicle();
        $this->apiClient = new ApiClient();
    }

    /**
     * Create new vehicle (catalog + units)
     */
    public function createVehicle($data) {
        try {
            // Validate required fields
            $required = ['type', 'brand', 'model', 'year', 'daily_rate'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Missing required field: $field"
                    ];
                }
            }

            // Validate vehicle type
            $validTypes = ['Car', 'Motorbike', 'Bicycle', 'Electric_Scooter'];
            if (!in_array($data['type'], $validTypes)) {
                return [
                    'success' => false,
                    'message' => 'Invalid vehicle type. Must be: ' . implode(', ', $validTypes)
                ];
            }

            // Validate year
            $currentYear = date('Y');
            if ($data['year'] < 1900 || $data['year'] > $currentYear + 1) {
                return [
                    'success' => false,
                    'message' => 'Invalid year'
                ];
            }

            // Validate daily_rate
            if ($data['daily_rate'] <= 0) {
                return [
                    'success' => false,
                    'message' => 'Daily rate must be greater than 0'
                ];
            }

            // Create catalog
            $catalogId = $this->vehicle->createCatalog([
                'type' => $data['type'],
                'brand' => $data['brand'],
                'model' => $data['model'],
                'year' => $data['year'],
                'daily_rate' => $data['daily_rate'],
                'seats' => $data['seats'] ?? null,
                'transmission' => $data['transmission'] ?? null,
                'fuel_type' => $data['fuel_type'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true
            ]);

            // Create units if provided
            $createdUnits = [];
            if (isset($data['units']) && is_array($data['units'])) {
                foreach ($data['units'] as $unitData) {
                    if (isset($unitData['license_plate'])) {
                        $unitId = $this->vehicle->createUnit([
                            'catalog_id' => $catalogId,
                            'license_plate' => $unitData['license_plate'],
                            'odo_km' => $unitData['odo_km'] ?? 0,
                            'fuel_level' => $unitData['fuel_level'] ?? 100,
                            'condition_rating' => $unitData['condition_rating'] ?? 5,
                            'status' => $unitData['status'] ?? 'Available'
                        ]);
                        
                        $createdUnits[] = [
                            'unit_id' => $unitId,
                            'license_plate' => $unitData['license_plate']
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Vehicle created successfully',
                'data' => [
                    'catalog_id' => $catalogId,
                    'units' => $createdUnits
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all vehicles BY CATALOG (grouped by model)
     * Returns catalog info + available count
     */
    public function getAllVehicles($filters = []) {
        try {
            $result = $this->vehicle->getAllCatalogs($filters);
            
            return [
                'success' => true,
                'data' => $result['items'],
                'total' => $result['total'],
                'pagination' => [
                    'limit' => $result['limit'],
                    'offset' => $result['offset'],
                    'total_pages' => !empty($filters['limit']) ? ceil($result['total'] / $filters['limit']) : 1
                ]
            ];
            
        } catch (Exception $e) {
            error_log("getAllVehicles error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get catalog details with available units
     */
    public function getVehicleDetails($catalogId) {
        try {
            $catalog = $this->vehicle->getCatalogById($catalogId);
            
            if (!$catalog) {
                return [
                    'success' => false,
                    'message' => 'Vehicle catalog not found'
                ];
            }
            
            // Get available count
            $availableCount = $this->vehicle->getAvailableCount($catalogId);
            $catalog['available_count'] = $availableCount;
            
            return [
                'success' => true,
                'data' => $catalog
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Reserve a vehicle unit from catalog
     * Auto-picks the first available unit by ID
     */
    public function reserveVehicleUnit($catalogId, $quantity = 1) {
        try {
            // Get available units for this catalog (ordered by unit_id)
            $availableUnits = $this->vehicle->getAvailableUnitsByCatalog($catalogId, $quantity);
            
            if (count($availableUnits) < $quantity) {
                return [
                    'success' => false,
                    'message' => "Only " . count($availableUnits) . " units available, requested $quantity"
                ];
            }
            
            // Reserve the units (mark as pending or similar)
            $reservedUnits = [];
            foreach ($availableUnits as $unit) {
                $reservedUnits[] = [
                    'unit_id' => $unit['unit_id'],
                    'license_plate' => $unit['license_plate'],
                    'catalog_id' => $catalogId
                ];
            }
            
            return [
                'success' => true,
                'message' => "$quantity unit(s) reserved",
                'data' => [
                    'reserved_units' => $reservedUnits,
                    'catalog_id' => $catalogId
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available vehicles for rental (CATALOG VIEW)
     */
    public function getAvailableVehicles($filters = []) {
        try {
            $catalogs = $this->vehicle->getAvailableCatalogs($filters);
            
            return [
                'success' => true,
                'data' => $catalogs,
                'total' => count($catalogs)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Search vehicles
     */
    public function searchVehicles($searchTerm, $filters = []) {
        try {
            $result = $this->vehicle->searchVehicles($searchTerm, $filters);
            
            return [
                'success' => true,
                'data' => $result['items'],
                'total' => $result['total'],
                'search_term' => $searchTerm
            ];
            
        } catch (Exception $e) {
            error_log("searchVehicles error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get vehicle statistics
     */
    public function getStatistics() {
        try {
            $stats = $this->vehicle->getStats();
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update unit status (for rental service)
     */
    public function updateUnitStatus($unitId, $status) {
        try {
            $result = $this->vehicle->updateUnitStatus($unitId, $status);
            
            return [
                'success' => true,
                'message' => 'Unit status updated',
                'data' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Record vehicle usage
     */
    public function recordVehicleUsage($unitId, $rentalId, $startOdo, $endOdo = null, $fuelUsed = null) {
        try {
            $usageId = $this->vehicle->recordUsage($unitId, $rentalId, $startOdo, $endOdo, $fuelUsed);
            
            return [
                'success' => true,
                'message' => 'Usage recorded',
                'data' => ['usage_id' => $usageId]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}