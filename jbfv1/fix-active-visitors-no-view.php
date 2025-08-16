<?php
// Fix for active_visitors without CREATE VIEW privileges
// This script works around the hosting limitation by using a different approach

require_once 'config/database.php';

echo "<h2>Fixing Active Visitors Issue (No VIEW Required)</h2>\n";

try {
    // First, create the visitors table if it doesn't exist
    echo "<p>Creating visitors table...</p>\n";
    $sql = "CREATE TABLE IF NOT EXISTS visitors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(255) UNIQUE NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "<p>✓ Visitors table created/verified successfully.</p>\n";
    
    // Create an alternative active_visitors table instead of a view
    echo "<p>Creating active_visitors table (not a view)...</p>\n";
    $sql = "CREATE TABLE IF NOT EXISTS active_visitors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(255) UNIQUE NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_last_activity (last_activity)
    )";
    
    $pdo->exec($sql);
    echo "<p>✓ Active visitors table created successfully.</p>\n";
    
    // Clean up old active visitor records (older than 5 minutes)
    echo "<p>Cleaning up old visitor records...</p>\n";
    $sql = "DELETE FROM active_visitors WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    $pdo->exec($sql);
    echo "<p>✓ Cleaned up old visitor records.</p>\n";
    
    // Test the table
    echo "<p>Testing the active_visitors table...</p>\n";
    $stmt = $pdo->query("SELECT COUNT(*) as active_visitors FROM active_visitors");
    $result = $stmt->fetch();
    echo "<p>✓ Active visitors table test successful. Current active visitors: " . $result['active_visitors'] . "</p>\n";
    
    echo "<h3 style='color: green;'>✓ Fix completed successfully!</h3>\n";
    echo "<p><strong>Important:</strong> This solution uses a table instead of a view due to hosting restrictions.</p>\n";
    echo "<p>Your website should now work, but you'll need to implement a cleanup mechanism for old records.</p>\n";
    
    // Show additional instructions
    echo "<h4>Next Steps:</h4>\n";
    echo "<ul>\n";
    echo "<li>Your index.php should now work without errors</li>\n";
    echo "<li>Consider adding a cron job to clean old records periodically</li>\n";
    echo "<li>Or modify your visitor tracking code to handle cleanup automatically</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Error occurred:</h3>\n";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>\n";
    
    // Additional debugging information
    echo "<h4>Debug Information:</h4>\n";
    echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>\n";
    echo "<p><strong>Error File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Error Line:</strong> " . $e->getLine() . "</p>\n";
}
?>