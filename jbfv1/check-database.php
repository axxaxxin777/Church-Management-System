<?php
// Database check script for Joy Bible Fellowship
require_once 'config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test database connection
    echo "<p>✅ Database connection successful</p>";
    
    // Check if password_resets table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_resets'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ password_resets table exists</p>";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE password_resets");
        echo "<h3>password_resets table structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "<td>{$row['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if table has any data
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM password_resets");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>📊 password_resets table has {$count} records</p>";
        
    } else {
        echo "<p>❌ password_resets table does NOT exist</p>";
        echo "<p>You need to run the installation script to create the table.</p>";
    }
    
    // Check all tables
    $stmt = $pdo->query("SHOW TABLES");
    echo "<h3>All tables in database:</h3>";
    echo "<ul>";
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "<li>{$row[0]}</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
    echo "<p>Error code: " . $e->getCode() . "</p>";
}

echo "<hr>";
echo "<p><a href='install.php'>Run Installation Script</a></p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
?>
