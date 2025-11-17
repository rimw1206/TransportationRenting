<?php
/**
 * ============================================
 * services/customer/api/profile/get.php
 * Get user profile endpoint - COMPLETE FIX
 * ============================================
 */

require_once __DIR__ . '/../../../../env-bootstrap.php';

// Suppress errors in production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only GET allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only GET is supported.'
    ]);
    exit();
}

require_once __DIR__ . '/../../classes/Customer.php';
require_once __DIR__ . '/../../../../shared/classes/JWTHandler.php';

try {
    // ========== EXTRACT TOKEN ==========
    $token = null;
    
    // Try multiple header formats
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    } else {
        $authHeader = null;
    }
    
    if ($authHeader) {
        // Extract token from "Bearer TOKEN" or just "TOKEN"
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
        } else {
            $token = trim($authHeader);
        }
    }
    
    // Log for debugging
    error_log('GET /profile - Token extracted: ' . ($token ? 'YES (' . strlen($token) . ' chars)' : 'NO'));
    
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authorization token is required',
            'hint' => 'Include token in Authorization header as: Bearer YOUR_TOKEN'
        ]);
        exit();
    }
    
    // ========== VERIFY TOKEN ==========
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    if ($decoded === false || !is_array($decoded)) {
        error_log('GET /profile - Token decode failed');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token'
        ]);
        exit();
    }
    
    // Verify user_id in payload
    if (!isset($decoded['user_id']) || empty($decoded['user_id'])) {
        error_log('GET /profile - Missing user_id in token: ' . json_encode($decoded));
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid token payload - missing user_id'
        ]);
        exit();
    }
    
    $userId = (int)$decoded['user_id'];
    error_log('GET /profile - Fetching profile for user_id: ' . $userId);
    
    // ========== GET USER DATA ==========
    $userModel = new User();
    $user = $userModel->getById($userId);
    
    if (!$user || empty($user)) {
        error_log('GET /profile - User not found in database: ' . $userId);
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit();
    }
    
    // Remove sensitive data
    unset($user['password']);
    
    // Format dates for frontend
    if (isset($user['created_at'])) {
        $user['created_at_formatted'] = date('d/m/Y H:i', strtotime($user['created_at']));
    }
    
    if (isset($user['birthdate']) && $user['birthdate']) {
        $user['birthdate_formatted'] = date('d/m/Y', strtotime($user['birthdate']));
    }
    
    // Add role
    $user['role'] = ($userId === 1) ? 'admin' : 'user';
    
    error_log('GET /profile - Success for user: ' . $userId);
    
    // ========== SUCCESS RESPONSE ==========
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Profile retrieved successfully',
        'data' => $user
    ]);
    exit();
    
} catch (PDOException $e) {
    error_log('GET /profile - Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    exit();
    
} catch (Exception $e) {
    error_log('GET /profile - Exception: ' . $e->getMessage());
    error_log('GET /profile - Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
    exit();
}