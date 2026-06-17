<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

echo "<h2>Columns in cancellation_requests:</h2><pre>";
$cols = $conn->query("SHOW COLUMNS FROM cancellation_requests");
while($c = $cols->fetch(PDO::FETCH_ASSOC)) echo $c['Field']." | ".$c['Type']."\n";
echo "</pre>";

echo "<h2>All rows in cancellation_requests:</h2><pre>";
$all = $conn->query("SELECT * FROM cancellation_requests ORDER BY id DESC LIMIT 20");
while($r = $all->fetch(PDO::FETCH_ASSOC)) print_r($r);
echo "</pre>";

echo "<h2>JOIN Query result:</h2><pre>";
$sql = "SELECT cr.id, cr.booking_id, cr.guest_name, cr.requested_by, cr.requested_at, 
        b.booking_id as booking_number, u.first_name
        FROM cancellation_requests cr
        LEFT JOIN bookings b ON cr.booking_id = b.id
        LEFT JOIN users u ON cr.requested_by COLLATE utf8mb4_unicode_ci = u.username
        WHERE cr.status = 'Pending'
        ORDER BY cr.requested_at DESC LIMIT 10";
try {
    $res = $conn->query($sql);
    echo "Rows found: ".$res->rowCount()."\n";
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) print_r($row);
} catch (PDOException $e) {
    echo "SQL ERROR: ".$e->getMessage();
}
echo "</pre>";
?>
