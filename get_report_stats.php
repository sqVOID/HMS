<?php
// Prevent HTML error output - ensure JSON response even on errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Must be logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'config.php';
require_once 'report_helpers.php';

header('Content-Type: application/json');


$response = [
    'success' => false,
    'message' => '',
    'stats' => [
        'check_in' => 0,
        'check_out' => 0,
        'total_rooms' => 0,
        'canceled' => 0,
        'promo' => 0,
        'payment_methods' => [
            'cash' => 0,
            'gcash' => 0,
            'cash_total' => 0,
            'gcash_total' => 0
        ],
        'revenue' => [],
        'selected_range' => []
    ],
    'booking_amounts' => []
];

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
    // Check if reports & bookings tables exist
    $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
    $hasReportsTable = $checkTable->rowCount() > 0;

    $checkBookingsTable = $conn->query("SHOW TABLES LIKE 'bookings'");
    $hasBookingsTable = $checkBookingsTable->rowCount() > 0;

    if (!$hasReportsTable) {
        // Reporting requires the reports table; without it, we can't show historical stats.
        $response['success'] = true;
        $response['message'] = 'Reports table does not exist yet.';
        echo json_encode($response);
        exit;
    }

    ensureReportFinancialColumns($conn);
    
    // Build date range for filtering all stats
    $filterRangeMeta = buildDateRange($selectedRangeKey, $customStart, $customEnd);
    $filterStart = $filterRangeMeta['start'];
    $filterEnd = $filterRangeMeta['end'];
    
    // Count check-ins within the selected date range
    // Only count from bookings table (active bookings)
    // Reports table should only be used for checked-out/canceled bookings to avoid double counting
    $checkInCount = 0;
    
    if ($hasBookingsTable) {
        // Count from active bookings table only
        $checkInBookingsStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM bookings 
            WHERE status IN ('Confirming', 'Confirmed', 'Occupied')
              AND DATE(check_in) BETWEEN :start AND :end
        ");
        $checkInBookingsStmt->bindParam(':start', $filterStart);
        $checkInBookingsStmt->bindParam(':end', $filterEnd);
        $checkInBookingsStmt->execute();
        $checkInBookingsResult = $checkInBookingsStmt->fetch(PDO::FETCH_ASSOC);
        $checkInCount = intval($checkInBookingsResult['count'] ?? 0);
    }
    
    $response['stats']['check_in'] = $checkInCount;
    
    // Count Check-Out - from reports table (bookings are removed on checkout).
    // Filter by actual checkout date (checked_out_at), fallback to check_out if checked_out_at is null
    $checkOutStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM reports 
        WHERE status = 'Checked Out' 
          AND DATE(COALESCE(checked_out_at, check_out)) BETWEEN :start AND :end
    ");
    $checkOutStmt->bindParam(':start', $filterStart);
    $checkOutStmt->bindParam(':end', $filterEnd);
    $checkOutStmt->execute();
    $checkOutResult = $checkOutStmt->fetch(PDO::FETCH_ASSOC);
    $response['stats']['check_out'] = intval($checkOutResult['count'] ?? 0);
    
    // Count canceled bookings - from reports table (canceled bookings are stored there).
    // Use COALESCE(canceled_at, check_in) so we still count cancellations that never had a check‑in date.
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
    $response['stats']['canceled'] = intval($canceledResult['count'] ?? 0);
    
    // Count Total Rooms - ALL rooms including Out of Order
    $totalRoomsStmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms");
    $totalRoomsStmt->execute();
    $totalRoomsResult = $totalRoomsStmt->fetch(PDO::FETCH_ASSOC);
    $response['stats']['total_rooms'] = intval($totalRoomsResult['count'] ?? 0);

    // Count Out of Order rooms
    $outOfOrderRoomsStmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms WHERE status = 'Out of Order'");
    $outOfOrderRoomsStmt->execute();
    $outOfOrderRoomsResult = $outOfOrderRoomsStmt->fetch(PDO::FETCH_ASSOC);
    $response['stats']['out_of_order_rooms'] = intval($outOfOrderRoomsResult['count'] ?? 0);

    // Count Operational Rooms (excluding Out of Order) for occupancy calculation
    $operationalRoomsStmt = $conn->prepare("SELECT COUNT(*) as count FROM rooms WHERE status != 'Out of Order'");
    $operationalRoomsStmt->execute();
    $operationalRoomsResult = $operationalRoomsStmt->fetch(PDO::FETCH_ASSOC);
    $operationalRoomsCount = intval($operationalRoomsResult['count'] ?? 0);

    // Count Available rooms (rooms that are not currently occupied)
    // This is calculated as: Total operational rooms - Occupied rooms
    // This will be calculated after we get the occupied count

    // Calculate occupied rooms based on active bookings (checked in but not checked out yet)
    if ($hasBookingsTable) {
        // Use live bookings for current occupancy; include Confirming so upcoming same‑day stays are reflected.
        $occupiedRoomsStmt = $conn->prepare("
            SELECT COUNT(DISTINCT room_id) as count 
            FROM bookings 
            WHERE status IN ('Confirming', 'Confirmed', 'Occupied') 
              AND DATE(check_in) <= CURDATE() 
              AND DATE(check_out) >= CURDATE()
        ");
        $occupiedRoomsStmt->execute();
        $occupiedRoomsResult = $occupiedRoomsStmt->fetch(PDO::FETCH_ASSOC);
        $occupiedRooms = intval($occupiedRoomsResult['count'] ?? 0);
    } else {
        // Fallback to reports if bookings table is not available.
        $occupiedRoomsStmt = $conn->prepare("
            SELECT COUNT(DISTINCT room_id) as count 
            FROM reports 
            WHERE status IN ('Confirming', 'Confirmed', 'Occupied') 
              AND DATE(check_in) <= CURDATE() 
              AND DATE(check_out) >= CURDATE()
        ");
        $occupiedRoomsStmt->execute();
        $occupiedRoomsResult = $occupiedRoomsStmt->fetch(PDO::FETCH_ASSOC);
        $occupiedRooms = intval($occupiedRoomsResult['count'] ?? 0);
    }
    
    // Payment method counts (based on checked-out bookings from reports table, filtered by date)
    // Fetches individual payment columns including deposits and downpayments to accurately calculate totals
    // This matches the logic in export_daily_sales.php
    $paymentStatsStmt = $conn->prepare("
        SELECT 
            payment_status_cash, 
            payment_status_g_cash, 
            payment_status_maya,
            deposit_details,
            deposit_gcash_ref,
            deposit_maya_ref,
            downpayment_cash,
            downpayment_gcash,
            downpayment_maya
        FROM reports
        WHERE status = 'Checked Out' 
        AND DATE(check_in) BETWEEN :start AND :end
    ");
    $paymentStatsStmt->bindParam(':start', $filterStart);
    $paymentStatsStmt->bindParam(':end', $filterEnd);
    $paymentStatsStmt->execute();
    
    $cashCount = 0; $cashTotal = 0;
    $gcashCount = 0; $gcashTotal = 0;
    $mayaCount = 0; $mayaTotal = 0;
    
    while ($row = $paymentStatsStmt->fetch(PDO::FETCH_ASSOC)) {
        $hasCash = false;
        $hasGcash = false;
        $hasMaya = false;
        
        // === CHECKOUT PAYMENTS ===
        $checkoutCash = 0;
        $checkoutGcash = 0;
        $checkoutMaya = 0;
        
        // Cash - checkout payment
        if (!empty($row['payment_status_cash'])) {
            if (preg_match('/(?:P|₱)?([0-9,.]+)/', $row['payment_status_cash'], $m)) {
                $checkoutCash = floatval(str_replace(',', '', $m[1]));
            }
        }
        
        // G-Cash - checkout payment
        if (!empty($row['payment_status_g_cash'])) {
            if (preg_match('/(?:P|₱)?([0-9,.]+)/', $row['payment_status_g_cash'], $m)) {
                $checkoutGcash = floatval(str_replace(',', '', $m[1]));
            }
        }
        
        // Maya - checkout payment
        if (!empty($row['payment_status_maya'])) {
            if (preg_match('/(?:P|₱)?([0-9,.]+)/', $row['payment_status_maya'], $m)) {
                $checkoutMaya = floatval(str_replace(',', '', $m[1]));
            }
        }
        
        // === DEPOSIT (RESERVATION PAYMENT) ===
        $depositCash = 0;
        $depositGcash = 0;
        $depositMaya = 0;
        
        $depositDetails = $row['deposit_details'] ?? '';
        $depositAmt = 0.0;
        
        if (!empty($depositDetails)) {
            if (preg_match('/₱\s*([0-9,]+\.?[0-9]*)/', $depositDetails, $m)) {
                $depositAmt = floatval(str_replace(',', '', $m[1]));
            }
        }
        
        if ($depositAmt > 0) {
            $dGcash = $row['deposit_gcash_ref'] ?? null;
            $dMaya = $row['deposit_maya_ref'] ?? null;
            
            // Determine deposit payment method
            if (!empty($dGcash) && $dGcash !== 'NULL' && $dGcash !== '') {
                $depositGcash = $depositAmt;
            } elseif (!empty($dMaya) && $dMaya !== 'NULL' && $dMaya !== '') {
                $depositMaya = $depositAmt;
            } else {
                // Check deposit_details text
                if (stripos($depositDetails, 'G-Cash') !== false || stripos($depositDetails, 'Gcash') !== false) {
                    $depositGcash = $depositAmt;
                } elseif (stripos($depositDetails, 'Maya') !== false) {
                    $depositMaya = $depositAmt;
                } else {
                    // Default to cash
                    $depositCash = $depositAmt;
                }
            }
        }
        
        // === DOWNPAYMENTS ===
        $dpCash = floatval($row['downpayment_cash'] ?? 0);
        $dpGcash = floatval($row['downpayment_gcash'] ?? 0);
        $dpMaya = floatval($row['downpayment_maya'] ?? 0);
        
        // === COMBINE ALL PAYMENT SOURCES ===
        $totalCash = $checkoutCash + $depositCash + $dpCash;
        $totalGcash = $checkoutGcash + $depositGcash + $dpGcash;
        $totalMaya = $checkoutMaya + $depositMaya + $dpMaya;
        
        // Count this booking for each payment method used
        if ($totalCash > 0) {
            $hasCash = true;
            $cashTotal += $totalCash;
            $cashCount++;
        }
        if ($totalGcash > 0) {
            $hasGcash = true;
            $gcashTotal += $totalGcash;
            $gcashCount++;
        }
        if ($totalMaya > 0) {
            $hasMaya = true;
            $mayaTotal += $totalMaya;
            $mayaCount++;
        }
    }
    
    $response['stats']['payment_methods'] = [
        'cash' => $cashCount,
        'gcash' => $gcashCount,
        'maya' => $mayaCount,
        'cash_total' => $cashTotal,
        'gcash_total' => $gcashTotal,
        'maya_total' => $mayaTotal
    ];

    // Promo count - filtered by check_in date.
    // Count promos from both live bookings (upcoming/current) and reports (historical).
    $promoCount = 0;

    if ($hasBookingsTable) {
        $promoBookingsStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM bookings 
            WHERE promo IS NOT NULL 
              AND promo <> '' 
              AND promo <> 'None' 
              AND DATE(check_in) BETWEEN :start AND :end
        ");
        $promoBookingsStmt->bindParam(':start', $filterStart);
        $promoBookingsStmt->bindParam(':end', $filterEnd);
        $promoBookingsStmt->execute();
        $promoBookingsResult = $promoBookingsStmt->fetch(PDO::FETCH_ASSOC);
        $promoCount += intval($promoBookingsResult['count'] ?? 0);
    }

    $promoReportsStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM reports 
        WHERE promo IS NOT NULL 
          AND promo <> '' 
          AND promo <> 'None' 
          AND DATE(check_in) BETWEEN :start AND :end
    ");
    $promoReportsStmt->bindParam(':start', $filterStart);
    $promoReportsStmt->bindParam(':end', $filterEnd);
    $promoReportsStmt->execute();
    $promoReportsResult = $promoReportsStmt->fetch(PDO::FETCH_ASSOC);
    $promoCount += intval($promoReportsResult['count'] ?? 0);

    $response['stats']['promo'] = $promoCount;

    // Walk-in and Reservation counts - filtered by check_in date
    $walkinCount = 0;
    $reservationCount = 0;

    if ($hasBookingsTable) {
        // Count Walk-in bookings
        $walkinBookingsStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM bookings 
            WHERE booking_type = 'Walk-in'
              AND DATE(check_in) BETWEEN :start AND :end
        ");
        $walkinBookingsStmt->bindParam(':start', $filterStart);
        $walkinBookingsStmt->bindParam(':end', $filterEnd);
        $walkinBookingsStmt->execute();
        $walkinBookingsResult = $walkinBookingsStmt->fetch(PDO::FETCH_ASSOC);
        $walkinCount += intval($walkinBookingsResult['count'] ?? 0);

        // Count Reservation bookings
        $reservationBookingsStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM bookings 
            WHERE booking_type = 'Reservation'
              AND DATE(check_in) BETWEEN :start AND :end
        ");
        $reservationBookingsStmt->bindParam(':start', $filterStart);
        $reservationBookingsStmt->bindParam(':end', $filterEnd);
        $reservationBookingsStmt->execute();
        $reservationBookingsResult = $reservationBookingsStmt->fetch(PDO::FETCH_ASSOC);
        $reservationCount += intval($reservationBookingsResult['count'] ?? 0);
    }

    // Also count from reports table
    $walkinReportsStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM reports 
        WHERE booking_type = 'Walk-in'
          AND DATE(check_in) BETWEEN :start AND :end
    ");
    $walkinReportsStmt->bindParam(':start', $filterStart);
    $walkinReportsStmt->bindParam(':end', $filterEnd);
    $walkinReportsStmt->execute();
    $walkinReportsResult = $walkinReportsStmt->fetch(PDO::FETCH_ASSOC);
    $walkinCount += intval($walkinReportsResult['count'] ?? 0);

    $reservationReportsStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM reports 
        WHERE booking_type = 'Reservation'
          AND DATE(check_in) BETWEEN :start AND :end
    ");
    $reservationReportsStmt->bindParam(':start', $filterStart);
    $reservationReportsStmt->bindParam(':end', $filterEnd);
    $reservationReportsStmt->execute();
    $reservationReportsResult = $reservationReportsStmt->fetch(PDO::FETCH_ASSOC);
    $reservationCount += intval($reservationReportsResult['count'] ?? 0);

    $response['stats']['walkin'] = $walkinCount;
    $response['stats']['reservation'] = $reservationCount;

    // Guest Type counts - filtered by check_in date
    $guestTypeSolo = 0;
    $guestTypeDuo = 0;
    $guestTypeFamily = 0;
    $guestTypeGroup = 0;
    $guestTypeCompany = 0;

    if ($hasBookingsTable) {
        // Count from bookings table
        $guestTypeSoloStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE guest_type = 'Solo' AND DATE(check_in) BETWEEN :start AND :end");
        $guestTypeSoloStmt->bindParam(':start', $filterStart);
        $guestTypeSoloStmt->bindParam(':end', $filterEnd);
        $guestTypeSoloStmt->execute();
        $guestTypeSolo += intval($guestTypeSoloStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        $guestTypeDuoStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE guest_type = 'Duo' AND DATE(check_in) BETWEEN :start AND :end");
        $guestTypeDuoStmt->bindParam(':start', $filterStart);
        $guestTypeDuoStmt->bindParam(':end', $filterEnd);
        $guestTypeDuoStmt->execute();
        $guestTypeDuo += intval($guestTypeDuoStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        $guestTypeFamilyStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE guest_type = 'Family' AND DATE(check_in) BETWEEN :start AND :end");
        $guestTypeFamilyStmt->bindParam(':start', $filterStart);
        $guestTypeFamilyStmt->bindParam(':end', $filterEnd);
        $guestTypeFamilyStmt->execute();
        $guestTypeFamily += intval($guestTypeFamilyStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        $guestTypeGroupStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE guest_type = 'Group' AND DATE(check_in) BETWEEN :start AND :end");
        $guestTypeGroupStmt->bindParam(':start', $filterStart);
        $guestTypeGroupStmt->bindParam(':end', $filterEnd);
        $guestTypeGroupStmt->execute();
        $guestTypeGroup += intval($guestTypeGroupStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        $guestTypeCompanyStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE guest_type = 'Company' AND DATE(check_in) BETWEEN :start AND :end");
        $guestTypeCompanyStmt->bindParam(':start', $filterStart);
        $guestTypeCompanyStmt->bindParam(':end', $filterEnd);
        $guestTypeCompanyStmt->execute();
        $guestTypeCompany += intval($guestTypeCompanyStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    }

    // Also count from reports table
    $guestTypeSoloReportsStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE guest_type = 'Solo' AND DATE(check_in) BETWEEN :start AND :end");
    $guestTypeSoloReportsStmt->bindParam(':start', $filterStart);
    $guestTypeSoloReportsStmt->bindParam(':end', $filterEnd);
    $guestTypeSoloReportsStmt->execute();
    $guestTypeSolo += intval($guestTypeSoloReportsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $guestTypeDuoReportsStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE guest_type = 'Duo' AND DATE(check_in) BETWEEN :start AND :end");
    $guestTypeDuoReportsStmt->bindParam(':start', $filterStart);
    $guestTypeDuoReportsStmt->bindParam(':end', $filterEnd);
    $guestTypeDuoReportsStmt->execute();
    $guestTypeDuo += intval($guestTypeDuoReportsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $guestTypeFamilyReportsStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE guest_type = 'Family' AND DATE(check_in) BETWEEN :start AND :end");
    $guestTypeFamilyReportsStmt->bindParam(':start', $filterStart);
    $guestTypeFamilyReportsStmt->bindParam(':end', $filterEnd);
    $guestTypeFamilyReportsStmt->execute();
    $guestTypeFamily += intval($guestTypeFamilyReportsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $guestTypeGroupReportsStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE guest_type = 'Group' AND DATE(check_in) BETWEEN :start AND :end");
    $guestTypeGroupReportsStmt->bindParam(':start', $filterStart);
    $guestTypeGroupReportsStmt->bindParam(':end', $filterEnd);
    $guestTypeGroupReportsStmt->execute();
    $guestTypeGroup += intval($guestTypeGroupReportsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $guestTypeCompanyReportsStmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE guest_type = 'Company' AND DATE(check_in) BETWEEN :start AND :end");
    $guestTypeCompanyReportsStmt->bindParam(':start', $filterStart);
    $guestTypeCompanyReportsStmt->bindParam(':end', $filterEnd);
    $guestTypeCompanyReportsStmt->execute();
    $guestTypeCompany += intval($guestTypeCompanyReportsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $response['stats']['guest_type_solo'] = $guestTypeSolo;
    $response['stats']['guest_type_duo'] = $guestTypeDuo;
    $response['stats']['guest_type_family'] = $guestTypeFamily;
    $response['stats']['guest_type_group'] = $guestTypeGroup;
    $response['stats']['guest_type_company'] = $guestTypeCompany;

    // Revenue summaries (detailed booking report logic + non-refund downpayments)
    $revenueSummary = [];
    $selectedRevenueData = ['total' => 0, 'records' => []];
    $selectedRangeMeta = null;

    $rangeKeys = ['today', 'last_week', 'last_month'];
    foreach ($rangeKeys as $rangeKey) {
        $rangeMeta = buildDateRange($rangeKey);
        $withRecords = ($selectedRangeKey === $rangeKey);
        $rangeOverview = fetchDetailedBookingRevenueOverview($conn, $rangeMeta['start'], $rangeMeta['end']);

        $revenueSummary[$rangeKey] = [
            'label' => $rangeMeta['label'],
            'start' => $rangeMeta['start'],
            'end' => $rangeMeta['end'],
            'total' => $rangeOverview['total']
        ];
        if ($withRecords) {
            $selectedRevenueData = [
                'total' => $rangeOverview['total'],
                'records' => $rangeOverview['records']
            ];
            $selectedRangeMeta = $rangeMeta;
            $selectedRangeMeta['key'] = $rangeKey;
        }
    }

    if ($customStart && $customEnd) {
        $customRangeMeta = buildDateRange('custom', $customStart, $customEnd);
        $customRangeMeta['label'] = 'Custom Range';
        $customRangeMeta['key'] = 'custom';
        $customOverview = fetchDetailedBookingRevenueOverview($conn, $customRangeMeta['start'], $customRangeMeta['end']);

        if ($selectedRangeKey === 'custom') {
            $selectedRevenueData = [
                'total' => $customOverview['total'],
                'records' => $customOverview['records']
            ];
        }
        $customCombinedTotal = $customOverview['total'];
    } else {
        $customRangeMeta = [
            'label' => 'Custom Range',
            'key' => 'custom',
            'start' => '',
            'end' => ''
        ];
        $customCombinedTotal = 0;
    }
    $revenueSummary['custom'] = [
        'label' => $customRangeMeta['label'],
        'start' => $customRangeMeta['start'],
        'end' => $customRangeMeta['end'],
        'total' => $customCombinedTotal
    ];
    if ($selectedRangeKey === 'custom') {
        $selectedRangeMeta = $customRangeMeta;
    }
    if (!$selectedRangeMeta) {
        $selectedRangeMeta = buildDateRange('today');
        $selectedRangeMeta['key'] = 'today';
    }

    $response['stats']['revenue'] = $revenueSummary;
    $response['booking_amounts'] = $selectedRevenueData['records'];
    $response['stats']['selected_range'] = [
        'key' => $selectedRangeMeta['key'],
        'label' => $selectedRangeMeta['label'],
        'start' => $selectedRangeMeta['start'],
        'end' => $selectedRangeMeta['end'],
        'total' => $selectedRevenueData['total']
    ];

    // Calculate occupancy rate using operational rooms (excluding Out of Order)
    $occupancyRate = $operationalRoomsCount > 0 ? round(($occupiedRooms / $operationalRoomsCount) * 100, 1) : 0;
    
    // Calculate available rooms (operational rooms that are not currently occupied)
    $availableRooms = $operationalRoomsCount - $occupiedRooms;
    $response['stats']['available_rooms'] = $availableRooms;
    
    $response['stats']['occupancy'] = [
        'occupied' => $occupiedRooms,
        'available' => $availableRooms,
        'rate' => $occupancyRate
    ];
    
    // Get upcoming check-ins (next 7 days)
    if ($hasBookingsTable) {
        $upcomingCheckInsStmt = $conn->prepare("
            SELECT booking_id, guest_name, room_id, reservation_date, check_in, check_out, status 
            FROM bookings 
            WHERE booking_type = 'Reservation'
              AND status = 'Reserved'
              AND reservation_date IS NOT NULL
              AND DATE(reservation_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY reservation_date ASC
            LIMIT 10
        ");
    } else {
        $upcomingCheckInsStmt = $conn->prepare("
            SELECT booking_id, guest_name, room_id, reservation_date, check_in, check_out, status 
            FROM reports 
            WHERE booking_type = 'Reservation'
              AND status = 'Reserved'
              AND reservation_date IS NOT NULL
              AND DATE(reservation_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY reservation_date ASC
            LIMIT 10
        ");
    }
    $upcomingCheckInsStmt->execute();
    $upcomingCheckIns = $upcomingCheckInsStmt->fetchAll(PDO::FETCH_ASSOC);
    $response['upcoming_checkins'] = $upcomingCheckIns;
    
    // Get upcoming check-outs (next 3 days)
    if ($hasBookingsTable) {
        $upcomingCheckOutsStmt = $conn->prepare("
            SELECT booking_id, guest_name, room_id, check_in, check_out, status 
            FROM bookings 
            WHERE status IN ('Confirming', 'Confirmed', 'Occupied') 
              AND DATE(check_out) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
            ORDER BY check_out ASC
            LIMIT 10
        ");
    } else {
        $upcomingCheckOutsStmt = $conn->prepare("
            SELECT booking_id, guest_name, room_id, check_in, check_out, status 
            FROM reports 
            WHERE status IN ('Confirming', 'Confirmed', 'Occupied') 
              AND DATE(check_out) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
            ORDER BY check_out ASC
            LIMIT 10
        ");
    }
    $upcomingCheckOutsStmt->execute();
    $upcomingCheckOuts = $upcomingCheckOutsStmt->fetchAll(PDO::FETCH_ASSOC);
    $response['upcoming_checkouts'] = $upcomingCheckOuts;
    
    // Debug: Get total count and all statuses for troubleshooting
    if ($hasBookingsTable) {
        $totalBookingsStmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings");
        $totalBookingsStmt->execute();
        $totalBookingsResult = $totalBookingsStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $totalBookingsResult = ['total' => 0];
    }

    if ($hasReportsTable) {
        $totalReportsStmt = $conn->prepare("SELECT COUNT(*) as total FROM reports");
        $totalReportsStmt->execute();
        $totalReportsResult = $totalReportsStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $totalReportsResult = ['total' => 0];
    }
    
    // Debug: Get sample payment data to troubleshoot
    $debugPaymentStmt = $conn->prepare("
        SELECT 
            booking_id,
            status,
            checked_out_at,
            check_out,
            payment_status_cash, 
            payment_status_g_cash, 
            payment_status_maya,
            downpayment_cash,
            downpayment_gcash,
            downpayment_maya
        FROM reports
        WHERE status = 'Checked Out'
        ORDER BY COALESCE(checked_out_at, check_out) DESC
        LIMIT 5
    ");
    $debugPaymentStmt->execute();
    $debugPaymentData = $debugPaymentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['debug'] = [
        'total_bookings_records' => intval($totalBookingsResult['total'] ?? 0),
        'total_reports_records' => intval($totalReportsResult['total'] ?? 0),
        'check_in_from_bookings' => isset($checkInBookingsResult) ? intval($checkInBookingsResult['count'] ?? 0) : 0,
        'check_in_total' => $checkInCount,
        'check_out_query_result' => intval($checkOutResult['count'] ?? 0),
        'total_rooms_query_result' => intval($totalRoomsResult['count'] ?? 0),
        'canceled_query_result' => intval($canceledResult['count'] ?? 0),
        'payment_stats_filter_range' => ['start' => $filterStart, 'end' => $filterEnd],
        'payment_counts' => ['cash' => $cashCount, 'gcash' => $gcashCount, 'maya' => $mayaCount],
        'payment_totals' => ['cash' => $cashTotal, 'gcash' => $gcashTotal, 'maya' => $mayaTotal],
        'sample_checkout_records' => $debugPaymentData
    ];
    
    $response['success'] = true;
    $response['message'] = 'Statistics loaded successfully.';
    
} catch(PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch(Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
} catch(Error $e) {
    $response['message'] = 'Fatal error: ' . $e->getMessage();
}

echo json_encode($response);
?>

