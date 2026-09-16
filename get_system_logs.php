<?php
/**
 * get_system_logs.php
 * JSON API — returns paginated, filtered rows from system_logs.
 * Supports: type, category, date_from, date_to, search, page, per_page
 */
require_once 'config.php';
require_once 'auth.php';
require_once 'system_logger.php';

header('Content-Type: application/json; charset=utf-8');

// Only admin / super_admin may query logs
$lvl = getUserAccessLevel();
if (!in_array($lvl, ['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Ensure table exists
_ensureSystemLogsTable($conn);

// ── Input params ──────────────────────────────────────────────
$type      = trim($_GET['type']      ?? '');          // activity | error | warning | ''
$category  = trim($_GET['category']  ?? '');          // booking | cash | menu | ...
$date_from = trim($_GET['date_from'] ?? '');          // YYYY-MM-DD
$date_to   = trim($_GET['date_to']   ?? '');          // YYYY-MM-DD
$search    = trim($_GET['search']    ?? '');          // free text search
$page      = max(1, intval($_GET['page']     ?? 1));
$per_page  = min(200, max(10, intval($_GET['per_page'] ?? 50)));
$offset    = ($page - 1) * $per_page;

// ── Build WHERE ───────────────────────────────────────────────
$where  = [];
$params = [];

if ($type !== '' && in_array($type, ['activity', 'error', 'warning'])) {
    $where[]           = 'log_type = :type';
    $params[':type']   = $type;
}
if ($category !== '') {
    $where[]              = 'category = :category';
    $params[':category']  = strtolower($category);
}
if ($date_from !== '') {
    $where[]               = 'DATE(created_at) >= :date_from';
    $params[':date_from']  = $date_from;
}
if ($date_to !== '') {
    $where[]             = 'DATE(created_at) <= :date_to';
    $params[':date_to']  = $date_to;
}
if ($search !== '') {
    $where[]            = '(description LIKE :s1 OR action LIKE :s2 OR username LIKE :s3 OR affected_id LIKE :s4)';
    $like               = '%' . $search . '%';
    $params[':s1']      = $like;
    $params[':s2']      = $like;
    $params[':s3']      = $like;
    $params[':s4']      = $like;
}

$whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

try {
    // Total count
    $countSql  = "SELECT COUNT(*) FROM system_logs $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Rows
    $dataSql  = "SELECT * FROM system_logs $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $dataStmt = $conn->prepare($dataSql);
    foreach ($params as $k => $v) {
        $dataStmt->bindValue($k, $v);
    }
    $dataStmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode metadata JSON for each row
    foreach ($rows as &$row) {
        if (!empty($row['metadata'])) {
            $decoded = json_decode($row['metadata'], true);
            $row['metadata'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['metadata'] = [];
        }
    }
    unset($row);

    // Summary stats (always computed without date/search filters for cards)
    $statsStmt = $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(log_type = 'activity') AS activities,
            SUM(log_type = 'error')    AS errors,
            SUM(log_type = 'warning')  AS warnings,
            SUM(DATE(created_at) = CURDATE()) AS today
        FROM system_logs
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Category breakdown
    $catStmt = $conn->query("
        SELECT category, COUNT(*) AS cnt
        FROM system_logs
        GROUP BY category
        ORDER BY cnt DESC
    ");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'    => true,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $per_page,
        'total_pages'=> max(1, ceil($total / $per_page)),
        'rows'       => $rows,
        'stats'      => $stats,
        'categories' => $categories,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
