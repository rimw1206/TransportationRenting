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
                email_verified BOOLEAN DEFAULT FALSE,
                phone VARCHAR(20),
                birthdate DATE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME NULL,
                status ENUM('Active','Inactive','Pending') DEFAULT 'Pending',
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_email_verified (email_verified),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
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
                rental_id INT NOT NULL COMMENT 'Reference only - NO FK to RentalDB',
                rented_at DATETIME NOT NULL,
                returned_at DATETIME,
                total_cost DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_rental_id (rental_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Email Verifications
        $conn->exec("
            CREATE TABLE IF NOT EXISTS email_verifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                email VARCHAR(100) NOT NULL,
                expires_at DATETIME NOT NULL,
                verified_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
                INDEX idx_token (token),
                INDEX idx_user_id (user_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insert demo users if table is empty
        $stmt = $conn->query("SELECT COUNT(*) as count FROM Users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            echo "   📝 Adding demo users...\n";
            
            $demoUsers = [
                ['admin', 'admin123', 'Administrator', 'admin@transportation.com', '0901234567', '1990-01-01', 'Active'],
                ['user', 'user123', 'Nguyễn Văn A', 'user@example.com', '0912345678', '1995-05-15', 'Active'],
                ['customer1', 'customer123', 'Trần Thị B', 'customer1@example.com', '0923456789', '1992-08-20', 'Active'],
                ['pending_user', 'pending123', 'Lê Văn C', 'pending@example.com', '0934567890', '1998-12-10', 'Pending'],
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
            
            // Insert KYC data
            echo "   📝 Adding KYC data...\n";
            $conn->exec("
                INSERT INTO KYC (user_id, identity_number, verification_status, verified_at) VALUES
                (2, '001234567890', 'Verified', NOW()),
                (3, '001234567891', 'Verified', NOW()),
                (4, '001234567892', 'Pending', NULL)
            ");
            
            // Insert PaymentMethod data
            echo "   📝 Adding payment methods...\n";
            $conn->exec("
                INSERT INTO PaymentMethod (user_id, type, provider, account_number, is_default) VALUES
                (2, 'CreditCard', 'Visa', '**** **** **** 1234', TRUE),
                (3, 'EWallet', 'MoMo', '0923456789', TRUE),
                (4, 'BankTransfer', 'Vietcombank', '1234567890', TRUE)
            ");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Customer database setup error: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup Vehicle Database
 */
function setupVehicleDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Vehicles Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS Vehicles (
                vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
                license_plate VARCHAR(20) UNIQUE NOT NULL,
                brand VARCHAR(50) NOT NULL,
                model VARCHAR(50) NOT NULL,
                type ENUM('Car', 'Motorbike', 'Bicycle', 'Electric_Scooter') NOT NULL,
                status ENUM('Available', 'Rented', 'Maintenance', 'Retired') DEFAULT 'Available',
                odo_km INT DEFAULT 0,
                fuel_level DECIMAL(5,2) DEFAULT 100.00,
                location VARCHAR(100),
                registration_date DATE,
                hourly_rate DECIMAL(10,2),
                daily_rate DECIMAL(10,2),
                INDEX idx_license (license_plate),
                INDEX idx_status (status),
                INDEX idx_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Maintenance Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS Maintenance (
                maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                maintenance_date DATE NOT NULL,
                description VARCHAR(255),
                next_maintenance DATE,
                status ENUM('Scheduled', 'InProgress', 'Completed') DEFAULT 'Scheduled',
                FOREIGN KEY (vehicle_id) REFERENCES Vehicles(vehicle_id) ON DELETE CASCADE,
                INDEX idx_vehicle_id (vehicle_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // VehicleUsageHistory Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS VehicleUsageHistory (
                usage_id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                rental_id INT NOT NULL COMMENT 'Reference only - NO FK to RentalDB',
                start_odo INT NOT NULL,
                end_odo INT,
                fuel_used DECIMAL(5,2),
                FOREIGN KEY (vehicle_id) REFERENCES Vehicles(vehicle_id) ON DELETE CASCADE,
                INDEX idx_vehicle_id (vehicle_id),
                INDEX idx_rental_id (rental_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed Data
        $count = $conn->query("SELECT COUNT(*) FROM Vehicles")->fetchColumn();
        if ($count == 0) {
            echo "   🚗 Adding vehicles...\n";

            // Insert Cars
            $conn->exec("
                INSERT INTO Vehicles (license_plate, brand, model, type, status, odo_km, fuel_level, location, registration_date, hourly_rate, daily_rate) VALUES
                ('59A-12345', 'Toyota', 'Vios 2023', 'Car', 'Available', 5000, 95.00, 'Quận 1, TP.HCM', '2023-01-15', 80000, 500000),
                ('51F-67890', 'Toyota', 'Camry 2023', 'Car', 'Available', 3000, 100.00, 'Quận 3, TP.HCM', '2023-03-20', 150000, 900000),
                ('50G-11111', 'Toyota', 'Fortuner 2022', 'Car', 'Rented', 12000, 75.00, 'Quận 7, TP.HCM', '2022-06-10', 180000, 1200000),
                ('59B-22222', 'Toyota', 'Innova 2023', 'Car', 'Available', 8000, 90.00, 'Quận 2, TP.HCM', '2023-02-28', 120000, 700000),
                ('51G-33333', 'Honda', 'City 2023', 'Car', 'Available', 4000, 100.00, 'Quận 1, TP.HCM', '2023-04-05', 70000, 450000),
                ('59C-44444', 'Honda', 'Civic 2022', 'Car', 'Maintenance', 15000, 80.00, 'Quận 5, TP.HCM', '2022-08-15', 100000, 600000),
                ('50H-55555', 'Honda', 'CR-V 2023', 'Car', 'Available', 6000, 95.00, 'Quận 10, TP.HCM', '2023-01-10', 140000, 850000),
                ('51H-66666', 'Mazda', 'CX-5 2023', 'Car', 'Available', 7000, 100.00, 'Quận 3, TP.HCM', '2023-05-20', 160000, 1000000),
                ('59D-77777', 'Mazda', 'Mazda3 2022', 'Car', 'Available', 10000, 85.00, 'Quận Bình Thạnh', '2022-11-30', 90000, 550000),
                ('51K-88888', 'VinFast', 'Fadil 2023', 'Car', 'Available', 2000, 100.00, 'Quận 1, TP.HCM', '2023-06-01', 60000, 400000),
                ('59E-99999', 'VinFast', 'Lux A2.0', 'Car', 'Available', 5000, 95.00, 'Quận 7, TP.HCM', '2023-03-15', 130000, 800000)
            ");

            // Insert Motorbikes
            $conn->exec("
                INSERT INTO Vehicles (license_plate, brand, model, type, status, odo_km, fuel_level, location, registration_date, hourly_rate, daily_rate) VALUES
                ('59-A1 12345', 'Honda', 'Air Blade 160', 'Motorbike', 'Available', 8000, 90.00, 'Quận 1, TP.HCM', '2022-01-15', 20000, 100000),
                ('51-F1 67890', 'Honda', 'SH 160i', 'Motorbike', 'Available', 12000, 85.00, 'Quận 3, TP.HCM', '2021-08-20', 30000, 150000),
                ('59-B1 11111', 'Honda', 'Wave RSX', 'Motorbike', 'Available', 15000, 80.00, 'Quận 5, TP.HCM', '2021-05-10', 15000, 80000),
                ('50-G1 22222', 'Honda', 'Winner X', 'Motorbike', 'Rented', 10000, 75.00, 'Quận 7, TP.HCM', '2022-09-25', 25000, 120000),
                ('51-H1 33333', 'Honda', 'Vision 2023', 'Motorbike', 'Available', 5000, 100.00, 'Quận 2, TP.HCM', '2023-02-14', 18000, 90000),
                ('59-C1 44444', 'Yamaha', 'Exciter 155', 'Motorbike', 'Available', 9000, 90.00, 'Quận 1, TP.HCM', '2022-03-10', 25000, 130000),
                ('51-K1 55555', 'Yamaha', 'Sirius', 'Motorbike', 'Available', 20000, 70.00, 'Quận 10, TP.HCM', '2020-11-20', 12000, 70000),
                ('59-D1 66666', 'Yamaha', 'Janus', 'Motorbike', 'Available', 7000, 95.00, 'Quận Bình Thạnh', '2022-07-05', 20000, 100000),
                ('50-H1 77777', 'Yamaha', 'Grande', 'Motorbike', 'Maintenance', 13000, 60.00, 'Quận 3, TP.HCM', '2021-12-30', 22000, 110000),
                ('51-M1 88888', 'Suzuki', 'Raider 150', 'Motorbike', 'Available', 11000, 85.00, 'Quận 7, TP.HCM', '2021-10-15', 18000, 95000),
                ('59-E1 99999', 'Suzuki', 'Address', 'Motorbike', 'Available', 6000, 100.00, 'Quận 1, TP.HCM', '2022-11-01', 20000, 100000),
                ('50-K1 00000', 'SYM', 'Attila Venus', 'Motorbike', 'Available', 8500, 90.00, 'Quận 5, TP.HCM', '2022-04-20', 17000, 85000),
                ('51-N1 10101', 'SYM', 'Galaxy', 'Motorbike', 'Available', 14000, 75.00, 'Quận 2, TP.HCM', '2021-06-18', 16000, 80000)
            ");

            // Insert Bicycles
            $conn->exec("
                INSERT INTO Vehicles (license_plate, brand, model, type, status, odo_km, fuel_level, location, registration_date, hourly_rate, daily_rate) VALUES
                ('BIC-001', 'Giant', 'ATX 890', 'Bicycle', 'Available', 500, 0, 'Quận 1, TP.HCM', '2023-01-10', 5000, 30000),
                ('BIC-002', 'Trek', 'FX 3', 'Bicycle', 'Available', 300, 0, 'Quận 3, TP.HCM', '2023-02-15', 6000, 35000),
                ('BIC-003', 'Merida', 'Crossway 100', 'Bicycle', 'Available', 800, 0, 'Quận 7, TP.HCM', '2022-11-20', 5000, 30000),
                ('BIC-004', 'Giant', 'Escape 3', 'Bicycle', 'Rented', 1200, 0, 'Quận 2, TP.HCM', '2022-08-05', 5500, 32000),
                ('BIC-005', 'Cannondale', 'Quick 4', 'Bicycle', 'Available', 200, 0, 'Quận 1, TP.HCM', '2023-04-01', 7000, 40000),
                ('BIC-006', 'Specialized', 'Sirrus X 2.0', 'Bicycle', 'Available', 600, 0, 'Quận 5, TP.HCM', '2022-12-10', 8000, 45000),
                ('BIC-007', 'Trek', 'Marlin 5', 'Bicycle', 'Available', 1500, 0, 'Quận 10, TP.HCM', '2022-05-22', 6500, 38000),
                ('BIC-008', 'Giant', 'Roam 2', 'Bicycle', 'Maintenance', 2000, 0, 'Quận Bình Thạnh', '2022-03-15', 5500, 33000)
            ");

            // Insert Electric Scooters
            $conn->exec("
                INSERT INTO Vehicles (license_plate, brand, model, type, status, odo_km, fuel_level, location, registration_date, hourly_rate, daily_rate) VALUES
                ('SCOOT-001', 'Xiaomi', 'Mi Scooter Pro 2', 'Electric_Scooter', 'Available', 300, 85.00, 'Quận 1, TP.HCM', '2023-03-10', 8000, 50000),
                ('SCOOT-002', 'Segway', 'Ninebot Max', 'Electric_Scooter', 'Available', 450, 90.00, 'Quận 3, TP.HCM', '2023-02-20', 10000, 60000),
                ('SCOOT-003', 'Xiaomi', 'Mi Scooter Essential', 'Electric_Scooter', 'Available', 200, 100.00, 'Quận 7, TP.HCM', '2023-04-05', 7000, 45000),
                ('SCOOT-004', 'Segway', 'Ninebot E22', 'Electric_Scooter', 'Rented', 600, 70.00, 'Quận 2, TP.HCM', '2023-01-15', 9000, 55000),
                ('SCOOT-005', 'Xiaomi', 'Mi Scooter 1S', 'Electric_Scooter', 'Available', 350, 95.00, 'Quận 5, TP.HCM', '2023-03-01', 8500, 52000),
                ('SCOOT-006', 'NIU', 'KQi3 Pro', 'Electric_Scooter', 'Available', 150, 100.00, 'Quận 1, TP.HCM', '2023-05-10', 11000, 65000),
                ('SCOOT-007', 'Segway', 'Ninebot F40', 'Electric_Scooter', 'Available', 500, 80.00, 'Quận 10, TP.HCM', '2023-02-12', 9500, 58000)
            ");

            echo "      ✅ Added " . $conn->query("SELECT COUNT(*) FROM Vehicles")->fetchColumn() . " vehicles\n";

            // Insert Maintenance Records
            echo "   📝 Adding maintenance records...\n";
            $conn->exec("
                INSERT INTO Maintenance (vehicle_id, maintenance_date, description, next_maintenance, status)
                SELECT v.vehicle_id, '2024-11-01', 'Thay dầu động cơ và lọc gió', '2025-02-01', 'Completed'
                FROM Vehicles v WHERE v.license_plate = '59C-44444'
                UNION ALL
                SELECT v.vehicle_id, '2024-11-15', 'Sửa hệ thống phanh và kiểm tra lốp', '2024-12-15', 'InProgress'
                FROM Vehicles v WHERE v.license_plate = '59C-44444'
                UNION ALL
                SELECT v.vehicle_id, '2024-10-20', 'Bảo dưỡng định kỳ 10,000 km', '2025-01-20', 'Completed'
                FROM Vehicles v WHERE v.license_plate = '50G-11111'
                UNION ALL
                SELECT v.vehicle_id, '2024-11-10', 'Thay nhớt và lọc nhớt', '2025-01-10', 'Completed'
                FROM Vehicles v WHERE v.license_plate = '59-D1 66666'
                UNION ALL
                SELECT v.vehicle_id, '2024-11-20', 'Kiểm tra và điều chỉnh xích', NULL, 'Scheduled'
                FROM Vehicles v WHERE v.license_plate = '50-H1 77777'
                UNION ALL
                SELECT v.vehicle_id, '2024-11-05', 'Thay lốp và căng xích', '2024-12-05', 'Completed'
                FROM Vehicles v WHERE v.license_plate = 'BIC-008'
            ");

            // Insert Vehicle Usage History
            echo "   📝 Adding usage history...\n";
            $conn->exec("
                INSERT INTO VehicleUsageHistory (vehicle_id, rental_id, start_odo, end_odo, fuel_used)
                SELECT v.vehicle_id, 1001, 4800, 5000, 5.50 FROM Vehicles v WHERE v.license_plate = '59A-12345'
                UNION ALL
                SELECT v.vehicle_id, 1002, 2800, 3000, 4.20 FROM Vehicles v WHERE v.license_plate = '51F-67890'
                UNION ALL
                SELECT v.vehicle_id, 1003, 11500, 12000, 12.00 FROM Vehicles v WHERE v.license_plate = '50G-11111'
                UNION ALL
                SELECT v.vehicle_id, 1004, 7500, 8000, 10.50 FROM Vehicles v WHERE v.license_plate = '59B-22222'
                UNION ALL
                SELECT v.vehicle_id, 2001, 7800, 8000, 1.20 FROM Vehicles v WHERE v.license_plate = '59-A1 12345'
                UNION ALL
                SELECT v.vehicle_id, 2002, 11800, 12000, 1.50 FROM Vehicles v WHERE v.license_plate = '51-F1 67890'
                UNION ALL
                SELECT v.vehicle_id, 2003, 14800, 15000, 1.00 FROM Vehicles v WHERE v.license_plate = '59-B1 11111'
                UNION ALL
                SELECT v.vehicle_id, 2004, 9800, 10000, 1.40 FROM Vehicles v WHERE v.license_plate = '50-G1 22222'
                UNION ALL
                SELECT v.vehicle_id, 3001, 450, 500, NULL FROM Vehicles v WHERE v.license_plate = 'BIC-001'
                UNION ALL
                SELECT v.vehicle_id, 3002, 250, 300, NULL FROM Vehicles v WHERE v.license_plate = 'BIC-002'
                UNION ALL
                SELECT v.vehicle_id, 4001, 280, 300, NULL FROM Vehicles v WHERE v.license_plate = 'SCOOT-001'
                UNION ALL
                SELECT v.vehicle_id, 4002, 550, 600, NULL FROM Vehicles v WHERE v.license_plate = 'SCOOT-004'
            ");
        }

        return true;
    } catch (PDOException $e) {
        error_log("❌ Vehicle database setup error: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup Rental Database
 */
function setupRentalDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Rentals Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS Rentals (
                rental_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL COMMENT 'Reference only - validated via Customer API',
                vehicle_id INT NOT NULL COMMENT 'Reference only - validated via Vehicle API',
                start_time DATETIME NOT NULL,
                end_time DATETIME NOT NULL,
                pickup_location VARCHAR(100) NOT NULL,
                dropoff_location VARCHAR(100) NOT NULL,
                total_cost DECIMAL(10,2) NOT NULL,
                status ENUM('Pending', 'Ongoing', 'Completed', 'Cancelled') DEFAULT 'Pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_vehicle_id (vehicle_id),
                INDEX idx_status (status),
                INDEX idx_start_time (start_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Rental Contract Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS RentalContract (
                contract_id INT AUTO_INCREMENT PRIMARY KEY,
                rental_id INT NOT NULL,
                contract_url TEXT,
                signed_at DATETIME,
                is_signed BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (rental_id) REFERENCES Rentals(rental_id) ON DELETE CASCADE,
                INDEX idx_rental_id (rental_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Promotion Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS Promotion (
                promo_id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(20) UNIQUE NOT NULL,
                description VARCHAR(255),
                discount_percent DECIMAL(5,2) NOT NULL,
                valid_from DATE NOT NULL,
                valid_to DATE NOT NULL,
                active BOOLEAN DEFAULT TRUE,
                INDEX idx_code (code),
                INDEX idx_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed Data
        $count = $conn->query("SELECT COUNT(*) FROM Rentals")->fetchColumn();
        if ($count == 0) {
            echo "   📄 Adding demo rentals...\n";

            $conn->exec("
                INSERT INTO Rentals (user_id, vehicle_id, start_time, end_time, pickup_location, dropoff_location, total_cost, status) VALUES
                (2, 1, '2024-11-14 10:00:00', '2024-11-17 10:00:00', 'Quận 1, TP.HCM', 'Quận 1, TP.HCM', 1500000, 'Ongoing'),
                (3, 15, '2024-11-15 14:00:00', '2024-11-18 14:00:00', 'Quận 3, TP.HCM', 'Quận 3, TP.HCM', 360000, 'Ongoing'),
                (2, 5, '2024-11-20 09:00:00', '2024-11-25 09:00:00', 'Quận 1, TP.HCM', 'Quận 1, TP.HCM', 2025000, 'Pending'),
                (4, 22, '2024-11-18 15:00:00', '2024-11-20 15:00:00', 'Quận 7, TP.HCM', 'Quận 7, TP.HCM', 180000, 'Pending'),
                (2, 12, '2024-11-01 10:00:00', '2024-11-05 10:00:00', 'Quận 1, TP.HCM', 'Quận 1, TP.HCM', 400000, 'Completed')
            ");

            echo "      ✅ Added " . $conn->query("SELECT COUNT(*) FROM Rentals")->fetchColumn() . " rentals\n";
        }

        return true;
    } catch (PDOException $e) {
        error_log("❌ Rental database setup error: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup Order Database
 */
function setupOrderDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Orders Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS Orders (
                order_id INT AUTO_INCREMENT PRIMARY KEY,
                rental_id INT NOT NULL COMMENT 'Reference only - validated via Rental API',
                user_id INT NOT NULL COMMENT 'Reference only - validated via Customer API',
                order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                delivery_status ENUM('Pending', 'Confirmed', 'InTransit', 'Delivered', 'Cancelled') DEFAULT 'Pending',
                INDEX idx_rental_id (rental_id),
                INDEX idx_user_id (user_id),
                INDEX idx_status (delivery_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // OrderTracking Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS OrderTracking (
                tracking_id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                status_update ENUM('Created', 'Confirmed', 'VehicleAssigned', 'Delivered', 'Completed', 'Cancelled') NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                note VARCHAR(255),
                FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,
                INDEX idx_order_id (order_id),
                INDEX idx_updated_at (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // CancellationRequest Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS CancellationRequest (
                cancel_id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                reason VARCHAR(255) NOT NULL,
                requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                approved BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,
                INDEX idx_order_id (order_id),
                INDEX idx_approved (approved)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "   ✅ Order tables created\n";
        return true;
    } catch (PDOException $e) {
        error_log("❌ Order database setup error: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup Payment Database
 */
function setupPaymentDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Transactions Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS Transactions (
                transaction_id INT AUTO_INCREMENT PRIMARY KEY,
                rental_id INT NOT NULL COMMENT 'Reference only - validated via Rental API',
                user_id INT NOT NULL COMMENT 'Reference only - validated via Customer API',
                amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50) NOT NULL,
                payment_gateway VARCHAR(50) NOT NULL,
                transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                status ENUM('Pending', 'Success', 'Failed', 'Refunded') DEFAULT 'Pending',
                INDEX idx_rental_id (rental_id),
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_date (transaction_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Invoice Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS Invoice (
                invoice_id INT AUTO_INCREMENT PRIMARY KEY,
                transaction_id INT NOT NULL,
                invoice_number VARCHAR(50) UNIQUE NOT NULL,
                issued_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                total_amount DECIMAL(10,2) NOT NULL,
                pdf_url TEXT,
                FOREIGN KEY (transaction_id) REFERENCES Transactions(transaction_id) ON DELETE CASCADE,
                INDEX idx_transaction_id (transaction_id),
                INDEX idx_invoice_number (invoice_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Refunds Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS Refunds (
                refund_id INT AUTO_INCREMENT PRIMARY KEY,
                transaction_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                reason VARCHAR(255),
                processed_at DATETIME,
                status ENUM('Pending', 'Completed', 'Failed') DEFAULT 'Pending',
                FOREIGN KEY (transaction_id) REFERENCES Transactions(transaction_id) ON DELETE CASCADE,
                INDEX idx_transaction_id (transaction_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "   ✅ Payment tables created\n";
        return true;
    } catch (PDOException $e) {
        error_log("❌ Payment database setup error: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup Notification Database
 */
function setupNotificationDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Notifications Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS Notifications (
                notification_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL COMMENT 'Reference only - validated via Customer API',
                type ENUM('Email', 'SMS', 'Push') NOT NULL,
                title VARCHAR(100) NOT NULL,
                message VARCHAR(255) NOT NULL,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                status ENUM('Sent', 'Failed') DEFAULT 'Sent',
                INDEX idx_user_id (user_id),
                INDEX idx_type (type),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "   ✅ Notification tables created\n";
        return true;
    } catch (PDOException $e) {
        error_log("❌ Notification database setup error: " . $e->getMessage());
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
        if ($service === 'vehicle') {
            setupVehicleDatabase($config);
        }
        if ($service === 'rental') {
            setupRentalDatabase($config);
        }
        if ($service === 'order') {
            setupOrderDatabase($config);
        }
        if ($service === 'payment') {
            setupPaymentDatabase($config);
        }
        if ($service === 'notification') {
            setupNotificationDatabase($config);
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
    echo "   • Vehicle Service:  http://localhost:8002\n";
    echo "   • Rental Service:   http://localhost:8003\n";
    echo "   • Order Service:    http://localhost:8004\n";
    echo "   • Payment Service:  http://localhost:8005\n";
    echo "   • Notification:     http://localhost:8006\n";
    echo "   • Main Gateway:     http://localhost\n\n";
    echo "💡 Demo accounts:\n";
    echo "   • admin / admin123\n";
    echo "   • user / user123\n";
    echo "   • customer1 / customer123\n\n";
    
} elseif (basename($_SERVER['SCRIPT_FILENAME']) === 'index.php') {
    // Silent mode for index.php
    AutoJWTFixComplete::autoFix();
    loadEnvFile();
    ensureDatabasesSetup(true);
    ensureServicesRunning();
}