<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get dashboard statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_members FROM users WHERE role = 'member' AND is_active = 1");
$total_members = $stmt->fetch()['total_members'];

$stmt = $pdo->query("SELECT COUNT(*) as total_events FROM events WHERE event_date >= CURDATE()");
$total_events = $stmt->fetch()['total_events'];

$stmt = $pdo->query("SELECT COUNT(*) as total_prayers FROM prayer_requests WHERE is_answered = 0");
$total_prayers = $stmt->fetch()['total_prayers'];

// Get recent activity
$stmt = $pdo->query("SELECT * FROM sermons ORDER BY created_at DESC LIMIT 4");
$recent_sermons = $stmt->fetchAll();

// Get upcoming events
$stmt = $pdo->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 3");
$upcoming_events = $stmt->fetchAll();

// Get recent prayer requests
$stmt = $pdo->query("SELECT pr.*, u.first_name, u.last_name FROM prayer_requests pr LEFT JOIN users u ON pr.user_id = u.id ORDER BY pr.created_at DESC LIMIT 3");
$recent_prayers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Joy Bible Fellowship - Members Dashboard</title>
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

        /* Sidebar Styles */
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

        .search-bar {
            position: relative;
            width: 300px;
        }

        .search-bar input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid var(--accent);
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(109, 76, 65, 0.1);
        }

        .search-bar i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary);
        }

        .user-actions {
            display: flex;
            align-items: center;
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: var(--secondary);
            font-size: 1.25rem;
            margin-right: 1rem;
            cursor: pointer;
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            font-weight: 600;
        }

        .user-dropdown {
            display: flex;
            align-items: center;
            cursor: pointer;
            position: relative;
        }

        .user-dropdown-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
            color: var(--primary);
            font-weight: 600;
        }

        .user-dropdown-name {
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }

        .user-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            width: 200px;
            padding: 0.5rem 0;
            z-index: 10;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            transform: translateY(10px);
        }

        .user-dropdown:hover .user-dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: block;
            padding: 0.5rem 1rem;
            color: var(--text);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background-color: var(--light);
            color: var(--primary);
        }

        .dropdown-divider {
            height: 1px;
            background-color: var(--accent);
            margin: 0.25rem 0;
        }

        /* Dashboard Content */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .page-title h1 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.8rem;
        }

        .page-title p {
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .page-actions .btn {
            display: inline-block;
            padding: 0.6rem 1.5rem;
            background-color: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
        }

        .page-actions .btn:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .page-actions .btn i {
            margin-right: 0.5rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background-color: var(--white);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-icon.primary {
            background-color: rgba(109, 76, 65, 0.1);
            color: var(--primary);
        }

        .stat-icon.success {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success);
        }

        .stat-icon.warning {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--warning);
        }

        .stat-icon.info {
            background-color: rgba(33, 150, 243, 0.1);
            color: var(--info);
        }

        .stat-trend {
            display: flex;
            align-items: center;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .stat-trend.up {
            color: var(--success);
        }

        .stat-trend.down {
            color: var(--danger);
        }

        .stat-trend i {
            margin-right: 0.25rem;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }

        .stat-title {
            font-size: 0.9rem;
            color: var(--secondary);
        }

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        /* Recent Activity */
        .activity-card {
            background-color: var(--white);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--accent);
        }

        .card-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.25rem;
        }

        .card-actions a {
            color: var(--secondary);
            font-size: 0.9rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .card-actions a:hover {
            color: var(--primary);
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            padding: 1rem 0;
            border-bottom: 1px solid var(--light);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--primary);
            font-size: 1rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .activity-description {
            font-size: 0.85rem;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .activity-meta {
            display: flex;
            align-items: center;
            font-size: 0.75rem;
            color: var(--secondary);
        }

        .activity-meta i {
            margin-right: 0.25rem;
        }

        .activity-meta span {
            margin-right: 1rem;
        }

        /* Upcoming Events */
        .events-card {
            background-color: var(--white);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .event-list {
            list-style: none;
        }

        .event-item {
            display: flex;
            padding: 1rem 0;
            border-bottom: 1px solid var(--light);
        }

        .event-item:last-child {
            border-bottom: none;
        }

        .event-date {
            width: 50px;
            height: 50px;
            background-color: var(--light);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .event-day {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary);
        }

        .event-month {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--secondary);
        }

        .event-content {
            flex: 1;
        }

        .event-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .event-description {
            font-size: 0.85rem;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .event-meta {
            display: flex;
            align-items: center;
            font-size: 0.75rem;
            color: var(--secondary);
        }

        .event-meta i {
            margin-right: 0.25rem;
        }

        .event-meta span {
            margin-right: 1rem;
        }

        .event-actions {
            margin-top: 0.5rem;
        }

        .event-actions .btn {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background-color: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
            text-decoration: none;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .event-actions .btn:hover {
            background-color: var(--primary);
            color: var(--white);
        }

        /* Prayer Requests */
        .prayer-card {
            background-color: var(--white);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-top: 1.5rem;
        }

        .prayer-list {
            list-style: none;
        }

        .prayer-item {
            padding: 1rem 0;
            border-bottom: 1px solid var(--light);
        }

        .prayer-item:last-child {
            border-bottom: none;
        }

        .prayer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .prayer-user {
            display: flex;
            align-items: center;
        }

        .prayer-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
            color: var(--primary);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .prayer-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .prayer-date {
            font-size: 0.75rem;
            color: var(--secondary);
        }

        .prayer-content {
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .prayer-actions {
            display: flex;
            align-items: center;
        }

        .prayer-actions button {
            background: none;
            border: none;
            color: var(--secondary);
            font-size: 0.8rem;
            margin-right: 1rem;
            cursor: pointer;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
        }

        .prayer-actions button:hover {
            color: var(--primary);
        }

        .prayer-actions button i {
            margin-right: 0.25rem;
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .sidebar {
                width: 220px;
            }
            
            .main-content {
                margin-left: 220px;
            }
        }

        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-bar {
                width: 100%;
                margin-bottom: 1rem;
            }
            
            .user-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-actions {
                margin-top: 1rem;
                width: 100%;
            }
            
            .page-actions .btn {
                display: block;
                text-align: center;
            }
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            width: 40px;
            height: 40px;
            background-color: var(--primary);
            color: white;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: flex;
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
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="events.php" class="menu-item">
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
                    <?php if ($total_prayers > 0): ?>
                        <span class="badge"><?php echo $total_prayers; ?></span>
                    <?php endif; ?>
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
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                
                <div class="user-actions">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-count">2</span>
                    </button>
                    
                    <div class="user-dropdown">
                        <div class="user-dropdown-avatar"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
                        <span class="user-dropdown-name"><?php echo htmlspecialchars($user['first_name']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                        
                        <div class="user-dropdown-menu">
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user mr-2"></i> My Profile
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog mr-2"></i> Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="../logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! Here's what's happening at Joy Bible Fellowship.</p>
                </div>
                <div class="page-actions">
                    <a href="prayer-requests.php" class="btn">
                        <i class="fas fa-plus"></i> New Prayer Request
                    </a>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-trend up">
                            <i class="fas fa-arrow-up"></i> 12%
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_members); ?></div>
                    <div class="stat-title">Total Members</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-trend up">
                            <i class="fas fa-arrow-up"></i> 5%
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_events; ?></div>
                    <div class="stat-title">Upcoming Events</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-pray"></i>
                        </div>
                        <div class="stat-trend down">
                            <i class="fas fa-arrow-down"></i> 3%
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_prayers; ?></div>
                    <div class="stat-title">Prayer Requests</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon info">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-trend up">
                            <i class="fas fa-arrow-up"></i> 8%
                        </div>
                    </div>
                    <div class="stat-value">$12,450</div>
                    <div class="stat-title">Monthly Giving</div>
                </div>
            </div>
            
            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Left Column -->
                <div class="left-column">
                    <!-- Recent Activity -->
                    <div class="activity-card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Activity</h3>
                            <div class="card-actions">
                                <a href="sermons.php">View All</a>
                            </div>
                        </div>
                        
                        <ul class="activity-list">
                            <?php foreach ($recent_sermons as $sermon): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-video"></i>
                                </div>
                                <div class="activity-content">
                                    <h4 class="activity-title">New Sermon Uploaded</h4>
                                    <p class="activity-description">"<?php echo htmlspecialchars($sermon['title']); ?>" by <?php echo htmlspecialchars($sermon['speaker']); ?></p>
                                    <div class="activity-meta">
                                        <span><i class="far fa-clock"></i> <?php echo date('M j', strtotime($sermon['created_at'])); ?></span>
                                        <span><i class="far fa-eye"></i> <?php echo number_format($sermon['views']); ?> views</span>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="right-column">
                    <!-- Upcoming Events -->
                    <div class="events-card">
                        <div class="card-header">
                            <h3 class="card-title">Upcoming Events</h3>
                            <div class="card-actions">
                                <a href="events.php">View Calendar</a>
                            </div>
                        </div>
                        
                        <ul class="event-list">
                            <?php foreach ($upcoming_events as $event): ?>
                            <li class="event-item">
                                <div class="event-date">
                                    <div class="event-day"><?php echo date('j', strtotime($event['event_date'])); ?></div>
                                    <div class="event-month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                </div>
                                <div class="event-content">
                                    <h4 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h4>
                                    <p class="event-description"><?php echo htmlspecialchars($event['description']); ?></p>
                                    <div class="event-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?></span>
                                        <span><i class="far fa-clock"></i> <?php echo $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : 'TBD'; ?></span>
                                    </div>
                                    <div class="event-actions">
                                        <a href="events.php?id=<?php echo $event['id']; ?>" class="btn">Learn More</a>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- Prayer Requests -->
                    <div class="prayer-card">
                        <div class="card-header">
                            <h3 class="card-title">Prayer Requests</h3>
                            <div class="card-actions">
                                <a href="prayer-requests.php">View All</a>
                            </div>
                        </div>
                        
                        <ul class="prayer-list">
                            <?php foreach ($recent_prayers as $prayer): ?>
                            <li class="prayer-item">
                                <div class="prayer-header">
                                    <div class="prayer-user">
                                        <div class="prayer-avatar">
                                            <?php 
                                            if ($prayer['user_id']) {
                                                echo strtoupper(substr($prayer['first_name'], 0, 1) . substr($prayer['last_name'], 0, 1));
                                            } else {
                                                echo 'A';
                                            }
                                            ?>
                                        </div>
                                        <span class="prayer-name">
                                            <?php 
                                            if ($prayer['user_id']) {
                                                echo htmlspecialchars($prayer['first_name'] . ' ' . $prayer['last_name']);
                                            } else {
                                                echo 'Anonymous';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <span class="prayer-date"><?php echo date('M j', strtotime($prayer['created_at'])); ?></span>
                                </div>
                                <p class="prayer-content"><?php echo htmlspecialchars($prayer['request_text']); ?></p>
                                <div class="prayer-actions">
                                    <button>
                                        <i class="fas fa-pray"></i> Prayed
                                    </button>
                                    <button>
                                        <i class="far fa-comment"></i> Comment
                                    </button>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Initialize Pusher for real-time updates
        const pusher = new Pusher('<?php echo PUSHER_KEY; ?>', {
            cluster: '<?php echo PUSHER_CLUSTER; ?>'
        });

        const channel = pusher.subscribe('prayer-requests');
        channel.bind('new-request', function(data) {
            // Show real-time notification for new prayer requests
            showNotification('New prayer request submitted', 'info');
        });

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
        
        // Show notification function
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.textContent = message;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '10000';
            notification.style.animation = 'slideIn 0.3s ease';
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
        
        // Add slideIn animation
        const style = document.createElement('style');
        style.innerHTML = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
