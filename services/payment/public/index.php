<?php
/**
 * ================================================
 * services/payment/public/index.php
 * ✅ FIXED: Admin transactions route now works
 * ================================================
 */

require_once __DIR__ . '/../../../env-bootstrap.php';
header('Content-Type: application/json');

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($requestUri, PHP_URL_PATH);

// Remove service prefix if exists
$prefix = '/services/payment';
if (strpos($uri, $prefix) === 0) {
    $uri = substr($uri, strlen($prefix));
    if ($uri === '') $uri = '/';
}

error_log("=== PAYMENT SERVICE REQUEST ===");
error_log("Method: " . $requestMethod);
error_log("Original URI: " . $requestUri);
error_log("Processed URI: " . $uri);

// Health check
if ($uri === '/health') {
    require_once __DIR__ . '/health.php';
    exit;
}

// ✅ CRITICAL FIX: Admin route MUST come FIRST
// Admin get all transactions (GET /payments/transactions/admin)
if (preg_match('#^/payments/transactions/admin$#', $uri) && $requestMethod === 'GET') {
    error_log("✅ Matched admin transactions route");
    require_once __DIR__ . '/get-admin-transactions.php';
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
    error_log("✅ Matched single transaction route: ID " . $matches[1]);
    $_GET['transaction_id'] = $matches[1];
    require_once __DIR__ . '/get-transaction.php';
    exit;
}
// Add this route BEFORE the generic /payments/rental/{id} route
if (preg_match('#^/payments/rental/(\d+)/transactions$#', $uri, $matches) && $requestMethod === 'GET') {
    error_log("✅ Matched rental transactions route: Rental ID " . $matches[1]);
    $_GET['rental_id'] = $matches[1];
    require_once __DIR__ . '/get-rental-transactions.php';
    exit;
}
// Get transactions by rental_id (GET /payments/rental/{rental_id})
if (preg_match('#^/payments/rental/(\d+)$#', $uri, $matches) && $requestMethod === 'GET') {
    error_log("✅ Matched rental transactions route: Rental ID " . $matches[1]);
    $_GET['rental_id'] = $matches[1];
    require_once __DIR__ . '/get-transactions.php';
    exit;
}

// Get user transactions (GET /payments/transactions)
if (preg_match('#^/payments/transactions$#', $uri) && $requestMethod === 'GET') {
    error_log("✅ Matched user transactions route");
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
error_log("❌ No route matched for: " . $uri);
http_response_code(404);
echo json_encode([
    'success' => false,
    'message' => 'Payment endpoint not found',
    'requested_uri' => $uri,
    'method' => $requestMethod
]);