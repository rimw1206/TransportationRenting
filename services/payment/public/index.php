<?php
require_once __DIR__ . '/../../../env-bootstrap.php';
header('Content-Type: application/json');

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($requestUri, PHP_URL_PATH);

// Health check
if ($uri === '/health') {
    require_once __DIR__ . '/health.php';
    exit;
}

// Process payment (POST /payments/process)
if (preg_match('#^/payments/process$#', $uri) && $requestMethod === 'POST') {
    require_once __DIR__ . '/process-payment.php';
    exit;
}

// Verify payment (POST /payments/verify)
if (preg_match('#^/payments/verify$#', $uri)) {
    require_once __DIR__ . '/verify.php';
    exit;
}

// Get transaction by ID (GET /payments/transactions/{id})
if (preg_match('#^/payments/transactions/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
    $_GET['transaction_id'] = $matches[1];
    require_once __DIR__ . '/get-transaction.php';
    exit;
}

// Get user transactions (GET /payments/transactions)
if (preg_match('#^/payments/transactions$#', $uri) && $requestMethod === 'GET') {
    require_once __DIR__ . '/get-transactions.php';
    exit;
}

// Get invoice (GET /payments/invoices/{id})
if (preg_match('#^/payments/invoices/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
    $_GET['invoice_id'] = $matches[1];
    require_once __DIR__ . '/get-invoice.php';
    exit;
}

// Request refund (POST /payments/refunds)
if (preg_match('#^/payments/refunds$#', $uri) && $requestMethod === 'POST') {
    require_once __DIR__ . '/request-refund.php';
    exit;
}

// Get refund status (GET /payments/refunds/{id})
if (preg_match('#^/payments/refunds/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
    $_GET['refund_id'] = $matches[1];
    require_once __DIR__ . '/get-refund.php';
    exit;
}

// Not found
http_response_code(404);
echo json_encode([
    'success' => false,
    'message' => 'Payment endpoint not found'
]);
?>