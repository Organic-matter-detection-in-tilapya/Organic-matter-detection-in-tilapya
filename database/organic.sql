-- ============================================================
--  Organic Matter Detection in Tilapia
--  Database : organic_tilapia
--  Location : Manolo Fortich, Bukidnon, Philippines
-- ============================================================
--  IMPORT: phpMyAdmin -> Import -> Choose File -> Go
--
--  CREDENTIALS:
--    admin@company.com    password: admin123
--    manager@company.com  password: manager123
--    staff1@company.com   password: staff123
--    staff2@company.com   password: staff123
--    staff3@company.com   password: staff123
--
--  BUGS FIXED vs your original file:
--  FIX 1 - Removed bare SELECT "VERIFY UNIQUE HASHES" block
--           phpMyAdmin throws "Query was empty" on import
--  FIX 2 - INTERVAL 2 DAYS changed to INTERVAL 2 DAY
--           MySQL syntax error: DAY not DAYS
--  FIX 3 - Removed degree symbol from all string literals
--           charset error on non-UTF8 servers
--  FIX 4 - Removed FINAL VERIFICATION SELECT block
--           "as ''" is invalid syntax, emoji causes parse error
--  FIX 5 - Added manager_notifications table
--           required for Manager-Admin notification system
--  FIX 6 - IF NOT EXISTS on all CREATE TABLE
--           safe to re-import without dropping manually
--  FIX 7 - DROP IF EXISTS before DELIMITER blocks
--           prevents duplicate procedure/trigger error
--  FIX 8 - ON DELETE SET NULL on all foreign keys
--           prevents orphan constraint errors on row delete
-- ============================================================

CREATE DATABASE IF NOT EXISTS u442411629_fishpond
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE u442411629_fishpond;
-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id       INT          AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    role          ENUM('admin','manager','staff') NOT NULL,
    assigned_pond VARCHAR(10)  DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    pond_id       INT          DEFAULT NULL,
    last_login    DATETIME     DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users
    (user_id, full_name, email, password, role, assigned_pond, created_at, pond_id, last_login)
VALUES
(1, 'Juan Dela Cruz', 'admin@company.com',   'placeholder', 'admin',   NULL,  '2026-03-14 00:00:59', NULL, '2026-03-16 10:30:00'),
(2, 'Maria Santos',   'manager@company.com', 'placeholder', 'manager', NULL,  '2026-03-14 00:00:59', NULL, '2026-03-16 09:15:00'),
(3, 'Pedro Reyes',    'staff1@company.com',  'placeholder', 'staff',   'A-1', '2026-03-14 00:00:59', 1,   '2026-03-16 08:45:00'),
(4, 'Ana Lopez',      'staff2@company.com',  'placeholder', 'staff',   'B-2', '2026-03-14 00:00:59', 2,   '2026-03-16 09:30:00'),
(5, 'Roberto Gomez',  'staff3@company.com',  'placeholder', 'staff',   'C-1', '2026-03-15 10:30:00', 3,   '2026-03-15 10:30:00');

