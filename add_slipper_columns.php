<?php
require_once 'config.php';

$results = [];

// Add additional_slippers column (number of slippers given out)
$sql = "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS additional_slippers INT DEFAULT 0 AFTER additional_pet";
try {
    $conn->exec($sql);
    $results[] = "✅ additional_slippers column added (or already exists).";
} catch (PDOException $e) {
    $results[] = "❌ Error adding additional_slippers: " . $e->getMessage();
}

// Add slipper_status column: 'none' | 'added' | 'missing' | 'returned'
$sql = "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS slipper_status VARCHAR(20) DEFAULT 'none' AFTER additional_slippers";
try {
    $conn->exec($sql);
    $results[] = "✅ slipper_status column added (or already exists).";
} catch (PDOException $e) {
    $results[] = "❌ Error adding slipper_status: " . $e->getMessage();
}

// Also update reports table if it exists
try {
    $checkReports = $conn->query("SHOW TABLES LIKE 'reports'");
    if ($checkReports && $checkReports->rowCount() > 0) {
        $sql = "ALTER TABLE reports ADD COLUMN IF NOT EXISTS additional_slippers INT DEFAULT 0";
        try {
            $conn->exec($sql);
            $results[] = "✅ reports.additional_slippers column added (or already exists).";
        } catch (PDOException $e) {
            $results[] = "❌ Error adding reports.additional_slippers: " . $e->getMessage();
        }

        $sql = "ALTER TABLE reports ADD COLUMN IF NOT EXISTS slipper_status VARCHAR(20) DEFAULT 'none'";
        try {
            $conn->exec($sql);
            $results[] = "✅ reports.slipper_status column added (or already exists).";
        } catch (PDOException $e) {
            $results[] = "❌ Error adding reports.slipper_status: " . $e->getMessage();
        }
    }
} catch (PDOException $e) {
    $results[] = "❌ Error checking/updating reports table: " . $e->getMessage();
}

echo "<pre style='font-family:monospace; background:#111; color:#0f0; padding:20px; border-radius:8px;'>";
echo "=== Slipper Columns Migration ===\n\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\n✅ Migration complete.";
echo "</pre>";
?>
