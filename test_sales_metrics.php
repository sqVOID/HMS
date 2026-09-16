<?php
session_start();

// Set a test session if needed
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'test_user';
}

require_once 'config.php';

echo "<h2>Sales Metrics Debug</h2>";

// Test 1: Check if reports table has data
echo "<h3>Test 1: Check Reports Table</h3>";
$checkQuery = "SELECT COUNT(*) as total_records FROM reports";
$result = $conn->query($checkQuery);
$row = $result->fetch(PDO::FETCH_ASSOC);
echo "Total records in reports table: " . $row['total_records'] . "<br>";

// Test 2: Check checked_out records
echo "<h3>Test 2: Checked Out Records</h3>";
$checkedOutQuery = "SELECT COUNT(*) as checked_out_count FROM reports WHERE status = 'checked_out'";
$result = $conn->query($checkedOutQuery);
$row = $result->fetch(PDO::FETCH_ASSOC);
echo "Total checked_out records: " . $row['checked_out_count'] . "<br>";

// Test 3: Check records with checked_out_at date
echo "<h3>Test 3: Records with checked_out_at Date</h3>";
$dateQuery = "SELECT COUNT(*) as date_count FROM reports WHERE checked_out_at IS NOT NULL";
$result = $conn->query($dateQuery);
$row = $result->fetch(PDO::FETCH_ASSOC);
echo "Records with checked_out_at date: " . $row['date_count'] . "<br>";

// Test 4: Sample checked_out_at dates
echo "<h3>Test 4: Sample checked_out_at Dates</h3>";
$sampleQuery = "SELECT booking_id, checked_out_at, total_amount, status FROM reports WHERE checked_out_at IS NOT NULL ORDER BY checked_out_at DESC LIMIT 10";
$result = $conn->query($sampleQuery);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Booking ID</th><th>Checked Out At</th><th>Total Amount</th><th>Status</th></tr>";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['booking_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['checked_out_at']) . "</td>";
    echo "<td>₱" . number_format($row['total_amount'], 2) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 5: Calculate Running Sales (MTD) - Last 30 days
echo "<h3>Test 5: Running Sales (Last 30 Days)</h3>";
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
$today = date('Y-m-d');
echo "Date range: $thirtyDaysAgo to $today<br>";

$mtdQuery = "SELECT 
                COUNT(*) as record_count,
                SUM(total_amount) as mtd_sales 
             FROM reports 
             WHERE DATE(checked_out_at) >= :thirty_days_ago 
             AND DATE(checked_out_at) <= :today
             AND status = 'checked_out'";
$stmt = $conn->prepare($mtdQuery);
$stmt->bindParam(':thirty_days_ago', $thirtyDaysAgo);
$stmt->bindParam(':today', $today);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Records found in last 30 days: " . $row['record_count'] . "<br>";
echo "Running Sales (MTD): ₱" . number_format($row['mtd_sales'] ?? 0, 2) . "<br>";

// Test 6: Try without status filter
echo "<h3>Test 6: Last 30 Days (Without Status Filter)</h3>";
$mtdQuery2 = "SELECT 
                COUNT(*) as record_count,
                SUM(total_amount) as mtd_sales 
             FROM reports 
             WHERE DATE(checked_out_at) >= :thirty_days_ago 
             AND DATE(checked_out_at) <= :today";
$stmt2 = $conn->prepare($mtdQuery2);
$stmt2->bindParam(':thirty_days_ago', $thirtyDaysAgo);
$stmt2->bindParam(':today', $today);
$stmt2->execute();
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "Records found (any status): " . $row2['record_count'] . "<br>";
echo "Total sales (any status): ₱" . number_format($row2['mtd_sales'] ?? 0, 2) . "<br>";

// Test 7: Check all statuses
echo "<h3>Test 7: All Status Types</h3>";
$statusQuery = "SELECT status, COUNT(*) as count FROM reports GROUP BY status";
$result = $conn->query($statusQuery);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>" . htmlspecialchars($row['status']) . "</td><td>" . $row['count'] . "</td></tr>";
}
echo "</table>";

// Test 8: Total sales ever
echo "<h3>Test 8: Total Sales (All Time)</h3>";
$totalQuery = "SELECT SUM(total_amount) as total_sales FROM reports WHERE total_amount > 0";
$result = $conn->query($totalQuery);
$row = $result->fetch(PDO::FETCH_ASSOC);
echo "Total sales (all time): ₱" . number_format($row['total_sales'] ?? 0, 2) . "<br>";

?>
