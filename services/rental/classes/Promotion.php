<?php
// services/rental/classes/Promotion.php
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class Promotion {
    private $serviceName = "rental";

    /**
     * Get all promotions with filters
     */
    public function getAll($filters = []) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $sql = "SELECT * FROM Promotion WHERE 1=1";
            $params = [];
            
            // Filter by active status
            if (isset($filters['active'])) {
                $sql .= " AND active = ?";
                $params[] = (bool)$filters['active'];
            }
            
            // Filter by code
            if (!empty($filters['code'])) {
                $sql .= " AND code = ?";
                $params[] = $filters['code'];
            }
            
            // Filter by valid date range
            if (isset($filters['valid_now'])) {
                $now = date('Y-m-d');
                $sql .= " AND valid_from <= ? AND valid_to >= ?";
                $params[] = $now;
                $params[] = $now;
            }
            
            // Order by
            $sql .= " ORDER BY promo_id DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Promotion::getAll error: " . $e->getMessage());
            throw new Exception("Failed to fetch promotions");
        }
    }

    /**
     * Get promotion by ID
     */
    public function getById($promoId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("SELECT * FROM Promotion WHERE promo_id = ?");
            $stmt->execute([$promoId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Promotion::getById error: " . $e->getMessage());
            throw new Exception("Failed to fetch promotion");
        }
    }

    /**
     * Get promotion by code
     */
    public function getByCode($code) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("SELECT * FROM Promotion WHERE code = ?");
            $stmt->execute([strtoupper($code)]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Promotion::getByCode error: " . $e->getMessage());
            throw new Exception("Failed to fetch promotion");
        }
    }

    /**
     * Get all active promotions
     */
    public function getActive() {
        return $this->getAll(['active' => true, 'valid_now' => true]);
    }

    /**
     * Validate promotion code
     */
    public function validate($code) {
        try {
            $promo = $this->getByCode($code);
            
            if (!$promo) {
                return [
                    'valid' => false,
                    'message' => 'Mã khuyến mãi không tồn tại'
                ];
            }
            
            if (!$promo['active']) {
                return [
                    'valid' => false,
                    'message' => 'Mã khuyến mãi không còn hiệu lực'
                ];
            }
            
            $now = new DateTime();
            $validFrom = new DateTime($promo['valid_from']);
            $validTo = new DateTime($promo['valid_to']);
            
            if ($now < $validFrom) {
                return [
                    'valid' => false,
                    'message' => 'Mã khuyến mãi chưa có hiệu lực'
                ];
            }
            
            if ($now > $validTo) {
                return [
                    'valid' => false,
                    'message' => 'Mã khuyến mãi đã hết hạn'
                ];
            }
            
            return [
                'valid' => true,
                'promo' => $promo,
                'message' => 'Mã khuyến mãi hợp lệ'
            ];
            
        } catch (Exception $e) {
            error_log("Promotion::validate error: " . $e->getMessage());
            throw new Exception("Failed to validate promotion");
        }
    }

    /**
     * Create new promotion
     */
    public function create($data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                INSERT INTO Promotion (
                    code, description, discount_percent,
                    valid_from, valid_to, active
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                strtoupper($data['code']),
                $data['description'] ?? null,
                $data['discount_percent'],
                $data['valid_from'],
                $data['valid_to'],
                $data['active'] ?? true
            ]);
            
            return [
                'promo_id' => $db->lastInsertId()
            ];
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                throw new Exception("Mã khuyến mãi đã tồn tại");
            }
            error_log("Promotion::create error: " . $e->getMessage());
            throw new Exception("Failed to create promotion");
        }
    }

    /**
     * Update promotion
     */
    public function update($promoId, $data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $fields = [];
            $params = [];
            
            $allowedFields = [
                'code', 'description', 'discount_percent',
                'valid_from', 'valid_to', 'active'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "{$field} = ?";
                    $params[] = $field === 'code' ? strtoupper($data[$field]) : $data[$field];
                }
            }
            
            if (empty($fields)) {
                throw new Exception("No fields to update");
            }
            
            $params[] = $promoId;
            
            $sql = "UPDATE Promotion SET " . implode(', ', $fields) . " WHERE promo_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Promotion not found");
            }
            
            return $this->getById($promoId);
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                throw new Exception("Mã khuyến mãi đã tồn tại");
            }
            error_log("Promotion::update error: " . $e->getMessage());
            throw new Exception("Failed to update promotion");
        }
    }

    /**
     * Toggle promotion active status
     */
    public function toggleActive($promoId) {
        try {
            $promo = $this->getById($promoId);
            
            if (!$promo) {
                throw new Exception("Promotion not found");
            }
            
            return $this->update($promoId, ['active' => !$promo['active']]);
            
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Delete promotion
     */
    public function delete($promoId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("DELETE FROM Promotion WHERE promo_id = ?");
            $stmt->execute([$promoId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Promotion not found");
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Promotion::delete error: " . $e->getMessage());
            throw new Exception("Failed to delete promotion");
        }
    }

    /**
     * Calculate discount amount
     */
    public function calculateDiscount($totalAmount, $discountPercent) {
        return round($totalAmount * $discountPercent / 100, 2);
    }

    /**
     * Apply promotion to rental
     */
    public function applyToRental($code, $totalCost) {
        try {
            $validation = $this->validate($code);
            
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            
            $promo = $validation['promo'];
            $discount = $this->calculateDiscount($totalCost, $promo['discount_percent']);
            $finalCost = $totalCost - $discount;
            
            return [
                'success' => true,
                'promo' => $promo,
                'original_cost' => $totalCost,
                'discount_amount' => $discount,
                'final_cost' => $finalCost,
                'message' => "Đã áp dụng mã giảm giá {$promo['discount_percent']}%"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}