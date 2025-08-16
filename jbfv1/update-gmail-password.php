<?php
/**
 * Gmail App Password Update Helper
 * This script helps you update your Gmail app password in the configuration
 */

// Load current configuration
require_once 'config/database.php';

echo "<h1>Gmail App Password Update Helper</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; background: #e8f5e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: red; background: #ffe8e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: blue; background: #e8f0ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .warning { color: orange; background: #fff8e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .form-group { margin: 15px 0; }
    label { display: block; margin-bottom: 5px; font-weight: bold; }
    input[type='text'], input[type='password'] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
    button { background: #6d4c41; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
    button:hover { background: #5d3f35; }
    .steps { background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0; }
</style>";

// Handle form submission
if ($_POST && isset($_POST['update_password'])) {
    $new_password = trim($_POST['new_password']);
    
    if (empty($new_password)) {
        echo "<div class='error'>❌ Please enter a valid app password</div>";
    } else {
        // Read the current database.php file
        $config_file = 'config/database.php';
        $config_content = file_get_contents($config_file);
        
        // Update the password
        $old_password = MAIL_PASSWORD;
        $new_config_content = str_replace(
            "define('MAIL_PASSWORD', '$old_password');",
            "define('MAIL_PASSWORD', '$new_password');",
            $config_content
        );
        
        // Write the updated configuration
        if (file_put_contents($config_file, $new_config_content)) {
            echo "<div class='success'>✅ Gmail app password updated successfully!</div>";
            echo "<div class='info'>You can now test the email configuration using the test script.</div>";
        } else {
            echo "<div class='error'>❌ Failed to update configuration file. Please check file permissions.</div>";
        }
    }
}

// Display current configuration
echo "<h2>Current Email Configuration</h2>";
echo "<div class='info'>";
echo "<p><strong>SMTP Host:</strong> " . MAIL_HOST . "</p>";
echo "<p><strong>SMTP Port:</strong> " . MAIL_PORT . "</p>";
echo "<p><strong>Username:</strong> " . MAIL_USERNAME . "</p>";
echo "<p><strong>From Email:</strong> " . MAIL_FROM_EMAIL . "</p>";
echo "<p><strong>From Name:</strong> " . MAIL_FROM_NAME . "</p>";
echo "</div>";

// Display instructions
echo "<h2>How to Generate a Gmail App Password</h2>";
echo "<div class='steps'>";
echo "<ol>";
echo "<li><strong>Enable 2-Factor Authentication:</strong> Go to your Google Account settings and enable 2-Step Verification if not already enabled.</li>";
echo "<li><strong>Generate App Password:</strong> Go to <a href='https://myaccount.google.com/apppasswords' target='_blank'>Google App Passwords</a></li>";
echo "<li><strong>Select App:</strong> Choose 'Mail' or 'Other (Custom name)' and enter 'Joy Bible Fellowship'</li>";
echo "<li><strong>Copy Password:</strong> Google will generate a 16-character password. Copy it.</li>";
echo "<li><strong>Update Configuration:</strong> Paste the password in the form below and click 'Update Password'</li>";
echo "</ol>";
echo "</div>";

// Display form
echo "<h2>Update Gmail App Password</h2>";
echo "<form method='post'>";
echo "<div class='form-group'>";
echo "<label for='new_password'>New Gmail App Password (16 characters):</label>";
echo "<input type='password' id='new_password' name='new_password' placeholder='Enter your Gmail app password' required>";
echo "</div>";
echo "<button type='submit' name='update_password'>Update Password</button>";
echo "</form>";

echo "<h2>Alternative Email Providers</h2>";
echo "<div class='info'>";
echo "<p>If Gmail continues to cause issues, you can use other email providers:</p>";
echo "<ul>";
echo "<li><strong>Outlook/Hotmail:</strong> smtp-mail.outlook.com, Port 587, TLS</li>";
echo "<li><strong>Yahoo:</strong> smtp.mail.yahoo.com, Port 587, TLS</li>";
echo "<li><strong>ProtonMail:</strong> smtp.protonmail.ch, Port 587, TLS</li>";
echo "<li><strong>Zoho:</strong> smtp.zoho.com, Port 587, TLS</li>";
echo "</ul>";
echo "</div>";

echo "<h2>Test Your Configuration</h2>";
echo "<div class='info'>";
echo "<p>After updating your password, test the email configuration:</p>";
echo "<p><a href='test-email-config.php' style='background: #6d4c41; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Run Email Configuration Test</a></p>";
echo "</div>";

echo "<hr>";
echo "<p><small>Last updated: " . date('Y-m-d H:i:s') . "</small></p>";
?>
