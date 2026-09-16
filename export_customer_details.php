<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'system_logger.php';
require_once 'report_helpers.php';
require_once __DIR__ . '/detailed_booking_report_functions.php';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="customer_details_report_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');
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

// Resolve who is exporting
$_exp_first = trim($_SESSION['first_name'] ?? '');
$_exp_last  = trim($_SESSION['last_name'] ?? '');
$_exp_user  = ($_exp_first !== '' || $_exp_last !== '') ? trim($_exp_first . ' ' . $_exp_last) : trim($_SESSION['username'] ?? 'Unknown');

logActivity($conn, 'report', 'REPORT_EXPORT',
    "Customer Details report exported (XLS) by {$_exp_user} for range: {$selectedRangeKey}" . ($selectedRangeKey === 'custom' ? " ({$customStart} to {$customEnd})" : ''),
    ['range' => $selectedRangeKey, 'start' => $customStart, 'end' => $customEnd, 'format' => 'XLS', 'exported_by' => $_exp_user]
);

try {
    $filterRangeMeta = buildDateRange($selectedRangeKey, $customStart, $customEnd);
    $filterStart = $filterRangeMeta['start'];
    $filterEnd = $filterRangeMeta['end'];

    $stmt = $conn->prepare("
        SELECT 
            r.booking_id,
            r.room_id,
            r.guest_name,
            r.second_guest_name,
            r.additional_guest_names,
            COALESCE(r.guest_type, b.guest_type) as guest_type,
            COALESCE(r.address, b.address) as address,
            COALESCE(r.contact_no, b.contact_no) as contact_no,
            COALESCE(r.contact_person_name, b.contact_person_name) as contact_person_name,
            COALESCE(b.email, '') as email,
            COALESCE(r.id_number, b.id_number) as id_number,
            COALESCE(b.tin_number, '') as tin_number,
            r.check_in,
            r.check_out,
            r.checked_out_at,
            COALESCE(r.duration, b.duration, 0) AS duration,
            COALESCE(r.duration_unit, b.duration_unit, 'hours') AS duration_unit,
            COALESCE(r.extend_hours, b.extend_hours) as extend_hours,
            COALESCE(r.extend_minutes, b.extend_minutes) as extend_minutes,
            COALESCE(r.extend_price, b.extend_price) as extend_price,
            COALESCE(r.extend_regular_rate, b.extend_regular_rate) as extend_regular_rate,
            COALESCE(r.extend_bundle_rate, b.extend_bundle_rate) as extend_bundle_rate,
            COALESCE(b.status, r.status) as status,
            GREATEST(COALESCE(r.total_amount, 0), COALESCE(b.total_amount, 0)) as total_amount,
            r.deposit_cash,
            r.deposit_g_cash,
            r.deposit_maya,
            r.deposit_instapay,
            r.deposit_online_banking,
            r.deposit_airbnb,
            r.downpayment_cash,
            r.downpayment_gcash,
            r.downpayment_maya,
            r.downpayment_instapay,
            r.downpayment_online_banking,
            r.downpayment_airbnb,
            COALESCE(r.vehicle_type, b.vehicle_type) as vehicle_type,
            COALESCE(r.vehicle_description, b.vehicle_description) as vehicle_description,
            COALESCE(r.plate_number, b.plate_number) as plate_number,
            COALESCE(r.sales_channel, b.sales_channel) as sales_channel,
            COALESCE(r.reason_for_stay, b.reason_for_stay) as reason_for_stay,
            COALESCE(r.request, b.request) as request,
            r.room_type,
            COALESCE(r.booking_type, b.booking_type) as booking_type,
            COALESCE(r.transfer_room_from, b.transfer_room_from, '') as transfer_room_from,
            GREATEST(COALESCE(r.discount_amount, 0), COALESCE(b.discount_amount, 0)) as discount_amount,
            COALESCE(r.promo, b.promo) as promo,
            COALESCE(r.hours, b.hours) as hours
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id COLLATE utf8mb4_unicode_ci = b.booking_id COLLATE utf8mb4_unicode_ci
        WHERE DATE(COALESCE(r.check_in, r.reservation_date)) BETWEEN :start AND :end
        ORDER BY r.guest_name ASC, r.check_in DESC, r.id DESC
    ");
    $stmt->bindParam(':start', $filterStart);
    $stmt->bindParam(':end', $filterEnd);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Consolidate guest names for all records
    foreach ($records as &$record) {
        $allGuestNames = [];
        if (!empty($record['guest_name'])) {
            $allGuestNames[] = trim($record['guest_name']);
        }
        if (!empty($record['second_guest_name'])) {
            $secondNames = array_filter(array_map('trim', explode('|', $record['second_guest_name'])));
            $allGuestNames = array_merge($allGuestNames, $secondNames);
        }
        if (!empty($record['additional_guest_names'])) {
            $additionalNames = array_filter(array_map('trim', explode('|', $record['additional_guest_names'])));
            $allGuestNames = array_merge($allGuestNames, $additionalNames);
        }
        $record['consolidated_guest_name'] = !empty($allGuestNames) ? implode("\n", $allGuestNames) : '';
    }
    unset($record);

    $durationMaps = loadReportRoomDurationMaps($conn);
    foreach ($records as &$recordRow) {
        attachReportRoomRates($recordRow, $durationMaps);
    }
    unset($recordRow);

    // Expand each booking into one row per guest name so repeated booking IDs
    // (with multiple guest names) are rendered as separate rows.
    $expandedRecords = [];
    foreach ($records as $record) {
        $guestTypeRaw = strtolower(trim((string)($record['guest_type'] ?? '')));

        if ($guestTypeRaw === 'company') {
            $companyGuestName = trim((string)($record['contact_person_name'] ?? ''));
            $copy = $record;
            $copy['display_guest_name'] = $companyGuestName;
            $expandedRecords[] = $copy;
            continue;
        }

        $guestNames = [];
        $appendName = function (string $name) use (&$guestNames): void {
            $name = trim($name);
            if ($name === '' || $name === '-' || $name === '—') {
                return;
            }
            $key = strtolower(preg_replace('/\s+/', ' ', $name));
            if (!isset($guestNames[$key])) {
                $guestNames[$key] = $name;
            }
        };

        $appendName((string)($record['guest_name'] ?? ''));

        $secondRaw = (string)($record['second_guest_name'] ?? '');
        if ($secondRaw !== '') {
            foreach (explode('|', $secondRaw) as $namePart) {
                $appendName($namePart);
            }
        }

        $additionalRaw = (string)($record['additional_guest_names'] ?? '');
        if ($additionalRaw !== '') {
            foreach (explode('|', $additionalRaw) as $namePart) {
                $appendName($namePart);
            }
        }

        if (empty($guestNames) && !empty($record['consolidated_guest_name'])) {
            $parts = preg_split('/\r\n|\r|\n/', (string)$record['consolidated_guest_name']);
            if (is_array($parts)) {
                foreach ($parts as $namePart) {
                    $appendName((string)$namePart);
                }
            }
        }

        if (!empty($guestNames)) {
            foreach (array_values($guestNames) as $guestName) {
                $copy = $record;
                $copy['display_guest_name'] = $guestName;
                $expandedRecords[] = $copy;
            }
        } else {
            $copy = $record;
            $copy['display_guest_name'] = '';
            $expandedRecords[] = $copy;
        }
    }
    $records = $expandedRecords;

    $guestBookingCounts = [];
    $resolveGuestCountKey = function (array $row): string {
        $guestType = strtolower(trim((string)($row['guest_type'] ?? '')));
        $displayGuestName = trim((string)($row['display_guest_name'] ?? ''));

        if ($displayGuestName !== '') {
            return strtolower(preg_replace('/\s+/', ' ', $displayGuestName));
        }

        if ($guestType === 'company') {
            $name = (string)($row['contact_person_name'] ?? '');
        } else {
            $name = !empty($row['consolidated_guest_name'])
                ? (string)$row['consolidated_guest_name']
                : (string)($row['guest_name'] ?? '');
        }

        $name = trim($name);
        if ($name === '' || $name === '-' || $name === '—') {
            return '';
        }

        $parts = preg_split('/\r\n|\r|\n/', $name);
        $primaryName = '';
        if (is_array($parts)) {
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $primaryName = $part;
                    break;
                }
            }
        }
        if ($primaryName === '') {
            $primaryName = $name;
        }

        return strtolower(preg_replace('/\s+/', ' ', trim($primaryName)));
    };

    foreach ($records as $recordForCount) {
        $guestKeyForCount = $resolveGuestCountKey($recordForCount);
        if ($guestKeyForCount === '') {
            continue;
        }
        if (!isset($guestBookingCounts[$guestKeyForCount])) {
            $guestBookingCounts[$guestKeyForCount] = 0;
        }
        $guestBookingCounts[$guestKeyForCount]++;
    }

    usort($records, function ($a, $b) use ($resolveGuestCountKey, $guestBookingCounts) {
        $keyA = $resolveGuestCountKey($a);
        $keyB = $resolveGuestCountKey($b);

        if ($keyA === '' && $keyB !== '') return 1;
        if ($keyA !== '' && $keyB === '') return -1;

        $countA = $guestBookingCounts[$keyA] ?? 0;
        $countB = $guestBookingCounts[$keyB] ?? 0;
        if ($countA !== $countB) {
            return $countB <=> $countA;
        }

        if ($keyA !== $keyB) {
            return strcmp($keyA, $keyB);
        }

        $bookingIdA = (string)($a['booking_id'] ?? '');
        $bookingIdB = (string)($b['booking_id'] ?? '');
        return strcmp($bookingIdA, $bookingIdB);
    });

    // Function to format datetime to 12-hour format
    function formatDateTime($datetime) {
        if (empty($datetime) || $datetime === '—' || $datetime === '0000-00-00 00:00:00') {
            return '—';
        }
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '—';
        }
        return date('m/d/Y g:i A', $timestamp);
    }

    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1" cellpadding="5" cellspacing="0">';

    $currentTime = date('d/m/Y H:i:s');
    $rangeLabel = $filterRangeMeta['label'];
    if (!empty($filterRangeMeta['start']) && !empty($filterRangeMeta['end'])) {
        $rangeLabel .= ' (' . $filterRangeMeta['start'] . ' - ' . $filterRangeMeta['end'] . ')';
    }

    echo '<tr><td colspan="27" style="background-color: #afad4cff; color: white; font-weight: bold; font-size: 16px; text-align: center; padding: 10px;">Hotel Management System - Customer Details Report</td></tr>';
    echo '<tr><td colspan="27" style="text-align: center; padding: 5px;">Generated on: ' . $currentTime . '</td></tr>';
    echo '<tr><td colspan="27" style="text-align: center; padding: 5px; font-weight: bold; color: #8d855aff;">Date Range: ' . htmlspecialchars($rangeLabel) . '</td></tr>';
    echo '<tr><td colspan="27"></td></tr>';

    echo '<tr style="background-color: #FFF3C5; font-weight: bold;">
        <th style="padding: 10px;">Booking ID</th>
        <th style="padding: 10px;">Guest Name</th>
        <th style="padding: 10px;">Company Name</th>
        <th style="padding: 10px;">Guest Type</th>
        <th style="padding: 10px;">Booking Type</th>
        <th style="padding: 10px;">Room Type</th>
        <th style="padding: 10px;">Room Number</th>
        <th style="padding: 10px;">Original Room</th>
        <th style="padding: 10px;">Address</th>
        <th style="padding: 10px;">Contact No</th>
        <th style="padding: 10px;">Email</th>
        <th style="padding: 10px;">TIN Number</th>
        <th style="padding: 10px;">Check In</th>
        <th style="padding: 10px;">Check Out</th>
        <th style="padding: 10px;">Duration</th>
        <th style="padding: 10px;">Extend Duration</th>
        <th style="padding: 10px;">Sales Channel</th>
        <th style="padding: 10px;">Reason for Stay</th>
        <th style="padding: 10px;">Special Request</th>
        <th style="padding: 10px;">Vehicle Type</th>
        <th style="padding: 10px;">Plate Number</th>
        <th style="padding: 10px;">Vehicle Description</th>
        <th style="padding: 10px;">Status</th>
        <th style="padding: 10px;">SC or PWD ID Number</th>
        <th style="padding: 10px;">Discount Amount</th>
        <th style="padding: 10px;">Total Amount</th>
        <th style="padding: 10px;">Total Bookings</th>
    </tr>';

    // Track which guests we've already shown the rowspan for
    $guestFirstOccurrence = [];

    foreach ($records as $row) {
        $promoStr = $row['promo'] ?? '';
        $isPromo = !empty($promoStr) && !in_array(strtolower(trim($promoStr)), ['', 'none', 'regular', 'select bundle', 'select promo']);
        
        // Calculate actual amount paid (sum of all payment methods)
        $totalAmt = 0;
        $totalAmt += max(floatval($row['deposit_cash'] ?? 0), floatval($row['downpayment_cash'] ?? 0));
        $totalAmt += max(floatval($row['deposit_g_cash'] ?? 0), floatval($row['downpayment_gcash'] ?? 0));
        $totalAmt += max(floatval($row['deposit_maya'] ?? 0), floatval($row['downpayment_maya'] ?? 0));
        $totalAmt += max(floatval($row['deposit_instapay'] ?? 0), floatval($row['downpayment_instapay'] ?? 0));
        $totalAmt += max(floatval($row['deposit_online_banking'] ?? 0), floatval($row['downpayment_online_banking'] ?? 0));
        $totalAmt += max(floatval($row['deposit_airbnb'] ?? 0), floatval($row['downpayment_airbnb'] ?? 0));
        
        if ($totalAmt <= 0 && $isPromo) {
            $promoMeta = parsePromoSelection($promoStr);
            $totalAmt = $promoMeta['price'] ?? 0;
        }
        if (isCanceledBookingStatus($row['status'] ?? '')) {
            $totalAmt = 0;
        }

        $durationDisplays = buildCustomerDetailsDurationDisplays($row);
        $durationInfo = $durationDisplays['duration'];
        $extensionDisplay = $durationDisplays['extension_duration'];

        $rawStatus = $row['status'] ?? '—';
        $displayStatus = ($rawStatus === 'Confirmed') ? 'Check in' : $rawStatus;
        $originalRoom = !empty($row['transfer_room_from']) ? $row['transfer_room_from'] : '—';
        
        // Determine company name and guest name display
        $companyName = '—';
        $displayGuestName = !empty($row['display_guest_name'])
            ? $row['display_guest_name']
            : (!empty($row['consolidated_guest_name']) ? $row['consolidated_guest_name'] : ($row['guest_name'] ?? '—'));
        $guestType = $row['guest_type'] ?? '';
        
        if (strtolower(trim($guestType)) === 'company') {
            $companyName = !empty($row['consolidated_guest_name']) ? $row['consolidated_guest_name'] : ($row['guest_name'] ?? '—');
            $displayGuestName = $row['contact_person_name'] ?? '—';
        }
        
        $guestKey = $resolveGuestCountKey($row);
        
        // Only show total bookings if guest name is not blank
        $hasGuestName = !empty(trim($displayGuestName)) && $displayGuestName !== '—';
        
        // Get total bookings for this guest (only if name exists)
        $totalBookings = $hasGuestName ? ($guestBookingCounts[$guestKey] ?? 1) : 1;
        
        // Check if this is the first occurrence of this guest
        $isFirstOccurrence = $hasGuestName && !isset($guestFirstOccurrence[$guestKey]);
        if ($isFirstOccurrence) {
            $guestFirstOccurrence[$guestKey] = true;
        }

        echo '<tr>
            <td>' . htmlspecialchars($row['booking_id'] ?? '—') . '</td>
            <td>' . htmlspecialchars($displayGuestName) . '</td>
            <td>' . nl2br(htmlspecialchars($companyName)) . '</td>
            <td>' . htmlspecialchars($row['guest_type'] ?? '—') . '</td>
            <td>' . htmlspecialchars($row['booking_type'] ?? '—') . '</td>
            <td>' . htmlspecialchars($row['room_type'] ?? '—') . '</td>
            <td>Room ' . htmlspecialchars($row['room_id'] ?? '—') . '</td>
            <td>' . htmlspecialchars($originalRoom) . '</td>
            <td>' . htmlspecialchars($row['address'] ?? '—') . '</td>
            <td>' . htmlspecialchars($row['contact_no'] ?? '—') . '</td>
            <td>' . htmlspecialchars($row['email'] ?? '—') . '</td>
            <td>' . htmlspecialchars($row['tin_number'] ?? '—') . '</td>
            <td>' . htmlspecialchars(formatDateTime($row['check_in'] ?? '—')) . '</td>
            <td>' . htmlspecialchars(formatDateTime($row['check_out'] ?? '—')) . '</td>
            <td>' . htmlspecialchars($durationInfo) . '</td>
            <td>' . htmlspecialchars($extensionDisplay) . '</td>
            <td>' . htmlspecialchars($row['sales_channel'] ?? '—') . '</td>
            <td>' . htmlspecialchars($row['reason_for_stay'] ?? '—') . '</td>
            <td>' . htmlspecialchars($row['request'] ?? '—') . '</td>
            <td>' . htmlspecialchars($row['vehicle_type'] ?? '—') . '</td>
            <td>' . htmlspecialchars($row['plate_number'] ?? '—') . '</td>
            <td>' . htmlspecialchars($row['vehicle_description'] ?? '—') . '</td>
            <td>' . htmlspecialchars($displayStatus) . '</td>
            <td>' . htmlspecialchars($row['id_number'] ?? '—') . '</td>
            <td>' . ($row['discount_amount'] > 0 ? number_format($row['discount_amount'], 2) : '—') . '</td>
            <td>' . number_format($totalAmt, 2) . '</td>';
        
        // Only add Total Bookings cell if guest has a name
        if ($isFirstOccurrence && $hasGuestName) {
            echo '<td rowspan="' . $totalBookings . '" style="vertical-align: middle; text-align: center; font-weight: bold; background-color: #fffacd;">' . $totalBookings . '</td>';
        } elseif (!$hasGuestName) {
            echo '<td style="text-align: center;">—</td>';
        }
        
        echo '</tr>';
    }

    echo '</table></body></html>';

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>
