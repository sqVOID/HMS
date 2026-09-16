<?php
// API endpoint for calculating MTD sales with custom date range
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header('Content-Type: application/json');

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Turn off error reporting to prevent notices from corrupting JSON
error_reporting(0);
ini_set('display_errors', 0);

try {
    // Check if user is authenticated
    if (!isset($_SESSION['username'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Not authenticated'
        ]);
        exit;
    }

    require_once 'config.php';
    require_once 'calculate_mtd_sales.php';

    // Get date parameters
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid date format. Use YYYY-MM-DD'
        ]);
        exit;
    }

    // Validate that start date is before or equal to end date
    if (strtotime($startDate) > strtotime($endDate)) {
        echo json_encode([
            'success' => false,
            'message' => 'Start date must be before or equal to end date'
        ]);
        exit;
    }

    // Calculate MTD sales for the date range
    $mtd_sales = calculateMTDSales($startDate, $endDate);

    echo json_encode([
        'success' => true,
        'mtd_sales' => number_format($mtd_sales, 2, '.', ''),
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
