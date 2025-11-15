<?php 

require_once __DIR__ . '/../../../..//env-bootstrap.php';
require_once __DIR__ . '/../../classes/RentalHistory.php';
require_once __DIR__ . '/../../../../shared/classes/JWTHandler.php';
require_once __DIR__ . '/../../../../shared/classes/ApiResponse.php';

// Get token from header
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
}

if (!$token) {
    ApiResponse::unauthorized('Token is required');
}

try {
    // Verify token
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token); // Changed from verify()
    
    if (!$decoded) {
        ApiResponse::unauthorized('Invalid token');
    }
    
    $userId = $decoded['user_id']; // Changed from $decode->user_id
    
    // Get rental history
    $historyModel = new RentalHistory();
    $history = $historyModel->getByUserId($userId);
    
    ApiResponse::success($history, 'Rental history retrieved successfully');
    
} catch (Exception $e) {
    error_log('Get rental history error: ' . $e->getMessage());
    ApiResponse::error('Failed to get rental history', 500);
}