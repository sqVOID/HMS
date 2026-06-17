<?php

/**
 * Shared helper functions for financial reporting.
 */
// Needed for single-day, payment-split exports (formatPaymentAmountsForExport).
// Some pages include report_helpers.php without including the calculator, which
// would otherwise cause "undefined function" fatals.
require_once __DIR__ . '/payment_amount_calculator.php';
if (!function_exists('sqlMergedDiscountAmount')) {
    /**
     * Merge discount from reports + bookings. update_booking writes discount to bookings;
     * reports may still hold 0, so plain COALESCE(r, b) never reaches bookings.
     */
    function sqlMergedDiscountAmount(string $alias = 'discount_amount'): string
    {
        return 'GREATEST(COALESCE(r.discount_amount, 0), COALESCE(b.discount_amount, 0)) AS ' . $alias;
    }
}

if (!function_exists('ensureReportFinancialColumns')) {
    /**
     * Ensure tables contain the columns needed for financial reporting.
     */
    function ensureReportFinancialColumns(PDO $conn): void
    {
        try {
            // Ensure bookings table has required columns
            $bookingColumns = [
                'total_amount' => "ALTER TABLE bookings ADD COLUMN total_amount DECIMAL(12,2) DEFAULT 0 AFTER hygiene_kit_price",
                'supplier' => "ALTER TABLE bookings ADD COLUMN supplier VARCHAR(255) NULL DEFAULT NULL AFTER referral_name",
                'reference_no' => "ALTER TABLE bookings ADD COLUMN reference_no TEXT NULL DEFAULT NULL AFTER payment_status",
                'change_amount' => "ALTER TABLE bookings ADD COLUMN change_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_status",
                'payment_date_time' => "ALTER TABLE bookings ADD COLUMN payment_date_time DATETIME NULL DEFAULT NULL",
                'extend_additional_guest' => "ALTER TABLE bookings ADD COLUMN extend_additional_guest INT DEFAULT 0 AFTER extend_price",
                'extend_regular_rate' => "ALTER TABLE bookings ADD COLUMN extend_regular_rate DECIMAL(10,2) DEFAULT NULL AFTER extend_price",
                'extend_bundle_rate' => "ALTER TABLE bookings ADD COLUMN extend_bundle_rate DECIMAL(10,2) DEFAULT NULL AFTER extend_regular_rate",
                'extend_bundle_breakfast' => "ALTER TABLE bookings ADD COLUMN extend_bundle_breakfast TEXT DEFAULT NULL AFTER extend_bundle_rate",
                // Extension withdrawal tracking (keep extension visible + persist refund record)
                'extension_withdraw' => "ALTER TABLE bookings ADD COLUMN extension_withdraw TINYINT(1) DEFAULT 0 AFTER extend_bundle_breakfast",
                'refund_amount_extension' => "ALTER TABLE bookings ADD COLUMN refund_amount_extension DECIMAL(10,2) DEFAULT 0.00 AFTER extension_withdraw",
                // Store withdrawn extension separately so new extensions don't "combine" into 48hrs
                'withdrawn_extend_hours' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_hours INT DEFAULT 0 AFTER refund_amount_extension",
                'withdrawn_extend_minutes' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_minutes INT DEFAULT 0 AFTER withdrawn_extend_hours",
                'withdrawn_extend_price' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_price DECIMAL(10,2) DEFAULT 0.00 AFTER withdrawn_extend_minutes",
                'withdrawn_extend_regular_rate' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_regular_rate DECIMAL(10,2) DEFAULT NULL AFTER withdrawn_extend_price",
                'withdrawn_extend_bundle_rate' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_bundle_rate DECIMAL(10,2) DEFAULT NULL AFTER withdrawn_extend_regular_rate",
                'withdrawn_extend_bundle_breakfast' => "ALTER TABLE bookings ADD COLUMN withdrawn_extend_bundle_breakfast TEXT DEFAULT NULL AFTER withdrawn_extend_bundle_rate",
                // Ordered list of extension segments (JSON) so "Withdraw" removes only the last increment
                'extension_stack' => "ALTER TABLE bookings ADD COLUMN extension_stack TEXT NULL DEFAULT NULL AFTER withdrawn_extend_bundle_breakfast"
            ];

            foreach ($bookingColumns as $column => $alterQuery) {
                try {
                    $check = $conn->query("SHOW COLUMNS FROM bookings LIKE '$column'");
                    if ($check->rowCount() === 0) {
                        $conn->exec($alterQuery);
                    }
                } catch (PDOException $e) {
                    // Column might already exist or table might be missing; ignore
                }
            }

            // Ensure reports table columns exist before adding reporting fields
            try {
                $checkReports = $conn->query("SHOW TABLES LIKE 'reports'");
                $hasReports = $checkReports->rowCount() > 0;
            } catch (PDOException $e) {
                $hasReports = false;
            }

            if ($hasReports) {
                $reportColumns = [
                    'promo' => "ALTER TABLE reports ADD COLUMN promo VARCHAR(255) NULL AFTER request",
                    'breakfast' => "ALTER TABLE reports ADD COLUMN breakfast VARCHAR(255) NULL AFTER promo",
                    'additional_guest' => "ALTER TABLE reports ADD COLUMN additional_guest INT DEFAULT 0 AFTER breakfast",
                    'payment_status' => "ALTER TABLE reports ADD COLUMN payment_status VARCHAR(50) NULL AFTER additional_guest",
                    'reference_no' => "ALTER TABLE reports ADD COLUMN reference_no TEXT NULL DEFAULT NULL AFTER payment_status",
                    'referral_name' => "ALTER TABLE reports ADD COLUMN referral_name VARCHAR(255) NULL AFTER reference_no",
                    'supplier' => "ALTER TABLE reports ADD COLUMN supplier VARCHAR(255) NULL AFTER referral_name",
                    'additional' => "ALTER TABLE reports ADD COLUMN additional TEXT NULL AFTER supplier",
                    'additional_food' => "ALTER TABLE reports ADD COLUMN additional_food TEXT NULL AFTER additional",
                    'additional_items' => "ALTER TABLE reports ADD COLUMN additional_items TEXT NULL AFTER additional_food",
                    'paid_status' => "ALTER TABLE reports ADD COLUMN paid_status VARCHAR(50) DEFAULT 'Unpaid' AFTER additional_items",
                    'total_amount' => "ALTER TABLE reports ADD COLUMN total_amount DECIMAL(12,2) DEFAULT 0 AFTER paid_status",
                    'room_price' => "ALTER TABLE reports ADD COLUMN room_price DECIMAL(10,2) DEFAULT 0 AFTER total_amount",
                    'missing_items_list' => "ALTER TABLE reports ADD COLUMN missing_items_list TEXT NULL DEFAULT NULL AFTER room_price",
                    'missing_items_fees' => "ALTER TABLE reports ADD COLUMN missing_items_fees DECIMAL(10,2) DEFAULT 0 AFTER missing_items_list",
                    'penalty_list' => "ALTER TABLE reports ADD COLUMN penalty_list TEXT NULL DEFAULT NULL AFTER missing_items_fees",
                    'penalty_amount' => "ALTER TABLE reports ADD COLUMN penalty_amount DECIMAL(10,2) DEFAULT 0 AFTER penalty_list",
                    'additional_fees_status' => "ALTER TABLE reports ADD COLUMN additional_fees_status VARCHAR(50) DEFAULT 'None' AFTER penalty_amount",
                    'additional_fees_payment_method' => "ALTER TABLE reports ADD COLUMN additional_fees_payment_method VARCHAR(50) NULL DEFAULT NULL AFTER additional_fees_status",
                    'additional_fees_reference_no' => "ALTER TABLE reports ADD COLUMN additional_fees_reference_no VARCHAR(255) NULL DEFAULT NULL AFTER additional_fees_payment_method",
                    'confirmed_at' => "ALTER TABLE reports ADD COLUMN confirmed_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER additional_fees_reference_no",
                    'checked_out_at' => "ALTER TABLE reports ADD COLUMN checked_out_at DATETIME NULL AFTER confirmed_at",
                    'change_amount' => "ALTER TABLE reports ADD COLUMN change_amount DECIMAL(10,2) DEFAULT 0.00 AFTER checked_out_at",
                    'downpayment_amount' => "ALTER TABLE reports ADD COLUMN downpayment_amount DECIMAL(10,2) DEFAULT 0.00 AFTER change_amount",
                    'downpayment_cash' => "ALTER TABLE reports ADD COLUMN downpayment_cash DECIMAL(10,2) DEFAULT 0.00 AFTER downpayment_amount",
                    'downpayment_gcash' => "ALTER TABLE reports ADD COLUMN downpayment_gcash DECIMAL(10,2) DEFAULT 0.00 AFTER downpayment_cash",
                    'downpayment_maya' => "ALTER TABLE reports ADD COLUMN downpayment_maya DECIMAL(10,2) DEFAULT 0.00 AFTER downpayment_gcash",
                    'downpayment_instapay' => "ALTER TABLE reports ADD COLUMN downpayment_instapay DECIMAL(10,2) DEFAULT 0.00 AFTER downpayment_maya",
                    'downpayment_online_banking' => "ALTER TABLE reports ADD COLUMN downpayment_online_banking DECIMAL(10,2) DEFAULT 0.00 AFTER downpayment_instapay",
                    'downpayment_airbnb' => "ALTER TABLE reports ADD COLUMN downpayment_airbnb DECIMAL(10,2) DEFAULT 0.00 AFTER downpayment_online_banking",
                    'extend_hours' => "ALTER TABLE reports ADD COLUMN extend_hours INT DEFAULT 0 AFTER downpayment_maya",
                    'extend_minutes' => "ALTER TABLE reports ADD COLUMN extend_minutes INT DEFAULT 0 AFTER extend_hours",
                    'extend_price' => "ALTER TABLE reports ADD COLUMN extend_price DECIMAL(10,2) DEFAULT 0.00 AFTER extend_minutes",
                    'extend_additional_guest' => "ALTER TABLE reports ADD COLUMN extend_additional_guest INT DEFAULT 0 AFTER extend_price",
                    'extend_regular_rate' => "ALTER TABLE reports ADD COLUMN extend_regular_rate DECIMAL(10,2) DEFAULT NULL AFTER extend_price",
                    'extend_bundle_rate' => "ALTER TABLE reports ADD COLUMN extend_bundle_rate DECIMAL(10,2) DEFAULT NULL AFTER extend_regular_rate",
                    'extend_bundle_breakfast' => "ALTER TABLE reports ADD COLUMN extend_bundle_breakfast TEXT DEFAULT NULL AFTER extend_bundle_rate",
                    'payment_date_time' => "ALTER TABLE reports ADD COLUMN payment_date_time DATETIME NULL DEFAULT NULL AFTER extend_price",
                    // Extension withdrawal tracking (keep extension visible + persist refund record)
                    'extension_withdraw' => "ALTER TABLE reports ADD COLUMN extension_withdraw TINYINT(1) DEFAULT 0 AFTER extend_bundle_breakfast",
                    'refund_amount_extension' => "ALTER TABLE reports ADD COLUMN refund_amount_extension DECIMAL(10,2) DEFAULT 0.00 AFTER extension_withdraw",
                    // Store withdrawn extension separately so new extensions don't "combine" into 48hrs
                    'withdrawn_extend_hours' => "ALTER TABLE reports ADD COLUMN withdrawn_extend_hours INT DEFAULT 0 AFTER refund_amount_extension",
                    'withdrawn_extend_minutes' => "ALTER TABLE reports ADD COLUMN withdrawn_extend_minutes INT DEFAULT 0 AFTER withdrawn_extend_hours",
                    'withdrawn_extend_price' => "ALTER TABLE reports ADD COLUMN withdrawn_extend_price DECIMAL(10,2) DEFAULT 0.00 AFTER withdrawn_extend_minutes",
                    'withdrawn_extend_regular_rate' => "ALTER TABLE reports ADD COLUMN withdrawn_extend_regular_rate DECIMAL(10,2) DEFAULT NULL AFTER withdrawn_extend_price",
                    'withdrawn_extend_bundle_rate' => "ALTER TABLE reports ADD COLUMN withdrawn_extend_bundle_rate DECIMAL(10,2) DEFAULT NULL AFTER withdrawn_extend_regular_rate",
                    'withdrawn_extend_bundle_breakfast' => "ALTER TABLE reports ADD COLUMN withdrawn_extend_bundle_breakfast TEXT DEFAULT NULL AFTER withdrawn_extend_bundle_rate",
                    'extension_stack' => "ALTER TABLE reports ADD COLUMN extension_stack TEXT NULL DEFAULT NULL AFTER withdrawn_extend_bundle_breakfast"
                ];

                foreach ($reportColumns as $column => $alterQuery) {
                    try {
                        $check = $conn->query("SHOW COLUMNS FROM reports LIKE '$column'");
                        if ($check->rowCount() === 0) {
                            $conn->exec($alterQuery);
                        }
                    } catch (PDOException $e) {
                        // Ignore if column already exists
                    }
                }
            }
        } catch (PDOException $e) {
            // Silent fail – schema updates are best-effort
        }

        ensureAdditionalDateHistoryColumns($conn);
    }

    /**
     * Whether an additional date value is empty or MySQL zero-date.
     */
    function isInvalidAdditionalDateValue($value): bool
    {
        if ($value === null) {
            return true;
        }
        $v = trim((string) $value);
        return $v === '' || strcasecmp($v, 'NULL') === 0 || $v === '0000-00-00 00:00:00' || $v === '0000-00-00';
    }

    /**
     * Parse legacy single datetime or JSON array into a clean timestamp list.
     */
    function parseAdditionalDateHistory($raw): array
    {
        if (isInvalidAdditionalDateValue($raw)) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            $dates = [];
            foreach ($decoded as $entry) {
                if (!isInvalidAdditionalDateValue($entry)) {
                    $dates[] = trim((string) $entry);
                }
            }
            return $dates;
        }

        return [trim((string) $raw)];
    }

    /**
     * Append a timestamp to additional date history and return JSON array string.
     */
    function appendAdditionalDateHistory($raw, ?string $timestamp = null): ?string
    {
        $timestamp = $timestamp ?? date('Y-m-d H:i:s');
        $dates = parseAdditionalDateHistory($raw);
        $dates[] = $timestamp;

        return json_encode($dates);
    }

    /**
     * Build the first history entry when additionals are added at booking confirm.
     */
    function buildInitialAdditionalDateHistory(bool $hasValue, ?string $timestamp = null): ?string
    {
        if (!$hasValue) {
            return null;
        }

        $timestamp = $timestamp ?? date('Y-m-d H:i:s');
        return json_encode([$timestamp]);
    }

    /**
     * Ensure additional_*_date columns store JSON history in TEXT columns.
     */
    function ensureAdditionalDateHistoryColumns(PDO $conn): void
    {
        $columns = [
            'additional_items_date',
            'additional_food_date',
            'additional_guest_date',
            'additional_pet_date',
        ];
        $tables = ['bookings', 'reports'];

        foreach ($tables as $table) {
            try {
                $tableCheck = $conn->query("SHOW TABLES LIKE " . $conn->quote($table));
                if ($tableCheck->rowCount() === 0) {
                    continue;
                }
            } catch (PDOException $e) {
                continue;
            }

            foreach ($columns as $columnName) {
                try {
                    $check = $conn->query("SHOW COLUMNS FROM {$table} LIKE " . $conn->quote($columnName));
                    $col = $check->fetch(PDO::FETCH_ASSOC);
                    if (!$col) {
                        continue;
                    }

                    $type = strtolower((string) ($col['Type'] ?? ''));
                    if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
                        $stmt = $conn->query("SELECT id, {$columnName} FROM {$table} WHERE {$columnName} IS NOT NULL");
                        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

                        $conn->exec("ALTER TABLE {$table} MODIFY COLUMN {$columnName} TEXT NULL DEFAULT NULL");

                        foreach ($rows as $row) {
                            $value = $row[$columnName] ?? null;
                            $jsonValue = isInvalidAdditionalDateValue($value)
                                ? null
                                : json_encode([trim((string) $value)]);

                            $updateStmt = $conn->prepare("UPDATE {$table} SET {$columnName} = :value WHERE id = :id");
                            if ($jsonValue === null) {
                                $updateStmt->bindValue(':value', null, PDO::PARAM_NULL);
                            } else {
                                $updateStmt->bindValue(':value', $jsonValue, PDO::PARAM_STR);
                            }
                            $updateStmt->bindValue(':id', $row['id'], PDO::PARAM_INT);
                            $updateStmt->execute();
                        }
                    } else {
                        $conn->exec(
                            "UPDATE {$table} SET {$columnName} = NULL
                             WHERE {$columnName} IN ('0000-00-00 00:00:00', '0000-00-00', '')"
                        );

                        $legacyStmt = $conn->query(
                            "SELECT id, {$columnName} FROM {$table}
                             WHERE {$columnName} IS NOT NULL
                               AND {$columnName} NOT LIKE '[%'"
                        );
                        if ($legacyStmt) {
                            foreach ($legacyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                                $value = $row[$columnName] ?? null;
                                if (isInvalidAdditionalDateValue($value)) {
                                    continue;
                                }
                                $jsonValue = json_encode([trim((string) $value)]);
                                $updateStmt = $conn->prepare("UPDATE {$table} SET {$columnName} = :value WHERE id = :id");
                                $updateStmt->execute([
                                    ':value' => $jsonValue,
                                    ':id' => $row['id'],
                                ]);
                            }
                        }
                    }
                } catch (PDOException $e) {
                    // Ignore per-column migration failures
                }
            }
        }
    }

    /**
     * Convert duration/unit to hour representation.
     */
    function convertDurationToHours($duration, $unit): int
    {
        $duration = intval($duration ?? 0);
        $unit = strtolower($unit ?? 'hours');
        if ($duration <= 0) {
            return 0;
        }

        if ($unit === 'night' || $unit === 'nights') {
            return $duration * 12;
        }

        return $duration;
    }

    /**
     * Extract numeric price from strings formatted like "Label - ₱1,234.00".
     */
    function parseCurrencyFromString(?string $value): float
    {
        if (!$value) {
            return 0.0;
        }

        if (preg_match('/₱\s*([\d,]+(?:\.\d+)?)/u', $value, $matches)) {
            return floatval(str_replace(',', '', $matches[1]));
        }

        if (preg_match('/([\d,]+(?:\.\d+)?)/', $value, $matches)) {
            return floatval(str_replace(',', '', $matches[1]));
        }

        return 0.0;
    }

    /**
     * Extract promo metadata from the stored string.
     */
    function parsePromoSelection(?string $value): array
    {
        if (!$value) {
            return [
                'title' => '',
                'hours' => 0,
                'price' => 0.0
            ];
        }

        $normalized = trim($value);
        if (
            $normalized === '' ||
            stripos($normalized, 'select promo') !== false ||
            strtolower($normalized) === 'none'
        ) {
            return [
                'title' => '',
                'hours' => 0,
                'price' => 0.0
            ];
        }

        $title = $normalized;
        $hours = 0;

        if (preg_match('/^(.*?)\s+(\d+)\s*hrs?/i', $normalized, $matches)) {
            $title = trim($matches[1]);
            $hours = intval($matches[2]);
        }

        $price = parseCurrencyFromString($normalized);

        return [
            'title' => $title,
            'hours' => $hours,
            'price' => $price
        ];
    }

    /**
     * Extract breakfast metadata from selection string.
     */
    function parseBreakfastSelection(?string $value): array
    {
        if (!$value || strtolower(trim($value)) === 'none' || stripos($value, 'select breakfast') !== false) {
            return [
                'name' => '',
                'price' => 0.0
            ];
        }

        $name = trim($value);
        if (strpos($value, ' - ') !== false) {
            [$namePart] = explode(' - ', $value, 2);
            $name = trim($namePart);
        }

        // CRITICAL FIX: If breakfast contains "(Promo)", it's included in the promo price, so price should be 0
        $price = 0.0;
        if (stripos($value, '(Promo)') === false && stripos($value, '(promo)') === false) {
            // Not a promo breakfast, extract the price
            $price = parseCurrencyFromString($value);
        }

        return [
            'name' => $name,
            'price' => $price
        ];
    }

    /**
     * Calculate base room rate based on room type and hours.
     */
    function calculateRoomRate(?string $roomType, int $hours): float
    {
        if (empty($roomType) || $hours <= 0) {
            return 0.0;
        }

        $normalized = strtolower($roomType);

        // Premium 1 pricing
        if (strpos($normalized, 'premium 1') !== false || strpos($normalized, 'premium1') !== false) {
            if ($hours >= 24) {
                return 2900;
            }
            if ($hours >= 12) {
                return 1990;
            }
        }

        // Premium 2 pricing
        if (strpos($normalized, 'premium 2') !== false || strpos($normalized, 'premium2') !== false) {
            if ($hours >= 24) {
                return 2100;
            }
            if ($hours >= 12) {
                return 1500;
            }
        }

        // Deluxe pricing
        if (strpos($normalized, 'deluxe') !== false) {
            if ($hours >= 24) {
                return 1490;
            }
            if ($hours >= 12) {
                return 960;
            }
            if ($hours >= 6) {
                return 800;
            }
            if ($hours >= 3) {
                return 600;
            }
        }

        return 0.0;
    }

    /**
     * Compute the total booking amount from available data.
     */
    function computeBookingTotalAmount(array $data): float
    {
        $roomType = $data['room_type'] ?? '';
        $duration = $data['duration'] ?? 0;
        $durationUnit = $data['duration_unit'] ?? 'hours';
        $promo = $data['promo'] ?? null;
        $breakfast = $data['breakfast'] ?? null;
        // Hygiene-related charges should not be included in base booking total_amount.
        // They are handled as additional fees elsewhere.
        $hygieneUsed = 0;
        $hygienePrice = 0.0;
        $roomPrice = floatval($data['room_price'] ?? 0);
        // Add extension price if applicable
        $extendPrice = floatval($data['extend_price'] ?? 0);
        // Optional deposit (downpayment) that reduces the remaining balance shown on receipts.
        $deposit = isset($data['deposit']) ? floatval($data['deposit']) : 0.0;

        // Guest and pet fees
        $additionalGuest = isset($data['additional_guest']) ? intval($data['additional_guest']) : 0;
        $extendAdditionalGuest = isset($data['extend_additional_guest']) ? intval($data['extend_additional_guest']) : 0;
        $additionalPet = isset($data['additional_pet']) ? intval($data['additional_pet']) : 0;

        $guestFee = ($additionalGuest + $extendAdditionalGuest) * 300.0;
        $petFee = $additionalPet * 500.0;

        $totalHours = convertDurationToHours($duration, $durationUnit);

        $promoMeta = parsePromoSelection($promo);
        $breakfastMeta = parseBreakfastSelection($breakfast);

        $promoPrice = $promoMeta['price'] ?? 0.0;
        $additionalHoursPrice = 0.0;

        // CRITICAL FIX: If promo is selected, use ONLY promo price, not room_price
        // Promo price already includes everything (room + breakfast + hygiene kit)
        if (!empty($promoMeta['title']) && $promoPrice > 0) {
            // Promo booking: use only promo price + extensions
            $total = $promoPrice + $extendPrice;
        } else {
            // Regular booking: calculate room price + breakfast
            // Use provided room_price if available, otherwise calculate it
            if ($roomPrice <= 0) {
                if (!empty($promoMeta['title']) && $promoMeta['hours'] > 0) {
                    $additionalHours = $totalHours;
                    if ($additionalHours > 0) {
                        $additionalHoursPrice = calculateRoomRate($roomType, $additionalHours);
                    }
                } else {
                    $roomPrice = calculateRoomRate($roomType, $totalHours);
                }
            }

            // Exclude hygiene kit from total_amount; it is treated as an additional fee.
            $hygieneTotal = 0.0;

            $total = $promoPrice + $additionalHoursPrice + $roomPrice + ($breakfastMeta['price'] ?? 0.0) + $hygieneTotal + $extendPrice;
        }

        // Include guest and pet fees in the total charge calculation
        $total += $guestFee + $petFee;

        // Subtract any recorded deposit from the total so database totals match the
        // "Total Amount Due" shown on the receipt (room + food - deposit).
        if ($deposit > 0) {
            $total -= $deposit;
        }

        // Never return a negative total.
        return round(max(0, $total), 2);
    }

    /**
     * Resolve supplier value by falling back to referral name.
     */
    function resolveSupplier(?string $supplier, ?string $referral): string
    {
        $supplier = trim((string) ($supplier ?? ''));
        if ($supplier !== '') {
            return $supplier;
        }

        return trim((string) ($referral ?? ''));
    }

    /**
     * Resolve total amount, computing if missing.
     */
    function resolveTotalAmount(array $record): float
    {
        if (isset($record['total_amount']) && floatval($record['total_amount']) > 0) {
            return round(floatval($record['total_amount']), 2);
        }

        return computeBookingTotalAmount($record);
    }

    /**
     * Grand total for export "Overall Amount": booking column + itemized extras, without double-counting
     * when the booking total already equals room_price (+ extend) + the same extras shown in detail columns.
     */
    function overallAmountBookingPlusFees(
        float $bookingTotal,
        float $additionalFees,
        float $roomPrice,
        float $extendPrice = 0.0
    ): float {
        $bookingTotal = max(0.0, $bookingTotal);
        $additionalFees = max(0.0, $additionalFees);
        $roomCore = max(0.0, $roomPrice) + max(0.0, $extendPrice);
        if ($additionalFees > 0.001 && $roomCore > 0.001) {
            $expectedIfExtrasBrokenOutSeparately = $roomCore + $additionalFees;
            if (abs($bookingTotal - $expectedIfExtrasBrokenOutSeparately) < 0.02) {
                return $bookingTotal;
            }
        }
        return $bookingTotal + $additionalFees;
    }

    /**
     * Build a consistent date range.
     */
    function buildDateRange(string $key, ?string $customStart = null, ?string $customEnd = null): array
    {
        $today = new DateTimeImmutable('today');
        $start = $today;
        $end = $today;
        $label = 'Today';

        switch ($key) {
            case 'last_week':
                $start = $today->sub(new DateInterval('P6D'));
                $label = 'Last 7 Days';
                break;
            case 'last_month':
                $start = $today->sub(new DateInterval('P29D'));
                $label = 'Last 30 Days';
                break;
            case 'custom':
                if ($customStart && $customEnd) {
                    try {
                        // Inputs from the UI are typically in d/m/Y format (e.g. 01/03/2026).
                        // PHP's DateTimeImmutable($str) may interpret this as mm/d/Y depending on locale,
                        // which breaks the SQL date range filtering (Jan/Feb show instead of March).
                        $parseCustomDate = function (?string $val): DateTimeImmutable {
                            $val = trim((string) ($val ?? ''));
                            if ($val === '') {
                                return new DateTimeImmutable('today');
                            }

                            // 1) Try d/m/Y (most common in your UI)
                            $dt = DateTimeImmutable::createFromFormat('d/m/Y', $val);
                            if ($dt instanceof DateTimeImmutable) {
                                return $dt->setTime(0, 0, 0);
                            }

                            // 1b) Try d-m-Y (just in case)
                            $dtDash = DateTimeImmutable::createFromFormat('d-m-Y', $val);
                            if ($dtDash instanceof DateTimeImmutable) {
                                return $dtDash->setTime(0, 0, 0);
                            }

                            // 2) Try Y-m-d
                            $dt2 = DateTimeImmutable::createFromFormat('Y-m-d', $val);
                            if ($dt2 instanceof DateTimeImmutable) {
                                return $dt2->setTime(0, 0, 0);
                            }

                            // 3) Fallback to whatever PHP can parse
                            return new DateTimeImmutable($val);
                        };

                        $start = $parseCustomDate($customStart);
                        $end = $parseCustomDate($customEnd);
                        if ($start > $end) {
                            [$start, $end] = [$end, $start];
                        }
                        $label = 'Custom Range';
                        break;
                    } catch (Exception $e) {
                        // Fallback to last week if parsing fails
                    }
                }
                $key = 'last_week';
                $start = $today->sub(new DateInterval('P6D'));
                $label = 'Last 7 Days';
                break;
            default:
                $key = 'today';
                $label = 'Today';
                break;
        }

        return [
            'key' => $key,
            'label' => $label,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d')
        ];
    }

    /**
     * Parse and sum values from additional items/food raw data.
     */
    function parseAdditionalTotal($raw): float
    {
        if (!$raw)
            return 0.0;
        $total = 0.0;
        // Try JSON first
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $it) {
                $qty = floatval($it['quantity'] ?? ($it['qty'] ?? 1));
                $price = floatval($it['price'] ?? 0);
                $total += $qty * $price;
            }
            return $total;
        }
        // Otherwise parse lines like "1 hotdog = ₱120.00" or lines containing ₱ amounts
        $lines = preg_split('/\r?\n/', $raw);
        foreach ($lines as $line) {
            if (preg_match('/₱\s*([0-9,]+\.?[0-9]*)/', $line, $m)) {
                $num = str_replace(',', '', $m[1]);
                $total += floatval($num);
            } else {
                // try to find plain numbers at end
                if (preg_match('/([0-9]+\.?[0-9]*)\s*$/', trim($line), $m2)) {
                    $total += floatval($m2[1]);
                }
            }
        }
        return $total;
    }

    /**
     * Unique payment dates (Y-m-d) within a filter range — same grouping as export_report expanded rows.
     *
     * @return string[] Sorted date keys
     */
    function collectPaymentDatesInRange(array $report, string $startDate, string $endDate): array
    {
        $paymentDatesByDate = [];

        $downpaymentDate = $report['downpayment_date'] ?? null;
        if (!empty($report['payment_date_time'])) {
            foreach (explode('|', (string) $report['payment_date_time']) as $ts) {
                $ts = trim($ts);
                if ($ts === '') {
                    continue;
                }
                try {
                    $dt = new DateTime($ts);
                    $dateStr = $dt->format('Y-m-d');
                    if ($dateStr >= $startDate && $dateStr <= $endDate) {
                        if (!isset($paymentDatesByDate[$dateStr])) {
                            $paymentDatesByDate[$dateStr] = true;
                        }
                    }
                } catch (Exception $e) {
                    // Skip invalid timestamp
                }
            }
        }
        if (empty($paymentDatesByDate) && !empty($downpaymentDate)) {
            try {
                $dt = new DateTime($downpaymentDate);
                $dateStr = $dt->format('Y-m-d');
                if ($dateStr >= $startDate && $dateStr <= $endDate) {
                    $paymentDatesByDate[$dateStr] = true;
                }
            } catch (Exception $e) {
                // Skip invalid timestamp
            }
        }

        $dates = array_keys($paymentDatesByDate);
        sort($dates);

        return $dates;
    }

    /**
     * Fetch revenue records for a given date range.
     */
    function fetchCheckoutRevenueData(PDO $conn, string $startDate, string $endDate, bool $withRecords = true): array
    {
        // Use checked_out_at if available; fall back to check_out to keep legacy data.
        $query = "
            SELECT 
                r.booking_id,
                COALESCE(r.guest_name, b.guest_name) AS guest_name,
                COALESCE(r.room_id, b.room_id) AS room_id,
                COALESCE(r.room_type, b.room_type) AS room_type,
                COALESCE(r.payment_status, b.payment_status) AS payment_status,
                COALESCE(r.payment_status_cash, b.payment_status_cash) AS payment_status_cash,
                COALESCE(r.payment_status_g_cash, b.payment_status_g_cash) AS payment_status_g_cash,
                COALESCE(r.payment_status_maya, b.payment_status_maya) AS payment_status_maya,
                COALESCE(r.payment_status_instapay, b.payment_status_instapay) AS payment_status_instapay,
                COALESCE(r.payment_status_online_banking, b.payment_status_online_banking) AS payment_status_online_banking,
                COALESCE(r.payment_status_airbnb, b.payment_status_airbnb) AS payment_status_airbnb,
                COALESCE(r.reference_no, b.reference_no) AS reference_no,
                COALESCE(r.reference_no_g_cash, b.reference_no_g_cash) AS reference_no_g_cash,
                COALESCE(r.reference_no_maya, b.reference_no_maya) AS reference_no_maya,
                COALESCE(r.reference_no_instapay, b.reference_no_instapay) AS reference_no_instapay,
                COALESCE(r.reference_no_online_banking, b.reference_no_online_banking) AS reference_no_online_banking,
                COALESCE(r.reference_no_airbnb, b.reference_no_airbnb) AS reference_no_airbnb,
                COALESCE(r.referral_name, b.referral_name) AS referral_name,
                COALESCE(r.supplier, b.supplier) AS supplier,
                COALESCE(r.booking_type, b.booking_type) AS booking_type,
                COALESCE(b.status, r.status) AS status,
                GREATEST(COALESCE(r.total_amount, 0), COALESCE(b.total_amount, 0)) AS total_amount,
                COALESCE(r.check_in, b.check_in) AS check_in,
                COALESCE(r.check_out, b.check_out) AS check_out,
                r.checked_out_at AS checked_out_at,
                COALESCE(r.promo, b.promo) AS promo,
                COALESCE(r.breakfast, b.breakfast) AS breakfast,
                COALESCE(r.duration, b.duration) AS duration,
                COALESCE(r.duration_unit, b.duration_unit) AS duration_unit,
                COALESCE(r.hygiene_kit_used, b.hygiene_kit_used) AS hygiene_kit_used,
                COALESCE(r.hygiene_kit_price, b.hygiene_kit_price) AS hygiene_kit_price,
                COALESCE(r.room_price, b.room_price) AS room_price,
                GREATEST(COALESCE(r.discount_amount, 0), COALESCE(b.discount_amount, 0)) AS discount_amount,
                COALESCE(r.paid_status, b.paid_status) AS paid_status,
                COALESCE(r.additional_items, b.additional_items) AS additional_items,
                COALESCE(r.additional_food, b.additional_food) AS additional_food,
                GREATEST(COALESCE(r.additional_guest, 0), COALESCE(b.additional_guest, 0)) AS additional_guest,
                GREATEST(COALESCE(r.additional_pet, 0), COALESCE(b.additional_pet, 0)) AS additional_pet,
                COALESCE(r.missing_items_fees, b.missing_items_fees, 0) AS missing_items_fees,
                COALESCE(r.change_amount, b.change_amount, 0) AS change_amount,
                COALESCE(r.transfer_refund_amount, b.transfer_refund_amount, 0) AS transfer_refund_amount,
                COALESCE(r.deposit_details, b.deposit_details) AS deposit_details,
                COALESCE(r.deposit_cash, b.deposit_cash, 0) AS deposit_cash,
                COALESCE(r.deposit_g_cash, b.deposit_g_cash, 0) AS deposit_g_cash,
                COALESCE(r.deposit_maya, b.deposit_maya, 0) AS deposit_maya,
                COALESCE(r.deposit_gcash_ref, b.deposit_gcash_ref) AS deposit_gcash_ref,
                COALESCE(r.deposit_maya_ref, b.deposit_maya_ref) AS deposit_maya_ref,
                COALESCE(r.deposit_instapay_ref, b.deposit_instapay_ref) AS deposit_instapay_ref,
                COALESCE(r.deposit_online_banking_ref, b.deposit_online_banking_ref) AS deposit_online_banking_ref,
                COALESCE(r.deposit_airbnb_ref, b.deposit_airbnb_ref) AS deposit_airbnb_ref,
                COALESCE(r.penalty_amount, b.penalty_amount, 0) AS penalty_amount,
                COALESCE(r.penalty_list, b.penalty_list) AS penalty_list,
                COALESCE(r.downpayment_amount, b.downpayment_amount, 0) AS downpayment_amount,
                COALESCE(r.downpayment_cash, b.downpayment_cash, 0) AS downpayment_cash,
                COALESCE(r.downpayment_gcash, b.downpayment_gcash, 0) AS downpayment_gcash,
                COALESCE(r.downpayment_maya, b.downpayment_maya, 0) AS downpayment_maya,
                COALESCE(r.downpayment_instapay, b.downpayment_instapay, 0) AS downpayment_instapay,
                COALESCE(r.downpayment_online_banking, b.downpayment_online_banking, 0) AS downpayment_online_banking,
                COALESCE(r.downpayment_airbnb, b.downpayment_airbnb, 0) AS downpayment_airbnb,
                COALESCE(r.downpayment_date, b.downpayment_date) AS downpayment_date,
                COALESCE(r.downpayment_gcash_ref, b.downpayment_gcash_ref) AS downpayment_gcash_ref,
                COALESCE(r.downpayment_maya_ref, b.downpayment_maya_ref) AS downpayment_maya_ref,
                COALESCE(r.downpayment_instapay_ref, b.downpayment_instapay_ref) AS downpayment_instapay_ref,
                COALESCE(r.downpayment_online_banking_ref, b.downpayment_online_banking_ref) AS downpayment_online_banking_ref,
                COALESCE(r.downpayment_airbnb_ref, b.downpayment_airbnb_ref) AS downpayment_airbnb_ref,
                COALESCE(r.total_amount_reservation, b.total_amount_reservation, 0) AS total_amount_reservation,
                COALESCE(b.extend_hours, r.extend_hours, 0) AS extend_hours,
                COALESCE(b.extend_minutes, r.extend_minutes, 0) AS extend_minutes,
                COALESCE(b.extend_price, r.extend_price, 0) AS extend_price,
                GREATEST(COALESCE(r.extend_additional_guest, 0), COALESCE(b.extend_additional_guest, 0)) AS extend_additional_guest,
                COALESCE(b.extend_regular_rate, r.extend_regular_rate, 0) AS extend_regular_rate,
                COALESCE(b.extend_bundle_rate, r.extend_bundle_rate, 0) AS extend_bundle_rate,
                COALESCE(b.payment_date_time, r.payment_date_time) AS payment_date_time,
                COALESCE(r.payment_amount_cash_history, b.payment_amount_cash_history) AS payment_amount_cash_history,
                COALESCE(r.payment_amount_g_cash_history, b.payment_amount_g_cash_history) AS payment_amount_g_cash_history,
                COALESCE(r.payment_amount_maya_history, b.payment_amount_maya_history) AS payment_amount_maya_history,
                COALESCE(r.payment_amount_instapay_history, b.payment_amount_instapay_history) AS payment_amount_instapay_history,
                COALESCE(r.payment_amount_online_banking_history, b.payment_amount_online_banking_history) AS payment_amount_online_banking_history,
                COALESCE(r.payment_amount_airbnb_history, b.payment_amount_airbnb_history) AS payment_amount_airbnb_history,
                COALESCE(r.deposit_instapay, b.deposit_instapay, 0) AS deposit_instapay,
                COALESCE(r.deposit_online_banking, b.deposit_online_banking, 0) AS deposit_online_banking,
                COALESCE(r.deposit_airbnb, b.deposit_airbnb, 0) AS deposit_airbnb
            FROM reports r
            LEFT JOIN bookings b ON r.booking_id COLLATE utf8mb4_unicode_ci = b.booking_id COLLATE utf8mb4_unicode_ci
            WHERE COALESCE(b.status, r.status) COLLATE utf8mb4_unicode_ci NOT IN ('Canceled')
              AND (COALESCE(b.payment_date_time, r.payment_date_time) IS NOT NULL OR COALESCE(r.downpayment_date, b.downpayment_date) IS NOT NULL)
            ORDER BY COALESCE(b.payment_date_time, r.payment_date_time) DESC, r.booking_id DESC
        ";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter rows to include only those with payment dates or downpayment dates in the specified range
        $rows = [];
        foreach ($allRows as $row) {
            $hasPaymentInRange = false;

            // Check downpayment_date first
            $downpaymentDate = $row['downpayment_date'] ?? null;
            if (!empty($downpaymentDate)) {
                try {
                    $dt = new DateTime($downpaymentDate);
                    $paymentDate = $dt->format('Y-m-d');
                    if ($paymentDate >= $startDate && $paymentDate <= $endDate) {
                        $hasPaymentInRange = true;
                    }
                } catch (Exception $e) {
                    // Skip invalid timestamp
                }
            }

            // Check payment_date_time timestamps
            if (!$hasPaymentInRange) {
                $paymentDateTime = $row['payment_date_time'] ?? null;
                if (!empty($paymentDateTime)) {
                    $timestamps = explode('|', $paymentDateTime);
                    foreach ($timestamps as $timestamp) {
                        $timestamp = trim($timestamp);
                        if (!empty($timestamp)) {
                            try {
                                $dt = new DateTime($timestamp);
                                $paymentDate = $dt->format('Y-m-d');
                                if ($paymentDate >= $startDate && $paymentDate <= $endDate) {
                                    $hasPaymentInRange = true;
                                    break;
                                }
                            } catch (Exception $e) {
                                // Skip invalid timestamps
                            }
                        }
                    }
                }
            }

            if ($hasPaymentInRange) {
                $rows[] = $row;
            }
        }

        $total = 0.0;
        $records = [];

        foreach ($rows as $row) {
            // One revenue row per payment date (matches export_report detailed / Overall Amount rows).
            $paymentDatesInRange = collectPaymentDatesInRange($row, $startDate, $endDate);
            if (empty($paymentDatesInRange)) {
                continue;
            }

            foreach ($paymentDatesInRange as $paymentDateStr) {
                // Per-day payment slice — same as export_report expanded rows (e.g. ₱1,400 + ₱1,400, not ₱2,800).
                $finalAmount = calculatePaymentAmountInDateRange($row, $paymentDateStr, $paymentDateStr);
                $total += $finalAmount;

                if (!$withRecords) {
                    continue;
                }

                // Calculate Payment Based Total (The "Source of Truth" for Revenue)
                $payCash = parseCurrencyFromString($row['payment_status_cash'] ?? '');
                $payGcash = parseCurrencyFromString($row['payment_status_g_cash'] ?? '');
                $payMaya = parseCurrencyFromString($row['payment_status_maya'] ?? '');
                $checkoutPaid = $payCash + $payGcash + $payMaya;

                // For Check-In (Confirmed) status, if checkout payments are empty, use deposit breakdown
                if ($checkoutPaid <= 0) {
                    // CRITICAL FIX: Use max() to avoid double-counting reservation downpayments
                    // which are already included in the deposit_* fields.
                    $payCash = max(floatval($row['deposit_cash'] ?? 0), floatval($row['downpayment_cash'] ?? 0));
                    $payGcash = max(floatval($row['deposit_g_cash'] ?? 0), floatval($row['downpayment_gcash'] ?? 0));
                    $payMaya = max(floatval($row['deposit_maya'] ?? 0), floatval($row['downpayment_maya'] ?? 0));
                    $payInstapay = floatval($row['downpayment_instapay'] ?? 0);
                    $payOnlineBanking = floatval($row['downpayment_online_banking'] ?? 0);
                    $payAirbnb = floatval($row['downpayment_airbnb'] ?? 0);
                    $checkoutPaid = $payCash + $payGcash + $payMaya + $payInstapay + $payOnlineBanking + $payAirbnb;
                }

                $changeAmt = floatval($row['change_amount'] ?? 0);
                $transferRefundAmt = floatval($row['transfer_refund_amount'] ?? 0);
                $netCheckoutPaid = max(0, $checkoutPaid - $changeAmt - $transferRefundAmt);

                $depositDetails = $row['deposit_details'] ?? '';
                $depositAmt = 0.0;
                if ($depositDetails && preg_match('/([0-9,]+\.?[0-9]*)/', $depositDetails, $m)) {
                    $depositAmt = floatval(str_replace(',', '', $m[1]));
                }

                $downpaymentAmt = floatval($row['downpayment_amount'] ?? 0);

                // CRITICAL FIX: Use max() instead of addition to correctly calculate total money paid
                // when the deposit already includes the reservation downpayment.
                $totalMoneyPaid = $netCheckoutPaid + max($depositAmt, $downpaymentAmt);

                // Calculate the booking amount to match Booking checkout totals.
                // Penalties/missing items are shown as separate columns and should not be
                // deducted from the booking total itself.
                if ($netCheckoutPaid > 0) {
                    $bookingAmount = max(0, $netCheckoutPaid);
                    if (strcasecmp(trim((string) ($row['booking_type'] ?? '')), 'Reservation') === 0) {
                        // Keep reservation totals consistent with export detail rows:
                        // full collected = reservation fee + checkout, capped by room+extension.
                        $fullCollected = getReservationFullCollectedAmount($row);
                        if ($fullCollected > 0) {
                            $bookingAmount = $fullCollected;
                        } elseif ($downpaymentAmt > 0) {
                            // CRITICAL FIX: Use max() to avoid double-counting
                            $sum = max($downpaymentAmt, $netCheckoutPaid);
                            $bookingAmount = max($bookingAmount, $sum);
                        }
                    }
                } elseif (strcasecmp(trim((string) ($row['booking_type'] ?? '')), 'Reservation') === 0) {
                    $collected = getReservationFullCollectedAmount($row);
                    $bookingAmount = $collected > 0 ? $collected : max(0, resolveTotalAmount($row));
                } elseif ($depositAmt > 0 || $downpaymentAmt > 0) {
                    // CRITICAL FIX: Use max() to solve 1660 vs 1160 display issue
                    $bookingAmount = max(0, max($depositAmt, $downpaymentAmt));
                } else {
                    // Fallback to theoretical calculation
                    $baseAmount = resolveTotalAmount($row);
                    $bookingAmount = max(0, $baseAmount);
                }

                if ($withRecords) {
                    // Determine payment method from payment columns
                    // CRITICAL FIX: Use payment history columns for accurate payment method detection
                    $paymentMethods = [];

                    // Get payment amounts from history columns (same as export files)
                    $cashHistoryArr = !empty($row['payment_amount_cash_history'])
                        ? explode('|', (string) $row['payment_amount_cash_history'])
                        : [];
                    $gcashHistoryArr = !empty($row['payment_amount_g_cash_history'])
                        ? explode('|', (string) $row['payment_amount_g_cash_history'])
                        : [];
                    $mayaHistoryArr = !empty($row['payment_amount_maya_history'])
                        ? explode('|', (string) $row['payment_amount_maya_history'])
                        : [];
                    $instapayHistoryArr = !empty($row['payment_amount_instapay_history'])
                        ? explode('|', (string) $row['payment_amount_instapay_history'])
                        : [];
                    $onlineBankingHistoryArr = !empty($row['payment_amount_online_banking_history'])
                        ? explode('|', (string) $row['payment_amount_online_banking_history'])
                        : [];
                    $airbnbHistoryArr = !empty($row['payment_amount_airbnb_history'])
                        ? explode('|', (string) $row['payment_amount_airbnb_history'])
                        : [];

                    $totalCash = 0;
                    $totalGcash = 0;
                    $totalMaya = 0;
                    $totalInstapay = 0;
                    $totalOnlineBanking = 0;
                    $totalAirbnb = 0;

                    foreach ($cashHistoryArr as $amt)
                        $totalCash += floatval($amt);
                    foreach ($gcashHistoryArr as $amt)
                        $totalGcash += floatval($amt);
                    foreach ($mayaHistoryArr as $amt)
                        $totalMaya += floatval($amt);
                    foreach ($instapayHistoryArr as $amt)
                        $totalInstapay += floatval($amt);
                    foreach ($onlineBankingHistoryArr as $amt)
                        $totalOnlineBanking += floatval($amt);
                    foreach ($airbnbHistoryArr as $amt)
                        $totalAirbnb += floatval($amt);

                    // CRITICAL FIX: Deduct downpayment/reservation amounts from totals
                    // Downpayment is not an additional charge, it's a deposit toward the final price
                    // Only deduct if there are additional charges (pet, guest, food, items)
                    $hasAdditionalCharges = false;

                    // Check for additional pet
                    $additionalPet = floatval($row['additional_pet'] ?? 0);
                    if ($additionalPet > 0) {
                        $hasAdditionalCharges = true;
                    }

                    // Check for additional guest
                    $additionalGuest = floatval($row['additional_guest'] ?? 0) + floatval($row['extend_additional_guest'] ?? 0);
                    if ($additionalGuest > 0) {
                        $hasAdditionalCharges = true;
                    }

                    // Check for additional food
                    $additionalFood = $row['additional_food'] ?? '';
                    if (!empty($additionalFood) && trim($additionalFood) !== '') {
                        $hasAdditionalCharges = true;
                    }

                    // Check for additional items
                    $additionalItems = $row['additional_items'] ?? '';
                    if (!empty($additionalItems) && trim($additionalItems) !== '') {
                        $hasAdditionalCharges = true;
                    }

                    // Only deduct downpayment if there are additional charges
                    if ($hasAdditionalCharges) {
                        $downpaymentCash = floatval($row['downpayment_cash'] ?? 0);
                        $downpaymentGcash = floatval($row['downpayment_gcash'] ?? 0);
                        $downpaymentMaya = floatval($row['downpayment_maya'] ?? 0);
                        $downpaymentInstapay = floatval($row['downpayment_instapay'] ?? 0);
                        $downpaymentOnlineBanking = floatval($row['downpayment_online_banking'] ?? 0);
                        $downpaymentAirbnb = floatval($row['downpayment_airbnb'] ?? 0);

                        $totalCash = max(0, $totalCash - $downpaymentCash);
                        $totalGcash = max(0, $totalGcash - $downpaymentGcash);
                        $totalMaya = max(0, $totalMaya - $downpaymentMaya);
                        $totalInstapay = max(0, $totalInstapay - $downpaymentInstapay);
                        $totalOnlineBanking = max(0, $totalOnlineBanking - $downpaymentOnlineBanking);
                        $totalAirbnb = max(0, $totalAirbnb - $downpaymentAirbnb);
                    }

                    // Build payment methods array based on actual amounts
                    if ($totalCash > 0) {
                        $paymentMethods[] = 'Cash';
                    }
                    if ($totalGcash > 0) {
                        $paymentMethods[] = 'G-Cash';
                    }
                    if ($totalMaya > 0) {
                        $paymentMethods[] = 'Maya';
                    }
                    if ($totalInstapay > 0) {
                        $paymentMethods[] = 'Instapay';
                    }
                    if ($totalOnlineBanking > 0) {
                        $paymentMethods[] = 'Online Banking';
                    }
                    if ($totalAirbnb > 0) {
                        $paymentMethods[] = 'Airbnb';
                    }

                    // Fallback: if no payment history, use the old logic
                    if (empty($paymentMethods)) {
                        // Check checkout payment columns
                        $checkoutCash = parseCurrencyFromString($row['payment_status_cash'] ?? '');
                        $checkoutGcash = parseCurrencyFromString($row['payment_status_g_cash'] ?? '');
                        $checkoutMaya = parseCurrencyFromString($row['payment_status_maya'] ?? '');
                        $checkoutInstapay = parseCurrencyFromString($row['payment_status_instapay'] ?? '');
                        $checkoutOnlineBanking = parseCurrencyFromString($row['payment_status_online_banking'] ?? '');
                        $checkoutAirbnb = parseCurrencyFromString($row['payment_status_airbnb'] ?? '');

                        // Check deposit breakdown columns (for Check-In status)
                        $depositCash = floatval($row['deposit_cash'] ?? 0);
                        $depositGcash = floatval($row['deposit_g_cash'] ?? 0);
                        $depositMaya = floatval($row['deposit_maya'] ?? 0);

                        // Check downpayment columns
                        $downCash = floatval($row['downpayment_cash'] ?? 0);
                        $downGcash = floatval($row['downpayment_gcash'] ?? 0);
                        $downMaya = floatval($row['downpayment_maya'] ?? 0);
                        $downInstapay = floatval($row['downpayment_instapay'] ?? 0);
                        $downOnlineBanking = floatval($row['downpayment_online_banking'] ?? 0);
                        $downAirbnb = floatval($row['downpayment_airbnb'] ?? 0);

                        // Combine all payment sources
                        $totalCash = $checkoutCash + $depositCash + $downCash;
                        $totalGcash = $checkoutGcash + $depositGcash + $downGcash;
                        $totalMaya = $checkoutMaya + $depositMaya + $downMaya;
                        $totalInstapay = $checkoutInstapay + $downInstapay;
                        $totalOnlineBanking = $checkoutOnlineBanking + $downOnlineBanking;
                        $totalAirbnb = $checkoutAirbnb + $downAirbnb;

                        // Build payment method string
                        $cashLikeLabels = [];
                        $cashStatusRaw = (string) ($row['payment_status_cash'] ?? '');
                        if (stripos($cashStatusRaw, 'Instapay') !== false) {
                            $cashLikeLabels[] = 'Instapay';
                        }
                        if (stripos($cashStatusRaw, 'Online Banking') !== false) {
                            $cashLikeLabels[] = 'Online Banking';
                        }
                        if (stripos($cashStatusRaw, 'Airbnb') !== false) {
                            $cashLikeLabels[] = 'Airbnb';
                        }
                        if (stripos($cashStatusRaw, 'Cash') !== false) {
                            $cashLikeLabels[] = 'Cash';
                        }
                        if ($totalCash > 0) {
                            if (!empty($cashLikeLabels)) {
                                foreach ($cashLikeLabels as $label) {
                                    if (!in_array($label, $paymentMethods, true)) {
                                        $paymentMethods[] = $label;
                                    }
                                }
                            } else {
                                $hasDedicatedCashLikeMethod = ($totalInstapay > 0 || $totalOnlineBanking > 0 || $totalAirbnb > 0);
                                if (!$hasDedicatedCashLikeMethod) {
                                    $paymentMethods[] = 'Cash';
                                }
                            }
                        }
                        if ($totalGcash > 0) {
                            $paymentMethods[] = 'G-Cash';
                        }
                        if ($totalMaya > 0) {
                            $paymentMethods[] = 'Maya';
                        }
                        if ($totalInstapay > 0) {
                            $paymentMethods[] = 'Instapay';
                        }
                        if ($totalOnlineBanking > 0) {
                            $paymentMethods[] = 'Online Banking';
                        }
                        if ($totalAirbnb > 0) {
                            $paymentMethods[] = 'Airbnb';
                        }
                    }

                    $paymentStatus = !empty($paymentMethods) ? implode(', ', $paymentMethods) : ($row['payment_status'] ?? '');

                    // Apply reference number fallback logic (same as export_report.php)
                    // Only include references for payment methods that were actually used
                    $refGcash = null;
                    $refMaya = null;
                    $refInstapay = null;
                    $refOnlineBanking = null;
                    $refAirbnb = null;

                    // Check if each payment method was used, then get its reference
                    if (in_array('G-Cash', $paymentMethods)) {
                        $refGcash = $row['reference_no_g_cash'] ?? null;
                        // If not found, fallback to deposit refs (for legacy data or reservation deposits)
                        if (empty($refGcash) || $refGcash === '') {
                            $refGcash = $row['deposit_gcash_ref'] ?? ($row['downpayment_gcash_ref'] ?? null);
                        }
                    }

                    if (in_array('Maya', $paymentMethods)) {
                        $refMaya = $row['reference_no_maya'] ?? null;
                        if (empty($refMaya) || $refMaya === '') {
                            $refMaya = $row['deposit_maya_ref'] ?? ($row['downpayment_maya_ref'] ?? null);
                        }
                    }

                    if (in_array('Instapay', $paymentMethods)) {
                        $refInstapay = $row['reference_no_instapay'] ?? null;
                        if (empty($refInstapay) || $refInstapay === '') {
                            $refInstapay = $row['deposit_instapay_ref'] ?? ($row['downpayment_instapay_ref'] ?? null);
                        }
                    }

                    if (in_array('Online Banking', $paymentMethods)) {
                        $refOnlineBanking = $row['reference_no_online_banking'] ?? null;
                        if (empty($refOnlineBanking) || $refOnlineBanking === '') {
                            $refOnlineBanking = $row['deposit_online_banking_ref'] ?? ($row['downpayment_online_banking_ref'] ?? null);
                        }
                    }

                    if (in_array('Airbnb', $paymentMethods)) {
                        $refAirbnb = $row['reference_no_airbnb'] ?? null;
                        if (empty($refAirbnb) || $refAirbnb === '') {
                            $refAirbnb = $row['deposit_airbnb_ref'] ?? ($row['downpayment_airbnb_ref'] ?? null);
                        }
                    }

                    $records[] = [
                        'booking_id' => $row['booking_id'] ?? '',
                        'guest_name' => $row['guest_name'] ?? '',
                        'room_id' => $row['room_id'] ?? '',
                        'room_type' => $row['room_type'] ?? '',
                        'payment_status' => $paymentStatus,
                        'reference_no' => $row['reference_no'] ?? '',
                        'reference_no_g_cash' => $refGcash,
                        'reference_no_maya' => $refMaya,
                        'reference_no_instapay' => $refInstapay,
                        'reference_no_online_banking' => $refOnlineBanking,
                        'reference_no_airbnb' => $refAirbnb,
                        'deposit_gcash_ref' => $row['deposit_gcash_ref'] ?? null,
                        'deposit_maya_ref' => $row['deposit_maya_ref'] ?? null,
                        'deposit_instapay_ref' => $row['deposit_instapay_ref'] ?? null,
                        'deposit_online_banking_ref' => $row['deposit_online_banking_ref'] ?? null,
                        'deposit_airbnb_ref' => $row['deposit_airbnb_ref'] ?? null,
                        'downpayment_gcash_ref' => $row['downpayment_gcash_ref'] ?? null,
                        'downpayment_maya_ref' => $row['downpayment_maya_ref'] ?? null,
                        'downpayment_instapay_ref' => $row['downpayment_instapay_ref'] ?? null,
                        'downpayment_online_banking_ref' => $row['downpayment_online_banking_ref'] ?? null,
                        'downpayment_airbnb_ref' => $row['downpayment_airbnb_ref'] ?? null,
                        'supplier' => resolveSupplier($row['supplier'] ?? '', $row['referral_name'] ?? ''),
                        'status' => formatDetailedReportStatusDisplay($row['status'] ?? ''),
                        'check_in' => $row['check_in'] ?? '',
                        'check_out' => $row['check_out'] ?? '',
                        'checked_out_at' => $row['checked_out_at'] ?? null,
                        'check_out_display' => formatDetailedReportCheckOutDisplay($row['checked_out_at'] ?? null, $row['check_out'] ?? null),
                        'payment_date_time' => $row['payment_date_time'] ?? null,
                        'downpayment_date' => $row['downpayment_date'] ?? null,
                        '_payment_date_for_row' => $paymentDateStr,
                        'payment_date_display' => formatDetailedReportPaymentDateDisplay($paymentDateStr),
                        'total_amount' => round($finalAmount, 2),
                        'total_amount_display' => formatDetailedReportPaymentAmountDisplay(round($finalAmount, 2))
                    ];
                }
            }
        }

        return [
            'total' => round($total, 2),
            'records' => $withRecords ? $records : []
        ];
    }

    /**
     * Aggregates additional items (food or items) by name and formats them for display.
     * Handles both JSON strings and plain text formats.
     */
    function aggregateAdditionalItems(?string $raw): string
    {
        if (!$raw || trim($raw) === '') {
            return '—';
        }

        $aggregated = [];

        // Helper to normalize name for grouping key
        $normalizeKey = function ($name) {
            $name = trim($name);
            $name = str_replace('_', ' ', $name);
            $name = preg_replace('/\s+/', ' ', $name);
            return strtoupper($name);
        };

        // Helper to format name for display
        $formatName = function ($name) {
            $name = trim($name);
            $name = str_replace('_', ' ', $name);
            // Title Case
            return ucwords(strtolower($name));
        };

        // Try JSON first
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                // Handle different possible key names from JS
                $name = $item['selectedItem'] ?? ($item['name'] ?? ($item['itemName'] ?? 'Item'));
                $qty = floatval($item['quantity'] ?? ($item['qty'] ?? 1));
                $price = floatval($item['price'] ?? 0);

                // Filter invalid/placeholders ("1 Item = 0.00" or empty name)
                if ($qty == 1 && $price == 0 && (empty($name) || stripos($name, 'Item') !== false || stripos($name, 'Food') !== false)) {
                    continue;
                }

                // Skip if name is "Select Food" or "Select Item"
                if (stripos($name, 'Select') !== false)
                    continue;

                $key = $normalizeKey($name);
                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'name' => $formatName($name),
                        'qty' => 0,
                        'price' => 0.0
                    ];
                }
                $aggregated[$key]['qty'] += $qty;
                $aggregated[$key]['price'] += $price;
            }
        } else {
            // Text format
            $lines = preg_split('/\r?\n/', $raw);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line))
                    continue;

                // Match: 1 Longganisa = P150.00
                if (preg_match('/^(\d+)\s+(.*?)\s*[=-]\s*[₱P]?([\d,]+\.?\d*)/u', $line, $m)) {
                    $qty = intval($m[1]);
                    $name = trim($m[2]);
                    $price = floatval(str_replace(',', '', $m[3]));

                    // Filter invalid/placeholders
                    if ($qty == 1 && $price == 0 && (empty($name) || stripos($name, 'Item') !== false)) {
                        continue;
                    }

                    $key = $normalizeKey($name);
                    if (!isset($aggregated[$key])) {
                        $aggregated[$key] = [
                            'name' => $formatName($name),
                            'qty' => 0,
                            'price' => 0.0
                        ];
                    }
                    $aggregated[$key]['qty'] += $qty;
                    $aggregated[$key]['price'] += $price;
                }
            }
        }

        if (empty($aggregated)) {
            return '—';
        }

        $lines = [];
        foreach ($aggregated as $item) {
            $lines[] = $item['qty'] . ' ' . $item['name'] . ' = ₱' . number_format($item['price'], 2);
        }

        return implode('<br>', $lines);
    }

    /**
     * Fetch non-refund downpayments from canceled reservations that never checked in.
     */
    function fetchNonRefundDownpayments(PDO $conn, string $startDate, string $endDate): array
    {
        $query = "
            SELECT 
                r.booking_id,
                r.room_id,
                r.guest_name,
                r.guest_type,
                r.canceled_at,
                COALESCE(r.downpayment_amount, b.downpayment_amount, 0) as downpayment_amount,
                COALESCE(r.deposit_cash, b.deposit_cash, 0) as deposit_cash,
                COALESCE(r.deposit_g_cash, b.deposit_g_cash, 0) as deposit_g_cash,
                COALESCE(r.deposit_maya, b.deposit_maya, 0) as deposit_maya,
                COALESCE(r.deposit_instapay, b.deposit_instapay, 0) as deposit_instapay,
                COALESCE(r.deposit_online_banking, b.deposit_online_banking, 0) as deposit_online_banking,
                COALESCE(r.deposit_airbnb, b.deposit_airbnb, 0) as deposit_airbnb,
                COALESCE(r.downpayment_cash, b.downpayment_cash, 0) as downpayment_cash,
                COALESCE(r.downpayment_gcash, b.downpayment_gcash, 0) as downpayment_gcash,
                COALESCE(r.downpayment_maya, b.downpayment_maya, 0) as downpayment_maya,
                COALESCE(r.downpayment_instapay, b.downpayment_instapay, 0) as downpayment_instapay,
                COALESCE(r.downpayment_online_banking, b.downpayment_online_banking, 0) as downpayment_online_banking,
                COALESCE(r.downpayment_airbnb, b.downpayment_airbnb, 0) as downpayment_airbnb,
                COALESCE(r.downpayment_gcash_ref, b.downpayment_gcash_ref) as downpayment_gcash_ref,
                COALESCE(r.downpayment_maya_ref, b.downpayment_maya_ref) as downpayment_maya_ref,
                COALESCE(r.downpayment_instapay_ref, b.downpayment_instapay_ref) as downpayment_instapay_ref,
                COALESCE(r.downpayment_online_banking_ref, b.downpayment_online_banking_ref) as downpayment_online_banking_ref,
                COALESCE(r.downpayment_airbnb_ref, b.downpayment_airbnb_ref) as downpayment_airbnb_ref,
                COALESCE(r.deposit_gcash_ref, b.deposit_gcash_ref) as deposit_gcash_ref,
                COALESCE(r.deposit_maya_ref, b.deposit_maya_ref) as deposit_maya_ref,
                COALESCE(r.deposit_instapay_ref, b.deposit_instapay_ref) as deposit_instapay_ref,
                COALESCE(r.deposit_online_banking_ref, b.deposit_online_banking_ref) as deposit_online_banking_ref,
                COALESCE(r.deposit_airbnb_ref, b.deposit_airbnb_ref) as deposit_airbnb_ref,
                COALESCE(r.encoder, b.encoder) as encoder,
                COALESCE(r.modification_updated_at, b.modification_updated_at) as modification_updated_at,
                COALESCE(b.payment_date_time, r.payment_date_time) as payment_date_time,
                COALESCE(b.downpayment_date, r.downpayment_date) as downpayment_date
            FROM reports r
            LEFT JOIN bookings b ON r.booking_id COLLATE utf8mb4_unicode_ci = b.booking_id COLLATE utf8mb4_unicode_ci
            WHERE r.status = 'Canceled'
              AND r.checked_out_at IS NULL
              AND (
                  r.check_in IS NULL 
                  OR YEAR(r.check_in) < 1000 
                  OR r.check_out IS NULL 
                  OR YEAR(r.check_out) < 1000
              )
              AND (
                  COALESCE(r.downpayment_amount, b.downpayment_amount, 0) > 0
                  OR COALESCE(r.deposit_cash, b.deposit_cash, 0) > 0
                  OR COALESCE(r.deposit_g_cash, b.deposit_g_cash, 0) > 0
                  OR COALESCE(r.deposit_maya, b.deposit_maya, 0) > 0
                  OR COALESCE(r.deposit_instapay, b.deposit_instapay, 0) > 0
                  OR COALESCE(r.deposit_online_banking, b.deposit_online_banking, 0) > 0
                  OR COALESCE(r.deposit_airbnb, b.deposit_airbnb, 0) > 0
                  OR COALESCE(r.downpayment_cash, b.downpayment_cash, 0) > 0
                  OR COALESCE(r.downpayment_gcash, b.downpayment_gcash, 0) > 0
                  OR COALESCE(r.downpayment_maya, b.downpayment_maya, 0) > 0
                  OR COALESCE(r.downpayment_instapay, b.downpayment_instapay, 0) > 0
                  OR COALESCE(r.downpayment_online_banking, b.downpayment_online_banking, 0) > 0
                  OR COALESCE(r.downpayment_airbnb, b.downpayment_airbnb, 0) > 0
              )
            ORDER BY r.canceled_at DESC
        ";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter rows to include only those with payment dates in the specified range
        $rows = [];
        foreach ($allRows as $row) {
            $includeRow = false;

            // Check payment_date_time for multiple timestamps
            $paymentDateTime = $row['payment_date_time'] ?? null;
            if (!empty($paymentDateTime)) {
                $timestamps = explode('|', $paymentDateTime);
                foreach ($timestamps as $timestamp) {
                    $timestamp = trim($timestamp);
                    if (!empty($timestamp)) {
                        try {
                            $dt = new DateTime($timestamp);
                            $paymentDate = $dt->format('Y-m-d');
                            if ($paymentDate >= $startDate && $paymentDate <= $endDate) {
                                $includeRow = true;
                                break;
                            }
                        } catch (Exception $e) {
                            // Skip invalid timestamps
                        }
                    }
                }
            }

            // Fallback to downpayment_date if no payment_date_time match
            if (!$includeRow) {
                $fallbackDate = $row['downpayment_date'] ?? null;
                if (!empty($fallbackDate)) {
                    try {
                        $dt = new DateTime($fallbackDate);
                        $dateStr = $dt->format('Y-m-d');
                        if ($dateStr >= $startDate && $dateStr <= $endDate) {
                            $includeRow = true;
                        }
                    } catch (Exception $e) {
                        // Skip invalid dates
                    }
                }
            }

            if ($includeRow) {
                $rows[] = $row;
            }
        }

        $total = 0.0;
        $records = [];

        foreach ($rows as $row) {
            // Calculate total downpayment
            $downpaymentAmt = floatval($row['downpayment_amount'] ?? 0);
            $depositCash = floatval($row['deposit_cash'] ?? 0);
            $depositGcash = floatval($row['deposit_g_cash'] ?? 0);
            $depositMaya = floatval($row['deposit_maya'] ?? 0);
            $depositInstapay = floatval($row['deposit_instapay'] ?? 0);
            $depositOnlineBanking = floatval($row['deposit_online_banking'] ?? 0);
            $depositAirbnb = floatval($row['deposit_airbnb'] ?? 0);

            $downCash = floatval($row['downpayment_cash'] ?? 0);
            $downGcash = floatval($row['downpayment_gcash'] ?? 0);
            $downMaya = floatval($row['downpayment_maya'] ?? 0);
            $downInstapay = floatval($row['downpayment_instapay'] ?? 0);
            $downOnlineBanking = floatval($row['downpayment_online_banking'] ?? 0);
            $downAirbnb = floatval($row['downpayment_airbnb'] ?? 0);

            $totalDownpayment = $downpaymentAmt > 0
                ? $downpaymentAmt
                : ($depositCash + $depositGcash + $depositMaya + $depositInstapay + $depositOnlineBanking + $depositAirbnb
                    + $downCash + $downGcash + $downMaya + $downInstapay + $downOnlineBanking + $downAirbnb);
            $total += $totalDownpayment;

            // Determine payment methods
            // CRITICAL FIX: Use payment history columns for accurate payment method detection
            $paymentMethods = [];

            // Get payment amounts from history columns (same as export files)
            $cashHistoryArr = !empty($row['payment_amount_cash_history'])
                ? explode('|', (string) $row['payment_amount_cash_history'])
                : [];
            $gcashHistoryArr = !empty($row['payment_amount_g_cash_history'])
                ? explode('|', (string) $row['payment_amount_g_cash_history'])
                : [];
            $mayaHistoryArr = !empty($row['payment_amount_maya_history'])
                ? explode('|', (string) $row['payment_amount_maya_history'])
                : [];
            $instapayHistoryArr = !empty($row['payment_amount_instapay_history'])
                ? explode('|', (string) $row['payment_amount_instapay_history'])
                : [];
            $onlineBankingHistoryArr = !empty($row['payment_amount_online_banking_history'])
                ? explode('|', (string) $row['payment_amount_online_banking_history'])
                : [];
            $airbnbHistoryArr = !empty($row['payment_amount_airbnb_history'])
                ? explode('|', (string) $row['payment_amount_airbnb_history'])
                : [];

            $totalCashFromHistory = 0;
            $totalGcashFromHistory = 0;
            $totalMayaFromHistory = 0;
            $totalInstapayFromHistory = 0;
            $totalOnlineBankingFromHistory = 0;
            $totalAirbnbFromHistory = 0;

            foreach ($cashHistoryArr as $amt)
                $totalCashFromHistory += floatval($amt);
            foreach ($gcashHistoryArr as $amt)
                $totalGcashFromHistory += floatval($amt);
            foreach ($mayaHistoryArr as $amt)
                $totalMayaFromHistory += floatval($amt);
            foreach ($instapayHistoryArr as $amt)
                $totalInstapayFromHistory += floatval($amt);
            foreach ($onlineBankingHistoryArr as $amt)
                $totalOnlineBankingFromHistory += floatval($amt);
            foreach ($airbnbHistoryArr as $amt)
                $totalAirbnbFromHistory += floatval($amt);

            // CRITICAL FIX: Deduct downpayment/reservation amounts from totals
            // Downpayment is not an additional charge, it's a deposit toward the final price
            // Only deduct if there are additional charges (pet, guest, food, items)
            $hasAdditionalCharges = false;

            // Check for additional pet
            $additionalPet = floatval($row['additional_pet'] ?? 0);
            if ($additionalPet > 0) {
                $hasAdditionalCharges = true;
            }

            // Check for additional guest
            $additionalGuest = floatval($row['additional_guest'] ?? 0) + floatval($row['extend_additional_guest'] ?? 0);
            if ($additionalGuest > 0) {
                $hasAdditionalCharges = true;
            }

            // Check for additional food
            $additionalFood = $row['additional_food'] ?? '';
            if (!empty($additionalFood) && trim($additionalFood) !== '') {
                $hasAdditionalCharges = true;
            }

            // Check for additional items
            $additionalItems = $row['additional_items'] ?? '';
            if (!empty($additionalItems) && trim($additionalItems) !== '') {
                $hasAdditionalCharges = true;
            }

            // Only deduct downpayment if there are additional charges
            if ($hasAdditionalCharges) {
                $totalCashFromHistory = max(0, $totalCashFromHistory - $downCash);
                $totalGcashFromHistory = max(0, $totalGcashFromHistory - $downGcash);
                $totalMayaFromHistory = max(0, $totalMayaFromHistory - $downMaya);
                $totalInstapayFromHistory = max(0, $totalInstapayFromHistory - $downInstapay);
                $totalOnlineBankingFromHistory = max(0, $totalOnlineBankingFromHistory - $downOnlineBanking);
                $totalAirbnbFromHistory = max(0, $totalAirbnbFromHistory - $downAirbnb);
            }

            // Build payment methods array based on actual amounts
            if ($totalCashFromHistory > 0) {
                $paymentMethods[] = 'Cash';
            }
            if ($totalGcashFromHistory > 0) {
                $paymentMethods[] = 'G-Cash';
            }
            if ($totalMayaFromHistory > 0) {
                $paymentMethods[] = 'Maya';
            }
            if ($totalInstapayFromHistory > 0) {
                $paymentMethods[] = 'Instapay';
            }
            if ($totalOnlineBankingFromHistory > 0) {
                $paymentMethods[] = 'Online Banking';
            }
            if ($totalAirbnbFromHistory > 0) {
                $paymentMethods[] = 'Airbnb';
            }

            // Fallback: if no payment history, use the old logic
            if (empty($paymentMethods)) {
                $cashLikeLabels = [];
                $cashStatusRaw = (string) ($row['payment_status_cash'] ?? '');
                if (stripos($cashStatusRaw, 'Instapay') !== false) {
                    $cashLikeLabels[] = 'Instapay';
                }
                if (stripos($cashStatusRaw, 'Online Banking') !== false) {
                    $cashLikeLabels[] = 'Online Banking';
                }
                if (stripos($cashStatusRaw, 'Airbnb') !== false) {
                    $cashLikeLabels[] = 'Airbnb';
                }
                if (stripos($cashStatusRaw, 'Cash') !== false) {
                    $cashLikeLabels[] = 'Cash';
                }
                $isReservation = strcasecmp(trim((string) ($row['booking_type'] ?? '')), 'Reservation') === 0;
                $totalCash = ($isReservation && $depositCash >= $downCash - 0.01) ? $depositCash : ($depositCash + $downCash);
                $totalInstapay = ($isReservation && $depositInstapay >= $downInstapay - 0.01) ? $depositInstapay : ($depositInstapay + $downInstapay);
                $totalOnlineBanking = ($isReservation && $depositOnlineBanking >= $downOnlineBanking - 0.01) ? $depositOnlineBanking : ($depositOnlineBanking + $downOnlineBanking);
                $totalAirbnb = ($isReservation && $depositAirbnb >= $downAirbnb - 0.01) ? $depositAirbnb : ($depositAirbnb + $downAirbnb);

                if ($totalCash > 0) {
                    if (!empty($cashLikeLabels)) {
                        foreach ($cashLikeLabels as $label) {
                            if (!in_array($label, $paymentMethods, true)) {
                                $paymentMethods[] = $label;
                            }
                        }
                    } else {
                        $hasDedicatedCashLikeMethod = ($totalInstapay > 0 || $totalOnlineBanking > 0 || $totalAirbnb > 0);
                        if (!$hasDedicatedCashLikeMethod) {
                            $paymentMethods[] = 'Cash';
                        }
                    }
                }
                if (($depositGcash + $downGcash) > 0) {
                    $paymentMethods[] = 'G-Cash';
                }
                if (($depositMaya + $downMaya) > 0) {
                    $paymentMethods[] = 'Maya';
                }
                if ($totalInstapay > 0) {
                    $paymentMethods[] = 'Instapay';
                }
                if ($totalOnlineBanking > 0) {
                    $paymentMethods[] = 'Online Banking';
                }
                if ($totalAirbnb > 0) {
                    $paymentMethods[] = 'Airbnb';
                }
            }
            if ($totalOnlineBanking > 0) {
                $paymentMethods[] = 'Online Banking';
            }
            if ($totalAirbnb > 0) {
                $paymentMethods[] = 'Airbnb';
            }

            $paymentStatus = !empty($paymentMethods) ? implode(', ', $paymentMethods) : (($row['payment_status'] ?? '') ?: 'Cash');

            // Get references
            $refGcash = $row['downpayment_gcash_ref'] ?? $row['deposit_gcash_ref'] ?? null;
            $refMaya = $row['downpayment_maya_ref'] ?? $row['deposit_maya_ref'] ?? null;
            $refInstapay = $row['downpayment_instapay_ref'] ?? $row['deposit_instapay_ref'] ?? null;
            $refOnlineBanking = $row['downpayment_online_banking_ref'] ?? $row['deposit_online_banking_ref'] ?? null;
            $refAirbnb = $row['downpayment_airbnb_ref'] ?? $row['deposit_airbnb_ref'] ?? null;

            $records[] = [
                'booking_id' => $row['booking_id'] ?? '',
                'room_id' => $row['room_id'] ?? '',
                'guest_name' => $row['guest_name'] ?? '',
                'guest_type' => $row['guest_type'] ?? '',
                'payment_status' => $paymentStatus,
                'reference_no' => '',
                'reference_no_g_cash' => $refGcash,
                'reference_no_maya' => $refMaya,
                'reference_no_instapay' => $refInstapay,
                'reference_no_online_banking' => $refOnlineBanking,
                'reference_no_airbnb' => $refAirbnb,
                'encoder' => $row['encoder'] ?? '',
                'modification_updated_at' => $row['modification_updated_at'] ?? null,
                'status' => 'Canceled Reservation',
                'check_in' => '',
                'check_out' => '',
                'checked_out_at' => $row['canceled_at'] ?? null,
                'payment_date_time' => null,
                'downpayment_date' => $row['downpayment_date'] ?? null,
                'total_amount' => round($totalDownpayment, 2)
            ];
        }

        return [
            'total' => round($total, 2),
            'records' => $records
        ];
    }
}

