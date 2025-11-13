-- Database Customer Service
-- ============================================
-- DATABASE 1: CUSTOMER SERVICE
-- ============================================

CREATE DATABASE IF NOT EXISTS customer_service_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE customer_service_db;

CREATE TABLE IF NOT EXISTS Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    birthdate DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Active', 'Inactive', 'Pending') DEFAULT 'Pending',
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ============================================
-- INSERT SAMPLE DATA
-- ============================================

-- Insert demo users
-- Password for 'admin123' and 'user123' are hashed using password_hash()
INSERT INTO Users (username, password, name, email, phone, birthdate, status, created_at) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@transportation.com', '0901234567', '1990-01-01', 'Active', NOW()),
('user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nguyễn Văn A', 'user@example.com', '0912345678', '1995-05-15', 'Active', NOW()),
('customer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Trần Thị B', 'customer1@example.com', '0923456789', '1992-08-20', 'Active', NOW()),
('pending_user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lê Văn C', 'pending@example.com', '0934567890', '1998-12-10', 'Pending', NOW());

-- Note: Password hash above is for 'password' - Change in production!
-- Generate proper password hash using: password_hash('your_password', PASSWORD_DEFAULT)

-- Insert KYC records
INSERT INTO KYC (user_id, identity_number, verification_status, verified_at) VALUES
(2, '001234567890', 'Verified', NOW()),
(3, '001234567891', 'Verified', NOW()),
(4, '001234567892', 'Pending', NULL);

-- Insert payment methods
INSERT INTO PaymentMethod (user_id, type, provider, account_number, is_default) VALUES
(2, 'CreditCard', 'Visa', '**** **** **** 1234', TRUE),
(3, 'EWallet', 'MoMo', '0923456789', TRUE),
(4, 'BankTransfer', 'Vietcombank', '1234567890', TRUE);

-- ============================================
-- VERIFY INSTALLATION
-- ============================================

-- Check if tables were created
SELECT 
    TABLE_NAME,
    TABLE_ROWS
FROM 
    information_schema.TABLES
WHERE 
    TABLE_SCHEMA = 'customer_service_db'
ORDER BY 
    TABLE_NAME;

-- List all users
SELECT 
    user_id,
    username,
    name,
    email,
    status,
    created_at
FROM 
    Users;