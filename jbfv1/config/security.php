<?php
// Security configuration for Joy Bible Fellowship
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security headers
function setSecurityHeaders() {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://js.pusher.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self' https://js.pusher.com;");
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

// Input sanitization
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Rate limiting
function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "rate_limit_{$action}_{$ip}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => time()];
    }
    
    $current = $_SESSION[$key];
    
    // Reset if time window has passed
    if (time() - $current['first_attempt'] > $timeWindow) {
        $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => time()];
        return true;
    }
    
    // Check if limit exceeded
    if ($current['attempts'] >= $maxAttempts) {
        return false;
    }
    
    // Increment attempts
    $_SESSION[$key]['attempts']++;
    return true;
}

// Password strength validation
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return $errors;
}

// Email validation
function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Check for suspicious patterns
    $suspiciousPatterns = [
        '/<script/i',
        '/javascript:/i',
        '/vbscript:/i',
        '/onload/i',
        '/onerror/i'
    ];
    
    foreach ($suspiciousPatterns as $pattern) {
        if (preg_match($pattern, $email)) {
            return false;
        }
    }
    
    return true;
}

// SQL Injection prevention
function preventSQLInjection($input) {
    if (is_array($input)) {
        return array_map('preventSQLInjection', $input);
    }
    
    // Remove SQL keywords and patterns
    $sqlPatterns = [
        '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b/i',
        '/[\'";]/',
        '/--/',
        '/\/\*/',
        '/\*\//'
    ];
    
    foreach ($sqlPatterns as $pattern) {
        $input = preg_replace($pattern, '', $input);
    }
    
    return $input;
}

// XSS Prevention
function preventXSS($input) {
    if (is_array($input)) {
        return array_map('preventXSS', $input);
    }
    
    // Remove dangerous HTML tags and attributes
    $dangerousTags = [
        '/<script[^>]*>.*?<\/script>/is',
        '/<iframe[^>]*>.*?<\/iframe>/is',
        '/<object[^>]*>.*?<\/object>/is',
        '/<embed[^>]*>.*?<\/embed>/is',
        '/<form[^>]*>.*?<\/form>/is',
        '/<input[^>]*>/i',
        '/<textarea[^>]*>.*?<\/textarea>/is',
        '/<select[^>]*>.*?<\/select>/is'
    ];
    
    foreach ($dangerousTags as $pattern) {
        $input = preg_replace($pattern, '', $input);
    }
    
    // Remove dangerous attributes
    $dangerousAttributes = [
        '/on\w+\s*=/i',
        '/javascript:/i',
        '/vbscript:/i',
        '/data:/i'
    ];
    
    foreach ($dangerousAttributes as $pattern) {
        $input = preg_replace($pattern, '', $input);
    }
    
    return $input;
}

// Log security events
function logSecurityEvent($event, $details = '') {
    $logEntry = date('Y-m-d H:i:s') . " - {$event} - {$details} - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
    $logFile = __DIR__ . '/../logs/security.log';
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Initialize security
setSecurityHeaders();
?>
