<?php
// Comprehensive database debugging script
require_once 'config/database.php';

echo "<h2>Database Debugging</h2>";

try {
    // Test database connection
    echo "<p>✅ Database connection successful</p>";
    
    // List all tables in the database
    echo "<h3>All tables in database:</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<p>No tables found in database</p>";
    } else {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>{$table}</li>";
        }
        echo "</ul>";
    }
    
    // Check each table individually
    $expectedTables = ['users', 'settings', 'password_resets', 'events', 'sermons', 'prayer_requests', 'contact_messages'];
    
    foreach ($expectedTables as $table) {
        echo "<h4>Checking table: {$table}</h4>";
        
        // Check if table exists in metadata
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "<p>✓ Table metadata exists</p>";
            
            // Try to access the table
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "<p>✓ Table accessible, contains {$count} records</p>";
            } catch (PDOException $e) {
                echo "<p>✗ Table not accessible: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p>✗ Table metadata does not exist</p>";
        }
    }
    
    // Check database engine
    echo "<h3>Database Engine Information:</h3>";
    $stmt = $pdo->query("SELECT @@version, @@version_comment");
    $versionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>MySQL Version: " . $versionInfo['@@version'] . "</p>";
    echo "<p>MySQL Comment: " . $versionInfo['@@version_comment'] . "</p>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
    echo "<p>Error code: " . $e->getCode() . "</p>";
}

echo "<hr>";
echo "<p><a href='install.php'>Run Installation Script</a></p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
?>