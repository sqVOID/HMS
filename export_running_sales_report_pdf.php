<?php
require_once 'config.php';
require_once 'report_helpers.php';

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

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Running Sales Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 0px;
            padding-bottom: 20px;
        }

        .header h1 {
            color: #000000ff;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .header .info {
            color: #666;
            font-size: 13px;
            margin: 5px 0;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        .print-btn:hover {
            background: #45a049;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 12px;
        }

        th {
            background: #4CAF50;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #45a049;
        }

        td {
            padding: 8px;
            border: 1px solid #ddd;
            background: white;
        }

        tr:nth-child(even) td {
            background: #f9f9f9;
        }

        .text-right {
            text-align: right;
        }

        .total-row td {
            background: #e8f5e9 !important;
            font-weight: bold;
            border-top: 2px solid #4CAF50;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                padding: 10px;
            }

            .print-btn {
                display: none;
            }

            @page {
                size: landscape;
                margin: 0.5in;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Running Sales Report</h1>
            <div class="info" style="color: #000000ff; font-weight: bold;">
                <?php echo htmlspecialchars($startDate) . ' - ' . htmlspecialchars($endDate); ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Deposit ID</th>
                    <th>Shift Date</th>
                    <th>Shift Start</th>
                    <th>Shift End</th>
                    <th>Cash Expected</th>
                    <th>Cash Deposited</th>
                    <th>Total Variance</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Breakdown</th>
                    <th>Notes</th>
                    <th>Created By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deposits)): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 20px; color: #999;">
                            No deposits found for the selected date range.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($deposits as $deposit): ?>
                        <?php
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
                                    $breakdownDisplay = implode('<br>', $lines);
                                }
                            } catch (Exception $e) {
                                $breakdownDisplay = '-';
                            }
                        }
                        ?>
                        <tr>
                            <td>CD-<?php echo htmlspecialchars($deposit['id']); ?></td>
                            <td><?php echo date('m/d/Y', strtotime($deposit['shift_date'])); ?></td>
                            <td><?php echo formatShiftDateTime($deposit['shift_start']); ?></td>
                            <td><?php echo formatShiftDateTime($deposit['shift_end']); ?></td>
                            <td class="text-right">₱<?php echo number_format($deposit['cash_expected'], 2); ?></td>
                            <td class="text-right">₱<?php echo number_format($deposit['cash_deposited'], 2); ?></td>
                            <td class="text-right"><?php echo formatVarianceDisplay($deposit['variance']); ?></td>
                            <td><?php echo htmlspecialchars(strtoupper($deposit['status'])); ?></td>
                            <td><?php echo $reason ?: '-'; ?></td>
                            <td><?php echo $breakdownDisplay; ?></td>
                            <td><?php echo htmlspecialchars($deposit['notes'] ?? '') ?: '-'; ?></td>
                            <td><?php echo htmlspecialchars($deposit['created_by']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="4" style="text-align: right;">GRAND TOTAL:</td>
                        <td class="text-right">₱<?php echo number_format($totalExpected, 2); ?></td>
                        <td class="text-right">₱<?php echo number_format($totalDeposited, 2); ?></td>
                        <td class="text-right"><?php echo formatVarianceDisplay($totalVariance); ?></td>
                        <td colspan="5"></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>

</html>