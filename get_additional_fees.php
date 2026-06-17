<?php
require_once 'config.php';
require_once 'report_helpers.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'records' => []
];

// Get date range parameters
$selectedRangeKey = strtolower($_GET['range'] ?? 'today');
$customStart = $_GET['start_date'] ?? null;
$customEnd = $_GET['end_date'] ?? null;
$validRanges = ['today', 'last_week', 'last_month', 'custom'];
if (!in_array($selectedRangeKey, $validRanges, true)) {
    $selectedRangeKey = 'today';
}
if ($selectedRangeKey === 'custom' && (!$customStart || !$customEnd)) {
    $selectedRangeKey = 'today';
}

// Build date range
$filterRangeMeta = buildDateRange($selectedRangeKey, $customStart, $customEnd);
$filterStart = $filterRangeMeta['start'];
$filterEnd = $filterRangeMeta['end'];

try {
    ensureReportFinancialColumns($conn);
    // Ensure columns exist
    $columnsToEnsure = [
        'missing_items_fees' => "DECIMAL(10,2) DEFAULT 0",
        'missing_items_list' => "TEXT NULL DEFAULT NULL",
        'additional_fees_status' => "VARCHAR(50) DEFAULT 'None'",
        'payment_status' => "VARCHAR(50) NULL DEFAULT NULL",
        'supplier' => "VARCHAR(255) NULL DEFAULT NULL"
    ];
    
    foreach ($columnsToEnsure as $column => $definition) {
        try {
            $check = $conn->query("SHOW COLUMNS FROM bookings LIKE '$column'");
            if ($check->rowCount() === 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN $column $definition");
            }
        } catch (PDOException $e) {
            error_log("Failed to ensure column $column: " . $e->getMessage());
        }
    }
    
    // Query from both bookings (active) and reports (checked out) tables with date filtering
    // First get from active bookings
    $stmt1 = $conn->prepare("
        SELECT booking_id, guest_name, room_id, payment_status, additional_fees_status,
               missing_items_fees, missing_items_list, check_out, 'Active' as source
        FROM bookings
        WHERE COALESCE(missing_items_fees, 0) > 0
          AND DATE(check_out) BETWEEN :start AND :end
        ORDER BY check_out DESC
    ");
    $stmt1->bindParam(':start', $filterStart);
    $stmt1->bindParam(':end', $filterEnd);
    $stmt1->execute();
    $activeRecords = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    // Then get from reports (checked out bookings)
    $reportsRecords = [];
    try {
        // Check if reports table exists
        $checkReportsTable = $conn->query("SHOW TABLES LIKE 'reports'");
        if ($checkReportsTable->rowCount() > 0) {
            // Check if columns exist in reports table
            $hasColumns = true;
            $requiredColumns = ['missing_items_fees', 'missing_items_list', 'additional_fees_status'];
            foreach ($requiredColumns as $col) {
                $check = $conn->query("SHOW COLUMNS FROM reports LIKE '$col'");
                if ($check->rowCount() === 0) {
                    $hasColumns = false;
                    break;
                }
            }
            
            if ($hasColumns) {
                $stmt2 = $conn->prepare("
                    SELECT booking_id, guest_name, room_id, payment_status, additional_fees_status,
                           missing_items_fees, missing_items_list, checked_out_at as check_out, 'Checked Out' as source
                    FROM reports
                    WHERE COALESCE(missing_items_fees, 0) > 0
                      AND DATE(COALESCE(checked_out_at, check_out)) BETWEEN :start AND :end
                    ORDER BY checked_out_at DESC
                ");
                $stmt2->bindParam(':start', $filterStart);
                $stmt2->bindParam(':end', $filterEnd);
                $stmt2->execute();
                $reportsRecords = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        error_log("Failed to query reports table: " . $e->getMessage());
    }
    
    // Combine both results
    $records = array_merge($activeRecords, $reportsRecords);
    
    // Sort by check_out date descending
    usort($records, function($a, $b) {
        $dateA = $a['check_out'] ? strtotime($a['check_out']) : 0;
        $dateB = $b['check_out'] ? strtotime($b['check_out']) : 0;
        return $dateB - $dateA;
    });
    
    foreach ($records as &$record) {
        // Clean payment_status
        if (!empty($record['payment_status'])) {
             $record['payment_status'] = preg_replace('/\s*\([^)]*\)/', '', $record['payment_status']);
        }

        $items = [];
        if (!empty($record['missing_items_list'])) {
            $decoded = json_decode($record['missing_items_list'], true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }
        
        if (empty($items)) {
            $items = [[
                'name' => 'Missing Items',
                'price' => floatval($record['missing_items_fees'] ?? 0)
            ]];
        }
        
        $record['missing_items'] = $items;
        $record['missing_items_fees'] = floatval($record['missing_items_fees'] ?? 0);
    }
    
    $response['records'] = $records;
    $response['success'] = true;
   
} catch(PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>

