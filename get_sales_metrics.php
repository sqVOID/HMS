<?php
// Prevent browser caching
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

    // Get date parameter for daily sales filter (optional)
    $selectedDate = $_GET['date'] ?? date('Y-m-d'); // Default to today
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        $selectedDate = date('Y-m-d');
    }

    // Create sales_metrics table if it doesn't exist (for Target only)
    $createTable = "CREATE TABLE IF NOT EXISTS sales_metrics (
        id INT PRIMARY KEY AUTO_INCREMENT,
        target DECIMAL(15,2) DEFAULT 0.00,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100)
    )";
    
    $conn->exec($createTable);

    // Calculate Running Sales (MTD) - Month-To-Date from 1st of current month to today
    $firstDayOfMonth = date('Y-m-01');
    $today = date('Y-m-d');
    
    // Use the same calculation logic as the export report
    $running_sales_mtd = calculateMTDSales($firstDayOfMonth, $today);

    // Calculate Avg Daily Sales up to the selected date
    $avg_daily_sales = calculateAvgDailySales($selectedDate);
    
    // For EMSO calculation, use the average from month start to selected date
    // Always use 31 days for EMSO calculation (standardized monthly projection)
    $emso = $avg_daily_sales * 31;

    // Calculate EMSO vs Last Month - Compare current EMSO with previous month's actual sales
    $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
    $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
    $lastMonthSales = calculateMTDSales($lastMonthStart, $lastMonthEnd);
    
    $emso_vs_last_month = 0;
    $emso_vs_last_month_color = '#6c757d';
    if ($lastMonthSales > 0 && $emso > 0) {
        $emso_vs_last_month = (($emso - $lastMonthSales) / $lastMonthSales) * 100;
        $emso_vs_last_month_color = $emso_vs_last_month >= 0 ? '#28a745' : '#dc3545';
    }

    // Get the Target (manual entry)
    $targetQuery = "SELECT target FROM sales_metrics ORDER BY id DESC LIMIT 1";
    $targetResult = $conn->query($targetQuery);
    
    if ($targetResult && $targetResult->rowCount() > 0) {
        $targetRow = $targetResult->fetch(PDO::FETCH_ASSOC);
        $target = $targetRow['target'];
    } else {
        // Insert default target if no record exists
        $insertQuery = "INSERT INTO sales_metrics (target, updated_by) VALUES (0.00, :username)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bindParam(':username', $_SESSION['username']);
        $stmt->execute();
        $target = 0;
    }

    // Calculate Benchmark - (Current Day of Month / Total Days in Month) × 100
    // This shows where we should be in the month timeline
    $current_day = (int)date('j'); // Day of month without leading zeros
    $total_days_in_month = (int)date('t'); // Total days in current month
    $benchmark = ($current_day / $total_days_in_month) * 100;
    
    // Color coding based on achievement vs benchmark
    $benchmark_color = '#3a3515'; // Default dark color
    
    // Optional: You can add color coding if achievement surpasses benchmark
    // if ($target > 0) {
    //     $achievement = ($running_sales_mtd / $target) * 100;
    //     if ($achievement >= $benchmark) {
    //         $benchmark_color = '#28a745'; // Green - on track or ahead
    //     } else {
    //         $benchmark_color = '#dc3545'; // Red - behind schedule
    //     }
    // }


    $data = [
        'running_sales_mtd' => number_format($running_sales_mtd, 2, '.', ''),
        'avg_daily_sales' => number_format($avg_daily_sales, 2, '.', ''),
        'selected_date' => $selectedDate,
        'emso' => number_format($emso, 2, '.', ''),
        'emso_vs_last_month' => number_format($emso_vs_last_month, 1, '.', ''),
        'emso_vs_last_month_color' => $emso_vs_last_month_color,
        'target' => number_format($target, 2, '.', ''),
        'benchmark' => number_format($benchmark, 1, '.', ''),
        'benchmark_color' => $benchmark_color,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
