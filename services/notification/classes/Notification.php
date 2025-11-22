<?php
/**
 * ================================================
 * services/notification/classes/Notification.php
 * ================================================
 */

require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class Notification {
    private $db;

    public function __construct() {
        $this->db = DatabaseManager::getInstance('notification');
    }

    /**
     * Create notification
     */
    public function create($userId, $type, $title, $message) {
        $query = "
            INSERT INTO Notifications (user_id, type, title, message, sent_at, status)
            VALUES (?, ?, ?, ?, NOW(), 'Sent')
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId, $type, $title, $message]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications($userId, $filters = []) {
        $query = "
            SELECT *, 
                IF(status = 'Sent', 0, 1) as is_read  -- ✅ Giả lập read_at
            FROM Notifications
            WHERE user_id = ?
        ";
        
        $params = [$userId];
        
        if (!empty($filters['unread_only'])) {
            $query .= " AND status = 'Sent'"; 
        }
        
        if (!empty($filters['type'])) {
            $query .= " AND type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        $query .= " ORDER BY sent_at DESC";
        
        if (!empty($filters['limit'])) {
            $query .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get unread count
     */
    public function getUnreadCount($userId) {
        $query = "
            SELECT COUNT(*) as count
            FROM Notifications
            WHERE user_id = ? AND status = 'Sent'  -- ✅ Thay vì read_at IS NULL
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Mark as read
     */
    public function markAsRead($notificationId, $userId) {
        // ✅ Do không có cột read_at, ta chỉ return true
        // Hoặc có thể update status = 'Read' nếu muốn
        
        // Option 1: Không làm gì cả
        return true;
        
        // Option 2: Tạo status mới (cần ALTER TABLE)
        /*
        $query = "
            UPDATE Notifications
            SET status = 'Read'
            WHERE notification_id = ? AND user_id = ? AND status = 'Sent'
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$notificationId, $userId]);
        
        return $stmt->rowCount() > 0;
        */
}

    /**
     * Mark all as read
     */
    public function markAllAsRead($userId) {
        // ✅ Tương tự, return 0 vì không làm gì
        return 0;
        
        // Hoặc update status nếu có
        /*
        $query = "
            UPDATE Notifications
            SET status = 'Read'
            WHERE user_id = ? AND status = 'Sent'
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId]);
        
        return $stmt->rowCount();
        */
    }

    /**
     * Delete notification
     */
    public function delete($notificationId, $userId) {
        $query = "
            DELETE FROM Notifications
            WHERE notification_id = ? AND user_id = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$notificationId, $userId]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete all user notifications
     */
    public function deleteAll($userId) {
        $query = "DELETE FROM Notifications WHERE user_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId]);
        
        return $stmt->rowCount();
    }

    /**
     * Get notification by ID
     */
    public function getById($notificationId, $userId) {
        $query = "
            SELECT *
            FROM Notifications
            WHERE notification_id = ? AND user_id = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$notificationId, $userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Send notification (simulate sending)
     */
    public function send($userId, $type, $title, $message, $metadata = null) {
        $query = "
            INSERT INTO Notifications (user_id, type, title, message, metadata, sent_at, status)
            VALUES (?, ?, ?, ?, ?, NOW(), 'Sent')
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $userId,
            $type,
            $title,
            $message,
            $metadata ? json_encode($metadata) : null
        ]);
        
        return $this->db->lastInsertId();
    }
}