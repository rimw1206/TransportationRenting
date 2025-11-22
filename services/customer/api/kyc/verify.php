<?php
/**
 * ============================================
 * services/customer/api/kyc/verify.php
 * Admin: Verify hoặc reject KYC
 * ============================================
 */

require_once __DIR__ . '/../../../../env-bootstrap.php';
require_once __DIR__ . '/../../classes/KYC.php';
require_once __DIR__ . '/../../classes/User.php';
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
    
    // ✅ Check if user is admin
    $userModel = new User();
    $admin = $userModel->getById($decoded['user_id']);
    
    if (!$admin || ($admin['role'] ?? 'user') !== 'admin') {
        ApiResponse::error('Only admins can verify KYC', 403);
    }
    
    // Get request body
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($body['user_id']) || !isset($body['action'])) {
        ApiResponse::error('user_id and action are required', 400);
    }
    
    $targetUserId = $body['user_id'];
    $action = $body['action']; // 'approve' hoặc 'reject'
    
    $kycModel = new KYC();
    
    if ($action === 'approve') {
        $result = $kycModel->verify($targetUserId);
        
        if ($result) {
            // ✅ Update user status to Active
            $userModel->update($targetUserId, ['status' => 'Active']);
            
            ApiResponse::success(null, 'KYC approved successfully');
        } else {
            ApiResponse::error('Failed to approve KYC', 500);
        }
    } elseif ($action === 'reject') {
        $result = $kycModel->reject($targetUserId);
        
        if ($result) {
            ApiResponse::success(null, 'KYC rejected');
        } else {
            ApiResponse::error('Failed to reject KYC', 500);
        }
    } else {
        ApiResponse::error('Invalid action. Use "approve" or "reject"', 400);
    }
    
} catch (Exception $e) {
    error_log('Verify KYC error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}       