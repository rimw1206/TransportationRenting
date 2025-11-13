-- Database Order
-- ============================================
-- DATABASE 4: ORDER SERVICE
-- ============================================

CREATE DATABASE IF NOT EXISTS order_service_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE order_service_db;

/* Chỉ lưu reference, KHÔNG có FK đến RentalDB hoặc CustomerDB */
CREATE TABLE IF NOT EXISTS Orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    rental_id INT NOT NULL COMMENT 'Reference only - validated via Rental API',
    user_id INT NOT NULL COMMENT 'Reference only - validated via Customer API',
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    delivery_status ENUM('Pending', 'Confirmed', 'InTransit', 'Delivered', 'Cancelled') DEFAULT 'Pending',
    
    INDEX idx_rental_id (rental_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (delivery_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS OrderTracking (
    tracking_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status_update ENUM('Created', 'Confirmed', 'VehicleAssigned', 'Delivered', 'Completed', 'Cancelled') NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    note VARCHAR(255),
    
    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS CancellationRequest (
    cancel_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_approved (approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
