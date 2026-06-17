<?php
require_once 'config.php';
require_once 'report_helpers.php';

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

try {
    $stmt = $conn->prepare("
        SELECT 
            r.booking_id,
            r.room_id,
            r.guest_name,
            COALESCE(r.guest_type, b.guest_type) as guest_type,
            COALESCE(r.address, b.address) as address,
            COALESCE(r.contact_no, b.contact_no) as contact_no,
            COALESCE(r.contact_person_name, b.contact_person_name) as contact_person_name,
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
            COALESCE(b.room_type, r.room_type) as room_type,
            COALESCE(b.booking_type, r.booking_type) as booking_type,
            COALESCE(b.reason_for_stay, '') as reason_for_stay,
            COALESCE(b.request, '') as request,
            COALESCE(r.transfer_room_from, b.transfer_room_from, '') as transfer_room_from,
            GREATEST(COALESCE(r.discount_amount, 0), COALESCE(b.discount_amount, 0)) as discount_amount,
            COALESCE(r.promo, b.promo) as promo,
            COALESCE(r.hours, b.hours) as hours
        FROM reports r
        LEFT JOIN bookings b ON r.booking_id COLLATE utf8mb4_unicode_ci = b.booking_id COLLATE utf8mb4_unicode_ci
        WHERE DATE(COALESCE(r.check_in, r.reservation_date)) BETWEEN :start AND :end
        ORDER BY r.guest_name ASC, r.check_in DESC, r.id DESC
    ");
    $stmt->bindParam(':start', $startDate);
    $stmt->bindParam(':end', $endDate);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

// Function to format datetime to 12-hour format with date and time on separate lines
function formatDateTime($datetime) {
    if (empty($datetime) || $datetime === '—' || $datetime === '0000-00-00 00:00:00') {
        return '—';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '—';
    }
    return date('m/d/Y', $timestamp) . '<br>' . date('g:i A', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Details Report</title>
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
            max-width: 100%;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #afad4cff;
        }
        .header h1 {
            color: #222;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .header .info {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        th {
            background: #afad4cff;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #8d855aff;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
            background: white;
            vertical-align: top;
        }
        tr:nth-child(even) td {
            background: #f9f9f9;
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
            <h1>Customer Details Report</h1>
            <div class="info" style="color: #222; font-weight: bold;">
                Date Range: <?php echo htmlspecialchars($startDate) . ' to ' . htmlspecialchars($endDate); ?>
            </div>
            <div class="info">
                Generated on: <?php echo date('m/d/Y h:i a'); ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Guest Name</th>
                    <th>Company Name</th>
                    <th>Guest Type</th>
                    <th>Address</th>
                    <th>Contact No</th>

                    <th>TIN</th>
                    <th>Room</th>
                    <th>Original Room</th>
                    <th>Room Type</th>
                    <th>Booking Type</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Duration</th>
                    <th>Extend Duration</th>
                    <th>Reason for Stay</th>
                    <th>Request</th>
                    <th>Vehicle Details</th>
                    <th>Sales Channel</th>
                    <th>SC or PWD ID Number</th>
                    <th>Discount Amount</th>
                    <th>Amount</th>
                    <th>Total Bookings</th>
                </tr>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="23" style="text-align: center; padding: 20px; color: #999;">
                            No records found for the selected date range.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
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
                    
                    foreach ($records as $row): 
                        $vehicleInfo = trim(($row['vehicle_type'] ?? '') . ' ' . ($row['vehicle_description'] ?? '') . ' (' . ($row['plate_number'] ?? '') . ')');
                        if ($vehicleInfo === '()') {
                            $vehicleInfo = '—';
                        }
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
                        $originalRoom = !empty($row['transfer_room_from']) ? $row['transfer_room_from'] : '-';
                        
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
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['booking_id'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($displayGuestName); ?></td>
                            <td><?php echo htmlspecialchars($companyName); ?></td>
                            <td><?php echo htmlspecialchars($row['guest_type'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['address'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['contact_no'] ?? '—'); ?></td>

                            <td><?php echo htmlspecialchars($row['tin_number'] ?? '—'); ?></td>
                            <td>Room <?php echo htmlspecialchars($row['room_id'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($originalRoom); ?></td>
                            <td><?php echo htmlspecialchars($row['room_type'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['booking_type'] ?? '—'); ?></td>
                            <td><?php echo formatDateTime($row['check_in'] ?? '—'); ?></td>
                            <td><?php echo formatDateTime($row['check_out'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($durationInfo); ?></td>
                            <td><?php echo htmlspecialchars($extensionDisplay); ?></td>
                            <td><?php echo htmlspecialchars($row['reason_for_stay'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['request'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($vehicleInfo); ?></td>
                            <td><?php echo htmlspecialchars($row['sales_channel'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['id_number'] ?? '—'); ?></td>
                            <td><?php $disc = floatval($row['discount_amount'] ?? 0); echo $disc > 0 ? '₱' . number_format($disc, 2) : '—'; ?></td>
                            <td>₱<?php echo number_format($totalAmt, 2); ?></td>
                            <?php if ($isFirstOccurrence && $hasGuestName): ?>
                                <td rowspan="<?php echo $totalBookings; ?>" style="vertical-align: middle; text-align: center; font-weight: bold;"><?php echo $totalBookings; ?></td>
                            <?php elseif (!$hasGuestName): ?>
                                <td style="text-align: center;">—</td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
