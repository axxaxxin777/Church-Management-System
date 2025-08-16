<?php
require_once 'config/database.php';

echo "<h2>User Connection Debug</h2>";

try {
    // Get the token from password_resets
    $stmt = $pdo->query("SELECT * FROM password_resets ORDER BY created_at DESC LIMIT 1");
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reset) {
        echo "<h3>Password Reset Record:</h3>";
        echo "<p><strong>User ID:</strong> {$reset['user_id']}</p>";
        echo "<p><strong>Token:</strong> {$reset['token']}</p>";
        echo "<p><strong>Expires:</strong> {$reset['expires_at']}</p>";
        echo "<p><strong>Used:</strong> " . ($reset['used'] ? 'Yes' : 'No') . "</p>";
        
        // Check if user exists
        echo "<h3>User Check:</h3>";
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$reset['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<p style='color: green;'>✅ User found!</p>";
            echo "<p><strong>User ID:</strong> {$user['id']}</p>";
            echo "<p><strong>Name:</strong> {$user['first_name']} {$user['last_name']}</p>";
            echo "<p><strong>Email:</strong> {$user['email']}</p>";
        } else {
            echo "<p style='color: red;'>❌ User NOT found!</p>";
            echo "<p>User ID {$reset['user_id']} does not exist in users table.</p>";
        }
        
        // Test the JOIN query step by step
        echo "<h3>Testing JOIN Query Step by Step:</h3>";
        
        // Step 1: Check password_resets table
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ?");
        $stmt->execute([$reset['token']]);
        $tokenCheck = $stmt->fetch();
        echo "<p><strong>Step 1 - Token Check:</strong> " . ($tokenCheck ? "✅ Token found" : "❌ Token not found") . "</p>";
        
        // Step 2: Check if token is unused
        if ($tokenCheck) {
            echo "<p><strong>Step 2 - Used Check:</strong> " . ($tokenCheck['used'] == 0 ? "✅ Token unused" : "❌ Token already used") . "</p>";
        }
        
        // Step 3: Check if token is expired
        if ($tokenCheck) {
            $expires = new DateTime($tokenCheck['expires_at']);
            $now = new DateTime();
            $isExpired = $now > $expires;
            echo "<p><strong>Step 3 - Expiration Check:</strong> " . (!$isExpired ? "✅ Token not expired" : "❌ Token expired") . "</p>";
        }
        
        // Step 4: Check user exists
        if ($tokenCheck) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$tokenCheck['user_id']]);
            $userCheck = $stmt->fetch();
            echo "<p><strong>Step 4 - User Check:</strong> " . ($userCheck ? "✅ User exists" : "❌ User not found") . "</p>";
        }
        
        // Step 5: Test the full JOIN query
        echo "<h3>Full JOIN Query Test:</h3>";
        $stmt = $pdo->prepare("
            SELECT pr.user_id, pr.expires_at, u.first_name, u.last_name, u.email 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
        ");
        $stmt->execute([$reset['token']]);
        $fullResult = $stmt->fetch();
        
        if ($fullResult) {
            echo "<p style='color: green;'>✅ Full query successful!</p>";
            echo "<p><strong>Result:</strong> User {$fullResult['first_name']} {$fullResult['last_name']}</p>";
        } else {
            echo "<p style='color: red;'>❌ Full query failed!</p>";
            
            // Check what's in the users table
            echo "<h3>Users Table Check:</h3>";
            $stmt = $pdo->query("SELECT id, first_name, last_name, email FROM users ORDER BY id");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($users) > 0) {
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>ID</th><th>Name</th><th>Email</th></tr>";
                foreach ($users as $u) {
                    $highlight = ($u['id'] == $reset['user_id']) ? "style='background-color: yellow;'" : "";
                    echo "<tr {$highlight}>";
                    echo "<td>{$u['id']}</td>";
                    echo "<td>{$u['first_name']} {$u['last_name']}</td>";
                    echo "<td>{$u['email']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No users found in users table.</p>";
            }
        }
        
    } else {
        echo "<p>No password reset records found.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='forgot-password.php'>Request New Password Reset</a></p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
?>
