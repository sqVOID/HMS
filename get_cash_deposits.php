<?php
require_once 'config.php';
header('Content-Type: application/json');

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$depositDateFrom = isset($_GET['deposit_date_from']) ? trim($_GET['deposit_date_from']) : '';
$depositDateTo = isset($_GET['deposit_date_to']) ? trim($_GET['deposit_date_to']) : '';
$shiftDate = isset($_GET['shift_date']) ? trim($_GET['shift_date']) : '';
$depositId = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    // Auto-create cash_deposits table if not exists
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS cash_deposits (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                shift_date     DATE NOT NULL,
                shift_start    DATETIME NOT NULL,
                shift_end      DATETIME NOT NULL,
                deposit_date   DATE NULL DEFAULT NULL,
                cash_expected  DECIMAL(12,2) NOT NULL,
                cash_deposited DECIMAL(12,2) NOT NULL,
                variance       DECIMAL(12,2) NOT NULL,
                status         ENUM('exact','short','over') NOT NULL,
                reason         TEXT NULL DEFAULT NULL,
                notes          TEXT NULL DEFAULT NULL,
                breakdown      TEXT NULL DEFAULT NULL,
                created_by     VARCHAR(255) NOT NULL DEFAULT 'Unknown',
                created_at     DATETIME NOT NULL,
                updated_at     DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Add breakdown column if it doesn't exist
        try {
            $conn->exec("ALTER TABLE cash_deposits ADD COLUMN breakdown TEXT NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // Column already exists, ignore
        }
        // Add deposit_date column if it doesn't exist
        try {
            $conn->exec("ALTER TABLE cash_deposits ADD COLUMN deposit_date DATE NULL DEFAULT NULL");
            $conn->exec("UPDATE cash_deposits SET deposit_date = shift_date WHERE deposit_date IS NULL");
        } catch (PDOException $e) {
            // Column already exists, ignore
        }
    } catch (PDOException $e) {}

    if ($depositId > 0) {
        // Fetch single deposit by ID
        $stmt = $conn->prepare("
            SELECT * FROM cash_deposits 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $depositId]);
    } else {
        // Build dynamic WHERE clause
        $whereConditions = [];
        $params = [];
        
        // Shift date filters
        if ($startDate !== '' && $endDate !== '') {
            $whereConditions[] = "shift_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        } elseif ($startDate !== '') {
            $whereConditions[] = "shift_date >= :start_date";
            $params[':start_date'] = $startDate;
        } elseif ($endDate !== '') {
            $whereConditions[] = "shift_date <= :end_date";
            $params[':end_date'] = $endDate;
        }
        
        // Deposit date filters
        if ($depositDateFrom !== '' && $depositDateTo !== '') {
            $whereConditions[] = "deposit_date BETWEEN :deposit_date_from AND :deposit_date_to";
            $params[':deposit_date_from'] = $depositDateFrom;
            $params[':deposit_date_to'] = $depositDateTo;
        } elseif ($depositDateFrom !== '') {
            $whereConditions[] = "deposit_date >= :deposit_date_from";
            $params[':deposit_date_from'] = $depositDateFrom;
        } elseif ($depositDateTo !== '') {
            $whereConditions[] = "deposit_date <= :deposit_date_to";
            $params[':deposit_date_to'] = $depositDateTo;
        }
        
        // Shift date specific filter
        if ($shiftDate !== '') {
            $whereConditions[] = "shift_date = :shift_date";
            $params[':shift_date'] = $shiftDate;
        }
        
        // Build final query
        $sql = "SELECT * FROM cash_deposits";
        if (count($whereConditions) > 0) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        $sql .= " ORDER BY id DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
    }
    
    $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format numeric values
    foreach ($deposits as &$d) {
        $d['id'] = (int)$d['id'];
        $d['cash_expected'] = floatval($d['cash_expected']);
        $d['cash_deposited'] = floatval($d['cash_deposited']);
        $d['variance'] = floatval($d['variance']);
    }
    
    echo json_encode([
        'success' => true,
        'deposits' => $deposits
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
