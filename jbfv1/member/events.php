<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../logout.php');
    exit();
}

// Handle event registration
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event'])) {
    $event_id = (int)$_POST['event_id'];
    
    // Check if already registered
    $stmt = $pdo->prepare("SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    
    if ($stmt->fetch()) {
        $error = 'You are already registered for this event.';
    } else {
        try {
            // Register for event
            $stmt = $pdo->prepare("INSERT INTO event_registrations (event_id, user_id) VALUES (?, ?)");
            $stmt->execute([$event_id, $user_id]);
            
            // Update event attendee count
            $stmt = $pdo->prepare("UPDATE events SET current_attendees = current_attendees + 1 WHERE id = ?");
            $stmt->execute([$event_id]);
            
            $message = 'Successfully registered for the event!';
        } catch (Exception $e) {
            $error = 'Error registering for event. Please try again.';
        }
    }
}

// Handle event unregistration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unregister_event'])) {
    $event_id = (int)$_POST['event_id'];
    
    try {
        // Unregister from event
        $stmt = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$event_id, $user_id]);
        
        // Update event attendee count
        $stmt = $pdo->prepare("UPDATE events SET current_attendees = current_attendees - 1 WHERE id = ?");
        $stmt->execute([$event_id]);
        
        $message = 'Successfully unregistered from the event.';
    } catch (Exception $e) {
        $error = 'Error unregistering from event. Please try again.';
    }
}

// Get events with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 6;
$offset = ($page - 1) * $limit;

// Get total count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM events");
$total_events = $stmt->fetch()['total'];
$total_pages = ceil($total_events / $limit);

