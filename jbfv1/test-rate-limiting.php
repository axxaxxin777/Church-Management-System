<?php
// Test rate limiting
session_start();
require_once 'config/security.php';

echo "<h2>Rate Limiting Test</h2>";

// Display current session ID
echo "<p>Session ID: " . session_id() . "</p>";

// Display remote address
echo "<p>Remote Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Not set') . "</p>";

// Test rate limiting for registration
echo "<h3>Registration Rate Limiting Test</h3>";

$attempts = 5;
for ($i = 0; $i < $attempts; $i++) {
    $result = checkRateLimit('registration', 3, 300); // 3 attempts per 5 minutes
    echo "<p>Attempt " . ($i + 1) . ": " . ($result ? "Allowed" : "Blocked") . "</p>";
    
    if (!$result) {
        echo "<p>Rate limiting is working for registration</p>";
        break;
    }
}

// Test rate limiting for login
echo "<h3>Login Rate Limiting Test</h3>";

$attempts = 7;
for ($i = 0; $i < $attempts; $i++) {
    $result = checkRateLimit('login', 5, 300); // 5 attempts per 5 minutes
    echo "<p>Attempt " . ($i + 1) . ": " . ($result ? "Allowed" : "Blocked") . "</p>";
    
    if (!$result) {
        echo "<p>Rate limiting is working for login</p>";
        break;
    }
}

// Test rate limiting for password reset
echo "<h3>Password Reset Rate Limiting Test</h3>";

$attempts = 5;
for ($i = 0; $i < $attempts; $i++) {
    $result = checkRateLimit('password_reset', 3, 600); // 3 attempts per 10 minutes
    echo "<p>Attempt " . ($i + 1) . ": " . ($result ? "Allowed" : "Blocked") . "</p>";
    
    if (!$result) {
        echo "<p>Rate limiting is working for password reset</p>";
        break;
    }
}

// Display current rate limit data
echo "<h3>Current Rate Limit Data</h3>";
echo "<pre>";
foreach ($_SESSION as $key => $value) {
    if (strpos($key, 'rate_limit_') === 0) {
        echo "$key: " . print_r($value, true) . "\n";
    }
}
echo "</pre>";

// Test resetting rate limit
echo "<h3>Reset Rate Limit Test</h3>";

// Wait for a moment to simulate time passing
echo "<p>Waiting 2 seconds to simulate time passing...</p>";
sleep(2);

// Reset rate limit for registration
$key = "rate_limit_registration_" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
unset($_SESSION[$key]);
echo "<p>Reset rate limit for registration</p>";

// Test again
$result = checkRateLimit('registration', 3, 300);
echo "<p>After reset - Registration attempt: " . ($result ? "Allowed" : "Blocked") . "</p>";

// Display final rate limit data
echo "<h3>Final Rate Limit Data</h3>";
echo "<pre>";
foreach ($_SESSION as $key => $value) {
    if (strpos($key, 'rate_limit_') === 0) {
        echo "$key: " . print_r($value, true) . "\n";
    }
}
echo "</pre>";
?>