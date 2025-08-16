# Email Configuration Setup Guide

## Problem Summary
You're experiencing email sending failures with the error:
```
Warning: mail(): Failed to connect to mailserver at "localhost" port 25, verify your "SMTP" and "smtp_port" setting in php.ini or use ini_set()
```

This happens because the system is falling back to PHP's built-in `mail()` function when PHPMailer fails.

## Quick Fix Steps

### 1. Test Current Configuration
First, run the email configuration test:
```
http://your-domain/test-email-config.php
```

This will show you exactly what's wrong with your current setup.

### 2. Update Gmail App Password
If you're using Gmail, you need to generate a new app password:

1. Go to [Google App Passwords](https://myaccount.google.com/apppasswords)
2. Generate a new app password for "Mail"
3. Use the helper script: `http://your-domain/update-gmail-password.php`
4. Enter the new 16-character app password

### 3. Alternative: Use Different Email Provider
If Gmail continues to cause issues, consider using:
- **Outlook/Hotmail**: smtp-mail.outlook.com, Port 587
- **Yahoo**: smtp.mail.yahoo.com, Port 587
- **ProtonMail**: smtp.protonmail.ch, Port 587

## Detailed Troubleshooting

### Gmail Configuration Issues

#### Common Problems:
1. **2-Factor Authentication Not Enabled**: Gmail requires 2FA for app passwords
2. **Wrong Password**: Using regular password instead of app password
3. **Less Secure Apps**: This feature is deprecated and won't work
4. **Account Security**: Gmail may block suspicious login attempts

#### Solution Steps:
1. **Enable 2-Factor Authentication**:
   - Go to Google Account Settings
   - Security → 2-Step Verification
   - Enable if not already enabled

2. **Generate App Password**:
   - Go to [Google App Passwords](https://myaccount.google.com/apppasswords)
   - Select "Mail" or "Other (Custom name)"
   - Enter "Joy Bible Fellowship" as the name
   - Copy the 16-character password

3. **Update Configuration**:
   - Use the helper script: `update-gmail-password.php`
   - Or manually edit `config/database.php`

### Server Configuration Issues

#### Check PHP Configuration:
```php
// Add this to test-email-config.php to check PHP settings
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "smtp_port: " . ini_get('smtp_port') . "\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n";
```

#### XAMPP Specific Issues:
1. **Mercury Mail Server**: XAMPP includes Mercury mail server, but it's not configured by default
2. **Port 25 Blocked**: Many ISPs block port 25 for security reasons
3. **Firewall Issues**: Windows firewall may block outgoing SMTP connections

### Alternative Solutions

#### 1. Use External SMTP Service
Consider using services like:
- **SendGrid**: Free tier available
- **Mailgun**: Free tier available
- **Amazon SES**: Very cost-effective

#### 2. Configure Local Mail Server
If you want to use local mail server:
1. Install and configure Mercury Mail Server (included with XAMPP)
2. Configure it to relay through your ISP's SMTP server
3. Update PHP configuration to use local mail server

#### 3. Use PHP mail() with Proper Configuration
If you prefer to use PHP's built-in mail function:
1. Configure a local mail server (Mercury, Postfix, etc.)
2. Update php.ini with correct SMTP settings
3. Test with the mail server before using in production

## Updated Code Structure

The system now uses an enhanced mail helper (`includes/mail.php`) that provides:

1. **Better Error Handling**: More detailed error messages
2. **Fallback Options**: Falls back to simple mail if PHPMailer fails
3. **Debug Mode**: Can enable debug output for troubleshooting
4. **Consistent Interface**: Same API across all email functions

### Key Functions:
- `sendWelcomeEmail()`: For new user registration
- `sendPasswordResetEmail()`: For password reset requests
- `testEmailConfiguration()`: For testing email setup

## Testing Your Setup

### 1. Run Configuration Test
```
http://your-domain/test-email-config.php
```

### 2. Test Registration
Try registering a new user and check if welcome email is sent.

### 3. Test Password Reset
Try the forgot password feature and check if reset email is sent.

### 4. Check Error Logs
Look for email-related errors in:
- PHP error log
- Application logs (`logs/security.log`)
- Browser developer tools (Network tab)

## Common Error Messages and Solutions

### "PHPMailer not available"
**Solution**: Run `composer install` or `composer require phpmailer/phpmailer`

### "Authentication failed"
**Solution**: Check username/password, ensure using app password for Gmail

### "Connection timeout"
**Solution**: Check firewall settings, try different port (465 instead of 587)

### "SMTP connect() failed"
**Solution**: Check if SMTP port is blocked, try different email provider

## Security Considerations

1. **App Passwords**: Use app passwords instead of regular passwords
2. **Environment Variables**: Consider moving email credentials to environment variables
3. **Rate Limiting**: The system includes rate limiting for email functions
4. **Logging**: All email attempts are logged for security monitoring

## Next Steps

1. Run the email configuration test
2. Update your Gmail app password if needed
3. Test the registration and password reset functionality
4. Monitor the logs for any remaining issues

If you continue to have problems, the test script will provide specific error messages and troubleshooting steps.
