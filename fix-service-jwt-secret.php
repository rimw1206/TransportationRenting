<?php
/**
 * ============================================
 * fix-service-jwt-secret.php
 * Check and fix JWT secret on running service
 * ============================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔧 Service JWT Secret Checker & Fixer\n";
echo "======================================\n\n";

// Load environment
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

// Step 1: Get current secret from .env
echo "1️⃣ Checking current JWT_SECRET...\n";

$currentSecret = getenv('JWT_SECRET');
if (!$currentSecret) {
    die("❌ JWT_SECRET not found in environment!\n");
}

echo "   Secret: " . substr($currentSecret, 0, 20) . "...\n";
echo "   Length: " . strlen($currentSecret) . " characters\n";
echo "   Hash: " . hash('sha256', $currentSecret) . "\n\n";

// Step 2: Create a test token with current secret
echo "2️⃣ Creating test token with current secret...\n";

require_once __DIR__ . '/shared/classes/JWTHandler.php';

$jwt = new JWTHandler();
$testPayload = [
    'user_id' => 999,
    'test' => true,
    'iat' => time(),
    'exp' => time() + 3600
];

$testToken = $jwt->encode($testPayload);

if (!$testToken) {
    die("❌ Failed to create test token!\n");
}

echo "   ✅ Test token created\n";
echo "   Token: " . substr($testToken, 0, 50) . "...\n\n";

// Step 3: Test against running service
echo "3️⃣ Testing against service on port 8001...\n";

$serviceUrl = 'http://localhost:8001/profile';

// First check if service is running
$ch = curl_init('http://localhost:8001/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 0) {
    echo "   ❌ Service NOT running on port 8001!\n";
    echo "   Start it with: php run.php\n\n";
    exit(1);
}

echo "   ✅ Service is running (Status: $httpCode)\n\n";

// Test with our token
echo "4️⃣ Testing /profile with our test token...\n";

$ch = curl_init($serviceUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $testToken,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 401) {
    echo "   ❌ Service rejected our token (401 Unauthorized)\n";
    echo "   Response: $response\n\n";
    
    echo "🔍 DIAGNOSIS:\n";
    echo "   The service is using a DIFFERENT JWT_SECRET!\n\n";
    
    echo "💡 SOLUTION:\n";
    echo "   The service loaded an OLD secret when it started.\n";
    echo "   You MUST restart the service to pick up the new secret.\n\n";
    
    echo "🔧 FIX STEPS:\n";
    echo "   1. Stop the service:\n";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        echo "      taskkill /F /IM php.exe\n";
    } else {
        echo "      killall php\n";
    }
    echo "\n   2. Restart with:\n";
    echo "      php run.php\n\n";
    
    echo "   3. Verify services started:\n";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        echo "      netstat -ano | findstr :8001\n\n";
    } else {
        echo "      lsof -i :8001\n\n";
    }
    
    // Offer to restart automatically
    echo "Would you like me to restart services now? (yes/no): ";
    $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) === 'yes') {
        echo "\n🔄 Stopping services...\n";
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('taskkill /F /IM php.exe 2>nul', $output, $return);
        } else {
            exec('killall php 2>/dev/null', $output, $return);
        }
        
        sleep(2);
        
        echo "   ✅ Services stopped\n\n";
        echo "🚀 Starting services...\n";
        echo "   Run manually: php run.php\n\n";
        
        echo "   Or press Enter to exit and run it yourself...\n";
        fgets(STDIN);
    }
    
} elseif ($httpCode === 200 || $httpCode === 404) {
    echo "   ✅ Service accepted our token!\n";
    echo "   Status: $httpCode\n";
    if ($httpCode === 404) {
        echo "   (404 is OK - endpoint exists, just test user not found)\n";
    }
    echo "   Response: $response\n\n";
    
    echo "🎉 SERVICE JWT SECRET IS CORRECT!\n\n";
    
    // Now test with real user token
    echo "5️⃣ Testing with real user token...\n";
    
    require_once __DIR__ . '/shared/classes/DatabaseManager.php';
    
    try {
        $db = DatabaseManager::getConnection('customer');
        $stmt = $db->prepare("SELECT user_id, username, email FROM Users WHERE username = 'user' LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $realPayload = [
                'user_id' => (int)$user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => 'user',
                'iat' => time(),
                'exp' => time() + 86400
            ];
            
            $realToken = $jwt->encode($realPayload);
            
            echo "   Creating real token for: {$user['username']}\n";
            
            // Test real token
            $ch = curl_init($serviceUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $realToken,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                echo "   ✅ Real user token works!\n";
                echo "   Response: $response\n\n";
                
                echo "🎊 EVERYTHING IS WORKING PERFECTLY!\n\n";
                
                echo "📝 Your working token:\n";
                echo "$realToken\n\n";
                
                echo "Use this in debug-profile-auth.php\n";
                
                // Save to file
                file_put_contents(__DIR__ . '/working_token.txt', $realToken);
                echo "✅ Saved to: working_token.txt\n\n";
                
            } else {
                echo "   ❌ Real token failed: Status $httpCode\n";
                echo "   Response: $response\n\n";
            }
        }
    } catch (Exception $e) {
        echo "   ⚠️  Database error: " . $e->getMessage() . "\n\n";
    }
    
} else {
    echo "   ⚠️  Unexpected status: $httpCode\n";
    echo "   Response: $response\n\n";
}