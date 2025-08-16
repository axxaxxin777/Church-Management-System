<?php
// Test database connection and tables
require_once 'config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test connection
    echo "<p>✓ Database connection successful</p>";
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✓ Users table exists</p>";
    } else {
        echo "<p>✗ Users table does not exist</p>";
    }
    
    // Check if settings table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✓ Settings table exists</p>";
    } else {
        echo "<p>✗ Settings table does not exist</p>";
    }
    
    // Check if password_resets table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_resets'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✓ Password resets table exists</p>";
    } else {
        echo "<p>✗ Password resets table does not exist</p>";
    }
    
    // Check settings data
    echo "<h3>Settings Data:</h3>";
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    if ($stmt->rowCount() > 0) {
        while ($row = $stmt->fetch()) {
            echo "<p><strong>{$row['setting_key']}:</strong> {$row['setting_value']}</p>";
        }
    } else {
        echo "<p>No settings found</p>";
    }
    
} catch(PDOException $e) {
    echo "<p>✗ Database connection failed: " . $e->getMessage() . "</p>";
}
?>