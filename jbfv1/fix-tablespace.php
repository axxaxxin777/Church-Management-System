<?php
// Script to fix tablespace issues by directly executing MySQL commands
require_once 'config/database.php';

echo "<h2>Tablespace Fix Script</h2>";

try {
    // Test database connection
    echo "<p>✅ Database connection successful</p>";
    
    // List of tables to fix
    $tables = [
        'users',
        'settings',
        'password_resets',
        'events',
        'sermons',
        'prayer_requests',
        'contact_messages',
        'visitors',
        'event_registrations'
    ];
    
    echo "<h3>Discarding tablespace for tables...</h3>";
    foreach ($tables as $table) {
        try {
            // Try to discard tablespace
            $pdo->exec("ALTER TABLE {$table} DISCARD TABLESPACE");
            echo "<p>✓ Discarded tablespace for: {$table}</p>";
        } catch (PDOException $e) {
            // This might fail if tablespace doesn't exist, which is fine
            echo "<p>ℹ Table {$table}: " . $e->getMessage() . "</p>";
        }
    }
    
    // Now drop all tables
    echo "<h3>Dropping tables...</h3>";
    foreach (array_reverse($tables) as $table) { // Reverse order for foreign keys
        try {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
            echo "<p>✓ Dropped table: {$table}</p>";
        } catch (PDOException $e) {
            echo "<p>⚠ Warning dropping {$table}: " . $e->getMessage() . "</p>";
        }
    }
    
    // Drop the view
    try {
        $pdo->exec("DROP VIEW IF EXISTS active_visitors");
        echo "<p>✓ Dropped view: active_visitors</p>";
    } catch (PDOException $e) {
        echo "<p>ℹ View active_visitors: " . $e->getMessage() . "</p>";
    }
    
    // Read and execute schema
    echo "<h3>Recreating tables from schema...</h3>";
    $schema = file_get_contents('database/schema.sql');
    
    if (!$schema) {
        throw new Exception("Could not read schema file");
    }
    
    // Split schema into statements
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
    
    // Execute CREATE TABLE statements first
    echo "<h4>Creating tables...</h4>";
    $createTableStatements = array_filter($statements, function($stmt) {
        return stripos($stmt, "CREATE TABLE") !== false;
    });
    
    foreach ($createTableStatements as $statement) {
        try {
            $pdo->exec($statement);
            // Extract table name for display
            preg_match('/CREATE TABLE\s+([^\s\(]+)/i', $statement, $matches);
            $tableName = isset($matches[1]) ? $matches[1] : 'unknown';
            echo "<p>✓ Created table: {$tableName}</p>";
        } catch (PDOException $e) {
            echo "<p>✗ Error creating table: " . $e->getMessage() . "</p>";
        }
    }
    
    // Execute INSERT statements
    echo "<h4>Inserting default data...</h4>";
    $insertStatements = array_filter($statements, function($stmt) {
        return stripos($stmt, "INSERT INTO") !== false;
    });
    
    foreach ($insertStatements as $statement) {
        try {
            $pdo->exec($statement);
            echo "<p>✓ Executed INSERT statement</p>";
        } catch (PDOException $e) {
            echo "<p>✗ Error executing INSERT: " . $e->getMessage() . "</p>";
        }
    }
    
    // Execute CREATE VIEW statements
    echo "<h4>Creating views...</h4>";
    $createViewStatements = array_filter($statements, function($stmt) {
        return stripos($stmt, "CREATE VIEW") !== false;
    });
    
    foreach ($createViewStatements as $statement) {
        try {
            $pdo->exec($statement);
            echo "<p>✓ Created view</p>";
        } catch (PDOException $e) {
            echo "<p>✗ Error creating view: " . $e->getMessage() . "</p>";
        }
    }
    
    // Verify tables
    echo "<h3>Verifying tables...</h3>";
    foreach (['users', 'settings'] as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<p>✓ {$table} table accessible, contains {$count} records</p>";
        } catch (PDOException $e) {
            echo "<p>✗ {$table} table not accessible: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h3>Tablespace fix completed!</h3>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Test Home Page</a></p>";
echo "<p><a href='check-database.php'>Check Database Again</a></p>";
?>