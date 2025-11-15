<?php
// services/vehicle/classes/Vehicle.php
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class Vehicle {
    private $serviceName = "vehicle";

    /**
     * Get all vehicles with filters
     */
    public function getAll($filters = []) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $sql = "SELECT * FROM Vehicles WHERE 1=1";
            $params = [];
            
            // Filter by status
            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }
            
            // Filter by type
            if (!empty($filters['type'])) {
                $sql .= " AND type = ?";
                $params[] = $filters['type'];
            }
            
            // Filter by brand
            if (!empty($filters['brand'])) {
                $sql .= " AND brand LIKE ?";
                $params[] = "%{$filters['brand']}%";
            }
            
            // Search query
            if (!empty($filters['search'])) {
                $sql .= " AND (brand LIKE ? OR model LIKE ? OR license_plate LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Price range
            if (!empty($filters['min_price'])) {
                $sql .= " AND daily_rate >= ?";
                $params[] = $filters['min_price'];
            }
            
            if (!empty($filters['max_price'])) {
                $sql .= " AND daily_rate <= ?";
                $params[] = $filters['max_price'];
            }
            
            // Order by
            $orderBy = $filters['order_by'] ?? 'vehicle_id';
            $orderDir = $filters['order_dir'] ?? 'DESC';
            $sql .= " ORDER BY {$orderBy} {$orderDir}";
            
            // Pagination
            if (isset($filters['limit'])) {
                $sql .= " LIMIT ?";
                $params[] = (int)$filters['limit'];
                
                if (isset($filters['offset'])) {
                    $sql .= " OFFSET ?";
                    $params[] = (int)$filters['offset'];
                }
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Vehicle::getAll error: " . $e->getMessage());
            throw new Exception("Failed to fetch vehicles");
        }
    }

    /**
     * Get vehicle by ID
     */
    public function getById($vehicleId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("SELECT * FROM Vehicles WHERE vehicle_id = ?");
            $stmt->execute([$vehicleId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Vehicle::getById error: " . $e->getMessage());
            throw new Exception("Failed to fetch vehicle");
        }
    }

    /**
     * Create new vehicle
     */
    public function create($data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                INSERT INTO Vehicles (
                    license_plate, brand, model, type, status,
                    odo_km, fuel_level, location, registration_date,
                    hourly_rate, daily_rate
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['license_plate'],
                $data['brand'],
                $data['model'],
                $data['type'],
                $data['status'] ?? 'Available',
                $data['odo_km'] ?? 0,
                $data['fuel_level'] ?? 100.00,
                $data['location'] ?? null,
                $data['registration_date'] ?? null,
                $data['hourly_rate'] ?? null,
                $data['daily_rate']
            ]);
            
            return [
                'vehicle_id' => $db->lastInsertId(),
                'license_plate' => $data['license_plate']
            ];
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                throw new Exception("License plate already exists");
            }
            error_log("Vehicle::create error: " . $e->getMessage());
            throw new Exception("Failed to create vehicle");
        }
    }

    /**
     * Update vehicle
     */
    public function update($vehicleId, $data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $fields = [];
            $params = [];
            
            $allowedFields = [
                'license_plate', 'brand', 'model', 'type', 'status',
                'odo_km', 'fuel_level', 'location', 'registration_date',
                'hourly_rate', 'daily_rate'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                throw new Exception("No fields to update");
            }
            
            $params[] = $vehicleId;
            
            $sql = "UPDATE Vehicles SET " . implode(', ', $fields) . " WHERE vehicle_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Vehicle not found");
            }
            
            return $this->getById($vehicleId);
            
        } catch (PDOException $e) {
            error_log("Vehicle::update error: " . $e->getMessage());
            throw new Exception("Failed to update vehicle");
        }
    }

    /**
     * Delete vehicle
     */
    public function delete($vehicleId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Check if vehicle exists
            $vehicle = $this->getById($vehicleId);
            if (!$vehicle) {
                throw new Exception("Vehicle not found");
            }
            
            // Check if vehicle is currently rented
            if ($vehicle['status'] === 'Rented') {
                throw new Exception("Cannot delete rented vehicle");
            }
            
            $stmt = $db->prepare("DELETE FROM Vehicles WHERE vehicle_id = ?");
            $stmt->execute([$vehicleId]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Vehicle::delete error: " . $e->getMessage());
            throw new Exception("Failed to delete vehicle");
        }
    }

    /**
     * Update vehicle status
     */
    public function updateStatus($vehicleId, $status) {
        $validStatuses = ['Available', 'Rented', 'Maintenance', 'Retired'];
        
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status");
        }
        
        return $this->update($vehicleId, ['status' => $status]);
    }

    /**
     * Get available vehicles
     */
    public function getAvailable($filters = []) {
        $filters['status'] = 'Available';
        return $this->getAll($filters);
    }

    /**
     * Get vehicle statistics
     */
    public function getStats() {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->query("
                SELECT 
                    COUNT(*) as total_vehicles,
                    SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN status = 'Rented' THEN 1 ELSE 0 END) as rented,
                    SUM(CASE WHEN status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance,
                    SUM(CASE WHEN status = 'Retired' THEN 1 ELSE 0 END) as retired,
                    SUM(CASE WHEN type = 'Car' THEN 1 ELSE 0 END) as cars,
                    SUM(CASE WHEN type = 'Motorbike' THEN 1 ELSE 0 END) as motorbikes,
                    SUM(CASE WHEN type = 'Bicycle' THEN 1 ELSE 0 END) as bicycles,
                    SUM(CASE WHEN type = 'Electric_Scooter' THEN 1 ELSE 0 END) as scooters
                FROM Vehicles
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Vehicle::getStats error: " . $e->getMessage());
            throw new Exception("Failed to fetch statistics");
        }
    }

    /**
     * Record vehicle usage
     */
    public function recordUsage($vehicleId, $rentalId, $startOdo, $endOdo = null, $fuelUsed = null) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                INSERT INTO VehicleUsageHistory (vehicle_id, rental_id, start_odo, end_odo, fuel_used)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$vehicleId, $rentalId, $startOdo, $endOdo, $fuelUsed]);
            
            return $db->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Vehicle::recordUsage error: " . $e->getMessage());
            throw new Exception("Failed to record usage");
        }
    }

    /**
     * Get vehicle usage history
     */
    public function getUsageHistory($vehicleId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                SELECT * FROM VehicleUsageHistory 
                WHERE vehicle_id = ? 
                ORDER BY usage_id DESC
            ");
            
            $stmt->execute([$vehicleId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Vehicle::getUsageHistory error: " . $e->getMessage());
            throw new Exception("Failed to fetch usage history");
        }
    }
}