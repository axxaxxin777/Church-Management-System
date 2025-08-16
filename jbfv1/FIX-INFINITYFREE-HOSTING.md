# Fix for InfinityFree Hosting Limitations

## The Issue
Your website shows this error:
```
Fatal error: Uncaught PDOException: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'if0_39655629_grace_community.active_visitors' doesn't exist
```

**Additional Error When Trying to Create VIEW:**
```
MySQL said: #1142 - CREATE VIEW command denied to user 'if0_39655629'@'192.168.0.6' for table `if0_39655629_grace_community`.`active_visitors`
```

## Root Cause
InfinityFree hosting (and most shared hosting providers) **restrict the CREATE VIEW privilege** for security reasons. The original code was designed to use a MySQL VIEW for `active_visitors`, but this isn't possible on your hosting.

## Solution: Table-Based Alternative

### Step 1: Run the Fix Script
Upload `fix-active-visitors-no-view.php` to your website and access it via:
```
https://yourwebsite.com/fix-active-visitors-no-view.php
```

This script will:
- Create the `visitors` table
- Create `active_visitors` as a **regular table** (not a view)
- Clean up old records
- Test the setup

### Step 2: Updated Code Implementation
The `index.php` file has been updated to:
1. Track visitors in the main `visitors` table
2. Automatically maintain the `active_visitors` table
3. Clean up old records on each page load
4. Count active visitors from the table

### What Changed
**Before (using VIEW - doesn't work on InfinityFree):**
```sql
CREATE VIEW active_visitors AS 
SELECT * FROM visitors WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE);
```

**After (using TABLE - works on InfinityFree):**
```sql
CREATE TABLE active_visitors (...);
-- Maintained by PHP code automatically
```

### Alternative: Manual SQL Execution
If you prefer to run SQL commands directly in phpMyAdmin:

1. Go to your phpMyAdmin
2. Select database: `if0_39655629_grace_community`
3. Run the SQL from `fix-active-visitors-no-view.sql`

```sql
-- Create visitors table if it doesn't exist
CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create active_visitors as a regular table
CREATE TABLE IF NOT EXISTS active_visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_last_activity (last_activity)
);
```

## Performance Considerations

### Automatic Cleanup
The updated code automatically cleans old records on each page load:
```php
$pdo->exec("DELETE FROM active_visitors WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
```

### Optional: Cron Job Optimization
For high-traffic sites, consider setting up a cron job to clean records instead of doing it on every page load:

1. Remove the cleanup line from `index.php`
2. Create a cron job running every 5 minutes:
```bash
*/5 * * * * /usr/bin/php /path/to/your/cleanup-visitors.php
```

## Verification Steps
1. Upload and run the fix script
2. Visit your website - the error should be gone
3. Check phpMyAdmin - you should see both `visitors` and `active_visitors` tables
4. The visitor count should display correctly on your homepage

## Why This Solution Works
- ✅ No CREATE VIEW privileges required
- ✅ Works on all shared hosting providers
- ✅ Automatically maintains data consistency
- ✅ Performance optimized with indexes
- ✅ Real-time visitor tracking still functions

## Security Notes
- Delete any fix scripts after running them
- The solution includes proper SQL injection protection
- Database permissions are respected

## Common InfinityFree Hosting Limitations
This fix addresses the most common database restriction. Other limitations you might encounter:
- No stored procedures
- No triggers
- Limited user privileges
- No DEFINER rights for views/procedures

This solution works around these limitations while maintaining full functionality.