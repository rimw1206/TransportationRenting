<?php
// services/vehicle/classes/Vehicle.php - FIXED WITH createVehicle METHOD
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class Vehicle {
    private $db;

    public function __construct() {
        $this->db = DatabaseManager::getInstance('vehicle');
    }

    /**
     * Create new vehicle catalog
     */
    public function createCatalog($data) {
        $query = "
            INSERT INTO VehicleCatalog 
            (type, brand, model, year, daily_rate, seats, transmission, fuel_type, description, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $data['type'],
            $data['brand'],
            $data['model'],
            $data['year'],
            $data['daily_rate'],
            $data['seats'] ?? null,
            $data['transmission'] ?? null,
            $data['fuel_type'] ?? null,
            $data['description'] ?? null,
            $data['is_active'] ?? true
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Create vehicle unit
     */
    public function createUnit($data) {
        $query = "
            INSERT INTO VehicleUnits 
            (catalog_id, license_plate, odo_km, fuel_level, condition_rating, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $data['catalog_id'],
            $data['license_plate'],
            $data['odo_km'] ?? 0,
            $data['fuel_level'] ?? 100,
            $data['condition_rating'] ?? 5,
            $data['status'] ?? 'Available'
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Get all catalogs with available count
     */
    public function getAllCatalogs($filters = []) {
        $query = "
            SELECT 
                vc.*,
                COUNT(vu.unit_id) as total_units,
                SUM(CASE WHEN vu.status = 'Available' THEN 1 ELSE 0 END) as available_count,
                SUM(CASE WHEN vu.status = 'Rented' THEN 1 ELSE 0 END) as rented_count,
                SUM(CASE WHEN vu.status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance_count
            FROM VehicleCatalog vc
            LEFT JOIN VehicleUnits vu ON vc.catalog_id = vu.catalog_id
            WHERE vc.is_active = TRUE
        ";
        
        $params = [];
        
        if (!empty($filters['type'])) {
            $query .= " AND vc.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['brand'])) {
            $query .= " AND vc.brand = ?";
            $params[] = $filters['brand'];
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND (vc.brand LIKE ? OR vc.model LIKE ? OR vc.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['min_price'])) {
            $query .= " AND vc.daily_rate >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $query .= " AND vc.daily_rate <= ?";
            $params[] = $filters['max_price'];
        }
        
        $query .= " GROUP BY vc.catalog_id";
        $query .= " ORDER BY " . ($filters['order_by'] ?? 'vc.catalog_id') . " " . ($filters['order_dir'] ?? 'DESC');
        
        if (!empty($filters['limit'])) {
            $query .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $query .= " OFFSET " . (int)$filters['offset'];
            }
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get available catalogs (only those with available units)
     */
    public function getAvailableCatalogs($filters = []) {
        $query = "
            SELECT 
                vc.*,
                COUNT(vu.unit_id) as total_units,
                SUM(CASE WHEN vu.status = 'Available' THEN 1 ELSE 0 END) as available_count
            FROM VehicleCatalog vc
            INNER JOIN VehicleUnits vu ON vc.catalog_id = vu.catalog_id
            WHERE vc.is_active = TRUE AND vu.status = 'Available'
        ";
        
        $params = [];
        
        if (!empty($filters['type'])) {
            $query .= " AND vc.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['brand'])) {
            $query .= " AND vc.brand = ?";
            $params[] = $filters['brand'];
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND (vc.brand LIKE ? OR vc.model LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['min_price'])) {
            $query .= " AND vc.daily_rate >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $query .= " AND vc.daily_rate <= ?";
            $params[] = $filters['max_price'];
        }
        
        $query .= " GROUP BY vc.catalog_id HAVING available_count > 0";
        $query .= " ORDER BY vc.catalog_id DESC";
        
        if (!empty($filters['limit'])) {
            $query .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $query .= " OFFSET " . (int)$filters['offset'];
            }
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get catalog by ID
     */
    public function getCatalogById($catalogId) {
        $query = "
            SELECT 
                vc.*,
                COUNT(vu.unit_id) as total_units,
                SUM(CASE WHEN vu.status = 'Available' THEN 1 ELSE 0 END) as available_count
            FROM VehicleCatalog vc
            LEFT JOIN VehicleUnits vu ON vc.catalog_id = vu.catalog_id
            WHERE vc.catalog_id = ?
            GROUP BY vc.catalog_id
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$catalogId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
/**
     * Get available units for catalog at location/time
     */
    public function getAvailableUnits($catalogId, $location, $startTime = null, $endTime = null) {
        try {
            // Base query - get units at location
            $sql = "
                SELECT 
                    u.unit_id,
                    u.catalog_id,
                    u.license_plate,
                    u.status,
                    u.condition_rating,
                    u.odo_km,
                    u.fuel_level,
                    u.current_location,
                    u.parking_spot,
                    c.brand,
                    c.model,
                    c.type,
                    c.year,
                    c.color,
                    c.daily_rate
                FROM VehicleUnits u
                JOIN VehicleCatalog c ON u.catalog_id = c.catalog_id
                WHERE u.catalog_id = ?
                    AND u.current_location = ?
                    AND u.status = 'Available'
                    AND c.is_active = TRUE
            ";
            
            $params = [$catalogId, $location];
            
            // If time range provided, exclude units that are rented during that period
            if ($startTime && $endTime) {
                $sql .= "
                    AND u.unit_id NOT IN (
                        SELECT vehicle_id 
                        FROM rental_service_db.Rentals
                        WHERE vehicle_id = u.unit_id
                            AND status IN ('Pending', 'Ongoing')
                            AND (
                                (start_time <= ? AND end_time >= ?)
                                OR (start_time >= ? AND start_time <= ?)
                                OR (end_time >= ? AND end_time <= ?)
                            )
                    )
                ";
                $params[] = $endTime;
                $params[] = $startTime;
                $params[] = $startTime;
                $params[] = $endTime;
                $params[] = $startTime;
                $params[] = $endTime;
            }
            
            $sql .= " ORDER BY u.condition_rating DESC, u.odo_km ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $units = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $units[] = [
                    'unit_id' => (int)$row['unit_id'],
                    'catalog_id' => (int)$row['catalog_id'],
                    'license_plate' => $row['license_plate'],
                    'status' => $row['status'],
                    'condition_rating' => (float)$row['condition_rating'],
                    'odo_km' => (int)$row['odo_km'],
                    'fuel_level' => (float)$row['fuel_level'],
                    'current_location' => $row['current_location'],
                    'parking_spot' => $row['parking_spot'],
                    'vehicle_info' => [
                        'brand' => $row['brand'],
                        'model' => $row['model'],
                        'type' => $row['type'],
                        'year' => (int)$row['year'],
                        'color' => $row['color'],
                        'daily_rate' => (float)$row['daily_rate']
                    ]
                ];
            }
            
            return $units;
            
        } catch (PDOException $e) {
            error_log('Get available units error: ' . $e->getMessage());
            throw new Exception('Database error');
        }
    }
    /**
     * Get available unit count for a catalog
     */
    public function getAvailableCount($catalogId) {
        $query = "
            SELECT COUNT(*) as count
            FROM VehicleUnits
            WHERE catalog_id = ? AND status = 'Available'
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$catalogId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Get available units for a catalog (ordered by unit_id)
     */
    public function getAvailableUnitsByCatalog($catalogId, $limit = 1) {
        $query = "
            SELECT unit_id, license_plate, catalog_id, odo_km, fuel_level, condition_rating
            FROM VehicleUnits
            WHERE catalog_id = ? AND status = 'Available'
            ORDER BY unit_id ASC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$catalogId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update unit status
     */
    public function updateUnitStatus($unitId, $status) {
        $validStatuses = ['Available', 'Rented', 'Maintenance', 'Retired'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid status');
        }
        
        $query = "UPDATE VehicleUnits SET status = ? WHERE unit_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$status, $unitId]);
        
        return ['unit_id' => $unitId, 'status' => $status];
    }

    /**
     * Record vehicle usage
     */
    public function recordUsage($unitId, $rentalId, $startOdo, $endOdo = null, $fuelUsed = null) {
        $query = "
            INSERT INTO VehicleUsageHistory 
            (unit_id, rental_id, start_datetime, start_odo, end_datetime, end_odo, fuel_used)
            VALUES (?, ?, NOW(), ?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $unitId,
            $rentalId,
            $startOdo,
            $endOdo ? date('Y-m-d H:i:s') : null,
            $endOdo,
            $fuelUsed
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Search vehicles
     */
    public function searchVehicles($searchTerm, $filters = []) {
        $query = "
            SELECT 
                vc.*,
                COUNT(vu.unit_id) as total_units,
                SUM(CASE WHEN vu.status = 'Available' THEN 1 ELSE 0 END) as available_count
            FROM VehicleCatalog vc
            LEFT JOIN VehicleUnits vu ON vc.catalog_id = vu.catalog_id
            WHERE vc.is_active = TRUE
            AND (vc.brand LIKE ? OR vc.model LIKE ? OR vc.description LIKE ?)
        ";
        
        $params = [
            '%' . $searchTerm . '%',
            '%' . $searchTerm . '%',
            '%' . $searchTerm . '%'
        ];
        
        if (!empty($filters['type'])) {
            $query .= " AND vc.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $query .= " AND vu.status = ?";
            $params[] = $filters['status'];
        }
        
        $query .= " GROUP BY vc.catalog_id";
        
        if (!empty($filters['limit'])) {
            $query .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get statistics
     */
    public function getStats() {
        $query = "
            SELECT 
                COUNT(DISTINCT vc.catalog_id) as total_vehicles,
                SUM(CASE WHEN vu.status = 'Available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN vu.status = 'Rented' THEN 1 ELSE 0 END) as rented,
                SUM(CASE WHEN vu.status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance,
                SUM(CASE WHEN vc.type = 'Car' AND vu.status = 'Available' THEN 1 ELSE 0 END) as cars,
                SUM(CASE WHEN vc.type = 'Motorbike' AND vu.status = 'Available' THEN 1 ELSE 0 END) as motorbikes,
                SUM(CASE WHEN vc.type = 'Bicycle' AND vu.status = 'Available' THEN 1 ELSE 0 END) as bicycles,
                SUM(CASE WHEN vc.type = 'Electric_Scooter' AND vu.status = 'Available' THEN 1 ELSE 0 END) as scooters
            FROM VehicleCatalog vc
            LEFT JOIN VehicleUnits vu ON vc.catalog_id = vu.catalog_id
            WHERE vc.is_active = TRUE
        ";
        
        $stmt = $this->db->query($query);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    /**
     * Get unit by ID with full catalog info
     */
    public function getUnitById($unitId) {
        try {
            $query = "
                SELECT 
                    u.unit_id,
                    u.catalog_id,
                    u.license_plate,
                    u.status,
                    u.condition_rating,
                    u.odo_km,
                    u.fuel_level,
                    u.current_location,
                    u.parking_spot,
                    c.brand,
                    c.model,
                    c.type,
                    c.year,
                    c.color,
                    c.daily_rate,
                    c.hourly_rate,
                    c.weekly_rate,
                    c.monthly_rate,
                    c.seats,
                    c.engine_capacity,
                    c.transmission,
                    c.fuel_type,
                    c.is_active
                FROM VehicleUnits u
                INNER JOIN VehicleCatalog c ON u.catalog_id = c.catalog_id
                WHERE u.unit_id = ?
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$unitId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return null;
            }
            
            // Format response
            return [
                'unit_id' => (int)$result['unit_id'],
                'catalog_id' => (int)$result['catalog_id'],
                'license_plate' => $result['license_plate'],
                'status' => $result['status'],
                'condition_rating' => (float)$result['condition_rating'],
                'odo_km' => (int)$result['odo_km'],
                'fuel_level' => (float)$result['fuel_level'],
                'current_location' => $result['current_location'],
                'parking_spot' => $result['parking_spot'],
                'catalog' => [
                    'brand' => $result['brand'],
                    'model' => $result['model'],
                    'type' => $result['type'],
                    'year' => (int)$result['year'],
                    'color' => $result['color'],
                    'daily_rate' => (float)$result['daily_rate'],
                    'hourly_rate' => (float)$result['hourly_rate'],
                    'weekly_rate' => (float)$result['weekly_rate'],
                    'monthly_rate' => (float)$result['monthly_rate'],
                    'seats' => $result['seats'] ? (int)$result['seats'] : null,
                    'engine_capacity' => $result['engine_capacity'] ? (int)$result['engine_capacity'] : null,
                    'transmission' => $result['transmission'],
                    'fuel_type' => $result['fuel_type'],
                    'is_active' => (bool)$result['is_active']
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Vehicle::getUnitById error: " . $e->getMessage());
            throw new Exception("Failed to get vehicle unit");
        }
    }

    /**
     * Check if unit is available for rental period (including current rentals)
     */
    public function isUnitAvailable($unitId, $startTime, $endTime) {
        try {
            $query = "
                SELECT u.status, u.unit_id
                FROM VehicleUnits u
                WHERE u.unit_id = ?
                AND u.status = 'Available'
                AND NOT EXISTS (
                    SELECT 1 
                    FROM rental_service_db.Rentals r
                    WHERE r.vehicle_id = u.unit_id
                    AND r.status IN ('Pending', 'Ongoing')
                    AND (
                        (r.start_time <= ? AND r.end_time >= ?)
                        OR (r.start_time >= ? AND r.start_time < ?)
                        OR (r.end_time > ? AND r.end_time <= ?)
                    )
                )
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $unitId,
                $endTime, $startTime,  // Overlap check 1
                $startTime, $endTime,  // Overlap check 2
                $startTime, $endTime   // Overlap check 3
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result !== false;
            
        } catch (PDOException $e) {
            error_log("Vehicle::isUnitAvailable error: " . $e->getMessage());
            return false;
        }
    }
}