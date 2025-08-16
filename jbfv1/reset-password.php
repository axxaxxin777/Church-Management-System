<?php
session_start();
require_once 'config/database.php';

$message = '';
$message_type = '';
$token_valid = false;
$user = null;

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $message = 'Invalid or missing reset token.';
    $message_type = 'error';
} else {
    try {
        // Verify token and get user
        $current_time = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            SELECT pr.user_id, pr.expires_at, u.first_name, u.last_name, u.email 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > ?
        ");
        $stmt->execute([$token, $current_time]);
        $reset_data = $stmt->fetch();
        
        if ($reset_data) {
            $token_valid = true;
            $user = [
                'id' => $reset_data['user_id'],
                'first_name' => $reset_data['first_name'],
                'last_name' => $reset_data['last_name'],
                'email' => $reset_data['email']
            ];
        } else {
            $message = 'Invalid or expired reset token. Please request a new password reset.';
            $message_type = 'error';
        }
    } catch(PDOException $e) {
        $message = 'An error occurred. Please try again later.';
        $message_type = 'error';
    }
}

// Handle password reset form submission
if ($_POST && isset($_POST['reset_password']) && $token_valid) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $message = 'Please fill in all fields.';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
    } elseif (strlen($new_password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $message_type = 'error';
    } else {
        try {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update user's password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user['id']]);
            
            // Mark reset token as used
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Send confirmation email using enhanced mail helper
            require_once 'includes/mail.php';
            
            $subject = 'Password Successfully Reset - Joy Bible Fellowship';
            $message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #6d4c41;'>Password Successfully Reset</h2>
                <p>Dear {$user['first_name']},</p>
                <p>Your password has been successfully reset for your Joy Bible Fellowship account.</p>
                <p>If you did not perform this action, please contact us immediately as your account may have been compromised.</p>
                <p>You can now <a href='http://{$_SERVER['HTTP_HOST']}/JBFV1/login.php'>login to your account</a> with your new password.</p>
                <p>For security reasons, we recommend:</p>
                <ul>
                    <li>Using a strong, unique password</li>
                    <li>Not sharing your password with anyone</li>
                    <li>Logging out when using shared devices</li>
                </ul>
                <p>Blessings,<br>The Joy Bible Fellowship Team</p>
            </div>";
            
            $enhancedMail = new EnhancedMail();
            $enhancedMail->send($user['email'], $subject, $message);
            
            $message = 'Your password has been successfully reset! You can now login with your new password.';
            $message_type = 'success';
            
            // Redirect to login after 3 seconds
            header("refresh:3;url=login.php");
            
        } catch(PDOException $e) {
            $message = 'An error occurred while resetting your password. Please try again.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Joy Bible Fellowship</title>
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

        .password-strength {
            height: 4px;
            background-color: var(--accent);
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            background-color: #28a745;
            transition: width 0.3s ease;
        }

        .password-requirements {
            font-size: 0.8rem;
            color: var(--secondary);
            margin-top: 0.5rem;
            text-align: left;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.3rem;
        }

        .requirement i {
            margin-right: 0.5rem;
            font-size: 0.7rem;
        }

        .requirement.valid i {
            color: #28a745;
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

        .btn:disabled {
            background-color: var(--accent);
            cursor: not-allowed;
            transform: none;
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
                    <h1>Joy Bible Fellowship</h1>
                    <p>Spreading Joy Through God's Word</p>
                </div>
            </div>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="index.php#about">About</a></li>
                    <li><a href="index.php#services">Services</a></li>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <!-- Reset Password Section -->
    <section class="auth-container">
        <div class="auth-card animate-fadeIn">
            <?php if ($token_valid): ?>
                <h2>Create New Password</h2>
                <p>Hello <?php echo htmlspecialchars($user['first_name']); ?>, please create a new password for your account.</p>
                
                <?php if ($message): ?>
                    <div class="alert <?php echo $message_type === 'error' ? 'alert-error' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement" id="lengthReq">
                                <i class="far fa-circle"></i>
                                <span>At least 8 characters</span>
                            </div>
                            <div class="requirement" id="numberReq">
                                <i class="far fa-circle"></i>
                                <span>Contains a number</span>
                            </div>
                            <div class="requirement" id="specialReq">
                                <i class="far fa-circle"></i>
                                <span>Contains a special character</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" name="reset_password" class="btn" id="submitBtn">Reset Password</button>
                </form>
                
                <div class="auth-links">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
            <?php else: ?>
                <h2>Invalid Reset Link</h2>
                <p><?php echo htmlspecialchars($message); ?></p>
                
                <div class="auth-links">
                    <a href="forgot-password.php">Request New Reset Link</a>
                    <br><br>
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
            <?php endif; ?>
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
                &copy; <?php echo date('Y'); ?> Joy Bible Fellowship. All Rights Reserved.
            </div>
        </div>
    </footer>
    
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
        
        <?php if ($token_valid): ?>
        // Password Strength Checker
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordStrengthBar = document.getElementById('passwordStrengthBar');
        const lengthReq = document.getElementById('lengthReq');
        const numberReq = document.getElementById('numberReq');
        const specialReq = document.getElementById('specialReq');
        const submitBtn = document.getElementById('submitBtn');
        
        function checkPasswordStrength() {
            const password = newPasswordInput.value;
            let strength = 0;
            
            // Length requirement
            const hasLength = password.length >= 8;
            if (hasLength) strength += 30;
            lengthReq.classList.toggle('valid', hasLength);
            lengthReq.querySelector('i').className = hasLength ? 'fas fa-check-circle' : 'far fa-circle';
            
            // Number requirement
            const hasNumber = /\d/.test(password);
            if (hasNumber) strength += 30;
            numberReq.classList.toggle('valid', hasNumber);
            numberReq.querySelector('i').className = hasNumber ? 'fas fa-check-circle' : 'far fa-circle';
            
            // Special character requirement
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            if (hasSpecial) strength += 30;
            specialReq.classList.toggle('valid', hasSpecial);
            specialReq.querySelector('i').className = hasSpecial ? 'fas fa-check-circle' : 'far fa-circle';
            
            // Uppercase requirement (optional)
            const hasUpper = /[A-Z]/.test(password);
            if (hasUpper) strength += 10;
            
            // Update strength bar
            passwordStrengthBar.style.width = strength + '%';
            passwordStrengthBar.style.backgroundColor = 
                strength < 40 ? '#dc3545' : 
                strength < 70 ? '#ffc107' : '#28a745';
            
            // Check if passwords match
            const passwordsMatch = password === confirmPasswordInput.value && password.length > 0;
            submitBtn.disabled = !passwordsMatch || strength < 70;
        }
        
        function checkPasswordMatch() {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const passwordsMatch = password === confirmPassword && password.length > 0;
            
            if (confirmPassword.length > 0) {
                if (passwordsMatch) {
                    confirmPasswordInput.style.borderColor = '#28a745';
                } else {
                    confirmPasswordInput.style.borderColor = '#dc3545';
                }
            } else {
                confirmPasswordInput.style.borderColor = '';
            }
            
            submitBtn.disabled = !passwordsMatch || password.length < 8;
        }
        
        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength();
            checkPasswordMatch();
        });
        
        confirmPasswordInput.addEventListener('input', function() {
            checkPasswordMatch();
        });
        <?php endif; ?>
    </script>
</body>
</html>
