<?php
// run.php - Auto-start services với database setup tự động (FIXED VERSION)

/**
 * Load .env file và set environment variables
 */
function loadEnvFile() {
    $envFile = __DIR__ . '/.env';
    
    if (!file_exists($envFile)) {
        error_log("⚠️  .env file không tồn tại, sử dụng config mặc định");
        return false;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes
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
 * Get database configs từ .env
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
 * Tạo database nếu chưa tồn tại
 */
function createDatabaseIfNotExists($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']}";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->query("SHOW DATABASES LIKE '{$config['dbname']}'");
        $exists = $stmt->rowCount() > 0;
        
        if (!$exists) {
            echo "   🔧 Tạo database: {$config['dbname']}\n";
            $conn->exec("CREATE DATABASE {$config['dbname']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("❌ Lỗi tạo database: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup Customer Database với demo data và FIXED missing columns
 */
function setupCustomerDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // ✅ FIX: Create Users table với ALL required columns including last_login
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
                last_login DATETIME NULL COMMENT 'Last successful login timestamp',
                status ENUM('Active', 'Inactive', 'Pending') DEFAULT 'Pending',
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_status (status),
                INDEX idx_last_login (last_login)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // ✅ FIX: Check and add last_login column if missing (for existing tables)
        $checkColumn = $conn->query("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = '{$config['dbname']}' 
            AND TABLE_NAME = 'Users' 
            AND COLUMN_NAME = 'last_login'
        ")->fetch(PDO::FETCH_ASSOC);
        
        if ($checkColumn['count'] == 0) {
            echo "   🔧 Adding missing 'last_login' column...\n";
            $conn->exec("
                ALTER TABLE Users 
                ADD COLUMN last_login DATETIME NULL COMMENT 'Last successful login timestamp' 
                AFTER created_at
            ");
            echo "   ✅ Column 'last_login' added\n";
        }
        
        // Create KYC table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS KYC (
                kyc_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                identity_number VARCHAR(50) UNIQUE,
                id_card_front_url TEXT,
                id_card_back_url TEXT,
                verified_at DATETIME,
                verification_status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
                FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_status (verification_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create PaymentMethod table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS PaymentMethod (
                method_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('CreditCard', 'DebitCard', 'EWallet', 'BankTransfer') NOT NULL,
                provider VARCHAR(50) NOT NULL,
                account_number VARCHAR(50),
                expiry_date DATE,
                is_default BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_default (is_default)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create RentalHistory table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS RentalHistory (
                history_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                rental_id INT NOT NULL,
                rented_at DATETIME NOT NULL,
                returned_at DATETIME,
                total_cost DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_rental_id (rental_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Check if demo data exists
        $stmt = $conn->query("SELECT COUNT(*) as count FROM Users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            echo "   📝 Thêm dữ liệu demo với password hash...\n";
            
            // ✅ Demo users với password đã hash ĐÚNG
            $demoUsers = [
                ['admin', 'admin123', 'Administrator', 'admin@transportation.com', '0901234567', '1990-01-01', 'Active'],
                ['user', 'user123', 'Nguyễn Văn A', 'user@example.com', '0912345678', '1995-05-15', 'Active'],
                ['customer1', 'customer123', 'Trần Thị B', 'customer1@example.com', '0923456789', '1992-08-20', 'Active'],
                ['customer2', 'customer456', 'Phạm Văn D', 'customer2@example.com', '0945678901', '1993-03-25', 'Active'],
                ['pending_user', 'pending123', 'Lê Văn C', 'pending@example.com', '0934567890', '1998-12-10', 'Pending'],
                ['inactive_user', 'inactive123', 'Hoàng Thị E', 'inactive@example.com', '0956789012', '1997-07-18', 'Inactive'],
            ];
            
            $stmt = $conn->prepare("
                INSERT INTO Users (username, password, name, email, phone, birthdate, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            foreach ($demoUsers as $user) {
                // ✅ Hash password TRƯỚC KHI insert
                $passwordHash = password_hash($user[1], PASSWORD_DEFAULT);
                
                $stmt->execute([
                    $user[0],      // username
                    $passwordHash, // hashed password
                    $user[2],      // name
                    $user[3],      // email
                    $user[4],      // phone
                    $user[5],      // birthdate
                    $user[6]       // status
                ]);
                
                echo "      ✅ {$user[0]} / {$user[1]}\n";
            }
            
            // Insert KYC records
            echo "   📋 Thêm KYC records...\n";
            $conn->exec("
                INSERT INTO KYC (user_id, identity_number, verification_status, verified_at) 
                SELECT user_id, 
                       CONCAT('00123456789', user_id),
                       CASE WHEN status = 'Active' THEN 'Verified' ELSE 'Pending' END,
                       CASE WHEN status = 'Active' THEN NOW() ELSE NULL END
                FROM Users
                WHERE status IN ('Active', 'Pending')
            ");
            
            // Insert payment methods
            echo "   💳 Thêm payment methods...\n";
            $conn->exec("
                INSERT INTO PaymentMethod (user_id, type, provider, account_number, is_default)
                SELECT user_id, 
                       CASE 
                           WHEN user_id % 3 = 0 THEN 'CreditCard'
                           WHEN user_id % 3 = 1 THEN 'EWallet'
                           ELSE 'BankTransfer'
                       END,
                       CASE 
                           WHEN user_id % 3 = 0 THEN 'Visa'
                           WHEN user_id % 3 = 1 THEN 'MoMo'
                           ELSE 'Vietcombank'
                       END,
                       CONCAT('**** **** **** ', LPAD(user_id, 4, '0')),
                       TRUE
                FROM Users
                WHERE status = 'Active'
            ");
            
            echo "\n   ✅ Demo accounts:\n";
            echo "      • admin / admin123 (Active)\n";
            echo "      • user / user123 (Active)\n";
            echo "      • customer1 / customer123 (Active)\n";
            echo "      • customer2 / customer456 (Active)\n";
            echo "      • pending_user / pending123 (Pending)\n";
            echo "      • inactive_user / inactive123 (Inactive)\n";
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Lỗi setup Customer database: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup các database service khác
 */
function setupOtherDatabases($config, $serviceName) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        switch ($serviceName) {
            case 'vehicle':
                $conn->exec("
                    CREATE TABLE IF NOT EXISTS Vehicles (
                        vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        type ENUM('Car', 'Motorcycle', 'Bicycle', 'Truck') NOT NULL,
                        brand VARCHAR(50),
                        model VARCHAR(50),
                        year INT,
                        license_plate VARCHAR(20) UNIQUE,
                        status ENUM('Available', 'Rented', 'Maintenance', 'Retired') DEFAULT 'Available',
                        daily_rate DECIMAL(10,2) NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_type (type),
                        INDEX idx_status (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                break;
                
            case 'rental':
                $conn->exec("
                    CREATE TABLE IF NOT EXISTS Rentals (
                        rental_id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        vehicle_id INT NOT NULL,
                        start_date DATETIME NOT NULL,
                        end_date DATETIME NOT NULL,
                        status ENUM('Pending', 'Active', 'Completed', 'Cancelled') DEFAULT 'Pending',
                        total_cost DECIMAL(10,2),
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_user (user_id),
                        INDEX idx_vehicle (vehicle_id),
                        INDEX idx_status (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                break;
                
            case 'order':
                $conn->exec("
                    CREATE TABLE IF NOT EXISTS Orders (
                        order_id INT AUTO_INCREMENT PRIMARY KEY,
                        rental_id INT NOT NULL,
                        delivery_address TEXT,
                        status ENUM('Pending', 'Processing', 'Delivering', 'Completed', 'Cancelled') DEFAULT 'Pending',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_rental (rental_id),
                        INDEX idx_status (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                break;
                
            case 'payment':
                $conn->exec("
                    CREATE TABLE IF NOT EXISTS Payments (
                        payment_id INT AUTO_INCREMENT PRIMARY KEY,
                        rental_id INT NOT NULL,
                        amount DECIMAL(10,2) NOT NULL,
                        payment_method VARCHAR(50),
                        status ENUM('Pending', 'Completed', 'Failed', 'Refunded') DEFAULT 'Pending',
                        transaction_id VARCHAR(100),
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_rental (rental_id),
                        INDEX idx_status (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                break;
                
            case 'notification':
                $conn->exec("
                    CREATE TABLE IF NOT EXISTS Notifications (
                        notification_id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        title VARCHAR(200) NOT NULL,
                        message TEXT NOT NULL,
                        type ENUM('Info', 'Warning', 'Success', 'Error') DEFAULT 'Info',
                        is_read BOOLEAN DEFAULT FALSE,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_user (user_id),
                        INDEX idx_read (is_read)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                break;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Lỗi setup {$serviceName} database: " . $e->getMessage());
        return false;
    }
}

/**
 * Khởi tạo tất cả databases
 */
function ensureDatabasesSetup($silent = false) {
    $setupFlagFile = __DIR__ . '/.db_setup_complete';
    
    if (file_exists($setupFlagFile)) {
        return true;
    }
    
    if (!$silent) echo "\n🚀 Khởi tạo databases...\n\n";
    
    loadEnvFile();
    $configs = getDatabaseConfigs();
    
    foreach ($configs as $service => $config) {
        if (!$silent) echo "📦 Service: {$service}\n";
        
        $isNew = createDatabaseIfNotExists($config);
        if ($isNew) {
            if (!$silent) echo "   ✅ Database created: {$config['dbname']}\n";
        } else {
            if (!$silent) echo "   ℹ️  Database exists: {$config['dbname']}\n";
        }
        
        if ($service === 'customer') {
            setupCustomerDatabase($config);
        } else {
            setupOtherDatabases($config, $service);
        }
        
        if (!$silent) echo "   ✅ Tables setup complete\n\n";
    }
    
    file_put_contents($setupFlagFile, date('Y-m-d H:i:s'));
    if (!$silent) {
        echo "✅ Database setup hoàn tất!\n";
        echo "💡 Xóa file .db_setup_complete để reset\n\n";
    }
    
    return true;
}

/**
 * Kiểm tra port đang chạy
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
 * Start service background
 */
function startServiceBackground($serviceName, $folder, $port) {
    if (!is_dir($folder)) {
        error_log("Folder not found: $folder");
        return false;
    }
    
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
 * Start tất cả services
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

// MAIN EXECUTION
// Chỉ chạy khi được gọi trực tiếp, KHÔNG phải từ index.php
if (php_sapi_name() === 'cli' || (basename($_SERVER['SCRIPT_FILENAME']) === 'run.php')) {
    ensureDatabasesSetup(false);
    ensureServicesRunning();
} elseif (basename($_SERVER['SCRIPT_FILENAME']) === 'index.php') {
    // Được gọi từ index.php - chạy silent mode
    ensureDatabasesSetup(false); // Output vẫn hiển thị cho setup page
    ensureServicesRunning();
}