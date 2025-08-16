<?php
require_once 'config/database.php';

echo "<h2>Password Reset Token Debug</h2>";

try {
    // Check what's in the password_resets table
    $stmt = $pdo->query("SELECT * FROM password_resets ORDER BY created_at DESC");
    $resets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>All Password Reset Records:</h3>";
    if (count($resets) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Token</th><th>Expires At</th><th>Used</th><th>Created At</th></tr>";
        foreach ($resets as $reset) {
            echo "<tr>";
            echo "<td>{$reset['id']}</td>";
            echo "<td>{$reset['user_id']}</td>";
            echo "<td style='word-break: break-all; max-width: 200px;'>{$reset['token']}</td>";
            echo "<td>{$reset['expires_at']}</td>";
            echo "<td>{$reset['used']}</td>";
            echo "<td>{$reset['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if any tokens are expired
        echo "<h3>Token Status Check:</h3>";
        foreach ($resets as $reset) {
            $expires = new DateTime($reset['expires_at']);
            $now = new DateTime();
            $isExpired = $now > $expires;
            $status = $isExpired ? "❌ EXPIRED" : "✅ VALID";
            $usedStatus = $reset['used'] ? "❌ USED" : "✅ UNUSED";
            
            echo "<p><strong>Token {$reset['id']}:</strong> {$status} | {$usedStatus}</p>";
            echo "<p>Expires: {$reset['expires_at']} | Current time: " . $now->format('Y-m-d H:i:s') . "</p>";
            
            if (!$isExpired && !$reset['used']) {
                echo "<p>✅ This token should work!</p>";
            }
        }
        
    } else {
        echo "<p>No password reset records found.</p>";
    }
    
    // Test the exact query from reset-password.php
    echo "<h3>Testing Token Validation Query:</h3>";
    if (count($resets) > 0) {
        $testToken = $resets[0]['token'];
        echo "<p>Testing with token: {$testToken}</p>";
        
        $stmt = $pdo->prepare("
            SELECT pr.user_id, pr.expires_at, u.first_name, u.last_name, u.email 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
        ");
        $stmt->execute([$testToken]);
        $result = $stmt->fetch();
        
        if ($result) {
            echo "<p style='color: green;'>✅ Token validation successful!</p>";
            echo "<p>User: {$result['first_name']} {$result['last_name']} ({$result['email']})</p>";
        } else {
            echo "<p style='color: red;'>❌ Token validation failed!</p>";
            
            // Check each condition separately
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ?");
            $stmt->execute([$testToken]);
            $tokenExists = $stmt->fetch();
            
            if ($tokenExists) {
                echo "<p>Token exists in database</p>";
                echo "<p>Used: " . ($tokenExists['used'] ? 'Yes' : 'No') . "</p>";
                echo "<p>Expires at: {$tokenExists['expires_at']}</p>";
                
                $expires = new DateTime($tokenExists['expires_at']);
                $now = new DateTime();
                echo "<p>Current time: " . $now->format('Y-m-d H:i:s') . "</p>";
                echo "<p>Is expired: " . ($now > $expires ? 'Yes' : 'No') . "</p>";
            } else {
                echo "<p>Token not found in database</p>";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='forgot-password.php'>Request New Password Reset</a></p>";
echo "<p><a href='index.php'>Back to Home</a></p>";
?>
