<?php
header('Content-Type: application/json');

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($requestUri, PHP_URL_PATH);

if ($uri === '/health') {
    require_once __DIR__ . '/health.php';
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Vehicle service is under maintenance.']);

