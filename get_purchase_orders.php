<?php
require_once 'config.php';
require_once 'report_helpers.php';

header('Content-Type: application/json');

try {
    // Ensure purchase_orders table exists
    try {
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch(PDOException $e) {
        // Table might already exist
    }

    // Frontend already sends explicit start_date/end_date for all ranges (today, last 7,
    // last 30, custom). Prefer those when present so the UI and backend stay in sync.
    $startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? trim($_GET['start_date']) : null;
    $endDate   = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? trim($_GET['end_date']) : null;

    // Fallback: if explicit dates are not provided, derive them from a range key.
    // This keeps the endpoint usable even if it's called without start/end params.
    if (!$startDate || !$endDate) {
        // Determine date range based on range key (today, last_week, last_month, custom)
        $rangeKey = isset($_GET['range']) ? strtolower(trim($_GET['range'])) : 'today';
        $customStart = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
        $customEnd = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;

        $validRanges = ['today', 'last_week', 'last_month', 'custom'];
        if (!in_array($rangeKey, $validRanges, true)) {
            $rangeKey = 'today';
        }

        if ($rangeKey === 'custom' && (!$customStart || !$customEnd)) {
            $rangeKey = 'today';
            $customStart = null;
            $customEnd = null;
        }

        $rangeMeta = buildDateRange($rangeKey, $customStart, $customEnd);
        $startDate = $startDate ?: ($rangeMeta['start'] ?? null);
        $endDate = $endDate ?: ($rangeMeta['end'] ?? null);
    }

    $conditions = [];
    $params = [];

    if ($startDate && $endDate) {
        $conditions[] = 'po_date BETWEEN :start_date AND :end_date';
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) . ' ' : '';
    $stmt = $conn->prepare("SELECT * FROM purchase_orders {$whereClause}ORDER BY created_at DESC");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $purchase_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON items for each PO
    foreach ($purchase_orders as &$po) {
        if (!empty($po['items'])) {
            $decoded = json_decode($po['items'], true);
            if (is_array($decoded)) {
                $po['items'] = $decoded;
            } else {
                $po['items'] = [];
            }
        } else {
            $po['items'] = [];
        }
    }

    echo json_encode(['success' => true, 'purchase_orders' => $purchase_orders]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

