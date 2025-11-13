<?php
/**
 * ============================================
 * tools/debug_auth.php
 * Debug AuthService để tìm nguyên nhân login fail
 * ============================================
 * 
 * CÁCH CHẠY:
 * php tools/debug_auth.php
 */

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║   AuthService Debugger                       ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// Load .env
function loadEnv() {
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
}

loadEnv();

require_once __DIR__ . '/../shared/classes/DatabaseManager.php';
require_once __DIR__ . '/../shared/classes/Cache.php';

echo "🔍 Step 1: Database Connection Test\n";
echo str_repeat("-", 60) . "\n";

try {
    $conn = DatabaseManager::getConnection('customer');
    echo "✅ Database connected: customer_service_db\n";
    
    // Get database name
    $stmt = $conn->query("SELECT DATABASE() as db");
    $currentDb = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Current DB: {$currentDb['db']}\n";
    
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🔍 Step 2: Check Users Table\n";
echo str_repeat("-", 60) . "\n";

try {
    // Check table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'Users'");
    if ($stmt->rowCount() === 0) {
        echo "❌ Table 'Users' does NOT exist!\n";
        echo "   Available tables:\n";
        $stmt = $conn->query("SHOW TABLES");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
            echo "   - {$table}\n";
        }
        exit(1);
    }
    echo "✅ Table 'Users' exists\n";
    
    // Check columns
    echo "\n   Columns:\n";
    $stmt = $conn->query("DESCRIBE Users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "   - {$col['Field']} ({$col['Type']})\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🔍 Step 3: Check Users Data\n";
echo str_repeat("-", 60) . "\n";

try {
    $stmt = $conn->query("SELECT user_id, username, name, email, status FROM Users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "❌ NO USERS in database!\n";
        exit(1);
    }
    
    echo "✅ Found " . count($users) . " users:\n\n";
    foreach ($users as $user) {
        echo "   👤 user_id: {$user['user_id']}\n";
        echo "      username: {$user['username']}\n";
        echo "      name: {$user['name']}\n";
        echo "      email: {$user['email']}\n";
        echo "      status: {$user['status']}\n\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "🔍 Step 4: Test AuthService Query (Exact Copy)\n";
echo str_repeat("-", 60) . "\n";

$testUsername = 'admin';
echo "Testing username: {$testUsername}\n\n";

try {
    // THIS IS THE EXACT QUERY FROM AuthService
    $sql = "SELECT user_id, username, password, name, email, phone, status 
            FROM Users WHERE username = ? LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$testUsername]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ QUERY RETURNED NULL!\n";
        echo "   This is why login fails: 'Tài khoản không tồn tại'\n\n";
        
        // Try without LIMIT
        echo "   Trying without prepare statement:\n";
        $stmt = $conn->query("SELECT * FROM Users WHERE username = '{$testUsername}'");
        $user2 = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user2) {
            echo "   ⚠️  Found user with direct query!\n";
            echo "   Issue: Prepared statement problem\n";
        } else {
            echo "   ❌ Still no user found\n";
            
            // Check all usernames
            echo "\n   All usernames in DB:\n";
            $stmt = $conn->query("SELECT username FROM Users");
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uname) {
                echo "   - '{$uname}' (length: " . strlen($uname) . ")\n";
            }
        }
        
    } else {
        echo "✅ QUERY SUCCESSFUL!\n\n";
        echo "   Retrieved user:\n";
        foreach ($user as $key => $value) {
            if ($key === 'password') {
                echo "   - {$key}: " . substr($value, 0, 30) . "...\n";
            } else {
                echo "   - {$key}: {$value}\n";
            }
        }
        
        // Test password
        echo "\n   Testing password verification:\n";
        $testPassword = 'admin123';
        $verified = password_verify($testPassword, $user['password']);
        
        if ($verified) {
            echo "   ✅ Password CORRECT for '{$testPassword}'\n";
        } else {
            echo "   ❌ Password WRONG for '{$testPassword}'\n";
            echo "   Hash in DB: {$user['password']}\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Query error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🔍 Step 5: Test Full AuthService Flow\n";
echo str_repeat("-", 60) . "\n";

try {
    require_once __DIR__ . '/../services/customer/services/AuthService.php';
    
    $authService = new AuthService();
    $result = $authService->login('admin', 'admin123');
    
    echo "AuthService Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    if ($result['success']) {
        echo "\n✅ AuthService login SUCCESS!\n";
    } else {
        echo "\n❌ AuthService login FAILED!\n";
        echo "   Message: {$result['message']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ AuthService error: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n🔍 Step 6: Test API Endpoint\n";
echo str_repeat("-", 60) . "\n";

$apiUrl = "http://localhost/TransportationRenting/gateway/api/auth/login";
echo "Calling: {$apiUrl}\n\n";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => 'admin',
    'password' => 'admin123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo "❌ CURL Error: {$curlError}\n";
} else {
    echo "HTTP Status: {$httpCode}\n";
    echo "Response:\n";
    
    $result = json_decode($response, true);
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    if (isset($result['success']) && $result['success']) {
        echo "\n✅ API LOGIN SUCCESS!\n";
    } else {
        echo "\n❌ API LOGIN FAILED!\n";
        echo "   Message: " . ($result['message'] ?? 'Unknown') . "\n";
    }
}

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║          Debug Complete                      ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";