<?php
// services/vehicle/services/VehicleService.php
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
     * Get all vehicles with filters
     */
    public function getAllVehicles($filters = []) {
        try {
            $vehicles = $this->vehicle->getAll($filters);
            
            return [
                'success' => true,
                'data' => $vehicles,
                'total' => count($vehicles)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get vehicle details
     */
    public function getVehicleDetails($vehicleId) {
        try {
            $vehicle = $this->vehicle->getById($vehicleId);
            
            if (!$vehicle) {
                return [
                    'success' => false,
                    'message' => 'Vehicle not found'
                ];
            }
            
            // Get usage history
            $usageHistory = $this->vehicle->getUsageHistory($vehicleId);
            
            return [
                'success' => true,
                'data' => [
                    'vehicle' => $vehicle,
                    'usage_history' => $usageHistory
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
     * Create new vehicle
     */
    public function createVehicle($data) {
        try {
            // Validate required fields
            $required = ['license_plate', 'brand', 'model', 'type', 'daily_rate'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field '{$field}' is required"
                    ];
                }
            }
            
            // Validate type
            $validTypes = ['Car', 'Motorbike', 'Bicycle', 'Electric_Scooter'];
            if (!in_array($data['type'], $validTypes)) {
                return [
                    'success' => false,
                    'message' => 'Invalid vehicle type'
                ];
            }
            
            // Validate rates
            if (!is_numeric($data['daily_rate']) || $data['daily_rate'] <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid daily rate'
                ];
            }
            
            $result = $this->vehicle->create($data);
            
            return [
                'success' => true,
                'message' => 'Vehicle created successfully',
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
     * Update vehicle
     */
    public function updateVehicle($vehicleId, $data) {
        try {
            // Validate type if provided
            if (isset($data['type'])) {
                $validTypes = ['Car', 'Motorbike', 'Bicycle', 'Electric_Scooter'];
                if (!in_array($data['type'], $validTypes)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid vehicle type'
                    ];
                }
            }
            
            // Validate status if provided
            if (isset($data['status'])) {
                $validStatuses = ['Available', 'Rented', 'Maintenance', 'Retired'];
                if (!in_array($data['status'], $validStatuses)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid status'
                    ];
                }
            }
            
            $result = $this->vehicle->update($vehicleId, $data);
            
            return [
                'success' => true,
                'message' => 'Vehicle updated successfully',
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
     * Delete vehicle
     */
    public function deleteVehicle($vehicleId) {
        try {
            $this->vehicle->delete($vehicleId);
            
            return [
                'success' => true,
                'message' => 'Vehicle deleted successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available vehicles for rental
     */
    public function getAvailableVehicles($filters = []) {
        try {
            $vehicles = $this->vehicle->getAvailable($filters);
            
            return [
                'success' => true,
                'data' => $vehicles,
                'total' => count($vehicles)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update vehicle status
     */
    public function updateVehicleStatus($vehicleId, $status) {
        try {
            $result = $this->vehicle->updateStatus($vehicleId, $status);
            
            return [
                'success' => true,
                'message' => 'Status updated successfully',
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
     * Record vehicle usage (called by Rental Service)
     */
    public function recordVehicleUsage($vehicleId, $rentalId, $startOdo, $endOdo = null, $fuelUsed = null) {
        try {
            $usageId = $this->vehicle->recordUsage($vehicleId, $rentalId, $startOdo, $endOdo, $fuelUsed);
            
            return [
                'success' => true,
                'message' => 'Usage recorded successfully',
                'data' => ['usage_id' => $usageId]
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
    public function searchVehicles($query, $filters = []) {
        try {
            $filters['search'] = $query;
            $vehicles = $this->vehicle->getAll($filters);
            
            return [
                'success' => true,
                'data' => $vehicles,
                'total' => count($vehicles),
                'query' => $query
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}