<?php
// Simple test script to debug login issues
require_once 'config/security.php';
require_once 'config/database.php';

echo "<h1>Login Test</h1>";

// First, create a test user to login with
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
    
    // Now test login
    echo "<h2>Testing Login...</h2>";
    
    // Test data
    $loginData = [
        'email' => $testEmail,
        'password' => $testPassword
    ];
    
    echo "<h3>Login Data:</h3>";
    echo "<pre>" . print_r($loginData, true) . "</pre>";
    
    // Simulate form submission
    $_POST = $loginData;
    $_POST['login_submit'] = true;
    
    // CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_POST['csrf_token'] = $_SESSION['csrf_token'];
    
    echo "<h3>Simulating Login Process...</h3>";
    
    // Include the login logic (simplified)
    require_once 'includes/form-validator.php';
    
    // Rate limiting check
    if (!checkRateLimit('login', 5, 300)) {
        echo "<p>❌ Rate limit exceeded</p>";
        // Clean up and exit
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        exit;
    }
    
    // Initialize validator
    $validator = new FormValidator($_POST);
    
    // Validate fields
    $validator->required('email', 'Email Address');
    $validator->required('password', 'Password');
    $validator->email('email', 'Email Address');
    
    if ($validator->isValid()) {
        echo "<p>✅ Form validation passed</p>";
        
        // Get sanitized data
        $data = $validator->getSanitizedData();
        echo "<h3>Sanitized Data:</h3>";
        echo "<pre>" . print_r($data, true) . "</pre>";
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo "<p>✅ User found in database</p>";
                echo "<h3>User Data:</h3>";
                echo "<pre>" . print_r($user, true) . "</pre>";
                
                if (password_verify($data['password'], $user['password'])) {
                    echo "<p>✅ Password verification successful</p>";
                    echo "<p>✅ Login would be successful</p>";
                } else {
                    echo "<p>❌ Password verification failed</p>";
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
    
    // Clean up test user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    echo "<p>✅ Test user cleaned up</p>";
    
} catch(PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}
?>