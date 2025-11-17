<?php
/**
 * Test Customer Service directly (bypass gateway)
 */

echo "🧪 Testing Customer Service Directly\n";
echo "=====================================\n\n";

// Test 1: Health check
echo "1️⃣ Health Check\n";
$healthUrl = "http://localhost:8001/health";
echo "   URL: {$healthUrl}\n";

$ch = curl_init($healthUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: {$httpCode}\n";
echo "   Response: {$response}\n\n";

// Test 2: Register endpoint
echo "2️⃣ Register Endpoint\n";
$registerUrl = "http://localhost:8001/auth/register";
echo "   URL: {$registerUrl}\n";

$testData = [
    'username' => 'directtest_' . time(),
    'password' => 'Test123456',
    'name' => 'Direct Test User',
    'email' => 'directtest_' . time() . '@example.com',
    'phone' => '0987654321'
];

echo "   Data: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init($registerUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "   Status: {$httpCode}\n";
if ($curlError) {
    echo "   CURL Error: {$curlError}\n";
}
echo "   Response: {$response}\n\n";

$result = json_decode($response, true);
if ($result === null) {
    echo "   ❌ JSON Decode Error: " . json_last_error_msg() . "\n";
} elseif ($result['success'] ?? false) {
    echo "   ✅ Registration successful!\n";
    if (isset($result['user_id'])) {
        echo "   User ID: {$result['user_id']}\n";
    }
} else {
    echo "   ❌ Registration failed: " . ($result['message'] ?? 'Unknown error') . "\n";
}

echo "\n=====================================\n";
echo "Direct service test completed\n";