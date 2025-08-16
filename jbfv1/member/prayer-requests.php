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

// Handle prayer request submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $title = trim($_POST['title'] ?? '');
    $request_text = trim($_POST['request_text'] ?? '');
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    if (empty($request_text)) {
        $error = 'Prayer request text is required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO prayer_requests (user_id, title, request_text, is_anonymous) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $request_text, $is_anonymous]);
            
            $message = 'Prayer request submitted successfully!';
            
            // Clear form data
            $title = '';
            $request_text = '';
            $is_anonymous = 0;
        } catch (Exception $e) {
            $error = 'Error submitting prayer request. Please try again.';
        }
    }
}

// Get prayer requests with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM prayer_requests");
$total_requests = $stmt->fetch()['total'];
$total_pages = ceil($total_requests / $limit);

// Get prayer requests
$stmt = $pdo->prepare("SELECT pr.*, u.first_name, u.last_name 
                       FROM prayer_requests pr 
                       LEFT JOIN users u ON pr.user_id = u.id 
                       ORDER BY pr.created_at DESC 
                       LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$prayer_requests = $stmt->fetchAll();

// Get user's own prayer requests
$stmt = $pdo->prepare("SELECT * FROM prayer_requests WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$user_requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grace Community Church - Prayer Requests</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
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

        /* Prayer Requests Content */
        .prayer-content {
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

        /* Submit Form */
        .submit-section {
            background-color: var(--white);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
        }

        .submit-section h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 1.5rem;
            text-align: center;
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

        .form-control.textarea {
            min-height: 120px;
            resize: vertical;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .form-actions {
            text-align: center;
        }

        .submit-btn {
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

        .submit-btn:hover {
            background-color: var(--dark);
            transform: translateY(-2px);
        }

        /* My Requests Section */
        .my-requests-section {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .my-requests-section h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .my-requests-grid {
            display: grid;
            gap: 1rem;
        }

        .my-request-card {
            background-color: var(--light);
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid var(--info);
        }

        .my-request-card h4 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .my-request-card p {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .my-request-card .request-date {
            font-size: 0.8rem;
            color: var(--secondary);
        }

        .my-request-card .request-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .request-status.answered {
            background-color: var(--success);
            color: var(--white);
        }

        .request-status.pending {
            background-color: var(--warning);
            color: var(--white);
        }

        /* Prayer Requests Grid */
        .requests-grid {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .request-card {
            background-color: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.15);
        }

        .request-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 1.5rem;
        }

        .request-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }

        .request-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .request-author {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .request-author i {
            color: var(--accent);
        }

        .request-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .request-date i {
            color: var(--accent);
        }

        .request-content {
            padding: 1.5rem;
        }

        .request-text {
            color: var(--text);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .request-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--light);
        }

        .request-status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-answered {
            background-color: var(--success);
            color: var(--white);
        }

        .status-pending {
            background-color: var(--warning);
            color: var(--white);
        }

        .prayer-count {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .prayer-count i {
            color: var(--primary);
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

            .request-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .request-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
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
                <a href="prayer-requests.php" class="menu-item active">
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
                    <h1>Prayer Requests</h1>
                    <p>Submit and view prayer requests from our community</p>
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

            <div class="prayer-content">
                <!-- Submit Prayer Request -->
                <section class="submit-section">
                    <h3>Submit a Prayer Request</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Title (Optional)</label>
                            <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($title ?? ''); ?>" placeholder="Brief title for your request">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Prayer Request *</label>
                            <textarea class="form-control textarea" name="request_text" placeholder="Share your prayer request with our community..." required><?php echo htmlspecialchars($request_text ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1" <?php echo ($is_anonymous ?? 0) ? 'checked' : ''; ?>>
                                <label for="is_anonymous">Submit anonymously</label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_request" class="submit-btn">
                                <i class="fas fa-pray"></i> Submit Prayer Request
                            </button>
                        </div>
                    </form>
                </section>

                <!-- My Prayer Requests -->
                <?php if (!empty($user_requests)): ?>
                <section class="my-requests-section">
                    <h3>My Prayer Requests</h3>
                    <div class="my-requests-grid">
                        <?php foreach ($user_requests as $request): ?>
                        <div class="my-request-card">
                            <h4><?php echo htmlspecialchars($request['title'] ?: 'Prayer Request'); ?></h4>
                            <p><?php echo htmlspecialchars(substr($request['request_text'], 0, 100)) . '...'; ?></p>
                            <div class="request-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                            </div>
                            <div class="request-status <?php echo $request['is_answered'] ? 'answered' : 'pending'; ?>">
                                <?php echo $request['is_answered'] ? 'Answered' : 'Pending'; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Community Prayer Requests -->
                <?php if (!empty($prayer_requests)): ?>
                <div class="requests-grid">
                    <?php foreach ($prayer_requests as $request): ?>
                    <div class="request-card">
                        <div class="request-header">
                            <h3 class="request-title"><?php echo htmlspecialchars($request['title'] ?: 'Prayer Request'); ?></h3>
                            <div class="request-meta">
                                <div class="request-author">
                                    <i class="fas fa-user"></i>
                                    <span>
                                        <?php if ($request['is_anonymous']): ?>
                                            Anonymous
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="request-date">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('M j, Y', strtotime($request['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="request-content">
                            <p class="request-text"><?php echo htmlspecialchars($request['request_text']); ?></p>
                            
                            <?php if ($request['is_answered'] && $request['answered_text']): ?>
                            <div class="answer-section" style="background-color: var(--light); padding: 1rem; border-radius: 6px; margin-top: 1rem;">
                                <h4 style="color: var(--success); margin-bottom: 0.5rem;">Answered Prayer</h4>
                                <p style="color: var(--text); margin: 0;"><?php echo htmlspecialchars($request['answered_text']); ?></p>
                                <small style="color: var(--secondary);">Answered on <?php echo date('M j, Y', strtotime($request['answered_date'])); ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="request-footer">
                            <div class="request-status-badge <?php echo $request['is_answered'] ? 'status-answered' : 'status-pending'; ?>">
                                <?php echo $request['is_answered'] ? 'Answered' : 'Pending'; ?>
                            </div>
                            <div class="prayer-count">
                                <i class="fas fa-heart"></i>
                                <span>Being prayed for</span>
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
                    <i class="fas fa-pray"></i>
                    <h3>No Prayer Requests Yet</h3>
                    <p>Be the first to submit a prayer request for our community to pray for.</p>
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

            // Initialize Pusher for real-time updates
            const pusher = new Pusher('<?php echo PUSHER_KEY; ?>', {
                cluster: '<?php echo PUSHER_CLUSTER; ?>'
            });

            const channel = pusher.subscribe('prayer-requests');
            channel.bind('new-request', function(data) {
                // Show notification for new prayer request
                if (data.user_id !== <?php echo $user_id; ?>) {
                    showNotification('New prayer request submitted', 'Someone in our community needs prayer. Let\'s lift them up together.');
                }
            });

            function showNotification(title, message) {
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification(title, { body: message, icon: '/favicon.ico' });
                } else if ('Notification' in window && Notification.permission !== 'denied') {
                    Notification.requestPermission().then(permission => {
                        if (permission === 'granted') {
                            new Notification(title, { body: message, icon: '/favicon.ico' });
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>
