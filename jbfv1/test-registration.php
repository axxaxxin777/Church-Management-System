<?php
// Test registration process
require_once 'config/security.php';
require_once 'config/database.php';
require_once 'includes/form-validator.php';

echo "<h2>Registration Process Debug</h2>";

// Simulate a registration form submission
$test_data = [
    'first_name' => 'Test',
    'last_name' => 'User',
    'email' => 'test@example.com',
    'phone' => '123-456-7890',
    'password' => 'Test123!',
    'confirm_password' => 'Test123!',
    'csrf_token' => generateCSRFToken()
];

echo "<h3>Test Data:</h3>";
echo "<pre>" . print_r($test_data, true) . "</pre>";

// Validate the data
$validator = new FormValidator($test_data);
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

if ($validator->isValid()) {
    echo "<p>✓ Form validation passed</p>";
    
    // Get sanitized data
    $data = $validator->getSanitizedData();
    echo "<h3>Sanitized Data:</h3>";
    echo "<pre>" . print_r($data, true) . "</pre>";
    
    // Check if email already exists
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        
        if ($stmt->fetch()) {
            echo "<p>✗ An account with this email already exists.</p>";
        } else {
            echo "<p>✓ Email is unique</p>";
            
            // Hash password
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            echo "<p>✓ Password hashed successfully</p>";
            
            // Insert new user
            echo "<p>Attempting to insert user into database...</p>";
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, member_since, created_at) VALUES (?, ?, ?, ?, ?, 'member', CURDATE(), NOW())");
            
            $result = $stmt->execute([$data['first_name'], $data['last_name'], $data['email'], $data['phone'] ?? '', $hashed_password]);
            
            if ($result) {
                $user_id = $pdo->lastInsertId();
                echo "<p>✓ User inserted successfully with ID: $user_id</p>";
            } else {
                echo "<p>✗ Failed to insert user into database</p>";
                echo "<p>Error info: " . print_r($stmt->errorInfo(), true) . "</p>";
            }
        }
    } catch(PDOException $e) {
        echo "<p>✗ Database error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>✗ Form validation failed</p>";
    echo "<p>Errors: " . print_r($validator->getErrors(), true) . "</p>";
}

echo "<h3>Database Users Table:</h3>";
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email FROM users");
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Email</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['first_name']}</td>";
        echo "<td>{$row['last_name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch(PDOException $e) {
    echo "<p>✗ Database query error: " . $e->getMessage() . "</p>";
}
?>