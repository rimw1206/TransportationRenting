<?php
/**
 * ============================================
 * tools/test_login.php
 * Script để test login functionality
 * ============================================
 * 
 * CÁCH CHẠY:
 * php tools/test_login.php
 */

class LoginTester {
    private $baseUrl = 'http://localhost/TransportationRenting/gateway/api';
    
    public function testLogin($username, $password) {
        echo "\n🔐 Testing login: {$username} / {$password}\n";
        echo str_repeat("-", 60) . "\n";
        
        $url = $this->baseUrl . '/auth/login';
        $data = json_encode([
            'username' => $username,
            'password' => $password
        ]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            echo "❌ CURL Error: {$curlError}\n";
            return false;
        }
        
        echo "HTTP Status: {$httpCode}\n";
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['success']) && $result['success']) {
            echo "✅ LOGIN SUCCESS!\n";
            echo "   User: {$result['user']['name']}\n";
            echo "   Email: {$result['user']['email']}\n";
            echo "   Token: " . substr($result['token'], 0, 50) . "...\n";
            return true;
        } else {
            echo "❌ LOGIN FAILED!\n";
            echo "   Message: " . ($result['message'] ?? 'Unknown error') . "\n";
            return false;
        }
    }
    
    public function testPasswordHash($password) {
        echo "\n🔑 Testing Password Hash for: {$password}\n";
        echo str_repeat("-", 60) . "\n";
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        echo "Generated Hash:\n{$hash}\n\n";
        
        $verified = password_verify($password, $hash);
        echo "Verification: " . ($verified ? "✅ PASS" : "❌ FAIL") . "\n";
        
        return $hash;
    }
    
    public function testDatabaseConnection() {
        echo "\n🗄️  Testing Database Connection\n";
        echo str_repeat("-", 60) . "\n";
        
        require_once __DIR__ . '/../shared/classes/DatabaseManager.php';
        
        // Load .env
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value, '"\'');
                }
            }
        }
        
        try {
            $conn = DatabaseManager::getConnection('customer');
            echo "✅ Database connected\n";
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM Users");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "   Total users: {$result['count']}\n";
            
            $stmt = $conn->query("SELECT username, name, status FROM Users LIMIT 5");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\n   Users in database:\n";
            foreach ($users as $user) {
                echo "   - {$user['username']} ({$user['name']}) - {$user['status']}\n";
            }
            
            return true;
        } catch (Exception $e) {
            echo "❌ Database error: {$e->getMessage()}\n";
            return false;
        }
    }
}

// ============================================
// MAIN EXECUTION
// ============================================

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║         Login Testing Tool                   ║\n";
echo "╚══════════════════════════════════════════════╝\n";

$tester = new LoginTester();

// Test database connection
$tester->testDatabaseConnection();

// Test password hashing
echo "\n";
$tester->testPasswordHash('admin123');

// Test login với các accounts khác nhau
$testAccounts = [
    ['username' => 'admin', 'password' => 'admin123'],
    ['username' => 'user', 'password' => 'user123'],
    ['username' => 'customer1', 'password' => 'customer123'],
    ['username' => 'admin', 'password' => 'wrongpassword'], // Should fail
];

foreach ($testAccounts as $account) {
    $tester->testLogin($account['username'], $account['password']);
}

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║         Testing Complete                     ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";