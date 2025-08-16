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

// Handle donation submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_donation'])) {
    $amount = floatval($_POST['amount']);
    $fund_type = trim($_POST['fund_type']);
    $payment_method = trim($_POST['payment_method']);
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');
    
    if ($amount <= 0) {
        $error = 'Please enter a valid donation amount.';
    } elseif (empty($fund_type)) {
        $error = 'Please select a fund type.';
    } elseif (empty($payment_method)) {
        $error = 'Please select a payment method.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO giving_records (user_id, amount, fund_type, payment_method, is_recurring, notes, donation_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $amount, $fund_type, $payment_method, $is_recurring, $notes]);
            
            $message = 'Thank you for your generous donation! Your gift has been recorded.';
            
            // Clear form data
            $amount = '';
            $fund_type = '';
            $payment_method = '';
            $is_recurring = 0;
            $notes = '';
        } catch (Exception $e) {
            $error = 'Error processing donation. Please try again.';
        }
    }
}

// Get user's giving history with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM giving_records WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_donations = $stmt->fetch()['total'];
$total_pages = ceil($total_donations / $limit);

// Get giving history
$stmt = $pdo->prepare("SELECT * FROM giving_records WHERE user_id = ? ORDER BY donation_date DESC LIMIT ? OFFSET ?");
$stmt->execute([$user_id, $limit, $offset]);
$giving_history = $stmt->fetchAll();

