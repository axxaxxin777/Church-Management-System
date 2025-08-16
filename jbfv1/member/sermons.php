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

// Get sermons with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 6;
$offset = ($page - 1) * $limit;

// Get total count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sermons");
$total_sermons = $stmt->fetch()['total'];
$total_pages = ceil($total_sermons / $limit);

// Get sermons
$stmt = $pdo->prepare("SELECT * FROM sermons ORDER BY sermon_date DESC, created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$sermons = $stmt->fetchAll();

// Get featured sermons
$stmt = $pdo->query("SELECT * FROM sermons WHERE is_featured = 1 ORDER BY sermon_date DESC LIMIT 3");
$featured_sermons = $stmt->fetchAll();

// Get sermon categories
$stmt = $pdo->query("SELECT DISTINCT speaker FROM sermons WHERE speaker IS NOT NULL ORDER BY speaker");
$speakers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grace Community Church - Sermons</title>
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

        /* Sermons Content */
        .sermons-content {
            display: grid;
            gap: 1.5rem;
        }

        /* Featured Section */
        .featured-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .featured-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            text-align: center;
        }

        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .featured-sermon {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .featured-sermon h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .featured-sermon p {
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .featured-sermon .sermon-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            opacity: 0.8;
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
            gap: 1rem;
            flex-wrap: wrap;
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

        /* Sermons Grid */
        .sermons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .sermon-card {
            background-color: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .sermon-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.15);
        }

        .sermon-video {
            width: 100%;
            height: 200px;
            background-color: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .sermon-video iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .sermon-video .play-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0, 0, 0, 0.7);
            color: var(--white);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .sermon-video:hover .play-overlay {
            background-color: var(--primary);
            transform: translate(-50%, -50%) scale(1.1);
        }

        .sermon-content {
            padding: 1.5rem;
        }

        .sermon-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .sermon-description {
            color: var(--text);
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .sermon-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--secondary);
        }

        .sermon-speaker {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sermon-speaker i {
            color: var(--primary);
        }

        .sermon-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sermon-date i {
            color: var(--primary);
        }

        .sermon-views {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sermon-views i {
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

            .featured-grid {
                grid-template-columns: 1fr;
            }

            .sermons-grid {
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
                <a href="sermons.php" class="menu-item active">
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
                    <h1>Sermons</h1>
                    <p>Watch and listen to our latest messages</p>
                </div>
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="sermons-content">
                <!-- Featured Sermons -->
                <?php if (!empty($featured_sermons)): ?>
                <section class="featured-section">
                    <h2>Featured Messages</h2>
                    <div class="featured-grid">
                        <?php foreach ($featured_sermons as $sermon): ?>
                        <div class="featured-sermon">
                            <h3><?php echo htmlspecialchars($sermon['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($sermon['description'], 0, 100)) . '...'; ?></p>
                            <div class="sermon-meta">
                                <span><?php echo htmlspecialchars($sermon['speaker']); ?></span>
                                <span><?php echo $sermon['sermon_date'] ? date('M j, Y', strtotime($sermon['sermon_date'])) : 'Recent'; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Filters -->
                <section class="filters">
                    <h3>Filter Sermons</h3>
                    <div class="filter-options">
                        <button class="filter-option active" data-filter="all">All Sermons</button>
                        <?php foreach ($speakers as $speaker): ?>
                        <button class="filter-option" data-filter="<?php echo htmlspecialchars($speaker['speaker']); ?>">
                            <?php echo htmlspecialchars($speaker['speaker']); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Sermons Grid -->
                <?php if (!empty($sermons)): ?>
                <div class="sermons-grid">
                    <?php foreach ($sermons as $sermon): ?>
                    <div class="sermon-card" data-speaker="<?php echo htmlspecialchars($sermon['speaker']); ?>">
                        <div class="sermon-video">
                            <?php if ($sermon['video_embed_code']): ?>
                                <?php echo $sermon['video_embed_code']; ?>
                            <?php else: ?>
                                <div class="play-overlay">
                                    <i class="fas fa-play"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="sermon-content">
                            <h3 class="sermon-title"><?php echo htmlspecialchars($sermon['title']); ?></h3>
                            <p class="sermon-description"><?php echo htmlspecialchars(substr($sermon['description'], 0, 120)) . '...'; ?></p>
                            <div class="sermon-meta">
                                <div class="sermon-speaker">
                                    <i class="fas fa-user"></i>
                                    <span><?php echo htmlspecialchars($sermon['speaker']); ?></span>
                                </div>
                                <div class="sermon-date">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo $sermon['sermon_date'] ? date('M j, Y', strtotime($sermon['sermon_date'])) : 'Recent'; ?></span>
                                </div>
                                <div class="sermon-views">
                                    <i class="fas fa-eye"></i>
                                    <span><?php echo number_format($sermon['views']); ?></span>
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
                    <i class="fas fa-video"></i>
                    <h3>No Sermons Available</h3>
                    <p>Check back later for new messages and teachings.</p>
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
            
            // Filter functionality
            const filterOptions = document.querySelectorAll('.filter-option');
            const sermonCards = document.querySelectorAll('.sermon-card');
            
            filterOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    
                    // Update active filter
                    filterOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Filter sermons
                    sermonCards.forEach(card => {
                        if (filter === 'all' || card.getAttribute('data-speaker') === filter) {
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
