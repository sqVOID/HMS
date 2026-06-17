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

    // Merge encoder names without duplicates (case-insensitive).
    // Format: "Name1 & Name2"
    $mergeEncoderNames = function ($existing, $current) {
        $current = trim((string)$current);
        $existing = trim((string)$existing);
        if ($current === '') return $existing;
        if ($existing === '') return $current;

        $parts = preg_split('/\s*(?:&|,|\|)\s*/', $existing);
        $names = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $key = mb_strtolower($p);
            $names[$key] = $p;
        }

        $curKey = mb_strtolower($current);
        if (!isset($names[$curKey])) {
            $names[$curKey] = $current;
        }
        return implode(' & ', array_values($names));
    };

    $bookingId              = $_POST['booking_id']    ?? null;
    $extendType             = $_POST['extend_type']   ?? 'duration';
    $extendHours            = intval($_POST['extend_hours']   ?? 0);
    $extendMinutes          = intval($_POST['extend_minutes']  ?? 0);
    $extendPrice            = floatval($_POST['extend_price']  ?? 0);
    $extendRegularRate      = floatval($_POST['extend_regular_rate']  ?? 0);
    $extendBundleRate       = floatval($_POST['extend_bundle_rate']  ?? 0);
    $extendBundleBreakfast  = $_POST['extend_bundle_breakfast']  ?? null;
    $newCheckout            = $_POST['new_checkout']  ?? null;
    $newAdditionalGuestTotal = intval($_POST['additional_guest'] ?? 0); // NEW total (base + extend combined)
    $previousAdditionalGuest = 0; // will be read from DB below — placeholder
    $extendAdditionalGuest  = 0;  // no longer stored separately
    $newAdditionalPetTotal  = intval($_POST['additional_pet'] ?? 0);   // NEW total pet (base + extend combined)
    $previousAdditionalPet  = 0; // will be read from DB below — placeholder
    $extendAdditionalPet    = 0;  // delta pets in this segment

    if (!$bookingId) {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
        exit;
    }

    // Ensure schema columns exist (best-effort)
    ensureReportFinancialColumns($conn);

    // Get current booking data INCLUDING existing extensions and payment fields
    $stmt = $conn->prepare("SELECT booking_id, room_id, check_in, check_out, duration, duration_unit,
                            room_type, room_price, promo, breakfast,
                            extend_hours, extend_minutes, extend_price,
                            extend_regular_rate, extend_bundle_rate, extend_bundle_breakfast, extend_bundle_breakfast_date,
                            additional_guest, additional_pet, extend_additional_guest, extension_stack,
                            total_amount, deposit, downpayment_amount, discount_amount, paid_status,
                            encoder, extension_time_at
                            FROM bookings WHERE id = :id");
    $stmt->bindParam(':id', $bookingId, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    // Resolve the DELTA guests added this extension (new total minus the current additional_guest base)
    $previousAdditionalGuest = intval($booking['additional_guest'] ?? 0);
    $extendAdditionalGuest   = max(0, $newAdditionalGuestTotal - $previousAdditionalGuest);
    // The new total that will be written to additional_guest
    $newAdditionalGuestFinal = $newAdditionalGuestTotal;

    // Resolve the DELTA pets added this extension (new total minus the current additional_pet base)
    $previousAdditionalPet = intval($booking['additional_pet'] ?? 0);
    $extendAdditionalPet   = max(0, $newAdditionalPetTotal - $previousAdditionalPet);
    // The new total that will be written to additional_pet
    $newAdditionalPetFinal = $newAdditionalPetTotal;

    // EXTENSION HISTORY: Append new extension timestamp
    // extension_time_at stores multiple timestamps separated by "|"
    // Example: "2026-01-18 10:10:00|2026-01-18 23:04:00"
    $existingExtensionTime = $booking['extension_time_at'] ?? null;
    $newExtensionTimestamp = date('Y-m-d H:i:s');
    
    if (!empty($existingExtensionTime)) {
        // Append to existing timestamps
        $updatedExtensionTime = $existingExtensionTime . '|' . $newExtensionTimestamp;
    } else {
        // First extension
        $updatedExtensionTime = $newExtensionTimestamp;
    }
    
    error_log("Extension recorded at: $newExtensionTimestamp");
    error_log("Updated extension_time_at: $updatedExtensionTime");

    // Per-segment bundle breakfast for this request only (stack aggregates full extend_bundle_breakfast)
    $segmentBf = null;
    if ($extendBundleBreakfast) {
        $newBreakfastStrArray = [];
        $newArray = json_decode($extendBundleBreakfast, true);

        if (is_array($newArray)) {
            foreach ($newArray as $entry) {
                if (empty($entry['item'])) {
                    continue;
                }
                $rawItem = $entry['item'];
                $quantity = isset($entry['quantity']) ? intval($entry['quantity']) : 1;
                $nameParts = explode(' - ', $rawItem);
                $name = trim($nameParts[0]);
                $name = ucwords(strtolower($name));
                $newBreakfastStrArray[] = "{$quantity} {$name} (Promo)";
            }
            $segmentBf = implode(' | ', $newBreakfastStrArray);
        } else {
            $segmentBf = $extendBundleBreakfast;
        }
    }

    // Parse the new checkout time and format it properly for database
    $checkoutDateTime = null;
    if ($newCheckout) {
        try {
            // Parse format: "3/10/2026, 3:14 PM"
            $checkoutDateTime = DateTime::createFromFormat('n/j/Y, g:i A', $newCheckout);
            if (!$checkoutDateTime) {
                // Try alternative format: "2025-12-23 11:00 PM"
                $checkoutDateTime = DateTime::createFromFormat('Y-m-d h:i A', $newCheckout);
            }
            if ($checkoutDateTime) {
                $checkoutDateTime = $checkoutDateTime->format('Y-m-d H:i:s');
            }
        } catch (Exception $e) {
            // If parsing fails, calculate from current checkout + hours + minutes
            if ($booking['check_out']) {
                $currentCheckout = new DateTime($booking['check_out']);
                $currentCheckout->modify("+{$extendHours} hours");
                $currentCheckout->modify("+{$extendMinutes} minutes");
                $checkoutDateTime = $currentCheckout->format('Y-m-d H:i:s');
            }
        }
    }

    // If we still don't have a checkout time, calculate it
    if (!$checkoutDateTime && $booking['check_out']) {
        $currentCheckout = new DateTime($booking['check_out']);
        $currentCheckout->modify("+{$extendHours} hours");
        $currentCheckout->modify("+{$extendMinutes} minutes");
        $checkoutDateTime = $currentCheckout->format('Y-m-d H:i:s');
    }

    // Extension stack: each save is one segment so "Withdraw extension" removes only the last increment.
    $previousExtendHours = intval($booking['extend_hours'] ?? 0);
    $previousExtendMinutes = intval($booking['extend_minutes'] ?? 0);
    $previousExtendPrice = floatval($booking['extend_price'] ?? 0);
    $previousExtendRegularRate = floatval($booking['extend_regular_rate'] ?? 0);
    $previousExtendBundleRate = floatval($booking['extend_bundle_rate'] ?? 0);

    $stack = booking_extension_stack_decode($booking['extension_stack'] ?? null);
    if ($stack === [] && ($previousExtendHours > 0 || $previousExtendMinutes > 0 || $previousExtendPrice > 0)) {
        $prevBf = $booking['extend_bundle_breakfast'] ?? null;
        $prevBf = ($prevBf !== null && trim((string) $prevBf) !== '' && strcasecmp(trim((string) $prevBf), 'None') !== 0)
            ? trim((string) $prevBf) : null;
        $stack[] = [
            'h' => $previousExtendHours,
            'm' => $previousExtendMinutes,
            'price' => $previousExtendPrice,
            'reg' => $previousExtendRegularRate,
            'bun' => $previousExtendBundleRate,
            'bf' => $prevBf,
            'eg' => intval($booking['extend_additional_guest'] ?? 0),
        ];
    }
    $stack[] = [
        'h' => $extendHours,
        'm' => $extendMinutes,
        'price' => $extendPrice,
        'reg' => $extendRegularRate,
        'bun' => $extendBundleRate,
        'bf' => ($segmentBf !== null && trim((string) $segmentBf) !== '') ? trim((string) $segmentBf) : null,
        'eg' => $extendAdditionalGuest,
        'ep' => $extendAdditionalPet,
    ];
    $agg = booking_extension_stack_aggregate_segments($stack);
    $totalExtendHours = $agg['h'];
    $totalExtendMinutes = $agg['m'];
    $totalExtendPrice = $agg['price'];
    $totalExtendRegularRate = $agg['reg'];
    $totalExtendBundleRate = $agg['bun'];
    $finalBreakfastData = $agg['bf'];
    $totalExtendAdditionalGuest = $agg['eg'];
    $totalExtendAdditionalPet = $agg['ep'] ?? 0;
    $extensionStackJson = booking_extension_stack_encode($stack);

    // Track extend_bundle_breakfast date - append timestamp whenever extension has bundle breakfast
    $extend_bundle_breakfast_date = $booking['extend_bundle_breakfast_date'] ?? null;
    $currentExtendBreakfast = trim($finalBreakfastData ?? '');
    
    // If this extension has bundle breakfast, append new timestamp
    // (We track every extension with breakfast, not just changes)
    if (!empty($currentExtendBreakfast) && $currentExtendBreakfast !== 'None') {
        $breakfastDates = json_decode($extend_bundle_breakfast_date, true) ?: [];
        $breakfastDates[] = $newExtensionTimestamp; // Use same timestamp as extension
        $extend_bundle_breakfast_date = json_encode($breakfastDates);
        error_log("=== EXTEND BUNDLE BREAKFAST ADDED ===");
        error_log("Bundle Breakfast: '{$currentExtendBreakfast}'");
        error_log("extend_bundle_breakfast_date: {$extend_bundle_breakfast_date}");
    }

    // Recalculate amount due using the same helper as get_bookings.php:
    // (room/promo + all extension charges) minus deposit already paid.
    // Do NOT do total_amount + extendPrice — that double-counts when a prior
    // extension was already paid but total_amount was not zeroed yet.
    $newTotalAmount = computeBookingTotalAmount([
        'room_type'              => $booking['room_type'] ?? '',
        'duration'               => intval($booking['duration'] ?? 0),
        'duration_unit'          => $booking['duration_unit'] ?? 'hours',
        'promo'                  => $booking['promo'] ?? null,
        'breakfast'              => $booking['breakfast'] ?? null,
        'hygiene_kit_used'       => 0,
        'hygiene_kit_price'      => 0,
        'room_price'             => floatval($booking['room_price'] ?? 0),
        'extend_price'           => $totalExtendPrice,
        'deposit'                => floatval($booking['deposit'] ?? 0),
        'discount_amount'        => floatval($booking['discount_amount'] ?? 0),
        'additional_guest'       => $newAdditionalGuestFinal,
        'extend_additional_guest'=> 0,  // guests merged into additional_guest; no double-count
        'additional_pet'         => $newAdditionalPetFinal,
    ]);

    // Mark Unpaid only when there is a remaining balance
    $newPaidStatus = ($newTotalAmount > 0.01) ? 'Unpaid' : ($booking['paid_status'] ?? 'Paid');

    // Preserve existing encoder(s) and append current user if new
    $mergedEncoder = $mergeEncoderNames($booking['encoder'] ?? '', $currentEncoder);

    // Update the booking with TOTAL accumulated extend hours, minutes, price,
    // new checkout time, updated total_amount, paid_status, and extension_time_at
    $updateStmt = $conn->prepare("
        UPDATE bookings 
        SET extend_hours   = :extend_hours, 
            extend_minutes = :extend_minutes,
            extend_price   = :extend_price,
            extend_regular_rate = :extend_regular_rate,
            extend_bundle_rate = :extend_bundle_rate,
            extend_bundle_breakfast = :extend_bundle_breakfast,
            extend_bundle_breakfast_date = :extend_bundle_breakfast_date,
            additional_guest = :additional_guest,
            extend_additional_guest = 0,
            additional_pet   = :additional_pet,
            check_out      = :check_out,
            total_amount   = :total_amount,
            paid_status    = :paid_status,
            encoder        = :encoder,
            extension_time_at = :extension_time_at,
            extension_stack = :extension_stack
        WHERE id = :id
    ");
    $updateStmt->bindParam(':extend_hours',   $totalExtendHours,   PDO::PARAM_INT);
    $updateStmt->bindParam(':extend_minutes', $totalExtendMinutes, PDO::PARAM_INT);
    $updateStmt->bindParam(':extend_price',   $totalExtendPrice);
    $updateStmt->bindParam(':extend_regular_rate', $totalExtendRegularRate);
    $updateStmt->bindParam(':extend_bundle_rate',  $totalExtendBundleRate);
    $updateStmt->bindParam(':extend_bundle_breakfast', $finalBreakfastData);
    if ($extend_bundle_breakfast_date === null) {
        $updateStmt->bindValue(':extend_bundle_breakfast_date', null, PDO::PARAM_NULL);
    } else {
        $updateStmt->bindParam(':extend_bundle_breakfast_date', $extend_bundle_breakfast_date);
    }
    $updateStmt->bindParam(':additional_guest', $newAdditionalGuestFinal, PDO::PARAM_INT);
    $updateStmt->bindParam(':additional_pet',   $newAdditionalPetFinal,   PDO::PARAM_INT);
    $updateStmt->bindParam(':check_out',      $checkoutDateTime);
    $updateStmt->bindParam(':total_amount',   $newTotalAmount);
    $updateStmt->bindParam(':paid_status',    $newPaidStatus);
    $updateStmt->bindParam(':encoder',        $mergedEncoder);
    $updateStmt->bindParam(':extension_time_at', $updatedExtensionTime);
    if ($extensionStackJson !== null && $extensionStackJson !== '') {
        $updateStmt->bindValue(':extension_stack', $extensionStackJson, PDO::PARAM_STR);
    } else {
        $updateStmt->bindValue(':extension_stack', null, PDO::PARAM_NULL);
    }
    $updateStmt->bindParam(':id',             $bookingId, PDO::PARAM_INT);

    if ($updateStmt->execute()) {
        // Also update the reports table.
        // IMPORTANT: bookings.id is numeric, but reports.booking_id is typically the booking_id string.
        // Some flows can have empty booking_id, so we also match by room_id + check_in for active stays.
        $bookingIdString = trim((string)($booking['booking_id'] ?? ''));
        $roomIdValue = trim((string)($booking['room_id'] ?? ''));
        $checkInValue = $booking['check_in'] ?? null;

        if ($bookingIdString !== '') {
            $whereClause = "booking_id = :booking_id";
        } else {
            $whereClause = "room_id = :room_id AND check_in = :check_in AND status IN ('Confirming','Confirmed','Occupied')";
        }

        $updateReportStmt = $conn->prepare("
            UPDATE reports 
            SET extend_hours   = :extend_hours, 
                extend_minutes = :extend_minutes,
                extend_price   = :extend_price,
                extend_regular_rate = :extend_regular_rate,
                extend_bundle_rate = :extend_bundle_rate,
                extend_bundle_breakfast = :extend_bundle_breakfast,
                extend_bundle_breakfast_date = :extend_bundle_breakfast_date,
                additional_guest = :additional_guest,
                extend_additional_guest = 0,
                additional_pet   = :additional_pet,
                check_out      = :check_out,
                total_amount   = :total_amount,
                paid_status    = :paid_status,
                encoder        = :encoder,
                extension_time_at = :extension_time_at,
                extension_stack = :extension_stack
            WHERE
                $whereClause
        ");
        $updateReportStmt->bindParam(':extend_hours',   $totalExtendHours,   PDO::PARAM_INT);
        $updateReportStmt->bindParam(':extend_minutes', $totalExtendMinutes, PDO::PARAM_INT);
        $updateReportStmt->bindParam(':extend_price',   $totalExtendPrice);
        $updateReportStmt->bindParam(':extend_regular_rate', $totalExtendRegularRate);
        $updateReportStmt->bindParam(':extend_bundle_rate',  $totalExtendBundleRate);
        $updateReportStmt->bindParam(':extend_bundle_breakfast', $finalBreakfastData);
        if ($extend_bundle_breakfast_date === null) {
            $updateReportStmt->bindValue(':extend_bundle_breakfast_date', null, PDO::PARAM_NULL);
        } else {
            $updateReportStmt->bindParam(':extend_bundle_breakfast_date', $extend_bundle_breakfast_date);
        }
        $updateReportStmt->bindParam(':additional_guest', $newAdditionalGuestFinal, PDO::PARAM_INT);
        $updateReportStmt->bindParam(':additional_pet',   $newAdditionalPetFinal,   PDO::PARAM_INT);
        $updateReportStmt->bindParam(':check_out',      $checkoutDateTime);
        $updateReportStmt->bindParam(':total_amount',   $newTotalAmount);
        $updateReportStmt->bindParam(':paid_status',    $newPaidStatus);
        $updateReportStmt->bindParam(':encoder',        $mergedEncoder);
        $updateReportStmt->bindParam(':extension_time_at', $updatedExtensionTime);
        if ($extensionStackJson !== null && $extensionStackJson !== '') {
            $updateReportStmt->bindValue(':extension_stack', $extensionStackJson, PDO::PARAM_STR);
        } else {
            $updateReportStmt->bindValue(':extension_stack', null, PDO::PARAM_NULL);
        }
        
        if ($bookingIdString !== '') {
            $updateReportStmt->bindParam(':booking_id', $bookingIdString);
        } else {
            $updateReportStmt->bindParam(':room_id',        $roomIdValue);
            $updateReportStmt->bindParam(':check_in',       $checkInValue);
        }
        $updateReportStmt->execute();
        
        echo json_encode([
            'success'            => true,
            'message'            => 'Extension saved successfully',
            'extend_hours'       => $totalExtendHours,
            'extend_minutes'     => $totalExtendMinutes,
            'extend_price'       => $totalExtendPrice,
            'additional_guest'        => $newAdditionalGuestFinal,
            'additional_pet'          => $newAdditionalPetFinal,
            'extend_additional_guest' => 0,
            'extend_regular_rate' => $totalExtendRegularRate,
            'extend_bundle_rate'  => $totalExtendBundleRate,
            'extend_bundle_breakfast' => $finalBreakfastData,
            'check_out'          => $checkoutDateTime,
            'formatted_checkout' => date('Y-m-d h:i A', strtotime($checkoutDateTime)),
            'total_amount'       => $newTotalAmount,
            'paid_status'        => $newPaidStatus,
            'incremental_extend_price' => $extendPrice,
            'extension_stack'    => $extensionStackJson,
            'extension_time_at'  => $updatedExtensionTime,
            'extension_timestamp' => $newExtensionTimestamp
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save extension']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>