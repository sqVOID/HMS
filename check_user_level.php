<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check User Access Level</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .info-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .info-row {
            margin: 15px 0;
            padding: 10px;
            background: #f9f9f9;
            border-left: 4px solid #4ba85f;
            border-radius: 4px;
        }
        .label {
            font-weight: 600;
            color: #555;
        }
        .value {
            color: #1a1a1a;
            font-size: 18px;
            font-weight: 700;
        }
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="info-box">
        <h1>🔍 User Access Level Checker</h1>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="info-row">
                <div class="label">User ID:</div>
                <div class="value"><?php echo htmlspecialchars($_SESSION['user_id']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="label">Username:</div>
                <div class="value"><?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="info-row <?php echo ($_SESSION['access_level'] ?? '') === 'super_admin' ? 'success' : 'warning'; ?>">
                <div class="label">Access Level:</div>
                <div class="value"><?php echo strtoupper($_SESSION['access_level'] ?? 'N/A'); ?></div>
            </div>
            
            <?php if (isset($_SESSION['first_name']) || isset($_SESSION['last_name'])): ?>
            <div class="info-row">
                <div class="label">Full Name:</div>
                <div class="value">
                    <?php echo htmlspecialchars(trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''))); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <div class="label">Session ID:</div>
                <div class="value"><?php echo htmlspecialchars($_SESSION['session_id'] ?? 'N/A'); ?></div>
            </div>
            
            <?php if (($_SESSION['access_level'] ?? '') === 'super_admin'): ?>
                <div class="info-row success">
                    <strong>✅ You can access System Log!</strong>
                </div>
            <?php else: ?>
                <div class="info-row warning">
                    <strong>⚠️ You cannot access System Log. Only SUPER_ADMIN can access it.</strong>
                    <p style="margin: 10px 0 0 0; font-size: 14px;">
                        Please log in with a Super Admin account to see the System Log menu.
                    </p>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="info-row warning">
                <strong>❌ You are not logged in!</strong>
                <p style="margin: 10px 0 0 0;">Please <a href="Login.html">log in</a> first.</p>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="Report.php" style="display: inline-block; padding: 10px 20px; background: #4ba85f; color: white; text-decoration: none; border-radius: 5px;">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
