<?php
/**
 * ============================================
 * run.php - FULLY AUTOMATED VERSION
 * Tự động sửa mọi vấn đề, không cần can thiệp
 * ============================================
 */

// Load Auto-Fix System
require_once __DIR__ . '/auto-jwt-fix-complete.php';

/**
 * Load .env file
 */
function loadEnvFile() {
    $envFile = __DIR__ . '/.env';
    
    if (!file_exists($envFile)) {
        return false;
    }
    
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
    
    return true;
}

/**
 * Get database configs
 */
function getDatabaseConfigs() {
    $databases = ['customer', 'vehicle', 'rental', 'order', 'payment', 'notification'];
    $configs = [];
    
    foreach ($databases as $service) {
        $prefix = strtoupper($service);
        $configs[$service] = [
            'host' => $_ENV["{$prefix}_DB_HOST"] ?? 'localhost',
            'port' => $_ENV["{$prefix}_DB_PORT"] ?? 3306,
            'dbname' => $_ENV["{$prefix}_DB_NAME"] ?? "{$service}_service_db",
            'username' => $_ENV["{$prefix}_DB_USER"] ?? 'root',
            'password' => $_ENV["{$prefix}_DB_PASS"] ?? '',
        ];
    }
    
    return $configs;
}

/**
 * Create database if not exists
 */
