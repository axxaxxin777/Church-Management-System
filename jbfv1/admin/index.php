<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get admin user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get system statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
$total_users = $stmt->fetch()['total_users'];

$stmt = $pdo->query("SELECT COUNT(*) as total_events FROM events");
$total_events = $stmt->fetch()['total_events'];

$stmt = $pdo->query("SELECT COUNT(*) as total_sermons FROM sermons");
$total_sermons = $stmt->fetch()['total_sermons'];

$stmt = $pdo->query("SELECT COUNT(*) as total_prayers FROM prayer_requests");
$total_prayers = $stmt->fetch()['total_prayers'];

// Get user registration trend (last 30 days)
$stmt = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date");
$user_trend_data = $stmt->fetchAll();

// Get event attendance statistics
$stmt = $pdo->query("SELECT SUM(current_attendees) as total_attendees, SUM(max_attendees) as total_capacity FROM events");
$event_stats = $stmt->fetch();
$attendance_rate = $event_stats['total_capacity'] > 0 ? round(($event_stats['total_attendees'] / $event_stats['total_capacity']) * 100, 1) : 0;

// Get sermon views statistics
$stmt = $pdo->query("SELECT SUM(views) as total_views FROM sermons");
$sermon_views = $stmt->fetch()['total_views'] ?? 0;

// Get prayer request status statistics
$stmt = $pdo->query("SELECT COUNT(*) as answered FROM prayer_requests WHERE is_answered = 1");
$answered_prayers = $stmt->fetch()['answered'];
$pending_prayers = $total_prayers - $answered_prayers;

// Get contact message statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_messages FROM contact_messages");
$total_messages = $stmt->fetch()['total_messages'];

$stmt = $pdo->query("SELECT COUNT(*) as unread_messages FROM contact_messages WHERE is_read = 0");
$unread_messages = $stmt->fetch()['unread_messages'];

// Handle user management actions
$message = '';
$message_type = '';

