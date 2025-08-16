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

// Handle sermon management actions
$message = '';
$message_type = '';

// Add/Edit sermon
if (isset($_POST['save_sermon'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $video_url = trim($_POST['video_url']);
    $speaker = trim($_POST['speaker']);
    $sermon_date = trim($_POST['sermon_date']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Simple validation
    if (empty($title) || empty($video_url)) {
        $message = 'Title and video URL are required.';
        $message_type = 'error';
    } else {
        // Generate embed code from YouTube URL if needed
        $video_embed_code = '';
        if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
            // Extract video ID from various YouTube URL formats
            $video_id = '';
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches)) {
                $video_id = $matches[1];
                $video_embed_code = '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            }
        }
        
        if (isset($_POST['sermon_id']) && !empty($_POST['sermon_id'])) {
            // Update existing sermon
            $sermon_id = intval($_POST['sermon_id']);
            $stmt = $pdo->prepare("UPDATE sermons SET title = ?, description = ?, video_url = ?, video_embed_code = ?, speaker = ?, sermon_date = ?, is_featured = ? WHERE id = ?");
            $result = $stmt->execute([$title, $description, $video_url, $video_embed_code, $speaker, $sermon_date, $is_featured, $sermon_id]);
            
            if ($result) {
                $message = 'Sermon updated successfully.';
                $message_type = 'success';
                
                // Send real-time notification for sermon update
                require_once '../includes/pusher.php';
                $pusher = getPusher();
                if ($pusher->isAvailable()) {
                    $pusher->notifySermon([
                        'id' => $sermon_id,
                        'title' => $title,
                        'description' => $description,
                        'speaker' => $speaker,
                        'sermon_date' => $sermon_date
                    ]);
                }
            } else {
                $message = 'Error updating sermon.';
                $message_type = 'error';
            }
        } else {
            // Add new sermon
            $stmt = $pdo->prepare("INSERT INTO sermons (title, description, video_url, video_embed_code, speaker, sermon_date, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$title, $description, $video_url, $video_embed_code, $speaker, $sermon_date, $is_featured]);
            
            if ($result) {
                $message = 'Sermon added successfully.';
                $message_type = 'success';
                
                // Send real-time notification for new sermon
                require_once '../includes/pusher.php';
                $pusher = getPusher();
                if ($pusher->isAvailable()) {
                    $sermon_id = $pdo->lastInsertId();
                    $pusher->notifySermon([
                        'id' => $sermon_id,
                        'title' => $title,
                        'description' => $description,
                        'speaker' => $speaker,
                        'sermon_date' => $sermon_date
                    ]);
                }
            } else {
                $message = 'Error adding sermon.';
                $message_type = 'error';
            }
        }
    }
}

// Delete sermon
if (isset($_GET['delete_sermon'])) {
    $sermon_id = intval($_GET['delete_sermon']);
    $stmt = $pdo->prepare("DELETE FROM sermons WHERE id = ?");
    if ($stmt->execute([$sermon_id])) {
        $message = 'Sermon deleted successfully.';
        $message_type = 'success';
        
        // Send real-time notification for sermon deletion
        require_once '../includes/pusher.php';
        $pusher = getPusher();
        if ($pusher->isAvailable()) {
            $pusher->notifyAll('sermon_deleted', [
                'id' => $sermon_id,
                'message' => 'A sermon has been removed from the library.'
            ]);
        }
    } else {
        $message = 'Error deleting sermon.';
        $message_type = 'error';
    }
}

// Get all sermons
$stmt = $pdo->query("SELECT * FROM sermons ORDER BY created_at DESC");
$sermons = $stmt->fetchAll();

// Get sermon statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_sermons FROM sermons");
$total_sermons = $stmt->fetch()['total_sermons'];

$stmt = $pdo->query("SELECT SUM(views) as total_views FROM sermons");
$total_views = $stmt->fetch()['total_views'] ?? 0;

// Get recent contact messages for sidebar badge
$stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5");
$recent_messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sermons - Admin Panel</title>
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

        .sermon-list {
            list-style: none;
        }

        .sermon-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--light);
        }

        .sermon-item:last-child {
            border-bottom: none;
        }

        .sermon-icon {
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

        .sermon-content {
            flex: 1;
        }

        .sermon-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .sermon-date {
            font-size: 0.85rem;
            color: var(--secondary);
            margin-bottom: 0.25rem;
        }

        .sermon-views {
            font-size: 0.75rem;
            color: var(--secondary);
        }

        .sermon-actions {
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
                <a href="events.php" class="menu-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Events</span>
                </a>
                <a href="sermons.php" class="menu-item active">
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
                    <h1>Manage Sermons</h1>
                    <p>Manage all sermons in the system</p>
                </div>
                <div class="page-actions">
                    <button id="addSermonBtn" class="btn">
                        <i class="fas fa-plus"></i> Add New Sermon
                    </button>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 5px; <?php echo $message_type === 'success' ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Add/Edit Sermon Form (Hidden by default) -->
            <div id="sermonFormContainer" style="display: none; background-color: var(--white); border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); margin-bottom: 1.5rem;">
                <h3 id="formTitle" style="font-family: 'Playfair Display', serif; color: var(--primary); margin-bottom: 1rem;">Add New Sermon</h3>
                <form id="sermonForm" method="POST" action="">
                    <input type="hidden" name="sermon_id" id="sermon_id">
                    <div style="margin-bottom: 1rem;">
                        <label for="title" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Title *</label>
                        <input type="text" id="title" name="title" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--accent); border-radius: 5px; font-family: 'Montserrat', sans-serif;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label for="description" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Description</label>
                        <textarea id="description" name="description" style="width: 100%; padding: 0.75rem; border: 1px solid var(--accent); border-radius: 5px; font-family: 'Montserrat', sans-serif; height: 100px;"></textarea>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label for="video_url" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Video URL *</label>
                        <input type="url" id="video_url" name="video_url" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--accent); border-radius: 5px; font-family: 'Montserrat', sans-serif;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label for="speaker" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Speaker</label>
                        <input type="text" id="speaker" name="speaker" style="width: 100%; padding: 0.75rem; border: 1px solid var(--accent); border-radius: 5px; font-family: 'Montserrat', sans-serif;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label for="sermon_date" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Sermon Date</label>
                        <input type="date" id="sermon_date" name="sermon_date" style="width: 100%; padding: 0.75rem; border: 1px solid var(--accent); border-radius: 5px; font-family: 'Montserrat', sans-serif;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; font-weight: 600;">
                            <input type="checkbox" id="is_featured" name="is_featured" value="1" style="margin-right: 0.5rem;">
                            Featured Sermon
                        </label>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" name="save_sermon" class="btn" style="flex: 1;">Save Sermon</button>
                        <button type="button" id="cancelFormBtn" class="btn" style="background-color: var(--secondary); flex: 1;">Cancel</button>
                    </div>
                </form>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-video"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_sermons); ?></div>
                    <div class="stat-title">Total Sermons</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon success">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_views); ?></div>
                    <div class="stat-title">Total Views</div>
                </div>
            </div>
            
            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Sermons List -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">All Sermons</h3>
                        <div class="card-actions">
                            <a href="#">View All</a>
                        </div>
                    </div>
                    
                    <ul class="sermon-list">
                        <?php foreach ($sermons as $sermon): ?>
                        <li class="sermon-item">
                            <div class="sermon-icon">
                                <i class="fas fa-video"></i>
                            </div>
                            <div class="sermon-content">
                                <div class="sermon-title"><?php echo htmlspecialchars($sermon['title']); ?></div>
                                <div class="sermon-date"><?php echo date('M j, Y', strtotime($sermon['created_at'])); ?></div>
                                <div class="sermon-views"><?php echo $sermon['views']; ?> views</div>
                            </div>
                            <div class="sermon-actions">
