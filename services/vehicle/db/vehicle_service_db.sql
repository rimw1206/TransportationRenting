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
-- Insert Sample Vehicle Data
USE vehicle_service_db;

-- Clear existing data (optional)
-- TRUNCATE TABLE VehicleUsageHistory;
-- TRUNCATE TABLE Maintenance;
-- TRUNCATE TABLE Vehicles;

-- Insert Cars (Ô tô)
INSERT INTO Vehicles (license_plate, brand, model, type, status, odo_km, fuel_level, location, registration_date, hourly_rate, daily_rate) VALUES
-- Toyota
('59A-12345', 'Toyota', 'Vios 2023', 'Car', 'Available', 5000, 95.00, 'Quận 1, TP.HCM', '2023-01-15', 80000, 500000),
('51F-67890', 'Toyota', 'Camry 2023', 'Car', 'Available', 3000, 100.00, 'Quận 3, TP.HCM', '2023-03-20', 150000, 900000),
('50G-11111', 'Toyota', 'Fortuner 2022', 'Car', 'Rented', 12000, 75.00, 'Quận 7, TP.HCM', '2022-06-10', 180000, 1200000),
('59B-22222', 'Toyota', 'Innova 2023', 'Car', 'Available', 8000, 90.00, 'Quận 2, TP.HCM', '2023-02-28', 120000, 700000),

-- Honda
('51G-33333', 'Honda', 'City 2023', 'Car', 'Available', 4000, 100.00, 'Quận 1, TP.HCM', '2023-04-05', 70000, 450000),
('59C-44444', 'Honda', 'Civic 2022', 'Car', 'Maintenance', 15000, 80.00, 'Quận 5, TP.HCM', '2022-08-15', 100000, 600000),
('50H-55555', 'Honda', 'CR-V 2023', 'Car', 'Available', 6000, 95.00, 'Quận 10, TP.HCM', '2023-01-10', 140000, 850000),

-- Mazda
('51H-66666', 'Mazda', 'CX-5 2023', 'Car', 'Available', 7000, 100.00, 'Quận 3, TP.HCM', '2023-05-20', 160000, 1000000),
('59D-77777', 'Mazda', 'Mazda3 2022', 'Car', 'Available', 10000, 85.00, 'Quận Bình Thạnh', '2022-11-30', 90000, 550000),

-- VinFast
('51K-88888', 'VinFast', 'Fadil 2023', 'Car', 'Available', 2000, 100.00, 'Quận 1, TP.HCM', '2023-06-01', 60000, 400000),
('59E-99999', 'VinFast', 'Lux A2.0', 'Car', 'Available', 5000, 95.00, 'Quận 7, TP.HCM', '2023-03-15', 130000, 800000);

-- Insert Motorbikes (Xe máy)
INSERT INTO Vehicles (license_plate, brand, model, type, status, odo_km, fuel_level, location, registration_date, hourly_rate, daily_rate) VALUES
-- Honda Motorbikes
('59-A1 12345', 'Honda', 'Air Blade 160', 'Motorbike', 'Available', 8000, 90.00, 'Quận 1, TP.HCM', '2022-01-15', 20000, 100000),
('51-F1 67890', 'Honda', 'SH 160i', 'Motorbike', 'Available', 12000, 85.00, 'Quận 3, TP.HCM', '2021-08-20', 30000, 150000),
('59-B1 11111', 'Honda', 'Wave RSX', 'Motorbike', 'Available', 15000, 80.00, 'Quận 5, TP.HCM', '2021-05-10', 15000, 80000),
('50-G1 22222', 'Honda', 'Winner X', 'Motorbike', 'Rented', 10000, 75.00, 'Quận 7, TP.HCM', '2022-09-25', 25000, 120000),
('51-H1 33333', 'Honda', 'Vision 2023', 'Motorbike', 'Available', 5000, 100.00, 'Quận 2, TP.HCM', '2023-02-14', 18000, 90000),

-- Yamaha
('59-C1 44444', 'Yamaha', 'Exciter 155', 'Motorbike', 'Available', 9000, 90.00, 'Quận 1, TP.HCM', '2022-03-10', 25000, 130000),
('51-K1 55555', 'Yamaha', 'Sirius', 'Motorbike', 'Available', 20000, 70.00, 'Quận 10, TP.HCM', '2020-11-20', 12000, 70000),
('59-D1 66666', 'Yamaha', 'Janus', 'Motorbike', 'Available', 7000, 95.00, 'Quận Bình Thạnh', '2022-07-05', 20000, 100000),
('50-H1 77777', 'Yamaha', 'Grande', 'Motorbike', 'Maintenance', 13000, 60.00, 'Quận 3, TP.HCM', '2021-12-30', 22000, 110000),

