<?php
// MTD sales uses the same revenue logic as Total Revenue in report_helpers.php
require_once 'config.php';
require_once 'report_helpers.php';

/**
 * Calculate revenue for a date range (matches Total Revenue on the dashboard).
 */
function calculateMTDSales($startDate, $endDate) {
    global $conn;

    $overview = fetchDetailedBookingRevenueOverview($conn, $startDate, $endDate);

    return floatval($overview['total'] ?? 0);
}

/**
 * Calculate average daily sales from month start to a specific date.
 */
function calculateAvgDailySales($endDate) {
    $dateObj = new DateTime($endDate);
    $firstDayOfMonth = $dateObj->format('Y-m-01');

    $totalSales = calculateMTDSales($firstDayOfMonth, $endDate);

    $start = new DateTime($firstDayOfMonth);
    $end = new DateTime($endDate);
    $daysDiff = $end->diff($start)->days + 1;

    return $daysDiff > 0 ? ($totalSales / $daysDiff) : 0;
}
?>
