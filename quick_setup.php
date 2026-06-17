<?php
/**
 * Quick Database Setup - Run this file in your browser
 * URL: http://localhost/Hotel%20Management%20System/quick_setup.php
 * This will automatically create the users table and add sample accounts
 */

require_once 'config.php';

$success = false;
$messages = [];
$errors = [];

try {
    // Step 1: Create users table
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(100) NOT NULL,
      `password` varchar(255) NOT NULL,
      `access_level` enum('staff','admin','user') NOT NULL DEFAULT 'user',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $conn->exec($createTableSQL);
    $messages[] = "✓ Users table created successfully!";
    
    // Step 2: Check if users exist
    $checkStmt = $conn->prepare("SELECT username FROM users WHERE username IN ('admin', 'staff', 'user')");
    $checkStmt->execute();
    $existingUsers = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Step 3: Use plain text password 'password123' (visible in database)
    $plainPassword = 'password123';
    
    // Step 4: Insert users that don't exist
    $usersToInsert = [
        ['admin', $plainPassword, 'admin'],
        ['staff', $plainPassword, 'staff'],
        ['user', $plainPassword, 'user']
    ];
    
    $insertStmt = $conn->prepare("INSERT IGNORE INTO users (username, password, access_level) VALUES (?, ?, ?)");
    $insertedCount = 0;
    
    foreach ($usersToInsert as $user) {
        if (!in_array($user[0], $existingUsers)) {
            $insertStmt->execute($user);
            $insertedCount++;
        }
    }
    
    if ($insertedCount > 0) {
        $messages[] = "✓ Created $insertedCount new user account(s)!";
    } else {
        $messages[] = "ℹ All example users already exist.";
    }
    
    // Step 5: Add missing columns to reports table
    try {
        // Check if reports table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
        if ($checkTable->rowCount() > 0) {
            // Add additional_food column
            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'additional_food'");
                if ($checkColumn->rowCount() === 0) {
                    $conn->exec("ALTER TABLE reports ADD COLUMN additional_food TEXT NULL DEFAULT NULL AFTER additional");
                    $messages[] = "✓ Added additional_food column to reports table";
                }
            } catch (PDOException $e) {
                // Column might already exist, continue
            }
            
            // Add additional_items column
            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'additional_items'");
                if ($checkColumn->rowCount() === 0) {
                    $conn->exec("ALTER TABLE reports ADD COLUMN additional_items TEXT NULL DEFAULT NULL AFTER additional_food");
                    $messages[] = "✓ Added additional_items column to reports table";
                }
            } catch (PDOException $e) {
                // Column might already exist, continue
            }
        }
    } catch (PDOException $e) {
        // Ignore - reports table may not exist yet
    }
    
    // Step 6: Verify users were created
    $verifyStmt = $conn->prepare("SELECT username, access_level FROM users ORDER BY username");
    $verifyStmt->execute();
    $allUsers = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $success = true;
    
} catch (PDOException $e) {
    $errors[] = "✗ Database Error: " . $e->getMessage();
    $errors[] = "Please check your database connection in config.php";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Database Setup</title>
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
            max-width: 700px;
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
        .info table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .info table th,
        .info table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .info table th {
            background: #bbdefb;
            font-weight: 600;
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
        .warning {
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 6px;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
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
        <h1>⚡ Quick Database Setup</h1>
        <p class="subtitle">Hotel Management System - Users Table</p>
        
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
        
        <?php if ($success): ?>
            <div class="info">
                <h3>✅ Setup Complete! You can now login with:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Access Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>admin</code></td>
                            <td><code>password123</code></td>
                            <td>Admin</td>
                        </tr>
                        <tr>
                            <td><code>staff</code></td>
                            <td><code>password123</code></td>
                            <td>Staff</td>
                        </tr>
                        <tr>
                            <td><code>user</code></td>
                            <td><code>password123</code></td>
                            <td>User</td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (!empty($allUsers)): ?>
                    <h3 style="margin-top: 20px;">📋 All Users in Database:</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Access Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($u['access_level'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <a href="Login.html" class="button">🚀 Go to Login Page</a>
            
            <div class="warning">
                <strong>⚠️ Security Note:</strong> Change these default passwords after first login! You can delete this setup file after use.
            </div>
        <?php else: ?>
            <div class="warning">
                <strong>⚠️ Setup Failed</strong><br>
                Please check:
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>MySQL/MariaDB service is running in XAMPP</li>
                    <li>Database 'hotel_management' exists</li>
                    <li>Check <code>config.php</code> for correct database settings</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

