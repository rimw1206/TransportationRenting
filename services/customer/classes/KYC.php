<?php 
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class KYC {
    private $serviceName = "customer";

    public function getByUserId($userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("SELECT * FROM KYC WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("KYC::getByUserId error: " . $e->getMessage());
            throw new Exception("Failed to fetch KYC");
        }
    }

    public function create($data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Check if KYC already exists
            $existing = $this->getByUserId($data['user_id']);
            if ($existing) {
                throw new Exception("KYC already exists for this user");
            }
            
            $stmt = $db->prepare("
                INSERT INTO KYC (
                    user_id, identity_number, id_card_front_url, 
                    id_card_back_url, verification_status
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['user_id'],
                $data['identity_number'],
                $data['id_card_front_url'] ?? null,
                $data['id_card_back_url'] ?? null,
                $data['verification_status'] ?? 'Pending'
            ]);
            
            return [
                'kyc_id' => $db->lastInsertId(),
                'user_id' => $data['user_id']
            ];
            
        } catch (PDOException $e) {
            error_log("KYC::create error: " . $e->getMessage());
            throw new Exception("Failed to create KYC");
        }
    }

    public function verify($kycId, $status) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $validStatuses = ['Verified', 'Rejected'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid status");
            }
            
            $stmt = $db->prepare("
                UPDATE KYC 
                SET verification_status = ?, 
                    verified_at = NOW() 
                WHERE kyc_id = ?
            ");
            
            $stmt->execute([$status, $kycId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("KYC::verify error: " . $e->getMessage());
            throw new Exception("Failed to verify KYC");
        }
    }
}
