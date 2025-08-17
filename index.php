<?php
session_start();
require_once 'config/database.php';
require_once 'config/privacy.php';
require_once 'includes/pusher.php';

// Fetch dynamic content from database
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Fetch recent sermons
$stmt = $pdo->query("SELECT * FROM sermons ORDER BY sermon_date DESC LIMIT 3");
$sermons = $stmt->fetchAll();

// Fetch upcoming events
$stmt = $pdo->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 3");
$events = $stmt->fetchAll();

// Fetch total prayer requests
$stmt = $pdo->query("SELECT COUNT(*) as total_prayers FROM prayer_requests");
$prayer_stats = $stmt->fetch();
$total_prayers = $prayer_stats['total_prayers'];

// Track visitor (simple session-based tracking)
$session_id = session_id();
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Update or insert visitor record
$stmt = $pdo->prepare("INSERT INTO visitors (session_id, ip_address, user_agent) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_activity = CURRENT_TIMESTAMP");
$stmt->execute([$session_id, $ip_address, $user_agent]);

// Get current active visitors count (last 5 minutes)
$stmt = $pdo->query("SELECT COUNT(*) as active_visitors FROM active_visitors");
$visitor_stats = $stmt->fetch();
$active_visitors = $visitor_stats['active_visitors'];

// Send real-time visitor update via Pusher
$pusher = getPusher();
if ($pusher->isAvailable()) {
    // Get total prayer requests for the Pusher event
    $stmt = $pdo->query("SELECT COUNT(*) as total_prayers FROM prayer_requests");
    $prayer_stats = $stmt->fetch();
    $total_prayers = $prayer_stats['total_prayers'];
    
    // Get upcoming events count for the Pusher event
    $stmt = $pdo->query("SELECT COUNT(*) as upcoming_events FROM events WHERE event_date >= CURDATE()");
    $event_stats = $stmt->fetch();
    $upcoming_events = $event_stats['upcoming_events'];
    
    // Send visitor update event
    $pusher->notifyAll('visitor_update', [
        'visitorCount' => $active_visitors,
        'prayerCount' => $total_prayers,
        'eventCount' => $upcoming_events
    ]);
}

// Handle contact form submission
$contact_message = '';
if ($_POST && isset($_POST['contact_submit'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if ($name && $email && $message) {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $email, $subject, $message])) {
            $contact_message = 'Thank you for your message! We will get back to you soon.';
        } else {
            $contact_message = 'Sorry, there was an error sending your message. Please try again.';
        }
    } else {
        $contact_message = 'Please fill in all required fields.';
    }
}

