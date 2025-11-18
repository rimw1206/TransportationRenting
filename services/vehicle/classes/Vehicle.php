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
}