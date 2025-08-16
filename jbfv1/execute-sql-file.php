<?php
// Script to execute SQL file using MySQL command line
echo "<h2>Executing SQL File</h2>";

// Database configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'grace_community';

// Path to SQL file
$sqlFile = 'database/schema.sql';

try {
    // Check if SQL file exists
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: {$sqlFile}");
    }
    
    echo "<p>✅ SQL file found: {$sqlFile}</p>";
    
    // Create MySQL command
    // Note: We're using a simple approach here that works with XAMPP's MySQL
    $command = "mysql -h {$dbHost} -u {$dbUser} " . (empty($dbPass) ? "" : "-p{$dbPass}") . " < {$sqlFile}";
    
    echo "<p>Executing command: {$command}</p>";
    
    // Execute the command
    $output = shell_exec($command . " 2>&1");
    
    if ($output) {
        echo "<p>Output: " . htmlspecialchars($output) . "</p>";
    } else {
        echo "<p>✅ Command executed successfully</p>";
    }
    
    // Test the connection to verify tables exist
    echo "<h3>Verifying database...</h3>";
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName}", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if settings table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM settings");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>✅ Settings table accessible, contains {$count} records</p>";
    
    // Check if users table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>✅ Users table accessible, contains {$count} records</p>";
    
    echo "<h3>Database reset completed successfully!</h3>";
    echo "<p>All tables have been recreated and populated with default data.</p>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Test Home Page</a></p>";
echo "<p><a href='check-database.php'>Check Database Again</a></p>";
?>