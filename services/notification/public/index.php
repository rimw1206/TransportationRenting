<?php
require_once __DIR__ . '/../../../env-bootstrap.php';
require_once __DIR__ . '/../../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../services/NotificationService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Parse URL
$uri = parse_url($requestUri, PHP_URL_PATH);

// Remove service prefix if exists
$prefix = '/services/notification';
if (strpos($uri, $prefix) === 0) {
    $uri = substr($uri, strlen($prefix));
    if ($uri === '') $uri = '/';
}

error_log("Notification API: $requestMethod $uri");

// Health check
if ($uri === '/health') {
    require_once __DIR__ . '/health.php';
    exit;
}

// Parse segments
$segments = array_values(array_filter(explode('/', $uri)));

// Get auth token
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
}

// Verify token and get user
$userId = null;
if ($token) {
    try {
        $jwtHandler = new JWTHandler();
        $decoded = $jwtHandler->decode($token); // ✅ FIXED: decode() instead of decodeToken()
        
        if ($decoded === false) {
            ApiResponse::unauthorized('Invalid or expired token');
        }
        
        $userId = $decoded['user_id'] ?? null;
    } catch (Exception $e) {
        error_log('JWT decode error: ' . $e->getMessage());
        ApiResponse::unauthorized('Invalid or expired token');
    }
}

if (!$userId) {
    ApiResponse::unauthorized('Authentication required');
}

$notificationService = new NotificationService();

try {
    // Route handling
    if ($requestMethod === 'GET') {
        
        // GET /notifications - Get user notifications
        if ($uri === '/notifications' || empty($segments)) {
            $filters = [];
            
            if (isset($_GET['unread_only'])) {
                $filters['unread_only'] = true;
            }
            
            if (isset($_GET['type'])) {
                $filters['type'] = $_GET['type'];
            }
            
            if (isset($_GET['limit'])) {
                $filters['limit'] = (int)$_GET['limit'];
            }
            
            $result = $notificationService->getUserNotifications($userId, $filters);
            
            if ($result['success']) {
                ApiResponse::success($result['data'], 'Notifications retrieved');
            } else {
                ApiResponse::error($result['message'], 500);
            }
            exit;
        }
        
        // GET /notifications/count - Get unread count
        if ($uri === '/notifications/count') {
            $result = $notificationService->getUnreadCount($userId);
            
            if ($result['success']) {
                ApiResponse::success($result['data'], 'Unread count retrieved');
            } else {
                ApiResponse::error($result['message'], 500);
            }
            exit;
        }
        
        ApiResponse::notFound('Endpoint not found');
    }
    
    if ($requestMethod === 'PUT') {
        
        // PUT /notifications/{id}/read - Mark as read
        if (isset($segments[1]) && $segments[1] === 'read' && is_numeric($segments[0])) {
            $notificationId = (int)$segments[0];
            
            $result = $notificationService->markAsRead($notificationId, $userId);
            
            if ($result['success']) {
                ApiResponse::success(null, $result['message']);
            } else {
                ApiResponse::error($result['message'], 400);
            }
            exit;
        }
        
        // PUT /notifications/mark-all-read - Mark all as read
        if ($uri === '/notifications/mark-all-read') {
            $result = $notificationService->markAllAsRead($userId);
            
            if ($result['success']) {
                ApiResponse::success($result['data'], $result['message']);
            } else {
                ApiResponse::error($result['message'], 500);
            }
            exit;
        }
        
        ApiResponse::notFound('Endpoint not found');
    }
    
    if ($requestMethod === 'DELETE') {
        
        // DELETE /notifications/{id} - Delete notification
        if (is_numeric($segments[0])) {
            $notificationId = (int)$segments[0];
            
            $result = $notificationService->delete($notificationId, $userId);
            
            if ($result['success']) {
                ApiResponse::success(null, $result['message']);
            } else {
                ApiResponse::error($result['message'], 404);
            }
            exit;
        }
        
        // DELETE /notifications - Delete all
        if ($uri === '/notifications') {
            $result = $notificationService->deleteAll($userId);
            
            if ($result['success']) {
                ApiResponse::success($result['data'], $result['message']);
            } else {
                ApiResponse::error($result['message'], 500);
            }
            exit;
        }
        
        ApiResponse::notFound('Endpoint not found');
    }
    
    if ($requestMethod === 'POST') {
        
        // POST /notifications/send - Send notification (internal use)
        if ($uri === '/notifications/send') {
            $body = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($body['user_id']) || !isset($body['type']) || !isset($body['title']) || !isset($body['message'])) {
                ApiResponse::badRequest('Missing required fields');
            }
            
            $result = $notificationService->send(
                $body['user_id'],
                $body['type'],
                $body['title'],
                $body['message'],
                $body['metadata'] ?? null
            );
            
            if ($result['success']) {
                ApiResponse::created($result['data'], $result['message']);
            } else {
                ApiResponse::error($result['message'], 500);
            }
            exit;
        }
        
        ApiResponse::notFound('Endpoint not found');
    }
    
    ApiResponse::methodNotAllowed('Method not allowed');
    
} catch (Exception $e) {
    error_log('Notification API Error: ' . $e->getMessage());
    ApiResponse::error('Internal server error: ' . $e->getMessage(), 500);
}