-- Suzuki
('51-M1 88888', 'Suzuki', 'Raider 150', 'Motorbike', 'Available', 11000, 85.00, 'Quận 7, TP.HCM', '2021-10-15', 18000, 95000),
('59-E1 99999', 'Suzuki', 'Address', 'Motorbike', 'Available', 6000, 100.00, 'Quận 1, TP.HCM', '2022-11-01', 20000, 100000),

-- SYM
('50-K1 00000', 'SYM', 'Attila Venus', 'Motorbike', 'Available', 8500, 90.00, 'Quận 5, TP.HCM', '2022-04-20', 17000, 85000),
('51-N1 10101', 'SYM', 'Galaxy', 'Motorbike', 'Available', 14000, 75.00, 'Quận 2, TP.HCM', '2021-06-18', 16000, 80000);

-- Insert Bicycles (Xe đạp)
INSERT INTO Vehicles (license_plate, brand, model, type, status, odo_km, fuel_level, location, registration_date, hourly_rate, daily_rate) VALUES
('BIC-001', 'Giant', 'ATX 890', 'Bicycle', 'Available', 500, 0, 'Quận 1, TP.HCM', '2023-01-10', 5000, 30000),
('BIC-002', 'Trek', 'FX 3', 'Bicycle', 'Available', 300, 0, 'Quận 3, TP.HCM', '2023-02-15', 6000, 35000),
('BIC-003', 'Merida', 'Crossway 100', 'Bicycle', 'Available', 800, 0, 'Quận 7, TP.HCM', '2022-11-20', 5000, 30000),
('BIC-004', 'Giant', 'Escape 3', 'Bicycle', 'Rented', 1200, 0, 'Quận 2, TP.HCM', '2022-08-05', 5500, 32000),
('BIC-005', 'Cannondale', 'Quick 4', 'Bicycle', 'Available', 200, 0, 'Quận 1, TP.HCM', '2023-04-01', 7000, 40000),
('BIC-006', 'Specialized', 'Sirrus X 2.0', 'Bicycle', 'Available', 600, 0, 'Quận 5, TP.HCM', '2022-12-10', 8000, 45000),
('BIC-007', 'Trek', 'Marlin 5', 'Bicycle', 'Available', 1500, 0, 'Quận 10, TP.HCM', '2022-05-22', 6500, 38000),
('BIC-008', 'Giant', 'Roam 2', 'Bicycle', 'Maintenance', 2000, 0, 'Quận Bình Thạnh', '2022-03-15', 5500, 33000);

-- Insert Electric Scooters (Xe điện)
INSERT INTO Vehicles (license_plate, brand, model, type, status, odo_km, fuel_level, location, registration_date, hourly_rate, daily_rate) VALUES
('SCOOT-001', 'Xiaomi', 'Mi Scooter Pro 2', 'Electric_Scooter', 'Available', 300, 85.00, 'Quận 1, TP.HCM', '2023-03-10', 8000, 50000),
('SCOOT-002', 'Segway', 'Ninebot Max', 'Electric_Scooter', 'Available', 450, 90.00, 'Quận 3, TP.HCM', '2023-02-20', 10000, 60000),
('SCOOT-003', 'Xiaomi', 'Mi Scooter Essential', 'Electric_Scooter', 'Available', 200, 100.00, 'Quận 7, TP.HCM', '2023-04-05', 7000, 45000),
('SCOOT-004', 'Segway', 'Ninebot E22', 'Electric_Scooter', 'Rented', 600, 70.00, 'Quận 2, TP.HCM', '2023-01-15', 9000, 55000),
('SCOOT-005', 'Xiaomi', 'Mi Scooter 1S', 'Electric_Scooter', 'Available', 350, 95.00, 'Quận 5, TP.HCM', '2023-03-01', 8500, 52000),
('SCOOT-006', 'NIU', 'KQi3 Pro', 'Electric_Scooter', 'Available', 150, 100.00, 'Quận 1, TP.HCM', '2023-05-10', 11000, 65000),
('SCOOT-007', 'Segway', 'Ninebot F40', 'Electric_Scooter', 'Available', 500, 80.00, 'Quận 10, TP.HCM', '2023-02-12', 9500, 58000);

