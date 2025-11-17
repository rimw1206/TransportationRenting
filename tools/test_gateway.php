<?php
/**
 * Test Gateway basic functionality
 */

echo "🔍 Testing Gateway\n";
echo "==================\n\n";

// Test 1: Gateway is accessible
echo "1️⃣ Gateway Accessibility Test\n";
$gatewayUrl = "http://localhost/TransportationRenting/gateway/api/auth/register";
echo "   URL: {$gatewayUrl}\n\n";

$testData = [
    'username' => 'gwtest_' . time(),
    'password' => 'Test123456',
    'name' => 'Gateway Test',
    'email' => 'gwtest_' . time() . '@example.com'
];

$ch = curl_init($gatewayUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Capture verbose output
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlInfo = curl_getinfo($ch);
$curlError = curl_error($ch);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);

curl_close($ch);

echo "📊 CURL Info:\n";
echo "   HTTP Code: {$httpCode}\n";
echo "   Content Type: " . ($curlInfo['content_type'] ?? 'N/A') . "\n";
echo "   Total Time: " . ($curlInfo['total_time'] ?? 'N/A') . "s\n";

if ($curlError) {
    echo "   ❌ CURL Error: {$curlError}\n";
}

echo "\n📥 Response:\n";
echo "   Length: " . strlen($response) . " bytes\n";
echo "   First 500 chars: " . substr($response, 0, 500) . "\n";

if (strlen($response) > 500) {
    echo "   ... (truncated)\n";
}

echo "\n🔍 Verbose Log:\n";
echo $verboseLog;

echo "\n==================\n";

// Try to parse as JSON
echo "\n2️⃣ JSON Parsing Test\n";
$decoded = json_decode($response, true);
if ($decoded === null) {
    echo "   ❌ JSON Error: " . json_last_error_msg() . "\n";
    echo "   Raw response type: " . gettype($response) . "\n";
} else {
    echo "   ✅ Valid JSON\n";
    echo "   Success: " . ($decoded['success'] ? 'true' : 'false') . "\n";
    echo "   Message: " . ($decoded['message'] ?? 'N/A') . "\n";
}

echo "\n==================\n";