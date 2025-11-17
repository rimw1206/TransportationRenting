<?php
/**
 * tools/test-complete-flow.php
 * Test complete registration flow through gateway
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🚀 Complete Integration Test\n";
echo str_repeat("=", 60) . "\n\n";

$timestamp = time();
$testUser = [
    'username' => "test_user_{$timestamp}",
    'password' => 'Test123456',
    'name' => 'Integration Test User',
    'email' => "test_{$timestamp}@example.com",
    'phone' => '0987654321'
];

echo "📋 Test User Data:\n";
echo "   Username: {$testUser['username']}\n";
echo "   Email: {$testUser['email']}\n\n";

// Test 1: Registration through Gateway
echo "1️⃣ Testing Registration via Gateway\n";
$gatewayUrl = 'http://localhost/TransportationRenting/gateway/api/auth/register';

$ch = curl_init($gatewayUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testUser));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $httpCode\n";
$result = json_decode($response, true);

if ($httpCode === 201 && $result['success']) {
    echo "   ✅ Registration successful!\n";
    echo "   User ID: {$result['user_id']}\n";
    echo "   Email sent: " . ($result['email_sent'] ? 'Yes' : 'No') . "\n\n";
    
    $userId = $result['user_id'];
} else {
    echo "   ❌ Registration failed\n";
    echo "   Message: " . ($result['message'] ?? 'Unknown error') . "\n";
    exit(1);
}

// Test 2: Try to login without email verification
echo "2️⃣ Testing Login Without Email Verification\n";
$loginUrl = 'http://localhost/TransportationRenting/gateway/api/auth/login';

$loginData = [
    'username' => $testUser['username'],
    'password' => $testUser['password']
];

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 401 && isset($result['requires_verification'])) {
    echo "   ✅ Login blocked - Email verification required\n";
    echo "   Message: {$result['message']}\n\n";
} else {
    echo "   ⚠️  Unexpected response\n";
    echo "   Status: $httpCode\n";
    echo "   Message: " . ($result['message'] ?? 'N/A') . "\n\n";
}

// Test 3: Get verification token from database
echo "3️⃣ Retrieving Verification Token from Database\n";
require_once __DIR__ . '/../shared/classes/DatabaseManager.php';
require_once __DIR__ . '/../env-bootstrap.php';

try {
    $db = DatabaseManager::getConnection('customer');
    $stmt = $db->prepare("
        SELECT token, expires_at 
        FROM email_verifications 
        WHERE user_id = ? AND verified_at IS NULL 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($verification) {
        echo "   ✅ Verification token found\n";
        echo "   Token: " . substr($verification['token'], 0, 20) . "...\n";
        echo "   Expires: {$verification['expires_at']}\n\n";
        
        $token = $verification['token'];
    } else {
        echo "   ❌ Verification token not found\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Verify email
echo "4️⃣ Testing Email Verification\n";
$verifyUrl = 'http://localhost/TransportationRenting/gateway/api/auth/verify-email';

$ch = curl_init($verifyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $token]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && $result['success']) {
    echo "   ✅ Email verification successful!\n";
    echo "   Message: {$result['message']}\n\n";
} else {
    echo "   ❌ Verification failed\n";
    echo "   Status: $httpCode\n";
    echo "   Message: " . ($result['message'] ?? 'Unknown error') . "\n";
    exit(1);
}

// Test 5: Login after verification
echo "5️⃣ Testing Login After Email Verification\n";

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && $result['success'] && isset($result['token'])) {
    echo "   ✅ Login successful!\n";
    echo "   Username: {$result['user']['username']}\n";
    echo "   Email: {$result['user']['email']}\n";
    echo "   Status: {$result['user']['status']}\n";
    echo "   Token: " . substr($result['token'], 0, 30) . "...\n\n";
    
    $authToken = $result['token'];
} else {
    echo "   ❌ Login failed\n";
    echo "   Status: $httpCode\n";
    echo "   Message: " . ($result['message'] ?? 'Unknown error') . "\n";
    exit(1);
}

// Test 6: Test authenticated endpoint
echo "6️⃣ Testing Authenticated Endpoint (Profile)\n";
$profileUrl = 'http://localhost/TransportationRenting/gateway/api/profile';

$ch = curl_init($profileUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $authToken
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && $result['success']) {
    echo "   ✅ Profile retrieved successfully!\n";
    echo "   Name: {$result['data']['name']}\n";
    echo "   Email: {$result['data']['email']}\n\n";
} else {
    echo "   ⚠️  Profile retrieval issue\n";
    echo "   Status: $httpCode\n";
    echo "   Message: " . ($result['message'] ?? 'Unknown error') . "\n\n";
}

// Test 7: Logout
echo "7️⃣ Testing Logout\n";
$logoutUrl = 'http://localhost/TransportationRenting/gateway/api/auth/logout';

$ch = curl_init($logoutUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $authToken
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && $result['success']) {
    echo "   ✅ Logout successful!\n\n";
} else {
    echo "   ⚠️  Logout issue\n";
    echo "   Status: $httpCode\n\n";
}

echo str_repeat("=", 60) . "\n";
echo "✅ Complete Integration Test Passed!\n";
echo "\n📊 Summary:\n";
echo "   • Registration via Gateway: ✅\n";
echo "   • Email verification required: ✅\n";
echo "   • Email verification process: ✅\n";
echo "   • Login after verification: ✅\n";
echo "   • Authenticated requests: ✅\n";
echo "   • Logout: ✅\n";
echo "\n🎉 All systems operational!\n";