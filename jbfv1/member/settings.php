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

// Handle password change
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect.';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $message = 'Password changed successfully!';
            
            // Clear form data
            $current_password = '';
            $new_password = '';
            $confirm_password = '';
        } catch (Exception $e) {
            $error = 'Error changing password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grace Community Church - Settings</title>
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

        /* Settings Content */
        .settings-content {
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

        /* Settings Sections */
        .settings-section {
            background-color: var(--white);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
        }

        .settings-section h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background-color: var(--white);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(109, 76, 65, 0.1);
        }

        .form-actions {
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid var(--light);
        }

        .settings-btn {
            background-color: var(--primary);
            color: var(--white);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .settings-btn:hover {
            background-color: var(--dark);
            transform: translateY(-2px);
        }

        .settings-btn.secondary {
            background-color: var(--secondary);
            margin-left: 1rem;
        }

        .settings-btn.secondary:hover {
            background-color: var(--dark);
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

            .form-actions {
                text-align: center;
            }

            .settings-btn {
                display: block;
                width: 100%;
                margin: 0.5rem 0;
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
                </a>
                
                <p class="menu-title">Personal</p>
                <a href="profile.php" class="menu-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="settings.php" class="menu-item active">
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
                    <h1>Settings</h1>
                    <p>Manage your account preferences and security</p>
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

            <div class="settings-content">
                <!-- Password Change -->
                <section class="settings-section">
                    <h3>Change Password</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required minlength="6">
                            <small style="color: var(--secondary); font-size: 0.8rem;">Password must be at least 6 characters long.</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="6">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="change_password" class="settings-btn">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Account Information -->
                <section class="settings-section">
                    <h3>Account Information</h3>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <small style="color: var(--secondary); font-size: 0.8rem;">Email address cannot be changed. Contact admin if needed.</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Member Since</label>
                        <input type="text" class="form-control" value="<?php echo $user['member_since'] ? date('F j, Y', strtotime($user['member_since'])) : 'Recently'; ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <input type="text" class="form-control" value="<?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>" disabled>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="settings-btn secondary" onclick="window.location.href='profile.php'">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </button>
                    </div>
                </section>

                <!-- Notification Preferences -->
                <section class="settings-section">
                    <h3>Notification Preferences</h3>
                    <p style="color: var(--secondary); margin-bottom: 1rem;">Manage how you receive notifications from our church community.</p>
                    
                    <div class="form-group">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="email_notifications" checked disabled>
                            <label for="email_notifications">Email notifications for important updates</label>
                        </div>
                        <small style="color: var(--secondary); font-size: 0.8rem;">Email notifications are always enabled for important church communications.</small>
                    </div>
                    
                    <div class="form-group">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="prayer_notifications" checked>
                            <label for="prayer_notifications">Real-time prayer request notifications</label>
                        </div>
                        <small style="color: var(--secondary); font-size: 0.8rem;">Get notified when new prayer requests are submitted.</small>
                    </div>
                    
                    <div class="form-group">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="event_reminders" checked>
                            <label for="event_reminders">Event reminders and updates</label>
                        </div>
                        <small style="color: var(--secondary); font-size: 0.8rem;">Receive reminders for events you're registered for.</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="settings-btn">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                </section>
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
