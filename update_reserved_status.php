<?php
/**
 * Database Update Script for Reserved! Status
 * This script ensures the rooms table can handle the "Reserved!" status
 * Run this once to verify database compatibility
 */

require_once 'config.php';

$messages = [];
$errors = [];

try {
    // Check if rooms table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'rooms'");
    
    if ($checkTable->rowCount() > 0) {
        // Check the status column type
        $checkColumn = $conn->query("SHOW COLUMNS FROM rooms LIKE 'status'");
        $columnInfo = $checkColumn->fetch(PDO::FETCH_ASSOC);
        
        if ($columnInfo) {
            $messages[] = "✓ Status column exists in rooms table";
            $messages[] = "  Column Type: " . $columnInfo['Type'];
            $messages[] = "  Default: " . ($columnInfo['Default'] ?? 'NULL');
            
            // If it's an ENUM, we need to modify it to include Reserved!
            if (stripos($columnInfo['Type'], 'enum') !== false) {
                $messages[] = "⚠ Status column is ENUM type - updating to include 'Reserved!'";
                
                try {
                    $conn->exec("ALTER TABLE rooms MODIFY COLUMN status ENUM('Available', 'Occupied', 'Reserved', 'Out of Order', 'Confirming') DEFAULT 'Available'");
                    $messages[] = "✓ Successfully updated ENUM to include 'Reserved' status";
                } catch (PDOException $e) {
                    $errors[] = "✗ Failed to update ENUM: " . $e->getMessage();
                }
            } else {
                // It's VARCHAR or TEXT, which is fine
                $messages[] = "✓ Status column type supports 'Reserved!' status (VARCHAR/TEXT)";
            }
        } else {
            $errors[] = "✗ Status column not found in rooms table";
        }
        
        // Test query to see if any rooms have Reserved status
        $testQuery = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'Reserved'");
        $result = $testQuery->fetch(PDO::FETCH_ASSOC);
        $messages[] = "ℹ Current rooms with 'Reserved' status: " . $result['count'];
        
        // Show all distinct statuses in the database
        $statusQuery = $conn->query("SELECT DISTINCT status FROM rooms ORDER BY status");
        $statuses = $statusQuery->fetchAll(PDO::FETCH_COLUMN);
        $messages[] = "ℹ All current room statuses in database: " . implode(', ', $statuses);
        
    } else {
        $errors[] = "✗ Rooms table does not exist!";
    }
    
} catch (PDOException $e) {
    $errors[] = "✗ Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Reserved! Status</title>
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
            max-width: 800px;
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
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 6px;
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
            font-size: 14px;
            line-height: 1.6;
        }
        .error {
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 6px;
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
            font-size: 14px;
            line-height: 1.6;
        }
        .info {
            margin-top: 30px;
            padding: 20px;
            background: #fff3cd;
            border-radius: 6px;
            border-left: 4px solid #ffc107;
        }
        .info h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        .info p {
            color: #555;
            margin-bottom: 8px;
            line-height: 1.6;
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
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Reserved! Status Update</h1>
        <p class="subtitle">Database compatibility check for Reserved! room status</p>
        
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
                <h3>✅ Database is Ready!</h3>
                <p>Your database can now store and display the <code>Reserved</code> status for rooms.</p>
                <p><strong>Next Steps:</strong></p>
                <p>1. Go to Room List page</p>
                <p>2. Edit a room or add a new room</p>
                <p>3. Select "Reserved" from the status dropdown</p>
                <p>4. The room will display with an orange "Reserved" button</p>
            </div>
            
            <a href="Roomlist.html" class="button">Go to Room List</a>
        <?php else: ?>
            <div class="info">
                <h3>⚠️ Action Required</h3>
                <p>Please check the errors above and ensure:</p>
                <p>• MySQL/MariaDB is running</p>
                <p>• The rooms table exists in your database</p>
                <p>• You have proper database permissions</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
