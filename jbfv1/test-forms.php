<?php
// Form Test Script
echo "<h1>Form Test Script</h1>";

// Test POST data
if ($_POST) {
    echo "<h2>POST Data Received:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    // Test CSRF token
    if (isset($_POST['csrf_token'])) {
        echo "<p>✅ CSRF token received: " . substr($_POST['csrf_token'], 0, 10) . "...</p>";
    } else {
        echo "<p>❌ No CSRF token received</p>";
    }
    
    // Test form fields
    $requiredFields = ['email', 'password'];
    foreach ($requiredFields as $field) {
        if (isset($_POST[$field]) && !empty($_POST[$field])) {
            echo "<p>✅ $field field received: " . substr($_POST[$field], 0, 10) . "...</p>";
        } else {
            echo "<p>❌ $field field missing or empty</p>";
        }
    }
    
    // Test form submission type
    if (isset($_POST['login_submit'])) {
        echo "<p>✅ Login form submitted</p>";
    } elseif (isset($_POST['register_submit'])) {
        echo "<p>✅ Registration form submitted</p>";
    } elseif (isset($_POST['forgot_password'])) {
        echo "<p>✅ Forgot password form submitted</p>";
    } else {
        echo "<p>❌ Unknown form submission</p>";
    }
    
} else {
    echo "<h2>No POST Data Received</h2>";
    echo "<p>This means the form is not submitting properly.</p>";
}

// Test session
echo "<h2>Session Test:</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p>✅ Session is active</p>";
    if (isset($_SESSION['csrf_token'])) {
        echo "<p>✅ CSRF token in session: " . substr($_SESSION['csrf_token'], 0, 10) . "...</p>";
    } else {
        echo "<p>❌ No CSRF token in session</p>";
    }
} else {
    echo "<p>❌ Session is not active</p>";
}

// Test required files
echo "<h2>Required Files Test:</h2>";
$requiredFiles = [
    'config/security.php',
    'config/database.php',
    'includes/form-validator.php',
    'assets/js/toastify.js'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "<p>✅ $file exists</p>";
    } else {
        echo "<p>❌ $file missing</p>";
    }
}

// Test form HTML
echo "<h2>Form Test Links:</h2>";
echo "<p><a href='login.php'>🔐 Test Login Form</a></p>";
echo "<p><a href='register.php'>📝 Test Registration Form</a></p>";
echo "<p><a href='forgot-password.php'>🔑 Test Forgot Password Form</a></p>";

echo "<h2>Debug Links:</h2>";
echo "<p><a href='test-database.php'>🗄️ Test Database</a></p>";
echo "<p><a href='test-email.php'>📧 Test Email</a></p>";

echo "<h2>Common Issues:</h2>";
echo "<ul>";
echo "<li>If no POST data: Check form action and method</li>";
echo "<li>If no CSRF token: Check security.php is loaded</li>";
echo "<li>If session issues: Check session_start() is called</li>";
echo "<li>If database errors: Check database connection</li>";
echo "</ul>";

echo "<p><a href='index.php'>← Back to Home</a></p>";
?>
