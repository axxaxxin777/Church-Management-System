<?php
// Optional cleanup script for active_visitors table
// Can be run via cron job every 5 minutes for better performance
// Usage: */5 * * * * /usr/bin/php /path/to/cleanup-visitors.php

require_once 'config/database.php';

try {
    // Clean up old active visitor records (older than 5 minutes)
    $stmt = $pdo->exec("DELETE FROM active_visitors WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    
    // Log the cleanup (optional)
    error_log("Active visitors cleanup: Removed " . $stmt . " old records at " . date('Y-m-d H:i:s'));
    
    // If running from command line, output result
    if (php_sapi_name() === 'cli') {
        echo "Cleanup completed: Removed $stmt old records\n";
    }
    
} catch (Exception $e) {
    error_log("Active visitors cleanup error: " . $e->getMessage());
    
    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>