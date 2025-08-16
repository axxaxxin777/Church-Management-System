-- SQL script to completely reset the database
DROP DATABASE IF EXISTS grace_community;
CREATE DATABASE grace_community;
USE grace_community;

-- Create all tables from schema
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

-- Event registrations
CREATE TABLE event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT,
    user_id INT,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'attended', 'cancelled') DEFAULT 'registered',
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sermons table (embedded videos only)
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

-- Settings table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Visitors table for tracking online users
CREATE TABLE visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Active visitors view (visitors active in the last 5 minutes)
CREATE VIEW active_visitors AS
SELECT * FROM visitors WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('church_name', 'Joy Bible Fellowship', 'Name of the church'),
('church_tagline', 'Spreading Joy Through God\'s Word', 'Church tagline'),
('church_address', '123 Joy Street, Bible City, BC 12345', 'Church address'),
('church_phone', '(123) 456-7890', 'Church phone number'),
('church_email', 'info@joybiblefellowship.org', 'Church email address'),
('sunday_service_time', '9:00 AM & 11:00 AM', 'Sunday service times'),
('bible_study_time', 'Wednesdays at 7:00 PM', 'Bible study time'),
('youth_group_time', 'Fridays at 7:00 PM', 'Youth group time');

-- Insert sample admin user (password: admin123)
INSERT INTO users (first_name, last_name, email, password, role, member_since) VALUES
('Admin', 'User', 'admin@joybiblefellowship.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2020-01-01');

-- Insert sample sermons
INSERT INTO sermons (title, description, video_url, video_embed_code, speaker, sermon_date) VALUES
('The Power of Faith', 'In this message, we explore how faith can move mountains in our lives when we fully trust in God\'s promises and power.', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>', 'Pastor John Smith', '2023-06-12'),
('Love Your Neighbor', 'Jesus calls us to love our neighbors as ourselves. This sermon unpacks what that means in practical terms for our daily lives.', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>', 'Pastor Sarah Johnson', '2023-06-05'),
('Finding Peace in Chaos', 'In a world full of turmoil and stress, discover how to experience God\'s perfect peace that surpasses all understanding.', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>', 'Pastor Michael Brown', '2023-05-29');

-- Insert sample events
INSERT INTO events (title, description, event_date, event_time, location, category, is_featured) VALUES
('Community Outreach Day', 'Join us as we serve our community through various outreach activities including free meals, clothing distribution, and prayer stations.', '2023-06-15', '09:00:00', 'City Park', 'Outreach', TRUE),
('Summer Bible Conference', 'A three-day conference featuring guest speakers, worship nights, and workshops to deepen your faith and biblical understanding.', '2023-06-22', '18:00:00', 'Church Sanctuary', 'Conference', TRUE),
('Youth Summer Camp', 'A week-long summer camp for teens with outdoor activities, worship, and Bible studies designed just for them.', '2023-06-30', '09:00:00', 'Lakeview Retreat Center', 'Youth', TRUE);