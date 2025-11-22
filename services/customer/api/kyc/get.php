<?php
/**
 * ============================================
 * services/customer/api/kyc/get.php
 * Get KYC status của user hiện tại
 * ============================================
 */

require_once __DIR__ . '/../../../../env-bootstrap.php';
require_once __DIR__ . '/../../classes/KYC.php';
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
    $decoded = $jwtHandler->decode($token);
    
    if (!$decoded || !isset($decoded['user_id'])) {
        ApiResponse::unauthorized('Invalid token');
    }
    
    $userId = $decoded['user_id'];
    
    // Get KYC status
    $kycModel = new KYC();
    $kyc = $kycModel->getByUserId($userId);
    
    // ✅ FIX: Return empty data thay vì null
    if (!$kyc) {
        ApiResponse::success([
            'kyc_status' => 'not_submitted',
            'message' => 'User has not submitted KYC yet'
        ], 'No KYC found');
    }
    
    ApiResponse::success($kyc, 'KYC retrieved successfully');
    
} catch (Exception $e) {
    error_log('Get KYC error: ' . $e->getMessage());
    ApiResponse::error('Failed to get KYC: ' . $e->getMessage(), 500);
}