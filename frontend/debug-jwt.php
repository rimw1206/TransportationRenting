<?php
session_start();

require_once __DIR__ . '/../shared/classes/JWTHandler.php';

if (!isset($_SESSION['token'])) {
    die("Please login first");
}

$token = $_SESSION['token'];

echo "<h1>JWT Debug</h1>";

echo "<h2>Session Data</h2>";
echo "<pre>";
print_r($_SESSION['user']);
echo "</pre>";

echo "<h2>Token</h2>";
echo "<textarea style='width:100%;height:100px'>$token</textarea>";

echo "<h2>Decoded Token</h2>";
try {
    $jwtHandler = new JWTHandler();
    $decoded = $jwtHandler->decode($token);
    
    echo "<pre>";
    print_r($decoded);
    echo "</pre>";
    
    echo "<h3>User ID from token: " . ($decoded->user_id ?? 'NOT FOUND') . "</h3>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Test direct database query
echo "<h2>Direct Database Check</h2>";
require_once __DIR__ . '/../shared/classes/DatabaseManager.php';

try {
    $conn = DatabaseManager::getInstance('payment');
    
    // Get all transactions for user_id = 5
    $stmt = $conn->prepare("SELECT * FROM Transactions WHERE user_id = 5");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Transactions for user_id=5:</strong></p>";
    echo "<pre>";
    print_r($results);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Database error: " . $e->getMessage() . "</p>";
}