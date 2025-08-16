<?php
// Test password reset functionality
require_once 'config/database.php';

echo "<h2>Password Reset Functionality Test</h2>";

try {
    // Check if password_resets table exists and has proper structure
    echo "<h3>Password Resets Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE password_resets");
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $stmt->fetch()) {
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
    
    // Test token generation
    echo "<h3>Token Generation Test:</h3>";
    $token = bin2hex(random_bytes(32));
    echo "<p>Generated token: $token</p>";
    echo "<p>Token length: " . strlen($token) . " characters</p>";
    
    // Test token expiration
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    echo "<p>Token expires at: $expires</p>";
    
    // Test inserting a reset token
    echo "<h3>Insert Test Token:</h3>";
    // First, check if we have a user to test with
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch();
    
    if ($user) {
        $user_id = $user['id'];
        echo "<p>Using user ID: $user_id</p>";
        
        // Insert test token
        $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $token, $expires]);
        $reset_id = $pdo->lastInsertId();
        echo "<p>✓ Test token inserted successfully with ID: $reset_id</p>";
        
        // Test retrieving the token
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE id = ?");
        $stmt->execute([$reset_id]);
        $reset_data = $stmt->fetch();
        
        if ($reset_data) {
            echo "<p>✓ Token retrieval successful</p>";
            echo "<p>Token data: " . print_r($reset_data, true) . "</p>";
        } else {
            echo "<p>✗ Failed to retrieve token</p>";
        }
        
        // Clean up test token
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE id = ?");
        $stmt->execute([$reset_id]);
        echo "<p>✓ Test token cleaned up</p>";
    } else {
        echo "<p>No users found in database to test with</p>";
    }
    
} catch(PDOException $e) {
    echo "<p>✗ Database error: " . $e->getMessage() . "</p>";
}

echo "<h3>Reset Password Form Test:</h3>";
echo "<p>Testing if reset-password.php can properly validate tokens...</p>";

// Simulate a token validation
$test_token = bin2hex(random_bytes(32));
echo "<p>Test token: $test_token</p>";

// In a real scenario, this would be validated against the database
echo "<p>In a real scenario, reset-password.php would check if this token exists in the database and hasn't expired.</p>";
?>