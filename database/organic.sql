-- =====================================================
-- Database: organic_tilapia
-- Complete SQL with all tables and sample data
-- =====================================================

-- Drop database if exists (optional - use with caution)
-- DROP DATABASE IF EXISTS organic_tilapia;

-- Create database
CREATE DATABASE IF NOT EXISTS organic_tilapia;
USE organic_tilapia;

-- =====================================================
-- 1. USERS TABLE
-- =====================================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','staff') NOT NULL,
    assigned_pond VARCHAR(10) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    pond_id INT DEFAULT NULL,
    last_login DATETIME DEFAULT NULL
);

-- Insert users
INSERT INTO users (user_id, full_name, email, password, role, assigned_pond, created_at, pond_id, last_login) VALUES
(1, 'Juan Dela Cruz', 'admin@company.com', 'admin123', 'admin', NULL, '2026-03-14 00:00:59', NULL, '2026-03-16 10:30:00'),
(2, 'Maria Santos', 'manager@company.com', 'manager123', 'manager', NULL, '2026-03-14 00:00:59', NULL, '2026-03-16 09:15:00'),
(3, 'Pedro Reyes', 'staff1@company.com', 'staff123', 'staff', 'A-1', '2026-03-14 00:00:59', 1, '2026-03-16 08:45:00'),
(4, 'Ana Lopez', 'staff2@company.com', 'staff123', 'staff', 'B-2', '2026-03-14 00:00:59', 2, '2026-03-16 09:30:00'),
(5, 'Roberto Gomez', 'staff3@company.com', 'staff123', 'staff', 'C-1', '2026-03-15 10:30:00', 3, '2026-03-15 10:30:00');

-- =====================================================
-- 2. ROLES PERMISSIONS TABLE
-- =====================================================
CREATE TABLE roles_permissions (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name ENUM('admin','manager','staff') NOT NULL,
    can_create_account TINYINT(1) DEFAULT 0,
    can_monitor TINYINT(1) DEFAULT 0,
    can_manage_notifications TINYINT(1) DEFAULT 0
);

-- Insert role permissions
INSERT INTO roles_permissions (role_name, can_create_account, can_monitor, can_manage_notifications) VALUES
('admin', 1, 1, 1),
('manager', 0, 1, 1),
('staff', 0, 1, 0);

