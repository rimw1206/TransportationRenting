<?php 

require_once __DIR__ . '/../../../..//env-bootstrap.php';
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
    $decoded = $jwtHandler->decode($token); // Changed from verify()
    
    if (!$decoded) {
        ApiResponse::unauthorized('Invalid token');
    }
    
    $userId = $decoded['user_id']; // Changed from $decode->user_id
    
    // Validate required fields
    if (!isset($_POST['identity_number']) || empty($_POST['identity_number'])) {
        ApiResponse::error('Identity number is required', 400);
    }
    
    // Handle file uploads (simplified - in production use proper file storage)
    $idCardFrontUrl = null;
    $idCardBackUrl = null;
    
    if (isset($_FILES['id_card_front'])) {
        // In production, upload to cloud storage (AWS S3, etc.)
        $uploadDir = __DIR__ . '/../../../../uploads/kyc/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $idCardFrontUrl = 'uploads/kyc/' . $userId . '_front_' . time() . '.jpg';
        move_uploaded_file($_FILES['id_card_front']['tmp_name'], __DIR__ . '/../../../../' . $idCardFrontUrl);
    }
    
    if (isset($_FILES['id_card_back'])) {
        $uploadDir = __DIR__ . '/../../../../uploads/kyc/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $idCardBackUrl = 'uploads/kyc/' . $userId . '_back_' . time() . '.jpg';
        move_uploaded_file($_FILES['id_card_back']['tmp_name'], __DIR__ . '/../../../../' . $idCardBackUrl);
    }
    
    // Create KYC record
    $kycModel = new KYC();
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
    error_log('Submit KYC error: ' . $e->getMessage());
    ApiResponse::error($e->getMessage(), 500);
}