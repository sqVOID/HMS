<?php
require_once __DIR__ . '/report_helpers.php';

if (!function_exists('renderDetailedBookingReportTable')) {
    /**
     * @param array<int, array<string, mixed>> $dataRows
     */
    function renderDetailedBookingReportTable(
        array $dataRows,
        float $grandTotal,
        float $grandTotalAdditional,
        string $startDate,
        string $endDate,
        bool $embedded = false,
        bool $isPdf = false
    ): void {
        $columnCount = 35;
        $tdBase = 'padding: 5px; text-align: left; vertical-align: top;';
        $tdText = $tdBase . " mso-number-format:'@';";
        $headerCellStyle = 'padding: 8px; font-weight: bold; text-align: left; vertical-align: top;';
        $detailedRangeLabel = $startDate . ' - ' . $endDate;

        if (!$embedded) {
            $currentTime = date('d/m/Y H:i:s');
            $rangeLabel = $detailedRangeLabel;

            if ($isPdf) {
                echo '<!DOCTYPE html>';
                echo '<html><head><meta charset="UTF-8">';
                echo '<title>Detailed Booking Report</title>';
                echo '<style>
                    @media print { @page { margin: 0.5in; size: landscape; } body { margin: 0; } }
                    body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background: white; color: #333; }
                    .header { text-align: center; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 3px solid #4CAF50; }
                    .header h1 { margin: 0; color: #4CAF50; font-size: 24px; font-weight: 600; }
                    .header .meta { margin-top: 8px; color: #666; font-size: 13px; }
                    .header .date-range { margin-top: 5px; color: #4CAF50; font-weight: 600; font-size: 14px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
                    th { background-color: #4CAF50; color: white; font-weight: 600; padding: 10px 6px; text-align: left; font-size: 11px; border: 1px solid #45a049; white-space: nowrap; vertical-align: top; }
                    td { border: 1px solid #e0e0e0; padding: 8px 6px; text-align: left; vertical-align: top; font-size: 10px; }
                    .print-button { position: fixed; top: 10px; right: 10px; background: #4CAF50; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; z-index: 1000; }
                    @media print { .print-button { display: none; } }
                </style>';
                echo '<script>function printPDF() { window.print(); }</script>';
                echo '</head><body>';
                echo '<button class="print-button" onclick="printPDF()">Print / Save as PDF</button>';
                echo '<div class="header">';
                echo '<h1>Hotel Management System - Detailed Booking Report</h1>';
                echo '<div class="meta">Generated on: ' . $currentTime . '</div>';
                echo '<div class="date-range">Date Range: ' . htmlspecialchars($rangeLabel) . '</div>';
                echo '</div>';
            } else {
                echo '<html><head><meta charset="UTF-8">';
                echo '<style>th, td { text-align: left; vertical-align: top; }</style>';
                echo '</head><body>';
            }

            echo '<table border="1" cellpadding="5" cellspacing="0">';

            if (!$isPdf) {
                echo '<tr><td colspan="' . $columnCount . '" style="background-color: #4CAF50; color: white; font-weight: bold; font-size: 16px; text-align: center; padding: 10px;">Hotel Management System - Detailed Booking Report</td></tr>';
                echo '<tr><td colspan="' . $columnCount . '" style="text-align: center; padding: 5px;">Generated on: ' . $currentTime . '</td></tr>';
                echo '<tr><td colspan="' . $columnCount . '" style="text-align: center; padding: 5px; font-weight: bold; color: #256d27;">Date Range: ' . htmlspecialchars($rangeLabel) . '</td></tr>';
                echo '<tr><td colspan="' . $columnCount . '"></td></tr>';
                echo '<tr><td colspan="' . $columnCount . '" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Detailed Booking Report (' . htmlspecialchars($startDate) . ' - ' . htmlspecialchars($endDate) . ')</td></tr>';
                echo '<tr style="background-color: #4CAF50; color: white;">';
            } else {
                echo '<thead><tr>';
            }
        } else {
            echo '<tr><td colspan="' . $columnCount . '" style="background-color: #f0f0f0; font-weight: bold; font-size: 14px; padding: 8px;">Booking Detailed Report - ' . htmlspecialchars($detailedRangeLabel) . '</td></tr>';
            echo '<tr style="background-color: #4CAF50; color: white;">';
        }

        $headerTag = $embedded ? 'td' : 'th';
        $headers = [
            'Booking ID', 'Encoder', 'Modified', 'Original Room', 'Sales Channel', 'Booking Type',
            'Current Room', 'Type of Guest', 'Guest Name', 'Address', 'Contact no.', 'Vehicle Description',
            'Check-in', 'Check out', 'Duration', 'Extend Duration', 'Extend Date',
            'Status', 'Reservation Date', 'Reservation Amount', 'Payment Method', 'Reference No.', 'Payment Date and Time',
            'Breakfast', 'Promo', 'Discount Amount', 'Discount Date', 'Additional Items', 'Additional Foods',
            'Additional Guest', 'Additional Pet', 'Additional Missing Items', 'Additional Penalty',
            'Additional Total Amount Fees', 'Amount Paid',
        ];
        foreach ($headers as $headerLabel) {
            echo '<' . $headerTag . ' style="' . $headerCellStyle . '">' . htmlspecialchars($headerLabel) . '</' . $headerTag . '>';
        }
        echo '</tr>';

        if (!$embedded && $isPdf) {
            echo '</thead><tbody>';
        } elseif (!$embedded) {
            echo '<tbody>';
        }

        if (empty($dataRows)) {
            echo '<tr><td colspan="' . $columnCount . '" style="text-align: center; padding: 20px; color: #999;">No payment records found for the selected date range.</td></tr>';
        } else {
            foreach ($dataRows as $row) {
                $discountDisplay = ($row['discount_amount'] ?? 0) > 0 ? '₱' . number_format($row['discount_amount'], 2) : '—';
                $reservationAmountDisplay = ($row['reservation_amount'] ?? '—') === '—'
                    ? '—'
                    : '₱' . $row['reservation_amount'];

                echo '<tr>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['booking_id']) . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['encoder']) . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['modified'] ?? '') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['original_room'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['sales_channel'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['booking_type'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['current_room'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['guest_type'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['guest_name']) . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['address'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['contact_no'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['vehicle_description'] ?? '—') . '</td>';
                echo '<td style="' . $tdText . '">' . ($row['check_in'] ?? '—') . '</td>';
                echo '<td style="' . $tdText . '">' . ($row['check_out'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['duration'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['extension_duration'] ?? '—') . '</td>';
                echo '<td style="' . $tdText . '">' . ($row['extend_date'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['status'] ?? '—') . '</td>';
                echo '<td style="' . $tdText . '">' . htmlspecialchars($row['reservation_date'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . $reservationAmountDisplay . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['payment_method']) . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['reference_no'] ?? '—') . '</td>';
                echo '<td style="' . $tdText . '">' . ($row['payment_date_time'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['breakfast'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['promo'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . $discountDisplay . '</td>';
                echo '<td style="' . $tdText . '">' . htmlspecialchars($row['discount_date'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['additional_items'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['additional_foods'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['additional_guest'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['additional_pet'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['additional_missing_items'] ?? '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . htmlspecialchars($row['additional_penalty'] ?? '—') . '</td>';
                $additionalTotalFees = floatval($row['additional_total_fees'] ?? 0);
                echo '<td style="' . $tdBase . '">' . ($additionalTotalFees > 0 ? '₱' . number_format($additionalTotalFees, 2) : '—') . '</td>';
                echo '<td style="' . $tdBase . '">' . formatDetailedReportPaymentAmountDisplay(floatval($row['amount'] ?? 0)) . '</td>';
                echo '</tr>';
            }

            echo '<tr style="background-color: #e0e0e0; font-weight: bold;">';
            echo '<td colspan="' . ($columnCount - 2) . '" style="padding: 8px; text-align: right;">GRAND TOTAL</td>';
            echo '<td style="padding: 8px;">₱' . number_format($grandTotalAdditional, 2) . '</td>';
            echo '<td style="padding: 8px;">₱' . number_format($grandTotal, 2) . '</td>';
            echo '</tr>';
        }

        if (!$embedded) {
            echo '</tbody></table>';
        }
    }
}
