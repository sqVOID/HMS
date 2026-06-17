<?php
/**
 * Update existing users table to use plain text passwords
 * Run this if you already have hashed passwords and want to convert to plain text
 * URL: http://localhost/Hotel%20Management%20System/update_users_to_plaintext.php
 */

require_once 'config.php';

$messages = [];
$errors = [];

try {
    // Update existing users to use plain text password
    $updateStmt = $conn->prepare("UPDATE users SET password = 'password123' WHERE username IN ('admin', 'staff', 'user')");
    $updateStmt->execute();
    $updatedCount = $updateStmt->rowCount();
    
    if ($updatedCount > 0) {
        $messages[] = "✓ Updated $updatedCount user(s) to use plain text password 'password123'";
    } else {
        $messages[] = "ℹ No users found to update, or passwords are already plain text";
    }
    
    // Verify current users
    $verifyStmt = $conn->prepare("SELECT id, username, password, access_level FROM users ORDER BY id");
    $verifyStmt->execute();
    $allUsers = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errors[] = "✗ Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Users to Plain Text</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 800px;
            width: 100%;
        }
        h1 { color: #333; margin-bottom: 10px; font-size: 28px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .message {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .error {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .info {
            margin-top: 30px;
            padding: 20px;
            background: #e3f2fd;
            border-radius: 6px;
            border-left: 4px solid #2196f3;
        }
        .info h3 { color: #1976d2; margin-bottom: 15px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: white;
        }
        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        table tr:hover {
            background: #f5f5f5;
        }
        .password-cell {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #28a745;
            font-size: 16px;
        }
        .button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .button:hover { transform: translateY(-2px); }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Update Users to Plain Text</h1>
        <p class="subtitle">Convert hashed passwords to visible plain text</p>
        
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message"><?php echo htmlspecialchars($msg); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="error"><?php echo htmlspecialchars($err); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($allUsers)): ?>
            <div class="info">
                <h3>📋 Current Users in Database:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Password (Visible)</th>
                            <th>Access Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['id']); ?></td>
                                <td><code><?php echo htmlspecialchars($u['username']); ?></code></td>
                                <td class="password-cell"><?php echo htmlspecialchars($u['password']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($u['access_level'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <a href="Login.html" class="button">🔐 Go to Login Page</a>
    </div>
</body>
</html>

