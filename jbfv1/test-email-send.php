<?php
// Test email sending
require_once 'config/database.php';

echo "<h2>Email Sending Test</h2>";

try {
    // Check if PHPMailer is available
    if (!file_exists('vendor/autoload.php')) {
        throw new Exception('PHPMailer not installed. Please run: composer require phpmailer/phpmailer');
    }
    
    require_once 'vendor/autoload.php';
    
    // Debug: Log email configuration
    echo "<p>Email Config: Host=" . MAIL_HOST . ", Username=" . MAIL_USERNAME . ", Port=" . MAIL_PORT . "</p>";
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = MAIL_PORT;
    
    // Enable debug output
    $mail->SMTPDebug = 2; // Set to 2 for debugging
    $mail->Debugoutput = 'echo';
    
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Joy Bible Fellowship';
    
    $mail->Body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #6d4c41;'>Test Email</h2>
        <p>This is a test email from Joy Bible Fellowship system.</p>
        <p>If you received this email, the email configuration is working properly.</p>
        <p>Blessings,<br>The Joy Bible Fellowship Team</p>
    </div>";
    
    // Send email
    if ($mail->send()) {
        echo "<p>✓ Test email sent successfully!</p>";
    } else {
        echo "<p>✗ Failed to send test email: " . $mail->ErrorInfo . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>✗ Email test failed: " . $e->getMessage() . "</p>";
    
    // Try fallback email method
    try {
        $to = MAIL_FROM_EMAIL;
        $subject = 'Test Email from Joy Bible Fellowship (Fallback)';
        $headers = "From: " . MAIL_FROM_EMAIL . "\r\n";
        $headers .= "Reply-To: " . MAIL_FROM_EMAIL . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $message_body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #6d4c41;'>Test Email</h2>
            <p>This is a test email from Joy Bible Fellowship system.</p>
            <p>If you received this email, the email configuration is working properly with the fallback method.</p>
            <p>Blessings,<br>The Joy Bible Fellowship Team</p>
        </div>";
        
        if (mail($to, $subject, $message_body, $headers)) {
            echo "<p>✓ Test email sent successfully using fallback method!</p>";
        } else {
            throw new Exception('Fallback email method also failed');
        }
    } catch (Exception $fallback_e) {
        echo "<p>✗ Fallback email test also failed: " . $fallback_e->getMessage() . "</p>";
    }
}
?>