<?php 
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class RentalHistory {
    private $serviceName = "customer";

    public function getByUserId($userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                SELECT * FROM RentalHistory 
                WHERE user_id = ? 
                ORDER BY rented_at DESC
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("RentalHistory::getByUserId error: " . $e->getMessage());
            throw new Exception("Failed to fetch rental history");
        }
    }

    public function create($data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                INSERT INTO RentalHistory (
                    user_id, rental_id, rented_at, returned_at, total_cost
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['user_id'],
                $data['rental_id'],
                $data['rented_at'],
                $data['returned_at'] ?? null,
                $data['total_cost']
            ]);
            
            return $db->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("RentalHistory::create error: " . $e->getMessage());
            throw new Exception("Failed to create rental history");
        }
    }
}
