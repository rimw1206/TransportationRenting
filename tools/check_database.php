<?php
/**
 * ============================================
 * tools/check_database.php
 * Script kiểm tra trạng thái database
 * ============================================
 * 
 * CÁCH CHẠY:
 * php tools/check_database.php
 */

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║   Database Status Checker                    ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// Load .env
function loadEnv() {
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) {
        echo "⚠️  .env file không tồn tại\n";
        echo "   Sử dụng config mặc định\n\n";
        return;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
}

loadEnv();

// Database configs
$services = [
    'customer' => [
        'host' => $_ENV['CUSTOMER_DB_HOST'] ?? 'localhost',
        'port' => $_ENV['CUSTOMER_DB_PORT'] ?? 3306,
        'dbname' => $_ENV['CUSTOMER_DB_NAME'] ?? 'customer_service_db',
        'user' => $_ENV['CUSTOMER_DB_USER'] ?? 'root',
        'pass' => $_ENV['CUSTOMER_DB_PASS'] ?? '',
    ],
    'vehicle' => [
        'host' => $_ENV['VEHICLE_DB_HOST'] ?? 'localhost',
        'port' => $_ENV['VEHICLE_DB_PORT'] ?? 3306,
        'dbname' => $_ENV['VEHICLE_DB_NAME'] ?? 'vehicle_service_db',
        'user' => $_ENV['VEHICLE_DB_USER'] ?? 'root',
        'pass' => $_ENV['VEHICLE_DB_PASS'] ?? '',
    ],
    'rental' => [
        'host' => $_ENV['RENTAL_DB_HOST'] ?? 'localhost',
        'port' => $_ENV['RENTAL_DB_PORT'] ?? 3306,
        'dbname' => $_ENV['RENTAL_DB_NAME'] ?? 'rental_service_db',
        'user' => $_ENV['RENTAL_DB_USER'] ?? 'root',
        'pass' => $_ENV['RENTAL_DB_PASS'] ?? '',
    ],
];

foreach ($services as $service => $config) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📦 Service: " . strtoupper($service) . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "   Host: {$config['host']}:{$config['port']}\n";
    echo "   Database: {$config['dbname']}\n";
    echo "   User: {$config['user']}\n\n";
    
    try {
        // Check if database exists
        $dsn = "mysql:host={$config['host']};port={$config['port']}";
        $conn = new PDO($dsn, $config['user'], $config['pass']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->query("SHOW DATABASES LIKE '{$config['dbname']}'");
        
        if ($stmt->rowCount() > 0) {
            echo "   ✅ Database EXISTS\n";
            
            // Connect to specific database
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
            $conn = new PDO($dsn, $config['user'], $config['pass']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Get tables
            $stmt = $conn->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                echo "   ⚠️  NO TABLES FOUND!\n";
            } else {
                echo "   📊 Tables (" . count($tables) . "):\n";
                
                foreach ($tables as $table) {
                    // Count rows
                    $stmt = $conn->query("SELECT COUNT(*) as count FROM `{$table}`");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    $status = $count > 0 ? "✅" : "⚠️ ";
                    echo "      {$status} {$table}: {$count} rows\n";
                    
                    // Show sample data for Users table
                    if ($table === 'Users' && $count > 0) {
                        echo "\n      👥 Sample Users:\n";
                        $stmt = $conn->query("
                            SELECT username, name, email, status 
                            FROM Users 
                            LIMIT 5
                        ");
                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($users as $user) {
                            echo "         • {$user['username']} ({$user['name']}) - {$user['status']}\n";
                        }
                        echo "\n";
                    }
                }
            }
            
        } else {
            echo "   ❌ Database DOES NOT EXIST!\n";
            echo "      Run: php index.php (to create)\n";
        }
        
    } catch (PDOException $e) {
        echo "   ❌ CONNECTION FAILED!\n";
        echo "      Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔍 Setup Status File\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$setupFile = __DIR__ . '/../.db_setup_complete';
if (file_exists($setupFile)) {
    $time = file_get_contents($setupFile);
    echo "   ✅ Setup completed at: {$time}\n";
    echo "   💡 Delete this file to re-run setup:\n";
    echo "      rm .db_setup_complete\n";
} else {
    echo "   ⚠️  Setup NOT completed\n";
    echo "   💡 Run setup:\n";
    echo "      php index.php\n";
}

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║          Check Complete                      ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";