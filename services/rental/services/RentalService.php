<?php
/**
 * ================================================
 * services/rental/services/RentalService.php
 * CRITICAL FIX - validateUser() using /profile
 * ================================================
 */

require_once __DIR__ . '/../classes/Rental.php';
require_once __DIR__ . '/../classes/Promotion.php';
require_once __DIR__ . '/../../../shared/classes/ApiClient.php';

class RentalService {
    private $rental;
    private $apiClient;
    private $promotion; 

    public function __construct() {
        $this->rental = new Rental();
        $this->promotion = new Promotion();
        $this->apiClient = new ApiClient();
        
        // Configure service URLs
        $this->apiClient->setServiceUrl('customer', 'http://localhost:8001');
        $this->apiClient->setServiceUrl('vehicle', 'http://localhost:8002');
    }

    /**
     * ✅ FIXED: Validate user via /profile instead of /users/{id}
     */
    private function validateUser($userId, $token = null) {
        try {
            if (!$token) {
                error_log("RentalService::validateUser - No token provided");
                return false;
            }
            
            $headers = ['Authorization: Bearer ' . $token];
            
            // ✅ USE /profile ENDPOINT (accessible by user themselves)
            $response = $this->apiClient->get('customer', '/profile', $headers);
            
            error_log("RentalService::validateUser - Response status: " . $response['status_code']);
            
            if ($response['status_code'] === 200) {
                $data = json_decode($response['raw_response'], true);
                
                if ($data && isset($data['success']) && $data['success']) {
                    if (isset($data['data']['user_id'])) {
                        $profileUserId = (int)$data['data']['user_id'];
                        $requestUserId = (int)$userId;
                        
                        error_log("RentalService::validateUser - Profile user_id: {$profileUserId}, Request user_id: {$requestUserId}");
                        
                        return $profileUserId === $requestUserId;
                    }
                }
            }
            
            error_log("RentalService::validateUser - Validation failed");
            return false;
            
        } catch (Exception $e) {
            error_log("RentalService::validateUser error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate vehicle exists and is available via Vehicle API
     */
    private function validateVehicle($unitId, $startTime, $endTime) {
        try {
            // Get unit details
            $response = $this->apiClient->get('vehicle', "/units/{$unitId}");
            
            error_log("validateVehicle - GET /units/{$unitId} status: " . $response['status_code']);
            
            if ($response['status_code'] !== 200) {
                error_log("validateVehicle - Unit not found");
                return ['exists' => false, 'available' => false];
            }
            
            $data = json_decode($response['raw_response'], true);
            
            if (!$data || !$data['success']) {
                error_log("validateVehicle - Invalid response");
                return ['exists' => false, 'available' => false];
            }
            
            $unit = $data['data'];
            
            // Check basic status
            if ($unit['status'] !== 'Available') {
                error_log("validateVehicle - Unit status: " . $unit['status']);
                return [
                    'exists' => true, 
                    'available' => false,
                    'reason' => 'Unit is currently ' . $unit['status']
                ];
            }
            
            // Check if catalog is active
            if (!$unit['catalog']['is_active']) {
                error_log("validateVehicle - Catalog inactive");
                return [
                    'exists' => true,
                    'available' => false,
                    'reason' => 'Vehicle model is no longer available'
                ];
            }
            
            // Double-check availability for time range
            $availResponse = $this->apiClient->get('vehicle', 
                "/units/{$unitId}/available?start=" . urlencode($startTime) . 
                "&end=" . urlencode($endTime)
            );
            
            if ($availResponse['status_code'] === 200) {
                $availData = json_decode($availResponse['raw_response'], true);
                
                if ($availData && $availData['success']) {
                    $isAvailable = $availData['data']['available'] ?? false;
                    
                    if (!$isAvailable) {
                        error_log("validateVehicle - Unit not available for time range");
                        return [
                            'exists' => true,
                            'available' => false,
                            'reason' => 'Unit is already booked for this time period'
                        ];
                    }
                }
            }
            
            error_log("validateVehicle - Unit is available");
            return [
                'exists' => true,
                'available' => true,
                'vehicle' => $unit
            ];
            
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
                    return ['success' => false, 'message' => "Field '{$field}' is required"];
                }
            }
            
            // Validate user
            if (!$this->validateUser($data['user_id'], $token)) {
                return ['success' => false, 'message' => 'User validation failed'];
            }
            
            // Validate dates first
            $startTime = new DateTime($data['start_time']);
            $endTime = new DateTime($data['end_time']);
            $now = new DateTime();
            
            if ($startTime < $now) {
                return ['success' => false, 'message' => 'Start time must be in the future'];
            }
            
            if ($endTime <= $startTime) {
                return ['success' => false, 'message' => 'End time must be after start time'];
            }
            
            // ✅ Validate vehicle with time range
            $vehicleValidation = $this->validateVehicle(
                $data['vehicle_id'], 
                $data['start_time'], 
                $data['end_time']
            );
            
            if (!$vehicleValidation['exists']) {
                return ['success' => false, 'message' => 'Vehicle unit not found'];
            }
            
            if (!$vehicleValidation['available']) {
                $reason = $vehicleValidation['reason'] ?? 'Vehicle is not available';
                return ['success' => false, 'message' => $reason];
            }
            
            $vehicle = $vehicleValidation['vehicle'];
            
            // Calculate cost from vehicle data
            $days = max(1, $endTime->diff($startTime)->days);
            $dailyRate = $vehicle['catalog']['daily_rate'];
            $calculatedCost = $days * $dailyRate;
            
            // Use provided total_cost if available (from checkout calculation)
            $finalCost = isset($data['total_cost']) ? $data['total_cost'] : $calculatedCost;
            
            // Handle promo code
            $promoCode = null;
            if (!empty($data['promo_code'])) {
                $promoCheck = $this->promotion->validate($data['promo_code']);
                if ($promoCheck['valid']) {
                    $promoCode = $data['promo_code'];
                }
            }
            
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
                'total_cost' => $finalCost,
                'promo_code' => $promoCode,
                'status' => 'Pending'
            ];
            
            $result = $this->rental->create($rentalData);
            
            // Update vehicle status
            $this->updateVehicleStatus($data['vehicle_id'], 'Rented');
            
            return [
                'success' => true,
                'message' => 'Rental created successfully',
                'data' => [
                    'rental_id' => $result['rental_id'],
                    'vehicle' => $vehicle['catalog']['brand'] . ' ' . $vehicle['catalog']['model'],
                    'license_plate' => $vehicle['license_plate'],
                    'promo_applied' => $promoCode
                ]
            ];
            
        } catch (Exception $e) {
            error_log("RentalService::createRental error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
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