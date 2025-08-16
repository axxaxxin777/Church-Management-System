<?php
// Email Configuration Test Script
// This script helps debug email configuration issues

echo "<h1>Email Configuration Test</h1>";

// Check if required files exist
echo "<h2>File Checks:</h2>";
echo "<ul>";
echo "<li>config/database.php: " . (file_exists('config/database.php') ? '✅ Exists' : '❌ Missing') . "</li>";
echo "<li>vendor/autoload.php: " . (file_exists('vendor/autoload.php') ? '✅ Exists' : '❌ Missing') . "</li>";
echo "<li>config/security.php: " . (file_exists('config/security.php') ? '✅ Exists' : '❌ Missing') . "</li>";
echo "</ul>";

// Load configuration
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
    
    echo "<h2>Email Configuration:</h2>";
    echo "<ul>";
    echo "<li>MAIL_HOST: " . (defined('MAIL_HOST') ? MAIL_HOST : '❌ Not defined') . "</li>";
    echo "<li>MAIL_USERNAME: " . (defined('MAIL_USERNAME') ? MAIL_USERNAME : '❌ Not defined') . "</li>";
    echo "<li>MAIL_PASSWORD: " . (defined('MAIL_PASSWORD') ? '***' . substr(MAIL_PASSWORD, -4) : '❌ Not defined') . "</li>";
    echo "<li>MAIL_PORT: " . (defined('MAIL_PORT') ? MAIL_PORT : '❌ Not defined') . "</li>";
    echo "<li>MAIL_FROM_EMAIL: " . (defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : '❌ Not defined') . "</li>";
    echo "<li>MAIL_FROM_NAME: " . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : '❌ Not defined') . "</li>";
    echo "</ul>";
    
    // Test database connection
    echo "<h2>Database Connection:</h2>";
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ Database connection successful<br>";
        
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Users table exists<br>";
            
            // Check if password_resets table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'password_resets'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Password resets table exists<br>";
            } else {
                echo "❌ Password resets table missing<br>";
            }
        } else {
            echo "❌ Users table missing<br>";
        }
        
    } catch(PDOException $e) {
        echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    }
    
    // Test PHPMailer
    echo "<h2>PHPMailer Test:</h2>";
    if (file_exists('vendor/autoload.php')) {
        try {
            require_once 'vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            echo "✅ PHPMailer class loaded successfully<br>";
            
            // Test SMTP configuration
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = MAIL_PORT;
            
            echo "✅ SMTP configuration set<br>";
            
        } catch (Exception $e) {
            echo "❌ PHPMailer error: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ PHPMailer not installed. Run: composer require phpmailer/phpmailer<br>";
    }
    
} else {
    echo "❌ Cannot load configuration file<br>";
}

echo "<h2>PHP Configuration:</h2>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>mail() function: " . (function_exists('mail') ? '✅ Available' : '❌ Not available') . "</li>";
echo "<li>OpenSSL: " . (extension_loaded('openssl') ? '✅ Loaded' : '❌ Not loaded') . "</li>";
echo "<li>cURL: " . (extension_loaded('curl') ? '✅ Loaded' : '❌ Not loaded') . "</li>";
echo "</ul>";

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>If PHPMailer is missing, run: <code>composer require phpmailer/phpmailer</code></li>";
echo "<li>Check your email credentials in config/database.php</li>";
echo "<li>Verify your Gmail app password is correct</li>";
echo "<li>Check if your hosting provider allows SMTP connections</li>";
echo "<li>Look at error logs for specific error messages</li>";
echo "</ol>";

echo "<p><a href='forgot-password.php'>← Back to Forgot Password</a></p>";
?>
