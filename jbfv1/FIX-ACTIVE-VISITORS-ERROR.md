# Fix for Active Visitors Table Error

## Error Description
```
Fatal error: Uncaught PDOException: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'if0_39655629_grace_community.active_visitors' doesn't exist in /home/vol6_7/infinityfree.com/if0_39655629/htdocs/index.php:41
```

## Cause
The `active_visitors` view and/or the underlying `visitors` table are missing from your database. The `active_visitors` is a MySQL VIEW that depends on the `visitors` table to track website visitors in real-time.

## Solution Options

### Option 1: Run the PHP Fix Script (Recommended)
1. Upload the `fix-active-visitors.php` file to your website
2. Access it via your web browser: `https://yourwebsite.com/fix-active-visitors.php`
3. The script will automatically create the missing table and view
4. Delete the script file after successful execution for security

### Option 2: Run SQL Commands Directly
If you have access to your database via phpMyAdmin or command line:

1. Execute the SQL commands in `fix-active-visitors-table.sql`
2. Or run these commands manually:

```sql
USE if0_39655629_grace_community;

-- Create visitors table
CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create active_visitors view
DROP VIEW IF EXISTS active_visitors;
CREATE VIEW active_visitors AS
SELECT * FROM visitors WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE);
```

### Option 3: Use Existing Script
The project already has an `add-visitors-table.php` script that you can run:
```bash
php add-visitors-table.php
```

## Verification
After applying the fix, your website should load without the PDOException error. The `active_visitors` view will now properly track users who have been active within the last 5 minutes.

## What These Tables Do
- **visitors**: Stores session information for website visitors
- **active_visitors**: A view showing only visitors active in the last 5 minutes
- Used for real-time visitor count display on the website

## Security Note
Remember to delete any fix scripts from your web server after running them to prevent unauthorized access.