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

// Handle settings management actions
$message = '';
$message_type = '';

// Update settings
if (isset($_POST['save_settings'])) {
    $settings_to_update = [
        'church_name' => trim($_POST['church_name']),
        'church_tagline' => trim($_POST['church_tagline']),
        'church_address' => trim($_POST['church_address']),
        'church_phone' => trim($_POST['church_phone']),
        'church_email' => trim($_POST['church_email']),
        'sunday_service_time' => trim($_POST['sunday_service_time']),
        'bible_study_time' => trim($_POST['bible_study_time']),
        'youth_group_time' => trim($_POST['youth_group_time'])
    ];
    
    $success = true;
    foreach ($settings_to_update as $key => $value) {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        if (!$stmt->execute([$value, $key])) {
            $success = false;
            break;
        }
    }
    
    if ($success) {
        $message = 'Settings updated successfully.';
        $message_type = 'success';
    } else {
        $message = 'Error updating settings.';
        $message_type = 'error';
    }
}

// Get system settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get settings statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_settings FROM settings");
$total_settings = $stmt->fetch()['total_settings'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Panel</title>
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

        /* Settings Form */
        .settings-form {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 1px solid var(--accent);
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            background-color: var(--white);
            color: var(--text);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(109, 76, 65, 0.2);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .settings-form {
            padding: 1.5rem;
        }
        
        .settings-form .form-group {
            margin-bottom: 1.5rem;
        }
        
        .settings-form .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text);
        }
        
        .settings-form .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .settings-form .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(109, 76, 65, 0.2);
        }

        .btn-secondary {
            padding: 0.6rem 1.5rem;
            background-color: var(--secondary);
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

        .btn-secondary:hover {
            background-color: #6d4c41;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
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
            
            .form-actions {
                flex-direction: column;
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
                <a href="sermons.php" class="menu-item">
                    <i class="fas fa-video"></i>
                    <span>Sermons</span>
                </a>
                <a href="prayers.php" class="menu-item">
                    <i class="fas fa-pray"></i>
                    <span>Prayer Requests</span>
                </a>
                
                <p class="menu-title">System</p>
                <a href="settings.php" class="menu-item active">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="messages.php" class="menu-item">
                    <i class="fas fa-envelope"></i>
                    <span>Contact Messages</span>
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
                    <h1>System Settings</h1>
                    <p>Manage system configuration and preferences</p>
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
                            <i class="fas fa-cog"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_settings; ?></div>
                    <div class="stat-title">Settings</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value">Active</div>
                    <div class="stat-title">System Status</div>
                </div>
            </div>
            
            <!-- Content Grid -->
            <div class="content-grid">
                <!-- General Settings -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">General Settings</h3>
                    </div>
                    
                    <form class="settings-form" method="POST">
                        <div class="form-group">
                            <label for="church_name">Church Name</label>
                            <input type="text" id="church_name" name="church_name" value="<?php echo htmlspecialchars($settings['church_name'] ?? 'Joy Bible Fellowship'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="church_tagline">Church Tagline</label>
                            <input type="text" id="church_tagline" name="church_tagline" value="<?php echo htmlspecialchars($settings['church_tagline'] ?? 'Spreading Joy Through God\'s Word'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="church_address">Church Address</label>
                            <input type="text" id="church_address" name="church_address" value="<?php echo htmlspecialchars($settings['church_address'] ?? '123 Joy Street, Bible City, BC 12345'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="church_phone">Church Phone</label>
                            <input type="text" id="church_phone" name="church_phone" value="<?php echo htmlspecialchars($settings['church_phone'] ?? '(123) 456-7890'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="church_email">Church Email</label>
                            <input type="email" id="church_email" name="church_email" value="<?php echo htmlspecialchars($settings['church_email'] ?? 'info@joybiblefellowship.org'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="sunday_service_time">Sunday Service Time</label>
                            <input type="text" id="sunday_service_time" name="sunday_service_time" value="<?php echo htmlspecialchars($settings['sunday_service_time'] ?? '9:00 AM & 11:00 AM'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="bible_study_time">Bible Study Time</label>
                            <input type="text" id="bible_study_time" name="bible_study_time" value="<?php echo htmlspecialchars($settings['bible_study_time'] ?? 'Wednesdays at 7:00 PM'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="youth_group_time">Youth Group Time</label>
                            <input type="text" id="youth_group_time" name="youth_group_time" value="<?php echo htmlspecialchars($settings['youth_group_time'] ?? 'Fridays at 7:00 PM'); ?>">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="save_settings" class="btn">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>