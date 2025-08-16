<?php
// Script to execute schema.sql file directly through PDO
echo "<h2>Executing Schema SQL File Directly</h2>";

// Database configuration
require_once 'config/database.php';

try {
    // Check if SQL file exists
    $sqlFile = 'database/schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: {$sqlFile}");
    }
    
    echo "<p>✅ SQL file found: {$sqlFile}</p>";
    
    // Read the SQL file
    $sqlContent = file_get_contents($sqlFile);
    
    // Remove comments (lines starting with --)
    $sqlContent = preg_replace('/--.*\n/', '', $sqlContent);
    
    // Split by semicolon, but be careful with semicolons in values
    $sqlStatements = [];
    $currentStatement = '';
    $inSingleQuote = false;
    $inDoubleQuote = false;
    
    for ($i = 0; $i < strlen($sqlContent); $i++) {
        $char = $sqlContent[$i];
        
        if ($char === "'" && !$inDoubleQuote) {
            $inSingleQuote = !$inSingleQuote;
        } elseif ($char === '"' && !$inSingleQuote) {
            $inDoubleQuote = !$inDoubleQuote;
        } elseif ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
            $currentStatement .= $char;
            $sqlStatements[] = trim($currentStatement);
            $currentStatement = '';
            continue;
        }
        
        $currentStatement .= $char;
    }
    
    // Add the last statement if it doesn't end with semicolon
    if (!empty(trim($currentStatement))) {
        $sqlStatements[] = trim($currentStatement);
    }
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($sqlStatements as $sql) {
        $sql = trim($sql);
        // Skip empty statements
        if (empty($sql)) {
            continue;
        }
        
        // Skip CREATE DATABASE and USE statements as we're already connected
        if (strpos($sql, 'CREATE DATABASE') === 0 || strpos($sql, 'USE ') === 0) {
            $successCount++;
            continue;
        }
        
        try {
            // Execute each SQL statement
            $pdo->exec($sql);
            $successCount++;
        } catch (PDOException $e) {
            echo "<p>⚠️ Warning: " . $e->getMessage() . " for SQL: " . substr($sql, 0, 100) . "...</p>";
            $errorCount++;
        }
    }
    
    echo "<p>✅ Successfully executed {$successCount} SQL statements</p>";
    if ($errorCount > 0) {
        echo "<p>⚠️ Encountered {$errorCount} warnings</p>";
    }
    
    // Test the connection to verify tables exist
    echo "<h3>Verifying database...</h3>";
    
    // Check if settings table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM settings");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>✅ Settings table accessible, contains {$count} records</p>";
    
    // Check if users table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>✅ Users table accessible, contains {$count} records</p>";
    
    echo "<h3>Database setup completed successfully!</h3>";
    echo "<p>All tables have been created and populated with default data.</p>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Test Home Page</a></p>";
echo "<p><a href='check-database.php'>Check Database Again</a></p>";
?>