<?php
// increment-prayer-count.php - Endpoint for incrementing prayer count
header('Content-Type: application/json');

require_once 'config/database.php';

try {
    // Since we're just counting prayer requests, we don't actually need to increment
    // anything in the database. The count is derived from the number of rows in the
    // prayer_requests table. However, we can add a simple counter in the settings
    // table if needed for performance reasons.
    
    // For now, we'll just return success
    echo json_encode([
        'success' => true
    ]);
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to increment prayer count'
    ]);
}
?>