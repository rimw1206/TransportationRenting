<?php
/**
 * Debug Login API - Find the exact error
 */

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║         Login API Debug Tool                 ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// Test 1: Check if customer service is running
echo "📡 Test 1: Check Customer Service\n";
echo str_repeat("-", 60) . "\n";

$serviceUrl = "http://localhost:8001/health";
$ch = curl_init($serviceUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ Customer Service is running on port 8001\n";
} else {
    echo "❌ Customer Service NOT running on port 8001\n";
    echo "   Please start it: cd services/customer/public && php -S localhost:8001\n";
}
echo "\n";

// Test 2: Direct call to Customer Service (bypass gateway)
echo "📡 Test 2: Direct API Call (Bypass Gateway)\n";
echo str_repeat("-", 60) . "\n";

$url = "http://localhost:8001/login";
$data = json_encode(['username' => 'admin', 'password' => 'admin123']);

echo "URL: $url\n";
echo "Body: $data\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response:\n$response\n";

if ($curlError) {
    echo "CURL Error: $curlError\n";
}
echo "\n";

// Test 3: Check error log
echo "📋 Test 3: Check Error Logs\n";
echo str_repeat("-", 60) . "\n";

$logFile = __DIR__ . '/../services/customer/logs/customer_api_errors.log';
if (file_exists($logFile)) {
    echo "Log file: $logFile\n\n";
    $logs = file_get_contents($logFile);
    $lines = explode("\n", $logs);
    $recentLines = array_slice($lines, -20); // Last 20 lines
    echo "Recent errors:\n";
    echo implode("\n", $recentLines) . "\n";
} else {
    echo "⚠️  Log file not found: $logFile\n";
}
echo "\n";

// Test 4: Call through Gateway
echo "📡 Test 4: Call Through Gateway\n";
echo str_repeat("-", 60) . "\n";

$url = "http://localhost/TransportationRenting/gateway/api/auth/login";
$data = json_encode(['username' => 'admin', 'password' => 'admin123']);

echo "URL: $url\n";
echo "Body: $data\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Content-Type: $contentType\n";
echo "Response Length: " . strlen($response) . "\n";
echo "Response:\n";
echo $response . "\n";

if ($curlError) {
    echo "CURL Error: $curlError\n";
}

// Try to decode
$decoded = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "\n❌ JSON Decode Error: " . json_last_error_msg() . "\n";
    echo "Raw bytes (first 500 chars):\n";
    echo substr($response, 0, 500) . "\n";
    
    // Check for PHP errors in response
    if (strpos($response, 'Fatal error') !== false || 
        strpos($response, 'Parse error') !== false ||
        strpos($response, 'Warning:') !== false) {
        echo "\n⚠️  PHP ERROR DETECTED IN RESPONSE!\n";
        echo "The response contains PHP error messages.\n";
    }
} else {
    echo "\n✅ JSON is valid\n";
    print_r($decoded);
}
echo "\n";

// Test 5: Check required files exist
echo "📁 Test 5: Check Required Files\n";
echo str_repeat("-", 60) . "\n";

$requiredFiles = [
    'AuthService' => __DIR__ . '/../services/customer/services/AuthService.php',
    'Login API' => __DIR__ . '/../services/customer/api/auth/login.php',
    'DatabaseManager' => __DIR__ . '/../shared/classes/DatabaseManager.php',
    'JWTHandler' => __DIR__ . '/../shared/classes/JWTHandler.php',
    'Cache' => __DIR__ . '/../shared/classes/Cache.php',
    'ApiResponse' => __DIR__ . '/../shared/classes/ApiResponse.php',
];

foreach ($requiredFiles as $name => $file) {
    if (file_exists($file)) {
        echo "✅ $name: exists\n";
    } else {
        echo "❌ $name: NOT FOUND - $file\n";
    }
}
echo "\n";

// Test 6: Test AuthService directly
echo "🔧 Test 6: Test AuthService Directly\n";
echo str_repeat("-", 60) . "\n";

try {
    require_once __DIR__ . '/../shared/classes/DatabaseManager.php';
    require_once __DIR__ . '/../shared/classes/JWTHandler.php';
    require_once __DIR__ . '/../shared/classes/Cache.php';
    require_once __DIR__ . '/../services/customer/services/AuthService.php';
    
    // Load .env
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value, '"\'');
                putenv(trim($key) . '=' . trim($value, '"\''));
            }
        }
    }
    
    echo "Creating AuthService instance...\n";
    $authService = new AuthService();
    
    echo "Calling login method...\n";
    $result = $authService->login('admin', 'admin123');
    
    echo "Result:\n";
    print_r($result);
    
    if ($result['success']) {
        echo "\n✅ AuthService works correctly!\n";
        echo "User ID: " . $result['user']['user_id'] . "\n";
        echo "Username: " . $result['user']['username'] . "\n";
        echo "Token length: " . strlen($result['token']) . "\n";
    } else {
        echo "\n❌ Login failed: " . $result['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

echo "╔══════════════════════════════════════════════╗\n";
echo "║         Debug Complete                       ║\n";
echo "╚══════════════════════════════════════════════╝\n";