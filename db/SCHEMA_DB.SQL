CREATE DATABASE tilapia_system;
USE tilapia_system;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','user') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ponds (
    pond_id INT AUTO_INCREMENT PRIMARY KEY,
    pond_name VARCHAR(100) NOT NULL,
    location VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);  


CREATE TABLE user_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE
);


INSERT INTO users (full_name, email, password, role) VALUES
('Admin User', 'admin@gmail.com', 'admin123', 'admin'),
('Manager User', 'manager@gmail.com', 'manager123', 'manager'),
('Regular User', 'user@gmail.com', 'user123', 'user');

INSERT INTO ponds (pond_name, location) VALUES
('Pond A', 'North Area'),
('Pond B', 'South Area');

INSERT INTO user_logs (user_id, action) VALUES
(1, 'Logged in'),
(1, 'Added Pond A'),
(2, 'Viewed Pond List'),
(3, 'Logged in');
