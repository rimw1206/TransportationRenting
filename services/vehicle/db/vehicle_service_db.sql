-- Databse Vehicle
-- ============================================
-- DATABASE 2: VEHICLE SERVICE
-- ============================================

CREATE DATABASE IF NOT EXISTS vehicle_service_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vehicle_service_db;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Chỉ lưu reference rental_id, không tạo FK */
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
