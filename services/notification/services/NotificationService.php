<?php
// services/notification/services/NotificationService.php
require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../../../shared/classes/ApiClient.php';

class NotificationService {
    private $notification;
    private $apiClient;

    public function __construct() {
        $this->notification = new Notification();
        $this->apiClient = new ApiClient();
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications($userId, $filters = []) {
        try {
            $notifications = $this->notification->getUserNotifications($userId, $filters);
            
            return [
                'success' => true,
                'data' => [
                    'items' => $notifications,
                    'total' => count($notifications),
                    'unread' => $this->notification->getUnreadCount($userId)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get unread count
     */
    public function getUnreadCount($userId) {
        try {
            $count = $this->notification->getUnreadCount($userId);
            
            return [
                'success' => true,
                'data' => [
                    'unread' => $count
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Mark as read
     */
    public function markAsRead($notificationId, $userId) {
        try {
            $result = $this->notification->markAsRead($notificationId, $userId);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Notification not found or already read'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Marked as read'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Mark all as read
     */
    public function markAllAsRead($userId) {
        try {
            $count = $this->notification->markAllAsRead($userId);
            
            return [
                'success' => true,
                'message' => "Marked {$count} notifications as read",
                'data' => ['count' => $count]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete notification
     */
    public function delete($notificationId, $userId) {
        try {
            $result = $this->notification->delete($notificationId, $userId);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Notification not found'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Notification deleted'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete all notifications
     */
    public function deleteAll($userId) {
        try {
            $count = $this->notification->deleteAll($userId);
            
            return [
                'success' => true,
                'message' => "Deleted {$count} notifications",
                'data' => ['count' => $count]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send notification
     */
    public function send($userId, $type, $title, $message, $metadata = null) {
        try {
            $notificationId = $this->notification->send($userId, $type, $title, $message, $metadata);
            
            return [
                'success' => true,
                'message' => 'Notification sent',
                'data' => ['notification_id' => $notificationId]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

