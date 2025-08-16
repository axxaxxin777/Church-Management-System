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

// Handle group joining
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_group'])) {
    $group_id = (int)$_POST['group_id'];
    
    // Check if already a member
    $stmt = $pdo->prepare("SELECT * FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $user_id]);
    
    if ($stmt->fetch()) {
        $error = 'You are already a member of this group.';
    } else {
        try {
            // Join group
            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, joined_date) VALUES (?, ?, NOW())");
            $stmt->execute([$group_id, $user_id]);
            
            $message = 'Successfully joined the group!';
        } catch (Exception $e) {
            $error = 'Error joining group. Please try again.';
        }
    }
}

// Handle group leaving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_group'])) {
    $group_id = (int)$_POST['group_id'];
    
    try {
        // Leave group
        $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        
        $message = 'Successfully left the group.';
    } catch (Exception $e) {
        $error = 'Error leaving group. Please try again.';
    }
}

// Get groups with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 6;
$offset = ($page - 1) * $limit;

// Get total count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM small_groups WHERE is_active = 1");
$total_groups = $stmt->fetch()['total'];
$total_pages = ceil($total_groups / $limit);

// Get groups with member count and user's membership status
$stmt = $pdo->prepare("SELECT sg.*, 
                              (SELECT COUNT(*) FROM group_members WHERE group_id = sg.id) as member_count,
                              (SELECT COUNT(*) FROM group_members WHERE group_id = sg.id AND user_id = :user_id) as user_member
                       FROM small_groups sg 
                       WHERE sg.is_active = 1
                       ORDER BY sg.name ASC 
                       LIMIT :limit OFFSET :offset");
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$groups = $stmt->fetchAll();

// Get user's joined groups
$stmt = $pdo->prepare("SELECT sg.*, gm.joined_date 
                       FROM small_groups sg 
                       INNER JOIN group_members gm ON sg.id = gm.group_id 
                       WHERE gm.user_id = ? AND sg.is_active = 1
                       ORDER BY sg.name ASC");
$stmt->execute([$user_id]);
$user_groups = $stmt->fetchAll();

// Get group categories
$stmt = $pdo->query("SELECT DISTINCT category FROM small_groups WHERE category IS NOT NULL AND is_active = 1 ORDER BY category");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joy Bible Fellowship - Small Groups</title>
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
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: 0.25rem;
        }

        .page-title p {
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .message.success {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success);
            border: 1px solid rgba(76, 175, 80, 0.2);
        }

        .message.error {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger);
            border: 1px solid rgba(244, 67, 54, 0.2);
        }

        /* My Groups Section */
        .my-groups-section {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .my-groups-section h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .my-groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .my-group-card {
            background-color: var(--light);
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid var(--success);
        }

        .my-group-card h4 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .my-group-card p {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .my-group-card .group-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--secondary);
        }

        .my-group-card .joined-date {
            font-weight: 600;
            color: var(--primary);
        }

        /* Groups Grid */
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .group-card {
            background-color: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .group-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.15);
        }

        .group-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 1.5rem;
        }

        .group-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }

        .group-category {
            display: inline-block;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }

        .group-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .group-leader {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .group-leader i {
            color: var(--accent);
        }

        .group-schedule {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .group-schedule i {
            color: var(--accent);
        }

        .group-content {
            padding: 1.5rem;
        }

        .group-description {
            color: var(--text);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .group-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-top: 1px solid var(--light);
            margin-top: 1rem;
        }

        .group-members {
            font-size: 0.9rem;
            color: var(--secondary);
        }

        .group-actions {
            display: flex;
            gap: 0.5rem;
        }

        .group-btn {
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

        .group-btn.primary {
            background-color: var(--primary);
            color: var(--white);
        }

        .group-btn.primary:hover {
            background-color: var(--dark);
        }

        .group-btn.secondary {
            background-color: var(--danger);
            color: var(--white);
        }

        .group-btn.secondary:hover {
            background-color: #c62828;
        }

        .group-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Filters */
        .filters {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .filters h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .filter-option {
            padding: 0.5rem 1rem;
            border: 2px solid var(--accent);
            border-radius: 20px;
            background: none;
            color: var(--secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .filter-option:hover,
        .filter-option.active {
            background-color: var(--primary);
            color: var(--white);
            border-color: var(--primary);
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

            .my-groups-grid {
                grid-template-columns: 1fr;
            }

            .groups-grid {
                grid-template-columns: 1fr;
            }

            .filter-options {
                flex-direction: column;
            }

            .filter-option {
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
                <a href="groups.php" class="menu-item active">
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
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1>Small Groups</h1>
                    <p>Connect with others in our community through small groups</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="groups-content">
                <!-- My Groups -->
                <?php if (!empty($user_groups)): ?>
                <section class="my-groups-section">
                    <h3>My Groups</h3>
                    <div class="my-groups-grid">
                        <?php foreach ($user_groups as $group): ?>
                        <div class="my-group-card">
                            <h4><?php echo htmlspecialchars($group['name']); ?></h4>
                            <p><?php echo htmlspecialchars(substr($group['description'], 0, 80)) . '...'; ?></p>
                            <div class="group-meta">
                                <span class="joined-date">
                                    <i class="fas fa-calendar"></i>
                                    Joined <?php echo date('M j, Y', strtotime($group['joined_date'])); ?>
                                </span>
                                <span>
                                    <i class="fas fa-users"></i>
                                    <?php echo $group['max_members'] ? $group['current_members'] . '/' . $group['max_members'] : $group['current_members']; ?> members
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Filters -->
                <section class="filters">
                    <h3>Filter Groups</h3>
                    <div class="filter-options">
                        <button class="filter-option active" data-filter="all">All Groups</button>
                        <?php foreach ($categories as $category): ?>
                        <button class="filter-option" data-filter="<?php echo htmlspecialchars($category['category']); ?>">
                            <?php echo htmlspecialchars($category['category']); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Groups Grid -->
                <?php if (!empty($groups)): ?>
                <div class="groups-grid">
                    <?php foreach ($groups as $group): ?>
                    <div class="group-card" data-category="<?php echo htmlspecialchars($group['category']); ?>">
                        <div class="group-header">
                            <h3 class="group-title"><?php echo htmlspecialchars($group['name']); ?></h3>
                            <div class="group-category"><?php echo htmlspecialchars($group['category']); ?></div>
                            <div class="group-meta">
                                <div class="group-leader">
                                    <i class="fas fa-user"></i>
                                    <span><?php echo htmlspecialchars($group['leader_name']); ?></span>
                                </div>
                                <div class="group-schedule">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo htmlspecialchars($group['meeting_time']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="group-content">
                            <p class="group-description"><?php echo htmlspecialchars($group['description']); ?></p>
                            
                            <div class="group-stats">
                                <div class="group-members">
                                    <i class="fas fa-users"></i>
                                    <?php echo $group['member_count']; ?> members
                                </div>
                                <div class="group-actions">
                                    <?php if ($group['user_member']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                            <button type="submit" name="leave_group" class="group-btn secondary">
                                                <i class="fas fa-times"></i> Leave Group
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                            <button type="submit" name="join_group" class="group-btn primary" 
                                                    <?php echo ($group['max_members'] && $group['member_count'] >= $group['max_members']) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-plus"></i> Join Group
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
                    <i class="fas fa-users"></i>
                    <h3>No Groups Available</h3>
                    <p>Check back later for new small groups and community opportunities.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterOptions = document.querySelectorAll('.filter-option');
            const groupCards = document.querySelectorAll('.group-card');
            
            filterOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    
                    // Update active filter
                    filterOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Filter groups
                    groupCards.forEach(card => {
                        if (filter === 'all' || card.getAttribute('data-category') === filter) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
