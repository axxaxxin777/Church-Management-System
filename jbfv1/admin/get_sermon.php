<?php
// get_sermon.php - Endpoint for fetching sermon data by ID
header('Content-Type: application/json');

require_once '../config/database.php';

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Sermon ID is required']);
    exit;
}

$sermon_id = intval($_GET['id']);

try {
    // Fetch sermon from database
    $stmt = $pdo->prepare("SELECT * FROM sermons WHERE id = ?");
    $stmt->execute([$sermon_id]);
    $sermon = $stmt->fetch();

    if (!$sermon) {
        http_response_code(404);
        echo json_encode(['error' => 'Sermon not found']);
        exit;
    }

    // Return sermon data
    echo json_encode($sermon);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch sermon']);
    exit;
}
?>