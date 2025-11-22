<?php
/**
 * ============================================
 * services/customer/api/kyc/submit.php
 * Submit KYC - Upload CMND/CCCD
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
    
    // Validate required fields
    if (!isset($_POST['identity_number']) || empty($_POST['identity_number'])) {
        ApiResponse::error('Identity number is required', 400);
    }
    
    // ✅ FIX: Improved file upload handling
    $idCardFrontUrl = null;
    $idCardBackUrl = null;
    
    $uploadDir = __DIR__ . '/../../../../uploads/kyc/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Upload front image
    if (isset($_FILES['id_card_front']) && $_FILES['id_card_front']['error'] === UPLOAD_ERR_OK) {
        $frontExt = pathinfo($_FILES['id_card_front']['name'], PATHINFO_EXTENSION);
        $frontFilename = $userId . '_front_' . time() . '.' . $frontExt;
        $frontPath = $uploadDir . $frontFilename;
        
        if (move_uploaded_file($_FILES['id_card_front']['tmp_name'], $frontPath)) {
            $idCardFrontUrl = 'uploads/kyc/' . $frontFilename;
        } else {
            ApiResponse::error('Failed to upload front image', 500);
        }
    }
    
    // Upload back image
    if (isset($_FILES['id_card_back']) && $_FILES['id_card_back']['error'] === UPLOAD_ERR_OK) {
        $backExt = pathinfo($_FILES['id_card_back']['name'], PATHINFO_EXTENSION);
        $backFilename = $userId . '_back_' . time() . '.' . $backExt;
        $backPath = $uploadDir . $backFilename;
        
        if (move_uploaded_file($_FILES['id_card_back']['tmp_name'], $backPath)) {
            $idCardBackUrl = 'uploads/kyc/' . $backFilename;
        } else {
            ApiResponse::error('Failed to upload back image', 500);
        }
    }
    
    // Create KYC record
    $kycModel = new KYC();
    
    try {
        $result = $kycModel->create([
            'user_id' => $userId,
            'identity_number' => $_POST['identity_number'],
            'id_card_front_url' => $idCardFrontUrl,
            'id_card_back_url' => $idCardBackUrl,
            'verification_status' => 'Pending'
        ]);
        
        if ($result) {
            ApiResponse::success($result, 'KYC submitted successfully', 201);
        } else {
            ApiResponse::error('Failed to submit KYC', 500);
        }
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already has a KYC') !== false) {
            ApiResponse::error('You have already submitted KYC. Please wait for verification.', 400);
        }
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Submit KYC error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}