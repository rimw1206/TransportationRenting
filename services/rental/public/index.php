<?php
header('Content-Type: application/json');

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Thêm dòng này
$uri = parse_url($requestUri, PHP_URL_PATH);

// Health check
if ($uri === '/health') {
    require_once __DIR__ . '/health.php';
    exit;
}

// Default not found
http_response_code(404);
echo json_encode(['error' => 'Rental service is under maintenance.']);
