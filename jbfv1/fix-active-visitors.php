<?php
// Fix for active_visitors table/view missing error
// This script can be run via web browser or command line

require_once 'config/database.php';

echo "<h2>Fixing Active Visitors Table/View Issue</h2>\n";

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
    
    // Drop the view first if it exists to recreate it
    echo "<p>Dropping existing active_visitors view if it exists...</p>\n";
    $pdo->exec("DROP VIEW IF EXISTS active_visitors");
    echo "<p>✓ Existing view dropped.</p>\n";
    
    // Create the active_visitors view
    echo "<p>Creating active_visitors view...</p>\n";
    $sql = "CREATE VIEW active_visitors AS
        SELECT * FROM visitors WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    
    $pdo->exec($sql);
    echo "<p>✓ Active visitors view created successfully.</p>\n";
    
    // Test the view
    echo "<p>Testing the active_visitors view...</p>\n";
    $stmt = $pdo->query("SELECT COUNT(*) as active_visitors FROM active_visitors");
    $result = $stmt->fetch();
    echo "<p>✓ Active visitors view test successful. Current active visitors: " . $result['active_visitors'] . "</p>\n";
    
    echo "<h3 style='color: green;'>✓ Fix completed successfully!</h3>\n";
    echo "<p>You can now reload your index.php page - the error should be resolved.</p>\n";
    
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