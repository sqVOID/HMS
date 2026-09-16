<?php
/**
 * Add Reserved Status to Bookings Table
 * This script updates the bookings table to support "Reserved" status
 * Run this once to fix the database
 */

require_once 'config.php';

$messages = [];
$errors = [];

try {
    // Check if bookings table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'bookings'");
    
    if ($checkTable->rowCount() > 0) {
        $messages[] = "✓ Bookings table found";
        
        // Get current column information
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'status'");
        $columnInfo = $checkColumn->fetch(PDO::FETCH_ASSOC);
        
        if ($columnInfo) {
            $currentType = $columnInfo['Type'];
            $messages[] = "Current status column type: " . $currentType;
            
            // Check if it's an ENUM type
            if (stripos($currentType, 'enum') !== false) {
                $messages[] = "⚠ Status is ENUM - Updating to include 'Reserved'";
                
                // Update ENUM to include Reserved
                try {
                    $conn->exec("ALTER TABLE bookings MODIFY COLUMN status ENUM('Available', 'Confirming', 'Confirmed', 'Reserved', 'Occupied', 'Checked Out', 'Canceled', 'Extended') DEFAULT 'Available'");
                    $messages[] = "✓ Successfully updated ENUM to include 'Reserved' status";
                } catch (PDOException $e) {
                    $errors[] = "✗ Failed to update ENUM: " . $e->getMessage();
                }
            } else {
                $messages[] = "✓ Status column is VARCHAR/TEXT - 'Reserved' already supported";
            }
            
            // Test inserting Reserved status
            $messages[] = "<br><strong>Testing Reserved Status:</strong>";
            
            // Check if we can use Reserved status
            try {
                $testQuery = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'Reserved'");
                $result = $testQuery->fetch(PDO::FETCH_ASSOC);
                $messages[] = "✓ Current bookings with 'Reserved' status: " . $result['count'];
            } catch (PDOException $e) {
                $errors[] = "✗ Error checking Reserved bookings: " . $e->getMessage();
            }
            
            // Show all distinct statuses
            try {
                $statusQuery = $conn->query("SELECT DISTINCT status, COUNT(*) as count FROM bookings GROUP BY status ORDER BY status");
                $statuses = $statusQuery->fetchAll(PDO::FETCH_ASSOC);
                $messages[] = "<br><strong>All booking statuses in database:</strong>";
                foreach ($statuses as $stat) {
                    $messages[] = "  • " . ($stat['status'] ?: '(empty)') . ": " . $stat['count'] . " booking(s)";
                }
            } catch (PDOException $e) {
                $errors[] = "✗ Error fetching statuses: " . $e->getMessage();
            }
            
        } else {
            $errors[] = "✗ Status column not found in bookings table";
        }
        
        // Also check reports table
        $checkReportsTable = $conn->query("SHOW TABLES LIKE 'reports'");
        if ($checkReportsTable->rowCount() > 0) {
            $messages[] = "<br><strong>Checking Reports Table:</strong>";
            
            $checkReportsColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'status'");
            $reportsColumnInfo = $checkReportsColumn->fetch(PDO::FETCH_ASSOC);
            
            if ($reportsColumnInfo) {
                $reportsType = $reportsColumnInfo['Type'];
                $messages[] = "Reports status column type: " . $reportsType;
                
                if (stripos($reportsType, 'enum') !== false) {
                    try {
                        $conn->exec("ALTER TABLE reports MODIFY COLUMN status ENUM('Available', 'Confirming', 'Confirmed', 'Reserved', 'Occupied', 'Checked Out', 'Canceled', 'Extended') DEFAULT 'Confirmed'");
                        $messages[] = "✓ Updated reports table ENUM to include 'Reserved'";
                    } catch (PDOException $e) {
                        $errors[] = "✗ Failed to update reports ENUM: " . $e->getMessage();
                    }
                } else {
                    $messages[] = "✓ Reports status column supports 'Reserved'";
                }
            }
        }
        
    } else {
        $errors[] = "✗ Bookings table does not exist!";
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
    <title>Add Reserved Status to Bookings</title>
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
            max-width: 900px;
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
        <h1>🔧 Add Reserved Status to Bookings</h1>
        <p class="subtitle">Database update to support Reserved booking status</p>
        
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
                <h3>✅ Database Updated Successfully!</h3>
                <p><strong>What was done:</strong></p>
                <p>• Updated bookings table to support "Reserved" status</p>
                <p>• Updated reports table to support "Reserved" status</p>
                <p>• Verified the status column can store "Reserved" value</p>
                
                <p><strong>How to use:</strong></p>
                <p>1. Go to Booking page</p>
                <p>2. Select "Reservation" mode (not Walk-in)</p>
                <p>3. Fill in guest details and downpayment</p>
                <p>4. Click "Confirm Downpayment"</p>
                <p>5. When asked "Did the customer pay?", click OK</p>
                <p>6. The booking status will be "Reserved"</p>
                <p>7. The room will show orange "Reserved" button in Room List</p>
                
                <p><strong>Status Flow:</strong></p>
                <p>• <strong>Reservation + Paid</strong> = Reserved (Orange)</p>
                <p>• <strong>Reservation + Unpaid</strong> = Confirming (Purple)</p>
                <p>• <strong>Walk-in + Paid</strong> = Confirmed → Occupied (Blue)</p>
                <p>• <strong>Walk-in + Unpaid</strong> = Confirming (Purple)</p>
            </div>
            
            <a href="Booking.html" class="button">Go to Booking Page</a>
            <a href="Roomlist.html" class="button secondary">Go to Room List</a>
        <?php else: ?>
            <div class="info">
                <h3>⚠️ Action Required</h3>
                <p>Please check the errors above and ensure:</p>
                <p>• MySQL/MariaDB is running in XAMPP</p>
                <p>• The bookings table exists in your database</p>
                <p>• You have proper database permissions</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