-- =====================================================
-- 3. PONDS TABLE
-- =====================================================
CREATE TABLE ponds (
    pond_id INT AUTO_INCREMENT PRIMARY KEY,
    pond_name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert ponds
INSERT INTO ponds (pond_id, pond_name, location, created_at) VALUES
(1, 'A-1', 'North Section - Manolo Fortich', '2026-01-15 00:00:00'),
(2, 'B-2', 'South Section - Manolo Fortich', '2026-01-15 00:00:00'),
(3, 'C-1', 'East Section - Manolo Fortich', '2026-02-01 00:00:00');

-- =====================================================
-- 4. USER-PONDS ASSIGNMENTS TABLE
-- =====================================================
CREATE TABLE user_ponds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pond_name VARCHAR(50) NOT NULL,
    organic_mg_l FLOAT,
    temperature_c FLOAT,
    ph_level FLOAT,
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Insert sample assignments
INSERT INTO user_ponds (pond_name, organic_mg_l, temperature_c, ph_level, detected_at, user_id) VALUES
('A-1', 65.5, 28.5, 7.2, NOW() - INTERVAL 5 MINUTE, 3),
('B-2', 82.3, 31.2, 8.1, NOW() - INTERVAL 2 MINUTE, 4),
('C-1', 45.2, 27.3, 6.9, NOW() - INTERVAL 1 HOUR, 5);

-- =====================================================
-- 5. READINGS TABLE
-- =====================================================
CREATE TABLE readings (
    reading_id INT AUTO_INCREMENT PRIMARY KEY,
    pond_name VARCHAR(50),
    temperature FLOAT,
    ph FLOAT,
    organic FLOAT,
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    staff_id INT,
    FOREIGN KEY (staff_id) REFERENCES users(user_id)
);

-- Insert sample readings (last 24 hours)
INSERT INTO readings (pond_name, temperature, ph, organic, detected_at, staff_id) VALUES
-- Pond A-1 readings
('A-1', 28.5, 7.2, 65.5, NOW() - INTERVAL 5 MINUTE, 3),
('A-1', 28.3, 7.1, 64.8, NOW() - INTERVAL 1 HOUR, 3),
('A-1', 28.1, 7.2, 63.2, NOW() - INTERVAL 2 HOUR, 3),
('A-1', 27.9, 7.3, 62.5, NOW() - INTERVAL 3 HOUR, 3),
('A-1', 28.2, 7.2, 63.8, NOW() - INTERVAL 4 HOUR, 3),
('A-1', 28.4, 7.1, 64.2, NOW() - INTERVAL 5 HOUR, 3),
('A-1', 28.6, 7.2, 65.1, NOW() - INTERVAL 6 HOUR, 3),

-- Pond B-2 readings
('B-2', 31.2, 8.1, 82.3, NOW() - INTERVAL 2 MINUTE, 4),
('B-2', 30.9, 8.0, 81.5, NOW() - INTERVAL 1 HOUR, 4),
('B-2', 30.5, 7.9, 80.2, NOW() - INTERVAL 2 HOUR, 4),
('B-2', 30.1, 7.8, 78.9, NOW() - INTERVAL 3 HOUR, 4),
('B-2', 29.8, 7.8, 77.5, NOW() - INTERVAL 4 HOUR, 4),
('B-2', 30.2, 7.9, 79.1, NOW() - INTERVAL 5 HOUR, 4),
('B-2', 30.7, 8.0, 80.8, NOW() - INTERVAL 6 HOUR, 4),

-- Pond C-1 readings
('C-1', 27.3, 6.9, 45.2, NOW() - INTERVAL 1 HOUR, 5),
('C-1', 27.1, 6.8, 44.5, NOW() - INTERVAL 2 HOUR, 5),
('C-1', 26.9, 6.9, 43.8, NOW() - INTERVAL 3 HOUR, 5),
('C-1', 27.0, 7.0, 44.1, NOW() - INTERVAL 4 HOUR, 5),
('C-1', 27.2, 6.9, 44.9, NOW() - INTERVAL 5 HOUR, 5),
('C-1', 27.4, 6.8, 45.5, NOW() - INTERVAL 6 HOUR, 5),
('C-1', 27.5, 6.9, 46.0, NOW() - INTERVAL 7 HOUR, 5);

-- =====================================================
-- 6. DETECTIONS TABLE
-- =====================================================
CREATE TABLE detections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sample_code VARCHAR(50),
    pond_id INT,
    organic_level FLOAT,
    water_temperature FLOAT,
    ph_level FLOAT,
    status VARCHAR(20),
    created_by INT,
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pond_id) REFERENCES ponds(pond_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Insert sample detections
INSERT INTO detections (sample_code, pond_id, organic_level, water_temperature, ph_level, status, created_by, detected_at) VALUES
-- Critical detection for B-2
('SMP-001', 2, 82.3, 31.2, 8.1, 'critical', 4, NOW() - INTERVAL 2 MINUTE),
-- Warning detection for A-1
('SMP-002', 1, 65.5, 28.5, 7.2, 'warning', 3, NOW() - INTERVAL 5 MINUTE),
-- Normal detection for C-1
('SMP-003', 3, 45.2, 27.3, 6.9, 'normal', 5, NOW() - INTERVAL 1 HOUR),

-- Previous detections
('SMP-004', 1, 64.8, 28.3, 7.1, 'normal', 3, NOW() - INTERVAL 1 HOUR),
('SMP-005', 2, 81.5, 30.9, 8.0, 'critical', 4, NOW() - INTERVAL 1 HOUR),
('SMP-006', 1, 63.2, 28.1, 7.2, 'normal', 3, NOW() - INTERVAL 2 HOUR),
('SMP-007', 2, 80.2, 30.5, 7.9, 'warning', 4, NOW() - INTERVAL 2 HOUR),
('SMP-008', 3, 44.5, 27.1, 6.8, 'normal', 5, NOW() - INTERVAL 2 HOUR),
('SMP-009', 1, 62.5, 27.9, 7.3, 'normal', 3, NOW() - INTERVAL 3 HOUR),
('SMP-010', 2, 78.9, 30.1, 7.8, 'warning', 4, NOW() - INTERVAL 3 HOUR),
('SMP-011', 1, 63.8, 28.2, 7.2, 'normal', 3, NOW() - INTERVAL 4 HOUR),
('SMP-012', 2, 77.5, 29.8, 7.8, 'warning', 4, NOW() - INTERVAL 4 HOUR),
('SMP-013', 3, 44.1, 27.0, 7.0, 'normal', 5, NOW() - INTERVAL 4 HOUR),
('SMP-014', 1, 64.2, 28.4, 7.1, 'normal', 3, NOW() - INTERVAL 5 HOUR),
('SMP-015', 2, 79.1, 30.2, 7.9, 'warning', 4, NOW() - INTERVAL 5 HOUR),
('SMP-016', 1, 65.1, 28.6, 7.2, 'warning', 3, NOW() - INTERVAL 6 HOUR),
('SMP-017', 2, 80.8, 30.7, 8.0, 'critical', 4, NOW() - INTERVAL 6 HOUR),
('SMP-018', 3, 44.9, 27.2, 6.9, 'normal', 5, NOW() - INTERVAL 5 HOUR),
('SMP-019', 3, 45.5, 27.4, 6.8, 'normal', 5, NOW() - INTERVAL 6 HOUR),
('SMP-020', 3, 46.0, 27.5, 6.9, 'normal', 5, NOW() - INTERVAL 7 HOUR);

-- =====================================================
-- 7. NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    pond_id INT,
    message VARCHAR(255) NOT NULL,
    status ENUM('unread','read','resolved') DEFAULT 'unread',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pond_id) REFERENCES ponds(pond_id)
);