<a href="#" class="btn-small" data-sermon-id="<?php echo $sermon['id']; ?>">Edit</a>
                                <a href="#" class="btn-small">Edit</a>
                                <a href="sermons.php?delete_sermon=<?php echo $sermon['id']; ?>" class="btn-small danger" onclick="return confirm('Are you sure you want to delete this sermon?')">Delete</a>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Handle Add New Sermon button click
        document.getElementById('addSermonBtn').addEventListener('click', function() {
            // Reset form
            document.getElementById('sermonForm').reset();
            document.getElementById('sermon_id').value = '';
            document.getElementById('formTitle').textContent = 'Add New Sermon';
            
            // Show form
            document.getElementById('sermonFormContainer').style.display = 'block';
            
            // Scroll to form
            document.getElementById('sermonFormContainer').scrollIntoView({ behavior: 'smooth' });
        });
        
        // Handle Cancel button click
        document.getElementById('cancelFormBtn').addEventListener('click', function() {
            // Hide form
            document.getElementById('sermonFormContainer').style.display = 'none';
        });
        
// Handle Edit button clicks
        document.querySelectorAll('.sermon-actions .btn-small:first-child').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get sermon ID from data attribute
                const sermonId = this.getAttribute('data-sermon-id');
                
                // Fetch sermon data from server
                fetch('get_sermon.php?id=' + sermonId)
                    .then(response => response.json())
                    .then(sermon => {
                        // Populate form with sermon data
                        document.getElementById('sermon_id').value = sermon.id;
                        document.getElementById('title').value = sermon.title;
                        document.getElementById('description').value = sermon.description;
                        document.getElementById('video_url').value = sermon.video_url;
                        document.getElementById('speaker').value = sermon.speaker;
                        document.getElementById('sermon_date').value = sermon.sermon_date;
                        document.getElementById('is_featured').checked = sermon.is_featured == 1;
                        
                        // Update form title
                        document.getElementById('formTitle').textContent = 'Edit Sermon';
                        
                        // Show form
                        document.getElementById('sermonFormContainer').style.display = 'block';
                        
                        // Scroll to form
                        document.getElementById('sermonFormContainer').scrollIntoView({ behavior: 'smooth' });
                    })
                    .catch(error => {
                        console.error('Error fetching sermon:', error);
                        alert('Error fetching sermon data.');
                    });
            });
        });
        // Handle Edit button clicks
        document.querySelectorAll('.sermon-actions .btn-small:first-child').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get sermon ID from data attribute
                const sermonId = this.getAttribute('data-sermon-id');
                
                // Fetch sermon data from server
                fetch('get_sermon.php?id=' + sermonId)
                    .then(response => response.json())
                    .then(sermon => {
                        // Populate form with sermon data
                        document.getElementById('sermon_id').value = sermon.id;
                        document.getElementById('title').value = sermon.title;
                        document.getElementById('description').value = sermon.description;
                        document.getElementById('video_url').value = sermon.video_url;
                        document.getElementById('speaker').value = sermon.speaker;
                        document.getElementById('sermon_date').value = sermon.sermon_date;
                        document.getElementById('is_featured').checked = sermon.is_featured == 1;
                        
                        // Update form title
                        document.getElementById('formTitle').textContent = 'Edit Sermon';
                        
                        // Show form
                        document.getElementById('sermonFormContainer').style.display = 'block';
                        
                        // Scroll to form
                        document.getElementById('sermonFormContainer').scrollIntoView({ behavior: 'smooth' });
                    })
                    .catch(error => {
                        console.error('Error fetching sermon:', error);
                        alert('Error fetching sermon data.');
                    });
            });
        });
    </script>
</body>
</html>