# PHPMailer Manual Installation Guide

## Option 1: Download PHPMailer Manually (Recommended)

1. **Download PHPMailer** from GitHub:
   - Go to: https://github.com/PHPMailer/PHPMailer
   - Click "Code" → "Download ZIP"
   - Extract the ZIP file

2. **Copy PHPMailer files** to your project:
   ```
   JBFV1/
   ├── includes/
   │   └── PHPMailer/
   │       ├── PHPMailer.php
   │       ├── SMTP.php
   │       └── Exception.php
   ```

3. **File structure should look like:**
   ```
   JBFV1/
   ├── includes/
   │   ├── mail.php (already created)
   │   └── PHPMailer/
   │       ├── PHPMailer.php
   │       ├── SMTP.php
   │       └── Exception.php
   ```

## Option 2: Use Composer (Advanced Users)

If you have Composer installed:

```bash
cd JBFV1
composer require phpmailer/phpmailer
```

## Option 3: Use Basic Email (No Installation Required)

The system will automatically fall back to basic PHP `mail()` function if PHPMailer is not available.

## Testing Email Functionality

1. **Register a new user** at `register.php`
2. **Check if welcome email is sent**
3. **Check your email inbox** (and spam folder)

## Troubleshooting

### Email Not Sending?
- Check your Gmail app password is correct
- Verify SMTP settings in `config/database.php`
- Check XAMPP's mail settings
- Look for error messages in browser console

### PHPMailer Not Found?
- Verify PHPMailer files are in `includes/PHPMailer/` directory
- Check file permissions
- Ensure PHP can read the files

### Gmail SMTP Issues?
- Make sure 2-factor authentication is enabled
- Generate a new app password
- Check if Gmail allows "less secure app access"

## Current Status

✅ **Registration system working** - Users can register without errors
✅ **Database integration** - New users are saved to database
✅ **Fallback email system** - Basic email functionality available
🔄 **Advanced email** - PHPMailer integration ready when installed

## Next Steps

1. **Test registration** - Try creating a new account
2. **Install PHPMailer** - Follow Option 1 above for best email features
3. **Test email sending** - Verify welcome emails are received
4. **Customize email templates** - Modify `includes/mail.php` as needed

Your registration system is now fully functional! 🎉
