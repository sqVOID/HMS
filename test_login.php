<?php
/**
 * Test Login Verification
 * This script helps debug login issues
 */

require_once 'config.php';

echo "<h2>Login Verification Test</h2>";

// Test with sample credentials
$testUsername = 'admin';
$testPassword = 'password123';
$wrongPassword = 'wrongpassword';

try {
    // Get user from database
    $stmt = $conn->prepare("SELECT id, username, password, access_level FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$testUsername]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<h3>User Found:</h3>";
        echo "<pre>";
        echo "ID: " . htmlspecialchars($user['id']) . "\n";
        echo "Username: " . htmlspecialchars($user['username']) . "\n";
        echo "Password (from DB): " . htmlspecialchars($user['password']) . "\n";
        echo "Password Length: " . strlen($user['password']) . "\n";
        echo "Access Level: " . htmlspecialchars($user['access_level']) . "\n";
        echo "</pre>";
        
        echo "<h3>Password Comparison Tests:</h3>";
        
        // Test 1: Correct password
        $test1 = ($testPassword === $user['password']);
        echo "<p>Test 1 - Correct password ('password123'): " . ($test1 ? "✅ MATCH" : "❌ NO MATCH") . "</p>";
        echo "<pre>Input: '$testPassword' (length: " . strlen($testPassword) . ")\n";
        echo "DB:    '{$user['password']}' (length: " . strlen($user['password']) . ")\n";
        echo "Match: " . ($test1 ? 'YES' : 'NO') . "</pre>";
        
        // Test 2: Wrong password
        $test2 = ($wrongPassword === $user['password']);
        echo "<p>Test 2 - Wrong password ('wrongpassword'): " . ($test2 ? "❌ INCORRECTLY MATCHED" : "✅ CORRECTLY REJECTED") . "</p>";
        echo "<pre>Input: '$wrongPassword' (length: " . strlen($wrongPassword) . ")\n";
        echo "DB:    '{$user['password']}' (length: " . strlen($user['password']) . ")\n";
        echo "Match: " . ($test2 ? 'YES (WRONG!)' : 'NO (CORRECT)') . "</pre>";
        
        // Test 3: Empty password
        $test3 = ('' === $user['password']);
        echo "<p>Test 3 - Empty password: " . ($test3 ? "❌ INCORRECTLY MATCHED" : "✅ CORRECTLY REJECTED") . "</p>";
        
        // Test 4: Trimmed comparison
        $test4 = (trim($testPassword) === trim($user['password']));
        echo "<p>Test 4 - Trimmed comparison: " . ($test4 ? "✅ MATCH" : "❌ NO MATCH") . "</p>";
        
        echo "<h3>All Users in Database:</h3>";
        $allStmt = $conn->prepare("SELECT id, username, password, access_level FROM users");
        $allStmt->execute();
        $allUsers = $allStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Username</th><th>Password</th><th>Password Length</th><th>Access Level</th></tr>";
        foreach ($allUsers as $u) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($u['id']) . "</td>";
            echo "<td>" . htmlspecialchars($u['username']) . "</td>";
            echo "<td>" . htmlspecialchars($u['password']) . "</td>";
            echo "<td>" . strlen($u['password']) . "</td>";
            echo "<td>" . htmlspecialchars($u['access_level']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p style='color:red;'>❌ User '$testUsername' not found in database!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

