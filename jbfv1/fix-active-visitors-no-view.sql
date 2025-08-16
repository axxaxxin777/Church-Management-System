-- Fix for active_visitors without CREATE VIEW privileges
-- This works around InfinityFree hosting limitations

USE if0_39655629_grace_community;

-- Create visitors table if it doesn't exist
CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create active_visitors as a regular table (not a view)
-- This table will store only active visitors (last 5 minutes)
CREATE TABLE IF NOT EXISTS active_visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_last_activity (last_activity)
);

-- Clean up old records from active_visitors
DELETE FROM active_visitors WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE);

-- Verify table creation
SELECT 'Tables created successfully!' as status;
SELECT COUNT(*) as active_visitor_count FROM active_visitors;