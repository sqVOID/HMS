<?php
/**
 * Fix Reserved! Status in Database
 * This script will update the rooms table to properly support Reserved! status
 * Run this once to fix the issue
 */

require_once 'config.php';

$messages = [];
$errors = [];

try {
    // Check if rooms table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'rooms'");
    
    if ($checkTable->rowCount() > 0) {
        $messages[] = "✓ Rooms table found";
        
        // Get current column information
        $checkColumn = $conn->query("SHOW COLUMNS FROM rooms LIKE 'status'");
        $columnInfo = $checkColumn->fetch(PDO::FETCH_ASSOC);
        
        if ($columnInfo) {
            $currentType = $columnInfo['Type'];
            $messages[] = "Current status column type: " . $currentType;
            
            // Check if it's an ENUM type
            if (stripos($currentType, 'enum') !== false) {
                $messages[] = "⚠ Status is ENUM - Converting to VARCHAR for flexibility";
                
                // Convert ENUM to VARCHAR to support any status including Reserved!
                $conn->exec("ALTER TABLE rooms MODIFY COLUMN status VARCHAR(50) DEFAULT 'Available'");
                $messages[] = "✓ Successfully converted status column to VARCHAR(50)";
            } else {
                $messages[] = "✓ Status column is already VARCHAR/TEXT - no conversion needed";
            }
            
            // Update any empty status values to 'Available'
            $updateEmpty = $conn->exec("UPDATE rooms SET status = 'Available' WHERE status IS NULL OR status = ''");
            if ($updateEmpty > 0) {
                $messages[] = "✓ Updated $updateEmpty room(s) with empty status to 'Available'";
            }
            
            // Show current status distribution
            $statusQuery = $conn->query("
                SELECT status, COUNT(*) as count 
                FROM rooms 
                GROUP BY status 
                ORDER BY status
            ");
            $statusCounts = $statusQuery->fetchAll(PDO::FETCH_ASSOC);
            
            $messages[] = "<br><strong>Current Room Status Distribution:</strong>";
            if (count($statusCounts) > 0) {
                foreach ($statusCounts as $stat) {
                    $messages[] = "  • " . ($stat['status'] ?: '(empty)') . ": " . $stat['count'] . " room(s)";
                }
            } else {
                $messages[] = "  No rooms in database yet";
            }
            
        } else {
            // Status column doesn't exist - create it
            $messages[] = "⚠ Status column not found - creating it";
            $conn->exec("ALTER TABLE rooms ADD COLUMN status VARCHAR(50) DEFAULT 'Available'");
            $messages[] = "✓ Created status column";
        }
        
        // Test inserting Reserved! status
        $messages[] = "<br><strong>Testing Reserved! Status:</strong>";
        $testQuery = $conn->prepare("SELECT id FROM rooms LIMIT 1");
        $testQuery->execute();
        $testRoom = $testQuery->fetch(PDO::FETCH_ASSOC);
        
        if ($testRoom) {
            // Try to update a room to Reserved! status
            $updateTest = $conn->prepare("UPDATE rooms SET status = 'Reserved!' WHERE id = :id");
            $updateTest->execute([':id' => $testRoom['id']]);
            
            // Verify it was saved correctly
            $verifyTest = $conn->prepare("SELECT status FROM rooms WHERE id = :id");
            $verifyTest->execute([':id' => $testRoom['id']]);
            $savedStatus = $verifyTest->fetch(PDO::FETCH_ASSOC);
            
            if ($savedStatus['status'] === 'Reserved!') {
                $messages[] = "✓ Successfully saved and retrieved 'Reserved!' status";
                
                // Revert the test
                $revertTest = $conn->prepare("UPDATE rooms SET status = 'Available' WHERE id = :id");
                $revertTest->execute([':id' => $testRoom['id']]);
                $messages[] = "✓ Test completed - room status reverted";
            } else {
                $errors[] = "✗ Failed to save 'Reserved!' status correctly";
                $errors[] = "  Saved as: " . $savedStatus['status'];
            }
        } else {
            $messages[] = "ℹ No rooms in database to test with";
        }
        
    } else {
        $errors[] = "✗ Rooms table does not exist!";
        $errors[] = "  Please add at least one room first";
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
    <title>Fix Reserved! Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ffa500 0%, #ff6b35 100%);
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
            padding: 10px 15px;
            margin-bottom: 6px;
            border-radius: 6px;
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
            font-size: 14px;
            line-height: 1.6;
        }
        .error {
            padding: 10px 15px;
            margin-bottom: 6px;
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
            border-left: 4px solid #ffa500;
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
            margin-right: 10px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #ffa500 0%, #ff6b35 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .button:hover {
            transform: translateY(-2px);
        }
        .button.secondary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        strong {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Reserved! Status</h1>
        <p class="subtitle">Database update for Reserved! room status support</p>
        
        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message"><?php echo $msg; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="error"><?php echo htmlspecialchars($err); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (empty($errors)): ?>
            <div class="info">
                <h3>✅ Database is Ready for Reserved! Status!</h3>
                <p><strong>What was done:</strong></p>
                <p>• Verified/updated the status column to support any text value</p>
                <p>• Tested that "Reserved!" can be saved and retrieved correctly</p>
                <p>• Your database now fully supports the Reserved! status</p>
                
                <p><strong>How to use:</strong></p>
                <p>1. Go to Room List page</p>
                <p>2. Click "Edit" on any room or "Add Room"</p>
                <p>3. Select "Reserved!" from the Status dropdown</p>
                <p>4. Save the room</p>
                <p>5. The room will display with an orange "Reserved!" button</p>
            </div>
            
            <a href="Roomlist.html" class="button">Go to Room List</a>
            <a href="update_reserved_status.php" class="button secondary">View Status Report</a>
        <?php else: ?>
            <div class="info">
                <h3>⚠️ Action Required</h3>
                <p>Please check the errors above and ensure:</p>
                <p>• MySQL/MariaDB is running in XAMPP</p>
                <p>• The rooms table exists in your database</p>
                <p>• You have proper database permissions</p>
                <p>• At least one room has been added to the system</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
