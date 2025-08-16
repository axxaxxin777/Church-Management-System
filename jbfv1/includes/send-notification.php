<?php
/**
 * Send Notification Endpoint
 * Allows client-side code to send notifications via Pusher
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once 'pusher.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['event']) || !isset($input['data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

try {
    $pusher = getPusher();
    
    if ($pusher->isAvailable()) {
        $success = $pusher->notifyAll($input['event'], $input['data']);
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Notification sent']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send notification']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Pusher not available']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
