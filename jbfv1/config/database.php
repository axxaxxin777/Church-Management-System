<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'grace_community');

// Create connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Pusher configuration
define('PUSHER_APP_ID', '2034789');
define('PUSHER_KEY', '52a1f3b0b3938b43e304');
define('PUSHER_SECRET', 'ba8e17611b34f49c482f');
define('PUSHER_CLUSTER', 'ap1');

// PHPMailer configuration
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'jmsof777@gmail.com');
define('MAIL_PASSWORD', 'iaglvxihokafrmpc');
define('MAIL_FROM_NAME', 'Joy Bible Fellowship');
define('MAIL_FROM_EMAIL', 'jmsof777@gmail.com');
?>
