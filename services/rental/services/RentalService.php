<?php
// services/rental/services/RentalService.php
require_once __DIR__ . '/../classes/Rental.php';
require_once __DIR__ . '/../../../shared/classes/ApiClient.php';

class RentalService {
    private $rental;
    private $apiClient;

    public function __construct() {
        $this->rental = new Rental();
        $this->apiClient = new ApiClient();
        
        // Configure service URLs
        $this->apiClient->setServiceUrl('customer', 'http://localhost:8001');
        $this->apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
    }

    /**
     * Validate user exists via Customer API
     */
    private function validateUser($userId, $token = null) {
        try {
            $headers = $token ? ['Authorization: Bearer ' . $token] : [];
            $response = $this->apiClient->get('customer', "/users/{$userId}", $headers);
            
            if ($response['status_code'] === 200) {
                $data = json_decode($response['raw_response'], true);
                return $data['success'] ?? false;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("validateUser error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate vehicle exists and is available via Vehicle API
     */
    private function validateVehicle($vehicleId) {
        try {
            $response = $this->apiClient->get('vehicle', "/{$vehicleId}");
            
            if ($response['status_code'] === 200) {
                $data = json_decode($response['raw_response'], true);
                if ($data['success']) {
                    $vehicle = $data['data']['vehicle'] ?? null;
                    return [
                        'exists' => true,
                        'available' => $vehicle['status'] === 'Available',
                        'vehicle' => $vehicle
                    ];
                }
            }
            
            return ['exists' => false, 'available' => false];
        } catch (Exception $e) {
            error_log("validateVehicle error: " . $e->getMessage());
            return ['exists' => false, 'available' => false];
        }
    }

    /**
     * Update vehicle status via Vehicle API
     */
    private function updateVehicleStatus($vehicleId, $status) {
        try {
            $response = $this->apiClient->put('vehicle', "/{$vehicleId}/status", [
                'status' => $status
            ]);
            
            return $response['status_code'] === 200;
        } catch (Exception $e) {
            error_log("updateVehicleStatus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate rental cost
     */
    private function calculateCost($vehicle, $startTime, $endTime) {
        $start = new DateTime($startTime);
        $end = new DateTime($endTime);
        $days = $end->diff($start)->days;
        
        if ($days < 1) $days = 1;
        
        $dailyRate = $vehicle['daily_rate'] ?? 0;
        $totalCost = $dailyRate * $days;
        
        return [
            'days' => $days,
            'daily_rate' => $dailyRate,
            'total_cost' => $totalCost
        ];
    }

    /**
     * Create new rental
     */
    public function createRental($data, $token = null) {
        try {
            // Validate required fields
            $required = ['user_id', 'vehicle_id', 'start_time', 'end_time', 'pickup_location'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field '{$field}' is required"
                    ];
                }
            }
            
            // Validate user
            if (!$this->validateUser($data['user_id'], $token)) {
                return [
                    'success' => false,
                    'message' => 'Invalid user'
                ];
            }
            
            // Validate vehicle
            $vehicleValidation = $this->validateVehicle($data['vehicle_id']);
            if (!$vehicleValidation['exists']) {
                return [
                    'success' => false,
                    'message' => 'Vehicle not found'
                ];
            }
            
            if (!$vehicleValidation['available']) {
                return [
                    'success' => false,
                    'message' => 'Vehicle is not available'
                ];
            }
            
            $vehicle = $vehicleValidation['vehicle'];
            
            // Validate dates
            $startTime = new DateTime($data['start_time']);
            $endTime = new DateTime($data['end_time']);
            $now = new DateTime();
            
            if ($startTime < $now) {
                return [
                    'success' => false,
                    'message' => 'Start time must be in the future'
                ];
            }
            
            if ($endTime <= $startTime) {
                return [
                    'success' => false,
                    'message' => 'End time must be after start time'
                ];
            }
            
            // Check availability
            if (!$this->rental->checkAvailability($data['vehicle_id'], $data['start_time'], $data['end_time'])) {
                return [
                    'success' => false,
                    'message' => 'Vehicle is not available for selected dates'
                ];
            }
            
            // Calculate cost
            $costInfo = $this->calculateCost($vehicle, $data['start_time'], $data['end_time']);
            
            // Set dropoff location if not provided
            if (empty($data['dropoff_location'])) {
                $data['dropoff_location'] = $data['pickup_location'];
            }
            
            // Create rental
            $rentalData = [
                'user_id' => $data['user_id'],
                'vehicle_id' => $data['vehicle_id'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'pickup_location' => $data['pickup_location'],
                'dropoff_location' => $data['dropoff_location'],
                'total_cost' => $costInfo['total_cost'],
                'status' => 'Pending'
            ];
            
            $result = $this->rental->create($rentalData);
            
            // Update vehicle status to Rented
            $this->updateVehicleStatus($data['vehicle_id'], 'Rented');
            
            return [
                'success' => true,
                'message' => 'Rental created successfully',
                'data' => [
                    'rental_id' => $result['rental_id'],
                    'cost_breakdown' => $costInfo
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
     * Get user's rentals
     */
    public function getUserRentals($userId, $filters = []) {
        try {
            $filters['user_id'] = $userId;
            $rentals = $this->rental->getAll($filters);
            
            return [
                'success' => true,
                'data' => $rentals,
                'total' => count($rentals)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get rental details with vehicle info
     */
    public function getRentalDetails($rentalId) {
        try {
            $rental = $this->rental->getById($rentalId);
            
            if (!$rental) {
                return [
                    'success' => false,
                    'message' => 'Rental not found'
                ];
            }
            
            // Fetch vehicle details
            $vehicleResponse = $this->apiClient->get('vehicle', '/' . $rental['vehicle_id']);
            $vehicle = null;
            
            if ($vehicleResponse['status_code'] === 200) {
                $vehicleData = json_decode($vehicleResponse['raw_response'], true);
                if ($vehicleData['success']) {
                    $vehicle = $vehicleData['data']['vehicle'] ?? null;
                }
            }
            
            return [
                'success' => true,
                'data' => [
                    'rental' => $rental,
                    'vehicle' => $vehicle
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
     * Cancel rental
     */
    public function cancelRental($rentalId, $userId) {
        try {
            $rental = $this->rental->getById($rentalId);
            
            if (!$rental) {
                return [
                    'success' => false,
                    'message' => 'Rental not found'
                ];
            }
            
            // Check ownership
            if ($rental['user_id'] != $userId) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized'
                ];
            }
            
            // Cancel rental
            $this->rental->cancel($rentalId);
            
            // Update vehicle status back to Available
            $this->updateVehicleStatus($rental['vehicle_id'], 'Available');
            
            return [
                'success' => true,
                'message' => 'Rental cancelled successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics($userId) {
        try {
            $stats = $this->rental->getUserStats($userId);
            
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
     * Check vehicle availability
     */
    public function checkVehicleAvailability($vehicleId, $startTime, $endTime) {
        try {
            $isAvailable = $this->rental->checkAvailability($vehicleId, $startTime, $endTime);
            
            return [
                'success' => true,
                'data' => [
                    'available' => $isAvailable,
                    'vehicle_id' => $vehicleId,
                    'start_time' => $startTime,
                    'end_time' => $endTime
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}