<?php
// Debug script to capture the actual error
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG CONFIRM BOOKING ===\n\n";

// Test 1: Check if files exist
echo "1. Checking required files:\n";
echo "   config.php exists: " . (file_exists('config.php') ? 'YES' : 'NO') . "\n";
echo "   report_helpers.php exists: " . (file_exists('report_helpers.php') ? 'YES' : 'NO') . "\n\n";

// Test 2: Try to include files and catch errors
echo "2. Including config.php:\n";
try {
    ob_start();
    require_once 'config.php';
    $output = ob_get_clean();
    if ($output) {
        echo "   WARNING: config.php produced output:\n";
        echo "   " . var_export($output, true) . "\n";
    } else {
        echo "   OK - No output\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

echo "3. Including report_helpers.php:\n";
try {
    ob_start();
    require_once 'report_helpers.php';
    $output = ob_get_clean();
    if ($output) {
        echo "   WARNING: report_helpers.php produced output:\n";
        echo "   " . var_export($output, true) . "\n";
    } else {
        echo "   OK - No output\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Check database connection
echo "4. Testing database connection:\n";
try {
    if (isset($conn)) {
        echo "   Connection established: YES\n";
        $stmt = $conn->query("SELECT COUNT(*) as cnt FROM bookings");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Bookings count: " . $result['cnt'] . "\n";
    } else {
        echo "   Connection established: NO\n";
    }
} catch (PDOException $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Check for BOM in files
echo "5. Checking for BOM (Byte Order Mark) in PHP files:\n";
$files = ['config.php', 'report_helpers.php', 'confirm_booking.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $bom = substr($content, 0, 3);
        if ($bom === "\xEF\xBB\xBF") {
            echo "   $file: HAS BOM (UTF-8 BOM detected!) ⚠️\n";
        } else {
            echo "   $file: No BOM\n";
        }
    }
}
echo "\n";

echo "=== END DEBUG ===\n";
?>