// Handle prayer request submission
$prayer_message = '';
if ($_POST && isset($_POST['prayer_submit'])) {
    $prayer_text = trim($_POST['prayer_text']);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    if ($prayer_text) {
        $stmt = $pdo->prepare("INSERT INTO prayer_requests (user_id, request_text, is_anonymous) VALUES (?, ?, ?)");
        $is_anonymous = !$user_id;
        if ($stmt->execute([$user_id, $prayer_text, $is_anonymous])) {
            $prayer_message = 'Your prayer request has been submitted. We\'ll pray for you!';
            
            // Update prayer count for real-time display
            $total_prayers++;
        } else {
            $prayer_message = 'Sorry, there was an error submitting your prayer request.';
        }
    } else {
        $prayer_message = 'Please enter your prayer request.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['church_name'] ?? 'Joy Bible Fellowship'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        :root {
            --primary: #6d4c41;
            --secondary: #8d6e63;
            --accent: #d7ccc8;
            --light: #efebe9;
            --dark: #3e2723;
            --text: #333;
            --white: #fff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            color: var(--text);
            background-color: var(--light);
            overflow-x: hidden;
            position: relative;
        }

        /* Fixed Floating Prayer Form Styles */
        .floating-prayers {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
            max-width: calc(100vw - 4rem);
        }

        .prayer-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            border: none;
            position: relative;
        }

        .prayer-btn:hover {
            transform: scale(1.1);
            background-color: var(--secondary);
        }

        .prayer-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .prayer-form {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 320px;
            max-width: calc(100vw - 4rem);
            background-color: var(--white);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: scale(0);
            transform-origin: bottom right;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            opacity: 0;
            visibility: hidden;
        }

        .prayer-form.active {
            transform: scale(1);
            opacity: 1;
            visibility: visible;
        }

        .prayer-form h3 {
            margin-bottom: 1rem;
            color: var(--primary);
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
        }

        .prayer-form textarea {
            width: 100%;
            height: 120px;
            padding: 1rem;
            border: 2px solid var(--accent);
            border-radius: 10px;
            resize: none;
            margin-bottom: 1rem;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .prayer-form textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .prayer-form button {
            width: 100%;
            padding: 0.8rem;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 1rem;
        }

        .prayer-form button:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }

        .prayer-form button:active {
            transform: translateY(0);
        }

        /* Mobile Responsive Fixes */
        @media (max-width: 768px) {
            .floating-prayers {
                bottom: 1rem;
                right: 1rem;
                max-width: calc(100vw - 2rem);
            }
            
            .prayer-btn {
                width: 55px;
                height: 55px;
                font-size: 1.3rem;
            }
            
            .prayer-form {
                width: 280px;
                max-width: calc(100vw - 2rem);
                bottom: 70px;
                padding: 1.2rem;
            }
            
            .prayer-form h3 {
                font-size: 1.2rem;
            }
            
            .prayer-form textarea {
                height: 100px;
                padding: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .floating-prayers {
                bottom: 0.5rem;
                right: 0.5rem;
                max-width: calc(100vw - 1rem);
            }
            
            .prayer-btn {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
            
            .prayer-form {
                width: 260px;
                max-width: calc(100vw - 1rem);
                bottom: 60px;
                padding: 1rem;
            }
        }

        /* Prevent form from going off-screen */
        @media (max-height: 600px) {
            .prayer-form {
                max-height: 60vh;
                overflow-y: auto;
                bottom: 70px;
            }
        }

        /* Alert Styles */
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            border-left: 4px solid;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #28a745;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }

        /* Animation for prayer button */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .prayer-btn.pulse {
            animation: pulse 1s ease;
        }

        /* Close button for prayer form */
        .prayer-form-close {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--secondary);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .prayer-form-close:hover {
            background-color: var(--accent);
            color: var(--primary);
        }

        /* Rest of your existing styles would go here */
        /* ... existing styles ... */
    </style>
</head>
<body>
    <!-- Your existing HTML content -->
    
    <!-- Fixed Floating Prayer Request Button -->
    <div class="floating-prayers">
        <div class="prayer-form" id="prayerForm">
            <button class="prayer-form-close" id="prayerFormClose">
                <i class="fas fa-times"></i>
            </button>
            <h3>Submit Prayer Request</h3>
            <?php if ($prayer_message): ?>
                <div class="alert <?php echo strpos($prayer_message, 'error') !== false ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($prayer_message); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="#" id="prayerFormElement">
                <textarea name="prayer_text" placeholder="Share your prayer need..." required></textarea>
                <button type="submit" name="prayer_submit">Submit Prayer Request</button>
            </form>
        </div>
        <button class="prayer-btn" id="prayerBtn">
            <i class="fas fa-pray"></i>
            <span class="prayer-count" id="prayerBtnCount"><?php echo $total_prayers; ?></span>
        </button>
    </div>
    
    <script>
        // Enhanced Prayer Form Functionality
        const prayerBtn = document.getElementById('prayerBtn');
        const prayerForm = document.getElementById('prayerForm');
        const prayerFormClose = document.getElementById('prayerFormClose');
        const prayerFormElement = document.getElementById('prayerFormElement');
        let lastScrollPosition = 0;

        // Toggle prayer form
        prayerBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            prayerForm.classList.toggle('active');
            
            // Focus on textarea when form opens
            if (prayerForm.classList.contains('active')) {
                setTimeout(() => {
                    const textarea = prayerForm.querySelector('textarea');
                    if (textarea) textarea.focus();
                }, 300);
            }
        });

        // Close form with close button
        prayerFormClose.addEventListener('click', function(e) {
            e.stopPropagation();
            prayerForm.classList.remove('active');
        });

        // Close form when clicking outside
        document.addEventListener('click', function(e) {
            if (!prayerBtn.contains(e.target) && !prayerForm.contains(e.target)) {
                prayerForm.classList.remove('active');
            }
        });

        // Close form when scrolling
        window.addEventListener('scroll', function() {
            const currentScrollPosition = window.pageYOffset;
            if (Math.abs(currentScrollPosition - lastScrollPosition) > 10) {
                prayerForm.classList.remove('active');
            }
            lastScrollPosition = currentScrollPosition;
        });

        // Handle form submission with AJAX
        prayerFormElement.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const textarea = this.querySelector('textarea[name="prayer_text"]');
            const prayerText = textarea.value.trim();
            
            if (!prayerText) {
                showAlert('Please enter your prayer request.', 'error');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;
            
            // Submit form via AJAX
            const formData = new FormData(this);
            formData.append('prayer_submit', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Show success message
                showAlert('Your prayer request has been submitted. We\'ll pray for you!', 'success');
                
                // Clear form
                textarea.value = '';
                
                // Close form
                prayerForm.classList.remove('active');
                
                // Update prayer count
                updatePrayerCount();
                
                // Reset button
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            })
            .catch(error => {
                showAlert('Sorry, there was an error submitting your prayer request.', 'error');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });

        // Show alert function
        function showAlert(message, type) {
            // Remove existing alerts
            const existingAlert = prayerForm.querySelector('.alert');
            if (existingAlert) {
                existingAlert.remove();
            }
            
            // Create new alert
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            
            // Insert after h3
            const h3 = prayerForm.querySelector('h3');
            h3.parentNode.insertBefore(alert, h3.nextSibling);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }

        // Update prayer count
        function updatePrayerCount() {
            const prayerBtnCount = document.getElementById('prayerBtnCount');
            const currentCount = parseInt(prayerBtnCount.textContent);
            prayerBtnCount.textContent = currentCount + 1;
            
            // Add pulse animation
            prayerBtn.classList.add('pulse');
            setTimeout(() => {
                prayerBtn.classList.remove('pulse');
            }, 1000);
        }

        // Prevent form from being submitted multiple times
        let isSubmitting = false;
        prayerFormElement.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return;
            }
            isSubmitting = true;
            
            setTimeout(() => {
                isSubmitting = false;
            }, 2000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key closes form
            if (e.key === 'Escape' && prayerForm.classList.contains('active')) {
                prayerForm.classList.remove('active');
            }
        });
    </script>
</body>
</html>