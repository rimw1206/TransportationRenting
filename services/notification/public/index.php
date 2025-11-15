<?php
require_once __DIR__ . '/../../../env-bootstrap.php';
// notification-service/public/index.php
header('Content-Type: application/json');

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Lọc query string
$uri = parse_url($requestUri, PHP_URL_PATH);

// Route: GET /health -> kiểm tra trạng thái service
if ($uri === '/health') {
   require_once __DIR__ . '/health.php';
    exit;
}

// Default: Not found
http_response_code(404);
echo json_encode(['error' => 'Notification service is under maintainance.']);
