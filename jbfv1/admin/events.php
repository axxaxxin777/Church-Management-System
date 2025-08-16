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

// Handle event management actions
$message = '';
$message_type = '';

// Add/Edit event
if (isset($_POST['save_event'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = trim($_POST['event_date']);
    $event_time = trim($_POST['event_time']);
    $location = trim($_POST['location']);
    $category = trim($_POST['category']);
    $max_attendees = intval($_POST['max_attendees']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Simple validation
    if (empty($title) || empty($event_date) || empty($event_time)) {
        $message = 'Title, date, and time are required.';
        $message_type = 'error';
    } else {
        if (isset($_POST['event_id']) && !empty($_POST['event_id'])) {
            // Update existing event
            $event_id = intval($_POST['event_id']);
            $stmt = $pdo->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, event_time = ?, location = ?, category = ?, max_attendees = ?, is_featured = ? WHERE id = ?");
            $result = $stmt->execute([$title, $description, $event_date, $event_time, $location, $category, $max_attendees, $is_featured, $event_id]);
            
            if ($result) {
                $message = 'Event updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error updating event.';
                $message_type = 'error';
            }
        } else {
            // Add new event
            $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, event_time, location, category, max_attendees, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$title, $description, $event_date, $event_time, $location, $category, $max_attendees, $is_featured]);
            
            if ($result) {
                $message = 'Event added successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error adding event.';
                $message_type = 'error';
            }
        }
    }
}

// Delete event
if (isset($_GET['delete_event'])) {
    $event_id = intval($_GET['delete_event']);
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    if ($stmt->execute([$event_id])) {
        $message = 'Event deleted successfully.';
        $message_type = 'success';
    } else {
        $message = 'Error deleting event.';
        $message_type = 'error';
    }
}

// Get event for editing
$edit_event = null;
if (isset($_GET['edit_event'])) {
    $event_id = intval($_GET['edit_event']);
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $edit_event = $stmt->fetch();
}

// Get all events
$stmt = $pdo->query("SELECT * FROM events ORDER BY event_date DESC, event_time DESC");
$events = $stmt->fetchAll();

// Get event statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_events FROM events");
$total_events = $stmt->fetch()['total_events'];

$stmt = $pdo->query("SELECT SUM(current_attendees) as total_attendees, SUM(max_attendees) as total_capacity FROM events");
$event_stats = $stmt->fetch();
$total_attendees = $event_stats['total_attendees'] ?? 0;
$total_capacity = $event_stats['total_capacity'] ?? 0;
$attendance_rate = $total_capacity > 0 ? round(($total_attendees / $total_capacity) * 100, 1) : 0;

// Get recent contact messages for sidebar badge
$stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5");
$recent_messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Admin Panel</title>
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
            grid-template-columns: 1fr;
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

        .event-list {
            list-style: none;
        }

        .event-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--light);
        }

        .event-item:last-child {
            border-bottom: none;
        }

        .event-icon {
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

        .event-content {
            flex: 1;
        }

        .event-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .event-date {
            font-size: 0.85rem;
            color: var(--secondary);
            margin-bottom: 0.25rem;
        }

        .event-attendees {
            font-size: 0.75rem;
            color: var(--secondary);
        }

        .event-actions {
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--primary);
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: var(--text);
        }
        
        .event-form {
            padding: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text);
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-group textarea {
            resize: vertical;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: normal;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: var(--white);
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
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
                <a href="index.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="events.php" class="menu-item active">
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
                    <h1>Manage Events</h1>
                    <p>Manage all events in the system</p>
                </div>
                <div class="page-actions">
                    <a href="#" class="btn" onclick="showEventForm()">
                        <i class="fas fa-plus"></i> Add New Event
                    </a>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 5px; <?php echo $message_type === 'success' ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_events); ?></div>
                    <div class="stat-title">Total Events</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_attendees); ?></div>
                    <div class="stat-title">Total Attendees</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                    <div class="stat-title">Attendance Rate</div>
                </div>
            </div>
            
            <!-- Event Form Modal -->
            <div id="eventFormModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="eventFormTitle">Add New Event</h2>
                        <span class="close" onclick="closeEventForm()">&times;</span>
                    </div>
                    <form method="POST" class="event-form">
                        <input type="hidden" name="event_id" id="event_id" value="">
                        <div class="form-group">
                            <label for="title">Event Title *</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="event_date">Event Date *</label>
                                <input type="date" id="event_date" name="event_date" required>
                            </div>
                            <div class="form-group">
                                <label for="event_time">Event Time *</label>
                                <input type="time" id="event_time" name="event_time" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" id="location" name="location">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" name="category">
                                    <option value="Service">Service</option>
                                    <option value="Conference">Conference</option>
                                    <option value="Youth">Youth</option>
                                    <option value="Outreach">Outreach</option>
                                    <option value="Bible Study">Bible Study</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="max_attendees">Max Attendees</label>
                                <input type="number" id="max_attendees" name="max_attendees" min="1" value="100">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="is_featured" name="is_featured">
                                <span class="checkmark"></span>
                                Featured Event
                            </label>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeEventForm()">Cancel</button>
                            <button type="submit" name="save_event" class="btn btn-primary">Save Event</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Events List -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">All Events</h3>
                        <div class="card-actions">
                            <a href="#">View All</a>
                        </div>
                    </div>
                    
                    <ul class="event-list">
                        <?php foreach ($events as $event): ?>
                        <li class="event-item">
                            <div class="event-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="event-content">
                                <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                <div class="event-date"><?php echo date('M j, Y g:i A', strtotime($event['event_date'] . ' ' . $event['event_time'])); ?></div>
                                <div class="event-attendees"><?php echo $event['current_attendees']; ?>/<?php echo $event['max_attendees']; ?> attendees</div>
                            </div>
                            <div class="event-actions">
                                <a href="#" class="btn-small" onclick="editEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)">Edit</a>
                                <a href="events.php?delete_event=<?php echo $event['id']; ?>" class="btn-small danger" onclick="return confirm('Are you sure you want to delete this event?')">Delete</a>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Event form functionality
        function showEventForm() {
            document.getElementById('eventFormModal').style.display = 'block';
            document.getElementById('eventFormTitle').textContent = 'Add New Event';
            document.getElementById('event_id').value = '';
            document.getElementById('title').value = '';
            document.getElementById('description').value = '';
            document.getElementById('event_date').value = '';
            document.getElementById('event_time').value = '';
            document.getElementById('location').value = '';
            document.getElementById('category').value = 'Service';
            document.getElementById('max_attendees').value = '100';
            document.getElementById('is_featured').checked = false;
        }
        
        function closeEventForm() {
            document.getElementById('eventFormModal').style.display = 'none';
        }
        
        function editEvent(event) {
            document.getElementById('eventFormModal').style.display = 'block';
            document.getElementById('eventFormTitle').textContent = 'Edit Event';
            document.getElementById('event_id').value = event.id;
            document.getElementById('title').value = event.title;
            document.getElementById('description').value = event.description || '';
            document.getElementById('event_date').value = event.event_date;
            document.getElementById('event_time').value = event.event_time;
            document.getElementById('location').value = event.location || '';
            document.getElementById('category').value = event.category || 'Service';
            document.getElementById('max_attendees').value = event.max_attendees || 100;
            document.getElementById('is_featured').checked = event.is_featured == 1;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('eventFormModal');
            if (event.target == modal) {
                closeEventForm();
            }
        }
        
        // Auto-populate form if editing event is set
        <?php if ($edit_event): ?>
        window.onload = function() {
            editEvent(<?php echo json_encode($edit_event); ?>);
        }
        <?php endif; ?>
    </script>
</body>
</html>