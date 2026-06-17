<?php
require_once 'config.php';
require_once 'report_helpers.php';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="running_sales_report_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');
// Output UTF-8 BOM to ensure proper encoding in Excel
echo "\xEF\xBB\xBF";

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Fetch cash deposits
$stmt = $conn->prepare("
    SELECT 
        id,
        shift_date,
        shift_start,
        shift_end,
        cash_expected,
        cash_deposited,
        variance,
        status,
        reason,
        notes,
        breakdown,
        created_by,
        created_at
    FROM cash_deposits
    WHERE shift_date BETWEEN :start_date AND :end_date
    ORDER BY shift_date ASC, shift_start ASC
");

$stmt->execute([
    ':start_date' => $startDate,
    ':end_date' => $endDate
]);

$deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalExpected = 0;
$totalDeposited = 0;
$totalVariance = 0;

foreach ($deposits as $deposit) {
    $totalExpected += $deposit['cash_expected'];
    $totalDeposited += $deposit['cash_deposited'];
    $totalVariance += $deposit['variance'];
}

$colCount = 12;
$currentTime = date('d/m/Y H:i:s');

function formatShiftDateTime($value)
{
    if (empty($value)) {
        return '-';
    }
    return date('m/d/Y g:i A', strtotime($value));
}

function formatVarianceDisplay($variance)
{
    $variance = floatval($variance);
    if ($variance > 0.01) {
        return '+₱' . number_format($variance, 2);
    }
    if ($variance < -0.01) {
        return '₱' . number_format(abs($variance), 2);
    }
    return '₱' . number_format(0, 2);
}

// Output Excel content
echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1" cellpadding="5" cellspacing="0">';

// Title
echo '<tr><td colspan="' . $colCount . '" style="background-color: #4CAF50; color: white; font-weight: bold; font-size: 16px; text-align: center; padding: 10px;">Running Sales Report</td></tr>';
echo '<tr><td colspan="' . $colCount . '" style="text-align: center; padding: 5px;">Generated on: ' . $currentTime . '</td></tr>';
echo '<tr><td colspan="' . $colCount . '" style="text-align: center; padding: 5px; font-weight: bold; color: #256d27;">Date Range: ' . htmlspecialchars($startDate) . ' to ' . htmlspecialchars($endDate) . '</td></tr>';
echo '<tr><td colspan="' . $colCount . '"></td></tr>';

// Table Header
echo '<tr style="background-color: #4CAF50; color: white; font-weight: bold;">';
echo '<th style="padding: 8px;">Deposit ID</th>';
echo '<th style="padding: 8px;">Shift Date</th>';
echo '<th style="padding: 8px;">Shift Start</th>';
echo '<th style="padding: 8px;">Shift End</th>';
echo '<th style="padding: 8px;">Cash Expected</th>';
echo '<th style="padding: 8px;">Cash Deposited</th>';
echo '<th style="padding: 8px;">Total Variance</th>';
echo '<th style="padding: 8px;">Status</th>';
echo '<th style="padding: 8px;">Reason</th>';
echo '<th style="padding: 8px;">Breakdown</th>';
echo '<th style="padding: 8px;">Notes</th>';
echo '<th style="padding: 8px;">Created By</th>';
echo '</tr>';

// Data Rows
if (empty($deposits)) {
    echo '<tr><td colspan="' . $colCount . '" style="text-align: center; padding: 20px; color: #999;">No deposits found for the selected date range.</td></tr>';
} else {
    foreach ($deposits as $deposit) {
        $reason = htmlspecialchars($deposit['reason'] ?? '');
        $breakdownDisplay = '-';
        if ($deposit['breakdown']) {
            try {
                $breakdownData = json_decode($deposit['breakdown'], true);
                if (is_array($breakdownData) && count($breakdownData) > 0) {
                    $descriptions = array_map(function ($item) {
                        return htmlspecialchars($item['description'] ?? '');
                    }, $breakdownData);
                    $reason = implode(', ', $descriptions);

                    $lines = array_map(function ($item) {
                        $amt = isset($item['amount']) ? '₱' . number_format(floatval($item['amount']), 2) : '';
                        $desc = htmlspecialchars($item['description'] ?? '');
                        return $amt ? $amt . ' - ' . $desc : $desc;
                    }, $breakdownData);
                    $breakdownDisplay = implode('&#10;', $lines);
                }
            } catch (Exception $e) {
                $breakdownDisplay = '-';
            }
        }

        echo '<tr>';
        echo '<td style="padding: 8px;">CD-' . htmlspecialchars($deposit['id']) . '</td>';
        echo '<td style="padding: 8px;">' . date('m/d/Y', strtotime($deposit['shift_date'])) . '</td>';
        echo '<td style="padding: 8px;">' . formatShiftDateTime($deposit['shift_start']) . '</td>';
        echo '<td style="padding: 8px;">' . formatShiftDateTime($deposit['shift_end']) . '</td>';
        echo '<td style="padding: 8px; text-align: right;">₱' . number_format($deposit['cash_expected'], 2) . '</td>';
        echo '<td style="padding: 8px; text-align: right;">₱' . number_format($deposit['cash_deposited'], 2) . '</td>';
        echo '<td style="padding: 8px; text-align: right;">' . formatVarianceDisplay($deposit['variance']) . '</td>';
        echo '<td style="padding: 8px;">' . htmlspecialchars(strtoupper($deposit['status'])) . '</td>';
        echo '<td style="padding: 8px;">' . ($reason ?: '-') . '</td>';
        echo '<td style="padding: 8px; white-space: pre-line;">' . $breakdownDisplay . '</td>';
        echo '<td style="padding: 8px;">' . (htmlspecialchars($deposit['notes'] ?? '') ?: '-') . '</td>';
        echo '<td style="padding: 8px;">' . htmlspecialchars($deposit['created_by']) . '</td>';
        echo '</tr>';
    }

    // Grand Total Row
    echo '<tr style="background-color: #e8f5e9; font-weight: bold;">';
    echo '<td colspan="4" style="padding: 10px; text-align: right;">Grand Total:</td>';
    echo '<td style="padding: 10px; text-align: right; font-size: 14px;">₱' . number_format($totalExpected, 2) . '</td>';
    echo '<td style="padding: 10px; text-align: right; font-size: 14px;">₱' . number_format($totalDeposited, 2) . '</td>';
    echo '<td style="padding: 10px; text-align: right; font-size: 14px;">' . formatVarianceDisplay($totalVariance) . '</td>';
    echo '<td colspan="5" style="padding: 10px;"></td>'; // Reason, Breakdown, Notes, Created By (4 cols) + Status (1) = 5
    echo '</tr>';
}

echo '</table>';
echo '</body></html>';
