<?php
require_once "config.php";

header("Content-Type: application/json");

try {
    // First, try to get actual bookings if bookings table exists
    $bookings = [];
    $hasBookingsTable = false;

    // Check if bookings table exists
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'bookings'");
        $hasBookingsTable = $checkTable->rowCount() > 0;
    } catch (PDOException $e) {
        $hasBookingsTable = false;
    }

    if ($hasBookingsTable) {
        // Ensure tin_number column exists
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'tin_number'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN tin_number VARCHAR(255) DEFAULT NULL AFTER address"
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add tin_number column: " . $e->getMessage(),
            );
        }

        // Ensure additional_guest column exists
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'additional_guest'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN additional_guest INT DEFAULT 0 AFTER address"
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add additional_guest column: " . $e->getMessage(),
            );
        }

        // Ensure additional_pet column exists
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'additional_pet'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN additional_pet INT DEFAULT 0 AFTER additional_guest"
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add additional_pet column: " . $e->getMessage(),
            );
        }
        // Ensure reason_for_stay column exists
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'reason_for_stay'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN reason_for_stay VARCHAR(255) DEFAULT NULL AFTER guest_name",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add reason_for_stay column: " .
                $e->getMessage(),
            );
        }

        // Ensure address column exists
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'address'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN address TEXT DEFAULT NULL AFTER reason_for_stay",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add address column: " . $e->getMessage(),
            );
        }

        // Ensure extended_duration column exists
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'extended_duration'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN extended_duration INT DEFAULT 0 AFTER duration",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add extended_duration column: " .
                $e->getMessage(),
            );
        }

        // Ensure extend_hours column exists
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'extend_hours'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN extend_hours INT DEFAULT 0",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add extend_hours column: " .
                $e->getMessage(),
            );
        }

        // Ensure extend_minutes column exists
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'extend_minutes'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN extend_minutes INT DEFAULT 0",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add extend_minutes column: " .
                $e->getMessage(),
            );
        }

        // Ensure extend_price column exists
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'extend_price'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN extend_price DECIMAL(10,2) DEFAULT 0.00",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add extend_price column: " .
                $e->getMessage(),
            );
        }

        // Ensure extension withdrawal tracking columns exist
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'extension_withdraw'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN extension_withdraw TINYINT(1) DEFAULT 0");
            }
        } catch (PDOException $e) {
            error_log("Failed to check/add extension_withdraw column: " . $e->getMessage());
        }
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'refund_amount_extension'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN refund_amount_extension DECIMAL(10,2) DEFAULT 0.00");
            }
        } catch (PDOException $e) {
            error_log("Failed to check/add refund_amount_extension column: " . $e->getMessage());
        }

        // Ensure withdrawn extension columns exist (so we can show both active + withdrawn rows)
        $withdrawnCols = [
            'withdrawn_extend_hours' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_hours INT DEFAULT 0",
            'withdrawn_extend_minutes' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_minutes INT DEFAULT 0",
            'withdrawn_extend_price' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_price DECIMAL(10,2) DEFAULT 0.00",
            'withdrawn_extend_regular_rate' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_regular_rate DECIMAL(10,2) DEFAULT NULL",
            'withdrawn_extend_bundle_rate' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_bundle_rate DECIMAL(10,2) DEFAULT NULL",
            'withdrawn_extend_bundle_breakfast' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_bundle_breakfast TEXT DEFAULT NULL"
        ];
        foreach ($withdrawnCols as $col => $sql) {
            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE '$col'");
                if ($checkColumn->rowCount() == 0) {
                    $conn->exec($sql);
                }
            } catch (PDOException $e) {
                error_log("Failed to check/add $col column: " . $e->getMessage());
            }
        }

        // Ensure missing_items_fees and missing_items_list columns exist
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'missing_items_fees'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN missing_items_fees DECIMAL(10,2) DEFAULT 0",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add missing_items_fees column: " .
                $e->getMessage(),
            );
        }

        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'missing_items_list'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN missing_items_list TEXT NULL DEFAULT NULL",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add missing_items_list column: " .
                $e->getMessage(),
            );
        }

        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'additional_food'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN additional_food TEXT NULL DEFAULT NULL",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add additional_food column: " .
                $e->getMessage(),
            );
        }

        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'additional_items'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN additional_items TEXT NULL DEFAULT NULL",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add additional_items column: " .
                $e->getMessage(),
            );
        }

        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'additional_fees_status'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN additional_fees_status VARCHAR(50) DEFAULT 'None'",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add additional_fees_status column: " .
                $e->getMessage(),
            );
        }

        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'additional_fees_items'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN additional_fees_items TEXT NULL DEFAULT NULL",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add additional_fees_items column: " .
                $e->getMessage(),
            );
        }

        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'additional_paid_status'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN additional_paid_status VARCHAR(50) DEFAULT 'None'",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add additional_paid_status column: " .
                $e->getMessage(),
            );
        }

        // Ensure paid_status column exists
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'paid_status'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN paid_status VARCHAR(50) DEFAULT 'Unpaid'",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add paid_status column: " . $e->getMessage(),
            );
        }

        // Ensure penalty_amount column exists
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'penalty_amount'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN penalty_amount DECIMAL(10,2) DEFAULT 0.00");
            }
        } catch (PDOException $e) {
            error_log("Failed to check/add penalty_amount column: " . $e->getMessage());
        }

        // Ensure penalty_list column exists
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'penalty_list'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE bookings ADD COLUMN penalty_list TEXT NULL DEFAULT NULL");
            }
        } catch (PDOException $e) {
            error_log("Failed to check/add penalty_list column: " . $e->getMessage());
        }

        // Ensure deposit column exists
        try {
            $checkColumn = $conn->query(
                "SHOW COLUMNS FROM bookings LIKE 'deposit'",
            );
            if ($checkColumn->rowCount() == 0) {
                $conn->exec(
                    "ALTER TABLE bookings ADD COLUMN deposit DECIMAL(10,2) DEFAULT 0",
                );
            }
        } catch (PDOException $e) {
            error_log(
                "Failed to check/add deposit column: " . $e->getMessage(),
            );
        }

        // Get bookings from database with room information
        $stmt = $conn->prepare("
    SELECT
        b.*,
        b.additional_guest,
        b.additional_pet,
        COALESCE(b.room_type, r.room_type) as room_type,
        COALESCE(b.room_price, 0) as room_price,
        COALESCE(b.paid_status, 'Unpaid') as paid_status,
        COALESCE(b.cancellation_status, 'None') as cancellation_status,
        COALESCE(b.deposit, 0) as deposit,
        COALESCE(b.deposit_details, '') as deposit_details,
        COALESCE(b.deposit_cash, 0) as deposit_cash,
        COALESCE(b.deposit_g_cash, 0) as deposit_g_cash,
        COALESCE(b.deposit_maya, 0) as deposit_maya,
        COALESCE(b.deposit_instapay, 0) as deposit_instapay,
        COALESCE(b.deposit_online_banking, 0) as deposit_online_banking,
        COALESCE(b.deposit_airbnb, 0) as deposit_airbnb,
        COALESCE(b.downpayment_amount, 0) as downpayment_amount,
        COALESCE(b.downpayment_cash, 0) as downpayment_cash,
        COALESCE(b.downpayment_gcash, 0) as downpayment_gcash,
        COALESCE(b.downpayment_maya, 0) as downpayment_maya,
        COALESCE(b.downpayment_instapay, 0) as downpayment_instapay,
        COALESCE(b.downpayment_online_banking, 0) as downpayment_online_banking,
        COALESCE(b.downpayment_airbnb, 0) as downpayment_airbnb,
        COALESCE(b.total_amount_reservation, 0) as total_amount_reservation,
        COALESCE(b.extend_hours, 0) as extend_hours,
        COALESCE(b.extend_minutes, 0) as extend_minutes,
        COALESCE(b.extend_price, 0) as extend_price,
        COALESCE(b.extension_withdraw, 0) as extension_withdraw,
        COALESCE(b.refund_amount_extension, 0) as refund_amount_extension,
        COALESCE(b.withdrawn_extend_price, 0) as withdrawn_extend_price,
        COALESCE(b.discount_enabled, 0) as discount_enabled,
        COALESCE(b.discount_type, 'regular') as discount_type,
        COALESCE(b.sc_pwd_count, 0) as sc_pwd_count,
        COALESCE(b.discount_amount, 0) as discount_amount,
        r.id as room_db_id,
        r.room_size,
        r.bed_type,
        r.guest_capacity,
        COALESCE(b.missing_items_fees, 0) as missing_items_fees,
        b.missing_items_list,
        COALESCE(b.penalty_amount, 0) as penalty_amount,
        b.penalty_list,
        COALESCE(b.additional_fees_status, 'None') as additional_fees_status,
        COALESCE(b.additional_paid_status, 'None') as additional_paid_status,
        b.additional_fees_items,
        b.additional_food,
        b.additional_items,
        b.reference_no,
        b.payment_status,
        COALESCE(b.payment_status_cash, '') as payment_status_cash,
        COALESCE(b.payment_status_g_cash, '') as payment_status_g_cash,
        COALESCE(b.payment_status_maya, '') as payment_status_maya,
        COALESCE(b.payment_status_instapay, '') as payment_status_instapay,
        COALESCE(b.payment_status_online_banking, '') as payment_status_online_banking,
        COALESCE(b.payment_status_airbnb, '') as payment_status_airbnb,
        b.reference_no_g_cash,
        b.reference_no_maya,
        b.reason_for_stay,
        b.contact_no,
        b.address,
        b.tin_number
    FROM bookings b
    LEFT JOIN rooms r ON b.room_id = r.room_id
    WHERE b.status IN ('Confirming', 'Confirmed', 'Reserved', 'Occupied', 'Available', 'Extended', 'Out of Order')
    ORDER BY b.created_at DESC
");

        $stmt->execute();
        $allBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // One row per room: keep only the single "best" active booking per room.
        // Occupied > Confirmed > Confirming > Reserved, then reservation-aware tie-break.
        $statusPriority = ['Occupied' => 4, 'Confirmed' => 3, 'Confirming' => 2, 'Reserved' => 1, 'Extended' => 0, 'Out of Order' => 0, 'Available' => -1];
        $pickBetterReserved = function (array $current, array $candidate, int $nowTs): array {
            $curRes = strtotime($current['reservation_date'] ?? '') ?: PHP_INT_MAX;
            $candRes = strtotime($candidate['reservation_date'] ?? '') ?: PHP_INT_MAX;

            $curDue = $curRes <= $nowTs;
            $candDue = $candRes <= $nowTs;

            // Prefer the reservation that is due now over future ones.
            if ($curDue && !$candDue) {
                return $current;
            }
            if ($candDue && !$curDue) {
                return $candidate;
            }

            // Both due: keep the one that became active most recently.
            if ($curDue && $candDue) {
                return $curRes >= $candRes ? $current : $candidate;
            }

            // Both future: keep the nearest upcoming reservation.
            return $curRes <= $candRes ? $current : $candidate;
        };

        $nowTs = time();
        $byRoom = [];
        foreach ($allBookings as $b) {
            $rid = $b['room_id'] ?? '';
            if ($rid === '') {
                continue;
            }
            $p = $statusPriority[$b['status'] ?? ''] ?? -2;
            if (!isset($byRoom[$rid])) {
                $byRoom[$rid] = $b;
                continue;
            }

            $existingP = $statusPriority[$byRoom[$rid]['status'] ?? ''] ?? -2;
            if ($p > $existingP) {
                $byRoom[$rid] = $b;
            } elseif ($p === $existingP) {
                if (($b['status'] ?? '') === 'Reserved' && ($byRoom[$rid]['status'] ?? '') === 'Reserved') {
                    $byRoom[$rid] = $pickBetterReserved($byRoom[$rid], $b, $nowTs);
                } elseif (strtotime($b['created_at'] ?? 0) > strtotime($byRoom[$rid]['created_at'] ?? 0)) {
                    $byRoom[$rid] = $b;
                }
            }
        }

        // Fetch all room durations to attach to bookings
        $durationsMap = [];
        try {
            $durStmt = $conn->query("SELECT room_id, duration_hours, price FROM room_durations");
            while ($row = $durStmt->fetch(PDO::FETCH_ASSOC)) {
                // Initialize array for this room if not exists
                if (!isset($durationsMap[$row['room_id']])) {
                    $durationsMap[$row['room_id']] = [];
                }
                // Push standard object structure matching get_rooms.php
                $durationsMap[$row['room_id']][] = [
                    'duration_hours' => $row['duration_hours'],
                    'price' => floatval($row['price'])
                ];
            }
        } catch (Exception $e) {
            // Table might not exist or empty
        }

        // Fetch all rooms to establish one-row-per-room order
        $stmtRooms = $conn->prepare("SELECT * FROM rooms ORDER BY created_at DESC");
        $stmtRooms->execute();
        $roomsList = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

        // Build one row per room in room list order: chosen booking or Available placeholder
        $bookings = [];
        foreach ($roomsList as $room) {
            $rid = $room["room_id"] ?? '';
            if ($rid === '')
                continue;
            if (isset($byRoom[$rid])) {
                $bookings[] = $byRoom[$rid];
            } else {
                $bookings[] = [
                    "id" => $room["id"],
                    "booking_id" => "",
                    "room_id" => $room["room_id"],
                    "room_type" => $room["room_type"],
                    "room_image" => $room["room_image"] ?? "",
                    "room_db_id" => $room["id"],
                    "room_price" => $room["room_price"] ?? 0,
                    "rates" => $durationsMap[$room["id"]] ?? [],
                    "room_size" => $room["room_size"] ?? "",
                    "bed_type" => $room["bed_type"] ?? "",
                    "guest_capacity" => $room["guest_capacity"] ?? "",
                    "status" => ($room["status"] === "Out of Order") ? "Out of Order" : "Available",
                    "cancellation_status" => "None",
                    "guest_name" => "",
                    "breakfast_qty" => 1,
                    "reason_for_stay" => "",
                    "contact_no" => "",
                    "address" => "",
                    "request" => "",
                    "check_in" => "",
                    "duration" => 0,
                    "duration_unit" => "hours",
                    "check_out" => "",
                    "hours" => "",
                    "is_room" => true,
                    "hygiene_kit_used" => 0,
                    "hygiene_kit_price" => 0,
                    "missing_items_fees" => 0,
                    "missing_items_list" => null,
                    "penalty_amount" => 0,
                    "penalty_list" => null,
                    "additional_fees_status" => "None",
                    "additional_paid_status" => "None",
                    "additional_fees_items" => null,
                    "paid_status" => "Unpaid",
                    "deposit" => 0,
                ];
            }
        }

        // Add image path for each booking (use room_image from rooms table)

        foreach ($bookings as &$booking) {
            // Set default values for missing_items_fees and missing_items_list if not present
            if (!isset($booking["missing_items_fees"])) {
                $booking["missing_items_fees"] = 0;
            }
            if (!isset($booking["missing_items_list"])) {
                $booking["missing_items_list"] = null;
            }
            if (!isset($booking["penalty_amount"])) {
                $booking["penalty_amount"] = 0;
            }
            if (!isset($booking["penalty_list"])) {
                $booking["penalty_list"] = null;
            }
            if (!isset($booking["additional_fees_status"])) {
                $booking["additional_fees_status"] = "None";
            }
            if (!isset($booking["additional_fees_items"])) {
                $booking["additional_fees_items"] = null;
            }
            if (!isset($booking["additional_food"])) {
                $booking["additional_food"] = null;
            }
            if (!isset($booking["additional_items"])) {
                $booking["additional_items"] = null;
            }
            if (!isset($booking["breakfast_qty"])) {
                $booking["breakfast_qty"] = 1;
            }
            // Set default values for new fields
            if (!isset($booking["reason_for_stay"])) {
                $booking["reason_for_stay"] = "";
            }
            if (!isset($booking["address"])) {
                $booking["address"] = "";
            }
            if (!isset($booking["additional_paid_status"])) {
                $booking["additional_paid_status"] = "None";
            }
            if (!isset($booking["deposit"])) {
                $booking["deposit"] = 0;
            }
            if (!isset($booking["cancellation_status"])) {
                $booking["cancellation_status"] = "None";
            }

            // Attach rates
            $booking["rates"] = $durationsMap[$booking["room_db_id"]] ?? [];

            if (!empty($booking["room_db_id"])) {
                // Get room image path and details from rooms table
                $room_stmt = $conn->prepare(
                    "SELECT room_image, room_size, bed_type, guest_capacity FROM rooms WHERE id = :id",
                );
                $room_stmt->bindParam(
                    ":id",
                    $booking["room_db_id"],
                    PDO::PARAM_INT,
                );
                $room_stmt->execute();
                $room_data = $room_stmt->fetch(PDO::FETCH_ASSOC);
                if ($room_data) {
                    $booking["room_image"] = $room_data["room_image"]
                        ? $room_data["room_image"]
                        : "";
                    // Use room details from database if available
                    if (!empty($room_data["room_size"])) {
                        $booking["room_size"] = $room_data["room_size"];
                    }
                    if (!empty($room_data["bed_type"])) {
                        $booking["bed_type"] = $room_data["bed_type"];
                    }
                    if (!empty($room_data["guest_capacity"])) {
                        $booking["guest_capacity"] =
                            $room_data["guest_capacity"];
                    }
                } else {
                    $booking["room_image"] = "";
                }
            } else {
                $booking["room_image"] = "";
            }

            // Recalculate total_amount to ensure it's correct (doesn't include additional fees)
            // Always recalculate using computeBookingTotalAmount to ensure consistency
            require_once "report_helpers.php";

            // CRITICAL FIX: For extension withdrawals, subtract the withdrawn_extend_price from extend_price
            // to get the actual remaining extension price that should be included in total_amount
            $extendPriceForCalculation = floatval($booking["extend_price"] ?? 0);
            $withdrawnExtendPrice = floatval($booking["withdrawn_extend_price"] ?? 0);
            $extensionWithdraw = intval($booking["extension_withdraw"] ?? 0);

            // If extension was withdrawn, subtract the withdrawn price from the extend_price
            // This ensures the total_amount reflects only the active (non-withdrawn) extension
            if ($extensionWithdraw && $withdrawnExtendPrice > 0) {
                // The extend_price in DB should already be the remaining price after withdrawal
                // But we log this for debugging
                error_log("=== EXTENSION WITHDRAWAL IN GET_BOOKINGS ===");
                error_log("Booking ID: " . ($booking["booking_id"] ?? 'N/A'));
                error_log("extend_price from DB: " . $extendPriceForCalculation);
                error_log("withdrawn_extend_price: " . $withdrawnExtendPrice);
                error_log("extension_withdraw flag: " . $extensionWithdraw);
                error_log("=== END ===");
            }

            $booking["total_amount"] = computeBookingTotalAmount([
                "room_type" => $booking["room_type"] ?? "",
                "duration" => intval($booking["duration"] ?? 0),
                "duration_unit" => $booking["duration_unit"] ?? "hours",
                "promo" => $booking["promo"] ?? null,
                "breakfast" => $booking["breakfast"] ?? null,
                "hygiene_kit_used" => intval($booking["hygiene_kit_used"] ?? 0),
                "hygiene_kit_price" => floatval(
                    $booking["hygiene_kit_price"] ?? 0,
                ),
                "room_price" => floatval($booking["room_price"] ?? 0),
                // Use the current extend_price (should already be reduced after withdrawal)
                "extend_price" => $extendPriceForCalculation,
                "deposit" => floatval($booking["deposit"] ?? 0),
            ]);

            // Only update bookings table for actual bookings, not Available placeholders
            if (empty($booking["is_room"])) {
                try {
                    $updateTotalStmt = $conn->prepare(
                        "UPDATE bookings SET total_amount = :total_amount WHERE id = :id",
                    );
                    $updateTotalStmt->bindParam(
                        ":total_amount",
                        $booking["total_amount"],
                    );
                    $updateTotalStmt->bindParam(
                        ":id",
                        $booking["id"],
                        PDO::PARAM_INT,
                    );
                    $updateTotalStmt->execute();
                } catch (PDOException $e) {
                    error_log("Failed to update total_amount: " . $e->getMessage());
                }
            }
        }
    }

    // Format check_in and check_out for datetime-local input
    foreach ($bookings as &$booking) {
        if (!empty($booking["check_in"])) {
            $checkIn = new DateTime($booking["check_in"]);
            $booking["check_in"] = $checkIn->format("Y-m-d\TH:i");
        }
        if (!empty($booking["check_out"])) {
            $checkOut = new DateTime($booking["check_out"]);
            $booking["check_out"] = $checkOut->format("Y-m-d H:i");
        }

        // Remove amount from payment_status for display (e.g. "Cash (P1000.00)" -> "Cash")
        if (!empty($booking["payment_status"])) {
            $booking["payment_status"] = preg_replace('/\s*\([^)]*\)/', '', $booking["payment_status"]);
        }
    }

    echo json_encode(["success" => true, "bookings" => $bookings]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>