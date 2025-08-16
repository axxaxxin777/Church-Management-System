<?php
session_start();
require_once 'config/database.php';
require_once 'config/privacy.php';
require_once 'includes/pusher.php';

// Fetch dynamic content from database
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Fetch recent sermons
$stmt = $pdo->query("SELECT * FROM sermons ORDER BY sermon_date DESC LIMIT 3");
$sermons = $stmt->fetchAll();

// Fetch upcoming events
$stmt = $pdo->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 3");
$events = $stmt->fetchAll();

// Fetch total prayer requests
$stmt = $pdo->query("SELECT COUNT(*) as total_prayers FROM prayer_requests");
$prayer_stats = $stmt->fetch();
$total_prayers = $prayer_stats['total_prayers'];

// Track visitor (simple session-based tracking)
$session_id = session_id();
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Update or insert visitor record
$stmt = $pdo->prepare("INSERT INTO visitors (session_id, ip_address, user_agent) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_activity = CURRENT_TIMESTAMP");
$stmt->execute([$session_id, $ip_address, $user_agent]);

// Get current active visitors count (last 5 minutes)
$stmt = $pdo->query("SELECT COUNT(*) as active_visitors FROM active_visitors");
$visitor_stats = $stmt->fetch();
$active_visitors = $visitor_stats['active_visitors'];

// Send real-time visitor update via Pusher
$pusher = getPusher();
if ($pusher->isAvailable()) {
    // Get total prayer requests for the Pusher event
    $stmt = $pdo->query("SELECT COUNT(*) as total_prayers FROM prayer_requests");
    $prayer_stats = $stmt->fetch();
    $total_prayers = $prayer_stats['total_prayers'];
    
    // Get upcoming events count for the Pusher event
    $stmt = $pdo->query("SELECT COUNT(*) as upcoming_events FROM events WHERE event_date >= CURDATE()");
    $event_stats = $stmt->fetch();
    $upcoming_events = $event_stats['upcoming_events'];
    
    // Send visitor update event
    $pusher->notifyAll('visitor_update', [
        'visitorCount' => $active_visitors,
        'prayerCount' => $total_prayers,
        'eventCount' => $upcoming_events
    ]);
}

// Handle contact form submission
$contact_message = '';
if ($_POST && isset($_POST['contact_submit'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if ($name && $email && $message) {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $email, $subject, $message])) {
            $contact_message = 'Thank you for your message! We will get back to you soon.';
        } else {
            $contact_message = 'Sorry, there was an error sending your message. Please try again.';
        }
    } else {
        $contact_message = 'Please fill in all required fields.';
    }
}

