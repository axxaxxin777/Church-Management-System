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

// Handle profile update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, city = ?, state = ?, zip_code = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $phone, $address, $city, $state, $zip_code, $user_id]);
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            $message = 'Profile updated successfully!';
        } catch (Exception $e) {
            $error = 'Error updating profile. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grace Community Church - My Profile</title>
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

        /* Profile Content */
        .profile-content {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 2rem;
            text-align: center;
        }

        .profile-avatar {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .avatar-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--primary);
            margin: 0 auto;
            border: 4px solid var(--white);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .avatar-edit {
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: var(--accent);
            color: var(--primary);
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .avatar-edit:hover {
            background-color: var(--light);
            transform: scale(1.1);
        }

        .profile-info h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .profile-info p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .profile-meta {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
        }

        .meta-item {
            text-align: center;
        }

        .meta-item .number {
            font-size: 1.5rem;
            font-weight: 600;
            display: block;
        }

        .meta-item .label {
            font-size: 0.8rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Profile Tabs */
        .profile-tabs {
            display: flex;
            background-color: var(--light);
            border-bottom: 1px solid #e0e0e0;
        }

        .profile-tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--secondary);
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .profile-tab:hover {
            background-color: rgba(109, 76, 65, 0.1);
        }

        .profile-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background-color: var(--white);
        }

        /* Profile Content Sections */
        .profile-section {
            padding: 2rem;
            display: none;
        }

        .profile-section.active {
            display: block;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
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
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(109, 76, 65, 0.1);
        }

        .form-control:disabled {
            background-color: #f5f5f5;
            color: #666;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }

        .form-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-btn.primary {
            background-color: var(--primary);
            color: var(--white);
        }

        .form-btn.primary:hover {
            background-color: var(--dark);
            transform: translateY(-2px);
        }

        .form-btn.secondary {
            background-color: transparent;
            color: var(--secondary);
            border: 2px solid var(--secondary);
        }

        .form-btn.secondary:hover {
            background-color: var(--secondary);
            color: var(--white);
        }

        .form-btn.outline {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .form-btn.outline:hover {
            background-color: var(--primary);
            color: var(--white);
        }

        /* Family Members */
        .family-members {
            margin-top: 2rem;
        }

        .family-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .family-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin: 0;
        }

        .family-list {
            list-style: none;
        }

        .family-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background-color: var(--light);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .family-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .family-info {
            flex: 1;
        }

        .family-info h4 {
            margin: 0 0 0.25rem 0;
            color: var(--dark);
        }

        .family-info p {
            margin: 0;
            color: var(--secondary);
            font-size: 0.8rem;
        }

        .family-actions {
            display: flex;
            gap: 0.5rem;
        }

        .family-action {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--secondary);
        }

        .family-action:hover {
            background-color: var(--accent);
            color: var(--primary);
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

            .profile-meta {
                flex-direction: column;
                gap: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
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
                <a href="profile.php" class="menu-item active">
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
                    <h1>My Profile</h1>
                    <p>Manage your personal information and preferences</p>
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

            <div class="profile-content">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <div class="avatar-image">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                        <button class="avatar-edit" title="Change Avatar">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                        <p>Member since <?php echo $user['member_since'] ? date('F Y', strtotime($user['member_since'])) : 'Recently'; ?></p>
                    </div>
                    <div class="profile-meta">
                        <div class="meta-item">
                            <span class="number"><?php echo $user['role'] === 'admin' ? 'Admin' : 'Member'; ?></span>
                            <span class="label">Role</span>
                        </div>
                        <div class="meta-item">
                            <span class="number"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span>
                            <span class="label">Status</span>
                        </div>
                    </div>
                </div>

                <div class="profile-tabs">
                    <button class="profile-tab active" data-tab="personal">Personal Info</button>
                    <button class="profile-tab" data-tab="contact">Contact</button>
                    <button class="profile-tab" data-tab="preferences">Preferences</button>
                </div>

                <!-- Personal Information Tab -->
                <div class="profile-section active" id="personal">
                    <form method="POST" action="">
                        <div class="form-section">
                            <h3 class="form-section-title">Basic Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <small style="color: #666; font-size: 0.8rem;">Email cannot be changed. Contact admin if needed.</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Member Since</label>
                                <input type="date" class="form-control" value="<?php echo $user['member_since'] ?? date('Y-m-d'); ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3 class="form-section-title">Contact Information</h3>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">State/Province</label>
                                    <input type="text" class="form-control" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">ZIP/Postal Code</label>
                                    <input type="text" class="form-control" name="zip_code" value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="form-btn secondary" onclick="window.location.reload()">
                                Cancel
                            </button>
                            <button type="submit" class="form-btn primary">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Contact Tab -->
                <div class="profile-section" id="contact">
                    <div class="form-section">
                        <h3 class="form-section-title">Contact Preferences</h3>
                        <p>This section will contain contact preferences and communication settings.</p>
                    </div>
                </div>

                <!-- Preferences Tab -->
                <div class="profile-section" id="preferences">
                    <div class="form-section">
                        <h3 class="form-section-title">Account Preferences</h3>
                        <p>This section will contain account preferences and notification settings.</p>
                    </div>
                </div>
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
            
            // Tab switching
            const profileTabs = document.querySelectorAll('.profile-tab');
            const profileSections = document.querySelectorAll('.profile-section');
            
            profileTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and sections
                    profileTabs.forEach(t => t.classList.remove('active'));
                    profileSections.forEach(s => s.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding section
                    this.classList.add('active');
                    document.getElementById(targetTab).classList.add('active');
                });
            });
            
            // Avatar edit
            const avatarEdit = document.querySelector('.avatar-edit');
            avatarEdit.addEventListener('click', function() {
                alert('Avatar edit functionality would go here');
            });
        });
    </script>
</body>
</html>