if (!function_exists('booking_extension_stack_decode')) {
    /**
     * @return list<array{h:int,m:int,price:float,reg:float,bun:float,bf:?string}>
     */
    function booking_extension_stack_decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'h' => intval($row['h'] ?? 0),
                'm' => intval($row['m'] ?? 0),
                'price' => floatval($row['price'] ?? 0),
                'reg' => floatval($row['reg'] ?? 0),
                'bun' => floatval($row['bun'] ?? 0),
                'bf' => isset($row['bf']) && $row['bf'] !== '' ? (string) $row['bf'] : null,
                'eg' => intval($row['eg'] ?? 0),
            ];
        }

        return $out;
    }
}

if (!function_exists('booking_extension_stack_encode')) {
    function booking_extension_stack_encode(array $stack): ?string
    {
        if ($stack === []) {
            return null;
        }

        return json_encode(array_values($stack), JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('booking_extension_stack_bootstrap_from_row')) {
    /**
     * Build stack from JSON or legacy cumulative extend_* columns.
     *
     * @param array<string,mixed> $row
     * @return list<array{h:int,m:int,price:float,reg:float,bun:float,bf:?string}>
     */
    function booking_extension_stack_bootstrap_from_row(array $row): array
    {
        $decoded = booking_extension_stack_decode($row['extension_stack'] ?? null);
        if ($decoded !== []) {
            return $decoded;
        }
        $h = intval($row['extend_hours'] ?? 0);
        $m = intval($row['extend_minutes'] ?? 0);
        $p = floatval($row['extend_price'] ?? 0);
        if ($h === 0 && $m === 0 && $p <= 0) {
            return [];
        }

        $bf = $row['extend_bundle_breakfast'] ?? null;
        $bf = ($bf !== null && trim((string) $bf) !== '' && strcasecmp(trim((string) $bf), 'None') !== 0)
            ? trim((string) $bf) : null;

        return [
            [
                'h' => $h,
                'm' => $m,
                'price' => $p,
                'reg' => floatval($row['extend_regular_rate'] ?? 0),
                'bun' => floatval($row['extend_bundle_rate'] ?? 0),
                'bf' => $bf,
                'eg' => intval($row['extend_additional_guest'] ?? 0),
            ]
        ];
    }
}

if (!function_exists('booking_extension_stack_aggregate_segments')) {
    /**
     * @param list<array{h?:int,m?:int,price?:float,reg?:float,bun?:float,bf?:?string}> $stack
     * @return array{h:int,m:int,price:float,reg:float,bun:float,bf:?string}
     */
    function booking_extension_stack_aggregate_segments(array $stack): array
    {
        $th = 0;
        $tm = 0;
        $tp = 0.0;
        $tr = 0.0;
        $tb = 0.0;
        $bfs = [];
        $teg = 0;
        $tep = 0;
        foreach ($stack as $seg) {
            $th += intval($seg['h'] ?? 0);
            $tm += intval($seg['m'] ?? 0);
            $tp += floatval($seg['price'] ?? 0);
            $tr += floatval($seg['reg'] ?? 0);
            $tb += floatval($seg['bun'] ?? 0);
            $bf = isset($seg['bf']) ? trim((string) $seg['bf']) : '';
            if ($bf !== '' && strcasecmp($bf, 'None') !== 0) {
                $bfs[] = $bf;
            }
            $teg += intval($seg['eg'] ?? 0);
            $tep += intval($seg['ep'] ?? 0);
        }
        while ($tm >= 60) {
            $th += intdiv($tm, 60);
            $tm = $tm % 60;
        }
        $bfOut = $bfs === [] ? null : implode(' | ', $bfs);

        return [
            'h' => $th,
            'm' => $tm,
            'price' => round($tp, 2),
            'reg' => round($tr, 2),
            'bun' => round($tb, 2),
            'bf' => $bfOut,
            'eg' => $teg,
            'ep' => $tep,
        ];
    }
}

if (!function_exists('formatExtensionDurationDisplayForReport')) {
    /**
     * Format the extension duration column for display.
     */
    function formatExtensionDurationDisplayForReport(array $row, bool $isExpanded = false, ?int $rowIndex = null, ?int $totalRows = null): string
    {
        $stack = booking_extension_stack_bootstrap_from_row($row);
        $hasExtension = (count($stack) > 0);
        
        if (!$hasExtension) {
            return '—';
        }
        
        // If expanded, only show extension duration on the row representing the extension payment.
        if ($isExpanded && $rowIndex !== null && $totalRows !== null) {
            if ($rowIndex === 0 && $totalRows > 1) {
                return '—';
            }
        }
        
        $extLines = [];
        foreach ($stack as $index => $segment) {
            $h = $segment['h'];
            $m = $segment['m'];
            $price = $segment['price'];
            $bun = $segment['bun'];

            $isExtendPromo = ($bun > 0);
            $extTypeLabel = $isExtendPromo ? "(Extend Promo) " : "(Regular) ";

            $segmentStr = $extTypeLabel . $h . ' Hr';
            if ($m > 0) {
                $segmentStr .= ' ' . $m . ' min';
            }
            if ($price > 0) {
                $segmentStr .= ' = &#8369;' . number_format($price, 0);
            }

            $extLines[] = $segmentStr;
        }
        
        if (count($extLines) > 1) {
            $formattedStackDisplay = [];
            foreach ($extLines as $idx => $line) {
                $formattedStackDisplay[] = 'Seg ' . ($idx + 1) . ': ' . $line;
            }
            return implode('<br>', $formattedStackDisplay);
        } elseif (count($extLines) === 1) {
            return $extLines[0];
        }
        
        return '—';
    }
}

if (!function_exists('formatExtensionTimestampDisplayForReport')) {
    /**
     * Format the extension timestamp column for display using 12-hour format.
     */
    function formatExtensionTimestampDisplayForReport(array $row, bool $isExpanded = false, ?int $rowIndex = null, ?int $totalRows = null): string
    {
        $stack = booking_extension_stack_bootstrap_from_row($row);
        $hasExtension = (count($stack) > 0);
        
        if (!$hasExtension) {
            return '—';
        }
        
        // If expanded, only show extension timestamp on the row representing the extension payment.
        if ($isExpanded && $rowIndex !== null && $totalRows !== null) {
            if ($rowIndex === 0 && $totalRows > 1) {
                return '—';
            }
        }
        
        $timestamps = explode('|', (string) ($row['extension_time_at'] ?? ''));
        $extTsLines = [];
        
        foreach ($stack as $index => $segment) {
            $ts = isset($timestamps[$index]) ? trim($timestamps[$index]) : '';
            if ($ts !== '') {
                try {
                    $tsFormatted = (new DateTime($ts))->format('d/m/Y h:i A');
                    $extTsLines[] = $tsFormatted;
                } catch (Exception $e) {
                    $extTsLines[] = htmlspecialchars($ts);
                }
            } else {
                $extTsLines[] = '—';
            }
        }
        
        if (count($extTsLines) > 1) {
            $formattedStackDisplay = [];
            foreach ($extTsLines as $idx => $line) {
                $formattedStackDisplay[] = 'Seg ' . ($idx + 1) . ': ' . $line;
            }
            return implode('<br>', $formattedStackDisplay);
        } elseif (count($extTsLines) === 1) {
            return $extTsLines[0];
        }
        
        return '—';
    }
}

if (!function_exists('formatDurationDisplayForReport')) {
    /**
     * Format the duration column for display.
     */
    function formatDurationDisplayForReport(array $row, bool $isExpanded = false, ?int $rowIndex = null, ?int $totalRows = null): string
    {
        // Get base duration
        $duration = intval($row['duration'] ?? 0);
        $durationUnit = $row['duration_unit'] ?? 'hours';

        // If duration is 0, try to extract from promo string (e.g. "Package 1 12hrs")
        if ($duration == 0) {
            $promoVal = $row['promo'] ?? '';
            if (!empty($promoVal) && $promoVal !== 'None' && $promoVal !== 'Select Promo') {
                if (preg_match('/(\d+)\s*hrs?/i', $promoVal, $promoMatch)) {
                    $duration = intval($promoMatch[1]);
                } else {
                    $duration = 12; // default fallback
                }
            }
        }
        // Still 0? Try to derive from check_in / check_out timestamps
        if ($duration == 0 && !empty($row['check_in']) && !empty($row['check_out'])) {
            try {
                $ciDt = new DateTime($row['check_in']);
                $coDt = new DateTime($row['check_out']);
                $diffSecs = $coDt->getTimestamp() - $ciDt->getTimestamp();
                if ($diffSecs > 0) {
                    $duration = (int) round($diffSecs / 3600);
                }
            } catch (Exception $e) {
            }
        }

        $stack = booking_extension_stack_bootstrap_from_row($row);
        $agg = booking_extension_stack_aggregate_segments($stack);
        $totalExtHours = $agg['h'];
        $totalExtMinutes = $agg['m'];
        $hasExtension = ($totalExtHours > 0 || $totalExtMinutes > 0);

        // Determine if we should show the extended duration
        // If expanded, only show extended duration on subsequent rows (rowIndex > 0)
        $showExtended = $hasExtension && (!$isExpanded || ($rowIndex !== null && $rowIndex > 0) || ($totalRows !== null && $totalRows === 1));

        if ($showExtended) {
            $totalMinutes = ($duration * 60) + ($totalExtHours * 60) + $totalExtMinutes;
            $totalDisplayHours = intdiv($totalMinutes, 60);
            $totalDisplayMins = $totalMinutes % 60;

            if ($durationUnit === 'days') {
                return $duration . ' day' . ($duration != 1 ? 's' : '') . ' (Extended)';
            } else {
                if ($totalDisplayMins > 0) {
                    return $totalDisplayHours . ' hr' . ($totalDisplayHours != 1 ? 's' : '') . ' ' . $totalDisplayMins . ' min (Extended)';
                } else {
                    return $totalDisplayHours . ' hour' . ($totalDisplayHours != 1 ? 's' : '') . ' (Extended)';
                }
            }
        } else {
            // Show base duration
            if ($durationUnit === 'days') {
                return $duration . ' day' . ($duration != 1 ? 's' : '');
            } else {
                return $duration . ' hour' . ($duration != 1 ? 's' : '');
            }
        }
    }
}

if (!function_exists('formatDetailedReportCheckOutDisplay')) {
    /**
     * Match export_detailed_booking_report check-out: date on first line, 12-hour time below.
     */
    function formatDetailedReportCheckOutDisplay($checkedOutAt = null, $checkOut = null): string
    {
        $raw = '';
        if (!empty($checkedOutAt) && trim((string) $checkedOutAt) !== '' && trim((string) $checkedOutAt) !== '0000-00-00 00:00:00') {
            $raw = trim((string) $checkedOutAt);
        } elseif (!empty($checkOut) && trim((string) $checkOut) !== '' && trim((string) $checkOut) !== '0000-00-00 00:00:00') {
            $raw = trim((string) $checkOut);
        }

        if ($raw === '') {
            return '—';
        }

        try {
            $dt = new DateTime($raw);
            return $dt->format('m/d/Y') . '<br>' . $dt->format('h:i a');
        } catch (Exception $e) {
            return '—';
        }
    }
}

if (!function_exists('formatDetailedReportPaymentDateDisplay')) {
    /**
     * Match export_detailed_booking_report payment date column: date only (m/d/Y).
     */
    function formatDetailedReportPaymentDateDisplay(?string $rawTimestamp): string
    {
        if (empty($rawTimestamp) || !is_string($rawTimestamp)) {
            return '—';
        }

        $rawTimestamp = trim($rawTimestamp);
        if ($rawTimestamp === '' || $rawTimestamp === '0000-00-00 00:00:00') {
            return '—';
        }

        try {
            return (new DateTime($rawTimestamp))->format('m/d/Y');
        } catch (Exception $e) {
            return '—';
        }
    }
}

if (!function_exists('isCanceledBookingStatus')) {
    function isCanceledBookingStatus(?string $status): bool
    {
        $status = trim((string) ($status ?? ''));
        if ($status === '') {
            return false;
        }

        return strcasecmp($status, 'Canceled') === 0
            || strcasecmp($status, 'Cancelled') === 0;
    }
}

if (!function_exists('applyCanceledBookingFinancialsToRow')) {
    /**
     * Canceled bookings keep the row but Amount Paid and additional fees are zero.
     */
    function applyCanceledBookingFinancialsToRow(array $row): array
    {
        if (!isCanceledBookingStatus($row['status'] ?? '')) {
            return $row;
        }

        $row['amount'] = 0.0;
        $row['additional_total_fees'] = 0.0;
        $row['additional_items'] = '—';
        $row['additional_foods'] = '—';
        $row['additional_guest'] = '—';
        $row['additional_pet'] = '—';
        $row['additional_missing_items'] = '—';
        $row['additional_penalty'] = '—';
        $row['_guest_count'] = 0;
        $row['_pet_count'] = 0;

        return $row;
    }
}

if (!function_exists('applyCanceledBookingFinancialsToRows')) {
    function applyCanceledBookingFinancialsToRows(array $dataRows): array
    {
        foreach ($dataRows as $index => $row) {
            $dataRows[$index] = applyCanceledBookingFinancialsToRow($row);
        }

        return $dataRows;
    }
}

if (!function_exists('sumPaymentRowAmounts')) {
    function sumPaymentRowAmounts(array $dataRows): float
    {
        $total = 0.0;
        foreach ($dataRows as $row) {
            $total += floatval($row['amount'] ?? 0);
        }

        return $total;
    }
}

if (!function_exists('sumPaymentRowAdditionalFees')) {
    function sumPaymentRowAdditionalFees(array $dataRows): float
    {
        $total = 0.0;
        foreach ($dataRows as $row) {
            if (isCanceledBookingStatus($row['status'] ?? '')) {
                continue;
            }
            $total += floatval($row['additional_total_fees'] ?? 0);
        }

        return $total;
    }
}

if (!function_exists('formatDetailedReportPaymentAmountDisplay')) {
    /**
     * Match export_detailed_booking_report Amount Paid formatting.
     */
    function formatDetailedReportPaymentAmountDisplay(float $amount): string
    {
        return '₱' . number_format($amount, 2);
    }
}

if (!function_exists('formatDetailedReportStatusDisplay')) {
    function formatDetailedReportStatusDisplay(?string $status): string
    {
        $status = trim((string) ($status ?? ''));
        if ($status === '') {
            return '—';
        }
        if (strcasecmp($status, 'Confirmed') === 0) {
            return 'Check-in';
        }
        return $status;
    }
}

if (!function_exists('mapDetailedBookingRowsForRevenueApi')) {
    /**
     * Map detailed booking report rows to Revenue Overview / Booking Revenue Breakdown API records.
     */
    function mapDetailedBookingRowsForRevenueApi(array $dataRows): array
    {
        $records = [];
        foreach ($dataRows as $row) {
            $amount = floatval($row['amount'] ?? 0);
            $records[] = [
                'booking_id' => $row['booking_id'] ?? '',
                'guest_name' => $row['guest_name'] ?? '',
                'payment_method' => $row['payment_method'] ?? '—',
                'reference_no' => $row['reference_no'] ?? '—',
                'status' => $row['status'] ?? '—',
                'check_out_display' => $row['check_out'] ?? '—',
                'payment_date_display' => $row['payment_date_time'] ?? '—',
                'total_amount' => $amount,
                'total_amount_display' => formatDetailedReportPaymentAmountDisplay($amount),
            ];
        }
        return $records;
    }
}

if (!function_exists('mapNonRefundRecordForRevenueApi')) {
    function mapNonRefundRecordForRevenueApi(array $record): array
    {
        $paymentMethodRaw = trim((string) ($record['payment_status'] ?? '—'));
        $paymentMethods = array_map('trim', explode(',', $paymentMethodRaw));
        $refs = [];

        if (in_array('G-Cash', $paymentMethods, true) && !empty($record['reference_no_g_cash']) && $record['reference_no_g_cash'] !== 'NULL') {
            $refs[] = (string) $record['reference_no_g_cash'];
        }
        if (in_array('Maya', $paymentMethods, true) && !empty($record['reference_no_maya']) && $record['reference_no_maya'] !== 'NULL') {
            $refs[] = (string) $record['reference_no_maya'];
        }
        if (in_array('Instapay', $paymentMethods, true) && !empty($record['reference_no_instapay']) && $record['reference_no_instapay'] !== 'NULL') {
            $refs[] = (string) $record['reference_no_instapay'];
        }
        if (in_array('Online Banking', $paymentMethods, true) && !empty($record['reference_no_online_banking']) && $record['reference_no_online_banking'] !== 'NULL') {
            $refs[] = (string) $record['reference_no_online_banking'];
        }
        if (in_array('Airbnb', $paymentMethods, true) && !empty($record['reference_no_airbnb']) && $record['reference_no_airbnb'] !== 'NULL') {
            $refs[] = (string) $record['reference_no_airbnb'];
        }

        $referenceDisplay = !empty($refs) ? implode(', ', $refs) : '—';
        $canceledDisplay = '—';
        if (!empty($record['checked_out_at'])) {
            try {
                $canceledDisplay = (new DateTime($record['checked_out_at']))->format('m/d/Y') . '<br>' . (new DateTime($record['checked_out_at']))->format('h:i a');
            } catch (Exception $e) {
                $canceledDisplay = (string) $record['checked_out_at'];
            }
        }

        $paymentDateDisplay = '—';
        if (!empty($record['downpayment_date'])) {
            try {
                $dpDt = new DateTime($record['downpayment_date']);
                $paymentDateDisplay = $dpDt->format('d/m/Y, g:i A');
            } catch (Exception $e) {
                $paymentDateDisplay = (string) $record['downpayment_date'];
            }
        }

        $amount = floatval($record['total_amount'] ?? 0);

        return [
            'booking_id' => $record['booking_id'] ?? '',
            'guest_name' => $record['guest_name'] ?? '',
            'payment_method' => $paymentMethodRaw !== '' ? $paymentMethodRaw : '—',
            'reference_no' => $referenceDisplay,
            'status' => 'Canceled Reservation',
            'check_out_display' => '—',
            'payment_date_display' => $paymentDateDisplay,
            'total_amount' => $amount,
            'total_amount_display' => formatDetailedReportPaymentAmountDisplay($amount),
        ];
    }
}

if (!function_exists('formatPaymentTimestampForExport')) {
    function formatPaymentTimestampForExport(?string $rawTimestamp): array
    {
        $rawTimestamp = trim((string) ($rawTimestamp ?? ''));
        if ($rawTimestamp === '') {
            return ['date' => 'N/A', 'payment_date_time' => 'N/A', 'raw' => ''];
        }

        try {
            $dt = new DateTime($rawTimestamp);
            return [
                'date' => $dt->format('m/d/Y'),
                'payment_date_time' => $dt->format('m/d/Y h:i a'),
                'raw' => $rawTimestamp,
            ];
        } catch (Exception $e) {
            return ['date' => 'N/A', 'payment_date_time' => $rawTimestamp, 'raw' => $rawTimestamp];
        }
    }
}

if (!function_exists('paymentExportRowMatchesDatetime')) {
    function paymentExportRowMatchesDatetime(array $row, string $rawTarget): bool
    {
        $rawTarget = trim($rawTarget);
        if ($rawTarget === '') {
            return false;
        }

        try {
            $target = new DateTime($rawTarget);
            $display = trim((string) ($row['payment_date_time'] ?? ''));
            if ($display === '') {
                return false;
            }

            $parsed = DateTime::createFromFormat('m/d/Y h:i a', $display);
            if ($parsed instanceof DateTime) {
                return $parsed->format('Y-m-d H:i') === $target->format('Y-m-d H:i');
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }
}

if (!function_exists('paymentExportRowMatchesDateOnly')) {
    function paymentExportRowMatchesDateOnly(array $row, string $rawTarget): bool
    {
        $rawTarget = trim($rawTarget);
        if ($rawTarget === '') {
            return false;
        }

        try {
            $target = new DateTime($rawTarget);
            $targetDate = $target->format('m/d/Y');
            $rowDate = trim((string) ($row['date'] ?? ''));
            if ($rowDate !== '' && $rowDate === $targetDate) {
                return true;
            }

            $display = trim((string) ($row['payment_date_time'] ?? ''));
            if ($display === '') {
                return false;
            }

            $parsed = DateTime::createFromFormat('m/d/Y h:i a', $display);
            if ($parsed instanceof DateTime) {
                return $parsed->format('m/d/Y') === $targetDate;
            }

            $parsedDate = DateTime::createFromFormat('m/d/Y', $display);
            return $parsedDate instanceof DateTime && $parsedDate->format('m/d/Y') === $targetDate;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('paymentExportRowIsDownpaymentDate')) {
    function paymentExportRowIsDownpaymentDate(array $row, string $rawTarget): bool
    {
        return paymentExportRowMatchesDatetime($row, $rawTarget)
            || paymentExportRowMatchesDateOnly($row, $rawTarget);
    }
}

if (!function_exists('paymentExportRowCalendarDate')) {
    function paymentExportRowCalendarDate(array $row): string
    {
        $rowDate = trim((string) ($row['date'] ?? ''));
        if ($rowDate !== '' && $rowDate !== 'N/A') {
            return $rowDate;
        }

        $display = trim((string) ($row['payment_date_time'] ?? ''));
        if ($display === '' || $display === 'N/A') {
            return '';
        }

        $parsed = DateTime::createFromFormat('m/d/Y h:i a', $display);
        if ($parsed instanceof DateTime) {
            return $parsed->format('m/d/Y');
        }

        $parsedDate = DateTime::createFromFormat('m/d/Y', $display);
        return $parsedDate instanceof DateTime ? $parsedDate->format('m/d/Y') : '';
    }
}

if (!function_exists('isLiveReservedPaymentExport')) {
    /**
     * Reservation not checked in yet — export downpayment on downpayment_date only.
     */
    function isLiveReservedPaymentExport(array $payment): bool
    {
        $status = strtolower(trim((string) ($payment['status'] ?? '')));
        return in_array($status, ['reserved', 'pending', 'confirming'], true);
    }
}

if (!function_exists('resolvePaymentExportDisplayStatus')) {
    function resolvePaymentExportDisplayStatus(array $payment): string
    {
        $status = trim((string) ($payment['status'] ?? ''));
        if ($status === '') {
            return 'N/A';
        }
        if (strcasecmp($status, 'Confirmed') === 0) {
            return 'Check-in';
        }
        if (strcasecmp($status, 'Canceled') === 0 || strcasecmp($status, 'Cancelled') === 0) {
            $bookingType = trim((string) ($payment['booking_type'] ?? ''));
            if (strcasecmp($bookingType, 'Reservation') === 0) {
                // Only "Canceled Reservation" if the booking NEVER checked in (no valid check_in)
                $checkIn = trim((string) ($payment['check_in'] ?? ''));
                $hasValidCheckIn = ($checkIn !== '' 
                    && strpos($checkIn, '0000') === false 
                    && intval(substr($checkIn, 0, 4)) >= 1000);
                if (!$hasValidCheckIn) {
                    return 'Canceled Reservation';
                }
                // Has valid check_in -> it actually checked in, treat as regular "Canceled"
            }
        }
        return $status;
    }
}

if (!function_exists('buildPaymentExportTimestampRows')) {
    /**
     * Build per-payment timestamps for export rows.
     * Live Reserved bookings use downpayment_date only (not scheduled check-in).
     */
    function buildPaymentExportTimestampRows(array $payment): array
    {
        if (isLiveReservedPaymentExport($payment) && !empty($payment['downpayment_date'])) {
            return [formatPaymentTimestampForExport((string) $payment['downpayment_date'])];
        }

        $timestampRows = [];
        if (!empty($payment['payment_date_time'])) {
            foreach (explode('|', (string) $payment['payment_date_time']) as $ts) {
                $ts = trim($ts);
                if ($ts === '') {
                    continue;
                }
                $timestampRows[] = formatPaymentTimestampForExport($ts);
            }
        }
        if (empty($timestampRows) && !empty($payment['downpayment_date'])) {
            $timestampRows[] = formatPaymentTimestampForExport((string) $payment['downpayment_date']);
        }
        if (empty($timestampRows)) {
            $timestampRows[] = [
                'date' => $payment['payment_date'] ?? 'N/A',
                'payment_date_time' => 'N/A',
                'raw' => '',
            ];
        }

        return $timestampRows;
    }
}

if (!function_exists('paymentExportMethodTotal')) {
    function paymentExportMethodTotal(array $payment, string $depositKey, string $downpaymentKey): float
    {
        $deposit = floatval($payment[$depositKey] ?? 0);
        $downpayment = floatval($payment[$downpaymentKey] ?? 0);
        if (isLiveReservedPaymentExport($payment)) {
            return $downpayment;
        }
        return max($deposit, $downpayment);
    }
}

if (!function_exists('resolvePaymentExportCashLikeLabel')) {
    function resolvePaymentExportCashLikeLabel(array $payment): string
    {
        $cashStatusRaw = (string) ($payment['payment_status_cash'] ?? '');
        if (stripos($cashStatusRaw, 'Instapay') !== false) {
            return 'Instapay';
        }
        if (stripos($cashStatusRaw, 'Online Banking') !== false) {
            return 'Online Banking';
        }
        if (stripos($cashStatusRaw, 'Airbnb') !== false) {
            return 'Airbnb';
        }
        if ($cashStatusRaw !== '') {
            return 'Cash';
        }

        if (isLiveReservedPaymentExport($payment)) {
            $globalStatus = (string) ($payment['payment_status'] ?? '');
            if (stripos($globalStatus, 'Instapay') !== false) {
                return 'Instapay';
            }
            if (stripos($globalStatus, 'Online Banking') !== false) {
                return 'Online Banking';
            }
            if (stripos($globalStatus, 'Airbnb') !== false) {
                return 'Airbnb';
            }
            if (stripos($globalStatus, 'Cash') !== false) {
                return 'Cash';
            }
        }

        return 'Cash';
    }
}

if (!function_exists('paymentHasReservationDownpayment')) {
    function paymentHasReservationDownpayment(array $payment): bool
    {
        if (strcasecmp(trim((string) ($payment['booking_type'] ?? '')), 'Reservation') !== 0) {
            return false;
        }

        $downpaymentTotal = floatval($payment['downpayment_gcash'] ?? 0)
            + floatval($payment['downpayment_cash'] ?? 0)
            + floatval($payment['downpayment_maya'] ?? 0)
            + floatval($payment['downpayment_instapay'] ?? 0)
            + floatval($payment['downpayment_online_banking'] ?? 0)
            + floatval($payment['downpayment_airbnb'] ?? 0);

        return $downpaymentTotal > 0.005 && trim((string) ($payment['downpayment_date'] ?? '')) !== '';
    }
}

if (!function_exists('normalizeReservationPaymentExportRows')) {
    /**
     * Match Modification.php reservation payment history:
     * - Downpayment on downpayment_date with correct method/amount
     * - Suppress duplicate checkout rows that repeat the downpayment
     * - When only checkout is in range, show balance (deposit - cash downpayment) not full deposit
     */
    function normalizeReservationPaymentExportRows(array $payment, array $rows): array
    {
        if (isLiveReservedPaymentExport($payment)) {
            return $rows;
        }

        if (!paymentHasReservationDownpayment($payment)) {
            return $rows;
        }

        $dpGcash = floatval($payment['downpayment_gcash'] ?? 0);
        $dpCash = floatval($payment['downpayment_cash'] ?? 0);
        $depositCash = floatval($payment['deposit_cash'] ?? 0);
        $dpDateRaw = trim((string) ($payment['downpayment_date'] ?? ''));
        $dpFormatted = formatPaymentTimestampForExport($dpDateRaw);

        $normalized = [];
        foreach ($rows as $row) {
            $method = (string) ($row['payment_method'] ?? '');
            $amt = floatval($row['amount'] ?? 0);

            // G-Cash downpayment: keep only on downpayment_date; suppress duplicates on other dates.
            if ($dpGcash > 0
                && strcasecmp($method, 'G-Cash') === 0
                && abs($amt - $dpGcash) < 0.02) {
                if (paymentExportRowIsDownpaymentDate($row, $dpDateRaw)) {
                    $row['date'] = $dpFormatted['date'];
                    $row['payment_date_time'] = $dpFormatted['payment_date_time'];
                    $normalized[] = $row;
                }
                continue;
            }

            // Cash that duplicates a G-Cash reservation downpayment on a later date.
            if ($dpGcash > 0
                && strcasecmp($method, 'Cash') === 0
                && abs($amt - $dpGcash) < 0.02
                && !paymentExportRowIsDownpaymentDate($row, $dpDateRaw)) {
                continue;
            }

            // Same-day cash downpayment + full-deposit checkout: keep checkout row only.
            if ($dpCash > 0
                && $depositCash > 0
                && strcasecmp($method, 'Cash') === 0
                && abs($amt - $dpCash) < 0.02) {
                $rowDate = paymentExportRowCalendarDate($row);
                $suppressDownpayment = false;
                foreach ($rows as $other) {
                    if (strcasecmp((string) ($other['payment_method'] ?? ''), 'Cash') !== 0) {
                        continue;
                    }
                    $otherAmt = floatval($other['amount'] ?? 0);
                    if (abs($otherAmt - $depositCash) < 0.02
                        && paymentExportRowCalendarDate($other) === $rowDate
                        && $rowDate !== '') {
                        $suppressDownpayment = true;
                        break;
                    }
                }
                if ($suppressDownpayment) {
                    continue;
                }
            }

            // Checkout-only in range: history may store full deposit instead of balance due.
            if ($dpCash > 0
                && $depositCash > 0
                && strcasecmp($method, 'Cash') === 0
                && abs($amt - $depositCash) < 0.02) {
                $rowDate = paymentExportRowCalendarDate($row);
                $hasSameDayDownpayment = false;
                foreach ($rows as $other) {
                    if (strcasecmp((string) ($other['payment_method'] ?? ''), 'Cash') !== 0) {
                        continue;
                    }
                    $otherAmt = floatval($other['amount'] ?? 0);
                    if (abs($otherAmt - $dpCash) < 0.02
                        && paymentExportRowCalendarDate($other) === $rowDate
                        && $rowDate !== '') {
                        $hasSameDayDownpayment = true;
                        break;
                    }
                }
                if (!$hasSameDayDownpayment) {
                    $balance = $depositCash - $dpCash;
                    $row['amount'] = $balance;
                    // Keep method columns in sync so Daily Sales Breakdown totals match Amount Paid.
                    if (floatval($row['cash_amt'] ?? 0) > 0.005) {
                        $row['cash_amt'] = $balance;
                    }
                }
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}

if (!function_exists('getReservationDownpaymentTotal')) {
    function getReservationDownpaymentTotal(array $payment): float
    {
        return floatval($payment['downpayment_cash'] ?? 0)
            + floatval($payment['downpayment_gcash'] ?? 0)
            + floatval($payment['downpayment_maya'] ?? 0)
            + floatval($payment['downpayment_instapay'] ?? 0)
            + floatval($payment['downpayment_online_banking'] ?? 0)
            + floatval($payment['downpayment_airbnb'] ?? 0);
    }
}

if (!function_exists('getReservationDownpaymentForMethod')) {
    function getReservationDownpaymentForMethod(array $payment, string $method): float
    {
        $map = [
            'Cash' => 'downpayment_cash',
            'G-Cash' => 'downpayment_gcash',
            'Maya' => 'downpayment_maya',
            'Instapay' => 'downpayment_instapay',
            'Online Banking' => 'downpayment_online_banking',
            'Airbnb' => 'downpayment_airbnb',
        ];

        $field = $map[$method] ?? null;
        return $field ? floatval($payment[$field] ?? 0) : 0.0;
    }
}

if (!function_exists('isReservationDownpaymentExportRow')) {
    /**
     * Reservation Date / Amount belong only on the downpayment payment row,
     * not on check-in balance or extension rows for the same booking.
     */
    function isReservationDownpaymentExportRow(array $payment, array $row): bool
    {
        if (strcasecmp(trim((string) ($payment['booking_type'] ?? '')), 'Reservation') !== 0) {
            return false;
        }

        $dpDateRaw = trim((string) ($payment['downpayment_date'] ?? ''));
        $targetDateRaw = $dpDateRaw !== '' ? $dpDateRaw : trim((string) ($payment['reservation_date'] ?? ''));
        if ($targetDateRaw === '') {
            return false;
        }

        if (!paymentExportRowIsDownpaymentDate($row, $targetDateRaw)) {
            return false;
        }

        $downpaymentTotal = getReservationDownpaymentTotal($payment);
        if ($downpaymentTotal <= 0.005) {
            return true;
        }

        $rowAmt = floatval($row['amount'] ?? 0);
        if (abs($rowAmt - $downpaymentTotal) < 0.02) {
            return true;
        }

        $methodDp = getReservationDownpaymentForMethod($payment, (string) ($row['payment_method'] ?? ''));
        return $methodDp > 0.005 && abs($rowAmt - $methodDp) < 0.02;
    }
}

if (!function_exists('applyReservationFieldsToDownpaymentExportRowOnly')) {
    function applyReservationFieldsToDownpaymentExportRowOnly(array $payment, array $rows): array
    {
        if (strcasecmp(trim((string) ($payment['booking_type'] ?? '')), 'Reservation') !== 0) {
            return $rows;
        }

        foreach ($rows as &$row) {
            if (!isReservationDownpaymentExportRow($payment, $row)) {
                $row['reservation_date'] = '—';
                $row['reservation_amount'] = '—';
            }
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('countExportPaymentTimestamps')) {
    function countExportPaymentTimestamps(array $payment): int
    {
        if (!empty($payment['payment_date_time'])) {
            $parts = array_values(array_filter(array_map('trim', explode('|', (string) $payment['payment_date_time']))));
            if (!empty($parts)) {
                return count($parts);
            }
        }

        return trim((string) ($payment['downpayment_date'] ?? '')) !== '' ? 1 : 0;
    }
}

if (!function_exists('paymentHasExtensionData')) {
    function paymentHasExtensionData(array $payment): bool
    {
        return intval($payment['extend_hours'] ?? 0) > 0
            || intval($payment['extend_minutes'] ?? 0) > 0
            || floatval($payment['extend_price'] ?? 0) > 0.005;
    }
}

if (!function_exists('isBundledExtensionExportPayment')) {
    /**
     * Walk-in book + extend in one payment (single payment_date_time entry).
     * Matches Modification.php totalPayments <= 1 with extend_price > 0.
     */
    function isBundledExtensionExportPayment(array $payment): bool
    {
        if (!paymentHasExtensionData($payment)) {
            return false;
        }

        return countExportPaymentTimestamps($payment) <= 1;
    }
}

if (!function_exists('paymentExportRowMatchesSingleBundledPayment')) {
    function paymentExportRowMatchesSingleBundledPayment(array $payment, array $row): bool
    {
        if (!empty($payment['payment_date_time'])) {
            $parts = array_values(array_filter(array_map('trim', explode('|', (string) $payment['payment_date_time']))));
            if (count($parts) === 1) {
                return paymentExportRowIsDownpaymentDate($row, $parts[0]);
            }
        }

        $dpDateRaw = trim((string) ($payment['downpayment_date'] ?? ''));
        if ($dpDateRaw !== '') {
            return paymentExportRowIsDownpaymentDate($row, $dpDateRaw);
        }

        return true;
    }
}

if (!function_exists('getExtensionPaymentTimestamps')) {
    /**
     * Payment timestamps that represent extension charges (all except the first checkout payment).
     */
    function getExtensionPaymentTimestamps(array $payment): array
    {
        $timestamps = [];
        if (!empty($payment['payment_date_time'])) {
            $parts = array_values(array_filter(array_map('trim', explode('|', (string) $payment['payment_date_time']))));
            if (count($parts) > 1) {
                $timestamps = array_slice($parts, 1);
            }
        }

        if (empty($timestamps)) {
            $timestamps = array_values(array_filter(array_map('trim', explode('|', (string) ($payment['extension_time_at'] ?? '')))));
        }

        return $timestamps;
    }
}

if (!function_exists('isExtensionPaymentExportRow')) {
    /**
     * Extend Duration / Extend Date belong only on extension payment rows,
     * not on check-in, downpayment, or other payments for the same booking.
     */
    function isExtensionPaymentExportRow(array $payment, array $row): bool
    {
        if (!paymentHasExtensionData($payment)) {
            return false;
        }

        if (isReservationDownpaymentExportRow($payment, $row)) {
            return false;
        }

        if (isBundledExtensionExportPayment($payment)) {
            return paymentExportRowMatchesSingleBundledPayment($payment, $row);
        }

        foreach (getExtensionPaymentTimestamps($payment) as $rawTs) {
            if (paymentExportRowIsDownpaymentDate($row, $rawTs)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('applyExtensionFieldsToExtensionExportRowOnly')) {
    function applyExtensionFieldsToExtensionExportRowOnly(array $payment, array $rows): array
    {
        foreach ($rows as &$row) {
            if (!isExtensionPaymentExportRow($payment, $row)) {
                $row['extension_duration'] = '—';
                $row['extend_date'] = '—';
            }
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('normalizeAllReservationPaymentExportRows')) {
    function normalizeAllReservationPaymentExportRows(array $dataRows, array $payments): array
    {
        if (empty($dataRows)) {
            return $dataRows;
        }

        $paymentMap = [];
        foreach ($payments as $payment) {
            $bookingId = (string) ($payment['booking_id'] ?? '');
            if ($bookingId !== '') {
                $paymentMap[$bookingId] = $payment;
            }
        }

        $byBooking = [];
        foreach ($dataRows as $row) {
            $bookingId = (string) ($row['booking_id'] ?? '');
            $byBooking[$bookingId][] = $row;
        }

        $normalizedRows = [];
        foreach ($byBooking as $bookingId => $rows) {
            $payment = $paymentMap[$bookingId] ?? null;
            if ($payment) {
                $rows = normalizeReservationPaymentExportRows($payment, $rows);
                $rows = applyReservationFieldsToDownpaymentExportRowOnly($payment, $rows);
                $rows = applyExtensionFieldsToExtensionExportRowOnly($payment, $rows);
            }
            foreach ($rows as $row) {
                $normalizedRows[] = $row;
            }
        }

        return $normalizedRows;
    }
}

if (!function_exists('fetchDetailedBookingRevenueOverview')) {
    /**
     * Revenue totals and line items aligned with detailed booking report / export_report.php logic.
     */
    function fetchDetailedBookingRevenueOverview(PDO $conn, string $startDate, string $endDate): array
    {
        require_once __DIR__ . '/detailed_booking_report_logic.php';

        $report = buildDetailedBookingReportData($conn, $startDate, $endDate);
        $nonRefundData = fetchNonRefundDownpayments($conn, $startDate, $endDate);
        $nonRefundTotal = floatval($nonRefundData['total'] ?? 0);

        $records = mapDetailedBookingRowsForRevenueApi($report['dataRows']);
        foreach ($nonRefundData['records'] ?? [] as $nonRefundRecord) {
            $records[] = mapNonRefundRecordForRevenueApi($nonRefundRecord);
        }

        return [
            'total' => floatval($report['grandTotal']) + $nonRefundTotal,
            'records' => $records,
            'grand_total' => floatval($report['grandTotal']),
            'non_refund_total' => $nonRefundTotal,
            'data_rows' => $report['dataRows'],
            'grand_total_additional' => floatval($report['grandTotalAdditional']),
        ];
    }
}