// Get giving statistics
$stmt = $pdo->prepare("SELECT 
                           SUM(amount) as total_given,
                           COUNT(*) as total_donations,
                           AVG(amount) as avg_donation,
                           MAX(donation_date) as last_donation
                       FROM giving_records 
                       WHERE user_id = ?");
$stmt->execute([$user_id]);
$giving_stats = $stmt->fetch();

// Get fund type breakdown
$stmt = $pdo->prepare("SELECT fund_type, SUM(amount) as total_amount, COUNT(*) as donation_count 
                       FROM giving_records 
                       WHERE user_id = ? 
                       GROUP BY fund_type 
                       ORDER BY total_amount DESC");
$stmt->execute([$user_id]);
$fund_breakdown = $stmt->fetchAll();

// Get monthly giving for current year
$stmt = $pdo->prepare("SELECT 
                           MONTH(donation_date) as month,
                           SUM(amount) as monthly_total
                       FROM giving_records 
                       WHERE user_id = ? AND YEAR(donation_date) = YEAR(CURDATE())
                       GROUP BY MONTH(donation_date)
                       ORDER BY month");
$stmt->execute([$user_id]);
$monthly_giving = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joy Bible Fellowship - Giving</title>
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

        /* Giving Stats */
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

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .stat-icon.success {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success);
        }

        .stat-icon.info {
            background-color: rgba(33, 150, 243, 0.1);
            color: var(--info);
        }

        .stat-icon.warning {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--warning);
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

        /* Donation Form */
        .donation-form {
            background-color: var(--white);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .donation-form h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 1.5rem;
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

        .form-control.textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
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

        /* Giving History */
        .giving-history {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .giving-history h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th,
        .history-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--light);
        }

        .history-table th {
            background-color: var(--light);
            font-weight: 600;
            color: var(--primary);
        }

        .history-table tr:hover {
            background-color: var(--light);
        }

        .amount {
            font-weight: 600;
            color: var(--success);
        }

        .fund-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            background-color: var(--accent);
            color: var(--primary);
        }

        .recurring {
            color: var(--info);
            font-size: 0.8rem;
        }

        /* Fund Breakdown */
        .fund-breakdown {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .fund-breakdown h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .fund-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--light);
        }

        .fund-item:last-child {
            border-bottom: none;
        }

        .fund-name {
            font-weight: 600;
            color: var(--dark);
        }

        .fund-amount {
            font-weight: 600;
            color: var(--success);
        }

        .fund-count {
            font-size: 0.8rem;
            color: var(--secondary);
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .history-table {
                font-size: 0.8rem;
            }

            .history-table th,
            .history-table td {
                padding: 0.5rem;
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
                <a href="giving.php" class="menu-item active">
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
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1>Giving</h1>
                    <p>Support our ministry and mission through generous giving</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="giving-content">
                <!-- Giving Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-value">$<?php echo number_format($giving_stats['total_given'] ?? 0, 2); ?></div>
                        <div class="stat-title">Total Given</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-value"><?php echo $giving_stats['total_donations'] ?? 0; ?></div>
                        <div class="stat-title">Total Donations</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="stat-value">$<?php echo number_format($giving_stats['avg_donation'] ?? 0, 2); ?></div>
                        <div class="stat-title">Average Donation</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-value"><?php echo $giving_stats['last_donation'] ? date('M j', strtotime($giving_stats['last_donation'])) : 'Never'; ?></div>
                        <div class="stat-title">Last Donation</div>
                    </div>
                </div>

                <!-- Donation Form -->
                <section class="donation-form">
                    <h3>Make a Donation</h3>
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Amount *</label>
                                <input type="number" class="form-control" name="amount" step="0.01" min="0.01" value="<?php echo htmlspecialchars($amount ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fund Type *</label>
                                <select class="form-control" name="fund_type" required>
                                    <option value="">Select Fund Type</option>
                                    <option value="General Fund" <?php echo ($fund_type ?? '') === 'General Fund' ? 'selected' : ''; ?>>General Fund</option>
                                    <option value="Missions & Outreach" <?php echo ($fund_type ?? '') === 'Missions & Outreach' ? 'selected' : ''; ?>>Missions & Outreach</option>
                                    <option value="Worship Ministry" <?php echo ($fund_type ?? '') === 'Worship Ministry' ? 'selected' : ''; ?>>Worship Ministry</option>
                                    <option value="Youth & Children" <?php echo ($fund_type ?? '') === 'Youth & Children' ? 'selected' : ''; ?>>Youth & Children</option>
                                    <option value="Building Fund" <?php echo ($fund_type ?? '') === 'Building Fund' ? 'selected' : ''; ?>>Building Fund</option>
                                    <option value="Special Projects" <?php echo ($fund_type ?? '') === 'Special Projects' ? 'selected' : ''; ?>>Special Projects</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Payment Method *</label>
                                <select class="form-control" name="payment_method" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="Cash" <?php echo ($payment_method ?? '') === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="Check" <?php echo ($payment_method ?? '') === 'Check' ? 'selected' : ''; ?>>Check</option>
                                    <option value="Online Transfer" <?php echo ($payment_method ?? '') === 'Online Transfer' ? 'selected' : ''; ?>>Online Transfer</option>
                                    <option value="Credit Card" <?php echo ($payment_method ?? '') === 'Credit Card' ? 'selected' : ''; ?>>Credit Card</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_recurring" name="is_recurring" value="1" <?php echo ($is_recurring ?? 0) ? 'checked' : ''; ?>>
                                <label for="is_recurring">Set up recurring donation</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control textarea" name="notes" placeholder="Any additional notes about your donation..."><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="form-btn secondary" onclick="window.location.reload()">
                                Cancel
                            </button>
                            <button type="submit" name="submit_donation" class="form-btn primary">
                                <i class="fas fa-heart"></i> Submit Donation
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Fund Breakdown -->
                <?php if (!empty($fund_breakdown)): ?>
                <section class="fund-breakdown">
                    <h3>Your Giving by Fund Type</h3>
                    <?php foreach ($fund_breakdown as $fund): ?>
                    <div class="fund-item">
                        <div>
                            <div class="fund-name"><?php echo htmlspecialchars($fund['fund_type']); ?></div>
                            <div class="fund-count"><?php echo $fund['donation_count']; ?> donation<?php echo $fund['donation_count'] != 1 ? 's' : ''; ?></div>
                        </div>
                        <div class="fund-amount">$<?php echo number_format($fund['total_amount'], 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>

                <!-- Giving History -->
                <?php if (!empty($giving_history)): ?>
                <section class="giving-history">
                    <h3>Giving History</h3>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Fund Type</th>
                                <th>Payment Method</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($giving_history as $donation): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($donation['donation_date'])); ?></td>
                                <td class="amount">$<?php echo number_format($donation['amount'], 2); ?></td>
                                <td>
                                    <span class="fund-type"><?php echo htmlspecialchars($donation['fund_type']); ?></span>
                                    <?php if ($donation['is_recurring']): ?>
                                        <div class="recurring">Recurring</div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($donation['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($donation['notes'] ?: '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

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
                </section>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // Any additional JavaScript can be added here
    </script>
</body>
</html>