// Get events
$stmt = $pdo->prepare("SELECT e.*, 
                              (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registered_count,
                              (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND user_id = :user_id) as user_registered
                       FROM events e 
                       ORDER BY event_date ASC, event_time ASC 
                       LIMIT :limit OFFSET :offset");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$events = $stmt->fetchAll();

// Get upcoming events
$stmt = $pdo->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC, event_time ASC LIMIT 3");
$upcoming_events = $stmt->fetchAll();

// Get user's registered events
$stmt = $pdo->prepare("SELECT e.* FROM events e 
                       INNER JOIN event_registrations er ON e.id = er.event_id 
                       WHERE er.user_id = ? AND e.event_date >= CURDATE() 
                       ORDER BY e.event_date ASC, e.event_time ASC");
$stmt->execute([$user_id]);
$user_events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grace Community Church - Events</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same CSS variables and reset as dashboard */
        :root {
            --primary: #6d4c41;
            --secondary: #8d6e63;
            --accent: #d7ccc8;
            --light: #efebe9;
            --dark: #3e2723;
            --text: #333;
            --white: #fff;
            --success: #4caf50;
            --warning: #ff9800;
            --danger: #f44336;
            --info: #2196f3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            color: var(--text);
            background-color: #f5f5f5;
            overflow-x: hidden;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles (same as dashboard) */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, rgba(109, 76, 65, 0.95) 0%, rgba(61, 39, 35, 0.95) 100%);
            color: var(--white);
            padding: 1.5rem 0;
            position: fixed;
            height: 100vh;
            z-index: 100;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            font-family: 'Playfair Display', serif;
            margin-bottom: 1rem;
        }

        .sidebar-logo i {
            font-size: 2rem;
            margin-right: 0.75rem;
            color: var(--accent);
        }

        .sidebar-logo-text h2 {
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .sidebar-logo-text p {
            font-size: 0.75rem;
            font-weight: 300;
            opacity: 0.8;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .admin-profile:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            color: var(--primary);
            font-weight: 600;
        }

        .admin-info h4 {
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }

        .admin-info p {
            font-size: 0.75rem;
            opacity: 0.7;
        }

        .sidebar-menu {
            padding: 0 1rem;
        }

        .menu-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--accent);
            margin: 1.5rem 0 0.75rem;
            padding-left: 0.5rem;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            margin-bottom: 0.25rem;
            border-radius: 5px;
            color: var(--white);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .menu-item i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1rem;
            text-align: center;
        }

        .menu-item span {
            font-size: 0.9rem;
        }

        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--accent);
        }

        .menu-item.active {
            background-color: var(--accent);
            color: var(--primary);
            font-weight: 600;
        }

        .menu-item.active i {
            color: var(--primary);
        }

        .menu-item .badge {
            margin-left: auto;
            background-color: var(--danger);
            color: white;
            border-radius: 10px;
            padding: 0.15rem 0.5rem;
            font-size: 0.7rem;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 1.5rem;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .page-title h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: var(--primary);
            margin: 0;
        }

        .page-title p {
            color: var(--secondary);
            margin: 0;
            font-size: 0.9rem;
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary);
            cursor: pointer;
        }

        /* Events Content */
        .events-content {
            display: grid;
            gap: 1.5rem;
        }

        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Upcoming Events Section */
        .upcoming-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .upcoming-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            text-align: center;
        }

        .upcoming-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .upcoming-event {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .upcoming-event h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .upcoming-event p {
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .upcoming-event .event-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            opacity: 0.8;
        }

        /* My Events Section */
        .my-events-section {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .my-events-section h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .my-events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .my-event-card {
            background-color: var(--light);
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid var(--success);
        }

        .my-event-card h4 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .my-event-card p {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .my-event-card .event-date {
            font-weight: 600;
            color: var(--primary);
        }

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .event-card {
            background-color: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.15);
        }

        .event-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 3rem;
        }

        .event-content {
            padding: 1.5rem;
        }

        .event-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .event-description {
            color: var(--text);
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .event-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--secondary);
        }

        .event-meta-item i {
            color: var(--primary);
            width: 16px;
        }

        .event-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-top: 1px solid var(--light);
            margin-top: 1rem;
        }

        .event-attendees {
            font-size: 0.9rem;
            color: var(--secondary);
        }

        .event-actions {
            display: flex;
            gap: 0.5rem;
        }

        .event-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .event-btn.primary {
            background-color: var(--primary);
            color: var(--white);
        }

        .event-btn.primary:hover {
            background-color: var(--dark);
        }

        .event-btn.secondary {
            background-color: var(--danger);
            color: var(--white);
        }

        .event-btn.secondary:hover {
            background-color: #c62828;
        }

        .event-btn.outline {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .event-btn.outline:hover {
            background-color: var(--primary);
            color: var(--white);
        }

        .event-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-link {
            padding: 0.5rem 1rem;
            border: 2px solid var(--accent);
            border-radius: 6px;
            background: none;
            color: var(--secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .page-link:hover,
        .page-link.active {
            background-color: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .upcoming-grid {
                grid-template-columns: 1fr;
            }

            .my-events-grid {
                grid-template-columns: 1fr;
            }

            .events-grid {
                grid-template-columns: 1fr;
            }

            .event-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                    <div class="sidebar-logo-text">
                        <h2>Member Panel</h2>
                        <p>Joy Bible Fellowship</p>
                    </div>
                </div>
            </div>
            
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
                <div class="admin-info">
                    <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p>Member</p>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <p class="menu-title">Main</p>
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="events.php" class="menu-item active">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Events</span>
                </a>
                <a href="sermons.php" class="menu-item">
                    <i class="fas fa-video"></i>
                    <span>Sermons</span>
                </a>
                <a href="giving.php" class="menu-item">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span>Giving</span>
                </a>
                
                <p class="menu-title">Community</p>
                <a href="groups.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Groups</span>
                </a>
                <a href="prayer-requests.php" class="menu-item">
                    <i class="fas fa-pray"></i>
                    <span>Prayer Requests</span>
                </a>
                
                <p class="menu-title">Personal</p>
                <a href="profile.php" class="menu-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                
                <p class="menu-title">Navigation</p>
                <a href="../index.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>View Site</span>
                </a>
                <a href="../logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1>Events</h1>
                    <p>Join us for upcoming events and activities</p>
                </div>
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="events-content">
                <!-- Upcoming Events -->
                <?php if (!empty($upcoming_events)): ?>
                <section class="upcoming-section">
                    <h2>Upcoming Events</h2>
                    <div class="upcoming-grid">
                        <?php foreach ($upcoming_events as $event): ?>
                        <div class="upcoming-event">
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($event['description'], 0, 100)) . '...'; ?></p>
                            <div class="event-meta">
                                <span><?php echo $event['event_date'] ? date('M j, Y', strtotime($event['event_date'])) : 'TBD'; ?></span>
                                <span><?php echo $event['location'] ?: 'TBD'; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- My Events -->
                <?php if (!empty($user_events)): ?>
                <section class="my-events-section">
                    <h3>My Registered Events</h3>
                    <div class="my-events-grid">
                        <?php foreach ($user_events as $event): ?>
                        <div class="my-event-card">
                            <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                            <p><?php echo htmlspecialchars(substr($event['description'], 0, 80)) . '...'; ?></p>
                            <div class="event-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo $event['event_date'] ? date('M j, Y', strtotime($event['event_date'])) : 'TBD'; ?>
                                <?php if ($event['event_time']): ?>
                                    at <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- All Events -->
                <?php if (!empty($events)): ?>
                <div class="events-grid">
                    <?php foreach ($events as $event): ?>
                    <div class="event-card">
                        <div class="event-image">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="event-content">
                            <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p class="event-description"><?php echo htmlspecialchars(substr($event['description'], 0, 120)) . '...'; ?></p>
                            
                            <div class="event-meta">
                                <div class="event-meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo $event['event_date'] ? date('M j, Y', strtotime($event['event_date'])) : 'TBD'; ?></span>
                                </div>
                                <div class="event-meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : 'TBD'; ?></span>
                                </div>
                                <div class="event-meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo $event['location'] ?: 'TBD'; ?></span>
                                </div>
                                <div class="event-meta-item">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo $event['category'] ?: 'General'; ?></span>
                                </div>
                            </div>

                            <div class="event-stats">
                                <div class="event-attendees">
                                    <i class="fas fa-users"></i>
                                    <?php echo $event['current_attendees']; ?> / <?php echo $event['max_attendees'] ?: '∞'; ?> registered
                                </div>
                                <div class="event-actions">
                                    <?php if ($event['user_registered']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" name="unregister_event" class="event-btn secondary">
                                                <i class="fas fa-times"></i> Unregister
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" name="register_event" class="event-btn primary" 
                                                    <?php echo ($event['max_attendees'] && $event['current_attendees'] >= $event['max_attendees']) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-plus"></i> Register
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="page-link">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="page-link">Next</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>No Events Available</h3>
                    <p>Check back later for upcoming events and activities.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // Mobile Menu Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            
            mobileMenuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('active');
            });
            
            document.addEventListener('click', function(event) {
                if (!sidebar.contains(event.target) && !mobileMenuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            });
            
            sidebar.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        });
    </script>
</body>
</html>
