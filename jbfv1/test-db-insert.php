<?php
// Test database insertion
require_once 'config/database.php';

echo "<h2>Database Insertion Test</h2>";

try {
    // Test inserting a user
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, member_since, created_at) VALUES (?, ?, ?, ?, ?, CURDATE(), NOW())");
    
    $first_name = "Test";
    $last_name = "User";
    $email = "test" . time() . "@example.com";
    $password = password_hash("test123", PASSWORD_DEFAULT);
    $role = "member";
    
    if ($stmt->execute([$first_name, $last_name, $email, $password, $role])) {
        $user_id = $pdo->lastInsertId();
        echo "<p>✓ User inserted successfully with ID: $user_id</p>";
        
        // Test selecting the user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<p>✓ User selected successfully:</p>";
            echo "<pre>" . print_r($user, true) . "</pre>";
        } else {
            echo "<p>✗ Failed to select user</p>";
        }
        
        // Clean up - delete the test user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        echo "<p>✓ Test user cleaned up</p>";
    } else {
        echo "<p>✗ Failed to insert user</p>";
        echo "<p>Error info: " . print_r($stmt->errorInfo(), true) . "</p>";
    }
} catch(PDOException $e) {
    echo "<p>✗ Database error: " . $e->getMessage() . "</p>";
}

// Test password_resets table
echo "<h3>Password Resets Table Test</h3>";

try {
    // Test inserting a password reset token
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    
    $user_id = 1; // Use a known user ID
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    if ($stmt->execute([$user_id, $token, $expires])) {
        $reset_id = $pdo->lastInsertId();
        echo "<p>✓ Password reset token inserted successfully with ID: $reset_id</p>";
        
        // Test selecting the token
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE id = ?");
        $stmt->execute([$reset_id]);
        $reset = $stmt->fetch();
        
        if ($reset) {
            echo "<p>✓ Password reset token selected successfully:</p>";
            echo "<pre>" . print_r($reset, true) . "</pre>";
        } else {
            echo "<p>✗ Failed to select password reset token</p>";
        }
        
        // Clean up - delete the test token
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE id = ?");
        $stmt->execute([$reset_id]);
        echo "<p>✓ Test password reset token cleaned up</p>";
    } else {
        echo "<p>✗ Failed to insert password reset token</p>";
        echo "<p>Error info: " . print_r($stmt->errorInfo(), true) . "</p>";
    }
} catch(PDOException $e) {
    echo "<p>✗ Database error: " . $e->getMessage() . "</p>";
}
?>