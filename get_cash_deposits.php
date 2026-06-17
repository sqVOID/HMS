<?php
require_once 'config.php';
header('Content-Type: application/json');

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
    } catch (PDOException $e) {}

    if ($depositId > 0) {
        // Fetch single deposit by ID
        $stmt = $conn->prepare("
            SELECT * FROM cash_deposits 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $depositId]);
    } elseif ($shiftDate !== '') {
        $stmt = $conn->prepare("
            SELECT * FROM cash_deposits 
            WHERE shift_date = :shift_date 
            ORDER BY id DESC
        ");
        $stmt->execute([':shift_date' => $shiftDate]);
    } else {
        $stmt = $conn->prepare("
            SELECT * FROM cash_deposits 
            ORDER BY id DESC
        ");
        $stmt->execute();
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
