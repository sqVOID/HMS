<?php
// Start output buffering
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Start session
session_start();

try {
    require_once 'config.php';

    // Ensure lightweight rebooked_flag helper column exists (used only for UI).
    // This avoids "Unknown column" errors when selecting it below.
    try {
        $checkCol = $conn->query("SHOW COLUMNS FROM bookings LIKE 'rebooked_flag'");
        if ($checkCol && $checkCol->rowCount() === 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN rebooked_flag TINYINT(1) NOT NULL DEFAULT 0");
        }
    } catch (\Throwable $e) {
        // Best-effort only; if this fails we still try to run the query below.
        error_log('Rebooked flag column check failed: ' . $e->getMessage());
    }

    // Filters
    $statusFilter = $_GET['status'] ?? 'all';
    $search       = trim($_GET['search'] ?? '');
    $date         = $_GET['date'] ?? null;

    // Build query — only pull Reservation booking_type rows from bookings
    $sql = "SELECT
                id,
                booking_id,
                room_id,
                room_type,
                guest_name,
                contact_no,
                guest_type,
                check_in,
                check_out,
                duration,
                duration_unit,
                status,
                reservation_date,
                downpayment_amount,
                downpayment_cash,
                downpayment_gcash,
                downpayment_maya,
                downpayment_gcash_ref,
                downpayment_maya_ref,
                downpayment_status,
                downpayment_date,
                total_amount_reservation,
                total_amount,
                paid_status,
                booking_type,
                room_image,
                extend_hours,
                extend_minutes,
                COALESCE(rebooked_flag, 0) AS rebooked_flag
            FROM bookings
            WHERE booking_type = 'Reservation'";

    $params = [];

    // Status filter
    if ($statusFilter !== 'all') {
        // Frontend now uses friendly labels: Normal, Done, Cancel, Rebooked
        // Map these labels to the underlying booking.status values.
        if (strcasecmp($statusFilter, 'Normal') === 0) {
            // Active reservations that are not canceled, not rebooked, and not checked out
            $sql .= " AND status NOT IN ('Canceled', 'Checked Out')
                      AND COALESCE(rebooked_flag, 0) = 0";
        } elseif (strcasecmp($statusFilter, 'Done') === 0) {
            // Completed reservations: only those fully checked out
            $sql .= " AND status = 'Checked Out'";
        } elseif (strcasecmp($statusFilter, 'Cancel') === 0 || strcasecmp($statusFilter, 'Canceled') === 0) {
            $sql .= " AND status = 'Canceled'";
        } elseif (strcasecmp($statusFilter, 'Rebooked') === 0) {
            // Rebooked rows are those with the helper flag set
            $sql .= " AND COALESCE(rebooked_flag, 0) = 1";
        } else {
            // Fallback: allow direct filtering by raw status if needed
            $sql .= " AND status = :status_filter";
            $params['status_filter'] = $statusFilter;
        }
    }

    // Search filter (booking_id or guest_name or room_id)
    if ($search !== '') {
        $sql .= " AND (booking_id LIKE :search1 OR guest_name LIKE :search2 OR room_id LIKE :search3)";
        $searchParam = '%' . $search . '%';
        $params['search1'] = $searchParam;
        $params['search2'] = $searchParam;
        $params['search3'] = $searchParam;
    }

    // Date filter (filter by reservation_date)
    if ($date) {
        $sql .= " AND DATE(reservation_date) = :date";
        $params['date'] = $date;
    }

    $sql .= " ORDER BY reservation_date DESC, id DESC";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare query');
    }

    $stmt->execute($params);

    $reservations = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $reservations[] = $row;
    }

    // Clear output buffer
    if (ob_get_length()) ob_clean();

    echo json_encode(['success' => true, 'data' => $reservations]);
    
    ob_end_flush();

} catch (PDOException $e) {
    // Clear output buffer
    if (ob_get_length()) ob_clean();
    
    error_log('Get reservations database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    
    ob_end_flush();
    
} catch (Exception $e) {
    // Clear output buffer
    if (ob_get_length()) ob_clean();
    
    error_log('Get reservations error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    
    ob_end_flush();
}
?>
