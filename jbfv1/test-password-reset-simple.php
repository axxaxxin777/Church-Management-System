<?php
// Simple test script to debug password reset issues
require_once 'config/security.php';
require_once 'config/database.php';

echo "<h1>Password Reset Test</h1>";

// First, create a test user
echo "<h2>Creating Test User...</h2>";

$testEmail = 'test' . time() . '@example.com';
$testPassword = 'Test123!@#';

// Hash password
$hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);

try {
    // Insert test user
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, member_since, created_at) VALUES (?, ?, ?, ?, 'member', CURDATE(), NOW())");
    $stmt->execute(['Test', 'User', $testEmail, $hashedPassword]);
    $userId = $pdo->lastInsertId();
    echo "<p>✅ Test user created with ID: $userId</p>";
    
    // Now test password reset request
    echo "<h2>Testing Password Reset Request...</h2>";
    
    // Test data
    $resetData = [
        'email' => $testEmail
    ];
    
    echo "<h3>Reset Data:</h3>";
    echo "<pre>" . print_r($resetData, true) . "</pre>";
    
    // Simulate form submission
    $_POST = $resetData;
    $_POST['forgot_password'] = true;
    
    // CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_POST['csrf_token'] = $_SESSION['csrf_token'];
    
    echo "<h3>Simulating Password Reset Request...</h3>";
    
    // Include the password reset logic (simplified)
    require_once 'includes/form-validator.php';
    
    // Rate limiting check
    if (!checkRateLimit('password_reset', 3, 600)) {
        echo "<p>❌ Rate limit exceeded</p>";
        // Clean up and exit
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        exit;
    }
    
    // Initialize validator
    $validator = new FormValidator($_POST);
    
    // Validate email
    $validator->required('email', 'Email Address');
    $validator->email('email', 'Email Address');
    
    if ($validator->isValid()) {
        echo "<p>✅ Form validation passed</p>";
        
        // Get sanitized data
        $data = $validator->getSanitizedData();
        echo "<h3>Sanitized Data:</h3>";
        echo "<pre>" . print_r($data, true) . "</pre>";
        
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo "<p>✅ User found in database</p>";
                echo "<h3>User Data:</h3>";
                echo "<pre>" . print_r($user, true) . "</pre>";
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                echo "<p>✅ Reset token generated: $token</p>";
                echo "<p>✅ Token expires at: $expires</p>";
                
                // Store reset token in database
                $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                if ($stmt->execute([$user['id'], $token, $expires])) {
                    echo "<p>✅ Reset token stored in database</p>";
                    
                    // Test token verification
                    echo "<h2>Testing Token Verification...</h2>";
                    
                    $current_time = date('Y-m-d H:i:s');
                    $stmt = $pdo->prepare("
                        SELECT pr.user_id, pr.expires_at, u.first_name, u.last_name, u.email 
                        FROM password_resets pr 
                        JOIN users u ON pr.user_id = u.id 
                        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > ?
                    ");
                    $stmt->execute([$token, $current_time]);
                    $reset_data = $stmt->fetch();
                    
                    if ($reset_data) {
                        echo "<p>✅ Token verification successful</p>";
                        echo "<h3>Reset Data:</h3>";
                        echo "<pre>" . print_r($reset_data, true) . "</pre>";
                        
                        // Test password reset
                        echo "<h2>Testing Password Reset...</h2>";
                        
                        $newPassword = 'NewPass123!@#';
                        $confirmPassword = 'NewPass123!@#';
                        
                        if ($newPassword === $confirmPassword) {
                            echo "<p>✅ Passwords match</p>";
                            
                            // Hash the new password
                            $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
                            echo "<p>✅ New password hashed</p>";
                            
                            // Update user's password
                            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                            if ($stmt->execute([$hashed_password, $user['id']])) {
                                echo "<p>✅ User password updated successfully</p>";
                                
                                // Mark reset token as used
                                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                                if ($stmt->execute([$token])) {
                                    echo "<p>✅ Reset token marked as used</p>";
                                    echo "<p>✅ Password reset completed successfully</p>";
                                } else {
                                    echo "<p>❌ Failed to mark reset token as used</p>";
                                }
                            } else {
                                echo "<p>❌ Failed to update user password</p>";
                            }
                        } else {
                            echo "<p>❌ Passwords do not match</p>";
                        }
                    } else {
                        echo "<p>❌ Token verification failed</p>";
                    }
                } else {
                    echo "<p>❌ Failed to store reset token in database</p>";
                }
            } else {
                echo "<p>❌ User not found in database</p>";
            }
        } catch(PDOException $e) {
            echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>❌ Form validation failed</p>";
        echo "<h3>Validation Errors:</h3>";
        echo "<pre>" . print_r($validator->getErrors(), true) . "</pre>";
    }
    
    // Clean up test user and reset token
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    echo "<p>✅ Test data cleaned up</p>";
    
} catch(PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}
?>