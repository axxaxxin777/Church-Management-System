<?php
/**
 * Email Configuration Test Script
 * This script will help diagnose and fix PHPMailer configuration issues
 */

// Load configuration
require_once 'config/database.php';
require_once 'includes/mail.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Email Configuration Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; background: #e8f5e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: red; background: #ffe8e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: blue; background: #e8f0ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .warning { color: orange; background: #fff8e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";

// Test 1: Check if configuration constants are defined
echo "<h2>1. Configuration Check</h2>";
$config_ok = true;

if (!defined('MAIL_HOST')) {
    echo "<div class='error'>❌ MAIL_HOST is not defined</div>";
    $config_ok = false;
} else {
    echo "<div class='success'>✅ MAIL_HOST: " . MAIL_HOST . "</div>";
}

if (!defined('MAIL_PORT')) {
    echo "<div class='error'>❌ MAIL_PORT is not defined</div>";
    $config_ok = false;
} else {
    echo "<div class='success'>✅ MAIL_PORT: " . MAIL_PORT . "</div>";
}

if (!defined('MAIL_USERNAME')) {
    echo "<div class='error'>❌ MAIL_USERNAME is not defined</div>";
    $config_ok = false;
} else {
    echo "<div class='success'>✅ MAIL_USERNAME: " . MAIL_USERNAME . "</div>";
}

if (!defined('MAIL_PASSWORD')) {
    echo "<div class='error'>❌ MAIL_PASSWORD is not defined</div>";
    $config_ok = false;
} else {
    echo "<div class='success'>✅ MAIL_PASSWORD: [HIDDEN]</div>";
}

if (!defined('MAIL_FROM_EMAIL')) {
    echo "<div class='error'>❌ MAIL_FROM_EMAIL is not defined</div>";
    $config_ok = false;
} else {
    echo "<div class='success'>✅ MAIL_FROM_EMAIL: " . MAIL_FROM_EMAIL . "</div>";
}

if (!defined('MAIL_FROM_NAME')) {
    echo "<div class='error'>❌ MAIL_FROM_NAME is not defined</div>";
    $config_ok = false;
} else {
    echo "<div class='success'>✅ MAIL_FROM_NAME: " . MAIL_FROM_NAME . "</div>";
}

// Test 2: Check if PHPMailer is available
echo "<h2>2. PHPMailer Availability Check</h2>";
$enhancedMail = new EnhancedMail();

if ($enhancedMail->isAvailable()) {
    echo "<div class='success'>✅ PHPMailer is available and loaded</div>";
} else {
    echo "<div class='error'>❌ PHPMailer is not available</div>";
    echo "<div class='info'>To install PHPMailer, run: <code>composer require phpmailer/phpmailer</code></div>";
}

// Test 3: Check Composer autoloader
echo "<h2>3. Composer Autoloader Check</h2>";
if (file_exists('vendor/autoload.php')) {
    echo "<div class='success'>✅ Composer autoloader exists</div>";
} else {
    echo "<div class='error'>❌ Composer autoloader not found</div>";
    echo "<div class='info'>Run: <code>composer install</code> to install dependencies</div>";
}

// Test 4: Check PHPMailer files
echo "<h2>4. PHPMailer Files Check</h2>";
$phpmailer_paths = [
    'vendor/phpmailer/phpmailer/src/PHPMailer.php',
    'vendor/phpmailer/phpmailer/src/SMTP.php',
    'vendor/phpmailer/phpmailer/src/Exception.php'
];

foreach ($phpmailer_paths as $path) {
    if (file_exists($path)) {
        echo "<div class='success'>✅ {$path}</div>";
    } else {
        echo "<div class='error'>❌ {$path} not found</div>";
    }
}

