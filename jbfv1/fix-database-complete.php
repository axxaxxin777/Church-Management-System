<?php
// Complete database fix script - drops and recreates all tables
require_once 'config/database.php';

echo "<h2>Complete Database Fix Script</h2>";

try {
    // Test database connection
    echo "<p>✅ Database connection successful</p>";
    
    // List of tables to drop (in reverse order to handle foreign key constraints)
    $tablesToDrop = [
        'event_registrations',
        'password_resets',
        'prayer_requests',
        'contact_messages',
        'settings',
        'sermons',
        'events',
        'users',
        'visitors',
        'active_visitors' // This is a view
    ];
    
    // Drop existing tables
    echo "<h3>Dropping existing tables...</h3>";
    foreach ($tablesToDrop as $table) {
        try {
            if ($table === 'active_visitors') {
                // Drop view
                $pdo->exec("DROP VIEW IF EXISTS {$table}");
                echo "<p>✓ Dropped view: {$table}</p>";
            } else {
                // Drop table
                $pdo->exec("DROP TABLE IF EXISTS {$table}");
                echo "<p>✓ Dropped table: {$table}</p>";
            }
        } catch (PDOException $e) {
            echo "<p>⚠ Warning dropping {$table}: " . $e->getMessage() . "</p>";
        }
    }
    
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
        $trimmedLine = trim($line);
        if ($trimmedLine === "" || substr($trimmedLine, 0, 2) === "--") {
            continue;
        }
        
        // Skip CREATE DATABASE and USE statements
        if (stripos($trimmedLine, "CREATE DATABASE") !== false || stripos($trimmedLine, "USE ") !== false) {
            continue;
        }
        
        $currentStatement .= $line . "\n";
        
        // If line ends with semicolon, we have a complete statement
        if (substr($trimmedLine, -1) === ";") {
            $statements[] = trim($currentStatement);
            $currentStatement = "";
        }
    }
    
    // Execute each statement
    echo "<h3>Creating tables from schema...</h3>";
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
            // Only show first part of statement to avoid cluttering output
            $shortStatement = substr($statement, 0, 50);
            echo "<p>✓ Executed: " . htmlspecialchars($shortStatement) . "...</p>";
        } catch (PDOException $e) {
            $errorCount++;
            $shortStatement = substr($statement, 0, 100);
            echo "<p>✗ Error executing statement: " . $e->getMessage() . "</p>";
            echo "<p>Statement: " . htmlspecialchars($shortStatement) . "...</p>";
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
    
    echo "<h3>Database fix completed!</h3>";
    echo "<p>The database tables have been recreated from the schema.</p>";
    
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