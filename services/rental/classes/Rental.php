<?php
// services/rental/classes/Rental.php
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class Rental {
    private $serviceName = "rental";

    /**
     * Get all rentals with filters
     */
    public function getAll($filters = []) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $sql = "SELECT * FROM Rentals WHERE 1=1";
            $params = [];
            
            // Filter by user_id
            if (!empty($filters['user_id'])) {
                $sql .= " AND user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            // Filter by status
            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }
            
            // Filter by vehicle_id
            if (!empty($filters['vehicle_id'])) {
                $sql .= " AND vehicle_id = ?";
                $params[] = $filters['vehicle_id'];
            }
            
            // Filter by date range
            if (!empty($filters['start_date'])) {
                $sql .= " AND start_time >= ?";
                $params[] = $filters['start_date'];
            }
            
            if (!empty($filters['end_date'])) {
                $sql .= " AND end_time <= ?";
                $params[] = $filters['end_date'];
            }
            
            // Order by
            $orderBy = $filters['order_by'] ?? 'created_at';
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
            error_log("Rental::getAll error: " . $e->getMessage());
            throw new Exception("Failed to fetch rentals");
        }
    }

    /**
     * Get rental by ID
     */
    public function getById($rentalId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("SELECT * FROM Rentals WHERE rental_id = ?");
            $stmt->execute([$rentalId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Rental::getById error: " . $e->getMessage());
            throw new Exception("Failed to fetch rental");
        }
    }

    /**
     * Create new rental
     */
public function create($data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Thêm 'promo_code' vào câu lệnh SQL
            $stmt = $db->prepare("
                INSERT INTO Rentals (
                    user_id, vehicle_id, start_time, end_time,
                    pickup_location, dropoff_location, total_cost, status, promo_code
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['user_id'],
                $data['vehicle_id'],
                $data['start_time'],
                $data['end_time'],
                $data['pickup_location'],
                $data['dropoff_location'],
                $data['total_cost'],
                $data['status'] ?? 'Pending',
                $data['promo_code'] ?? null // ✅ Thêm tham số promo_code (null nếu không có)
            ]);
            
            return [
                'rental_id' => $db->lastInsertId()
            ];
            
        } catch (PDOException $e) {
            error_log("Rental::create error: " . $e->getMessage());
            throw new Exception("Failed to create rental");
        }
    }

    /**
     * Update rental
     */
    public function update($rentalId, $data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $fields = [];
            $params = [];
            
            $allowedFields = [
                'start_time', 'end_time', 'pickup_location', 
                'dropoff_location', 'total_cost', 'status', 'promo_code'
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
            
            $params[] = $rentalId;
            
            $sql = "UPDATE Rentals SET " . implode(', ', $fields) . " WHERE rental_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Rental not found");
            }
            
            return $this->getById($rentalId);
            
        } catch (PDOException $e) {
            error_log("Rental::update error: " . $e->getMessage());
            throw new Exception("Failed to update rental");
        }
    }

    /**
     * Update rental status
     */
    public function updateStatus($rentalId, $status) {
        $validStatuses = ['Pending', 'Ongoing', 'Completed', 'Cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status");
        }
        
        return $this->update($rentalId, ['status' => $status]);
    }

    /**
     * Cancel rental
     */
    public function cancel($rentalId) {
        try {
            $rental = $this->getById($rentalId);
            
            if (!$rental) {
                throw new Exception("Rental not found");
            }
            
            if ($rental['status'] === 'Completed') {
                throw new Exception("Cannot cancel completed rental");
            }
            
            if ($rental['status'] === 'Cancelled') {
                throw new Exception("Rental already cancelled");
            }
            
            return $this->updateStatus($rentalId, 'Cancelled');
            
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get user's rental statistics
     */
    public function getUserStats($userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_rentals,
                    SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as active_rentals,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_rentals,
                    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_rentals,
                    SUM(CASE WHEN status = 'Completed' THEN total_cost ELSE 0 END) as total_spent
                FROM Rentals 
                WHERE user_id = ?
            ");
            
            $stmt->execute([$userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Rental::getUserStats error: " . $e->getMessage());
            throw new Exception("Failed to fetch user statistics");
        }
    }

    /**
     * Check vehicle availability for date range
     */
    public function checkAvailability($vehicleId, $startTime, $endTime, $excludeRentalId = null) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $sql = "
                SELECT COUNT(*) as conflicting_rentals
                FROM Rentals
                WHERE vehicle_id = ?
                AND status IN ('Pending', 'Ongoing')
                AND (
                    (start_time <= ? AND end_time >= ?)
                    OR (start_time <= ? AND end_time >= ?)
                    OR (start_time >= ? AND end_time <= ?)
                )
            ";
            
            $params = [$vehicleId, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime];
            
            if ($excludeRentalId) {
                $sql .= " AND rental_id != ?";
                $params[] = $excludeRentalId;
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['conflicting_rentals'] == 0;
            
        } catch (PDOException $e) {
            error_log("Rental::checkAvailability error: " . $e->getMessage());
            throw new Exception("Failed to check availability");
        }
    }

    /**
     * Get rentals by date range
     */
    public function getByDateRange($startDate, $endDate, $filters = []) {
        $filters['start_date'] = $startDate;
        $filters['end_date'] = $endDate;
        return $this->getAll($filters);
    }

    /**
     * Delete rental
     */
    public function delete($rentalId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $rental = $this->getById($rentalId);
            if (!$rental) {
                throw new Exception("Rental not found");
            }
            
            if ($rental['status'] === 'Ongoing') {
                throw new Exception("Cannot delete ongoing rental");
            }
            
            $stmt = $db->prepare("DELETE FROM Rentals WHERE rental_id = ?");
            $stmt->execute([$rentalId]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Rental::delete error: " . $e->getMessage());
            throw new Exception("Failed to delete rental");
        }
    }
}