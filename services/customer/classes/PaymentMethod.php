<?php
/**
 * services/customer/classes/PaymentMethod.php
 * FIXED VERSION - Clean output, proper error handling
 */

require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class PaymentMethod {
    private $serviceName = "customer";

    /**
     * Get all payment methods for a user
     */
    public function getByUserId($userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                SELECT 
                    method_id,
                    user_id,
                    type,
                    provider,
                    is_default,
                    created_at
                FROM PaymentMethod 
                WHERE user_id = ? 
                ORDER BY is_default DESC, created_at DESC
            ");
            
            $stmt->execute([$userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert is_default to boolean
            if ($results) {
                foreach ($results as &$row) {
                    $row['is_default'] = (bool)$row['is_default'];
                    $row['method_id'] = (int)$row['method_id'];
                    $row['user_id'] = (int)$row['user_id'];
                }
            }
            
            return $results ?: [];
            
        } catch (PDOException $e) {
            error_log("PaymentMethod::getByUserId error: " . $e->getMessage());
            throw new Exception("Failed to fetch payment methods: " . $e->getMessage());
        }
    }

    /**
     * Get a specific payment method
     */
    public function getById($methodId, $userId = null) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $sql = "SELECT * FROM PaymentMethod WHERE method_id = ?";
            $params = [$methodId];
            
            if ($userId !== null) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $result['is_default'] = (bool)$result['is_default'];
                $result['method_id'] = (int)$result['method_id'];
                $result['user_id'] = (int)$result['user_id'];
            }
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("PaymentMethod::getById error: " . $e->getMessage());
            throw new Exception("Failed to fetch payment method: " . $e->getMessage());
        }
    }

    /**
     * Create new payment method
     */
    public function create($data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Validate payment type
            $validTypes = ['COD', 'VNPayQR'];
            if (!isset($data['type']) || !in_array($data['type'], $validTypes)) {
                throw new Exception("Invalid payment type. Must be COD or VNPayQR");
            }
            
            // ✅ SIMPLIFIED: Both types don't need account_number
            // COD: Cash payment when receiving vehicle
            // VNPayQR: QR code generated at checkout (no pre-registration needed)
            $data['provider'] = null;
            $data['account_number'] = null;
            
            if ($data['type'] === 'VNPayQR') {
                $data['provider'] = 'VNPay';
            }
            
            // If setting as default, unset other defaults
            if (isset($data['is_default']) && $data['is_default']) {
                $stmt = $db->prepare("UPDATE PaymentMethod SET is_default = 0 WHERE user_id = ?");
                $stmt->execute([$data['user_id']]);
            }
            
            // Insert new payment method
            $stmt = $db->prepare("
                INSERT INTO PaymentMethod (
                    user_id, 
                    type, 
                    provider, 
                    is_default,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $data['user_id'],
                $data['type'],
                $data['provider'],
                $data['is_default'] ?? 0
            ]);
            
            $methodId = $db->lastInsertId();
            
            // Return created method
            return $this->getById($methodId);
            
        } catch (PDOException $e) {
            error_log("PaymentMethod::create error: " . $e->getMessage());
            throw new Exception("Failed to create payment method: " . $e->getMessage());
        }
    }

    /**
     * Delete payment method
     */
    public function delete($methodId, $userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Check if it exists and belongs to user
            $method = $this->getById($methodId, $userId);
            if (!$method) {
                throw new Exception("Payment method not found");
            }
            
            // Don't allow deleting default payment method if it's the only one
            if ($method['is_default']) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM PaymentMethod WHERE user_id = ?");
                $stmt->execute([$userId]);
                $count = $stmt->fetchColumn();
                
                if ($count <= 1) {
                    throw new Exception("Cannot delete the only payment method");
                }
            }
            
            // Delete
            $stmt = $db->prepare("
                DELETE FROM PaymentMethod 
                WHERE method_id = ? AND user_id = ?
            ");
            $stmt->execute([$methodId, $userId]);
            
            // If deleted default, set another as default
            if ($method['is_default']) {
                $stmt = $db->prepare("
                    SELECT method_id FROM PaymentMethod 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $newDefaultId = $stmt->fetchColumn();
                
                if ($newDefaultId) {
                    $this->setDefault($newDefaultId, $userId);
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("PaymentMethod::delete error: " . $e->getMessage());
            throw new Exception("Failed to delete payment method: " . $e->getMessage());
        }
    }

    /**
     * Set payment method as default
     */
    public function setDefault($methodId, $userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Check if method exists and belongs to user
            $method = $this->getById($methodId, $userId);
            if (!$method) {
                throw new Exception("Payment method not found");
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Unset all defaults for this user
                $stmt = $db->prepare("UPDATE PaymentMethod SET is_default = 0 WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // ✅ Set new default - BỎ updated_at
                $stmt = $db->prepare("
                    UPDATE PaymentMethod 
                    SET is_default = 1
                    WHERE method_id = ? AND user_id = ?
                ");
                $stmt->execute([$methodId, $userId]);
                
                $db->commit();
                return true;
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
        } catch (PDOException $e) {
            error_log("PaymentMethod::setDefault error: " . $e->getMessage());
            throw new Exception("Failed to set default payment method: " . $e->getMessage());
        }
    }

    /**
     * Get default payment method for user
     */
    public function getDefault($userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                SELECT * FROM PaymentMethod 
                WHERE user_id = ? AND is_default = 1
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no default found, get the first one
            if (!$result) {
                $stmt = $db->prepare("
                    SELECT * FROM PaymentMethod 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if ($result) {
                $result['is_default'] = (bool)$result['is_default'];
                $result['method_id'] = (int)$result['method_id'];
                $result['user_id'] = (int)$result['user_id'];
            }
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("PaymentMethod::getDefault error: " . $e->getMessage());
            throw new Exception("Failed to fetch default payment method: " . $e->getMessage());
        }
    }

    /**
     * Check if user has any payment method
     */
    public function hasPaymentMethod($userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM PaymentMethod WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (PDOException $e) {
            error_log("PaymentMethod::hasPaymentMethod error: " . $e->getMessage());
            throw new Exception("Failed to check payment methods: " . $e->getMessage());
        }
    }
}