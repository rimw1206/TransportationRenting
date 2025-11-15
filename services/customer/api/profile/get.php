<?php

require_once __DIR__ . '/../../../../env-bootstrap.php';
/**
 * ============================================
 * services/customer/api/profile/get.php
 * Get user profile endpoint - CORRECT VERSION
 * File: Customer.php, Class: User
 * ============================================
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only GET is supported.'
    ]);
    exit();
}

// CORRECT PATHS:
// Current file is at: services/customer/api/profile/get.php
// __DIR__ = services/customer/api/profile/
// Customer.php is at: services/customer/classes/Customer.php
// JWTHandler.php is at: shared/classes/JWTHandler.php

require_once __DIR__ . '/../../classes/Customer.php';  // Contains: class User
require_once __DIR__ . '/../../../../shared/classes/JWTHandler.php';

try {
    // Get token from Authorization header (case-insensitive)
    $token = null;
    $headers = getallheaders();
    
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            // Support both "Bearer TOKEN" and just "TOKEN"
            if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                $token = trim($matches[1]);
            } else {
                $token = trim($value);
            }
            break;
        }
    }
    
    // Log for debugging
    error_log('GET /profile - Authorization header found: ' . ($token ? 'YES' : 'NO'));
    
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authorization token is required',
            'hint' => 'Include token in Authorization header as: Bearer YOUR_TOKEN'
        ]);
        exit();
    }
    
    // Decode and verify token using JWTHandler
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    // Check if token is valid
    if ($decoded === false || !is_array($decoded)) {
        error_log('GET /profile - Token decode failed or invalid format');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token'
        ]);
        exit();
    }
    
    // Verify user_id exists in token payload
    if (!isset($decoded['user_id']) || empty($decoded['user_id'])) {
        error_log('GET /profile - Missing user_id in token payload: ' . json_encode($decoded));
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid token payload - missing user_id'
        ]);
        exit();
    }
    
    $userId = (int)$decoded['user_id'];
    error_log('GET /profile - Fetching profile for user_id: ' . $userId);
    
    // IMPORTANT: File is Customer.php but class name is User
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
    
    // Log success
    error_log('GET /profile - Successfully retrieved profile for user: ' . $userId);
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Profile retrieved successfully',
        'data' => $user
    ]);
    
} catch (PDOException $e) {
    error_log('GET /profile - Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log('GET /profile - Exception: ' . $e->getMessage());
    error_log('GET /profile - Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}