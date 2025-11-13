<?php
/**
 * tools/test_customer_service.php
 * Test customer service directly
 */

echo "╔══════════════════════════════════════════════╗\n";
echo "║   Customer Service Direct Test               ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// Test 1: Health check
echo "🔍 Test 1: Health Check\n";
echo str_repeat('-', 60) . "\n";

$ch = curl_init('http://localhost:8001/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: http://localhost:8001/health\n";
echo "Status: $httpCode\n";
echo "Response: $response\n\n";

// Test 2: Root path
echo "🔍 Test 2: Root Path\n";
echo str_repeat('-', 60) . "\n";

$ch = curl_init('http://localhost:8001/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: http://localhost:8001/\n";
echo "Status: $httpCode\n";
echo "Response: $response\n\n";

// Test 3: Direct login with /auth/login
echo "🔍 Test 3: Login with /auth/login\n";
echo str_repeat('-', 60) . "\n";

$postData = json_encode([
    'username' => 'admin',
    'password' => 'admin123'
]);

$ch = curl_init('http://localhost:8001/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: http://localhost:8001/auth/login\n";
echo "Status: $httpCode\n";
$decoded = json_decode($response, true);
if ($decoded) {
    echo "Response:\n";
    echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    if (isset($decoded['success']) && $decoded['success']) {
        echo "✅ Direct login SUCCESS!\n";
    } else {
        echo "❌ Direct login FAILED!\n";
    }
} else {
    echo "Response: $response\n";
}

// Test 4: Login with /login (without /auth prefix)
echo "\n🔍 Test 4: Login with /login (no /auth)\n";
echo str_repeat('-', 60) . "\n";

$ch = curl_init('http://localhost:8001/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: http://localhost:8001/login\n";
echo "Status: $httpCode\n";
echo "Response: " . substr($response, 0, 200) . "\n\n";

// Test 5: Check customer service logs
echo "🔍 Test 5: Check Customer Service Logs\n";
echo str_repeat('-', 60) . "\n";

$logFile = __DIR__ . '/../logs/customer_service.log';
if (file_exists($logFile)) {
    echo "Log file: $logFile\n";
    $logs = file_get_contents($logFile);
    $lines = explode("\n", trim($logs));
    $recentLines = array_slice($lines, -20);
    
    echo "Last 20 log entries:\n";
    foreach ($recentLines as $line) {
        if (!empty(trim($line))) {
            echo "  " . $line . "\n";
        }
    }
} else {
    echo "❌ Log file not found: $logFile\n";
}

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║          Test Complete                       ║\n";
echo "╚══════════════════════════════════════════════╝\n";