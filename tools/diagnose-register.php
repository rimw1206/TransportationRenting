<?php
/**
 * tools/diagnose-register.php
 * Comprehensive diagnostic for registration flow
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 Registration Flow Diagnostic\n";
echo str_repeat("=", 50) . "\n\n";

// Test 1: Check file existence
echo "1️⃣ File Existence Check\n";
$files = [
    'Gateway Index' => __DIR__ . '/../gateway/api/index.php',
    'Auth Service' => __DIR__ . '/../services/customer/services/AuthService.php',
    'User Model' => __DIR__ . '/../services/customer/classes/Customer.php',
    'Register Endpoint' => __DIR__ . '/../services/customer/api/auth/register.php',
    'Database Manager' => __DIR__ . '/../shared/classes/DatabaseManager.php',
    'Email Service' => __DIR__ . '/../shared/classes/EmailService.php',
    'JWT Handler' => __DIR__ . '/../shared/classes/JWTHandler.php',
];

foreach ($files as $name => $path) {
    echo sprintf("   %-20s: %s\n", $name, file_exists($path) ? "✅ EXISTS" : "❌ MISSING at $path");
}
echo "\n";

// Test 2: Check .env loading
echo "2️⃣ Environment Variables\n";
require_once __DIR__ . '/../env-bootstrap.php';

$envVars = [
    'JWT_SECRET',
    'CUSTOMER_DB_HOST',
    'CUSTOMER_DB_NAME',
    'CUSTOMER_DB_USER',
    'MAIL_HOST',
    'MAIL_USERNAME',
];

foreach ($envVars as $var) {
    $value = getenv($var);
    if ($value) {
        $display = ($var === 'JWT_SECRET') ? substr($value, 0, 10) . '...' : $value;
        echo "   ✅ $var = $display\n";
    } else {
        echo "   ❌ $var = NOT SET\n";
    }
}
echo "\n";

// Test 3: Database Connection
echo "3️⃣ Database Connection Test\n";
try {
    require_once __DIR__ . '/../shared/classes/DatabaseManager.php';
    $db = DatabaseManager::getConnection('customer');
    echo "   ✅ Connected to customer database\n";
    
    // Check Users table
    $stmt = $db->query("SHOW TABLES LIKE 'Users'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ Users table exists\n";
        
        // Check columns
        $stmt = $db->query("DESCRIBE Users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $required = ['user_id', 'username', 'password', 'email', 'email_verified'];
        foreach ($required as $col) {
            echo sprintf("      - %-15s: %s\n", $col, in_array($col, $columns) ? "✅" : "❌ MISSING");
        }
    } else {
        echo "   ❌ Users table does not exist\n";
    }
    
    // Check email_verifications table
    $stmt = $db->query("SHOW TABLES LIKE 'email_verifications'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ email_verifications table exists\n";
    } else {
        echo "   ⚠️  email_verifications table missing (will be created)\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Database Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: AuthService instantiation
echo "4️⃣ AuthService Test\n";
try {
    require_once __DIR__ . '/../services/customer/services/AuthService.php';
    $authService = new AuthService();
    echo "   ✅ AuthService instantiated successfully\n";
} catch (Exception $e) {
    echo "   ❌ AuthService Error: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    foreach (explode("\n", $e->getTraceAsString()) as $line) {
        echo "      $line\n";
    }
}
echo "\n";

// Test 5: Direct API call to service
echo "5️⃣ Direct Service Call Test\n";
$testData = [
    'username' => 'diagtest_' . time(),
    'password' => 'Test123456',
    'name' => 'Diagnostic Test',
    'email' => 'diagtest_' . time() . '@example.com',
];

$ch = curl_init('http://localhost:8001/auth/register');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
echo "   Response: " . substr($response, 0, 200) . "\n";

if ($httpCode === 201 || $httpCode === 200) {
    echo "   ✅ Registration successful\n";
} else {
    echo "   ❌ Registration failed\n";
    $decoded = json_decode($response, true);
    if ($decoded && isset($decoded['message'])) {
        echo "   Error: " . $decoded['message'] . "\n";
    }
}
echo "\n";

// Test 6: Check service logs
echo "6️⃣ Recent Service Logs\n";
$logFile = __DIR__ . '/../services/customer/logs/customer_api_errors.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recent = array_slice($lines, -10);
    echo "   Last 10 log entries:\n";
    foreach ($recent as $line) {
        echo "   " . trim($line) . "\n";
    }
} else {
    echo "   ⚠️  Log file not found at: $logFile\n";
}
echo "\n";

// Test 7: Check Apache error log
echo "7️⃣ Apache Error Log Check\n";
$apacheLog = 'C:/xampp/apache/logs/error.log';
if (file_exists($apacheLog)) {
    $lines = file($apacheLog);
    $recent = array_slice($lines, -5);
    echo "   Last 5 Apache errors:\n";
    foreach ($recent as $line) {
        if (stripos($line, 'TransportationRenting') !== false) {
            echo "   " . trim($line) . "\n";
        }
    }
} else {
    echo "   ⚠️  Apache log not found\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Diagnostic complete!\n";