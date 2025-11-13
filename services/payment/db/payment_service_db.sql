-- Database Payment
-- ============================================
-- DATABASE 5: PAYMENT SERVICE
-- ============================================

CREATE DATABASE IF NOT EXISTS payment_service_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE payment_service_db;

/* Chỉ lưu reference, KHÔNG có FK đến RentalDB hoặc CustomerDB */
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ⚠️ KHÔNG CÓ FK đến CustomerDB
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
