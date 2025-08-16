<?php
require_once 'config/security.php';
require_once 'config/database.php';
require_once 'includes/form-validator.php';

// Get settings from database
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch(PDOException $e) {
    // Use default settings if database query fails
}

$message = '';
$message_type = '';

if ($_POST && isset($_POST['forgot_password'])) {
    // CSRF Protection
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed. Please refresh the page and try again.';
        $message_type = 'error';
        logSecurityEvent('CSRF Attack', 'Forgot password form');
    } else {
        // Rate limiting for password reset requests
        if (!checkRateLimit('password_reset', 3, 600)) {
            $message = 'Too many password reset requests. Please wait 10 minutes before trying again.';
            $message_type = 'error';
            logSecurityEvent('Rate Limit Exceeded', 'Password reset form');
        } else {
            // Initialize validator
            $validator = new FormValidator($_POST);
            
            // Validate email
            $validator->required('email', 'Email Address');
            $validator->email('email', 'Email Address');
            
            if ($validator->isValid()) {
                // Get sanitized data
                $data = $validator->getSanitizedData();
                
                try {
                    // Check if user exists
                    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
                    $stmt->execute([$data['email']]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        // Generate reset token
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                        
                        // Store reset token in database
                        $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                        $stmt->execute([$user['id'], $token, $expires]);
                        
                        // Send reset email using enhanced mail helper
                        require_once 'includes/mail.php';
                        
                        $reset_link = "http://{$_SERVER['HTTP_HOST']}/JBFV1/reset-password.php?token=" . $token;
                        
                        $emailSent = sendPasswordResetEmail($data['email'], $user['first_name'], $reset_link);
                        
                        if ($emailSent) {
                            $message = 'Password reset link has been sent to your email address. Please check your inbox and spam folder.';
                            $message_type = 'success';
                            
                            // Log successful password reset request
                            logSecurityEvent('Password Reset Requested', 'Email: ' . $data['email'] . ', Token: ' . $token);
                        } else {
                            $message = 'Email could not be sent. Please contact support for assistance.';
                            $message_type = 'error';
                            
                            // Log the error for debugging
                            logSecurityEvent('Email Send Failed', 'Password reset: Email sending failed');
                        }
                    } else {
                        // Don't reveal if email exists or not for security
                        $message = 'If an account with that email exists, a password reset link has been sent.';
                        $message_type = 'success';
                    }
                } catch(PDOException $e) {
                    $message = 'An error occurred. Please try again later.';
                    $message_type = 'error';
                    
                    // Log the error for debugging
                    logSecurityEvent('Database Error', 'Password reset: ' . $e->getMessage());
                }
            } else {
                $message = $validator->getFirstError();
                $message_type = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo htmlspecialchars($settings['church_name'] ?? 'Joy Bible Fellowship'); ?></title>
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

        .stained-glass {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.3;
        }

        header {
            background: linear-gradient(135deg, rgba(109, 76, 65, 0.9) 0%, rgba(61, 39, 35, 0.9) 100%);
            color: var(--white);
            padding: 1rem 2rem;
            position: relative;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            font-family: 'Playfair Display', serif;
        }

        .logo i {
            font-size: 2.5rem;
            margin-right: 1rem;
            color: var(--accent);
        }

        .logo-text h1 {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .logo-text p {
            font-size: 0.9rem;
            font-weight: 300;
            opacity: 0.8;
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 2rem;
        }

        nav ul li a {
            color: var(--white);
            text-decoration: none;
            font-weight: 400;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
            padding-bottom: 5px;
        }

        nav ul li a:hover {
            color: var(--accent);
        }

        nav ul li a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--accent);
            transition: width 0.3s ease;
        }

        nav ul li a:hover:after {
            width: 100%;
        }

        .auth-container {
            display: flex;
            min-height: calc(100vh - 80px);
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .auth-card {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            padding: 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .auth-card h2 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            margin-bottom: 2rem;
            font-size: 2rem;
        }

        .auth-card p {
            margin-bottom: 2rem;
            color: var(--text);
            opacity: 0.8;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid var(--accent);
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(109, 76, 65, 0.2);
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 2rem;
            background-color: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            margin-top: 1rem;
        }

        .btn:hover {
            background-color: var(--secondary);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .auth-links {
            margin-top: 2rem;
            font-size: 0.9rem;
        }

        .auth-links a {
            color: var(--primary);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .auth-links a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        footer {
            background-color: var(--dark);
            color: var(--white);
            padding: 2rem;
            text-align: center;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .footer-links a {
            color: var(--accent);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--white);
        }

        .copyright {
            opacity: 0.7;
            font-size: 0.9rem;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
            }
            
            .logo {
                margin-bottom: 1rem;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            nav ul li {
                margin: 0.5rem 1rem;
            }
            
            .auth-card {
                padding: 2rem;
            }
        }

        @media (max-width: 576px) {
            .auth-card {
                padding: 1.5rem;
            }
        }

        /* Animation classes */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .animate-fadeIn {
            animation: fadeIn 1s ease forwards;
        }
        
        /* Form validation styles */
        .error-field {
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            min-height: 1rem;
        }
        
        .form-group.error input {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }
        
        .form-group.success input {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }
    </style>
</head>
<body>
    <!-- Stained glass background effect -->
    <canvas class="stained-glass" id="stainedGlass"></canvas>
    
    <!-- Header -->
    <header>
        <div class="header-container">
            <div class="logo">
                <i class="fas fa-church"></i>
                <div class="logo-text">
                    <h1><?php echo htmlspecialchars($settings['church_name'] ?? 'Joy Bible Fellowship'); ?></h1>
                    <p><?php echo htmlspecialchars($settings['church_tagline'] ?? 'Spreading Joy Through God\'s Word'); ?></p>
                </div>
            </div>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="index.php#about">About</a></li>
                    <li><a href="index.php#services">Services</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <!-- Forgot Password Section -->
    <section class="auth-container">
        <div class="auth-card animate-fadeIn">
            <h2>Reset Your Password</h2>
            <p>Enter your email address and we'll send you a link to reset your password for your <?php echo htmlspecialchars($settings['church_name'] ?? 'Joy Bible Fellowship'); ?> account.</p>
            
            <?php if ($message): ?>
                <div class="alert <?php echo $message_type === 'error' ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="forgotPasswordForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    <div class="error-field" id="email_error"></div>
                </div>
                <button type="submit" name="forgot_password" class="btn" id="submitBtn">Send Reset Link</button>
            </form>
            
            <div class="auth-links">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-links">
                <a href="index.php">Home</a>
                <a href="index.php#about">About</a>
                <a href="index.php#services">Services</a>
                <a href="index.php#sermons">Sermons</a>
                <a href="index.php#events">Events</a>
                <a href="index.php#contact">Contact</a>
                <a href="#">Privacy Policy</a>
            </div>
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['church_name'] ?? 'Joy Bible Fellowship'); ?>. All Rights Reserved.
            </div>
        </div>
    </footer>
    
    <!-- Toastify Script -->
    <script src="assets/js/toastify.js"></script>
    
    <script>
        // Stained Glass Canvas Effect
        const canvas = document.getElementById('stainedGlass');
        const ctx = canvas.getContext('2d');
        
        // Set canvas size
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        
        // Colors for stained glass effect
        const colors = [
            'rgba(109, 76, 65, 0.3)',
            'rgba(142, 36, 170, 0.3)',
            'rgba(60, 90, 166, 0.3)',
            'rgba(30, 130, 76, 0.3)',
            'rgba(214, 93, 14, 0.3)',
            'rgba(199, 36, 36, 0.3)'
        ];
        
        // Create stained glass effect
        function createStainedGlass() {
            const cellSize = 100;
            const rows = Math.ceil(canvas.height / cellSize) + 1;
            const cols = Math.ceil(canvas.width / cellSize) + 1;
            
            for (let i = 0; i < rows; i++) {
                for (let j = 0; j < cols; j++) {
                    const x = j * cellSize;
                    const y = i * cellSize;
                    const radius = cellSize / 2;
                    
                    // Random color
                    const color = colors[Math.floor(Math.random() * colors.length)];
                    
                    // Draw cell
                    ctx.beginPath();
                    ctx.arc(x + radius, y + radius, radius, 0, Math.PI * 2);
                    ctx.fillStyle = color;
                    ctx.fill();
                    ctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
                    ctx.stroke();
                }
            }
        }
        
        // Resize canvas when window resizes
        window.addEventListener('resize', function() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            createStainedGlass();
        });
        
        // Initialize stained glass
        createStainedGlass();
        
        // Form validation and Toastify integration
        const form = document.getElementById('forgotPasswordForm');
        const emailInput = document.getElementById('email');
        const submitBtn = document.getElementById('submitBtn');
        
        // Form submission with validation
        form.addEventListener('submit', function(e) {
            // Clear previous errors
            document.querySelectorAll('.error-field').forEach(field => field.textContent = '');
            document.querySelectorAll('.form-group').forEach(group => group.classList.remove('error', 'success'));
            
            let isValid = true;
            const formData = new FormData(form);
            
            // Validate email
            const email = formData.get('email');
            if (!email.trim()) {
                const emailGroup = emailInput.closest('.form-group');
                emailGroup.classList.add('error');
                document.getElementById('email_error').textContent = 'Email address is required';
                isValid = false;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                const emailGroup = emailInput.closest('.form-group');
                emailGroup.classList.add('error');
                document.getElementById('email_error').textContent = 'Please enter a valid email address';
                isValid = false;
            } else {
                const emailGroup = emailInput.closest('.form-group');
                emailGroup.classList.remove('error');
                emailGroup.classList.add('success');
            }
            
            if (isValid) {
                // Try to show success toast, but submit form regardless
                try {
                    if (typeof showToast === 'function') {
                        showToast('Form validation passed! Sending reset link...', 'success', 2000);
                    }
                } catch (error) {
                    // Toast error ignored
                }
                
                // Submit form immediately without waiting
                // This ensures the form is submitted even if toast fails
                return true; // Allow default form submission
            } else {
                // Try to show error toast, but don't prevent form submission
                try {
                    if (typeof showToast === 'function') {
                        showToast('Please fix the errors above', 'error', 5000);
                    }
                } catch (error) {
                    // Toast error ignored
                }
                
                // Prevent form submission if validation fails
                e.preventDefault();
                return false;
            }
        });
        
        // Show success/error messages as toasts
        <?php if ($message): ?>
        showToast('<?php echo addslashes($message); ?>', '<?php echo $message_type; ?>', 5000);
        <?php endif; ?>
    </script>
</body>
</html>