<?php
require_once 'config.php';
require_once 'report_helpers.php';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="customer_details_report_' . date('Y-m-d') . '.xls"');
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
    $filterRangeMeta = buildDateRange($selectedRangeKey, $customStart, $customEnd);
    $filterStart = $filterRangeMeta['start'];
    $filterEnd = $filterRangeMeta['end'];

    $stmt = $conn->prepare("
        SELECT 
            r.booking_id,
            r.room_id,
            r.guest_name,
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
            r.duration,
            r.duration_unit,
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

    // First pass: determine displayed guest names for all records
    $displayNames = [];
    foreach ($records as $index => $row) {
        $guestType = $row['guest_type'] ?? '';
        if (strtolower(trim($guestType)) === 'company') {
            $displayNames[$index] = $row['contact_person_name'] ?? '—';
        } else {
            $displayNames[$index] = $row['guest_name'] ?? '—';
        }
    }
    
    // Second pass: count bookings per displayed guest name
    $guestBookingCounts = [];
    foreach ($displayNames as $index => $displayedName) {
        // Skip blank names
        if (empty(trim($displayedName)) || $displayedName === '—') {
            continue;
        }
        
        // Normalize the name: trim, remove extra spaces, lowercase for comparison
        $guestKey = strtolower(preg_replace('/\s+/', ' ', trim($displayedName)));
        if (!isset($guestBookingCounts[$guestKey])) {
            $guestBookingCounts[$guestKey] = 0;
        }
        $guestBookingCounts[$guestKey]++;
    }
    
    // Sort records: customers with more bookings first, then alphabetically
    usort($records, function($a, $b) use ($guestBookingCounts) {
        // Get displayed names for both records
        $guestTypeA = $a['guest_type'] ?? '';
        if (strtolower(trim($guestTypeA)) === 'company') {
            $displayNameA = $a['contact_person_name'] ?? '';
        } else {
            $displayNameA = $a['guest_name'] ?? '';
        }
        $guestKeyA = strtolower(preg_replace('/\s+/', ' ', trim($displayNameA)));
        
        $guestTypeB = $b['guest_type'] ?? '';
        if (strtolower(trim($guestTypeB)) === 'company') {
            $displayNameB = $b['contact_person_name'] ?? '';
        } else {
            $displayNameB = $b['guest_name'] ?? '';
        }
        $guestKeyB = strtolower(preg_replace('/\s+/', ' ', trim($displayNameB)));
        
        $countA = $guestBookingCounts[$guestKeyA] ?? 1;
        $countB = $guestBookingCounts[$guestKeyB] ?? 1;
        
        // First, sort by booking count (descending - more bookings first)
        if ($countA != $countB) {
            return $countB - $countA;
        }
        
        // Then sort alphabetically by display name
        return strcasecmp($displayNameA, $displayNameB);
    });
    
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
        if ($isPromo && intval($row['duration'] ?? 0) == 0) {
            $promoMeta = parsePromoSelection($promoStr);
            $promoHours = $promoMeta['hours'] ?? 0;
            if ($promoHours > 0) {
                $durationInfo = $promoHours . ' Hrs (Promo)';
            } elseif (!empty($row['hours'])) {
                $durationInfo = ucwords($row['hours']) . ' (Promo)';
            } else {
                $durationInfo = '—';
            }
        } else {
            $durationInfo = trim(($row['duration'] ?? '—') . ' ' . ($row['duration_unit'] ?? ''));
            if ($durationInfo === '') {
                $durationInfo = '—';
            }
        }
        $rawStatus = $row['status'] ?? '—';
        $displayStatus = ($rawStatus === 'Confirmed') ? 'Check in' : $rawStatus;
        $originalRoom = !empty($row['transfer_room_from']) ? $row['transfer_room_from'] : '—';
        
        // Determine company name and guest name display
        $companyName = '—';
        $displayGuestName = $row['guest_name'] ?? '—';
        $guestType = $row['guest_type'] ?? '';
        
        if (strtolower(trim($guestType)) === 'company') {
            // For company bookings
            $companyName = $row['guest_name'] ?? '—';
            $displayGuestName = $row['contact_person_name'] ?? '—';
        }
        
        // Get guest key based on displayed guest name only
        $guestKey = strtolower(preg_replace('/\s+/', ' ', trim($displayGuestName)));
        
        // Only show total bookings if guest name is not blank
        $hasGuestName = !empty(trim($displayGuestName)) && $displayGuestName !== '—';
        
        // Get total bookings for this guest (only if name exists)
        $totalBookings = $hasGuestName ? ($guestBookingCounts[$guestKey] ?? 1) : 1;
        
        // Check if this is the first occurrence of this guest
        $isFirstOccurrence = $hasGuestName && !isset($guestFirstOccurrence[$guestKey]);
        if ($isFirstOccurrence) {
            $guestFirstOccurrence[$guestKey] = true;
        }
        
        // Format extension duration
        $extensionDisplay = '—';
        $extendHours = intval($row['extend_hours'] ?? 0);
        $extendMinutes = intval($row['extend_minutes'] ?? 0);
        
        if ($extendHours > 0 || $extendMinutes > 0) {
            $extParts = [];
            if ($extendHours > 0) {
                $extParts[] = $extendHours . ' ' . ($extendHours == 1 ? 'Hour' : 'Hours');
            }
            if ($extendMinutes > 0) {
                $extParts[] = $extendMinutes . ' ' . ($extendMinutes == 1 ? 'Minute' : 'Minutes');
            }
            $extensionDisplay = implode(' ', $extParts);
        }

        echo '<tr>
            <td>' . htmlspecialchars($row['booking_id'] ?? '—') . '</td>
            <td>' . htmlspecialchars($displayGuestName) . '</td>
            <td>' . htmlspecialchars($companyName) . '</td>
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