// Delete user
if (isset($_GET['delete_user'])) {
    $user_id = intval($_GET['delete_user']);
    if ($user_id != $user['id']) { // Prevent self-deletion
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $message = 'User deleted successfully.';
            $message_type = 'success';
            // Refresh user list
            $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
            $recent_users = $stmt->fetchAll();
        } else {
            $message = 'Error deleting user.';
            $message_type = 'error';
        }
    } else {
        $message = 'You cannot delete your own account.';
        $message_type = 'error';
    }
}
// Get recent activity
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5");
$recent_messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Panel - Joy Bible Fellowship</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            background: linear-gradient(135deg, rgba(61, 39, 35, 0.95) 0%, rgba(109, 76, 65, 0.95) 100%);
            color: var(--white);
            padding: 1.5rem 0;
            position: fixed;
            height: 100vh;
            z-index: 100;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
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

        /* Stats Grid */
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

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .content-card {
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

        .user-list, .message-list {
            list-style: none;
        }

        .user-item, .message-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--light);
        }

        .user-item:last-child, .message-item:last-child {
            border-bottom: none;
        }

        .user-avatar, .message-icon {
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

        .user-content, .message-content {
            flex: 1;
        }

        .user-name, .message-subject {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .user-email, .message-email {
            font-size: 0.85rem;
            color: var(--secondary);
            margin-bottom: 0.25rem;
        }

        .user-role, .message-date {
            font-size: 0.75rem;
            color: var(--secondary);
        }

        .user-actions, .message-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-small {
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

        .btn-small:hover {
            background-color: var(--primary);
            color: var(--white);
        }

        .btn-small.danger {
            border-color: var(--danger);
            color: var(--danger);
        }

        .btn-small.danger:hover {
            background-color: var(--danger);
            color: var(--white);
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .sidebar {
                width: 250px;
            }
            
            .main-content {
                margin-left: 250px;
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
    </style>
<script src="https://js.pusher.com/7.0/pusher.min.js"></script>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                    <div class="sidebar-logo-text">
                        <h2>Admin Panel</h2>
                        <p>Joy Bible Fellowship</p>
                    </div>
                </div>
            </div>
            
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
                <div class="admin-info">
                    <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p>Administrator</p>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <p class="menu-title">Management</p>
                <a href="index.php" class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="events.php" class="menu-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Events</span>
                </a>
                <a href="sermons.php" class="menu-item">
                    <i class="fas fa-video"></i>
                    <span>Sermons</span>
                </a>
                <a href="prayers.php" class="menu-item">
                    <i class="fas fa-pray"></i>
                    <span>Prayer Requests</span>
                </a>
                
                <p class="menu-title">System</p>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="messages.php" class="menu-item">
                    <i class="fas fa-envelope"></i>
                    <span>Contact Messages</span>
                    <?php if (count($recent_messages) > 0): ?>
                        <span class="badge"><?php echo count($recent_messages); ?></span>
                    <?php endif; ?>
                </a>
                
                <p class="menu-title">Navigation</p>
                <a href="../index.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>View Site</span>
                </a>
                <a href="../member/dashboard.php" class="menu-item">
                    <i class="fas fa-user"></i>
                    <span>Member Area</span>
                </a>
                <a href="../logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Admin Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! Here's an overview of your church management system.</p>
                </div>
                <div class="page-actions">
                    <a href="users.php" class="btn">
                        <i class="fas fa-plus"></i> Add User
                    </a>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 5px; <?php echo $message_type === 'success' ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Page Actions -->
            <div class="page-actions">
                <a href="users.php" class="btn" style="background-color: #8d6e63;">
                    <i class="fas fa-users"></i> Manage All Users
                </a>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_users); ?></div>
                    <div class="stat-title">Total Users</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_events; ?></div>
                    <div class="stat-title">Total Events</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-video"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_sermons; ?></div>
                    <div class="stat-title">Total Sermons</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon info">
                            <i class="fas fa-pray"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_prayers; ?></div>
                    <div class="stat-title">Prayer Requests</div>
                </div>
            </div>
            
            <!-- Additional Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon success">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_messages; ?></div>
                    <div class="stat-title">Total Messages</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-envelope-open"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $unread_messages; ?></div>
                    <div class="stat-title">Unread Messages</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon info">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($sermon_views); ?></div>
                    <div class="stat-title">Sermon Views</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                    <div class="stat-title">Event Attendance Rate</div>
                </div>
            </div>
            
            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Users -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Users</h3>
                        <div class="card-actions">
                            <a href="users.php">View All</a>
                        </div>
                    </div>
                    
                    <ul class="user-list">
                        <?php foreach ($recent_users as $recent_user): ?>
                        <li class="user-item">
                            <div class="user-avatar"><?php echo strtoupper(substr($recent_user['first_name'], 0, 1) . substr($recent_user['last_name'], 0, 1)); ?></div>
                            <div class="user-content">
                                <div class="user-name"><?php echo htmlspecialchars($recent_user['first_name'] . ' ' . $recent_user['last_name']); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($recent_user['email']); ?></div>
                                <div class="user-role"><?php echo ucfirst($recent_user['role']); ?></div>
                            </div>
                            <div class="user-actions">
                                <a href="users.php?edit=<?php echo $recent_user['id']; ?>" class="btn-small">Edit</a>
<a href="?delete_user=<?php echo $recent_user['id']; ?>" class="btn-small danger" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Recent Messages -->
                                <div class="content-card">
                                    <div class="card-header">
                                        <h3 class="card-title">Recent Contact Messages</h3>
                                        <div class="card-actions">
                                            <a href="messages.php">View All</a>
                                        </div>
                                    </div>
                    
                    <ul class="message-list">
                        <?php foreach ($recent_messages as $message): ?>
                        <li class="message-item">
                            <div class="message-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="message-content">
                                <div class="message-subject"><?php echo htmlspecialchars($message['subject'] ?: 'No Subject'); ?></div>
                                <div class="message-email"><?php echo htmlspecialchars($message['name'] . ' (' . $message['email'] . ')'); ?></div>
                                <div class="message-date"><?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?></div>
                            </div>
                            <div class="message-actions">
                                <a href="messages.php?view=<?php echo $message['id']; ?>" class="btn-small">View</a>
                                <a href="messages.php?delete=<?php echo $message['id']; ?>" class="btn-small danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

<script>
        // Initialize Pusher for realtime updates
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Pusher is available
            if (typeof Pusher !== 'undefined') {
                // Get Pusher configuration from server
                fetch('../includes/pusher-config.php')
                    .then(response => response.json())
                    .then(config => {
                        // Initialize Pusher
                        const pusher = new Pusher(config.key, {
                            cluster: config.cluster,
                            encrypted: config.encrypted
                        });
                        
                        // Subscribe to the main notifications channel
                        const channel = pusher.subscribe('church-notifications');
                        
                        // Bind to new user events
                        channel.bind('new_user', function(data) {
                            // Update user count
                            const userCountElement = document.querySelector('.stat-card:nth-child(1) .stat-value');
                            if (userCountElement) {
                                const currentCount = parseInt(userCountElement.textContent.replace(/,/g, ''));
                                userCountElement.textContent = (currentCount + 1).toLocaleString();
                            }
                            
                            // Show notification
                            showNotification('New Member Joined', `${data.data.first_name} ${data.data.last_name} has joined the fellowship!`, 'success');
                        });
                        
                        // Bind to prayer request events
                        channel.bind('prayer_request', function(data) {
                            // Update prayer count
                            const prayerCountElement = document.querySelector('.stat-card:nth-child(4) .stat-value');
                            if (prayerCountElement) {
                                const currentCount = parseInt(prayerCountElement.textContent.replace(/,/g, ''));
                                prayerCountElement.textContent = (currentCount + 1).toLocaleString();
                            }
                            
                            // Show notification
                            showNotification('Prayer Request', data.message, 'info');
                        });
                        
                        // Bind to event update events
                        channel.bind('event_update', function(data) {
                            showNotification('Event Update', data.message, 'info');
                        });
                        
                        // Bind to sermon update events
                        channel.bind('sermon_update', function(data) {
                            // Update sermon count
                            const sermonCountElement = document.querySelector('.stat-card:nth-child(3) .stat-value');
                            if (sermonCountElement) {
                                const currentCount = parseInt(sermonCountElement.textContent.replace(/,/g, ''));
                                sermonCountElement.textContent = (currentCount + 1).toLocaleString();
                            }
                            
                            // Show notification
                            showNotification('New Sermon', data.message, 'success');
                        });
                    })
                    .catch(error => {
                        console.error('Failed to initialize Pusher:', error);
                    });
            }
            
            // Function to show notifications
            function showNotification(title, message, type = 'info') {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `pusher-notification pusher-notification-${type}`;
                notification.innerHTML = `
                    <div class="notification-header">
                        <strong>${title}</strong>
                        <button class="notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
                    </div>
                    <div class="notification-message">${message}</div>
                `;
                
                // Get or create notifications container
                let container = document.getElementById('pusher-notifications');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'pusher-notifications';
                    container.className = 'pusher-notifications-container';
                    document.body.appendChild(container);
                }
                
                // Add notification
                container.appendChild(notification);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 5000);
            }
            
            // Add CSS for notifications if not already present
            if (!document.getElementById('pusher-notifications-css')) {
                const style = document.createElement('style');
                style.id = 'pusher-notifications-css';
                style.textContent = `
                    .pusher-notifications-container {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 9999;
                        max-width: 400px;
                    }
                    
                    .pusher-notification {
                        background: white;
                        border-radius: 8px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        margin-bottom: 10px;
                        padding: 15px;
                        border-left: 4px solid #6d4c41;
                        animation: slideInRight 0.3s ease-out;
                    }
                    
                    .pusher-notification-success {
                        border-left-color: #28a745;
                    }
                    
                    .pusher-notification-info {
                        border-left-color: #17a2b8;
                    }
                    
                    .pusher-notification-warning {
                        border-left-color: #ffc107;
                    }
                    
                    .pusher-notification-error {
                        border-left-color: #dc3545;
                    }
                    
                    .notification-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 8px;
                    }
                    
                    .notification-close {
                        background: none;
                        border: none;
                        font-size: 18px;
                        cursor: pointer;
                        color: #666;
                        padding: 0;
                        width: 20px;
                        height: 20px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    
                    .notification-close:hover {
                        color: #333;
                    }
                    
                    .notification-message {
                        color: #333;
                        font-size: 14px;
                        line-height: 1.4;
                    }
                    
                    @keyframes slideInRight {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    
                    @media (max-width: 768px) {
                        .pusher-notifications-container {
                            right: 10px;
                            left: 10px;
                            max-width: none;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
        });
    </script>
