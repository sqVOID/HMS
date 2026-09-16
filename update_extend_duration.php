<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'report_helpers.php';

try {
    // Start session to read logged-in user info (encoder)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Determine encoder = full name of the currently logged-in user
    $_enc_first = trim($_SESSION['first_name'] ?? '');
    $_enc_last  = trim($_SESSION['last_name']  ?? '');
    if ($_enc_first !== '' || $_enc_last !== '') {
        $currentEncoder = trim($_enc_first . ' ' . $_enc_last);
    } else {
        $currentEncoder = trim($_SESSION['username'] ?? 'Unknown');
    }

    $bookingId              = $_POST['booking_id']    ?? null;
    $extendType             = $_POST['extend_type']   ?? 'duration';
    $extendHours            = intval($_POST['extend_hours']   ?? 0);
    $extendMinutes          = intval($_POST['extend_minutes']  ?? 0);
    $extendRegularRate      = floatval($_POST['extend_regular_rate']  ?? 0);
    $extendBundleRate       = floatval($_POST['extend_bundle_rate']  ?? 0);
    $extendBundleBreakfast  = $_POST['extend_bundle_breakfast']  ?? null;
    $extendAdditionalGuest  = intval($_POST['extend_additional_guest'] ?? 0);

    if (!$bookingId) {
        echo json_encode(['success' => false, 'error' => 'Booking ID is required']);
        exit;
    }

    // Log the booking ID for debugging
    error_log("Searching for booking with ID: " . $bookingId);

    // Calculate extension price based on type (room rate only)
    $extendPrice = 0;
    if ($extendType === 'duration') {
        // Duration pricing: 200 per hour, 100 for 30 minutes
        $extendPrice = ($extendHours * 200) + ($extendMinutes === 30 ? 100 : 0);
    } else if ($extendRegularRate > 0) {
        $extendPrice = $extendRegularRate;
    } else if ($extendBundleRate > 0) {
        $extendPrice = $extendBundleRate;
    }

    // Get current booking data - try both id and booking_id fields
    $stmt = $conn->prepare("SELECT id, booking_id, room_id, check_in, check_out, duration, total_amount, extend_price, extend_additional_guest
                            FROM bookings WHERE id = :id OR booking_id = :booking_id");
    $stmt->bindParam(':id', $bookingId, PDO::PARAM_STR);
    $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_STR);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        // Try to find in reports table
        $stmtReport = $conn->prepare("SELECT booking_id, room_id, check_in, check_out, duration, total_amount, extend_price, extend_additional_guest
                                FROM reports WHERE booking_id = :booking_id");
        $stmtReport->bindParam(':booking_id', $bookingId, PDO::PARAM_STR);
        $stmtReport->execute();
        $reportBooking = $stmtReport->fetch(PDO::FETCH_ASSOC);
        
        if ($reportBooking) {
            // Booking is in reports table (completed), update only reports
            $checkInTime = new DateTime($reportBooking['check_in']);
            $baseDuration = intval($reportBooking['duration'] ?? 12);
            
            $checkoutDateTime = clone $checkInTime;
            $checkoutDateTime->modify("+{$baseDuration} hours");
            $checkoutDateTime->modify("+{$extendHours} hours");
            $checkoutDateTime->modify("+{$extendMinutes} minutes");
            $checkoutDateTimeStr = $checkoutDateTime->format('Y-m-d H:i:s');
            
            $currentTotal = floatval($reportBooking['total_amount'] ?? 0);
            $oldExtendPrice = floatval($reportBooking['extend_price'] ?? 0);
            $oldExtendAdditionalGuest = intval($reportBooking['extend_additional_guest'] ?? 0);
            
            // Recalculate total amount including the guest extension fee difference (₱300 per guest)
            $newTotalAmount = $currentTotal - $oldExtendPrice - ($oldExtendAdditionalGuest * 300) + $extendPrice + ($extendAdditionalGuest * 300);
            
            $updateReportStmt = $conn->prepare("
                UPDATE reports 
                SET extend_hours = :extend_hours,
                    extend_minutes = :extend_minutes,
                    extend_price = :extend_price,
                    extend_regular_rate = :extend_regular_rate,
                    extend_bundle_rate = :extend_bundle_rate,
                    extend_bundle_breakfast = :extend_bundle_breakfast,
                    extend_additional_guest = :extend_additional_guest,
                    check_out = :check_out,
                    total_amount = :total_amount
                WHERE booking_id = :booking_id
            ");
            
            $updateReportStmt->bindParam(':extend_hours', $extendHours, PDO::PARAM_INT);
            $updateReportStmt->bindParam(':extend_minutes', $extendMinutes, PDO::PARAM_INT);
            $updateReportStmt->bindParam(':extend_price', $extendPrice);
            $updateReportStmt->bindParam(':extend_regular_rate', $extendRegularRate);
            $updateReportStmt->bindParam(':extend_bundle_rate', $extendBundleRate);
            $updateReportStmt->bindParam(':extend_bundle_breakfast', $extendBundleBreakfast);
            $updateReportStmt->bindParam(':extend_additional_guest', $extendAdditionalGuest, PDO::PARAM_INT);
            $updateReportStmt->bindParam(':check_out', $checkoutDateTimeStr);
            $updateReportStmt->bindParam(':total_amount', $newTotalAmount);
            $updateReportStmt->bindParam(':booking_id', $bookingId);
            
            if ($updateReportStmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Extension updated successfully (reports)',
                    'new_checkout' => $checkoutDateTimeStr,
                    'new_total' => $newTotalAmount
                ]);
                exit;
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update report']);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Booking not found in bookings or reports table. ID: ' . $bookingId]);
        exit;
    }
    
    // Use the actual numeric ID for updates
    $actualId = $booking['id'];

    // Calculate new total amount (remove old room extension rate and old guest fee, add new room extension rate and new guest fee)
    $currentTotal = floatval($booking['total_amount'] ?? 0);
    $oldExtendPrice = floatval($booking['extend_price'] ?? 0);
    $oldExtendAdditionalGuest = intval($booking['extend_additional_guest'] ?? 0);
    
    $newTotalAmount = $currentTotal - $oldExtendPrice - ($oldExtendAdditionalGuest * 300) + $extendPrice + ($extendAdditionalGuest * 300);

    // Calculate new checkout time
    $checkInTime = new DateTime($booking['check_in']);
    $baseDuration = intval($booking['duration'] ?? 12);
    
    $checkoutDateTime = clone $checkInTime;
    $checkoutDateTime->modify("+{$baseDuration} hours");
    $checkoutDateTime->modify("+{$extendHours} hours");
    $checkoutDateTime->modify("+{$extendMinutes} minutes");
    $checkoutDateTimeStr = $checkoutDateTime->format('Y-m-d H:i:s');

    // REPLACE extension data in bookings table
    $updateStmt = $conn->prepare("
        UPDATE bookings 
        SET extend_hours = :extend_hours,
            extend_minutes = :extend_minutes,
            extend_price = :extend_price,
            extend_regular_rate = :extend_regular_rate,
            extend_bundle_rate = :extend_bundle_rate,
            extend_bundle_breakfast = :extend_bundle_breakfast,
            extend_additional_guest = :extend_additional_guest,
            check_out = :check_out,
            total_amount = :total_amount
        WHERE id = :id
    ");
    
    $updateStmt->bindParam(':extend_hours', $extendHours, PDO::PARAM_INT);
    $updateStmt->bindParam(':extend_minutes', $extendMinutes, PDO::PARAM_INT);
    $updateStmt->bindParam(':extend_price', $extendPrice);
    $updateStmt->bindParam(':extend_regular_rate', $extendRegularRate);
    $updateStmt->bindParam(':extend_bundle_rate', $extendBundleRate);
    $updateStmt->bindParam(':extend_bundle_breakfast', $extendBundleBreakfast);
    $updateStmt->bindParam(':extend_additional_guest', $extendAdditionalGuest, PDO::PARAM_INT);
    $updateStmt->bindParam(':check_out', $checkoutDateTimeStr);
    $updateStmt->bindParam(':total_amount', $newTotalAmount);
    $updateStmt->bindParam(':id', $actualId, PDO::PARAM_INT);
    
    if (!$updateStmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Failed to update booking']);
        exit;
    }

    // Also update reports table if the booking exists there
    $bookingIdString = $booking['booking_id'] ?? '';
    if ($bookingIdString !== '') {
        $updateReportStmt = $conn->prepare("
            UPDATE reports 
            SET extend_hours = :extend_hours,
                extend_minutes = :extend_minutes,
                extend_price = :extend_price,
                extend_regular_rate = :extend_regular_rate,
                extend_bundle_rate = :extend_bundle_rate,
                extend_bundle_breakfast = :extend_bundle_breakfast,
                extend_additional_guest = :extend_additional_guest,
                check_out = :check_out,
                total_amount = :total_amount
            WHERE booking_id = :booking_id
        ");
        
        $updateReportStmt->bindParam(':extend_hours', $extendHours, PDO::PARAM_INT);
        $updateReportStmt->bindParam(':extend_minutes', $extendMinutes, PDO::PARAM_INT);
        $updateReportStmt->bindParam(':extend_price', $extendPrice);
        $updateReportStmt->bindParam(':extend_regular_rate', $extendRegularRate);
        $updateReportStmt->bindParam(':extend_bundle_rate', $extendBundleRate);
        $updateReportStmt->bindParam(':extend_bundle_breakfast', $extendBundleBreakfast);
        $updateReportStmt->bindParam(':extend_additional_guest', $extendAdditionalGuest, PDO::PARAM_INT);
        $updateReportStmt->bindParam(':check_out', $checkoutDateTimeStr);
        $updateReportStmt->bindParam(':total_amount', $newTotalAmount);
        $updateReportStmt->bindParam(':booking_id', $bookingIdString);
        
        $updateReportStmt->execute();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Extension updated successfully',
        'new_checkout' => $checkoutDateTimeStr,
        'new_total' => $newTotalAmount
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
