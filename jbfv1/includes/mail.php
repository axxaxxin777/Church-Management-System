<?php
/**
 * Enhanced Mail Helper for Joy Bible Fellowship
 * Provides robust email functionality with PHPMailer and fallback options
 */

class SimpleMail {
    private $to;
    private $subject;
    private $message;
    private $headers;
    
    public function __construct() {
        $this->headers = array();
    }
    
    public function setTo($email, $name = '') {
        $this->to = $name ? "$name <$email>" : $email;
        return $this;
    }
    
    public function setSubject($subject) {
        $this->subject = $subject;
        return $this;
    }
    
    public function setMessage($message, $isHtml = false) {
        if ($isHtml) {
            $this->message = $message;
            $this->headers[] = 'MIME-Version: 1.0';
            $this->headers[] = 'Content-type: text/html; charset=UTF-8';
        } else {
            $this->message = $message;
        }
        return $this;
    }
    
    public function setFrom($email, $name = '') {
        $from = $name ? "$name <$email>" : $email;
        $this->headers[] = "From: $from";
        $this->headers[] = "Reply-To: $email";
        return $this;
    }
    
    public function send() {
        if (empty($this->to) || empty($this->subject) || empty($this->message)) {
            return false;
        }
        
        $headers = implode("\r\n", $this->headers);
        
        return mail($this->to, $this->subject, $this->message, $headers);
    }
}

/**
 * Enhanced Mail Helper using PHPMailer
 */
class EnhancedMail {
    private $mailer;
    private $isAvailable = false;
    private $lastError = '';
    
    public function __construct() {
        $this->isAvailable = $this->loadPHPMailer();
    }
    
    private function loadPHPMailer() {
        // Check if Composer autoloader exists
        if (file_exists('vendor/autoload.php')) {
            require_once 'vendor/autoload.php';
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return true;
            }
        }
        
        // Try alternative paths
        $possiblePaths = [
            'PHPMailer/PHPMailer/PHPMailer.php',
            'includes/PHPMailer/PHPMailer.php',
            'lib/PHPMailer/PHPMailer.php'
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    public function isAvailable() {
        return $this->isAvailable;
    }
    
    public function getLastError() {
        return $this->lastError;
    }
    
    public function send($to, $subject, $message, $from = null, $fromName = null, $debug = false) {
        if (!$this->isAvailable) {
            $this->lastError = 'PHPMailer not available';
            return false;
        }
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = MAIL_PORT;
            
            // Enable debug output if requested
            if ($debug) {
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = 'error_log';
            } else {
                $mail->SMTPDebug = 0;
            }
            
            // Set timeout
            $mail->Timeout = 30;
            $mail->SMTPKeepAlive = true;
            
            // Set from address
            if ($from) {
                $mail->setFrom($from, $fromName ?: MAIL_FROM_NAME);
            } else {
                $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            }
            
            // Add recipient
            $mail->addAddress($to);
            
            // Set content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
            
            // Send email
            $result = $mail->send();
            $this->lastError = '';
            return $result;
            
        } catch (Exception $e) {
            $this->lastError = "PHPMailer error: " . $e->getMessage();
            error_log("PHPMailer error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Send welcome email to new user
 */
function sendWelcomeEmail($email, $firstName, $lastName) {
    $enhancedMail = new EnhancedMail();
    
    if ($enhancedMail->isAvailable()) {
        $subject = 'Welcome to Joy Bible Fellowship!';
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #6d4c41;'>Welcome to Joy Bible Fellowship!</h2>
            <p>Dear {$firstName},</p>
            <p>Thank you for joining our fellowship! Your account has been successfully created.</p>
            <p>You can now:</p>
            <ul>
                <li>Access member resources</li>
                <li>Register for events</li>
                <li>Submit prayer requests</li>
                <li>View sermons and updates</li>
            </ul>
            <p>To get started, please <a href='http://{$_SERVER['HTTP_HOST']}/login.php'>login to your account</a>.</p>
            <p>If you have any questions, please don't hesitate to contact us.</p>
            <p>Blessings,<br>The Joy Bible Fellowship Team</p>
        </div>";
        
        return $enhancedMail->send($email, $subject, $message);
    }
    
    // Fallback to simple mail
    $simpleMail = new SimpleMail();
    $simpleMail->setTo($email, $firstName . ' ' . $lastName)
               ->setSubject('Welcome to Joy Bible Fellowship!')
               ->setMessage("Dear {$firstName},\n\nThank you for joining our fellowship! Your account has been successfully created.\n\nYou can now login at: http://{$_SERVER['HTTP_HOST']}/login.php\n\nBlessings,\nThe Joy Bible Fellowship Team")
               ->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    
    return $simpleMail->send();
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $firstName, $resetLink) {
    $enhancedMail = new EnhancedMail();
    
    if ($enhancedMail->isAvailable()) {
        $subject = 'Password Reset Request - Joy Bible Fellowship';
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #6d4c41;'>Password Reset Request</h2>
            <p>Dear {$firstName},</p>
            <p>We received a request to reset your password for your Joy Bible Fellowship account.</p>
            <p>Click the button below to reset your password:</p>
            <div style='text-align: center; margin: 2rem 0;'>
                <a href='{$resetLink}' style='background-color: #6d4c41; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
            </div>
            <p>If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
            <p>This link will expire in 1 hour for security reasons.</p>
            <p>If the button doesn't work, copy and paste this link into your browser:</p>
            <p style='word-break: break-all; color: #666;'>{$resetLink}</p>
            <p>Blessings,<br>The Joy Bible Fellowship Team</p>
        </div>";
        
        return $enhancedMail->send($email, $subject, $message);
    }
    
    // Fallback to simple mail
    $simpleMail = new SimpleMail();
    $simpleMail->setTo($email, $firstName)
               ->setSubject('Password Reset Request - Joy Bible Fellowship')
               ->setMessage("Dear {$firstName},\n\nWe received a request to reset your password for your Joy Bible Fellowship account.\n\nClick the link below to reset your password:\n{$resetLink}\n\nIf you didn't request this password reset, please ignore this email.\n\nThis link will expire in 1 hour for security reasons.\n\nBlessings,\nThe Joy Bible Fellowship Team", true)
               ->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    
    return $simpleMail->send();
}

/**
 * Test email configuration
 */
function testEmailConfiguration() {
    $enhancedMail = new EnhancedMail();
    
    if (!$enhancedMail->isAvailable()) {
        return [
            'success' => false,
            'message' => 'PHPMailer is not available. Please run: composer require phpmailer/phpmailer'
        ];
    }
    
    // Test with debug enabled
    $testEmail = MAIL_USERNAME; // Send to self for testing
    $subject = 'Email Configuration Test - Joy Bible Fellowship';
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
        </ul>
        <p>Blessings,<br>The Joy Bible Fellowship Team</p>
    </div>";
    
    $result = $enhancedMail->send($testEmail, $subject, $message, null, null, true);
    
    if ($result) {
        return [
            'success' => true,
            'message' => 'Email configuration test successful! Check your inbox.'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Email configuration test failed: ' . $enhancedMail->getLastError()
        ];
    }
}
?>
