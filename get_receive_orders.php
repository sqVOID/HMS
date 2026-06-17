<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Ensure purchase_orders table exists with the latest columns
    $conn->exec("
        CREATE TABLE IF NOT EXISTS purchase_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            po_number VARCHAR(50) NOT NULL UNIQUE,
            requestor VARCHAR(255) NOT NULL,
            po_date DATE NOT NULL,
            description TEXT NOT NULL,
            address VARCHAR(255) DEFAULT NULL,
            items JSON NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            status VARCHAR(50) DEFAULT 'Pending',
            date_approved DATE DEFAULT NULL,
            received_status VARCHAR(50) DEFAULT 'Pending',
            date_received DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Force-add the columns in case the table pre-dates the new schema
    $columnChecks = [
        'received_status' => "ALTER TABLE purchase_orders ADD COLUMN received_status VARCHAR(50) DEFAULT 'Pending'",
        'date_received' => "ALTER TABLE purchase_orders ADD COLUMN date_received DATE DEFAULT NULL"
    ];

    foreach ($columnChecks as $columnName => $alterSql) {
        $columnNameEscaped = str_replace('`', '``', $columnName);
        $checkSql = "SHOW COLUMNS FROM purchase_orders LIKE '{$columnNameEscaped}'";
        $checkStmt = $conn->query($checkSql);
        if ($checkStmt->rowCount() === 0) {
            $conn->exec($alterSql);
        }
    }
    
    $startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
    $endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;

    $startDateObj = $startDate ? DateTime::createFromFormat('Y-m-d', $startDate) : false;
    $endDateObj = $endDate ? DateTime::createFromFormat('Y-m-d', $endDate) : false;
    $startDate = ($startDateObj && $startDateObj->format('Y-m-d') === $startDate) ? $startDate : null;
    $endDate = ($endDateObj && $endDateObj->format('Y-m-d') === $endDate) ? $endDate : null;

    $conditions = ["LOWER(TRIM(status)) = 'approved'"];
    $params = [];
    $pendingClause = "(received_status IS NULL OR received_status = '' OR LOWER(received_status) = 'pending')";

    // Filter by date_received (when orders were received) instead of po_date
    // For pending orders without date_received, show them regardless of date so they can be processed
    if ($startDate && $endDate) {
        // Show orders received in date range OR pending orders (so they remain actionable)
        if ($startDate === $endDate) {
            // Single date (like "Today") - show orders received on this date OR pending orders
            $conditions[] = "(date_received = :start_date OR {$pendingClause})";
            $params[':start_date'] = $startDate;
        } else {
            // Date range - show orders received in range OR pending orders
            $conditions[] = "(date_received BETWEEN :start_date AND :end_date OR {$pendingClause})";
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        }
    } elseif ($startDate) {
        // Show orders received from start date onwards OR pending orders
        $conditions[] = "(date_received >= :start_date OR {$pendingClause})";
        $params[':start_date'] = $startDate;
    } elseif ($endDate) {
        // Show orders received up to end date OR pending orders
        $conditions[] = "(date_received <= :end_date OR {$pendingClause})";
        $params[':end_date'] = $endDate;
    }

    $whereClause = implode(' AND ', $conditions);
    $stmt = $conn->prepare("SELECT * FROM purchase_orders WHERE $whereClause ORDER BY date_approved DESC, created_at DESC");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $receive_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode JSON items for each order
    foreach ($receive_orders as &$order) {
        if (!empty($order['items'])) {
            $decoded = json_decode($order['items'], true);
            if (is_array($decoded)) {
                $order['items'] = $decoded;
            } else {
                $order['items'] = [];
            }
        } else {
            $order['items'] = [];
        }
        
        // Decode received_items if available
        if (!empty($order['received_items'])) {
            $decodedReceived = json_decode($order['received_items'], true);
            if (is_array($decodedReceived)) {
                $order['received_items'] = $decodedReceived;
            } else {
                $order['received_items'] = [];
            }
        } else {
            $order['received_items'] = [];
        }
        
        // Set received_status if not set
        if (!isset($order['received_status']) || $order['received_status'] === '') {
            $order['received_status'] = 'Pending';
        }
    }
    
    echo json_encode(['success' => true, 'receive_orders' => $receive_orders]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

