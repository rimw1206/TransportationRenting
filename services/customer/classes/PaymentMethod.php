<?php 
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class PaymentMethod {
    private $serviceName = "customer";

    public function getByUserId($userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                SELECT * FROM PaymentMethod 
                WHERE user_id = ? 
                ORDER BY is_default DESC, method_id DESC
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("PaymentMethod::getByUserId error: " . $e->getMessage());
            throw new Exception("Failed to fetch payment methods");
        }
    }

    public function create($data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // If setting as default, unset other defaults
            if (isset($data['is_default']) && $data['is_default']) {
                $stmt = $db->prepare("UPDATE PaymentMethod SET is_default = 0 WHERE user_id = ?");
                $stmt->execute([$data['user_id']]);
            }
            
            $stmt = $db->prepare("
                INSERT INTO PaymentMethod (
                    user_id, type, provider, account_number, 
                    expiry_date, is_default
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['user_id'],
                $data['type'],
                $data['provider'],
                $data['account_number'],
                $data['expiry_date'] ?? null,
                $data['is_default'] ?? 0
            ]);
            
            return [
                'method_id' => $db->lastInsertId(),
                'user_id' => $data['user_id']
            ];
            
        } catch (PDOException $e) {
            error_log("PaymentMethod::create error: " . $e->getMessage());
            throw new Exception("Failed to create payment method");
        }
    }

    public function delete($methodId, $userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                DELETE FROM PaymentMethod 
                WHERE method_id = ? AND user_id = ?
            ");
            $stmt->execute([$methodId, $userId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("PaymentMethod::delete error: " . $e->getMessage());
            throw new Exception("Failed to delete payment method");
        }
    }

    public function setDefault($methodId, $userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Unset all defaults
            $stmt = $db->prepare("UPDATE PaymentMethod SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Set new default
            $stmt = $db->prepare("
                UPDATE PaymentMethod 
                SET is_default = 1 
                WHERE method_id = ? AND user_id = ?
            ");
            $stmt->execute([$methodId, $userId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("PaymentMethod::setDefault error: " . $e->getMessage());
            throw new Exception("Failed to set default payment method");
        }
    }
}
