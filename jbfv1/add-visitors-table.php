<?php
// add-visitors-table.php - Script to add visitors table to existing database
require_once 'config/database.php';

try {
    // Create visitors table
    $sql = "CREATE TABLE IF NOT EXISTS visitors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(255) UNIQUE NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    
    // Create active_visitors view
    $sql = "CREATE OR REPLACE VIEW active_visitors AS
        SELECT * FROM visitors WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    
    $pdo->exec($sql);
    
    echo "Visitors table and active_visitors view created successfully.\n";
} catch (Exception $e) {
    echo "Error creating visitors table: " . $e->getMessage() . "\n";
}
?>