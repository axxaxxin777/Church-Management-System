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
            // Update prayer count
            $total_prayers++;
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
            margin-top: 30px;
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

        /* Enhanced Floating Prayer Form */
        .floating-prayers {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 1000;
            max-width: calc(100vw - 3rem);
        }

        .prayer-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(109, 76, 65, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .prayer-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s ease, height 0.3s ease;
        }

        .prayer-btn:hover {
            transform: scale(1.1) translateY(-2px);
            box-shadow: 0 12px 35px rgba(109, 76, 65, 0.4);
        }

        .prayer-btn:hover::before {
            width: 100%;
            height: 100%;
        }

        .prayer-btn:active {
            transform: scale(0.95);
        }

        .prayer-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border-radius: 50%;
            min-width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
            animation: pulse 2s infinite;
        }

        .prayer-form {
            position: absolute;
            bottom: 75px;
            right: 0;
            width: 320px;
            max-width: calc(100vw - 3rem);
            background: var(--white);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            transform: scale(0) translateY(20px);
            transform-origin: bottom right;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
            visibility: hidden;
            border: 1px solid var(--accent);
            backdrop-filter: blur(10px);
            z-index: 1001;
        }

        .prayer-form::before {
            content: '';
            position: absolute;
            bottom: -8px;
            right: 20px;
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-top: 8px solid var(--white);
        }

        .prayer-form.active {
            transform: scale(1) translateY(0);
            opacity: 1;
            visibility: visible;
        }

        .prayer-form h3 {
            margin: 0 0 1.5rem 0;
            color: var(--primary);
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            text-align: center;
        }

        .prayer-form textarea {
            width: 100%;
            min-height: 120px;
            max-height: 200px;
            padding: 1rem;
            border: 2px solid var(--accent);
            border-radius: 10px;
            resize: vertical;
            margin-bottom: 1rem;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background: var(--light);
        }

        .prayer-form textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(109, 76, 65, 0.1);
            background: var(--white);
        }

        .prayer-form textarea::placeholder {
            color: var(--secondary);
        }

        .prayer-form .form-actions {
            display: flex;
            gap: 0.75rem;
        }

        .prayer-form button {
            flex: 1;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .prayer-form .btn-submit {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
        }

        .prayer-form .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(109, 76, 65, 0.3);
        }

        .prayer-form .btn-cancel {
            background: transparent;
            color: var(--secondary);
            border: 2px solid var(--accent);
        }

        .prayer-form .btn-cancel:hover {
            background: var(--accent);
            color: var(--primary);
        }

        .prayer-notification {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(109, 76, 65, 0.3);
            transform: translateX(-150%);
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            max-width: calc(100vw - 4rem);
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

        /* Enhanced mobile responsiveness */
        @media (max-width: 768px) {
            .floating-prayers {
                bottom: 1rem;
                right: 1rem;
            }
            
            .prayer-btn {
                width: 56px;
                height: 56px;
                font-size: 1.3rem;
            }
            
            .prayer-count {
                top: -6px;
                right: -6px;
                min-width: 20px;
                height: 20px;
                font-size: 0.7rem;
            }
            
            .prayer-form {
                width: calc(100vw - 2rem);
                max-width: 300px;
                bottom: 70px;
                right: 0;
                padding: 1.25rem;
            }
            
            .prayer-form h3 {
                font-size: 1.2rem;
                margin-bottom: 1.25rem;
            }
            
            .prayer-form textarea {
                min-height: 100px;
                padding: 0.875rem;
                font-size: 0.9rem;
            }
            
            .prayer-notification {
                bottom: 1rem;
                left: 1rem;
                right: 1rem;
                max-width: none;
                text-align: center;
            }

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

            .about-content {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .prayer-form {
                width: calc(100vw - 1.5rem);
                right: 0.75rem;
                padding: 1rem;
            }
            
            .prayer-form .form-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .section {
                padding: 3rem 1rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .hero h2 {
                font-size: 1.8rem;
            }
        }

        /* Landscape phone adjustments */
        @media (max-height: 500px) and (orientation: landscape) {
            .prayer-form {
                bottom: 60px;
                max-height: calc(100vh - 120px);
                overflow-y: auto;
            }
            
            .prayer-form textarea {
                min-height: 80px;
                max-height: 120px;
            }
        }

        /* Prevent body scroll when form is open on mobile */
        body.prayer-form-open {
            overflow: hidden;
        }

        @media (max-width: 768px) {
            body.prayer-form-open {
                position: fixed;
                width: 100%;
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

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <!-- Stained glass background effect -->
    <canvas class="stained-glass" id="stainedGlass"></canvas>
    
    <!-- Real-time Stats Bar -->
    <div class="realtime-stats">
        <div class="stat-item">
            <i class="fas fa-users"></i>
            <span id="visitorCount"><?php echo $active_visitors; ?></span>
            <span class="stat-label">Online</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-pray"></i>
            <span id="prayerCount"><?php echo $total_prayers; ?></span>
            <span class="stat-label">Prayers Today</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-calendar-check"></i>
            <span id="eventCount"><?php echo count($events); ?></span>
            <span class="stat-label">Upcoming Events</span>
        </div>
    </div>
    
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
                <a href="#about" class="btn">Learn More</a>
                <a href="#contact" class="btn btn-outline">Contact Us</a>
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
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['church_name'] ?? 'Joy Bible Fellowship'); ?>. All Rights Reserved.
            </div>
        </div>
    </footer>

    <!-- Enhanced Floating Prayer Request Button -->
    <div class="floating-prayers">
        <div class="prayer-form" id="prayerForm">
            <h3>Submit Prayer Request</h3>
            <?php if ($prayer_message): ?>
                <div class="alert <?php echo strpos($prayer_message, 'error') !== false ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($prayer_message); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="#" id="prayerFormElement">
                <textarea name="prayer_text" placeholder="Share your prayer need..." required maxlength="500"></textarea>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" id="cancelPrayerBtn">Cancel</button>
                    <button type="submit" name="prayer_submit" class="btn-submit">Submit</button>
                </div>
            </form>
        </div>
        <button class="prayer-btn" id="prayerBtn" aria-label="Submit Prayer Request">
            <i class="fas fa-pray"></i>
            <span class="prayer-count" id="prayerBtnCount"><?php echo $total_prayers; ?></span>
        </button>
    </div>
    
    <!-- Prayer Notification -->
    <div class="prayer-notification" id="prayerNotification">
        <p>Your prayer request has been submitted. We'll pray for you!</p>
    </div>
    
    <script>
        // Initialize variables for real-time stats
        let visitorCount = <?php echo $active_visitors; ?>;
        let prayerCount = <?php echo $total_prayers; ?>;
        let eventCount = <?php echo count($events); ?>;
        
        // Initialize Pusher for real-time updates
        const pusher = new Pusher('<?php echo PUSHER_KEY; ?>', {
            cluster: '<?php echo PUSHER_CLUSTER; ?>'
        });

        // Prayer Request Functionality with Enhanced Mobile Support
        const prayerBtn = document.getElementById('prayerBtn');
        const prayerForm = document.getElementById('prayerForm');
        const prayerNotification = document.getElementById('prayerNotification');
        const cancelPrayerBtn = document.getElementById('cancelPrayerBtn');
        const body = document.body;

        // Toggle prayer form
        prayerBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            togglePrayerForm();
        });

        // Cancel button
        cancelPrayerBtn.addEventListener('click', function() {
            closePrayerForm();
        });

        function togglePrayerForm() {
            const isActive = prayerForm.classList.contains('active');
            if (isActive) {
                closePrayerForm();
            } else {
                openPrayerForm();
            }
        }

        function openPrayerForm() {
            prayerForm.classList.add('active');
            // Prevent body scroll on mobile when form is open
            if (window.innerWidth <= 768) {
                body.classList.add('prayer-form-open');
            }
            
            // Focus on textarea for better UX
            setTimeout(() => {
                const textarea = prayerForm.querySelector('textarea');
                if (textarea) {
                    textarea.focus();
                }
            }, 300);
        }

        function closePrayerForm() {
            prayerForm.classList.remove('active');
            body.classList.remove('prayer-form-open');
        }

        // Enhanced click outside to close
        document.addEventListener('click', function(e) {
            if (!prayerBtn.contains(e.target) && !prayerForm.contains(e.target)) {
                closePrayerForm();
            }
        });

        // Close form when scrolling on mobile
        let lastScrollPosition = 0;
        window.addEventListener('scroll', function() {
            const currentScrollPosition = window.pageYOffset;
            
            // Only close if scrolling significantly (prevent accidental closures)
            if (Math.abs(currentScrollPosition - lastScrollPosition) > 50) {
                closePrayerForm();
            }
            
            lastScrollPosition = currentScrollPosition;
        });

        // Handle form submission with enhanced error handling
        const prayerFormElement = document.getElementById('prayerFormElement');
        if (prayerFormElement) {
            prayerFormElement.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const textarea = this.querySelector('textarea[name="prayer_text"]');
                const prayerText = textarea.value.trim();
                
                if (!prayerText) {
                    showNotification('Please enter your prayer request.', 'error');
                    return;
                }

                if (prayerText.length > 500) {
                    showNotification('Prayer request must be less than 500 characters.', 'error');
                    return;
                }

                // Disable submit button to prevent double submission
                const submitBtn = this.querySelector('.btn-submit');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';
                
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
                    closePrayerForm();
                    
                    // Update prayer count
                    updatePrayerCount();
                    
                    // Show prayer notification
                    if (prayerNotification) {
                        prayerNotification.classList.add('active');
                        setTimeout(() => {
                            prayerNotification.classList.remove('active');
                        }, 4000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Sorry, there was an error submitting your prayer request. Please try again.', 'error');
                })
                .finally(() => {
                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
            });
        }

        // Real-time stats management
        function updateStatsDisplay() {
            document.getElementById('visitorCount').textContent = visitorCount;
            document.getElementById('prayerCount').textContent = prayerCount;
            document.getElementById('eventCount').textContent = eventCount;
            document.getElementById('prayerBtnCount').textContent = prayerCount;
        }

        // Listen for real-time visitor updates via Pusher
        const visitorChannel = pusher.subscribe('church-notifications');
        visitorChannel.bind('visitor_update', function(data) {
            visitorCount = data.visitorCount;
            prayerCount = data.prayerCount;
            eventCount = data.eventCount;
            updateStatsDisplay();
        });

        // Update prayer count in real-time
        function updatePrayerCount() {
            prayerCount++;
            updateStatsDisplay();
            
            // Add animation to indicate new prayer request
            prayerBtn.style.animation = 'pulse 1s ease';
            setTimeout(() => {
                prayerBtn.style.animation = '';
            }, 1000);
        }

        // Enhanced notification system
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
            
            // Enhanced notification styles
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
                animation: slideInFromRight 0.3s ease;
                display: flex;
                align-items: center;
                gap: 1rem;
                max-width: 400px;
                font-family: 'Montserrat', sans-serif;
                font-size: 0.9rem;
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.animation = 'slideOutToRight 0.3s ease';
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

        // Add notification animations to CSS
        const style = document.createElement('style');
        style.innerHTML = `
            @keyframes slideInFromRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOutToRight {
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

        // Smooth scrolling for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    // Close prayer form if open
                    closePrayerForm();
                    
                    // Calculate offset for fixed header
                    const headerOffset = 80;
                    const elementPosition = targetElement.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Enhanced scroll animations
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

        // Stained Glass Canvas Effect (simplified for better performance)
        const canvas = document.getElementById('stainedGlass');
        const ctx = canvas.getContext('2d');
        
        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            createStainedGlass();
        }
        
        const colors = [
            'rgba(109, 76, 65, 0.2)',
            'rgba(142, 36, 170, 0.2)',
            'rgba(60, 90, 166, 0.2)',
            'rgba(30, 130, 76, 0.2)',
            'rgba(214, 93, 14, 0.2)',
            'rgba(199, 36, 36, 0.2)'
        ];
        
        function createStainedGlass() {
            const cellSize = 120;
            const rows = Math.ceil(canvas.height / cellSize) + 1;
            const cols = Math.ceil(canvas.width / cellSize) + 1;
            
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            for (let i = 0; i < rows; i++) {
                for (let j = 0; j < cols; j++) {
                    const x = j * cellSize;
                    const y = i * cellSize;
                    const radius = cellSize / 2;
                    
                    const color = colors[Math.floor(Math.random() * colors.length)];
                    
                    ctx.beginPath();
                    ctx.arc(x + radius, y + radius, radius, 0, Math.PI * 2);
                    ctx.fillStyle = color;
                    ctx.fill();
                    ctx.strokeStyle = 'rgba(255, 255, 255, 0.1)';
                    ctx.lineWidth = 1;
                    ctx.stroke();
                }
            }
        }
        
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        // Initialize on page load
        updateStatsDisplay();
    </script>
</body>
</html>