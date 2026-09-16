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
               missing_items_fees, missing_items_list, penalty_amount, penalty_list,
               additional_fees_paid_date, check_out, check_in, created_at, 'Active' as source
        FROM bookings
        WHERE (COALESCE(missing_items_fees, 0) > 0 OR COALESCE(penalty_amount, 0) > 0)
          AND DATE(COALESCE(additional_fees_paid_date, check_out, check_in, created_at)) BETWEEN :start AND :end
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
                           missing_items_fees, missing_items_list, penalty_amount, penalty_list,
                           additional_fees_paid_date, checked_out_at as check_out, check_in, 'Checked Out' as source
                    FROM reports
                    WHERE (COALESCE(missing_items_fees, 0) > 0 OR COALESCE(penalty_amount, 0) > 0)
                      AND DATE(COALESCE(additional_fees_paid_date, checked_out_at, check_out, check_in)) BETWEEN :start AND :end
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
    
    // Combine both results (reports first so checked_out_at is prioritized)
    $combinedRecords = array_merge($reportsRecords, $activeRecords);
    $dedupedMap = [];
    
    foreach ($combinedRecords as $rec) {
        $bId = trim($rec['booking_id'] ?? '');
        if (empty($bId)) {
            $dedupedMap[] = $rec;
            continue;
        }
        
        if (!isset($dedupedMap[$bId])) {
            $dedupedMap[$bId] = $rec;
        } else {
            $existing = &$dedupedMap[$bId];
            
            // Prefer non-empty check_out date
            if (empty($existing['check_out']) && !empty($rec['check_out'])) {
                $existing['check_out'] = $rec['check_out'];
            }
            
            // Prefer status priority: Paid (3) > Pending (2) > None (1)
            $statusOrder = ['Paid' => 3, 'Pending' => 2, 'None' => 1];
            $existingStatusRank = $statusOrder[$existing['additional_fees_status'] ?? 'None'] ?? 0;
            $newStatusRank = $statusOrder[$rec['additional_fees_status'] ?? 'None'] ?? 0;
            if ($newStatusRank > $existingStatusRank) {
                $existing['additional_fees_status'] = $rec['additional_fees_status'];
            }
            
            // Prefer non-zero missing_items_fees
            if (floatval($existing['missing_items_fees'] ?? 0) == 0 && floatval($rec['missing_items_fees'] ?? 0) > 0) {
                $existing['missing_items_fees'] = $rec['missing_items_fees'];
            }
            
            // Prefer non-empty missing_items_list
            if ((empty($existing['missing_items_list']) || $existing['missing_items_list'] === '[]') && !empty($rec['missing_items_list']) && $rec['missing_items_list'] !== '[]') {
                $existing['missing_items_list'] = $rec['missing_items_list'];
            }
            
            // Prefer non-empty penalty_list
            if ((empty($existing['penalty_list']) || $existing['penalty_list'] === '[]') && !empty($rec['penalty_list']) && $rec['penalty_list'] !== '[]') {
                $existing['penalty_list'] = $rec['penalty_list'];
            }

            // Prefer non-zero penalty_amount
            if (floatval($existing['penalty_amount'] ?? 0) == 0 && floatval($rec['penalty_amount'] ?? 0) > 0) {
                $existing['penalty_amount'] = $rec['penalty_amount'];
            }

            // Prefer non-empty payment_status
            if (empty($existing['payment_status']) && !empty($rec['payment_status'])) {
                $existing['payment_status'] = $rec['payment_status'];
            }
            
            // Prefer non-empty guest_name / room_id
            if (empty($existing['guest_name']) && !empty($rec['guest_name'])) {
                $existing['guest_name'] = $rec['guest_name'];
            }
            if (empty($existing['room_id']) && !empty($rec['room_id'])) {
                $existing['room_id'] = $rec['room_id'];
            }
            
            // Prefer non-empty additional_fees_paid_date
            if (empty($existing['additional_fees_paid_date']) && !empty($rec['additional_fees_paid_date'])) {
                $existing['additional_fees_paid_date'] = $rec['additional_fees_paid_date'];
            }
            
            unset($existing);
        }
    }
    
    $records = array_values($dedupedMap);
    
    foreach ($records as &$record) {
        // Clean payment_status
        if (!empty($record['payment_status'])) {
             $record['payment_status'] = preg_replace('/\s*\([^)]*\)/', '', $record['payment_status']);
        }

        $penaltyItems = [];
        $penaltyItemDate = null;
        if (!empty($record['penalty_list']) && $record['penalty_list'] !== '[]') {
            $decodedPenalty = json_decode($record['penalty_list'], true);
            if (is_array($decodedPenalty)) {
                $penaltyItems = $decodedPenalty;
                foreach ($penaltyItems as &$pit) {
                    if (is_array($pit)) {
                        if (empty($pit['date'])) {
                            $pit['date'] = $record['additional_fees_paid_date'] ?? $record['check_out'] ?? date('Y-m-d H:i:s');
                        }
                        if (!$penaltyItemDate && !empty($pit['date'])) {
                            $penaltyItemDate = $pit['date'];
                        }
                    }
                }
                unset($pit);
            }
        }

        $items = [];
        $itemDate = null;
        if (!empty($record['missing_items_list'])) {
            $decoded = json_decode($record['missing_items_list'], true);
            if (is_array($decoded)) {
                $items = $decoded;
                foreach ($items as &$it) {
                    if (is_array($it)) {
                        if (empty($it['date'])) {
                            $it['date'] = $record['additional_fees_paid_date'] ?? $record['check_out'] ?? date('Y-m-d H:i:s');
                        }
                        if (!$itemDate && !empty($it['date'])) {
                            $itemDate = $it['date'];
                        }
                    }
                }
                unset($it);
            }
        }
        
        if (empty($items) && floatval($record['missing_items_fees'] ?? 0) > 0) {
            $itemDate = $record['additional_fees_paid_date'] ?? $record['check_out'] ?? date('Y-m-d H:i:s');
            $items = [[
                'name' => 'Missing Items',
                'price' => floatval($record['missing_items_fees'] ?? 0),
                'date' => $itemDate
            ]];
        }

        // Use penalty date as fallback effective date when no missing items
        $effectiveDate = $itemDate ?? $penaltyItemDate;
        
        if (empty($record['check_out']) && $effectiveDate) {
            $record['check_out'] = $effectiveDate;
        }
        
        $record['missing_items'] = $items;
        $record['missing_items_fees'] = floatval($record['missing_items_fees'] ?? 0);
        $record['penalty_items'] = $penaltyItems;
        $record['penalty_amount'] = floatval($record['penalty_amount'] ?? 0);
        $record['_effective_date'] = $effectiveDate;
    }
    unset($record);

    // Strict post-filter: Ensure record's effective fee date falls within filter start and end dates
    $records = array_values(array_filter($records, function($rec) use ($filterStart, $filterEnd) {
        $dateStr = $rec['_effective_date'] ?? null;
        if (!$dateStr) {
            // Try penalty items first, then missing items
            if (!empty($rec['penalty_items']) && is_array($rec['penalty_items']) && !empty($rec['penalty_items'][0]['date'])) {
                $dateStr = $rec['penalty_items'][0]['date'];
            } elseif (!empty($rec['missing_items']) && is_array($rec['missing_items']) && !empty($rec['missing_items'][0]['date'])) {
                $dateStr = $rec['missing_items'][0]['date'];
            }
        }
        if (!$dateStr) {
            $dateStr = $rec['additional_fees_paid_date'] ?? $rec['check_out'] ?? null;
        }
        if (!$dateStr) {
            return true;
        }
        $feeDay = date('Y-m-d', strtotime($dateStr));
        return ($feeDay >= $filterStart && $feeDay <= $filterEnd);
    }));

    // Sort by effective fee date descending
    usort($records, function($a, $b) {
        $dateAStr = $a['_effective_date'] ?? ($a['additional_fees_paid_date'] ?? $a['check_out']);
        $dateBStr = $b['_effective_date'] ?? ($b['additional_fees_paid_date'] ?? $b['check_out']);
        $timeA = $dateAStr ? strtotime($dateAStr) : 0;
        $timeB = $dateBStr ? strtotime($dateBStr) : 0;
        return $timeB - $timeA;
    });

    $response['records'] = $records;
    $response['success'] = true;
   
} catch(PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>

