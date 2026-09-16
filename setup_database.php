<?php
/**
 * Database Setup Script
 * Run this file once to create the users table and example accounts
 * Access via: http://localhost/Hotel%20Management%20System/setup_database.php
 */

require_once 'config.php';

$messages = [];
$errors = [];

try {
    // Create users table
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
    
    // Check if example users already exist
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username IN ('admin', 'staff', 'user')");
    $checkStmt->execute();
    $existingCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($existingCount == 0) {
        // Generate password hash for 'password123'
        $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
        
        // Insert example users
        $insertSQL = "
        INSERT INTO `users` (`username`, `password`, `access_level`) VALUES
        ('admin', :password, 'admin'),
        ('staff', :password, 'staff'),
        ('user', :password, 'user')
        ";
        
        $insertStmt = $conn->prepare($insertSQL);
        $insertStmt->execute(['password' => $passwordHash]);
        $messages[] = "✓ Example user accounts created successfully!";
        $messages[] = "  - Username: admin, Password: password123 (Admin)";
        $messages[] = "  - Username: staff, Password: password123 (Staff)";
        $messages[] = "  - Username: user, Password: password123 (User)";
    } else {
        $messages[] = "ℹ Example users already exist. Skipping user creation.";
    }
    
} catch (PDOException $e) {
    $errors[] = "✗ Error: " . $e->getMessage();
}

// Display results
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
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
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
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
        .info h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        .info p {
            color: #555;
            margin-bottom: 8px;
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
        .button:hover {
            transform: translateY(-2px);
        }
        .warning {
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 6px;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Database Setup</h1>
        <p class="subtitle">Hotel Management System - Users Table Setup</p>
        
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
        
        <?php if (empty($errors)): ?>
            <div class="info">
                <h3>✅ Setup Complete!</h3>
                <p><strong>You can now log in with:</strong></p>
                <p>• Username: <code>admin</code> | Password: <code>password123</code> (Admin access)</p>
                <p>• Username: <code>staff</code> | Password: <code>password123</code> (Staff access)</p>
                <p>• Username: <code>user</code> | Password: <code>password123</code> (User access)</p>
                <p style="margin-top: 15px;"><strong>⚠️ Important:</strong> Change these passwords after first login!</p>
            </div>
            
            <a href="Login.html" class="button">Go to Login Page</a>
        <?php else: ?>
            <div class="warning">
                <strong>⚠️ Setup Failed</strong><br>
                Please check your database connection settings in <code>config.php</code> and ensure:
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>MySQL/MariaDB is running</li>
                    <li>Database 'hotel_management' exists</li>
                    <li>User 'root' has proper permissions</li>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="info" style="margin-top: 30px;">
            <h3>📝 Next Steps</h3>
            <p>1. After setup, you can delete this file for security</p>
            <p>2. Use <code>generate_password_hash.php</code> to create new user passwords</p>
            <p>3. Access protected pages - they will redirect to login if not authenticated</p>
        </div>
    </div>
</body>
</html>

