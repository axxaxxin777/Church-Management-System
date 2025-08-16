<?php
// Script to add the missing password_resets table
require_once 'config/database.php';

echo "<h2>Adding password_resets table</h2>";

try {
    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_resets'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ password_resets table already exists</p>";
    } else {
        // Create the password_resets table
        $sql = "
        CREATE TABLE password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) UNIQUE NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $pdo->exec($sql);
        echo "<p>✅ password_resets table created successfully</p>";
    }
    
    // Verify the table was created
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_resets'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ Verification: password_resets table exists</p>";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE password_resets");
        echo "<h3>Table structure:</h3>";
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
        
        echo "<p style='color: green; font-weight: bold;'>🎉 Password reset functionality is now ready!</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Failed to create password_resets table</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='check-database.php'>Check Database Status</a></p>";
echo "<p><a href='forgot-password.php'>Test Forgot Password</a></p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
?>