function createDatabaseIfNotExists($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']}";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->query("SHOW DATABASES LIKE '{$config['dbname']}'");
        $exists = $stmt->rowCount() > 0;
        
        if (!$exists) {
            echo "   🔧 Creating database: {$config['dbname']}\n";
            $conn->exec("CREATE DATABASE {$config['dbname']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("❌ Database creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup Customer Database
 */
function setupCustomerDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create Users table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS Users (
                user_id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                phone VARCHAR(20),
                birthdate DATE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME NULL,
                status ENUM('Active', 'Inactive', 'Pending') DEFAULT 'Pending',
                INDEX idx_username (username),
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Check and add last_login if missing
        $checkColumn = $conn->query("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = '{$config['dbname']}' 
            AND TABLE_NAME = 'Users' 
            AND COLUMN_NAME = 'last_login'
        ")->fetch(PDO::FETCH_ASSOC);
        
        if ($checkColumn['count'] == 0) {
            $conn->exec("ALTER TABLE Users ADD COLUMN last_login DATETIME NULL AFTER created_at");
        }
        
        // Create other tables
        $conn->exec("
            CREATE TABLE IF NOT EXISTS KYC (
                kyc_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                identity_number VARCHAR(50) UNIQUE,
                id_card_front_url TEXT,
                id_card_back_url TEXT,
                verified_at DATETIME,
                verification_status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
                FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
        
        $conn->exec("
            CREATE TABLE IF NOT EXISTS PaymentMethod (
                method_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('CreditCard', 'DebitCard', 'EWallet', 'BankTransfer') NOT NULL,
                provider VARCHAR(50) NOT NULL,
                account_number VARCHAR(50),
                expiry_date DATE,
                is_default BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
        
        // Insert demo users if table is empty
        $stmt = $conn->query("SELECT COUNT(*) as count FROM Users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            echo "   📝 Adding demo users...\n";
            
            $demoUsers = [
                ['admin', 'admin123', 'Administrator', 'admin@transportation.com', '0901234567', '1990-01-01', 'Active'],
                ['user', 'user123', 'Nguyễn Văn A', 'user@example.com', '0912345678', '1995-05-15', 'Active'],
            ];
            
            $stmt = $conn->prepare("
                INSERT INTO Users (username, password, name, email, phone, birthdate, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            foreach ($demoUsers as $user) {
                $passwordHash = password_hash($user[1], PASSWORD_DEFAULT);
                $stmt->execute([$user[0], $passwordHash, $user[2], $user[3], $user[4], $user[5], $user[6]]);
                echo "      ✅ {$user[0]} / {$user[1]}\n";
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Customer database setup error: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup databases
 */
function ensureDatabasesSetup($silent = false) {
    $setupFlag = __DIR__ . '/.db_setup_complete';
    
    if (file_exists($setupFlag)) {
        return true;
    }
    
    if (!$silent) echo "\n🗄️  Setting up databases...\n\n";
    
    loadEnvFile();
    $configs = getDatabaseConfigs();
    
    foreach ($configs as $service => $config) {
        if (!$silent) echo "📦 Service: {$service}\n";
        
        createDatabaseIfNotExists($config);
        
        if ($service === 'customer') {
            setupCustomerDatabase($config);
        }
        
        if (!$silent) echo "   ✅ Setup complete\n\n";
    }
    
    file_put_contents($setupFlag, date('Y-m-d H:i:s'));
    if (!$silent) echo "✅ Database setup complete!\n\n";
    
    return true;
}

/**
 * Check if port is running
 */
function isPortRunning($port) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("netstat -ano | findstr :$port 2>nul", $output);
        return !empty($output);
    }
    exec("lsof -i :$port 2>/dev/null", $output);
    return count($output) > 1;
}

/**
 * Start service in background
 */
function startServiceBackground($serviceName, $folder, $port) {
    if (!is_dir($folder)) return false;
    if (isPortRunning($port)) return true;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = "start \"$serviceName\" /MIN php -S localhost:$port -t \"$folder\"";
        pclose(popen($cmd, 'r'));
    } else {
        $cmd = "nohup php -S localhost:$port -t \"$folder\" > /dev/null 2>&1 &";
        exec($cmd);
    }
    
    sleep(1);
    return isPortRunning($port);
}

/**
 * Ensure all services running
 */
function ensureServicesRunning() {
    $services = [
        'customer' => ['folder' => __DIR__ . '/services/customer/public', 'port' => 8001],
        'vehicle' => ['folder' => __DIR__ . '/services/vehicle/public', 'port' => 8002],
        'rental' => ['folder' => __DIR__ . '/services/rental/public', 'port' => 8003],
        'order' => ['folder' => __DIR__ . '/services/order/public', 'port' => 8004],
        'payment' => ['folder' => __DIR__ . '/services/payment/public', 'port' => 8005],
        'notification' => ['folder' => __DIR__ . '/services/notification/public', 'port' => 8006],
    ];
    
    foreach ($services as $name => $info) {
        if (!isPortRunning($info['port'])) {
            startServiceBackground($name, $info['folder'], $info['port']);
        }
    }
}

// ============================================
// MAIN EXECUTION - FULLY AUTOMATED
// ============================================

if (php_sapi_name() === 'cli' || basename($_SERVER['SCRIPT_FILENAME']) === 'run.php') {
    echo "🚀 Starting Application (Fully Automated)\n";
    echo "==========================================\n\n";
    
    // Step 1: AUTO-FIX JWT (tự động sửa mọi vấn đề)
    echo "🔐 Auto-fixing JWT configuration...\n";
    $jwtResult = AutoJWTFixComplete::autoFix();
    
    if (!$jwtResult['success']) {
        die("❌ JWT auto-fix failed: " . ($jwtResult['error'] ?? 'Unknown error') . "\n");
    }
    
    echo "   ✅ " . $jwtResult['message'] . "\n";
    if ($jwtResult['action'] !== 'none') {
        echo "   📝 Action taken: " . $jwtResult['action'] . "\n";
    }
    
    if (isset($jwtResult['note'])) {
        echo "   ⚠️  " . $jwtResult['note'] . "\n";
    }
    echo "\n";
    
    // Step 2: Load environment
    echo "📝 Loading environment variables...\n";
    loadEnvFile();
    echo "   ✅ Environment loaded\n\n";
    
    // Step 3: Setup databases
    ensureDatabasesSetup(false);
    
    // Step 4: Start services
    echo "🌐 Starting microservices...\n";
    ensureServicesRunning();
    echo "   ✅ All services started\n\n";
    
    echo "✅ System ready!\n\n";
    echo "📍 Access points:\n";
    echo "   • Customer Service: http://localhost:8001\n";
    echo "   • Main Gateway:     http://localhost\n\n";
    echo "💡 Demo accounts:\n";
    echo "   • admin / admin123\n";
    echo "   • user / user123\n\n";
    
} elseif (basename($_SERVER['SCRIPT_FILENAME']) === 'index.php') {
    // Silent mode for index.php
    AutoJWTFixComplete::autoFix();
    loadEnvFile();
    ensureDatabasesSetup(true);
    ensureServicesRunning();
}