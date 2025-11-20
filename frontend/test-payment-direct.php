<?php
session_start();

if (!isset($_SESSION['token'])) {
    die("Please login first at <a href='login.php'>login.php</a>");
}

$token = $_SESSION['token'];
$userId = $_SESSION['user']['user_id'];

echo "<h1>Payment API Direct Test</h1>";
echo "<p>User ID: $userId</p>";
echo "<p>Token: " . substr($token, 0, 20) . "...</p>";

// Test 1: Get all transactions
echo "<h2>Test 1: All Transactions</h2>";
$ch = curl_init('http://localhost:8005/payments/transactions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Status:</strong> $httpCode</p>";
echo "<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre>";

// Test 2: Get by rental_id
echo "<h2>Test 2: Filter by rental_id=6</h2>";
$ch = curl_init('http://localhost:8005/payments/transactions?rental_id=6');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Status:</strong> $httpCode</p>";
echo "<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre>";