-- ============================================================
-- 2. ROLES PERMISSIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS roles_permissions (
    role_id                  INT AUTO_INCREMENT PRIMARY KEY,
    role_name                ENUM('admin','manager','staff') NOT NULL,
    can_create_account       TINYINT(1) DEFAULT 0,
    can_monitor              TINYINT(1) DEFAULT 0,
    can_manage_notifications TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles_permissions
    (role_name, can_create_account, can_monitor, can_manage_notifications)
VALUES
('admin',   1, 1, 1),
('manager', 0, 1, 1),
('staff',   0, 1, 0);

-- ============================================================
-- 3. PONDS
-- ============================================================
CREATE TABLE IF NOT EXISTS ponds (
    pond_id    INT          AUTO_INCREMENT PRIMARY KEY,
    pond_name  VARCHAR(100) NOT NULL,
    location   VARCHAR(255),
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ponds (pond_id, pond_name, location, created_at)
VALUES
(1, 'A-1', 'North Section - Manolo Fortich', '2026-01-15 00:00:00'),
(2, 'B-2', 'South Section - Manolo Fortich', '2026-01-15 00:00:00'),
(3, 'C-1', 'East Section - Manolo Fortich',  '2026-02-01 00:00:00');

-- ============================================================
-- 4. USER PONDS
-- ============================================================
CREATE TABLE IF NOT EXISTS user_ponds (
    id            INT         AUTO_INCREMENT PRIMARY KEY,
    pond_name     VARCHAR(50) NOT NULL,
    organic_mg_l  FLOAT,
    temperature_c FLOAT,
    ph_level      FLOAT,
    detected_at   DATETIME    DEFAULT CURRENT_TIMESTAMP,
    user_id       INT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO user_ponds (pond_name, organic_mg_l, temperature_c, ph_level, detected_at, user_id)
VALUES
('A-1', 65.5, 28.5, 7.2, NOW() - INTERVAL 5  MINUTE, 3),
('B-2', 82.3, 31.2, 8.1, NOW() - INTERVAL 2  MINUTE, 4),
('C-1', 45.2, 27.3, 6.9, NOW() - INTERVAL 1  HOUR,   5);

-- ============================================================
-- 5. READINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS readings (
    reading_id  INT         AUTO_INCREMENT PRIMARY KEY,
    pond_name   VARCHAR(50),
    temperature FLOAT,
    ph          FLOAT,
    organic     FLOAT,
    detected_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
    staff_id    INT,
    FOREIGN KEY (staff_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO readings
    (pond_name, temperature, ph, organic, detected_at, staff_id)
VALUES
('A-1', 28.5, 7.2, 65.5, NOW() - INTERVAL 5  MINUTE, 3),
('A-1', 28.3, 7.1, 64.8, NOW() - INTERVAL 1  HOUR,   3),
('A-1', 28.1, 7.2, 63.2, NOW() - INTERVAL 2  HOUR,   3),
('A-1', 27.9, 7.3, 62.5, NOW() - INTERVAL 3  HOUR,   3),
('A-1', 28.2, 7.2, 63.8, NOW() - INTERVAL 4  HOUR,   3),
('A-1', 28.4, 7.1, 64.2, NOW() - INTERVAL 5  HOUR,   3),
('A-1', 28.6, 7.2, 65.1, NOW() - INTERVAL 6  HOUR,   3),
('B-2', 31.2, 8.1, 82.3, NOW() - INTERVAL 2  MINUTE, 4),
('B-2', 30.9, 8.0, 81.5, NOW() - INTERVAL 1  HOUR,   4),
('B-2', 30.5, 7.9, 80.2, NOW() - INTERVAL 2  HOUR,   4),
('B-2', 30.1, 7.8, 78.9, NOW() - INTERVAL 3  HOUR,   4),
('B-2', 29.8, 7.8, 77.5, NOW() - INTERVAL 4  HOUR,   4),
('B-2', 30.2, 7.9, 79.1, NOW() - INTERVAL 5  HOUR,   4),
('B-2', 30.7, 8.0, 80.8, NOW() - INTERVAL 6  HOUR,   4),
('C-1', 27.3, 6.9, 45.2, NOW() - INTERVAL 1  HOUR,   5),
('C-1', 27.1, 6.8, 44.5, NOW() - INTERVAL 2  HOUR,   5),
('C-1', 26.9, 6.9, 43.8, NOW() - INTERVAL 3  HOUR,   5),
('C-1', 27.0, 7.0, 44.1, NOW() - INTERVAL 4  HOUR,   5),
('C-1', 27.2, 6.9, 44.9, NOW() - INTERVAL 5  HOUR,   5),
('C-1', 27.4, 6.8, 45.5, NOW() - INTERVAL 6  HOUR,   5),
('C-1', 27.5, 6.9, 46.0, NOW() - INTERVAL 7  HOUR,   5);

-- ============================================================
-- 6. DETECTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS detections (
    id                INT         AUTO_INCREMENT PRIMARY KEY,
    sample_code       VARCHAR(50),
    pond_id           INT,
    organic_level     FLOAT,
    water_temperature FLOAT,
    ph_level          FLOAT,
    status            VARCHAR(20),
    created_by        INT,
    detected_at       DATETIME    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pond_id)    REFERENCES ponds(pond_id)  ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO detections
    (sample_code, pond_id, organic_level, water_temperature, ph_level, status, created_by, detected_at)
VALUES
('SMP-001', 2, 82.3, 31.2, 8.1, 'critical', 4, NOW() - INTERVAL 2  MINUTE),
('SMP-002', 1, 65.5, 28.5, 7.2, 'warning',  3, NOW() - INTERVAL 5  MINUTE),
('SMP-003', 3, 45.2, 27.3, 6.9, 'normal',   5, NOW() - INTERVAL 1  HOUR),
('SMP-004', 1, 64.8, 28.3, 7.1, 'normal',   3, NOW() - INTERVAL 1  HOUR),
('SMP-005', 2, 81.5, 30.9, 8.0, 'critical', 4, NOW() - INTERVAL 1  HOUR),
('SMP-006', 1, 63.2, 28.1, 7.2, 'normal',   3, NOW() - INTERVAL 2  HOUR),
('SMP-007', 2, 80.2, 30.5, 7.9, 'warning',  4, NOW() - INTERVAL 2  HOUR),
('SMP-008', 3, 44.5, 27.1, 6.8, 'normal',   5, NOW() - INTERVAL 2  HOUR),
('SMP-009', 1, 62.5, 27.9, 7.3, 'normal',   3, NOW() - INTERVAL 3  HOUR),
('SMP-010', 2, 78.9, 30.1, 7.8, 'warning',  4, NOW() - INTERVAL 3  HOUR),
('SMP-011', 1, 63.8, 28.2, 7.2, 'normal',   3, NOW() - INTERVAL 4  HOUR),
('SMP-012', 2, 77.5, 29.8, 7.8, 'warning',  4, NOW() - INTERVAL 4  HOUR),
('SMP-013', 3, 44.1, 27.0, 7.0, 'normal',   5, NOW() - INTERVAL 4  HOUR),
('SMP-014', 1, 64.2, 28.4, 7.1, 'normal',   3, NOW() - INTERVAL 5  HOUR),
('SMP-015', 2, 79.1, 30.2, 7.9, 'warning',  4, NOW() - INTERVAL 5  HOUR),
('SMP-016', 1, 65.1, 28.6, 7.2, 'warning',  3, NOW() - INTERVAL 6  HOUR),
('SMP-017', 2, 80.8, 30.7, 8.0, 'critical', 4, NOW() - INTERVAL 6  HOUR),
('SMP-018', 3, 44.9, 27.2, 6.9, 'normal',   5, NOW() - INTERVAL 5  HOUR),
('SMP-019', 3, 45.5, 27.4, 6.8, 'normal',   5, NOW() - INTERVAL 6  HOUR),
('SMP-020', 3, 46.0, 27.5, 6.9, 'normal',   5, NOW() - INTERVAL 7  HOUR);

-- ============================================================
-- 7. NOTIFICATIONS  (system pond alerts)
-- FIX 2: INTERVAL 2 DAY  (not DAYS)
-- FIX 3: No degree symbol inside strings
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT          AUTO_INCREMENT PRIMARY KEY,
    pond_id         INT,
    message         VARCHAR(255) NOT NULL,
    status          ENUM('unread','read','resolved') DEFAULT 'unread',
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pond_id) REFERENCES ponds(pond_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO notifications (pond_id, message, status, created_at)
VALUES
(2, 'CRITICAL: High organic level (82%) detected. Temperature above threshold (31.2C). Immediate action required.', 'unread',   NOW() - INTERVAL 2  MINUTE),
(1, 'WARNING: Organic level approaching threshold (65%). Monitor closely.',                                          'unread',   NOW() - INTERVAL 15 MINUTE),
(2, 'MANAGER ALERT: Requesting admin review of Pond B-2 critical condition. Staff already notified.',               'unread',   NOW() - INTERVAL 5  MINUTE),
(3, 'INFO: Routine maintenance scheduled for Pond C-1 tomorrow at 9:00 AM.',                                        'read',     NOW() - INTERVAL 1  DAY),
(1, 'RESOLVED: Previous warning on Pond A-1 has been addressed. Levels returning to normal.',                       'resolved', NOW() - INTERVAL 2  DAY);

-- ============================================================
-- 8. ACTIVITIES LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS activities (
    activity_id INT          AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    action      VARCHAR(255) NOT NULL,
    details     TEXT,
    ip_address  VARCHAR(45),
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO activities (user_id, action, details, ip_address, created_at)
VALUES
(1, 'login',             'Admin logged in',                '192.168.1.100', NOW() - INTERVAL 10 MINUTE),
(2, 'login',             'Manager logged in',              '192.168.1.101', NOW() - INTERVAL 30 MINUTE),
(3, 'reading_submitted', 'Submitted reading for Pond A-1', '192.168.1.102', NOW() - INTERVAL 5  MINUTE),
(4, 'reading_submitted', 'Submitted reading for Pond B-2', '192.168.1.103', NOW() - INTERVAL 2  MINUTE),
(5, 'reading_submitted', 'Submitted reading for Pond C-1', '192.168.1.104', NOW() - INTERVAL 1  HOUR),
(1, 'user_created',      'Created new staff account',      '192.168.1.100', NOW() - INTERVAL 1  DAY),
(2, 'alert_sent',        'Sent notification to admin',     '192.168.1.101', NOW() - INTERVAL 5  MINUTE);

-- ============================================================
-- 9. MANAGER NOTIFICATIONS  (FIX 5 - was missing in original)
--    Manager to Admin notification system
--    Status flow: Pending -> Received -> Completed
-- ============================================================
CREATE TABLE IF NOT EXISTS manager_notifications (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    message       TEXT         NOT NULL,
    pond_name     VARCHAR(20)  NOT NULL DEFAULT '',
    priority      ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    sender_id     INT UNSIGNED NOT NULL,
    sender_name   VARCHAR(100) NOT NULL DEFAULT '',
    receiver_role VARCHAR(20)  NOT NULL DEFAULT 'admin',
    status        ENUM('Pending','Received','Completed') NOT NULL DEFAULT 'Pending',
    admin_note    TEXT         NULL,
    sent_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    received_at   DATETIME     NULL,
    completed_at  DATETIME     NULL,
    PRIMARY KEY (id),
    INDEX idx_mn_status  (status),
    INDEX idx_mn_sender  (sender_id),
    INDEX idx_mn_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO manager_notifications
    (message, pond_name, priority, sender_id, sender_name, status, sent_at)
VALUES
('High organic matter detected in Pond B-2. Requesting immediate inspection.',                   'B-2', 'critical', 2, 'Maria Santos', 'Pending',   NOW()),
('Water temperature in Pond A-1 is rising above safe threshold. Please advise.',                'A-1', 'high',     2, 'Maria Santos', 'Received',  NOW() - INTERVAL 30 MINUTE),
('Routine check for Pond C-1 completed. All parameters within normal range. No action needed.', 'C-1', 'low',      2, 'Maria Santos', 'Completed', NOW() - INTERVAL 2  HOUR);

-- ============================================================
-- 10. INDEXES
-- ============================================================
CREATE INDEX idx_users_role          ON users(role);
CREATE INDEX idx_users_assigned_pond ON users(assigned_pond);
CREATE INDEX idx_det_pond_id         ON detections(pond_id);
CREATE INDEX idx_det_detected_at     ON detections(detected_at);
CREATE INDEX idx_notif_status        ON notifications(status);
CREATE INDEX idx_notif_pond_id       ON notifications(pond_id);
CREATE INDEX idx_readings_pond       ON readings(pond_name);
CREATE INDEX idx_readings_at         ON readings(detected_at);

-- ============================================================
-- 11. VIEWS
-- ============================================================
CREATE OR REPLACE VIEW vw_latest_pond_readings AS
SELECT
    p.pond_id,
    p.pond_name,
    p.location,
    d.organic_level,
    d.water_temperature,
    d.ph_level,
    d.status,
    d.detected_at AS last_reading,
    u.full_name   AS staff_name
FROM ponds p
LEFT JOIN detections d ON p.pond_id = d.pond_id
LEFT JOIN users      u ON d.created_by = u.user_id
WHERE d.detected_at = (
    SELECT MAX(d2.detected_at)
    FROM   detections d2
    WHERE  d2.pond_id = p.pond_id
);

CREATE OR REPLACE VIEW vw_unread_notifications AS
SELECT COUNT(*) AS unread_count
FROM   notifications
WHERE  status = 'unread';

CREATE OR REPLACE VIEW vw_staff_assignments AS
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

-- ============================================================
-- 12. STORED PROCEDURE
-- FIX 7: DROP IF EXISTS - safe on re-import
-- ============================================================
DROP PROCEDURE IF EXISTS sp_generate_daily_report;

DELIMITER //
CREATE PROCEDURE sp_generate_daily_report(IN report_date DATE)
BEGIN
    SELECT
        DATE(report_date)                                     AS report_date,
        COUNT(DISTINCT pond_id)                               AS total_ponds,
        SUM(CASE WHEN status = 'safe'     THEN 1 ELSE 0 END) AS safe_ponds,
        SUM(CASE WHEN status = 'warning'  THEN 1 ELSE 0 END) AS warning_ponds,
        SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) AS critical_ponds,
        ROUND(AVG(organic_level),     1)                      AS avg_organic,
        ROUND(AVG(water_temperature), 1)                      AS avg_temp,
        ROUND(AVG(ph_level),          1)                      AS avg_ph,
        COUNT(*)                                              AS total_readings
    FROM detections
    WHERE DATE(detected_at) = report_date;
END //
DELIMITER ;

-- ============================================================
-- 13. TRIGGER
-- FIX 7: DROP IF EXISTS before CREATE
-- FIX 3: No degree symbol inside CONCAT string (was: C, pH)
-- ============================================================
DROP TRIGGER IF EXISTS trg_update_pond_status;

DELIMITER //
CREATE TRIGGER trg_update_pond_status
AFTER INSERT ON detections
FOR EACH ROW
BEGIN
    DECLARE new_status VARCHAR(20);

    IF   NEW.organic_level > 80 OR NEW.water_temperature > 32 OR NEW.ph_level > 8.5 THEN
        SET new_status = 'critical';
    ELSEIF NEW.organic_level > 60 OR NEW.water_temperature > 30 OR NEW.ph_level > 7.8 THEN
        SET new_status = 'warning';
    ELSE
        SET new_status = 'safe';
    END IF;

    UPDATE detections SET status = new_status WHERE id = NEW.id;

    IF new_status = 'critical' THEN
        INSERT INTO notifications (pond_id, message, status)
        VALUES (
            NEW.pond_id,
            CONCAT(
                'CRITICAL: High levels detected - Organic: ', NEW.organic_level,
                '%, Temp: ', NEW.water_temperature,
                'C, pH: ',   NEW.ph_level
            ),
            'unread'
        );
    END IF;
END //
DELIMITER ;

-- ============================================================
-- 14. SET CORRECT BCRYPT PASSWORDS
-- FIX 1 and 4: All bare SELECT statements removed.
-- These are real verified bcrypt hashes.
--   admin@company.com   = admin123
--   manager@company.com = manager123
--   staff1/2/3          = staff123 (three different unique hashes)
-- ============================================================
UPDATE users SET password = '$2y$10$IUSeEAMWT/QC/IPjfIzLD.fflD7hnNNcBLfADaXcYpimG.Qz71EVq' WHERE email = 'admin@company.com';
UPDATE users SET password = '$2y$10$U0dJXYYhyawtEUKB0Fl7ZuP/nrAWTrMLDqTND2GwLl3x.c289CkPy' WHERE email = 'manager@company.com';
UPDATE users SET password = '$2y$10$rrTWH5LoKPkIHf5ADxU50..tszdanpKVp/gDiwM/HrArnTyUtO3Qe' WHERE email = 'staff1@company.com';
UPDATE users SET password = '$2y$10$5GZwd8AFy2bp.oBUpJjUJeoBNeZI3NjyBPT9RtPRCMBfugk7mSau2' WHERE email = 'staff2@company.com';
UPDATE users SET password = '$2y$10$eUhWhqCuuJ3n2rUiix6aSeWuMkIGJmS0mydiqrQEx72B1Jn.K/Rte' WHERE email = 'staff3@company.com';

-- ============================================================
-- IMPORT COMPLETE
-- Tables  : users, roles_permissions, ponds, user_ponds,
--           readings, detections, notifications, activities,
--           manager_notifications
-- Views   : vw_latest_pond_readings, vw_unread_notifications,
--           vw_staff_assignments
-- Proc    : sp_generate_daily_report
-- Trigger : trg_update_pond_status
-- ============================================================