// Test 5: Test email sending (if configuration is ok)
if ($config_ok && $enhancedMail->isAvailable()) {
    echo "<h2>5. Email Sending Test</h2>";
    
    // Test with debug enabled
    $testEmail = MAIL_USERNAME; // Send to self for testing
    $subject = 'Email Configuration Test - ' . date('Y-m-d H:i:s');
    $message = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #6d4c41;'>Email Configuration Test</h2>
        <p>This is a test email to verify that your email configuration is working correctly.</p>
        <p>If you received this email, your PHPMailer configuration is working properly!</p>
        <p>Configuration details:</p>
        <ul>
            <li>SMTP Host: " . MAIL_HOST . "</li>
            <li>SMTP Port: " . MAIL_PORT . "</li>
            <li>Username: " . MAIL_USERNAME . "</li>
            <li>Test Time: " . date('Y-m-d H:i:s') . "</li>
        </ul>
        <p>Blessings,<br>The Joy Bible Fellowship Team</p>
    </div>";
    
    echo "<div class='info'>Attempting to send test email to: {$testEmail}</div>";
    
    $result = $enhancedMail->send($testEmail, $subject, $message, null, null, true);
    
    if ($result) {
        echo "<div class='success'>✅ Email sent successfully! Check your inbox.</div>";
    } else {
        echo "<div class='error'>❌ Email sending failed: " . $enhancedMail->getLastError() . "</div>";
        
        // Provide troubleshooting tips
        echo "<h3>Troubleshooting Tips:</h3>";
        echo "<div class='warning'>";
        echo "<h4>For Gmail:</h4>";
        echo "<ol>";
        echo "<li>Make sure 2-Factor Authentication is enabled on your Gmail account</li>";
        echo "<li>Generate an App Password: Go to Google Account Settings > Security > 2-Step Verification > App passwords</li>";
        echo "<li>Use the generated App Password instead of your regular password</li>";
        echo "<li>Make sure 'Less secure app access' is disabled (it's deprecated)</li>";
        echo "</ol>";
        
        echo "<h4>For Other Providers:</h4>";
        echo "<ol>";
        echo "<li>Check if your email provider requires specific SMTP settings</li>";
        echo "<li>Verify your username and password are correct</li>";
        echo "<li>Check if your provider blocks SMTP access</li>";
        echo "<li>Try using port 465 with SSL instead of port 587 with TLS</li>";
        echo "</ol>";
        echo "</div>";
    }
} else {
    echo "<h2>5. Email Sending Test</h2>";
    echo "<div class='warning'>⚠️ Skipping email test due to configuration issues above</div>";
}

// Test 6: Check PHP mail() function
echo "<h2>6. PHP mail() Function Check</h2>";
if (function_exists('mail')) {
    echo "<div class='success'>✅ PHP mail() function is available</div>";
} else {
    echo "<div class='error'>❌ PHP mail() function is not available</div>";
}

// Test 7: Check SMTP settings
echo "<h2>7. SMTP Settings Recommendations</h2>";
echo "<div class='info'>";
echo "<h4>Recommended Gmail Settings:</h4>";
echo "<pre>";
echo "MAIL_HOST = 'smtp.gmail.com'\n";
echo "MAIL_PORT = 587\n";
echo "MAIL_SMTPSECURE = 'tls'\n";
echo "MAIL_SMTPAUTH = true\n";
echo "</pre>";

echo "<h4>Alternative Gmail Settings (if port 587 doesn't work):</h4>";
echo "<pre>";
echo "MAIL_HOST = 'smtp.gmail.com'\n";
echo "MAIL_PORT = 465\n";
echo "MAIL_SMTPSECURE = 'ssl'\n";
echo "MAIL_SMTPAUTH = true\n";
echo "</pre>";
echo "</div>";

// Test 8: Check current configuration
echo "<h2>8. Current Configuration Summary</h2>";
echo "<div class='info'>";
echo "<pre>";
echo "Current Settings:\n";
echo "Host: " . (defined('MAIL_HOST') ? MAIL_HOST : 'NOT SET') . "\n";
echo "Port: " . (defined('MAIL_PORT') ? MAIL_PORT : 'NOT SET') . "\n";
echo "Username: " . (defined('MAIL_USERNAME') ? MAIL_USERNAME : 'NOT SET') . "\n";
echo "From Email: " . (defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'NOT SET') . "\n";
echo "From Name: " . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'NOT SET') . "\n";
echo "</pre>";
echo "</div>";

echo "<h2>9. Next Steps</h2>";
if (!$config_ok) {
    echo "<div class='error'>❌ Fix configuration issues first</div>";
} elseif (!$enhancedMail->isAvailable()) {
    echo "<div class='error'>❌ Install PHPMailer: <code>composer require phpmailer/phpmailer</code></div>";
} else {
    echo "<div class='success'>✅ Configuration looks good! Try the email test above.</div>";
}

echo "<div class='info'>";
echo "<h4>If you're still having issues:</h4>";
echo "<ol>";
echo "<li>Check your Gmail account settings and app passwords</li>";
echo "<li>Try using a different email provider</li>";
echo "<li>Check your server's firewall settings</li>";
echo "<li>Contact your hosting provider about SMTP restrictions</li>";
echo "</ol>";
echo "</div>";

echo "<hr>";
echo "<p><small>Test completed at: " . date('Y-m-d H:i:s') . "</small></p>";
?>