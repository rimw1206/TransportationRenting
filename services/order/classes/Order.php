<?php
/**
 * ================================================
 * services/order/classes/Order.php
 * Order Model - Database Layer
 * ================================================
 */

require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class Order {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance('order');
    }
    
    /**
     * Create order from rental
     */
    public function createFromRental($rentalId, $userId) {
        $stmt = $this->db->prepare("
            INSERT INTO Orders (rental_id, user_id, delivery_status, order_date)
            VALUES (?, ?, 'Pending', NOW())
        ");
        
        $stmt->execute([$rentalId, $userId]);
        $orderId = $this->db->lastInsertId();
        
        // Add first tracking entry
        $this->addTracking($orderId, 'Created', 'Đơn giao xe được tạo');
        
        return $orderId;
    }
    
    /**
     * Add tracking entry
     */
    public function addTracking($orderId, $status, $note = null) {
        $stmt = $this->db->prepare("
            INSERT INTO OrderTracking (order_id, status_update, note, updated_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$orderId, $status, $note]);
    }
    
    /**
     * Update order status
     */
    public function updateStatus($orderId, $status, $note = null) {
        $validStatuses = ['Pending', 'Confirmed', 'InTransit', 'Delivered', 'Cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid order status');
        }
        
        $stmt = $this->db->prepare("
            UPDATE Orders 
            SET delivery_status = ?
            WHERE order_id = ?
        ");
        
        $stmt->execute([$status, $orderId]);
        
        // Map to tracking status
        $trackingStatusMap = [
            'Pending' => 'Created',
            'Confirmed' => 'Confirmed',
            'InTransit' => 'VehicleAssigned',
            'Delivered' => 'Delivered',
            'Cancelled' => 'Cancelled'
        ];
        
        $trackingStatus = $trackingStatusMap[$status] ?? $status;
        $this->addTracking($orderId, $trackingStatus, $note);
        
        return true;
    }
    
    /**
     * Get order by rental_id
     */
    public function getByRentalId($rentalId) {
        $stmt = $this->db->prepare("
            SELECT * FROM Orders WHERE rental_id = ?
        ");
        $stmt->execute([$rentalId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get order with tracking
     */
    public function getWithTracking($orderId) {
        // Get order
        $stmt = $this->db->prepare("SELECT * FROM Orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) return null;
        
        // Get tracking history
        $stmt = $this->db->prepare("
            SELECT * FROM OrderTracking 
            WHERE order_id = ? 
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$orderId]);
        $tracking = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $order['tracking'] = $tracking;
        
        return $order;
    }
    
    /**
     * Get user orders
     */
    public function getUserOrders($userId) {
        $stmt = $this->db->prepare("
            SELECT o.*, 
                   (SELECT COUNT(*) FROM OrderTracking WHERE order_id = o.order_id) as tracking_count
            FROM Orders o
            WHERE user_id = ?
            ORDER BY order_date DESC
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create cancellation request
     */
    public function createCancellationRequest($orderId, $reason) {
        $stmt = $this->db->prepare("
            INSERT INTO CancellationRequest (order_id, reason, requested_at)
            VALUES (?, ?, NOW())
        ");
        
        return $stmt->execute([$orderId, $reason]);
    }
    
    /**
     * Get cancellation requests
     */
    public function getCancellationRequests($orderId = null) {
        if ($orderId) {
            $stmt = $this->db->prepare("SELECT * FROM CancellationRequest WHERE order_id = ?");
            $stmt->execute([$orderId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->db->query("
                SELECT cr.*, o.rental_id, o.user_id, o.delivery_status
                FROM CancellationRequest cr
                JOIN Orders o ON cr.order_id = o.order_id
                WHERE cr.approved = FALSE
                ORDER BY cr.requested_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}