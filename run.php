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
/**
 * Setup Vehicle Database - INVENTORY MODEL
 */
function setupVehicleDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        $conn = new PDO($dsn, $config['username'], $config['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        echo "   📦 Creating Vehicle Inventory tables...\n";

        // VehicleCatalog Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS VehicleCatalog (
                catalog_id INT AUTO_INCREMENT PRIMARY KEY,
                brand VARCHAR(50) NOT NULL,
                model VARCHAR(50) NOT NULL,
                type ENUM('Car', 'Motorbike', 'Bicycle', 'Electric_Scooter') NOT NULL,
                year INT NOT NULL,
                color VARCHAR(30),
                description TEXT,
                image_url VARCHAR(255),
                seats INT COMMENT 'Số chỗ ngồi',
                engine_capacity INT COMMENT 'Dung tích động cơ (cc)',
                transmission ENUM('Manual', 'Automatic', 'CVT') COMMENT 'Hộp số',
                fuel_type ENUM('Gasoline', 'Diesel', 'Electric', 'Hybrid', 'None') DEFAULT 'Gasoline',
                hourly_rate DECIMAL(10,2),
                daily_rate DECIMAL(10,2),
                weekly_rate DECIMAL(10,2),
                monthly_rate DECIMAL(10,2),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_vehicle_model (brand, model, year, color),
                INDEX idx_type (type),
                INDEX idx_brand (brand),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // VehicleUnits Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS VehicleUnits (
                unit_id INT AUTO_INCREMENT PRIMARY KEY,
                catalog_id INT NOT NULL,
                license_plate VARCHAR(20) UNIQUE NOT NULL,
                status ENUM('Available', 'Rented', 'Maintenance', 'Retired') DEFAULT 'Available',
                condition_rating DECIMAL(3,2) DEFAULT 5.00,
                odo_km INT DEFAULT 0,
                fuel_level DECIMAL(5,2) DEFAULT 100.00,
                battery_level DECIMAL(5,2),
                current_location VARCHAR(100),
                parking_spot VARCHAR(50),
                registration_date DATE NOT NULL,
                last_inspection_date DATE,
                next_inspection_date DATE,
                purchase_price DECIMAL(12,2),
                purchase_date DATE,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (catalog_id) REFERENCES VehicleCatalog(catalog_id) ON DELETE CASCADE,
                INDEX idx_catalog (catalog_id),
                INDEX idx_status (status),
                INDEX idx_license (license_plate),
                INDEX idx_location (current_location)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // LocationInventory Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS LocationInventory (
                inventory_id INT AUTO_INCREMENT PRIMARY KEY,
                catalog_id INT NOT NULL,
                location_name VARCHAR(100) NOT NULL,
                total_units INT DEFAULT 0,
                available_units INT DEFAULT 0,
                rented_units INT DEFAULT 0,
                maintenance_units INT DEFAULT 0,
                min_stock_alert INT DEFAULT 2,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (catalog_id) REFERENCES VehicleCatalog(catalog_id) ON DELETE CASCADE,
                UNIQUE KEY unique_location_catalog (catalog_id, location_name),
                INDEX idx_location (location_name),
                INDEX idx_catalog (catalog_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Maintenance Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS Maintenance (
                maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
                unit_id INT NOT NULL,
                maintenance_type ENUM('Routine', 'Repair', 'Inspection', 'Cleaning', 'Emergency') NOT NULL,
                scheduled_date DATE NOT NULL,
                completed_date DATE,
                description TEXT NOT NULL,
                technician_name VARCHAR(100),
                cost DECIMAL(10,2),
                parts_replaced TEXT,
                status ENUM('Scheduled', 'InProgress', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
                next_maintenance_date DATE,
                next_maintenance_km INT,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (unit_id) REFERENCES VehicleUnits(unit_id) ON DELETE CASCADE,
                INDEX idx_unit (unit_id),
                INDEX idx_status (status),
                INDEX idx_scheduled_date (scheduled_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // VehicleUsageHistory Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS VehicleUsageHistory (
                usage_id INT AUTO_INCREMENT PRIMARY KEY,
                unit_id INT NOT NULL,
                rental_id INT NOT NULL COMMENT 'Reference to Rental Service',
                start_datetime DATETIME NOT NULL,
                end_datetime DATETIME,
                start_odo INT NOT NULL,
                end_odo INT,
                distance_km INT GENERATED ALWAYS AS (end_odo - start_odo) STORED,
                fuel_start DECIMAL(5,2),
                fuel_end DECIMAL(5,2),
                fuel_used DECIMAL(5,2),
                return_condition ENUM('Excellent', 'Good', 'Fair', 'Poor', 'Damaged'),
                return_notes TEXT,
                damage_reported BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (unit_id) REFERENCES VehicleUnits(unit_id) ON DELETE CASCADE,
                INDEX idx_unit (unit_id),
                INDEX idx_rental (rental_id),
                INDEX idx_dates (start_datetime, end_datetime)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // DamageReports Table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS DamageReports (
                report_id INT AUTO_INCREMENT PRIMARY KEY,
                unit_id INT NOT NULL,
                usage_id INT,
                report_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                reported_by VARCHAR(100),
                damage_type ENUM('Scratches', 'Dent', 'Mechanical', 'Interior', 'Tire', 'Other') NOT NULL,
                severity ENUM('Minor', 'Moderate', 'Major', 'Critical') NOT NULL,
                description TEXT NOT NULL,
                repair_cost_estimate DECIMAL(10,2),
                actual_repair_cost DECIMAL(10,2),
                status ENUM('Reported', 'UnderReview', 'Approved', 'Repaired', 'Closed') DEFAULT 'Reported',
                resolution_notes TEXT,
                images_url TEXT,
                FOREIGN KEY (unit_id) REFERENCES VehicleUnits(unit_id) ON DELETE CASCADE,
                FOREIGN KEY (usage_id) REFERENCES VehicleUsageHistory(usage_id) ON DELETE SET NULL,
                INDEX idx_unit (unit_id),
                INDEX idx_status (status),
                INDEX idx_date (report_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "   ✅ Tables created\n";
        echo "   📝 Creating triggers...\n";

        // Create Triggers
        $conn->exec("DROP TRIGGER IF EXISTS after_vehicle_unit_insert");
        $conn->exec("DROP TRIGGER IF EXISTS after_vehicle_unit_update");
        $conn->exec("DROP TRIGGER IF EXISTS after_vehicle_unit_delete");

        $conn->exec("
            CREATE TRIGGER after_vehicle_unit_insert
            AFTER INSERT ON VehicleUnits
            FOR EACH ROW
            BEGIN
                INSERT INTO LocationInventory (catalog_id, location_name, total_units, available_units)
                VALUES (NEW.catalog_id, NEW.current_location, 1, IF(NEW.status = 'Available', 1, 0))
                ON DUPLICATE KEY UPDATE
                    total_units = total_units + 1,
                    available_units = available_units + IF(NEW.status = 'Available', 1, 0);
            END
        ");

        $conn->exec("
            CREATE TRIGGER after_vehicle_unit_update
            AFTER UPDATE ON VehicleUnits
            FOR EACH ROW
            BEGIN
                IF OLD.current_location != NEW.current_location THEN
                    UPDATE LocationInventory 
                    SET total_units = total_units - 1,
                        available_units = available_units - IF(OLD.status = 'Available', 1, 0),
                        rented_units = rented_units - IF(OLD.status = 'Rented', 1, 0),
                        maintenance_units = maintenance_units - IF(OLD.status = 'Maintenance', 1, 0)
                    WHERE catalog_id = OLD.catalog_id AND location_name = OLD.current_location;
                    
                    INSERT INTO LocationInventory (catalog_id, location_name, total_units, available_units, rented_units, maintenance_units)
                    VALUES (
                        NEW.catalog_id, 
                        NEW.current_location, 
                        1,
                        IF(NEW.status = 'Available', 1, 0),
                        IF(NEW.status = 'Rented', 1, 0),
                        IF(NEW.status = 'Maintenance', 1, 0)
                    )
                    ON DUPLICATE KEY UPDATE
                        total_units = total_units + 1,
                        available_units = available_units + IF(NEW.status = 'Available', 1, 0),
                        rented_units = rented_units + IF(NEW.status = 'Rented', 1, 0),
                        maintenance_units = maintenance_units + IF(NEW.status = 'Maintenance', 1, 0);
                END IF;
                
                IF OLD.status != NEW.status AND OLD.current_location = NEW.current_location THEN
                    UPDATE LocationInventory 
                    SET available_units = available_units 
                        - IF(OLD.status = 'Available', 1, 0) 
                        + IF(NEW.status = 'Available', 1, 0),
                        rented_units = rented_units 
                        - IF(OLD.status = 'Rented', 1, 0) 
                        + IF(NEW.status = 'Rented', 1, 0),
                        maintenance_units = maintenance_units 
                        - IF(OLD.status = 'Maintenance', 1, 0) 
                        + IF(NEW.status = 'Maintenance', 1, 0)
                    WHERE catalog_id = NEW.catalog_id AND location_name = NEW.current_location;
                END IF;
            END
        ");

        $conn->exec("
            CREATE TRIGGER after_vehicle_unit_delete
            AFTER DELETE ON VehicleUnits
            FOR EACH ROW
            BEGIN
                UPDATE LocationInventory 
                SET total_units = total_units - 1,
                    available_units = available_units - IF(OLD.status = 'Available', 1, 0),
                    rented_units = rented_units - IF(OLD.status = 'Rented', 1, 0),
                    maintenance_units = maintenance_units - IF(OLD.status = 'Maintenance', 1, 0)
                WHERE catalog_id = OLD.catalog_id AND location_name = OLD.current_location;
            END
        ");

        echo "   ✅ Triggers created\n";
        echo "   📝 Creating views...\n";

        // Create Views
        $conn->exec("DROP VIEW IF EXISTS v_inventory_summary");
        $conn->exec("DROP VIEW IF EXISTS v_available_vehicles");
        $conn->exec("DROP VIEW IF EXISTS v_low_stock_alert");

        $conn->exec("
            CREATE VIEW v_inventory_summary AS
            SELECT 
                vc.catalog_id,
                vc.brand,
                vc.model,
                vc.type,
                vc.year,
                COUNT(vu.unit_id) as total_units,
                SUM(CASE WHEN vu.status = 'Available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN vu.status = 'Rented' THEN 1 ELSE 0 END) as rented,
                SUM(CASE WHEN vu.status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance,
                SUM(CASE WHEN vu.status = 'Retired' THEN 1 ELSE 0 END) as retired,
                vc.daily_rate,
                vc.is_active
            FROM VehicleCatalog vc
            LEFT JOIN VehicleUnits vu ON vc.catalog_id = vu.catalog_id
            GROUP BY vc.catalog_id
        ");

        $conn->exec("
            CREATE VIEW v_available_vehicles AS
            SELECT 
                vu.unit_id,
                vu.license_plate,
                vc.brand,
                vc.model,
                vc.type,
                vc.year,
                vc.color,
                vu.current_location,
                vu.odo_km,
                vu.condition_rating,
                vc.hourly_rate,
                vc.daily_rate,
                vc.seats,
                vc.transmission,
                vc.fuel_type
            FROM VehicleUnits vu
            JOIN VehicleCatalog vc ON vu.catalog_id = vc.catalog_id
            WHERE vu.status = 'Available' AND vc.is_active = TRUE
        ");

        $conn->exec("
            CREATE VIEW v_low_stock_alert AS
            SELECT 
                li.location_name,
                vc.brand,
                vc.model,
                vc.type,
                li.available_units,
                li.min_stock_alert,
                (li.min_stock_alert - li.available_units) as units_needed
            FROM LocationInventory li
            JOIN VehicleCatalog vc ON li.catalog_id = vc.catalog_id
            WHERE li.available_units < li.min_stock_alert
        ");

        echo "   ✅ Views created\n";

        // Seed Data
        $count = $conn->query("SELECT COUNT(*) FROM VehicleCatalog")->fetchColumn();
        if ($count == 0) {
            echo "   📝 Adding sample catalog data...\n";

            // Insert Catalogs
            $conn->exec("
                INSERT INTO VehicleCatalog (brand, model, type, year, color, seats, engine_capacity, transmission, fuel_type, hourly_rate, daily_rate, weekly_rate, monthly_rate) VALUES
                ('Toyota', 'Vios', 'Car', 2023, 'Trắng', 5, 1500, 'Automatic', 'Gasoline', 80000, 500000, 3200000, 12000000),
                ('Toyota', 'Camry', 'Car', 2023, 'Đen', 5, 2500, 'Automatic', 'Gasoline', 150000, 900000, 5800000, 22000000),
                ('Toyota', 'Fortuner', 'Car', 2022, 'Bạc', 7, 2700, 'Automatic', 'Diesel', 180000, 1200000, 7800000, 30000000),
                ('Honda', 'City', 'Car', 2023, 'Đỏ', 5, 1500, 'CVT', 'Gasoline', 70000, 450000, 2900000, 11000000),
                ('Honda', 'SH 160i', 'Motorbike', 2023, 'Đen', 2, 160, NULL, 'Gasoline', 30000, 150000, 950000, 3500000),
                ('Honda', 'Air Blade', 'Motorbike', 2023, 'Trắng', 2, 160, NULL, 'Gasoline', 20000, 100000, 650000, 2400000),
                ('Yamaha', 'Exciter 155', 'Motorbike', 2023, 'Xanh', 2, 155, NULL, 'Gasoline', 25000, 130000, 850000, 3200000),
                ('Giant', 'ATX 890', 'Bicycle', 2023, 'Xanh', NULL, NULL, NULL, 'None', 5000, 30000, 180000, 650000),
                ('Trek', 'FX 3', 'Bicycle', 2023, 'Cam', NULL, NULL, NULL, 'None', 6000, 35000, 210000, 750000),
                ('Xiaomi', 'Pro 2', 'Electric_Scooter', 2023, 'Đen', NULL, NULL, NULL, 'Electric', 8000, 50000, 320000, 1200000)
            ");

            echo "   📝 Adding vehicle units...\n";

            // Insert Vehicle Units
            $conn->exec("
                INSERT INTO VehicleUnits (catalog_id, license_plate, status, condition_rating, odo_km, fuel_level, current_location, parking_spot, registration_date, purchase_price, purchase_date) VALUES
                (1, '59A-12345', 'Available', 4.8, 5000, 95.00, 'Quận 1, TP.HCM', 'A-01', '2023-01-15', 450000000, '2023-01-10'),
                (1, '59A-12346', 'Available', 4.9, 3200, 98.00, 'Quận 1, TP.HCM', 'A-02', '2023-02-20', 450000000, '2023-02-15'),
                (2, '51F-67890', 'Available', 5.0, 2000, 100.00, 'Quận 3, TP.HCM', 'B-01', '2023-03-20', 950000000, '2023-03-15'),
                (3, '50G-11111', 'Rented', 4.7, 12000, 75.00, 'Quận 7, TP.HCM', 'C-01', '2022-06-10', 1200000000, '2022-06-05'),
                (4, '51G-33333', 'Available', 4.8, 4000, 100.00, 'Quận 1, TP.HCM', 'A-05', '2023-04-05', 520000000, '2023-04-01'),
                (5, '59-A1 12345', 'Available', 4.8, 8000, 90.00, 'Quận 1, TP.HCM', 'M-01', '2022-01-15', 95000000, '2022-01-10'),
                (5, '59-A1 12346', 'Available', 4.9, 5500, 95.00, 'Quận 1, TP.HCM', 'M-02', '2022-03-20', 95000000, '2022-03-15'),
                (6, '51-F1 67890', 'Available', 4.6, 12000, 85.00, 'Quận 1, TP.HCM', 'M-05', '2021-08-20', 42000000, '2021-08-15'),
                (7, '59-C1 44444', 'Available', 4.7, 9000, 90.00, 'Quận 1, TP.HCM', 'M-10', '2022-03-10', 48000000, '2022-03-05'),
                (8, 'BIC-001', 'Available', 4.9, 500, 0, 'Quận 1, TP.HCM', 'B-RACK-01', '2023-01-10', 12000000, '2023-01-05'),
                (9, 'BIC-002', 'Available', 5.0, 300, 0, 'Quận 3, TP.HCM', 'B-RACK-05', '2023-02-15', 13000000, '2023-02-10'),
                (10, 'SCOOT-001', 'Available', 4.9, 300, 85.00, 'Quận 1, TP.HCM', 'E-01', '2023-03-10', 15000000, '2023-03-05')
            ");

            echo "      ✅ Added " . $conn->query("SELECT COUNT(*) FROM VehicleUnits")->fetchColumn() . " vehicle units\n";
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