-- Insert Maintenance Records using license plates to find correct IDs
INSERT INTO Maintenance (vehicle_id, maintenance_date, description, next_maintenance, status)
SELECT v.vehicle_id, '2024-11-01', 'Thay dầu động cơ và lọc gió', '2025-02-01', 'Completed'
FROM Vehicles v WHERE v.license_plate = '59C-44444' -- Honda Civic in Maintenance
UNION ALL
SELECT v.vehicle_id, '2024-11-15', 'Sửa hệ thống phanh và kiểm tra lốp', '2024-12-15', 'InProgress'
FROM Vehicles v WHERE v.license_plate = '59C-44444' -- Honda Civic
UNION ALL
SELECT v.vehicle_id, '2024-10-20', 'Bảo dưỡng định kỳ 10,000 km', '2025-01-20', 'Completed'
FROM Vehicles v WHERE v.license_plate = '50G-11111' -- Toyota Fortuner
UNION ALL
-- Motorbikes maintenance
SELECT v.vehicle_id, '2024-11-10', 'Thay nhớt và lọc nhớt', '2025-01-10', 'Completed'
FROM Vehicles v WHERE v.license_plate = '59-D1 66666' -- Yamaha Janus
UNION ALL
SELECT v.vehicle_id, '2024-11-20', 'Kiểm tra và điều chỉnh xích', NULL, 'Scheduled'
FROM Vehicles v WHERE v.license_plate = '50-H1 77777' -- Yamaha Grande in Maintenance
UNION ALL
-- Bicycles maintenance
SELECT v.vehicle_id, '2024-11-05', 'Thay lốp và căng xích', '2024-12-05', 'Completed'
FROM Vehicles v WHERE v.license_plate = 'BIC-008'; -- Giant Roam in Maintenance

-- Insert Sample Vehicle Usage History
-- IMPORTANT: Use actual vehicle_id values from the Vehicles table
-- Check actual IDs first: SELECT vehicle_id, license_plate, brand, model FROM Vehicles;

-- Get actual vehicle IDs and insert usage history
INSERT INTO VehicleUsageHistory (vehicle_id, rental_id, start_odo, end_odo, fuel_used)
SELECT v.vehicle_id, 1001, 4800, 5000, 5.50
FROM Vehicles v WHERE v.license_plate = '59A-12345' -- Toyota Vios
UNION ALL
SELECT v.vehicle_id, 1002, 2800, 3000, 4.20
FROM Vehicles v WHERE v.license_plate = '51F-67890' -- Toyota Camry
UNION ALL
SELECT v.vehicle_id, 1003, 11500, 12000, 12.00
FROM Vehicles v WHERE v.license_plate = '50G-11111' -- Toyota Fortuner
UNION ALL
SELECT v.vehicle_id, 1004, 7500, 8000, 10.50
FROM Vehicles v WHERE v.license_plate = '59B-22222' -- Toyota Innova
UNION ALL
-- Motorbike usage
SELECT v.vehicle_id, 2001, 7800, 8000, 1.20
FROM Vehicles v WHERE v.license_plate = '59-A1 12345' -- Honda Air Blade
UNION ALL
SELECT v.vehicle_id, 2002, 11800, 12000, 1.50
FROM Vehicles v WHERE v.license_plate = '51-F1 67890' -- Honda SH
UNION ALL
SELECT v.vehicle_id, 2003, 14800, 15000, 1.00
FROM Vehicles v WHERE v.license_plate = '59-B1 11111' -- Honda Wave RSX
UNION ALL
SELECT v.vehicle_id, 2004, 9800, 10000, 1.40
FROM Vehicles v WHERE v.license_plate = '50-G1 22222' -- Honda Winner X
UNION ALL
-- Bicycle usage (no fuel)
SELECT v.vehicle_id, 3001, 450, 500, NULL
FROM Vehicles v WHERE v.license_plate = 'BIC-001' -- Giant ATX
UNION ALL
SELECT v.vehicle_id, 3002, 250, 300, NULL
FROM Vehicles v WHERE v.license_plate = 'BIC-002' -- Trek FX
UNION ALL
-- Scooter usage
SELECT v.vehicle_id, 4001, 280, 300, NULL
FROM Vehicles v WHERE v.license_plate = 'SCOOT-001' -- Xiaomi
UNION ALL
SELECT v.vehicle_id, 4002, 550, 600, NULL
FROM Vehicles v WHERE v.license_plate = 'SCOOT-004'; -- Segway

-- Summary Stats Query
SELECT 
    type,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN status = 'Rented' THEN 1 ELSE 0 END) as rented,
    SUM(CASE WHEN status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance,
    AVG(daily_rate) as avg_daily_rate
FROM Vehicles
GROUP BY type
ORDER BY type;

-- Check data
SELECT 'Cars' as category, COUNT(*) as count FROM Vehicles WHERE type = 'Car'
UNION ALL
SELECT 'Motorbikes', COUNT(*) FROM Vehicles WHERE type = 'Motorbike'
UNION ALL
SELECT 'Bicycles', COUNT(*) FROM Vehicles WHERE type = 'Bicycle'
UNION ALL
SELECT 'E-Scooters', COUNT(*) FROM Vehicles WHERE type = 'Electric_Scooter';