// Handle prayer request submission
$prayer_message = '';
if ($_POST && isset($_POST['prayer_submit'])) {
    $prayer_text = trim($_POST['prayer_text']);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    if ($prayer_text) {
        $stmt = $pdo->prepare("INSERT INTO prayer_requests (user_id, request_text, is_anonymous) VALUES (?, ?, ?)");
        $is_anonymous = !$user_id;
        if ($stmt->execute([$user_id, $prayer_text, $is_anonymous])) {
            $prayer_message = 'Your prayer request has been submitted. We\'ll pray for you!';
        } else {
            $prayer_message = 'Sorry, there was an error submitting your prayer request.';
        }
    } else {
        $prayer_message = 'Please enter your prayer request.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo htmlspecialchars($settings['church_name'] ?? 'Joy Bible Fellowship'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        :root {
            --primary: #6d4c41;
            --secondary: #8d6e63;
            --accent: #d7ccc8;
            --light: #efebe9;
            --dark: #3e2723;
            --text: #333;
            --white: #fff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            color: var(--text);
            background-color: var(--light);
            overflow-x: hidden;
            position: relative;
        }

        .stained-glass {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.3;
        }

        header {
            background: linear-gradient(135deg, rgba(109, 76, 65, 0.9) 0%, rgba(61, 39, 35, 0.9) 100%);
            color: var(--white);
            padding: 1rem 2rem;
            position: relative;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .auth-btn {
            padding: 0.5rem 1rem;
            border: 2px solid var(--accent);
            border-radius: 25px;
            text-decoration: none;
            color: var(--accent);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .auth-btn:hover {
            background-color: var(--accent);
            color: var(--dark);
        }

        .auth-btn.primary {
            background-color: var(--accent);
            color: var(--dark);
        }

        .auth-btn.primary:hover {
            background-color: var(--white);
            border-color: var(--white);
        }

        .logo {
            display: flex;
            align-items: center;
            font-family: 'Playfair Display', serif;
        }

        .logo i {
            font-size: 2.5rem;
            margin-right: 1rem;
            color: var(--accent);
        }

        .logo-text h1 {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .logo-text p {
            font-size: 0.9rem;
            font-weight: 300;
            opacity: 0.8;
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 2rem;
        }

        nav ul li a {
            color: var(--white);
            text-decoration: none;
            font-weight: 400;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
            padding-bottom: 5px;
        }

        nav ul li a:hover {
            color: var(--accent);
        }

        nav ul li a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--accent);
            transition: width 0.3s ease;
        }

        nav ul li a:hover:after {
            width: 100%;
        }

        .hero {
            height: 90vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(109, 76, 65, 0.7) 0%, rgba(61, 39, 35, 0.7) 100%);
            z-index: -1;
        }

        .hero-content {
            max-width: 800px;
            padding: 2rem;
            z-index: 2;
        }

        .hero h2 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 2rem;
            background-color: var(--accent);
            color: var(--dark);
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn:hover {
            background-color: var(--white);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--accent);
            color: var(--accent);
            margin-left: 1rem;
        }

        .btn-outline:hover {
            background-color: var(--accent);
            color: var(--dark);
        }

        .floating-prayers {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        /* Real-time Stats Bar */
        .realtime-stats {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, rgba(109, 76, 65, 0.95) 0%, rgba(61, 39, 35, 0.95) 100%);
            color: var(--white);
            padding: 0.3rem 1rem;
            display: flex;
            justify-content: center;
            gap: 2rem;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            font-size: 0.85rem;
        }
        
        /* Adjust header margin to account for fixed stats bar */
        header {
            margin-top: 30px;
            background: linear-gradient(135deg, rgba(109, 76, 65, 0.9) 0%, rgba(61, 39, 35, 0.9) 100%);
            color: var(--white);
            padding: 1rem 2rem;
            position: relative;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
        }
        
        .stat-item i {
            color: var(--accent);
            font-size: 0.9rem;
        }
        
        .stat-item span:first-of-type {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .stat-label {
            opacity: 0.8;
            font-size: 0.7rem;
        }

        .prayer-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            border: none;
            position: relative;
        }

        .prayer-btn:hover {
            transform: scale(1.1);
            background-color: var(--secondary);
        }

        .prayer-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .prayer-form {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 300px;
            background-color: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: scale(0);
            transform-origin: bottom right;
            transition: transform 0.3s ease;
            opacity: 0;
        }

        .prayer-form.active {
            transform: scale(1);
            opacity: 1;
        }

        .prayer-form h3 {
            margin-bottom: 1rem;
            color: var(--primary);
            font-family: 'Playfair Display', serif;
        }

        .prayer-form textarea {
            width: 100%;
            height: 120px;
            padding: 0.8rem;
            border: 1px solid var(--accent);
            border-radius: 5px;
            resize: none;
            margin-bottom: 1rem;
            font-family: 'Montserrat', sans-serif;
        }

        .prayer-form button {
            width: 100%;
            padding: 0.8rem;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .prayer-form button:hover {
            background-color: var(--secondary);
        }

        .prayer-notification {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            background-color: var(--primary);
            color: var(--white);
            padding: 1rem 2rem;
            border-radius: 5px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            transform: translateX(-150%);
            transition: transform 0.5s ease;
            z-index: 1000;
        }

        .prayer-notification.active {
            transform: translateX(0);
        }

        .section {
            padding: 5rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            position: relative;
        }

        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 3px;
            background-color: var(--accent);
            margin: 1rem auto;
        }

        .about-content {
            display: flex;
            align-items: center;
            gap: 3rem;
        }

        .about-text {
            flex: 1;
        }

        .about-text h3 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }

        .about-text p {
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .about-image {
            flex: 1;
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .about-image img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.5s ease;
        }

        .about-image:hover img {
            transform: scale(1.05);
        }

        .about-image:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(109, 76, 65, 0.3) 0%, rgba(61, 39, 35, 0.3) 100%);
            z-index: 1;
        }

        .services {
            background-color: var(--white);
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .service-card {
            background-color: var(--light);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .service-image {
            height: 200px;
            overflow: hidden;
        }

        .service-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .service-card:hover .service-image img {
            transform: scale(1.1);
        }

        .service-content {
            padding: 1.5rem;
        }

        .service-content h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .service-content p {
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .service-meta {
            display: flex;
            align-items: center;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .service-meta i {
            margin-right: 0.5rem;
        }

        .sermons {
            position: relative;
            overflow: hidden;
        }

        .sermons:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1514525253161-7a46d19cd819?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80') center/cover no-repeat;
            opacity: 0.1;
            z-index: -1;
        }

        .sermons-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .sermon-card {
            background-color: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .sermon-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .sermon-video {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            height: 0;
            overflow: hidden;
        }

        .sermon-video iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        .sermon-content {
            padding: 1.5rem;
        }

        .sermon-content h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .sermon-meta {
            display: flex;
            justify-content: space-between;
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .sermon-content p {
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .events {
            background-color: var(--light);
        }

        .events-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .event-card {
            background-color: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
        }

        .event-date {
            background-color: var(--primary);
            color: var(--white);
            padding: 1rem;
            text-align: center;
        }

        .event-date .day {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .event-date .month {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .event-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .event-content h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .event-meta {
            display: flex;
            align-items: center;
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .event-meta i {
            margin-right: 0.5rem;
        }

        .event-content p {
            line-height: 1.6;
            margin-bottom: 1.5rem;
            flex: 1;
        }

        .contact {
            background-color: var(--white);
        }

        .contact-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }

        .contact-info h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 1.5rem;
        }

        .contact-details {
            margin-bottom: 2rem;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .contact-icon {
            background-color: var(--accent);
            color: var(--primary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .contact-text h4 {
            font-size: 1.1rem;
            margin-bottom: 0.3rem;
        }

        .contact-text p, .contact-text a {
            color: var(--text);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .contact-text a:hover {
            color: var(--primary);
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: var(--accent);
            color: var(--primary);
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background-color: var(--primary);
            color: var(--white);
            transform: translateY(-3px);
        }

        .contact-form .form-group {
            margin-bottom: 1.5rem;
        }

        .contact-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .contact-form input,
        .contact-form textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid var(--accent);
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
            transition: border-color 0.3s ease;
        }

        .contact-form input:focus,
        .contact-form textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .contact-form textarea {
            height: 150px;
            resize: vertical;
        }

        footer {
            background-color: var(--dark);
            color: var(--white);
            padding: 3rem 2rem;
            text-align: center;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-logo {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            display: inline-block;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-links a {
            color: var(--accent);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--white);
        }

        .footer-social {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .footer-social a {
            color: var(--white);
            font-size: 1.5rem;
            transition: color 0.3s ease;
        }

        .footer-social a:hover {
            color: var(--accent);
        }

        .copyright {
            opacity: 0.7;
            font-size: 0.9rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Floating particles */
        .particle {
            position: absolute;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            pointer-events: none;
            z-index: -1;
        }

        /* Responsive styles */
        @media (max-width: 992px) {
            .about-content {
                flex-direction: column;
            }
            
            .about-image {
                order: -1;
                margin-bottom: 2rem;
            }
            
            .hero h2 {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
            }
            
            .logo {
                margin-bottom: 1rem;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            nav ul li {
                margin: 0.5rem 1rem;
            }
            
            .hero h2 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .btn {
                padding: 0.6rem 1.5rem;
                font-size: 0.9rem;
            }
            
            /* Adjust stats bar for mobile */
            .realtime-stats {
                gap: 1rem;
                padding: 0.2rem 0.5rem;
                font-size: 0.7rem;
            }
            
            .stat-item {
                gap: 0.3rem;
            }
            
            .stat-item i {
                font-size: 0.7rem;
            }
            
            .stat-item span:first-of-type {
                font-size: 0.8rem;
            }
            
            .stat-label {
                font-size: 0.6rem;
            }
        }

        @media (max-width: 576px) {
            .section {
                padding: 3rem 1rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .hero h2 {
                font-size: 1.8rem;
            }
            
            .prayer-form {
                width: 280px;
            }
        }

        /* Animation classes */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .animate-fadeIn {
            animation: fadeIn 1s ease forwards;
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(50px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slideUp {
            animation: slideUp 0.8s ease forwards;
        }

        .delay-1 { animation-delay: 0.2s; }
        .delay-2 { animation-delay: 0.4s; }
        .delay-3 { animation-delay: 0.6s; }
        .delay-4 { animation-delay: 0.8s; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Stained glass background effect -->
    <canvas class="stained-glass" id="stainedGlass"></canvas>
    
    <!-- Floating particles -->
    <div id="particles"></div>
    
    <!-- Header -->
    <header>
        <div class="header-container">
            <div class="logo">
                <i class="fas fa-church"></i>
                <div class="logo-text">
                    <h1>Joy Bible Fellowship</h1>
                    <p>Love God. Love People. Serve the World.</p>
                </div>
            </div>
            <nav>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#sermons">Sermons</a></li>
                    <li><a href="#events">Events</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </nav>
            <div class="auth-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="member/dashboard.php" class="auth-btn primary">Dashboard</a>
                    <a href="logout.php" class="auth-btn">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="auth-btn">Login</a>
                    <a href="register.php" class="auth-btn primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
                            <h2 class="animate-fadeIn">Welcome to <?php echo htmlspecialchars($settings['church_name'] ?? 'Joy Bible Fellowship'); ?></h2>
            <p class="animate-fadeIn delay-1"><?php echo htmlspecialchars($settings['church_tagline'] ?? 'A place where faith, hope, and love come together to transform lives and communities for Christ.'); ?></p>
            <div class="animate-fadeIn delay-2">
                <a href="#services" class="btn">Join Us</a>
                <a href="#about" class="btn btn-outline">Learn More</a>
            </div>
        </div>
    </section>
    
    <!-- About Section -->
    <section class="section" id="about">
        <h2 class="section-title animate-slideUp">About Our Church</h2>
        <div class="about-content">
            <div class="about-text animate-slideUp delay-1">
                <h3>Our Mission & Vision</h3>
                <p>Joy Bible Fellowship was founded with a simple mission: to spread joy through God's Word and share the love of Christ with our community and beyond. We believe in the power of faith to transform lives and bring hope to the hopeless.</p>
                <p>Our vision is to be a beacon of light in our city, offering spiritual guidance, practical help, and a welcoming community to all who seek God's love and truth.</p>
                <p>We are a multi-generational church with ministries for all ages, from children to seniors. Wherever you are in your spiritual journey, you're welcome here.</p>
                <a href="#contact" class="btn">Visit Us</a>
            </div>
            <div class="about-image animate-slideUp delay-2">
                <img src="https://images.unsplash.com/photo-1542272201-b1ca555f8505?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Church interior">
            </div>
        </div>
    </section>
    
    <!-- Services Section -->
    <section class="section services" id="services">
        <h2 class="section-title animate-slideUp">Our Services</h2>
        <div class="services-grid">
            <div class="service-card animate-slideUp delay-1">
                <div class="service-image">
                    <img src="https://images.unsplash.com/photo-1506126613408-eca07ce68773?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Sunday Service">
                </div>
                <div class="service-content">
                    <h3>Sunday Worship</h3>
                    <p>Join us every Sunday morning for uplifting worship, biblical teaching, and fellowship with other believers.</p>
                    <div class="service-meta">
                        <i class="far fa-clock"></i>
                        <span><?php echo htmlspecialchars($settings['sunday_service_time'] ?? 'Sundays at 9:00am & 11:00am'); ?></span>
                    </div>
                </div>
            </div>
            <div class="service-card animate-slideUp delay-2">
                <div class="service-image">
                    <img src="https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Bible Study">
                </div>
                <div class="service-content">
                    <h3>Bible Study</h3>
                    <p>Midweek Bible study groups where we dive deeper into God's Word and grow together in faith.</p>
                    <div class="service-meta">
                        <i class="far fa-clock"></i>
                        <span><?php echo htmlspecialchars($settings['bible_study_time'] ?? 'Wednesdays at 7:00pm'); ?></span>
                    </div>
                </div>
            </div>
            <div class="service-card animate-slideUp delay-3">
                <div class="service-image">
                    <img src="https://images.unsplash.com/photo-1529333166437-7750a6dd5a70?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Youth Group">
                </div>
                <div class="service-content">
                    <h3>Youth Ministry</h3>
                    <p>A dynamic gathering for teens with worship, relevant teaching, and fun activities designed just for them.</p>
                    <div class="service-meta">
                        <i class="far fa-clock"></i>
                        <span><?php echo htmlspecialchars($settings['youth_group_time'] ?? 'Fridays at 7:00pm'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Sermons Section -->
    <section class="section sermons" id="sermons">
        <h2 class="section-title animate-slideUp">Recent Sermons</h2>
        <div class="sermons-container">
            <?php foreach ($sermons as $sermon): ?>
            <div class="sermon-card animate-slideUp delay-1">
                <div class="sermon-video">
                    <?php echo $sermon['video_embed_code']; ?>
                </div>
                <div class="sermon-content">
                    <h3><?php echo htmlspecialchars($sermon['title']); ?></h3>
                    <div class="sermon-meta">
                        <span><i class="far fa-user"></i> <?php echo htmlspecialchars($sermon['speaker']); ?></span>
                        <span><i class="far fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($sermon['sermon_date'])); ?></span>
                    </div>
                    <p><?php echo htmlspecialchars($sermon['description']); ?></p>
                    <a href="#" class="btn">Watch Full Sermon</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    
    <!-- Events Section -->
    <section class="section events" id="events">
        <h2 class="section-title animate-slideUp">Upcoming Events</h2>
        <div class="events-container">
            <?php foreach ($events as $event): ?>
            <div class="event-card animate-slideUp delay-1">
                <div class="event-date">
                    <div class="day"><?php echo date('j', strtotime($event['event_date'])); ?></div>
                    <div class="month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                </div>
                <div class="event-content">
                    <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                    <div class="event-meta">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($event['location']); ?>, <?php echo $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : 'TBD'; ?></span>
                    </div>
                    <p><?php echo htmlspecialchars($event['description']); ?></p>
                    <a href="#" class="btn">Learn More</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    
    <!-- Contact Section -->
    <section class="section contact" id="contact">
        <h2 class="section-title animate-slideUp">Contact Us</h2>
        <div class="contact-container">
            <div class="contact-info animate-slideUp delay-1">
                <h3>Get In Touch</h3>
                <div class="contact-details">
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-text">
                            <h4>Address</h4>
                            <p><?php echo htmlspecialchars($settings['church_address'] ?? '123 Faith Avenue, Grace City, GC 12345'); ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div class="contact-text">
                            <h4>Phone</h4>
                            <p><a href="tel:<?php echo preg_replace('/[^0-9]/', '', $settings['church_phone'] ?? '(123) 456-7890'); ?>"><?php echo htmlspecialchars($settings['church_phone'] ?? '(123) 456-7890'); ?></a></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-text">
                            <h4>Email</h4>
                            <p><a href="mailto:<?php echo htmlspecialchars($settings['church_email'] ?? 'info@gracecommunity.org'); ?>"><?php echo htmlspecialchars($settings['church_email'] ?? 'info@gracecommunity.org'); ?></a></p>
                        </div>
                    </div>
                </div>
                <h3>Follow Us</h3>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            <div class="contact-form animate-slideUp delay-2">
                <h3>Send Us a Message</h3>
                <?php if ($contact_message): ?>
                    <div class="alert <?php echo strpos($contact_message, 'error') !== false ? 'alert-error' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($contact_message); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="#contact">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Your Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Your Message</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <button type="submit" name="contact_submit" class="btn">Send Message</button>
                </form>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer>
        <div class="footer-container">
                            <div class="footer-logo"><?php echo htmlspecialchars($settings['church_name'] ?? 'Joy Bible Fellowship'); ?></div>
            <div class="footer-links">
                <a href="#home">Home</a>
                <a href="#about">About</a>
                <a href="#services">Services</a>
                <a href="#sermons">Sermons</a>
                <a href="#events">Events</a>
                <a href="#contact">Contact</a>
                <a href="#">Give</a>
                <a href="#">Privacy Policy</a>
            </div>
            <div class="footer-social">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-youtube"></i></a>
                <a href="#"><i class="fab fa-spotify"></i></a>
            </div>
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['church_name'] ?? 'Joy Bible Fellowship'); ?>. All Rights Reserved.
            </div>
        </div>
    </footer>
    
    <!-- Real-time Stats Bar -->
    <div class="realtime-stats">
        <div class="stat-item">
            <i class="fas fa-users"></i>
            <span id="visitorCount">0</span>
            <span class="stat-label">Online</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-pray"></i>
            <span id="prayerCount">0</span>
            <span class="stat-label">Prayers Today</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-calendar-check"></i>
            <span id="eventCount">0</span>
            <span class="stat-label">Upcoming Events</span>
        </div>
    </div>

    <!-- Floating Prayer Request Button -->
    <div class="floating-prayers">
        <div class="prayer-form" id="prayerForm">
            <h3>Submit Prayer Request</h3>
            <?php if ($prayer_message): ?>
                <div class="alert <?php echo strpos($prayer_message, 'error') !== false ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($prayer_message); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="#">
                <textarea name="prayer_text" placeholder="Share your prayer need..." required></textarea>
                <button type="submit" name="prayer_submit">Submit</button>
            </form>
        </div>
        <button class="prayer-btn" id="prayerBtn">
            <i class="fas fa-pray"></i>
            <span class="prayer-count" id="prayerBtnCount">0</span>
        </button>
    </div>
    
    <!-- Prayer Notification -->
    <div class="prayer-notification" id="prayerNotification">
        <p>Your prayer request has been submitted. We'll pray for you!</p>
    </div>
    
    <script>
        // Stained Glass Canvas Effect
        const canvas = document.getElementById('stainedGlass');
        const ctx = canvas.getContext('2d');
        
        // Set canvas size
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        
        // Colors for stained glass effect
        const colors = [
            'rgba(109, 76, 65, 0.3)',
            'rgba(142, 36, 170, 0.3)',
            'rgba(60, 90, 166, 0.3)',
            'rgba(30, 130, 76, 0.3)',
            'rgba(214, 93, 14, 0.3)',
            'rgba(199, 36, 36, 0.3)'
        ];
        
        // Create stained glass effect
        function createStainedGlass() {
            const cellSize = 100;
            const rows = Math.ceil(canvas.height / cellSize) + 1;
            const cols = Math.ceil(canvas.width / cellSize) + 1;
            
            for (let i = 0; i < rows; i++) {
                for (let j = 0; j < cols; j++) {
                    const x = j * cellSize;
                    const y = i * cellSize;
                    const radius = cellSize / 2;
                    
                    // Random color
                    const color = colors[Math.floor(Math.random() * colors.length)];
                    
                    // Draw cell
                    ctx.beginPath();
                    ctx.arc(x + radius, y + radius, radius, 0, Math.PI * 2);
                    ctx.fillStyle = color;
                    ctx.fill();
                    ctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
                    ctx.stroke();
                }
            }
        }
        
        // Resize canvas when window resizes
        window.addEventListener('resize', function() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            createStainedGlass();
        });
        
        // Initialize stained glass
        createStainedGlass();
        
        // Real-time stats management - declare variables first
        let visitorCount = <?php echo $active_visitors; ?>;
        let prayerCount = <?php echo $total_prayers; ?>;
        let eventCount = <?php echo count($events); ?>;
        
        // Initialize Pusher for real-time updates
        const pusher = new Pusher('<?php echo PUSHER_KEY; ?>', {
            cluster: '<?php echo PUSHER_CLUSTER; ?>'
        });

        // Initialize real-time stats
        initializeStats();
        
        // Floating Particles
        const particlesContainer = document.getElementById('particles');
        const particlesCount = 30;
        
        for (let i = 0; i < particlesCount; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle');
            
            // Random size
            const size = Math.random() * 10 + 5;
            particle.style.width = `${size}px`;
            particle.style.height = `${size}px`;
            
            // Random position
            particle.style.left = `${Math.random() * 100}vw`;
            particle.style.top = `${Math.random() * 100}vh`;
            
            // Random opacity
            particle.style.opacity = Math.random() * 0.5 + 0.1;
            
            // Random animation
            const duration = Math.random() * 20 + 10;
            particle.style.animation = `float ${duration}s infinite linear`;
            
            // Add to container
            particlesContainer.appendChild(particle);
        }
        
        // Add floating animation to CSS
        const style = document.createElement('style');
        style.innerHTML = `
            @keyframes float {
                0% {
                    transform: translate(0, 0) rotate(0deg);
                }
                25% {
                    transform: translate(20px, 20px) rotate(90deg);
                }
                50% {
                    transform: translate(0, 40px) rotate(180deg);
                }
                75% {
                    transform: translate(-20px, 20px) rotate(270deg);
                }
                100% {
                    transform: translate(0, 0) rotate(360deg);
                }
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.1); }
                100% { transform: scale(1); }
            }
            
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                flex: 1;
            }
            
            .notification-close {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                padding: 0;
                font-size: 0.8rem;
                opacity: 0.7;
                transition: opacity 0.3s ease;
            }
            
            .notification-close:hover {
                opacity: 1;
            }
        `;
        document.head.appendChild(style);
        
        // Prayer Request Functionality
        const prayerBtn = document.getElementById('prayerBtn');
        const prayerForm = document.getElementById('prayerForm');
        const prayerNotification = document.getElementById('prayerNotification');
        
        prayerBtn.addEventListener('click', function() {
            prayerForm.classList.toggle('active');
        });
        
        // Handle prayer form submission
        const prayerFormElement = prayerForm.querySelector('form');
        if (prayerFormElement) {
            prayerFormElement.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const textarea = this.querySelector('textarea[name="prayer_text"]');
                const prayerText = textarea.value.trim();
                
                if (!prayerText) {
                    showNotification('Please enter your prayer request.', 'error');
                    return;
                }
                
                // Submit form via AJAX
                const formData = new FormData(this);
                formData.append('prayer_submit', '1');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Show success notification
                    showNotification('Your prayer request has been submitted. We\'ll pray for you!', 'success');
                    
                    // Clear form
                    textarea.value = '';
                    
                    // Close form
                    prayerForm.classList.remove('active');
                    
                    // Update prayer count
                    updatePrayerCount();
                    
                    // Show prayer notification
                    if (prayerNotification) {
                        prayerNotification.style.display = 'block';
                        setTimeout(() => {
                            prayerNotification.style.display = 'none';
                        }, 3000);
                    }
                })
                .catch(error => {
                    showNotification('Sorry, there was an error submitting your prayer request.', 'error');
                });
            });
        }
        
        // Smooth scrolling for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Scroll animations
        const animateOnScroll = function() {
            const elements = document.querySelectorAll('.animate-slideUp, .animate-fadeIn');
            
            elements.forEach(element => {
                const elementPosition = element.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                
                if (elementPosition < windowHeight - 100) {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }
            });
        };
        
        window.addEventListener('scroll', animateOnScroll);
        window.addEventListener('load', animateOnScroll);
        
        // Subscribe to multiple channels for different types of updates
        const prayerChannel = pusher.subscribe('prayer-requests');
        const eventsChannel = pusher.subscribe('events');
        const sermonsChannel = pusher.subscribe('sermons');
        const generalChannel = pusher.subscribe('general');

        // Handle prayer request notifications
        prayerChannel.bind('new-request', function(data) {
            showNotification('New prayer request submitted', 'info');
            updatePrayerCount();
        });

        // Handle new event notifications
        eventsChannel.bind('new-event', function(data) {
            showNotification(`New event: ${data.title}`, 'success');
            updateEventsSection();
        });

        // Handle new sermon notifications
        sermonsChannel.bind('new-sermon', function(data) {
            showNotification(`New sermon: ${data.title}`, 'success');
            updateSermonsSection();
        });

        // Handle general notifications
        generalChannel.bind('announcement', function(data) {
            showNotification(data.message, data.type || 'info');
        });

        // Show notification function with enhanced styling
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${getNotificationIcon(type)}"></i>
                    <span>${message}</span>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            // Add notification styles
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${getNotificationColor(type)};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                z-index: 10000;
                animation: slideIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 1rem;
                max-width: 400px;
                font-family: 'Montserrat', sans-serif;
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        function getNotificationIcon(type) {
            const icons = {
                'success': 'check-circle',
                'error': 'exclamation-circle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };
            return icons[type] || 'info-circle';
        }

        function getNotificationColor(type) {
            const colors = {
                'success': '#28a745',
                'error': '#dc3545',
                'warning': '#ffc107',
                'info': '#6d4c41'
            };
            return colors[type] || '#6d4c41';
        }




        // Initialize stats
        function initializeStats() {
            // Use real visitor count from server
            updateStatsDisplay();
            
            // Listen for real-time visitor updates via Pusher
            const visitorChannel = pusher.subscribe('church-notifications');
            visitorChannel.bind('visitor_update', function(data) {
                visitorCount = data.visitorCount;
                prayerCount = data.prayerCount;
                eventCount = data.eventCount;
                updateStatsDisplay();
            });
        }

        // Update stats (fallback for when Pusher is not available)
        function updateStats() {
            // Fetch real-time updates from server
            fetch('get-stats.php')
                .then(response => response.json())
                .then(data => {
                    visitorCount = data.visitorCount;
                    prayerCount = data.prayerCount;
                    eventCount = data.eventCount;
                    updateStatsDisplay();
                })
                .catch(error => {
                    console.error('Error fetching stats:', error);
                    // Fallback to incrementing local counters if server fetch fails
                    updateStatsDisplay();
                });
        }
        function updateStatsDisplay() {
            document.getElementById('visitorCount').textContent = visitorCount;
            document.getElementById('prayerCount').textContent = prayerCount;
            document.getElementById('eventCount').textContent = eventCount;
        }

        // Update prayer count in real-time
        function updatePrayerCount() {
            // Increment local counter
            prayerCount++;
            updateStatsDisplay();
            
            const prayerBtn = document.getElementById('prayerBtn');
            const prayerBtnCount = document.getElementById('prayerBtnCount');
            
            if (prayerBtn) {
                // Add a small animation to indicate new prayer request
                prayerBtn.style.animation = 'pulse 1s ease';
                setTimeout(() => {
                    prayerBtn.style.animation = '';
                }, 1000);
            }
            
            if (prayerBtnCount) {
                prayerBtnCount.textContent = prayerCount;
                prayerBtnCount.style.animation = 'pulse 1s ease';
                setTimeout(() => {
                    prayerBtnCount.style.animation = '';
                }, 1000);
            }
            
            // Update server-side counter
            fetch('increment-prayer-count.php', {
                method: 'POST'
            })
            .catch(error => {
                console.error('Error updating prayer count on server:', error);
            });
        }

        // Update events section with new events
        function updateEventsSection() {
            // This would typically fetch new events from the server
            // For now, we'll just show a notification
            console.log('Events section updated');
        }

        // Update sermons section with new sermons
        function updateSermonsSection() {
            // Fetch new sermons from server
            fetch('get-sermons.php')
                .then(response => response.json())
                .then(sermons => {
                    // Get the sermons container
                    const sermonsContainer = document.querySelector('.sermons-container');
                    
                    // Clear existing sermons
                    sermonsContainer.innerHTML = '';
                    
                    // Add new sermons
                    sermons.forEach(sermon => {
                        const sermonCard = document.createElement('div');
                        sermonCard.className = 'sermon-card animate-slideUp delay-1';
                        sermonCard.innerHTML = `
                            <div class="sermon-video">
                                ${sermon.video_embed_code}
                            </div>
                            <div class="sermon-content">
                                <h3>${sermon.title}</h3>
                                <div class="sermon-meta">
                                    <span><i class="far fa-user"></i> ${sermon.speaker}</span>
                                    <span><i class="far fa-calendar-alt"></i> ${new Date(sermon.sermon_date).toLocaleDateString()}</span>
                                </div>
                                <p>${sermon.description}</p>
                                <a href="#" class="btn">Watch Full Sermon</a>
                            </div>
                        `;
                        sermonsContainer.appendChild(sermonCard);
                    });
                })
                .catch(error => {
                    console.error('Error fetching sermons:', error);
                });
        }


    </script>
</body>
</html>