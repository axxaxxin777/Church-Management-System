<?php
/**
 * Pusher Configuration for Client-Side
 * Provides Pusher credentials to the frontend
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Return Pusher configuration
echo json_encode([
    'app_id' => PUSHER_APP_ID,
    'key' => PUSHER_KEY,
    'cluster' => PUSHER_CLUSTER,
    'encrypted' => true
]);
?>
