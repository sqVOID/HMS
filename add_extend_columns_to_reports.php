<?php
require_once 'config.php';

$results = [];

$columns = [
    'extend_hours'   => "ALTER TABLE reports ADD COLUMN extend_hours INT DEFAULT 0",
    'extend_minutes' => "ALTER TABLE reports ADD COLUMN extend_minutes INT DEFAULT 0",
    'extend_price'   => "ALTER TABLE reports ADD COLUMN extend_price DECIMAL(10,2) DEFAULT 0.00",
];

foreach ($columns as $col => $sql) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM reports LIKE '$col'");
        if ($check->rowCount() === 0) {
            $conn->exec($sql);
            $results[] = "✅ Added column <b>$col</b> to reports table.";
        } else {
            $results[] = "ℹ️ Column <b>$col</b> already exists – skipped.";
        }
    } catch (PDOException $e) {
        $results[] = "❌ Error adding <b>$col</b>: " . htmlspecialchars($e->getMessage());
    }
}

// Also try to copy existing extend data from bookings into reports for checked-out records
try {
    $updated = $conn->exec("
        UPDATE reports r
        JOIN bookings b ON r.booking_id = b.booking_id
        SET
            r.extend_hours   = COALESCE(b.extend_hours, 0),
            r.extend_minutes = COALESCE(b.extend_minutes, 0),
            r.extend_price   = COALESCE(b.extend_price, 0)
        WHERE (r.extend_hours = 0 AND r.extend_minutes = 0)
          AND (b.extend_hours > 0 OR b.extend_minutes > 0)
    ");
    $results[] = "✅ Synced extend data from bookings → reports for <b>$updated</b> active booking(s).";
} catch (PDOException $e) {
    $results[] = "⚠️ Could not sync live bookings (they may already be checked out): " . htmlspecialchars($e->getMessage());
}

echo '<html><head><meta charset="UTF-8"><title>Extend Columns Migration</title></head><body>';
echo '<h2>Migration: Add extend columns to reports</h2>';
echo '<ul>';
foreach ($results as $r) {
    echo "<li>$r</li>";
}
echo '</ul>';
echo '<p><b>Done!</b> You can now delete this file.</p>';
echo '</body></html>';
?>
