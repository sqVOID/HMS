<?php
/**
 * Test Accounts Reference Page
 * Shows all test account credentials for easy reference
 * URL: http://localhost/Hotel%20Management%20System/test_accounts.php
 */

require_once 'config.php';

$accounts = [];
try {
    $stmt = $conn->prepare("SELECT id, username, access_level, created_at FROM users ORDER BY id");
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Accounts Reference</title>
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
        .info-box {
            padding: 20px;
            background: #e3f2fd;
            border-radius: 6px;
            border-left: 4px solid #2196f3;
            margin-bottom: 20px;
        }
        .info-box h3 { color: #1976d2; margin-bottom: 15px; }
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
        .access-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-admin { background: #dc3545; color: white; }
        .badge-staff { background: #ffc107; color: #333; }
        .badge-user { background: #28a745; color: white; }
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
        .error {
            padding: 15px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 6px;
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 Test Accounts Reference</h1>
        <p class="subtitle">Sample login credentials for testing</p>
        
        <?php if (isset($error)): ?>
            <div class="error">Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📋 Default Test Accounts</h3>
            <p style="margin-bottom: 15px;">All test accounts use the same password: <code>password123</code></p>
            
            <?php if (!empty($accounts)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Access Level</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($account['id']); ?></td>
                                <td><code><?php echo htmlspecialchars($account['username']); ?></code></td>
                                <td class="password-cell">password123</td>
                                <td>
                                    <span class="access-badge badge-<?php echo $account['access_level']; ?>">
                                        <?php echo htmlspecialchars(ucfirst($account['access_level'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($account['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #666;">No users found in database. Run <code>quick_setup.php</code> first.</p>
            <?php endif; ?>
        </div>
        
        <div class="info-box">
            <h3>🚀 Quick Login Test</h3>
            <p><strong>Try logging in with:</strong></p>
            <ul style="margin-left: 20px; margin-top: 10px; line-height: 2;">
                <li>Username: <code>admin</code> | Password: <code>password123</code> (Full Admin Access)</li>
                <li>Username: <code>staff</code> | Password: <code>password123</code> (Staff Access)</li>
                <li>Username: <code>user</code> | Password: <code>password123</code> (User Access)</li>
            </ul>
        </div>
        
        <a href="Login.html" class="button">🔐 Go to Login Page</a>
        
        <div class="warning">
            <strong>⚠️ Security Note:</strong> This page shows passwords in plain text for testing purposes only. 
            In production, passwords are securely hashed and never displayed. Delete this file before deploying to production!
        </div>
    </div>
</body>
</html>

