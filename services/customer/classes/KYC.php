<?php
/**
 * ============================================
 * services/customer/classes/KYC.php
 * KYC Model - Xử lý xác thực danh tính
 * ============================================
 */

require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class KYC {
    private $db;

    public function __construct() {
        $this->db = DatabaseManager::getInstance('customer');
    }

    /**
     * Get KYC by user_id
     */
    public function getByUserId($userId) {
        $query = "
            SELECT 
                kyc_id,
                user_id,
                identity_number,
                id_card_front_url,
                id_card_back_url,
                verified_at,
                verification_status
            FROM KYC
            WHERE user_id = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create KYC record
     */
    public function create($data) {
        // Check if user already has KYC
        $existing = $this->getByUserId($data['user_id']);
        
        if ($existing) {
            throw new Exception('User already has a KYC record');
        }

        $query = "
            INSERT INTO KYC (
                user_id, 
                identity_number, 
                id_card_front_url, 
                id_card_back_url, 
                verification_status
            ) VALUES (?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([
            $data['user_id'],
            $data['identity_number'],
            $data['id_card_front_url'] ?? null,
            $data['id_card_back_url'] ?? null,
            $data['verification_status'] ?? 'Pending'
        ]);
        
        if (!$result) {
            return false;
        }
        
        // Return created KYC
        return $this->getByUserId($data['user_id']);
    }

    /**
     * Update KYC status
     */
    public function updateStatus($userId, $status, $verifiedAt = null) {
        $query = "
            UPDATE KYC
            SET verification_status = ?,
                verified_at = ?
            WHERE user_id = ?
        ";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            $status,
            $verifiedAt ?? ($status === 'Verified' ? date('Y-m-d H:i:s') : null),
            $userId
        ]);
    }

    /**
     * Verify KYC (Admin only)
     */
    public function verify($userId) {
        return $this->updateStatus($userId, 'Verified', date('Y-m-d H:i:s'));
    }

    /**
     * Reject KYC (Admin only)
     */
    public function reject($userId) {
        return $this->updateStatus($userId, 'Rejected');
    }

    /**
     * Get all pending KYC (Admin only)
     */
    public function getPendingList() {
        $query = "
            SELECT 
                k.kyc_id,
                k.user_id,
                k.identity_number,
                k.id_card_front_url,
                k.id_card_back_url,
                k.verification_status,
                u.name,
                u.email,
                u.phone
            FROM KYC k
            INNER JOIN Users u ON k.user_id = u.user_id
            WHERE k.verification_status = 'Pending'
            ORDER BY k.kyc_id DESC
        ";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}