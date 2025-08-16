<?php
// get-stats.php - Endpoint for fetching real-time stats
header('Content-Type: application/json');

require_once 'config/database.php';

try {
    // Get current active visitors count (last 5 minutes)
    $stmt = $pdo->query("SELECT COUNT(*) as active_visitors FROM active_visitors");
    $visitor_stats = $stmt->fetch();
    $active_visitors = $visitor_stats['active_visitors'];

    // Get total prayer requests
    $stmt = $pdo->query("SELECT COUNT(*) as total_prayers FROM prayer_requests");
    $prayer_stats = $stmt->fetch();
    $total_prayers = $prayer_stats['total_prayers'];

    // Get upcoming events count
    $stmt = $pdo->query("SELECT COUNT(*) as upcoming_events FROM events WHERE event_date >= CURDATE()");
    $event_stats = $stmt->fetch();
    $upcoming_events = $event_stats['upcoming_events'];

    // Return stats as JSON
    echo json_encode([
        'visitorCount' => $active_visitors,
        'prayerCount' => $total_prayers,
        'eventCount' => $upcoming_events
    ]);
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch stats'
    ]);
}
?>