-- Insert sample notifications
INSERT INTO notifications (pond_id, message, status, created_at) VALUES
(2, 'CRITICAL: High organic level (82%) detected. Temperature above threshold (31.2°C). Immediate action required.', 'unread', NOW() - INTERVAL 2 MINUTE),
(1, 'WARNING: Organic level approaching threshold (65%). Monitor closely.', 'unread', NOW() - INTERVAL 15 MINUTE),
(2, 'MANAGER ALERT: Requesting admin review of Pond B-2 critical condition. Staff already notified.', 'unread', NOW() - INTERVAL 5 MINUTE),
(3, 'INFO: Routine maintenance scheduled for Pond C-1 tomorrow at 9:00 AM.', 'read', NOW() - INTERVAL 1 DAY),
(1, 'RESOLVED: Previous warning on Pond A-1 has been addressed. Levels returning to normal.', 'resolved', NOW() - INTERVAL 2 DAYS);

-- =====================================================
-- 8. SYSTEM ACTIVITIES LOG (Optional - for tracking)
-- =====================================================
CREATE TABLE activities (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Insert sample activities
INSERT INTO activities (user_id, action, details, ip_address, created_at) VALUES
(1, 'login', 'Admin logged in', '192.168.1.100', NOW() - INTERVAL 10 MINUTE),
(2, 'login', 'Manager logged in', '192.168.1.101', NOW() - INTERVAL 30 MINUTE),
(3, 'reading_submitted', 'Submitted reading for Pond A-1', '192.168.1.102', NOW() - INTERVAL 5 MINUTE),
(4, 'reading_submitted', 'Submitted reading for Pond B-2', '192.168.1.103', NOW() - INTERVAL 2 MINUTE),
(5, 'reading_submitted', 'Submitted reading for Pond C-1', '192.168.1.104', NOW() - INTERVAL 1 HOUR),
(1, 'user_created', 'Created new staff account', '192.168.1.100', NOW() - INTERVAL 1 DAY),
(2, 'alert_sent', 'Sent notification to admin', '192.168.1.101', NOW() - INTERVAL 5 MINUTE);

-- =====================================================
-- 9. CREATE INDEXES FOR BETTER PERFORMANCE
-- =====================================================
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_assigned_pond ON users(assigned_pond);
CREATE INDEX idx_detections_pond_id ON detections(pond_id);
CREATE INDEX idx_detections_detected_at ON detections(detected_at);
CREATE INDEX idx_notifications_status ON notifications(status);
CREATE INDEX idx_notifications_pond_id ON notifications(pond_id);
CREATE INDEX idx_readings_pond_name ON readings(pond_name);
CREATE INDEX idx_readings_detected_at ON readings(detected_at);

-- =====================================================
-- 10. CREATE VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for latest pond readings
CREATE VIEW vw_latest_pond_readings AS
SELECT 
    p.pond_id,
    p.pond_name,
    p.location,
    d.organic_level,
    d.water_temperature,
    d.ph_level,
    d.status,
    d.detected_at as last_reading,
    u.full_name as staff_name
FROM ponds p
LEFT JOIN detections d ON p.pond_id = d.pond_id
LEFT JOIN users u ON d.created_by = u.user_id
WHERE d.detected_at = (
    SELECT MAX(detected_at) 
    FROM detections d2 
    WHERE d2.pond_id = p.pond_id
);

-- View for unread notifications count
CREATE VIEW vw_unread_notifications AS
SELECT 
    COUNT(*) as unread_count
FROM notifications
WHERE status = 'unread';

-- View for staff assignments
CREATE VIEW vw_staff_assignments AS
SELECT 
    u.user_id,
    u.full_name,
    u.email,
    u.assigned_pond,
    u.last_login,
    p.pond_name,
    p.location
FROM users u
LEFT JOIN ponds p ON u.assigned_pond = p.pond_name
WHERE u.role = 'staff';

-- =====================================================
-- 11. SAMPLE QUERIES (for reference)
-- =====================================================

/*

-- Get all staff members
SELECT * FROM users WHERE role = 'staff';

-- Get latest readings for all ponds
SELECT * FROM vw_latest_pond_readings;

-- Get unread notifications count
SELECT * FROM vw_unread_notifications;

-- Get readings for last 24 hours
SELECT * FROM detections 
WHERE detected_at >= NOW() - INTERVAL 24 HOUR 
ORDER BY detected_at DESC;

-- Get average readings per pond for last 7 days
SELECT 
    pond_id,
    AVG(organic_level) as avg_organic,
    AVG(water_temperature) as avg_temp,
    AVG(ph_level) as avg_ph
FROM detections
WHERE detected_at >= NOW() - INTERVAL 7 DAY
GROUP BY pond_id;

-- Get critical alerts
SELECT * FROM notifications 
WHERE status = 'unread' 
AND message LIKE '%CRITICAL%'
ORDER BY created_at DESC;

-- Get user login history
SELECT 
    u.full_name,
    u.role,
    a.action,
    a.created_at
FROM activities a
JOIN users u ON a.user_id = u.user_id
WHERE a.action = 'login'
ORDER BY a.created_at DESC
LIMIT 10;

*/

-- =====================================================
-- 12. STORED PROCEDURE FOR GENERATING DAILY REPORT
-- =====================================================

DELIMITER //

CREATE PROCEDURE sp_generate_daily_report(IN report_date DATE)
BEGIN
    SELECT 
        DATE(report_date) as report_date,
        COUNT(DISTINCT pond_id) as total_ponds,
        SUM(CASE WHEN status = 'safe' THEN 1 ELSE 0 END) as safe_ponds,
        SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning_ponds,
        SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) as critical_ponds,
        AVG(organic_level) as avg_organic,
        AVG(water_temperature) as avg_temp,
        AVG(ph_level) as avg_ph,
        COUNT(*) as total_readings
    FROM detections
    WHERE DATE(detected_at) = report_date;
