<?php
require_once 'config.php';
require_once 'report_helpers.php';
require_once 'payment_history_helpers.php';
require_once 'payment_amount_calculator.php';



header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="booking_report_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');
// Output UTF-8 BOM to ensure proper encoding in Excel
echo "\xEF\xBB\xBF";

$selectedRangeKey = strtolower($_GET['range'] ?? 'today');
$customStart = $_GET['start_date'] ?? null;
$customEnd = $_GET['end_date'] ?? null;
$validRanges = ['today', 'last_week', 'last_month', 'custom'];
if (!in_array($selectedRangeKey, $validRanges, true)) {
    $selectedRangeKey = 'today';
}
if ($selectedRangeKey === 'custom' && (!$customStart || !$customEnd)) {
    $selectedRangeKey = 'today';
}

try {

    $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
    $hasReportsTable = $checkTable->rowCount() > 0;

    if (!$hasReportsTable) {
        echo "Reports table does not exist yet.";
        exit;
    }

    ensureReportFinancialColumns($conn);

    $filterRangeMeta = buildDateRange($selectedRangeKey, $customStart, $customEnd);
    $filterStart = $filterRangeMeta['start'];
    $filterEnd = $filterRangeMeta['end'];
    $isSingleDayRange = ($filterStart === $filterEnd);

    $checkInStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE status IN ('Confirming', 'Confirmed') AND DATE(check_in) BETWEEN :start AND :end");
    $checkInStmt->bindParam(':start', $filterStart);
    $checkInStmt->bindParam(':end', $filterEnd);
    $checkInStmt->execute();
    $checkInResult = $checkInStmt->fetch(PDO::FETCH_ASSOC);
    $checkInCount = $checkInResult['count'] ?? 0;

    $checkOutStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE status = 'Checked Out' AND DATE(checked_out_at) BETWEEN :start AND :end");
    $checkOutStmt->bindParam(':start', $filterStart);
    $checkOutStmt->bindParam(':end', $filterEnd);
    $checkOutStmt->execute();
    $checkOutResult = $checkOutStmt->fetch(PDO::FETCH_ASSOC);
    $checkOutCount = $checkOutResult['count'] ?? 0;

    // Canceled count - use canceled_at when available, otherwise fall back to check_in
    $canceledStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM reports 
        WHERE status = 'Canceled' 
          AND DATE(COALESCE(canceled_at, check_in)) BETWEEN :start AND :end
    ");
    $canceledStmt->bindParam(':start', $filterStart);
    $canceledStmt->bindParam(':end', $filterEnd);
    $canceledStmt->execute();
    $canceledResult = $canceledStmt->fetch(PDO::FETCH_ASSOC);
    $canceledCount = $canceledResult['count'] ?? 0;

    $totalRoomsStmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms");
    $totalRoomsStmt->execute();
    $totalRoomsResult = $totalRoomsStmt->fetch(PDO::FETCH_ASSOC);
    $totalRoomsCount = $totalRoomsResult['count'] ?? 0;

    $extendedCount = 0;

    // Fetches individual payment columns to accurately calculate totals even for split payments
    // Also fetching deposit columns to include them in the summary
    $paymentStatsStmt = $conn->prepare("
        SELECT 
            r.payment_status_cash, r.payment_status_g_cash, r.payment_status_maya,
            COALESCE(r.deposit_details, b.deposit_details) as deposit_details,
            COALESCE(r.deposit_gcash_ref, b.deposit_gcash_ref) as deposit_gcash_ref,
            COALESCE(r.deposit_maya_ref, b.deposit_maya_ref) as deposit_maya_ref,
            COALESCE(r.booking_type, b.booking_type) as booking_type,
            COALESCE(r.downpayment_cash, b.downpayment_cash, 0) as downpayment_cash,
            COALESCE(r.downpayment_gcash, b.downpayment_gcash, 0) as downpayment_gcash,
            COALESCE(r.downpayment_maya, b.downpayment_maya, 0) as downpayment_maya,
            COALESCE(r.downpayment_instapay, b.downpayment_instapay, 0) as downpayment_instapay,
            COALESCE(r.downpayment_online_banking, b.downpayment_online_banking, 0) as downpayment_online_banking,
            COALESCE(r.downpayment_airbnb, b.downpayment_airbnb, 0) as downpayment_airbnb
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id COLLATE utf8mb4_unicode_ci = b.booking_id COLLATE utf8mb4_unicode_ci
        WHERE r.status = 'Checked Out' 
        AND DATE(r.checked_out_at) BETWEEN :start AND :end
    ");
    $paymentStatsStmt->bindParam(':start', $filterStart);
    $paymentStatsStmt->bindParam(':end', $filterEnd);
    $paymentStatsStmt->execute();

    $cashCount = 0;
    $cashTotal = 0;
    $gcashCount = 0;
    $gcashTotal = 0;
    $mayaCount = 0;
    $mayaTotal = 0;

    while ($row = $paymentStatsStmt->fetch(PDO::FETCH_ASSOC)) {
        // --- PROCESS CHECKOUT PAYMENTS ---
        // Cash
        if (!empty($row['payment_status_cash'])) {
            $amt = 0;
            if (preg_match('/(?:P|₱)?([0-9,.]+)/', $row['payment_status_cash'], $m)) {
                $amt = floatval(str_replace(',', '', $m[1]));
            }
            if ($amt > 0) {
                $cashCount++;
                $cashTotal += $amt;
            }
        }
        // G-Cash
        if (!empty($row['payment_status_g_cash'])) {
            $amt = 0;
            if (preg_match('/(?:P|₱)?([0-9,.]+)/', $row['payment_status_g_cash'], $m)) {
                $amt = floatval(str_replace(',', '', $m[1]));
            }
            if ($amt > 0) {
                $gcashCount++;
                $gcashTotal += $amt;
            }
        }
        // Maya
        if (!empty($row['payment_status_maya'])) {
            $amt = 0;
            if (preg_match('/(?:P|₱)?([0-9,.]+)/', $row['payment_status_maya'], $m)) {
                $amt = floatval(str_replace(',', '', $m[1]));
            }
            if ($amt > 0) {
                $mayaCount++;
                $mayaTotal += $amt;
            }
        }

        // Reservation downpayments are stored separately from checkout payment_status_* columns.
        if (strcasecmp(trim((string) ($row['booking_type'] ?? '')), 'Reservation') === 0) {
            $dpCash = floatval($row['downpayment_cash'] ?? 0);
            $dpG = floatval($row['downpayment_gcash'] ?? 0);
            $dpM = floatval($row['downpayment_maya'] ?? 0);
            $dpI = floatval($row['downpayment_instapay'] ?? 0);
            $dpOB = floatval($row['downpayment_online_banking'] ?? 0);
            $dpA = floatval($row['downpayment_airbnb'] ?? 0);
            if ($dpCash > 0) {
                $cashCount++;
                $cashTotal += $dpCash;
            }
            if ($dpG > 0) {
                $gcashCount++;
                $gcashTotal += $dpG;
            }
            if ($dpM > 0) {
                $mayaCount++;
                $mayaTotal += $dpM;
            }
        }
    }

    // Promo count - filtered by check_in date
    $promoStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE promo IS NOT NULL AND promo <> '' AND promo <> 'None' AND DATE(check_in) BETWEEN :start AND :end");
    $promoStmt->bindParam(':start', $filterStart);
    $promoStmt->bindParam(':end', $filterEnd);
    $promoStmt->execute();
    $promoResult = $promoStmt->fetch(PDO::FETCH_ASSOC);
    $promoCount = $promoResult['count'] ?? 0;

    // Guest Type counts - filtered by check_in date
    $guestTypeSoloStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE guest_type = 'Solo' AND DATE(check_in) BETWEEN :start AND :end");
    $guestTypeSoloStmt->bindParam(':start', $filterStart);
    $guestTypeSoloStmt->bindParam(':end', $filterEnd);
    $guestTypeSoloStmt->execute();
    $guestTypeSoloCount = $guestTypeSoloStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $guestTypeDuoStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE guest_type = 'Duo' AND DATE(check_in) BETWEEN :start AND :end");
    $guestTypeDuoStmt->bindParam(':start', $filterStart);
    $guestTypeDuoStmt->bindParam(':end', $filterEnd);
    $guestTypeDuoStmt->execute();
    $guestTypeDuoCount = $guestTypeDuoStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $guestTypeFamilyStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE guest_type = 'Family' AND DATE(check_in) BETWEEN :start AND :end");
    $guestTypeFamilyStmt->bindParam(':start', $filterStart);
    $guestTypeFamilyStmt->bindParam(':end', $filterEnd);
    $guestTypeFamilyStmt->execute();
    $guestTypeFamilyCount = $guestTypeFamilyStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $guestTypeGroupStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE guest_type = 'Group' AND DATE(check_in) BETWEEN :start AND :end");
    $guestTypeGroupStmt->bindParam(':start', $filterStart);
    $guestTypeGroupStmt->bindParam(':end', $filterEnd);
    $guestTypeGroupStmt->execute();
    $guestTypeGroupCount = $guestTypeGroupStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $guestTypeCompanyStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE guest_type = 'Company' AND DATE(check_in) BETWEEN :start AND :end");
    $guestTypeCompanyStmt->bindParam(':start', $filterStart);
    $guestTypeCompanyStmt->bindParam(':end', $filterEnd);
    $guestTypeCompanyStmt->execute();
    $guestTypeCompanyCount = $guestTypeCompanyStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Guest Type counts - filtered by check_in date
    $guestTypeSolo = 0;
    $guestTypeDuo = 0;
    $guestTypeFamily = 0;
    $guestTypeGroup = 0;
    $guestTypeCompany = 0;

    $guestTypeStmt = $conn->prepare("SELECT guest_type, COUNT(*) as count FROM reports WHERE DATE(check_in) BETWEEN :start AND :end GROUP BY guest_type");
    $guestTypeStmt->bindParam(':start', $filterStart);
    $guestTypeStmt->bindParam(':end', $filterEnd);
    $guestTypeStmt->execute();
    $guestTypeResults = $guestTypeStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($guestTypeResults as $row) {
        $type = strtolower($row['guest_type'] ?? '');
        $count = intval($row['count'] ?? 0);

        if ($type === 'solo') {
            $guestTypeSolo = $count;
        } elseif ($type === 'duo') {
            $guestTypeDuo = $count;
        } elseif ($type === 'family') {
            $guestTypeFamily = $count;
        } elseif ($type === 'group') {
            $guestTypeGroup = $count;
        } elseif ($type === 'company') {
            $guestTypeCompany = $count;
        }
    }

    $selectedRangeMeta = $filterRangeMeta;
    $selectedRangeMeta['key'] = $selectedRangeKey;
    if ($selectedRangeKey === 'custom') {
        $selectedRangeMeta['label'] = 'Custom Range';
    }

    $startDate = $filterStart;
    $endDate = $filterEnd;
    require_once __DIR__ . '/detailed_booking_report_logic.php';

    $nonRefundData = fetchNonRefundDownpayments($conn, $filterStart, $filterEnd);
    $nonRefundRevenueRecords = $nonRefundData['records'] ?? [];
    $totalNonRefundRevenue = floatval($nonRefundData['total'] ?? 0);
    $revenueOverviewTotal = $grandTotal + $totalNonRefundRevenue;

    // Output Excel content
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1" cellpadding="5" cellspacing="0">';

    // Title - Use current time with proper format
    $currentTime = date('d/m/Y H:i:s');
    $rangeLabel = $selectedRangeMeta['label'];
    if (!empty($selectedRangeMeta['start']) && !empty($selectedRangeMeta['end'])) {
        $rangeLabel .= ' (' . $selectedRangeMeta['start'] . ' - ' . $selectedRangeMeta['end'] . ')';
    }
    echo '<tr><td colspan="2" style="background-color: #4CAF50; color: white; font-weight: bold; font-size: 16px; text-align: center; padding: 10px;">Hotel Management System - Booking Report</td></tr>';
    echo '<tr><td colspan="2" style="text-align: center; padding: 5px;">Generated on: ' . $currentTime . '</td></tr>';
    echo '<tr><td colspan="2" style="text-align: center; padding: 5px; font-weight: bold; color: #256d27;">Date Range: ' . htmlspecialchars($rangeLabel) . '</td></tr>';
    echo '<tr><td colspan="2"></td></tr>';

    // Statistics Section
    echo '<tr><td colspan="2" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Booking Statistics</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px; width: 200px;">Check-In</td><td style="padding: 8px;">' . $checkInCount . '</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Check-Out</td><td style="padding: 8px;">' . $checkOutCount . '</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Canceled</td><td style="padding: 8px;">' . $canceledCount . '</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Promo</td><td style="padding: 8px;">' . $promoCount . '</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Total Rooms</td><td style="padding: 8px;">' . $totalRoomsCount . '</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Extended Time</td><td style="padding: 8px;">Coming Soon</td></tr>';
    echo '<tr><td colspan="2"></td></tr>';

    echo '<tr><td colspan="2" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Type of Guest</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Solo</td><td style="padding: 8px;">' . $guestTypeSoloCount . '</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Duo</td><td style="padding: 8px;">' . $guestTypeDuoCount . '</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Family</td><td style="padding: 8px;">' . $guestTypeFamilyCount . '</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Group</td><td style="padding: 8px;">' . $guestTypeGroupCount . '</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Company</td><td style="padding: 8px;">' . $guestTypeCompanyCount . '</td></tr>';
    echo '<tr><td colspan="2"></td></tr>';



    echo '<tr><td colspan="2" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Payment Methods</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Cash</td><td style="padding: 8px;">' . $cashCount . ' (' . number_format($cashTotal, 2) . ')</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">G-Cash</td><td style="padding: 8px;">' . $gcashCount . ' (' . number_format($gcashTotal, 2) . ')</td></tr>';
    echo '<tr><td style="font-weight: bold; padding: 8px;">Maya</td><td style="padding: 8px;">' . $mayaCount . ' (' . number_format($mayaTotal, 2) . ')</td></tr>';
    echo '<tr><td colspan="2"></td></tr>';

    echo '<tr><td colspan="2" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Revenue Overview</td></tr>';
    $overviewLabel = $selectedRangeMeta['label'];
    if (!empty($selectedRangeMeta['start']) && !empty($selectedRangeMeta['end'])) {
        $overviewLabel .= ' (' . $selectedRangeMeta['start'] . ' - ' . $selectedRangeMeta['end'] . ')';
    }
    echo '<tr>';
    echo '<td style="font-weight: bold; padding: 8px;">' . htmlspecialchars($overviewLabel) . '</td>';
    echo '<td style="padding: 8px;">' . number_format($revenueOverviewTotal, 2) . '</td>';
    echo '</tr>';
    echo '<tr><td colspan="2"></td></tr>';

    require_once __DIR__ . '/detailed_booking_report_render.php';
    renderDetailedBookingReportTable(
        $dataRows,
        $grandTotal,
        $grandTotalAdditional,
        $startDate,
        $endDate,
        true
    );
    echo '<tr><td colspan="35"></td></tr>';

    // Booking Revenue Breakdown
    $rangeLabel = $selectedRangeMeta['label'];
    if (!empty($selectedRangeMeta['start']) && !empty($selectedRangeMeta['end'])) {
        $rangeLabel .= ' (' . $selectedRangeMeta['start'] . ' - ' . $selectedRangeMeta['end'] . ')';
    }
    echo '<tr><td colspan="14" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Booking Revenue Breakdown - ' . htmlspecialchars($rangeLabel) . '</td></tr>';
    echo '<tr style="background-color: #4CAF50; color: white;">';
    echo '<td style="padding: 8px; font-weight: bold;">Booking ID</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Guest</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Payment</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Reference No.</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Status</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Checked-Out At</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Payment Date</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Total Amount</td>';
    echo '<td colspan="4"></td>';
    echo '</tr>';
    $revenueTdBase = 'padding: 5px; text-align: left; vertical-align: top;';
    $revenueTdText = $revenueTdBase . " mso-number-format:'@';";

    if (!empty($dataRows)) {
        foreach ($dataRows as $row) {
            echo '<tr>';
            echo '<td style="' . $revenueTdBase . '">' . htmlspecialchars($row['booking_id'] ?? '') . '</td>';
            echo '<td style="' . $revenueTdBase . '">' . htmlspecialchars($row['guest_name'] ?? '') . '</td>';
            echo '<td style="' . $revenueTdBase . '">' . htmlspecialchars($row['payment_method'] ?? '—') . '</td>';
            echo '<td style="' . $revenueTdBase . '">' . htmlspecialchars($row['reference_no'] ?? '—') . '</td>';
            echo '<td style="' . $revenueTdBase . '">' . htmlspecialchars($row['status'] ?? '—') . '</td>';
            echo '<td style="' . $revenueTdText . '">' . ($row['check_out'] ?? '—') . '</td>';
            echo '<td style="' . $revenueTdText . '">' . ($row['payment_date_time'] ?? '—') . '</td>';
            echo '<td style="' . $revenueTdBase . '">' . formatDetailedReportPaymentAmountDisplay(floatval($row['amount'] ?? 0)) . '</td>';
            echo '<td colspan="4"></td>';
            echo '</tr>';
        }
    }

    // Add Non-Refund Downpayments to Revenue Breakdown
    if (count($nonRefundRevenueRecords) > 0) {
        foreach ($nonRefundRevenueRecords as $record) {
            // Determine payment methods + references (match the helper output keys)
            $paymentMethodRaw = $record['payment_status'] ?? '—';
            $paymentMethodDisplay = htmlspecialchars($paymentMethodRaw);

            // Parse payment methods to check which ones were used
            $paymentMethods = array_map('trim', explode(',', $paymentMethodRaw));

            $refs = [];

            // Add references for each payment method that was actually used
            if (in_array('G-Cash', $paymentMethods) && !empty($record['reference_no_g_cash']) && $record['reference_no_g_cash'] !== 'NULL') {
                $refs[] = htmlspecialchars($record['reference_no_g_cash']);
            }
            if (in_array('Maya', $paymentMethods) && !empty($record['reference_no_maya']) && $record['reference_no_maya'] !== 'NULL') {
                $refs[] = htmlspecialchars($record['reference_no_maya']);
            }
            if (in_array('Instapay', $paymentMethods) && !empty($record['reference_no_instapay']) && $record['reference_no_instapay'] !== 'NULL') {
                $refs[] = htmlspecialchars($record['reference_no_instapay']);
            }
            if (in_array('Online Banking', $paymentMethods) && !empty($record['reference_no_online_banking']) && $record['reference_no_online_banking'] !== 'NULL') {
                $refs[] = htmlspecialchars($record['reference_no_online_banking']);
            }
            if (in_array('Airbnb', $paymentMethods) && !empty($record['reference_no_airbnb']) && $record['reference_no_airbnb'] !== 'NULL') {
                $refs[] = htmlspecialchars($record['reference_no_airbnb']);
            }

            $referenceDisplay = !empty($refs) ? implode(', ', $refs) : '—';

            $canceledDate = !empty($record['checked_out_at']) ? date('d/m/Y, g:i A', strtotime($record['checked_out_at'])) : '—';

            // Payment Date: use downpayment_date for Canceled Reservation rows
            $paymentDateDisplay = '—';
            if (!empty($record['downpayment_date'])) {
                try {
                    $dpDt = new DateTime($record['downpayment_date']);
                    $paymentDateDisplay = $dpDt->format('d/m/Y, g:i A');
                } catch (Exception $e) {
                    $paymentDateDisplay = htmlspecialchars($record['downpayment_date']);
                }
            }

            echo '<tr>';
            echo '<td style="padding:5px;">' . htmlspecialchars($record['booking_id'] ?? '') . '</td>';
            echo '<td style="padding:5px;">' . htmlspecialchars($record['guest_name'] ?? '') . '</td>';
            echo '<td style="padding:5px;">' . $paymentMethodDisplay . '</td>';
            echo '<td style="padding:5px;">' . $referenceDisplay . '</td>';
            echo '<td style="padding:5px;">Canceled Reservation</td>';
            echo '<td style="padding:5px;">—</td>';
            echo '<td style="padding:5px; mso-number-format:\'@\';">' . $paymentDateDisplay . '</td>';
            echo '<td style="padding:5px;">' . number_format(floatval($record['total_amount'] ?? 0), 2) . '</td>';
            echo '<td colspan="4"></td>';
            echo '</tr>';
        }
    }

    // Show "no records" message only if both checked-out and non-refund records are empty
    if (empty($dataRows) && count($nonRefundRevenueRecords) === 0) {
        echo '<tr><td colspan="14" style="padding:8px; text-align:center;">No revenue records in this range.</td></tr>';
    }

    // Grand Total for Booking Revenue Breakdown (including non-refund)
    $grandTotalRevenue = $grandTotal + $totalNonRefundRevenue;
    echo '<tr style="background-color: #e0e0e0; font-weight: bold;">';
    echo '<td colspan="7" style="padding: 8px; text-align: right;">Total Revenue:</td>';
    echo '<td style="padding: 8px;">' . number_format($grandTotalRevenue, 2) . '</td>';
    echo '<td colspan="4"></td>';
    echo '</tr>';

    echo '<tr><td colspan="14"></td></tr>';

    // Additional Fees Overview Section
    echo '<tr><td colspan="14" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Additional Fees Overview</td></tr>';

    // Check if bookings table exists and has additional fees columns
    $hasBookingsTable = false;
    try {
        $checkBookingsTable = $conn->query("SHOW TABLES LIKE 'bookings'");
        $hasBookingsTable = $checkBookingsTable->rowCount() > 0;
    } catch (PDOException $e) {
        $hasBookingsTable = false;
    }

    if ($hasBookingsTable) {
        // Ensure columns exist
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'missing_items_fees'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN missing_items_fees DECIMAL(10,2) DEFAULT 0");
            }
        } catch (PDOException $e) {
            // Column might already exist
        }

        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'missing_items_list'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN missing_items_list TEXT NULL DEFAULT NULL");
            }
        } catch (PDOException $e) {
            // Column might already exist
        }

        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'additional_fees_status'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN additional_fees_status VARCHAR(50) DEFAULT 'None'");
            }
        } catch (PDOException $e) {
            // Column might already exist
        }

        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'payment_status'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN payment_status VARCHAR(50) NULL DEFAULT NULL");
            }
        } catch (PDOException $e) {
            // Column might already exist
        }

        // Get additional fees records - filtered by date range (using reports table check_out date)
        // Get additional fees records - filtered by date range (using reports table check_out date)
        $additionalFeesStmt = $conn->prepare("
            SELECT r.booking_id, r.guest_name, r.room_id, r.payment_status, r.additional_fees_status,
                   r.missing_items_fees, r.missing_items_list, r.referral_name, r.supplier
            FROM reports r
            WHERE COALESCE(r.missing_items_fees, 0) > 0
            AND DATE(COALESCE(r.checked_out_at, r.check_out, r.check_in)) BETWEEN :start AND :end
            ORDER BY r.id DESC
        ");
        $additionalFeesStmt->bindParam(':start', $filterStart);
        $additionalFeesStmt->bindParam(':end', $filterEnd);
        $additionalFeesStmt->execute();
        $additionalFeesRecords = $additionalFeesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Additional Fees Table Headers
        echo '<tr style="background-color: #4CAF50; color: white;">';
        echo '<td style="padding: 8px; font-weight: bold;">Booking ID</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Guest</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Room</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Payment</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Missing Items</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Total Fee</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Status</td>';
        echo '<td colspan="4"></td>'; // Fill remaining columns
        echo '</tr>';

        // Additional Fees Table Rows
        if (count($additionalFeesRecords) > 0) {
            foreach ($additionalFeesRecords as $feeRecord) {
                $items = [];
                if (!empty($feeRecord['missing_items_list'])) {
                    $decoded = json_decode($feeRecord['missing_items_list'], true);
                    if (is_array($decoded)) {
                        $items = $decoded;
                    }
                }

                // Format missing items display
                $missingItemsDisplay = '—';
                if (!empty($items)) {
                    $itemStrings = [];
                    foreach ($items as $item) {
                        $name = $item['name'] ?? 'Item';
                        $price = isset($item['price']) ? number_format(floatval($item['price']), 2) : '0.00';
                        $itemStrings[] = $name;
                    }
                    $missingItemsDisplay = implode(', ', $itemStrings);
                } else if (floatval($feeRecord['missing_items_fees'] ?? 0) > 0) {
                    $missingItemsDisplay = 'Missing Items (' . number_format(floatval($feeRecord['missing_items_fees']), 2) . ')';
                }

                $totalFee = number_format(floatval($feeRecord['missing_items_fees'] ?? 0), 2);
                $status = $feeRecord['additional_fees_status'] ?? 'None';

                echo '<tr>';
                echo '<td style="padding: 5px;">' . htmlspecialchars($feeRecord['booking_id'] ?? '') . '</td>';
                echo '<td style="padding: 5px;">' . htmlspecialchars($feeRecord['guest_name'] ?? '') . '</td>';
                echo '<td style="padding: 5px;">' . htmlspecialchars($feeRecord['room_id'] ?? '') . '</td>';
                $cleanFeePayment = preg_replace('/\s*\([^)]*\)/', '', $feeRecord['payment_status'] ?? 'N/A');
                echo '<td style="padding: 5px;">' . htmlspecialchars($cleanFeePayment) . '</td>';
                echo '<td style="padding: 5px;">' . htmlspecialchars($missingItemsDisplay) . '</td>';
                echo '<td style="padding: 5px;">' . $totalFee . '</td>';
                echo '<td style="padding: 5px;">' . htmlspecialchars($status) . '</td>';
                echo '<td colspan="4"></td>'; // Fill remaining columns
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="14" style="padding: 8px; text-align: center;">No additional fees records found.</td></tr>';
        }
    } else {
        echo '<tr><td colspan="14" style="padding: 8px; text-align: center;">Bookings table does not exist.</td></tr>';
    }

    echo '<tr><td colspan="14"></td></tr>';

    // Supplier Report Section
    echo '<tr><td colspan="14" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Supplier Report</td></tr>';

    // Check if purchase_orders table exists
    $hasPurchaseOrdersTable = false;
    try {
        $checkPOTable = $conn->query("SHOW TABLES LIKE 'purchase_orders'");
        $hasPurchaseOrdersTable = $checkPOTable->rowCount() > 0;
    } catch (PDOException $e) {
        $hasPurchaseOrdersTable = false;
    }

    if ($hasPurchaseOrdersTable) {
        // Get purchase orders (suppliers) - filtered by date range
        $supplierStmt = $conn->prepare("
            SELECT po_number, requestor, po_date, description, items, total, status
            FROM purchase_orders
            WHERE DATE(po_date) BETWEEN :start AND :end
            ORDER BY id DESC
        ");
        $supplierStmt->bindParam(':start', $filterStart);
        $supplierStmt->bindParam(':end', $filterEnd);
        $supplierStmt->execute();
        $supplierRecords = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);

        // Supplier Table Headers
        echo '<tr style="background-color: #4CAF50; color: white;">';
        echo '<td style="padding: 8px; font-weight: bold;">PO Number</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Supplier</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Date</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Items</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Quantity</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Total</td>';
        echo '<td style="padding: 8px; font-weight: bold;">Status</td>';
        echo '<td colspan="2"></td>';
        echo '</tr>';

        if (count($supplierRecords) > 0) {
            foreach ($supplierRecords as $supplier) {
                $poDate = $supplier['po_date'] ? date('d/m/Y', strtotime($supplier['po_date'])) : '';
                $total = number_format(floatval($supplier['total'] ?? 0), 2);

                // Parse items JSON
                $itemsDisplay = '—';
                $quantityDisplay = '—';
                if (!empty($supplier['items'])) {
                    $items = json_decode($supplier['items'], true);
                    if (is_array($items) && count($items) > 0) {
                        $itemNames = [];
                        $itemQuantities = [];
                        foreach ($items as $item) {
                            $itemNames[] = htmlspecialchars($item['name'] ?? 'Item');
                            $itemQuantities[] = intval($item['quantity'] ?? 0);
                        }
                        $itemsDisplay = implode(', ', $itemNames);
                        $quantityDisplay = implode(', ', $itemQuantities);
                    }
                }

                echo '<tr>';
                echo '<td style="padding: 5px;">' . htmlspecialchars($supplier['po_number'] ?? '') . '</td>';
                echo '<td style="padding: 5px;">' . htmlspecialchars($supplier['requestor'] ?? '') . '</td>';
                echo '<td style="padding: 5px;">' . $poDate . '</td>';
                echo '<td style="padding: 5px;">' . $itemsDisplay . '</td>';
                echo '<td style="padding: 5px;">' . $quantityDisplay . '</td>';
                echo '<td style="padding: 5px;">' . $total . '</td>';
                echo '<td style="padding: 5px;">' . htmlspecialchars($supplier['status'] ?? 'Pending') . '</td>';
                echo '<td colspan="2"></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="14" style="padding: 8px; text-align: center;">No supplier records found.</td></tr>';
        }
    } else {
        echo '<tr><td colspan="14" style="padding: 8px; text-align: center;">Purchase orders table does not exist.</td></tr>';
    }

    echo '<tr><td colspan="13"></td></tr>';

    // Non-Refund Downpayments Section
    echo '<tr><td colspan="13" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Non-Refund Downpayments (Canceled Reservations)</td></tr>';

    // Use helper filtering because `payment_date_time` can be pipe-separated ("a|b|c"),
    // which MySQL DATE() cannot reliably parse.
    $nonRefundData = fetchNonRefundDownpayments($conn, $filterStart, $filterEnd);
    $nonRefundRecords = $nonRefundData['records'] ?? [];

    // Non-Refund Table Headers
    echo '<tr style="background-color: #4CAF50; color: white;">';
    echo '<td style="padding: 8px; font-weight: bold;">Booking ID</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Room ID</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Guest Name</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Guest Type</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Canceled Date</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Payment Date</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Downpayment Amount</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Payment Method</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Reference No</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Encoder</td>';
    echo '<td style="padding: 8px; font-weight: bold;">Modified</td>';
    echo '<td></td>';
    echo '</tr>';

    $totalNonRefund = 0;
    if (count($nonRefundRecords) > 0) {
        foreach ($nonRefundRecords as $record) {
            $canceledDate = !empty($record['checked_out_at']) ? date('d/m/Y, g:i A', strtotime($record['checked_out_at'])) : '—';
            $totalDownpayment = floatval($record['total_amount'] ?? 0);
            $totalNonRefund += $totalDownpayment;

            $paymentDateDisplay = '—';
            if (!empty($record['downpayment_date'])) {
                try {
                    $dpDt = new DateTime($record['downpayment_date']);
                    $paymentDateDisplay = $dpDt->format('d/m/Y, g:i A');
                } catch (Exception $e) {
                    $paymentDateDisplay = htmlspecialchars($record['downpayment_date']);
                }
            }

            $paymentMethodDisplay = htmlspecialchars($record['payment_status'] ?? '—');
            $refs = [];
            if (!empty($record['reference_no_g_cash']) && $record['reference_no_g_cash'] !== 'NULL') {
                $refs[] = htmlspecialchars($record['reference_no_g_cash']);
            }
            if (!empty($record['reference_no_maya']) && $record['reference_no_maya'] !== 'NULL') {
                $refs[] = htmlspecialchars($record['reference_no_maya']);
            }
            $referenceDisplay = !empty($refs) ? implode(', ', $refs) : '—';

            echo '<tr>';
            echo '<td style="padding: 5px;">' . htmlspecialchars($record['booking_id'] ?? '') . '</td>';
            echo '<td style="padding: 5px;">' . htmlspecialchars($record['room_id'] ?? '') . '</td>';
            echo '<td style="padding: 5px;">' . htmlspecialchars($record['guest_name'] ?? '') . '</td>';
            echo '<td style="padding: 5px;">' . htmlspecialchars($record['guest_type'] ?? '') . '</td>';
            echo '<td style="padding: 5px; mso-number-format:\'@\';">' . $canceledDate . '</td>';
            echo '<td style="padding: 5px; mso-number-format:\'@\';">' . $paymentDateDisplay . '</td>';
            echo '<td style="padding: 5px;">' . number_format($totalDownpayment, 2) . '</td>';
            echo '<td style="padding: 5px;">' . $paymentMethodDisplay . '</td>';
            echo '<td style="padding: 5px;">' . $referenceDisplay . '</td>';
            echo '<td style="padding: 5px;">' . htmlspecialchars($record['encoder'] ?? '') . '</td>';
            // Modified column - show "M" if modification_updated_at exists, blank otherwise
            $modifiedIndicator = (!empty($record['modification_updated_at']) && trim((string) $record['modification_updated_at']) !== '') ? 'M' : '';
            echo '<td style="padding: 5px; text-align: center;">' . $modifiedIndicator . '</td>';
            echo '<td></td>';
            echo '</tr>';
        }

        // Total row
        echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
        echo '<td colspan="6" style="padding: 8px; text-align: right;">Total Non-Refund Amount:</td>';
        echo '<td style="padding: 8px;">' . number_format($totalNonRefund, 2) . '</td>';
        echo '<td colspan="5"></td>';
        echo '</tr>';
    } else {
        echo '<tr><td colspan="13" style="padding: 8px; text-align: center;">No non-refund downpayments found.</td></tr>';
    }

    // ========== TURNOVER TABLE SECTION ==========
    echo '<tr><td colspan="14"></td></tr>';
    echo '<tr><td colspan="14"></td></tr>';
    echo '<tr><td colspan="14" style="background-color: #4CAF50; color: white; font-weight: bold; font-size: 14px; padding: 8px;">Cash Turnover Records  ' . '</td></tr>';

    // Check if turnover_records table exists
    $checkTurnoverTable = $conn->query("SHOW TABLES LIKE 'turnover_records'");
    $hasTurnoverTable = $checkTurnoverTable->rowCount() > 0;

    if ($hasTurnoverTable) {
        // Fetch turnover records for the selected date range
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
        $turnoverStmt->bindParam(':start', $filterStart);
        $turnoverStmt->bindParam(':end', $filterEnd);
        $turnoverStmt->execute();
        $turnoverRecords = $turnoverStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($turnoverRecords) > 0) {
            // Table headers
            echo '<tr style="background-color: #4CAF50; color: white;">';
            echo '<td style="padding: 8px; font-weight: bold;">Employee Name</td>';
            echo '<td style="padding: 8px; font-weight: bold;">Turnover Date</td>';
            echo '<td style="padding: 8px; font-weight: bold;">Turnover Time</td>';
            echo '<td style="padding: 8px; font-weight: bold;">Cash Amount</td>';
            echo '</tr>';

            // Table rows
            $totalCashTurnover = 0;

            foreach ($turnoverRecords as $turnover) {
                $employeeName = trim(($turnover['first_name'] ?? '') . ' ' . ($turnover['last_name'] ?? ''));
                if (empty($employeeName)) {
                    $employeeName = $turnover['username'] ?? 'N/A';
                }

                $turnoverDate = '—';
                $turnoverTime = '—';
                if (!empty($turnover['turnover_at'])) {
                    $dt = new DateTime($turnover['turnover_at']);
                    $turnoverDate = $dt->format('Y-m-d');
                    $turnoverTime = $dt->format('h:i A');
                }

                $cashAmt = floatval($turnover['cash_amount'] ?? 0);

                $totalCashTurnover += $cashAmt;

                echo '<tr>';
                echo '<td style="padding: 8px;">' . htmlspecialchars($employeeName) . '</td>';
                echo '<td style="padding: 8px;">' . htmlspecialchars($turnoverDate) . '</td>';
                echo '<td style="padding: 8px;">' . htmlspecialchars($turnoverTime) . '</td>';
                echo '<td style="padding: 8px;">₱' . number_format($cashAmt, 2) . '</td>';
                echo '</tr>';
            }

            // Total row
            echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
            echo '<td colspan="3" style="padding: 8px; text-align: right;">Total Turnover:</td>';
            echo '<td style="padding: 8px; color: #000000ff;">₱' . number_format($totalCashTurnover, 2) . '</td>';
            echo '</tr>';
        } else {
            echo '<tr><td colspan="4" style="padding: 8px; text-align: center;">No turnover records found for this date range.</td></tr>';
        }
    } else {
        echo '<tr><td colspan="4" style="padding: 8px; text-align: center;">Turnover records table does not exist.</td></tr>';
    }

    echo '</table>';
    echo '</body></html>';
} catch (PDOException $e) {
    echo 'Error generating report: ' . $e->getMessage();
}
?>
