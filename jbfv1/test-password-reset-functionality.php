<?php
// Test password reset functionality
require_once 'config/database.php';

echo "<h2>Password Reset Functionality Test</h2>";

try {
    // Test inserting a user for password reset
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, member_since, created_at) VALUES (?, ?, ?, ?, ?, CURDATE(), NOW())");
    
    $first_name = "Password";
    $last_name = "Reset";
    $email = "password" . time() . "@example.com";
    $password = password_hash("test123", PASSWORD_DEFAULT);
    $role = "member";
    
    if ($stmt->execute([$first_name, $last_name, $email, $password, $role])) {
        $user_id = $pdo->lastInsertId();
        echo "<p>✓ User inserted successfully with ID: $user_id</p>";
        
        // Test generating a reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        echo "<p>Generated reset token: $token</p>";
        echo "<p>Token expires at: $expires</p>";
        
        // Test inserting a password reset token
        $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        
        if ($stmt->execute([$user_id, $token, $expires])) {
            $reset_id = $pdo->lastInsertId();
            echo "<p>✓ Password reset token inserted successfully with ID: $reset_id</p>";
            
            // Test selecting the token
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();
            
            if ($reset) {
                echo "<p>✓ Password reset token selected successfully:</p>";
                echo "<pre>" . print_r($reset, true) . "</pre>";
                
                // Test updating user password
                $new_password = password_hash("newpassword123", PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                
                if ($stmt->execute([$new_password, $user_id])) {
                    echo "<p>✓ User password updated successfully</p>";
                    
                    // Test marking token as used
                    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                    
                    if ($stmt->execute([$reset_id])) {
                        echo "<p>✓ Password reset token marked as used</p>";
                    } else {
                        echo "<p>✗ Failed to mark password reset token as used</p>";
                    }
                } else {
                    echo "<p>✗ Failed to update user password</p>";
                }
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

// Test token validation
echo "<h3>Token Validation Test</h3>";

try {
    // Test inserting a token that expires in the past
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    
    $user_id = 1; // Use a known user ID
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('-1 hour')); // Expired token
    
    if ($stmt->execute([$user_id, $token, $expires])) {
        $reset_id = $pdo->lastInsertId();
        echo "<p>✓ Expired token inserted successfully with ID: $reset_id</p>";
        
        // Test selecting the expired token with validation
        $current_time = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > ?");
        $stmt->execute([$token, $current_time]);
        $reset = $stmt->fetch();
        
        if ($reset) {
            echo "<p>✗ Expired token was selected (this should not happen)</p>";
            echo "<pre>" . print_r($reset, true) . "</pre>";
        } else {
            echo "<p>✓ Expired token correctly rejected</p>";
        }
        
        // Clean up - delete the test token
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE id = ?");
        $stmt->execute([$reset_id]);
        echo "<p>✓ Expired token cleaned up</p>";
    } else {
        echo "<p>✗ Failed to insert expired token</p>";
        echo "<p>Error info: " . print_r($stmt->errorInfo(), true) . "</p>";
    }
} catch(PDOException $e) {
    echo "<p>✗ Database error: " . $e->getMessage() . "</p>";
}
?>