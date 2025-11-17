<?php
/**
 * Debug Registration Test
 * Đặt file này ở: TransportationRenting/debug-register.php
 * Truy cập: http://localhost/TransportationRenting/debug-register.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/shared/classes/DatabaseManager.php';

$testUsername = 'newuser' . rand(1000, 9999);
$testEmail = 'test' . rand(1000, 9999) . '@example.com';

echo "<h1>🔍 Registration Debug Test</h1>";
echo "<pre>";

try {
    $db = DatabaseManager::getConnection('customer');
    
    echo "✅ Database connected\n\n";
    
    // Test 1: Check username
    echo "=== TEST 1: Check Username Exists ===\n";
    $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE username = ?");
    $stmt->execute([$testUsername]);
    $usernameCount = $stmt->fetchColumn();
    
    echo "Username: {$testUsername}\n";
    echo "Count: {$usernameCount}\n";
    echo "Exists: " . ($usernameCount > 0 ? 'YES' : 'NO') . "\n\n";
    
    // Test 2: Check email
    echo "=== TEST 2: Check Email Exists ===\n";
    $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE email = ?");
    $stmt->execute([$testEmail]);
    $emailCount = $stmt->fetchColumn();
    
    echo "Email: {$testEmail}\n";
    echo "Count: {$emailCount}\n";
    echo "Exists: " . ($emailCount > 0 ? 'YES' : 'NO') . "\n\n";
    
    // Test 3: Try insert
    if ($usernameCount == 0 && $emailCount == 0) {
        echo "=== TEST 3: Try Insert ===\n";
        
        $stmt = $db->prepare("
            INSERT INTO Users (username, password, name, email, phone, birthdate, status, email_verified, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'Pending', FALSE, NOW())
        ");
        
        $testData = [
            $testUsername,
            password_hash('test123', PASSWORD_DEFAULT),
            'Test User',
            $testEmail,
            '0123456789',
            '2000-01-01'
        ];
        
        echo "Executing INSERT...\n";
        $success = $stmt->execute($testData);
        
        if ($success) {
            $userId = $db->lastInsertId();
            echo "✅ INSERT SUCCESS!\n";
            echo "User ID: {$userId}\n\n";
            
            // Verify insert
            echo "=== Verify Insert ===\n";
            $stmt = $db->prepare("SELECT user_id, username, email FROM Users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "Retrieved user:\n";
            print_r($user);
            
            // Cleanup
            echo "\n=== Cleanup ===\n";
            $stmt = $db->prepare("DELETE FROM Users WHERE user_id = ?");
            $stmt->execute([$userId]);
            echo "✅ Test user deleted\n";
            
        } else {
            echo "❌ INSERT FAILED!\n";
            print_r($stmt->errorInfo());
        }
    }
    
    // Test 4: Check existing usernames
    echo "\n=== TEST 4: All Existing Users ===\n";
    $stmt = $db->query("SELECT user_id, username, email, created_at FROM Users ORDER BY user_id DESC LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "ID: {$user['user_id']} | Username: {$user['username']} | Email: {$user['email']}\n";
    }
    
    echo "\n✅ All tests completed!";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "</pre>";

// Form để test thủ công
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Register</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .test-form { background: #f0f0f0; padding: 20px; margin-top: 20px; }
        input { padding: 8px; margin: 5px 0; width: 300px; }
        button { padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="test-form">
        <h2>Manual Test Form</h2>
        <form method="POST" action="http://localhost/TransportationRenting/gateway/api/auth/register">
            <input type="text" name="username" placeholder="Username" value="testuser<?= rand(100, 999) ?>" required><br>
            <input type="password" name="password" placeholder="Password" value="test123" required><br>
            <input type="text" name="name" placeholder="Full Name" value="Test User" required><br>
            <input type="email" name="email" placeholder="Email" value="test<?= rand(100, 999) ?>@example.com" required><br>
            <input type="tel" name="phone" placeholder="Phone" value="0123456789"><br>
            <input type="date" name="birthdate" placeholder="Birthdate" value="2000-01-01"><br>
            <button type="submit">Test Register via Gateway</button>
        </form>
        
        <p><strong>Note:</strong> Form này POST trực tiếp đến Gateway API</p>
    </div>
    
    <div class="test-form" style="margin-top: 20px;">
        <h2>Test với CURL</h2>
        <pre style="background: white; padding: 10px;">
curl -X POST http://localhost/TransportationRenting/gateway/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "test<?= rand(100, 999) ?>",
    "password": "test123",
    "name": "Test User",
    "email": "test<?= rand(100, 999) ?>@example.com",
    "phone": "0123456789"
  }'
        </pre>
    </div>
</body>
</html>