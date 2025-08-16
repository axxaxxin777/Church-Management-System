-- Fix for active_visitors table/view missing error
-- Run this script on your MySQL/MariaDB database

USE if0_39655629_grace_community;

-- First, create the visitors table if it doesn't exist
CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Then create the active_visitors view
-- Drop the view first if it exists to recreate it
DROP VIEW IF EXISTS active_visitors;

CREATE VIEW active_visitors AS
SELECT * FROM visitors WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE);

-- Verify the creation was successful
SELECT 'Visitors table and active_visitors view created successfully!' as status;

-- Test the active_visitors view
SELECT COUNT(*) as active_visitor_count FROM active_visitors;