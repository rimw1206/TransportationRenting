<?php

require_once __DIR__ . '/../../../..//env-bootstrap.php';
/**
 * ============================================
 * services/customer/api/payment-methods/list.php
 * List payment methods endpoint
 * ============================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// CORRECT PATHS from api/payment-methods/
require_once __DIR__ . '/../../classes/PaymentMethod.php';
require_once __DIR__ . '/../../../../shared/classes/JWTHandler.php';

try {
    // Get token (case-insensitive)
    $token = null;
    $headers = getallheaders();
    
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                $token = trim($matches[1]);
            } else {
                $token = trim($value);
            }
            break;
        }
    }
    
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authorization token is required'
        ]);
        exit();
    }
    
    // Verify token
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    if ($decoded === false || !is_array($decoded)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token'
        ]);
        exit();
    }
    
    if (!isset($decoded['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid token payload'
        ]);
        exit();
    }
    
    $userId = (int)$decoded['user_id'];
    
    // Get payment methods
    $paymentModel = new PaymentMethod();
    $methods = $paymentModel->getByUserId($userId);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment methods retrieved successfully',
        'data' => $methods ?: []
    ]);
    
} catch (Exception $e) {
    error_log('GET /payment-methods - Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}