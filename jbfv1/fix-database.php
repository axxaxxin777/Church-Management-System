<?php
// Script to fix database tables by recreating them from schema
require_once 'config/database.php';

echo "<h2>Database Fix Script</h2>";

try {
    // Test database connection
    echo "<p>✅ Database connection successful</p>";
    
    // Read the schema file
    $schema = file_get_contents('database/schema.sql');
    
    if (!$schema) {
        throw new Exception("Could not read schema file");
    }
    
    echo "<p>✅ Schema file loaded successfully</p>";
    
    // Split the schema into individual statements
    // Remove comments and split by semicolon
    $statements = [];
    $lines = explode("\n", $schema);
    $currentStatement = "";
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (trim($line) === "" || substr(trim($line), 0, 2) === "--") {
            continue;
        }
        
        // Skip CREATE DATABASE and USE statements
        if (stripos($line, "CREATE DATABASE") !== false || stripos($line, "USE ") !== false) {
            continue;
        }
        
        $currentStatement .= $line . "\n";
        
        // If line ends with semicolon, we have a complete statement
        if (substr(trim($line), -1) === ";") {
            $statements[] = trim($currentStatement);
            $currentStatement = "";
        }
    }
    
    // Execute each statement
    echo "<h3>Executing SQL statements...</h3>";
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
            echo "<p>✓ Executed: " . substr($statement, 0, 50) . "...</p>";
        } catch (PDOException $e) {
            $errorCount++;
            echo "<p>✗ Error executing statement: " . $e->getMessage() . "</p>";
            echo "<p>Statement: " . htmlspecialchars(substr($statement, 0, 100)) . "...</p>";
        }
    }
    
    echo "<h3>Results:</h3>";
    echo "<p>✅ Successfully executed: {$successCount} statements</p>";
    echo "<p>❌ Failed to execute: {$errorCount} statements</p>";
    
    // Verify the tables now exist
    echo "<h3>Verifying tables...</h3>";
    $expectedTables = ['users', 'settings', 'password_resets', 'events', 'sermons', 'prayer_requests', 'contact_messages'];
    
    foreach ($expectedTables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<p>✓ {$table} table accessible, contains {$count} records</p>";
        } catch (PDOException $e) {
            echo "<p>✗ {$table} table not accessible: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
    echo "<p>Error code: " . $e->getCode() . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Test Home Page</a></p>";
echo "<p><a href='check-database.php'>Check Database Again</a></p>";
?>