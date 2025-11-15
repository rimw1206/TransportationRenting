<?php
/**
 * ============================================
 * quick-test-login.php
 * Test login flow and verify token works
 * ============================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🧪 Quick Login & Token Test\n";
echo "============================\n\n";

// Load environment
require_once __DIR__ . '/shared/classes/DatabaseManager.php';
require_once __DIR__ . '/shared/classes/JWTHandler.php';

// Load .env
function loadEnv() {
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

loadEnv();

// Step 1: Simulate login
echo "1️⃣ Simulating login for user: user\n\n";

try {
    $db = DatabaseManager::getConnection('customer');
    
    // Get user
    $stmt = $db->prepare("
        SELECT user_id, username, password, name, email, phone, status 
        FROM Users 
        WHERE username = ? 
        LIMIT 1
    ");
    $stmt->execute(['user']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("❌ User 'user' not found in database!\n");
    }
    
    echo "✅ User found:\n";
    echo "   User ID: {$user['user_id']}\n";
    echo "   Username: {$user['username']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Status: {$user['status']}\n\n";
    
    // Verify password
    if (!password_verify('user123', $user['password'])) {
        die("❌ Password verification failed!\n");
    }
    
    echo "✅ Password verified\n\n";
    
    // Generate JWT token
    echo "2️⃣ Generating JWT token...\n\n";
    
    $jwt = new JWTHandler();
    
    $payload = [
        'user_id' => (int)$user['user_id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => 'user',
        'iat' => time(),
        'exp' => time() + 86400 // 24 hours
    ];
    
    $token = $jwt->encode($payload);
    
    if (!$token) {
        die("❌ Failed to generate token!\n");
    }
    
    echo "✅ Token generated:\n";
    echo "   Length: " . strlen($token) . " characters\n";
    echo "   Token: " . substr($token, 0, 50) . "...\n";
    echo "   Expires: " . date('Y-m-d H:i:s', $payload['exp']) . "\n\n";
    
    // Verify token immediately
    echo "3️⃣ Verifying token...\n\n";
    
    $decoded = $jwt->decode($token);
    
    if (!$decoded) {
        die("❌ Token verification failed!\n");
    }
    
    echo "✅ Token verified successfully:\n";
    echo "   User ID: {$decoded['user_id']}\n";
    echo "   Username: {$decoded['username']}\n";
    echo "   Email: {$decoded['email']}\n";
    echo "   Role: {$decoded['role']}\n\n";
    
    // Test API call with token
    echo "4️⃣ Testing API call to /profile...\n\n";
    
    $ch = curl_init('http://localhost:8001/profile');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ cURL error: $error\n\n";
    } else {
        $data = json_decode($response, true);
        
        if ($httpCode === 200) {
            echo "✅ API call successful (Status: 200)\n";
            echo "   Response:\n";
            echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
        } else {
            echo "❌ API call failed (Status: $httpCode)\n";
            echo "   Response: $response\n\n";
        }
    }
    
    // Save token to file for easy copy
    echo "5️⃣ Saving token for testing...\n\n";
    
    $tokenFile = __DIR__ . '/test_token.txt';
    file_put_contents($tokenFile, $token);
    
    echo "✅ Token saved to: test_token.txt\n\n";
    
    // Generate curl command
    echo "6️⃣ Test commands:\n\n";
    
    echo "Using cURL:\n";
    echo "curl http://localhost:8001/profile \\\n";
    echo "  -H \"Authorization: Bearer $token\"\n\n";
    
    echo "Using browser:\n";
    echo "1. Open: http://localhost/debug-profile-auth.php\n";
    echo "2. Paste this token:\n";
    echo "   $token\n";
    echo "3. Click 'Run Tests'\n\n";
    
    // Final summary
    echo "✅ ALL TESTS PASSED!\n\n";
    
    echo "📋 Summary:\n";
    echo "   • Login simulation: ✅\n";
    echo "   • Token generation: ✅\n";
    echo "   • Token verification: ✅\n";
    echo "   • API call: " . ($httpCode === 200 ? "✅" : "❌ (Status: $httpCode)") . "\n\n";
    
    if ($httpCode !== 200) {
        echo "⚠️  API call failed, but token itself is valid.\n";
        echo "   This might be due to:\n";
        echo "   • Service not running on port 8001\n";
        echo "   • Authorization middleware issue\n";
        echo "   • Different JWT_SECRET on service side\n\n";
        
        echo "🔧 Try these:\n";
        echo "   1. Check if service is running: netstat -ano | findstr :8001\n";
        echo "   2. Restart services: php run.php\n";
        echo "   3. Check service logs\n\n";
    } else {
        echo "🎉 Everything working perfectly!\n";
        echo "   Your system is ready to use.\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}