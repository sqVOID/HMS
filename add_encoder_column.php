<?php
/**
 * Migration: Add 'encoder' column to bookings and reports tables.
 * Run once, then you can delete this file.
 */
require_once 'config.php';

$results = [];

// Add to bookings
try {
    $check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'encoder'");
    if ($check->rowCount() == 0) {
        $conn->exec("ALTER TABLE bookings ADD COLUMN encoder VARCHAR(255) NULL DEFAULT NULL");
        $results[] = "✅ 'encoder' column added to bookings table.";
    } else {
        $results[] = "ℹ️ 'encoder' column already exists in bookings table.";
    }
} catch (PDOException $e) {
    $results[] = "❌ bookings error: " . $e->getMessage();
}

// Add to reports
try {
    $check = $conn->query("SHOW COLUMNS FROM reports LIKE 'encoder'");
    if ($check->rowCount() == 0) {
        $conn->exec("ALTER TABLE reports ADD COLUMN encoder VARCHAR(255) NULL DEFAULT NULL");
        $results[] = "✅ 'encoder' column added to reports table.";
    } else {
        $results[] = "ℹ️ 'encoder' column already exists in reports table.";
    }
} catch (PDOException $e) {
    $results[] = "❌ reports error: " . $e->getMessage();
}

echo "<pre>" . implode("\n", $results) . "</pre>";
echo "<p>Migration complete. You can delete this file.</p>";
?>
