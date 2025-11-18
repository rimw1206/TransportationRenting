<?php
/**
 * API Gateway - Main Router
 * FIXED VERSION - Correct path handling for payment-methods
 */

// Fix paths - go up two levels from gateway/api/ to root
require_once __DIR__ . '/../../shared/classes/ApiResponse.php';
require_once __DIR__ . '/../../shared/classes/ApiClient.php';

ApiResponse::handleOptions();

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove gateway prefix ONLY (keep the rest)
$prefix = '/TransportationRenting/gateway/api';
if (strpos($path, $prefix) === 0) {
    $path = substr($path, strlen($prefix));
    if ($path === '') $path = '/';
}

// Load config
$config = require __DIR__ . '/../config/services.php';
$serviceRoutes = $config['routes'];
$servicePorts = $config['services'];
$publicRoutes = $config['public_routes'] ?? [];

/**
 * Check if a service is available
 */
function isServiceAvailable($port, $timeout = 1)
{
    $connection = @fsockopen('localhost', $port, $errno, $errstr, $timeout);
    if ($connection) {
        fclose($connection);
        return true;
    }
    return false;
}

/**
 * Check if request is from browser
 */
function isBrowserRequest()
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return strpos($accept, 'text/html') !== false;
}

/**
 * Check if route is public (no auth required)
 */
function isPublicRoute($path, $publicRoutes)
{
    foreach ($publicRoutes as $publicRoute) {
        if ($path === $publicRoute) {
            return true;
        }
        if (strpos($path, $publicRoute . '/') === 0) {
            return true;
        }
    }
    return false;
}

try {
    $targetService = null;
    $targetPath = $path;
    $routePrefixMatched = null;

    // Sort routes by length (longest first = most specific)
    $sortedRoutes = $serviceRoutes;
    uksort($sortedRoutes, function($a, $b) {
        return strlen($b) - strlen($a);
    });

    // Find which service handles this route
    foreach ($sortedRoutes as $routePrefix => $service) {
        if ($path === $routePrefix || strpos($path, $routePrefix . '/') === 0) {
            $targetService = $service;
            $routePrefixMatched = $routePrefix;
            break;
        }
    }

    if (!$targetService) {
        ApiResponse::error("Service not found for path: $path", 404);
    }

    // ✅ CRITICAL FIX: KEEP THE FULL PATH - Don't strip anything more
    // The path already has the correct format after removing gateway prefix
    $targetPath = $path;

    // Check authentication (skip if public route)
    if (!isPublicRoute($path, $publicRoutes)) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (empty($authHeader)) {
            ApiResponse::error('Unauthorized - No token provided', 401);
        }
    }

    // Check service availability
    $servicePort = $servicePorts[$targetService] ?? null;
    if (!$servicePort) {
        ApiResponse::error("Port not defined for service: $targetService", 500);
    }

    if (!isServiceAvailable($servicePort)) {
        if (isBrowserRequest()) {
            header('Location: /TransportationRenting/frontend/maintenance.php');
            exit;
        }

        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Service '$targetService' temporarily unavailable (port $servicePort)"
        ]);
        exit;
    }

    // Setup API client - direct to port
    $apiClient = new ApiClient();
    $serviceUrl = "http://localhost:" . $servicePort;
    $apiClient->setServiceUrl($targetService, $serviceUrl);

    // Get request body
    $requestBody = null;
    if (in_array($requestMethod, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        $body = file_get_contents('php://input');
        if (!empty($body)) {
            $requestBody = json_decode($body, true);
        }
    }

    // Forward Authorization header
    $headers = [];
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers[] = 'Authorization: ' . $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // Add debug headers
    $headers[] = 'X-Gateway-Original-Path: ' . $path;
    $headers[] = 'X-Gateway-Target-Service: ' . $targetService;
    $headers[] = 'X-Gateway-Forward-Path: ' . $targetPath;

    // Forward request
    $response = null;
    switch ($requestMethod) {
        case 'GET':
            $response = $apiClient->get($targetService, $targetPath, $headers);
            break;
        case 'POST':
            $response = $apiClient->post($targetService, $targetPath, $requestBody, $headers);
            break;
        case 'PUT':
            $response = $apiClient->put($targetService, $targetPath, $requestBody, $headers);
            break;
        case 'DELETE':
            $response = $apiClient->delete($targetService, $targetPath, $headers);
            break;
        default:
            ApiResponse::error('Method not allowed', 405);
    }

    // Return response
    http_response_code($response['status_code']);
    header('Content-Type: application/json');
    echo $response['raw_response'];

} catch (Exception $e) {
    error_log('Gateway error: ' . $e->getMessage());
    error_log('Gateway trace: ' . $e->getTraceAsString());
    
    // Debug output
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}
// Forward Authorization header
$headers = [];
if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $headers[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
    error_log("✅ Gateway forwarding Authorization: " . substr($_SERVER['HTTP_AUTHORIZATION'], 0, 30) . "...");
} elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $headers[] = 'Authorization: ' . $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    error_log("✅ Gateway forwarding REDIRECT Authorization: " . substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 0, 30) . "...");
} else {
    error_log("❌ Gateway: No Authorization header found!");
}

// Add debug headers
$headers[] = 'X-Gateway-Original-Path: ' . $path;
$headers[] = 'X-Gateway-Target-Service: ' . $targetService;
$headers[] = 'X-Gateway-Forward-Path: ' . $targetPath;

error_log("Gateway forwarding to: {$serviceUrl}{$targetPath}");
error_log("Headers: " . json_encode($headers));