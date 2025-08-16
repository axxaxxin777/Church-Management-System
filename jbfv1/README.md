# Joy Bible Fellowship - Church Management System

A complete, modern church management system built with PHP, MySQL, and real-time features using Pusher.js. This system provides a beautiful landing page, member dashboard, and admin panel for managing church operations.

## Features

### 🏠 Landing Page
- Dynamic content management
- Responsive design with modern UI
- Contact form with email integration
- Prayer request submission
- Real-time notifications
- Embedded sermon videos
- Event management

### 👥 Member Area
- User registration and authentication
- Personal dashboard with statistics
- Event registration and management
- Prayer request submission and tracking
- Sermon library access
- Profile management

### ⚙️ Admin Panel
- User management
- Event creation and management
- Sermon upload and management
- Prayer request moderation
- Contact message management
- System settings configuration

### 🔔 Real-time Features
- Live prayer request notifications
- Real-time updates using Pusher.js
- Instant messaging system

## System Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Server**: Apache/Nginx
- **Extensions**: PDO MySQL, cURL
- **Browser**: Modern browsers with JavaScript enabled

## Installation

### 1. Download and Extract
```bash
# Download the project files to your web server directory
# Extract to your web root (e.g., /var/www/html/ or htdocs/)
```

### 2. Run Installation Script
1. Navigate to your project directory in a web browser
2. Run `install.php` (e.g., `http://yoursite.com/install.php`)
3. Fill in your database credentials:
   - **Database Host**: Usually `localhost`
   - **Database Username**: Your MySQL username
   - **Database Password**: Your MySQL password
   - **Database Name**: Choose a name (e.g., `grace_community`)
4. Click "Install System"

### 3. Configure External Services

#### Pusher.js (Real-time Features)
1. Sign up at [pusher.com](https://pusher.com)
2. Create a new app
3. Update `config/database.php` with your Pusher credentials:
```php
define('PUSHER_APP_ID', 'your_app_id');
define('PUSHER_KEY', 'your_key');
define('PUSHER_SECRET', 'your_secret');
define('PUSHER_CLUSTER', 'your_cluster');
```

#### PHPMailer (Email Features)
1. Update `config/database.php` with your email settings:
```php
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your_email@gmail.com');
define('MAIL_PASSWORD', 'your_app_password');
define('MAIL_FROM_NAME', 'Joy Bible Fellowship');
define('MAIL_FROM_EMAIL', 'noreply@joybiblefellowship.org');
```

### 4. Security
- **Delete** `install.php` after successful installation
- Set proper file permissions (755 for directories, 644 for files)
- Ensure `config/` directory is not publicly accessible

## Default Login Credentials

After installation, you can login with:

- **Email**: `admin@joybiblefellowship.org`
- **Password**: `admin123`

**Important**: Change these credentials immediately after first login!

## File Structure

```
joy-bible-fellowship/
├── admin/                 # Admin panel files
│   ├── index.php         # Admin dashboard
│   ├── users.php         # User management
│   ├── events.php        # Event management
│   ├── sermons.php       # Sermon management
│   └── settings.php      # System settings
├── member/               # Member area files
│   ├── dashboard.php     # Member dashboard
│   ├── events.php        # Event management
│   ├── sermons.php       # Sermon access
│   ├── prayer-requests.php # Prayer management
│   └── profile.php       # User profile
├── config/               # Configuration files
│   └── database.php      # Database and service config
├── database/             # Database files
│   └── schema.sql        # Database schema
├── index.php             # Main landing page
├── login.php             # Login page
├── register.php          # Registration page
├── logout.php            # Logout script
├── install.php           # Installation script
└── README.md             # This file
```

## Usage

### For Church Members
1. **Register**: Visit the registration page to create an account
2. **Login**: Access your personal dashboard
3. **Events**: Browse and register for upcoming events
4. **Sermons**: Watch and access sermon library
5. **Prayer**: Submit prayer requests and pray for others

### For Administrators
1. **Login**: Use admin credentials to access admin panel
2. **Users**: Manage member accounts and permissions
3. **Content**: Add/edit events, sermons, and announcements
4. **Settings**: Configure church information and system settings
5. **Messages**: Monitor and respond to contact form submissions

### For Website Visitors
1. **Browse**: View church information and upcoming events
2. **Contact**: Send messages through contact form
3. **Prayer**: Submit anonymous prayer requests
4. **Register**: Join the church community

## Customization

### Church Information
Update church details in the admin panel under Settings:
- Church name and tagline
- Contact information
- Service times
- Social media links

### Styling
The system uses CSS custom properties for easy theming:
```css
:root {
    --primary: #6d4c41;      /* Main brand color */
    --secondary: #8d6e63;    /* Secondary color */
    --accent: #d7ccc8;       /* Accent color */
    --light: #efebe9;        /* Light background */
    --dark: #3e2723;         /* Dark text */
}
```

### Sermon Videos
Sermons are embedded videos (YouTube, Vimeo, etc.):
1. Upload video to your preferred platform
2. Get embed code
3. Add through admin panel with title, description, and speaker

## Security Features

- Password hashing using PHP's built-in `password_hash()`
- SQL injection prevention with prepared statements
- Session-based authentication
- Role-based access control
- CSRF protection
- Input validation and sanitization

## Maintenance

### Regular Tasks
- Monitor prayer requests and respond appropriately
- Update events and sermon content
- Review and manage user accounts
- Check contact form submissions
- Backup database regularly

### Database Backup
```bash
mysqldump -u username -p grace_community > backup_$(date +%Y%m%d).sql
```

### Updates
- Keep PHP and MySQL versions current
- Monitor security updates
- Test new features in development environment

## Troubleshooting

### Common Issues

**Database Connection Error**
- Verify database credentials in `config/database.php`
- Ensure MySQL service is running
- Check database user permissions

**Real-time Features Not Working**
- Verify Pusher credentials
- Check browser console for JavaScript errors
- Ensure Pusher.js is loading correctly

**Email Not Sending**
- Verify SMTP settings in configuration
- Check email provider requirements
- Ensure server allows outgoing SMTP connections

**Permission Errors**
- Set proper file permissions (755 for directories, 644 for files)
- Ensure web server can write to config directory during installation

### Getting Help
- Check error logs in your web server
- Verify PHP error reporting is enabled
- Test database connection separately
- Check browser developer tools for JavaScript errors

## Support

For technical support or feature requests:
1. Check this README for common solutions
2. Review error logs and browser console
3. Test with different browsers and devices
4. Verify server requirements are met

## License

This project is provided as-is for church use. Please respect the original design and functionality while customizing for your specific needs.

## Credits

- **Design**: Modern, responsive church website design
- **Technology**: PHP, MySQL, JavaScript, CSS3
- **Real-time**: Pusher.js integration
- **Icons**: Font Awesome
- **Fonts**: Google Fonts (Playfair Display, Montserrat)

---

**Joy Bible Fellowship Management System** - Bringing faith communities into the digital age with love, technology, and purpose.
