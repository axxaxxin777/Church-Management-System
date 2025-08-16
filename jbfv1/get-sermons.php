<?php
// get-sermons.php - Endpoint for fetching recent sermons
header('Content-Type: application/json');

require_once 'config/database.php';

try {
    // Fetch recent sermons (limit to 3 as in the original index.php)
    $stmt = $pdo->query("SELECT * FROM sermons ORDER BY sermon_date DESC LIMIT 3");
    $sermons = $stmt->fetchAll();

    // Return sermons as JSON
    echo json_encode($sermons);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch sermons']);
}
?>