<?php
require_once 'config.php';
require_once 'report_helpers.php';
require_once 'detailed_booking_report_render.php';

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$isPdf = isset($_GET['pdf']) && $_GET['pdf'] === '1';

if (!$isPdf) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Detailed_Booking_Report_' . $startDate . '_to_' . $endDate . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
}

try {
    require __DIR__ . '/detailed_booking_report_logic.php';

    renderDetailedBookingReportTable(
        $dataRows,
        $grandTotal,
        $grandTotalAdditional,
        $startDate,
        $endDate,
        false,
        $isPdf
    );

    // ========== TURNOVER RECORDS SECTION ==========
    $checkTurnoverTable = $conn->query("SHOW TABLES LIKE 'turnover_records'");
    $hasTurnoverTable = $checkTurnoverTable->rowCount() > 0;

    if ($hasTurnoverTable) {
        $turnoverStmt = $conn->prepare("
            SELECT 
                t.id,
                t.session_id,
                t.username,
                t.cash_amount,
                t.total_amount,
                t.turnover_at,
                u.first_name,
                u.last_name
            FROM turnover_records t
            LEFT JOIN users u ON t.user_id = u.id
            WHERE DATE(t.turnover_at) BETWEEN :start AND :end
            ORDER BY t.turnover_at DESC
        ");
        $turnoverStmt->bindParam(':start', $startDate);
        $turnoverStmt->bindParam(':end', $endDate);
        $turnoverStmt->execute();
        $turnoverRecords = $turnoverStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($turnoverRecords) > 0) {
            $totalCashTurnover = 0;

            if (!$isPdf) {
                echo '<br>';
            }

            echo '<table border="1" cellpadding="5" cellspacing="0">';

            if (!$isPdf) {
                echo '<tr><td colspan="4" style="background-color: #4CAF50; color: white; font-weight: bold; font-size: 14px; text-align: center; padding: 10px;">Cash Turnover Records</td></tr>';
                echo '<tr style="background-color: #4CAF50; color: white;">';
            } else {
                echo '<thead>';
                echo '<tr><th colspan="4" class="section-title">Cash Turnover Records</th></tr>';
                echo '<tr>';
            }

            echo '<th style="padding: 8px; font-weight: bold; text-align: left; vertical-align: top;">Employee Name</th>';
            echo '<th style="padding: 8px; font-weight: bold; text-align: left; vertical-align: top;">Turn-over Date</th>';
            echo '<th style="padding: 8px; font-weight: bold; text-align: left; vertical-align: top;">Turn-over Time</th>';
            echo '<th style="padding: 8px; font-weight: bold; text-align: left; vertical-align: top;">Cash Amount</th>';
            echo '</tr>';

            if ($isPdf) {
                echo '</thead>';
            }

            echo '<tbody>';

            foreach ($turnoverRecords as $turnover) {
                $employeeName = trim(($turnover['first_name'] ?? '') . ' ' . ($turnover['last_name'] ?? ''));
                if (empty($employeeName)) {
                    $employeeName = $turnover['username'] ?? 'N/A';
                }

                $turnoverDate = '—';
                $turnoverTime = '—';
                if (!empty($turnover['turnover_at'])) {
                    $dt = new DateTime($turnover['turnover_at']);
                    $turnoverDate = $dt->format('d/m/Y');
                    $turnoverTime = $dt->format('h:i a');
                }

                $cashAmt = floatval($turnover['cash_amount'] ?? 0);
                $totalCashTurnover += $cashAmt;

                echo '<tr>';
                echo '<td style="padding: 5px;">' . htmlspecialchars($employeeName) . '</td>';
                echo '<td style="padding: 5px;">' . htmlspecialchars($turnoverDate) . '</td>';
                echo '<td style="padding: 5px;">' . htmlspecialchars($turnoverTime) . '</td>';
                echo '<td style="padding: 5px;">₱' . number_format($cashAmt, 2) . '</td>';
                echo '</tr>';
            }

            echo '<tr style="background-color: #e0e0e0; font-weight: bold;">';
            echo '<td colspan="3" style="padding: 8px; text-align: right;">Total Turnover:</td>';
            echo '<td style="padding: 8px;">₱' . number_format($totalCashTurnover, 2) . '</td>';
            echo '</tr>';

            echo '</tbody>';
            echo '</table>';
        }
    }

    echo '</body></html>';
} catch (PDOException $e) {
    echo 'Database error: ' . htmlspecialchars($e->getMessage());
    exit;
}
