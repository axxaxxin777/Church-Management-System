<?php
// Installation script for Joy Bible Fellowship
// Run this file once to set up your database and initial configuration

// Check if already installed
if (file_exists('config/database.php')) {
    die('System already installed. Remove install.php for security.');
}

$error = '';
$success = '';

if ($_POST && isset($_POST['install'])) {
    $db_host = trim($_POST['db_host']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    $db_name = trim($_POST['db_name']);
    
    if ($db_host && $db_user && $db_name) {
        try {
            // Test database connection
            $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
            $pdo->exec("USE `$db_name`");
            
            // Create tables
            $sql = "
            -- Users table
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(50) NOT NULL,
                last_name VARCHAR(50) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'member', 'guest') DEFAULT 'guest',
                phone VARCHAR(20),
                address TEXT,
                city VARCHAR(50),
                state VARCHAR(50),
                zip_code VARCHAR(20),
                member_since DATE,
                profile_image VARCHAR(255),
                is_active BOOLEAN DEFAULT TRUE,
                email_verified BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );

            -- Events table
            CREATE TABLE events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                description TEXT,
                event_date DATE NOT NULL,
                event_time TIME,
                end_time TIME,
                location VARCHAR(200),
                max_attendees INT,
                current_attendees INT DEFAULT 0,
                image VARCHAR(255),
                category VARCHAR(100),
                is_featured BOOLEAN DEFAULT FALSE,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            );

            -- Sermons table
            CREATE TABLE sermons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                description TEXT,
                video_url VARCHAR(500) NOT NULL,
                video_embed_code TEXT,
                speaker VARCHAR(100),
                sermon_date DATE,
                duration VARCHAR(20),
                views INT DEFAULT 0,
                is_featured BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );

            -- Prayer requests
            CREATE TABLE prayer_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                title VARCHAR(200),
                request_text TEXT NOT NULL,
                is_anonymous BOOLEAN DEFAULT FALSE,
                is_answered BOOLEAN DEFAULT FALSE,
                answered_date DATE,
                answered_text TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            -- Contact messages
            CREATE TABLE contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                subject VARCHAR(200),
                message TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            -- Password resets table
            CREATE TABLE password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                used BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            -- Settings table
            CREATE TABLE settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                description TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );
            ";
            
            $pdo->exec($sql);
            
            // Insert default settings
            $settings = [
                ['church_name', 'Joy Bible Fellowship', 'Name of the church'],
                ['church_tagline', 'Spreading Joy Through God\'s Word', 'Church tagline'],
                ['church_address', '123 Joy Street, Bible City, BC 12345', 'Church address'],
                ['church_phone', '(123) 456-7890', 'Church phone number'],
                ['church_email', 'info@joybiblefellowship.org', 'Church email address'],
                ['sunday_service_time', '9:00 AM & 11:00 AM', 'Sunday service times'],
                ['sunday_service_time', '9:00 AM & 11:00 AM', 'Sunday service times'],
                ['bible_study_time', 'Wednesdays at 7:00 PM', 'Bible study time'],
                ['youth_group_time', 'Fridays at 7:00 PM', 'Youth group time']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
            foreach ($settings as $setting) {
                $stmt->execute($setting);
            }
            
            // Insert sample admin user (password: admin123)
            $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, member_since) VALUES (?, ?, ?, ?, 'admin', CURDATE())");
            $stmt->execute(['Admin', 'User', 'admin@joybiblefellowship.org', $admin_password]);
            
            // Insert sample sermons
            $sermons = [
                ['The Power of Faith', 'In this message, we explore how faith can move mountains in our lives when we fully trust in God\'s promises and power.', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>', 'Pastor John Smith', '2023-06-12'],
                ['Love Your Neighbor', 'Jesus calls us to love our neighbors as ourselves. This sermon unpacks what that means in practical terms for our daily lives.', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>', 'Pastor Sarah Johnson', '2023-06-05'],
                ['Finding Peace in Chaos', 'In a world full of turmoil and stress, discover how to experience God\'s perfect peace that surpasses all understanding.', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>', 'Pastor Michael Brown', '2023-05-29']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO sermons (title, description, video_url, video_embed_code, speaker, sermon_date) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($sermons as $sermon) {
                $stmt->execute($sermon);
            }
            
            // Insert sample events
            $events = [
                ['Community Outreach Day', 'Join us as we serve our community through various outreach activities including free meals, clothing distribution, and prayer stations.', '2023-06-15', '09:00:00', 'City Park', 'Outreach', TRUE],
                ['Summer Bible Conference', 'A three-day conference featuring guest speakers, worship nights, and workshops to deepen your faith and biblical understanding.', '2023-06-22', '18:00:00', 'Church Sanctuary', 'Conference', TRUE],
                ['Youth Summer Camp', 'A week-long summer camp for teens with outdoor activities, worship, and Bible studies designed just for them.', '2023-06-30', '09:00:00', 'Lakeview Retreat Center', 'Youth', TRUE]
            ];
            
            $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, event_time, location, category, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($events as $event) {
                $stmt->execute($event);
            }
            
            // Create config directory and database.php file
            if (!is_dir('config')) {
                mkdir('config', 0755, true);
            }
            
            $config_content = "<?php
// Database configuration
define('DB_HOST', '$db_host');
define('DB_USER', '$db_user');
define('DB_PASS', '$db_pass');
define('DB_NAME', '$db_name');

// Create connection
try {
    \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME, DB_USER, DB_PASS);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException \$e) {
    die(\"Connection failed: \" . \$e->getMessage());
}

            // Pusher configuration (update with your credentials)
            define('PUSHER_APP_ID', '2034789');
            define('PUSHER_KEY', '52a1f3b0b3938b43e304');
            define('PUSHER_SECRET', 'ba8e17611b34f49c482f');
            define('PUSHER_CLUSTER', 'ap1');

            // PHPMailer configuration (update with your credentials)
            define('MAIL_HOST', 'smtp.gmail.com');
            define('MAIL_PORT', 587);
            define('MAIL_USERNAME', 'jmsof777@gmail.com');
            define('MAIL_PASSWORD', 'mgufwhnjaqjycabl');
            define('MAIL_FROM_NAME', 'Grace Community Church');
            define('MAIL_FROM_EMAIL', 'jmsof777@gmail.com');
?>";
            
            file_put_contents('config/database.php', $config_content);
            
            $success = 'Installation completed successfully! You can now login with:<br><strong>Email:</strong> admin@joybiblefellowship.org<br><strong>Password:</strong> admin123<br><br><strong>Important:</strong> Delete this install.php file for security!';
            
        } catch(PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Install Joy Bible Fellowship</title>
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
            background: linear-gradient(135deg, rgba(109, 76, 65, 0.1) 0%, rgba(61, 39, 35, 0.1) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .install-container {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 600px;
        }

        .install-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 2rem;
            text-align: center;
        }

        .install-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--accent);
        }

        .install-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .install-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .install-form {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
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
            border: 2px solid var(--light);
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(109, 76, 65, 0.1);
        }

        .install-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .install-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .requirements {
            background-color: var(--light);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .requirements h3 {
            color: var(--primary);
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .requirements ul {
            list-style: none;
            padding-left: 0;
        }

        .requirements li {
            padding: 0.25rem 0;
            font-size: 0.9rem;
        }

        .requirements li i {
            margin-right: 0.5rem;
            color: var(--success);
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <i class="fas fa-church"></i>
            <h1>Joy Bible Fellowship</h1>
            <p>Installation Wizard</p>
        </div>
        
        <div class="install-form">
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <?php echo $success; ?>
                </div>
            <?php else: ?>
                <div class="requirements">
                    <h3>System Requirements:</h3>
                    <ul>
                        <li><i class="fas fa-check"></i> PHP 7.4 or higher</li>
                        <li><i class="fas fa-check"></i> MySQL 5.7 or higher</li>
                        <li><i class="fas fa-check"></i> PDO MySQL extension</li>
                        <li><i class="fas fa-check"></i> Write permissions for config directory</li>
                    </ul>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="db_host">Database Host</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_user">Database Username</label>
                        <input type="text" id="db_user" name="db_user" value="root" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_pass">Database Password</label>
                        <input type="password" id="db_pass" name="db_pass">
                    </div>
                    
                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" id="db_name" name="db_name" value="grace_community" required>
                    </div>
                    
                    <button type="submit" name="install" class="install-btn">
                        Install System
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
