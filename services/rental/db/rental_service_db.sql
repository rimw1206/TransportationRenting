-- Database Rental
-- ============================================
-- DATABASE 3: RENTAL SERVICE
-- ============================================

CREATE DATABASE IF NOT EXISTS rental_service_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rental_service_db;

/* Chỉ lưu reference user_id và vehicle_id, KHÔNG có FK */
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS RentalContract (
    contract_id INT AUTO_INCREMENT PRIMARY KEY,
    rental_id INT NOT NULL,
    contract_url TEXT,
    signed_at DATETIME,
    is_signed BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (rental_id) REFERENCES Rentals(rental_id) ON DELETE CASCADE,
    INDEX idx_rental_id (rental_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
