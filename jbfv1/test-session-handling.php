<?php
// Test session handling
session_start();

echo "<h2>Session Handling Test</h2>";

// Display current session ID
echo "<p>Session ID: " . session_id() . "</p>";

// Display CSRF token
echo "<p>CSRF Token: " . ($_SESSION['csrf_token'] ?? 'Not set') . "</p>";

// If CSRF token is not set, generate it
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    echo "<p>Generated new CSRF token: " . $_SESSION['csrf_token'] . "</p>";
} else {
    echo "<p>CSRF token already exists</p>";
}

// Test setting a session variable
$_SESSION['test_variable'] = 'Test Value';
echo "<p>Set session variable 'test_variable' to 'Test Value'</p>";

// Test retrieving a session variable
echo "<p>Retrieved session variable 'test_variable': " . ($_SESSION['test_variable'] ?? 'Not set') . "</p>";

// Test form submission
if ($_POST) {
    echo "<h3>POST Data:</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    // Check CSRF token
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo "<p>✓ CSRF token validation passed</p>";
    } else {
        echo "<p>✗ CSRF token validation failed</p>";
        echo "<p>Expected: " . $_SESSION['csrf_token'] . "</p>";
        echo "<p>Received: " . ($_POST['csrf_token'] ?? 'Not set') . "</p>";
    }
    
    // Test session variable persistence
    echo "<p>Session variable 'test_variable' after POST: " . ($_SESSION['test_variable'] ?? 'Not set') . "</p>";
}

// Test session variable persistence across requests
echo "<p>Session variable 'test_variable' at end of script: " . ($_SESSION['test_variable'] ?? 'Not set') . "</p>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Session Handling Test</title>
</head>
<body>
    <h2>Session Test Form</h2>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <p>
            <label>Test Field:</label>
            <input type="text" name="test_field" value="Test Value">
        </p>
        <p>
            <input type="submit" value="Submit">
        </p>
    </form>
    
    <h3>Session Data:</h3>
    <pre><?php print_r($_SESSION); ?></pre>
</body>
</html>