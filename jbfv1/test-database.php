<?php
// Database Connection Test Script
echo "<h1>Database Connection Test</h1>";

// Check if config file exists
if (!file_exists('config/database.php')) {
    echo "❌ config/database.php file is missing!<br>";
    exit;
}

// Load configuration
require_once 'config/database.php';

echo "<h2>Database Configuration:</h2>";
echo "<ul>";
echo "<li>DB_HOST: " . (defined('DB_HOST') ? DB_HOST : '❌ Not defined') . "</li>";
echo "<li>DB_NAME: " . (defined('DB_NAME') ? DB_NAME : '❌ Not defined') . "</li>";
echo "<li>DB_USER: " . (defined('DB_USER') ? DB_USER : '❌ Not defined') . "</li>";
echo "<li>DB_PASS: " . (defined('DB_PASS') ? '***' . substr(DB_PASS, -4) : '❌ Not defined') . "</li>";
echo "</ul>";

// Test database connection
echo "<h2>Database Connection Test:</h2>";
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful!<br>";
    
    // Check tables
    echo "<h2>Database Tables:</h2>";
    $tables = ['users', 'settings', 'password_resets'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ $table table exists<br>";
            
            // Count records
            $countStmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $countStmt->fetchColumn();
            echo "   - Records: $count<br>";
        } else {
            echo "❌ $table table missing<br>";
        }
    }
    
    // Test user creation
    echo "<h2>Test User Creation:</h2>";
    try {
        $testEmail = 'test_' . time() . '@example.com';
        $testPassword = password_hash('test123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, member_since, created_at) VALUES (?, ?, ?, ?, 'member', CURDATE(), NOW())");
        $result = $stmt->execute(['Test', 'User', $testEmail, $testPassword]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            echo "✅ Test user created successfully (ID: $userId)<br>";
            
            // Clean up test user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            echo "✅ Test user cleaned up<br>";
        } else {
            echo "❌ Failed to create test user<br>";
        }
    } catch (Exception $e) {
        echo "❌ User creation test failed: " . $e->getMessage() . "<br>";
    }
    
} catch(PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    echo "<h3>Common Solutions:</h3>";
    echo "<ul>";
    echo "<li>Check if MySQL/XAMPP is running</li>";
    echo "<li>Verify database credentials in config/database.php</li>";
    echo "<li>Make sure the database exists</li>";
    echo "<li>Check if the user has proper permissions</li>";
    echo "</ul>";
}

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>If database connection fails, check XAMPP/MySQL is running</li>";
echo "<li>If tables are missing, run install.php</li>";
echo "<li>If user creation fails, check table structure</li>";
echo "</ol>";

echo "<p><a href='login.php'>← Back to Login</a> | <a href='register.php'>← Back to Register</a></p>";
?>
