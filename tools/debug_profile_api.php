<?php
// TEST FILE: public/test-profile.php
// Create this file to test the profile fetch directly

session_start();

// Check login
if (!isset($_SESSION['user'])) {
    die('Not logged in. Please login first.');
}

$user = $_SESSION['user'];
$token = $_SESSION['token'] ?? '';

echo "<h1>Profile Fetch Debug</h1>";
echo "<h3>1. Session Data:</h3>";
echo "<pre>";
print_r($user);
echo "</pre>";

echo "<h3>2. Token:</h3>";
echo "<pre>" . htmlspecialchars($token) . "</pre>";

// Test direct API call
require_once __DIR__ . '/../shared/classes/ApiClient.php';

$apiClient = new ApiClient();
$apiClient->setServiceUrl('customer', 'http://localhost:8001');

echo "<h3>3. Testing API Call to: http://localhost:8001/profile</h3>";

try {
    $response = $apiClient->get('customer', '/profile', [], $token);
    
    echo "<h4>Response Status Code:</h4>";
    echo "<pre>" . $response['status_code'] . "</pre>";
    
    echo "<h4>Raw Response:</h4>";
    echo "<pre>";
    echo htmlspecialchars($response['raw_response']);
    echo "</pre>";
    
    echo "<h4>Parsed Response:</h4>";
    echo "<pre>";
    $data = json_decode($response['raw_response'], true);
    print_r($data);
    echo "</pre>";
    
    if ($response['status_code'] !== 200) {
        echo "<h4 style='color: red;'>ERROR: Status code is not 200</h4>";
    }
    
    if (!$data || !isset($data['success'])) {
        echo "<h4 style='color: red;'>ERROR: Invalid JSON response</h4>";
    }
    
    if (isset($data['success']) && !$data['success']) {
        echo "<h4 style='color: red;'>ERROR: " . ($data['message'] ?? 'Unknown error') . "</h4>";
    }
    
} catch (Exception $e) {
    echo "<h4 style='color: red;'>EXCEPTION:</h4>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<h4>Stack Trace:</h4>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<h3>4. Testing Direct URL (Open in new tab):</h3>";
echo "<a href='http://localhost:8001/auth/profile' target='_blank'>http://localhost:8001/auth/profile</a>";
echo "<br><small>Note: This will fail without proper Authorization header</small>";

echo "<hr>";
echo "<h3>5. Check Customer Service is Running:</h3>";
try {
    $ch = curl_init('http://localhost:8001/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "<span style='color: green;'>✓ Customer service is running</span>";
        echo "<pre>" . htmlspecialchars($result) . "</pre>";
    } else {
        echo "<span style='color: red;'>✗ Customer service is NOT responding (HTTP $httpCode)</span>";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Cannot connect to customer service</span>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

echo "<hr>";
echo "<h3>6. Check Files Exist:</h3>";
$files = [
    'Customer Service Index' => __DIR__ . '/../services/customer/public/index.php',
    'Profile Get Endpoint' => __DIR__ . '/../services/customer/api/profile/get.php',
    'User Class' => __DIR__ . '/../services/customer/classes/User.php',
    'JWT Handler' => __DIR__ . '/../shared/classes/JWTHandler.php',
    'ApiClient' => __DIR__ . '/../shared/classes/ApiClient.php'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "<span style='color: green;'>✓ $name exists</span><br>";
        echo "<small>Path: $path</small><br><br>";
    } else {
        echo "<span style='color: red;'>✗ $name NOT FOUND</span><br>";
        echo "<small>Expected: $path</small><br><br>";
    }
}
?>