END //

DELIMITER ;

-- =====================================================
-- 13. TRIGGER FOR AUTO-UPDATING STATUS BASED ON READINGS
-- =====================================================

DELIMITER //

CREATE TRIGGER trg_update_pond_status
AFTER INSERT ON detections
FOR EACH ROW
BEGIN
    DECLARE new_status VARCHAR(20);
    
    -- Determine status based on readings
    IF NEW.organic_level > 80 OR NEW.water_temperature > 32 OR NEW.ph_level > 8.5 THEN
        SET new_status = 'critical';
    ELSEIF NEW.organic_level > 60 OR NEW.water_temperature > 30 OR NEW.ph_level > 7.8 THEN
        SET new_status = 'warning';
    ELSE
        SET new_status = 'safe';
    END IF;
    
    -- Update the detection status
    UPDATE detections SET status = new_status WHERE id = NEW.id;
    
    -- Create notification if critical
    IF new_status = 'critical' THEN
        INSERT INTO notifications (pond_id, message, status)
        VALUES (NEW.pond_id, 
                CONCAT('CRITICAL: High levels detected - Organic: ', NEW.organic_level, 
                       '%, Temp: ', NEW.water_temperature, '°C, pH: ', NEW.ph_level),
                'unread');
    END IF;
END //

DELIMITER ;

-- =====================================================
-- 14. GRANT PERMISSIONS (if needed)
-- =====================================================

-- Create application user (optional)
-- CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'app_password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON organic_tilapia.* TO 'app_user'@'localhost';
-- FLUSH PRIVILEGES;

-- =====================================================
-- END OF DATABASE SCRIPT
-- =====================================================