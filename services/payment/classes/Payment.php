<?php
/**
 * ================================================
 * services/payment/classes/Payment.php
 * ✅ FIXED: Added method to get payments by rental_id
 * ================================================
 */

require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class Payment {
    private $serviceName = 'payment';
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance($this->serviceName);
    }
    
    /**
     * ✅ NEW: Get transaction(s) by rental_id
     * Handles both single rental and multi-rental payments
     */
    public function getTransactionsByRentalId($rentalId, $userId = null) {
        try {
            // Check RentalPayments junction table first
            $sql = "
                SELECT 
                    t.transaction_id,
                    t.user_id,
                    t.amount as total_amount,
                    t.payment_method,
                    t.payment_gateway,
                    t.transaction_code,
                    t.qr_code_url,
                    t.transaction_date,
                    t.status,
                    t.metadata,
                    rp.amount as rental_portion
                FROM Transactions t
                INNER JOIN RentalPayments rp ON t.transaction_id = rp.transaction_id
                WHERE rp.rental_id = ?
            ";
            
            $params = [$rentalId];
            
            if ($userId !== null) {
                $sql .= " AND t.user_id = ?";
                $params[] = $userId;
            }
            
            $sql .= " ORDER BY t.transaction_date DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("getTransactionsByRentalId error: " . $e->getMessage());
            throw new Exception("Failed to fetch transactions");
        }
    }
    
    /**
     * Create new transaction with multi-rental support
     */
    public function createTransaction($data) {
        $stmt = $this->db->prepare("
            INSERT INTO Transactions (
                user_id, amount, 
                payment_method, payment_gateway, transaction_code,
                qr_code_url, metadata, status, transaction_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Prepare metadata JSON
        $metadata = isset($data['metadata']) ? json_encode($data['metadata']) : null;
        
        $stmt->execute([
            $data['user_id'],
            $data['amount'],
            $data['payment_method'],
            $data['payment_gateway'] ?? null,
            $data['transaction_code'],
            $data['qr_code_url'] ?? null,
            $metadata,
            $data['status'] ?? 'Pending'
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * ✅ NEW: Link rental(s) to transaction via junction table
     */
    public function linkRentalsToTransaction($transactionId, $rentalIds, $amounts = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO RentalPayments (rental_id, transaction_id, amount)
                VALUES (?, ?, ?)
            ");
            
            // If amounts not provided, distribute evenly
            if ($amounts === null) {
                $transaction = $this->getTransactionById($transactionId);
                $totalAmount = $transaction['amount'];
                $perRental = $totalAmount / count($rentalIds);
                $amounts = array_fill(0, count($rentalIds), $perRental);
            }
            
            foreach ($rentalIds as $index => $rentalId) {
                $stmt->execute([
                    $rentalId,
                    $transactionId,
                    $amounts[$index]
                ]);
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("linkRentalsToTransaction error: " . $e->getMessage());
            throw new Exception("Failed to link rentals to transaction");
        }
    }
    
    /**
     * Get transaction by ID
     */
    public function getTransactionById($transactionId, $userId = null) {
        $sql = "
            SELECT 
                t.*,
                i.invoice_number,
                i.invoice_id,
                i.pdf_url
            FROM Transactions t
            LEFT JOIN Invoice i ON t.transaction_id = i.transaction_id
            WHERE t.transaction_id = ?
        ";
        
        $params = [$transactionId];
        
        if ($userId !== null) {
            $sql .= " AND t.user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * ✅ NEW: Get all rentals linked to a transaction
     */
    public function getRentalsByTransactionId($transactionId) {
        try {
            $stmt = $this->db->prepare("
                SELECT rental_id, amount
                FROM RentalPayments
                WHERE transaction_id = ?
            ");
            
            $stmt->execute([$transactionId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("getRentalsByTransactionId error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get transaction by code
     */
    public function getTransactionByCode($transactionCode) {
        $stmt = $this->db->prepare("
            SELECT * FROM Transactions 
            WHERE transaction_code = ?
        ");
        $stmt->execute([$transactionCode]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user transactions with pagination
     */
    public function getUserTransactions($userId, $filters = [], $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        $whereConditions = ['user_id = ?'];
        $params = [$userId];
        
        if (!empty($filters['status'])) {
            $whereConditions[] = 'status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['payment_method'])) {
            $whereConditions[] = 'payment_method = ?';
            $params[] = $filters['payment_method'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) as total 
            FROM Transactions 
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get transactions
        $stmt = $this->db->prepare("
            SELECT 
                t.*,
                i.invoice_number,
                i.invoice_id
            FROM Transactions t
            LEFT JOIN Invoice i ON t.transaction_id = i.transaction_id
            WHERE {$whereClause}
            ORDER BY t.transaction_date DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        
        return [
            'transactions' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total
        ];
    }
    
    /**
     * Update transaction status
     */
    public function updateTransactionStatus($transactionId, $status) {
        $stmt = $this->db->prepare("
            UPDATE Transactions 
            SET status = ?, transaction_date = NOW()
            WHERE transaction_id = ?
        ");
        
        return $stmt->execute([$status, $transactionId]);
    }
    
    /**
     * Create invoice
     */
    public function createInvoice($transactionId, $amount) {
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($transactionId, 6, '0', STR_PAD_LEFT);
        
        $stmt = $this->db->prepare("
            INSERT INTO Invoice (
                transaction_id, invoice_number, 
                issued_date, total_amount
            ) VALUES (?, ?, NOW(), ?)
        ");
        
        $stmt->execute([$transactionId, $invoiceNumber, $amount]);
        
        return [
            'invoice_id' => $this->db->lastInsertId(),
            'invoice_number' => $invoiceNumber
        ];
    }
    
    /**
     * Get invoice by ID
     */
    public function getInvoiceById($invoiceId, $userId = null) {
        $sql = "
            SELECT 
                i.*,
                t.user_id,
                t.amount,
                t.payment_method,
                t.transaction_code,
                t.status as payment_status
            FROM Invoice i
            INNER JOIN Transactions t ON i.transaction_id = t.transaction_id
            WHERE i.invoice_id = ?
        ";
        
        $params = [$invoiceId];
        
        if ($userId !== null) {
            $sql .= " AND t.user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create refund request
     */
    public function createRefund($data) {
        $stmt = $this->db->prepare("
            INSERT INTO Refunds (
                transaction_id, amount, reason, 
                refund_method, status
            ) VALUES (?, ?, ?, ?, 'Pending')
        ");
        
        $stmt->execute([
            $data['transaction_id'],
            $data['amount'],
            $data['reason'],
            $data['refund_method']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get refund by ID
     */
    public function getRefundById($refundId, $userId = null) {
        $sql = "
            SELECT 
                r.*,
                t.user_id,
                t.transaction_code,
                t.payment_method
            FROM Refunds r
            INNER JOIN Transactions t ON r.transaction_id = t.transaction_id
            WHERE r.refund_id = ?
        ";
        
        $params = [$refundId];
        
        if ($userId !== null) {
            $sql .= " AND t.user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if refund exists for transaction
     */
    public function hasActiveRefund($transactionId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM Refunds 
            WHERE transaction_id = ? AND status != 'Failed'
        ");
        $stmt->execute([$transactionId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->db->rollback();
    }
}
?>