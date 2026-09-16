<?php
require_once 'config.php';

$results = [];

$newDepositColumns = [
    'new_deposit' => "DECIMAL(10,2) DEFAULT 0",
    'new_deposit_cash' => "DECIMAL(10,2) DEFAULT 0",
    'new_deposit_g_cash' => "DECIMAL(10,2) DEFAULT 0",
    'new_deposit_maya' => "DECIMAL(10,2) DEFAULT 0",
    'new_deposit_instapay' => "DECIMAL(10,2) DEFAULT 0",
    'new_deposit_online_banking' => "DECIMAL(10,2) DEFAULT 0",
    'new_deposit_airbnb' => "DECIMAL(10,2) DEFAULT 0",
    'new_deposit_details' => "TEXT NULL DEFAULT NULL",
    'new_deposit_gcash_ref' => "VARCHAR(255) NULL DEFAULT NULL",
    'new_deposit_maya_ref' => "VARCHAR(255) NULL DEFAULT NULL",
    'new_deposit_instapay_ref' => "VARCHAR(255) NULL DEFAULT NULL",
    'new_deposit_online_banking_ref' => "VARCHAR(255) NULL DEFAULT NULL",
    'new_deposit_airbnb_ref' => "VARCHAR(255) NULL DEFAULT NULL",
    'forfeited_new_deposit_cash' => "DECIMAL(10,2) DEFAULT 0",
    'forfeited_new_deposit_g_cash' => "DECIMAL(10,2) DEFAULT 0",
    'forfeited_new_deposit_maya' => "DECIMAL(10,2) DEFAULT 0",
    'forfeited_new_deposit_instapay' => "DECIMAL(10,2) DEFAULT 0",
    'forfeited_new_deposit_online_banking' => "DECIMAL(10,2) DEFAULT 0",
    'forfeited_new_deposit_airbnb' => "DECIMAL(10,2) DEFAULT 0",
];

foreach ($newDepositColumns as $column => $definition) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM bookings LIKE '$column'");
        if ($check->rowCount() === 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN $column $definition");
            $results[] = "✅ bookings.$column added.";
        } else {
            $results[] = "ℹ️ bookings.$column already exists.";
        }
    } catch (PDOException $e) {
        $results[] = "❌ bookings.$column: " . $e->getMessage();
    }
}

try {
    $checkReports = $conn->query("SHOW TABLES LIKE 'reports'");
    if ($checkReports && $checkReports->rowCount() > 0) {
        foreach ($newDepositColumns as $column => $definition) {
            try {
                $check = $conn->query("SHOW COLUMNS FROM reports LIKE '$column'");
                if ($check->rowCount() === 0) {
                    $conn->exec("ALTER TABLE reports ADD COLUMN $column $definition");
                    $results[] = "✅ reports.$column added.";
                } else {
                    $results[] = "ℹ️ reports.$column already exists.";
                }
            } catch (PDOException $e) {
                $results[] = "❌ reports.$column: " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    $results[] = "❌ Error checking reports table: " . $e->getMessage();
}

echo "<pre style='font-family:monospace; background:#111; color:#0f0; padding:20px; border-radius:8px;'>";
echo "=== New Deposit Columns Migration ===\n\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\n✅ Migration complete.";
echo "</pre>";
?>
