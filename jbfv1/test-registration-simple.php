<?php
// Simple test script to debug registration issues
require_once 'config/security.php';
require_once 'config/database.php';

echo "<h1>Registration Test</h1>";

// Test data
$testData = [
    'first_name' => 'Test',
    'last_name' => 'User',
    'email' => 'test' . time() . '@example.com',
    'password' => 'Test123!@#',
    'confirm_password' => 'Test123!@#',
    'phone' => '1234567890'
];

echo "<h2>Test Data:</h2>";
echo "<pre>" . print_r($testData, true) . "</pre>";

// Simulate form submission
$_POST = $testData;
$_POST['register_submit'] = true;

// CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_POST['csrf_token'] = $_SESSION['csrf_token'];

echo "<h2>Simulating Registration Process...</h2>";

// Include the registration logic (simplified)
require_once 'includes/form-validator.php';

// Rate limiting check
if (!checkRateLimit('registration', 3, 300)) {
    echo "<p>❌ Rate limit exceeded</p>";
    exit;
}

// Initialize validator
$validator = new FormValidator($_POST);

// Validate all fields
$validator->required('first_name', 'First Name');
$validator->required('last_name', 'Last Name');
$validator->required('email', 'Email Address');
$validator->required('password', 'Password');
$validator->required('confirm_password', 'Password Confirmation');
$validator->email('email', 'Email Address');
$validator->password('password', 'Password');
$validator->passwordConfirm('password', 'confirm_password', 'Password Confirmation');
$validator->minLength('first_name', 'First Name', 2);
$validator->maxLength('first_name', 'First Name', 50);
$validator->minLength('last_name', 'Last Name', 2);
$validator->maxLength('last_name', 'Last Name', 50);
$validator->pattern('first_name', 'First Name', ValidationPatterns::NAME, 'First name contains invalid characters');
$validator->pattern('last_name', 'Last Name', ValidationPatterns::NAME, 'Last name contains invalid characters');

// Phone validation (optional)
if (!empty($_POST['phone'])) {
    $validator->phone('phone', 'Phone Number');
}

if ($validator->isValid()) {
    echo "<p>✅ Form validation passed</p>";
    
    // Get sanitized data
    $data = $validator->getSanitizedData();
    echo "<h3>Sanitized Data:</h3>";
    echo "<pre>" . print_r($data, true) . "</pre>";
    
    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        
        if ($stmt->fetch()) {
            echo "<p>❌ Email already exists</p>";
        } else {
            echo "<p>✅ Email is unique</p>";
            
            // Hash password
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            echo "<p>✅ Password hashed</p>";
            
            // Insert new user
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, member_since, created_at) VALUES (?, ?, ?, ?, ?, 'member', CURDATE(), NOW())");
            
            if ($stmt->execute([$data['first_name'], $data['last_name'], $data['email'], $data['phone'] ?? '', $hashed_password])) {
                $user_id = $pdo->lastInsertId();
                echo "<p>✅ User created successfully with ID: $user_id</p>";
                
                // Clean up test user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                echo "<p>✅ Test user cleaned up</p>";
            } else {
                echo "<p>❌ Database insert failed</p>";
                echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
            }
        }
    } catch(PDOException $e) {
        echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ Form validation failed</p>";
    echo "<h3>Validation Errors:</h3>";
    echo "<pre>" . print_r($validator->getErrors(), true) . "</pre>";
}
?>