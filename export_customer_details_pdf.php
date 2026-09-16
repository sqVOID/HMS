<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'system_logger.php';
require_once 'report_helpers.php';
require_once 'fpdf.php';
require_once __DIR__ . '/detailed_booking_report_functions.php';

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Resolve who is exporting
$_exp_first = trim($_SESSION['first_name'] ?? '');
$_exp_last  = trim($_SESSION['last_name'] ?? '');
$_exp_user  = ($_exp_first !== '' || $_exp_last !== '') ? trim($_exp_first . ' ' . $_exp_last) : trim($_SESSION['username'] ?? 'Unknown');

logActivity($conn, 'report', 'REPORT_VIEW_PDF',
    "Customer Details PDF viewed by {$_exp_user} for {$startDate} to {$endDate}",
    ['start_date' => $startDate, 'end_date' => $endDate, 'format' => 'PDF', 'viewed_by' => $_exp_user]
);

// Include the full data processing from the HTML backup file
ob_start();
include 'export_customer_details_pdf_html_backup.php';
$htmlOutput = ob_get_clean();

// CREATE PDF
class PDF extends FPDF {
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape orientation
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);

// Title
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, 'CUSTOMER DETAILS REPORT', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 6, 'Date Range: ' . $startDate . ' to ' . $endDate, 0, 1, 'C');
$pdf->SetFont('Arial', '', 7);
$pdf->Cell(0, 5, 'Generated on: ' . date('m/d/Y h:i a'), 0, 1, 'C');
$pdf->Ln(2);

// Table Header - Using smaller font and abbreviated headers for many columns
$pdf->SetFont('Arial', 'B', 5);
$pdf->SetFillColor(175, 173, 76);
$pdf->SetTextColor(255, 255, 255);

// Column widths for landscape A4 (297mm width - 20mm margins = 277mm available)
$w = [18, 22, 22, 12, 28, 15, 12, 12, 12, 15, 15, 18, 18, 12, 12, 20, 15, 20, 15, 12, 12, 12, 12]; 
// Total: ~301mm, will scale down slightly

$leftMargin = 10;
$availableWidth = 277; // Landscape A4 available width
$totalW = array_sum($w);
$scale = $availableWidth / $totalW;
$w = array_map(function($width) use ($scale) {
    return $width * $scale;
}, $w);

$pdf->SetX($leftMargin);

// Header row - abbreviated to fit
$pdf->Cell($w[0], 6, 'Booking ID', 1, 0, 'C', true);
$pdf->Cell($w[1], 6, 'Guest Name', 1, 0, 'C', true);
$pdf->Cell($w[2], 6, 'Company', 1, 0, 'C', true);
$pdf->Cell($w[3], 6, 'Type', 1, 0, 'C', true);
$pdf->Cell($w[4], 6, 'Address', 1, 0, 'C', true);
$pdf->Cell($w[5], 6, 'Contact', 1, 0, 'C', true);
$pdf->Cell($w[6], 6, 'TIN', 1, 0, 'C', true);
$pdf->Cell($w[7], 6, 'Room', 1, 0, 'C', true);
$pdf->Cell($w[8], 6, 'Orig Rm', 1, 0, 'C', true);
$pdf->Cell($w[9], 6, 'Room Type', 1, 0, 'C', true);
$pdf->Cell($w[10], 6, 'Booking Type', 1, 0, 'C', true);
$pdf->Cell($w[11], 6, 'Check In', 1, 0, 'C', true);
$pdf->Cell($w[12], 6, 'Check Out', 1, 0, 'C', true);
$pdf->Cell($w[13], 6, 'Duration', 1, 0, 'C', true);
$pdf->Cell($w[14], 6, 'Extend', 1, 0, 'C', true);
$pdf->Cell($w[15], 6, 'Reason', 1, 0, 'C', true);
$pdf->Cell($w[16], 6, 'Request', 1, 0, 'C', true);
$pdf->Cell($w[17], 6, 'Vehicle', 1, 0, 'C', true);
$pdf->Cell($w[18], 6, 'Sales Ch.', 1, 0, 'C', true);
$pdf->Cell($w[19], 6, 'SC/PWD', 1, 0, 'C', true);
$pdf->Cell($w[20], 6, 'Discount', 1, 0, 'C', true);
$pdf->Cell($w[21], 6, 'Amount', 1, 0, 'C', true);
$pdf->Cell($w[22], 6, 'Total Bkgs', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Arial', '', 4.5);
$pdf->SetTextColor(0, 0, 0);

if (empty($records)) {
    $pdf->SetX($leftMargin);
    $pdf->Cell($availableWidth, 10, 'No records found', 1, 1, 'C');
} else {
    // Expand each booking into one row per guest name so repeated booking IDs
    // (with multiple guest names) are rendered as separate rows.
    $expandedRecords = [];
    foreach ($records as $record) {
        $guestTypeRaw = strtolower(trim((string)($record['guest_type'] ?? '')));

        if ($guestTypeRaw === 'company') {
            $companyGuestName = trim((string)($record['contact_person_name'] ?? ''));
            if ($companyGuestName !== '') {
                $copy = $record;
                $copy['pdf_display_guest_name'] = $companyGuestName;
                $expandedRecords[] = $copy;
            } else {
                $copy = $record;
                $copy['pdf_display_guest_name'] = '';
                $expandedRecords[] = $copy;
            }
            continue;
        }

        $guestNames = [];
        $appendName = function(string $name) use (&$guestNames): void {
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

        // Fallback: split consolidated names if dedicated fields are empty.
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
                $copy['pdf_display_guest_name'] = $guestName;
                $expandedRecords[] = $copy;
            }
        } else {
            $copy = $record;
            $copy['pdf_display_guest_name'] = '';
            $expandedRecords[] = $copy;
        }
    }
    $records = $expandedRecords;

    // Build PDF-specific booking counts so names like
    // "TEST1\nTEST2" and "TEST1\nTEST2\nTEST3" are grouped by primary guest ("TEST1").
    $guestBookingCounts = [];
    $resolveGuestCountKey = function(array $row): string {
        $guestType = strtolower(trim((string)($row['guest_type'] ?? '')));
        $pdfDisplayGuestName = trim((string)($row['pdf_display_guest_name'] ?? ''));

        if ($pdfDisplayGuestName !== '') {
            return strtolower(preg_replace('/\s+/', ' ', $pdfDisplayGuestName));
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

        // Use the first non-empty line as the fallback primary guest key.
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

    // Keep same guest names together while preserving separate booking rows.
    usort($records, function ($a, $b) use ($resolveGuestCountKey, $guestBookingCounts) {
        $keyA = $resolveGuestCountKey($a);
        $keyB = $resolveGuestCountKey($b);

        // Named guests first, blank names last.
        if ($keyA === '' && $keyB !== '') return 1;
        if ($keyA !== '' && $keyB === '') return -1;

        // More bookings first, then guest key alphabetical.
        $countA = $guestBookingCounts[$keyA] ?? 0;
        $countB = $guestBookingCounts[$keyB] ?? 0;
        if ($countA !== $countB) {
            return $countB <=> $countA;
        }

        if ($keyA !== $keyB) {
            return strcmp($keyA, $keyB);
        }

        // Same guest: keep a stable per-booking order.
        $bookingIdA = (string)($a['booking_id'] ?? '');
        $bookingIdB = (string)($b['booking_id'] ?? '');
        return strcmp($bookingIdA, $bookingIdB);
    });

    $computeRowHeight = function(array $row): float {
        $displayGuestName = !empty($row['pdf_display_guest_name'])
            ? $row['pdf_display_guest_name']
            : (!empty($row['consolidated_guest_name']) ? $row['consolidated_guest_name'] : ($row['guest_name'] ?? '-'));
        $companyName = '-';
        $guestType = $row['guest_type'] ?? '';
        if (strtolower(trim($guestType)) === 'company') {
            $companyName = !empty($row['consolidated_guest_name']) ? $row['consolidated_guest_name'] : ($row['guest_name'] ?? '-');
            $displayGuestName = $row['contact_person_name'] ?? '-';
        }

        $guestNameLines = explode("\n", (string)$displayGuestName);
        $companyNameLines = explode("\n", (string)$companyName);
        $guestNameHeight = max(1, count($guestNameLines)) * 3.5 + 2;
        $companyNameHeight = max(1, count($companyNameLines)) * 3.5 + 2;
        return max(5, $guestNameHeight, $companyNameHeight);
    };

    // Pre-compute merged heights for Total Bkgs rowspan per guest group.
    $guestGroupInfo = [];
    $recordCount = count($records);
    for ($i = 0; $i < $recordCount; $i++) {
        $guestKey = $resolveGuestCountKey($records[$i]);
        $rowHeight = $computeRowHeight($records[$i]);

        if ($guestKey === '') {
            $guestGroupInfo[$i] = ['isFirst' => true, 'mergeHeight' => $rowHeight];
            continue;
        }

        $prevKey = ($i > 0) ? $resolveGuestCountKey($records[$i - 1]) : '';
        if ($i === 0 || $prevKey !== $guestKey) {
            $mergeHeight = $rowHeight;
            for ($j = $i + 1; $j < $recordCount; $j++) {
                if ($resolveGuestCountKey($records[$j]) !== $guestKey) {
                    break;
                }
                $mergeHeight += $computeRowHeight($records[$j]);
            }
            $guestGroupInfo[$i] = ['isFirst' => true, 'mergeHeight' => $mergeHeight];
        } else {
            $guestGroupInfo[$i] = ['isFirst' => false, 'mergeHeight' => 0];
        }
    }
    
    foreach ($records as $rowIndex => $row) {
        // Handle page break
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            // Reprint header
            $pdf->SetFont('Arial', 'B', 5);
            $pdf->SetFillColor(175, 173, 76);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetX($leftMargin);
            $pdf->Cell($w[0], 6, 'Booking ID', 1, 0, 'C', true);
            $pdf->Cell($w[1], 6, 'Guest Name', 1, 0, 'C', true);
            $pdf->Cell($w[2], 6, 'Company', 1, 0, 'C', true);
            $pdf->Cell($w[3], 6, 'Type', 1, 0, 'C', true);
            $pdf->Cell($w[4], 6, 'Address', 1, 0, 'C', true);
            $pdf->Cell($w[5], 6, 'Contact', 1, 0, 'C', true);
            $pdf->Cell($w[6], 6, 'TIN', 1, 0, 'C', true);
            $pdf->Cell($w[7], 6, 'Room', 1, 0, 'C', true);
            $pdf->Cell($w[8], 6, 'Orig Rm', 1, 0, 'C', true);
            $pdf->Cell($w[9], 6, 'Room Type', 1, 0, 'C', true);
            $pdf->Cell($w[10], 6, 'Booking Type', 1, 0, 'C', true);
            $pdf->Cell($w[11], 6, 'Check In', 1, 0, 'C', true);
            $pdf->Cell($w[12], 6, 'Check Out', 1, 0, 'C', true);
            $pdf->Cell($w[13], 6, 'Duration', 1, 0, 'C', true);
            $pdf->Cell($w[14], 6, 'Extend', 1, 0, 'C', true);
            $pdf->Cell($w[15], 6, 'Reason', 1, 0, 'C', true);
            $pdf->Cell($w[16], 6, 'Request', 1, 0, 'C', true);
            $pdf->Cell($w[17], 6, 'Vehicle', 1, 0, 'C', true);
            $pdf->Cell($w[18], 6, 'Sales Ch.', 1, 0, 'C', true);
            $pdf->Cell($w[19], 6, 'SC/PWD', 1, 0, 'C', true);
            $pdf->Cell($w[20], 6, 'Discount', 1, 0, 'C', true);
            $pdf->Cell($w[21], 6, 'Amount', 1, 0, 'C', true);
            $pdf->Cell($w[22], 6, 'Total Bkgs', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 4.5);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        // Determine company name and guest name display
        $companyName = '-';
        $displayGuestName = !empty($row['pdf_display_guest_name'])
            ? $row['pdf_display_guest_name']
            : (!empty($row['consolidated_guest_name']) ? $row['consolidated_guest_name'] : ($row['guest_name'] ?? '-'));
        $guestType = $row['guest_type'] ?? '';
        
        if (strtolower(trim($guestType)) === 'company') {
            $companyName = !empty($row['consolidated_guest_name']) ? $row['consolidated_guest_name'] : ($row['guest_name'] ?? '-');
            $displayGuestName = $row['contact_person_name'] ?? '-';
        }
        
        $originalRoom = !empty($row['transfer_room_from']) ? $row['transfer_room_from'] : '-';
        
        // Calculate amount (same logic as HTML version)
        $historyMethodMap = [
            'payment_amount_cash_history'          => ['deposit_cash',         'downpayment_cash'],
            'payment_amount_g_cash_history'        => ['deposit_g_cash',        'downpayment_gcash'],
            'payment_amount_maya_history'          => ['deposit_maya',           'downpayment_maya'],
            'payment_amount_instapay_history'      => ['deposit_instapay',       'downpayment_instapay'],
            'payment_amount_online_banking_history'=> ['deposit_online_banking', 'downpayment_online_banking'],
            'payment_amount_airbnb_history'        => ['deposit_airbnb',         'downpayment_airbnb'],
        ];
        $totalAmt = 0;
        foreach ($historyMethodMap as $histCol => [$depCol, $downCol]) {
            $histRaw = $row[$histCol] ?? '';
            if (!empty($histRaw)) {
                foreach (explode('|', (string) $histRaw) as $seg) {
                    $totalAmt += floatval(trim($seg));
                }
            } else {
                $dep  = floatval($row[$depCol]  ?? 0);
                $down = floatval($row[$downCol] ?? 0);
                $totalAmt += max($dep, $down);
            }
        }
        
        // Format dates
        $checkIn = '-';
        $checkOut = '-';
        if (!empty($row['check_in']) && $row['check_in'] !== '0000-00-00 00:00:00') {
            try {
                $dt = new DateTime($row['check_in']);
                $checkIn = $dt->format('m/d/Y');
            } catch (Exception $e) {}
        }
        if (!empty($row['checked_out_at']) && $row['checked_out_at'] !== '0000-00-00 00:00:00') {
            try {
                $dt = new DateTime($row['checked_out_at']);
                $checkOut = $dt->format('m/d/Y');
            } catch (Exception $e) {}
        }
        
        // Calculate heights for multi-line fields (Guest Name and Company Name)
        $guestNameLines = explode("\n", $displayGuestName);
        $companyNameLines = explode("\n", $companyName);
        $guestNameHeight = max(1, count($guestNameLines)) * 3.5 + 2;
        $companyNameHeight = max(1, count($companyNameLines)) * 3.5 + 2;
        $h = max(5, $guestNameHeight, $companyNameHeight);
        
        $y = $pdf->GetY();
        $xPos = $leftMargin;
        
        // Booking ID cell
        $pdf->SetXY($xPos, $y);
        $pdf->Cell($w[0], $h, substr($row['booking_id'] ?? '-', 0, 15), 1, 0, 'C');
        $xPos += $w[0];
        
        // Guest Name cell - multi-line
        $pdf->Rect($xPos, $y, $w[1], $h);
        $lineY = $y + ($h - (count($guestNameLines) * 3.5)) / 2;
        foreach ($guestNameLines as $line) {
            $pdf->SetXY($xPos + 0.5, $lineY);
            $pdf->Cell($w[1] - 1, 3.5, substr(trim($line), 0, 18), 0, 0, 'L');
            $lineY += 3.5;
        }
        $xPos += $w[1];
        
        // Company Name cell - multi-line
        $pdf->Rect($xPos, $y, $w[2], $h);
        $lineY = $y + ($h - (count($companyNameLines) * 3.5)) / 2;
        foreach ($companyNameLines as $line) {
            $pdf->SetXY($xPos + 0.5, $lineY);
            $pdf->Cell($w[2] - 1, 3.5, substr(trim($line), 0, 18), 0, 0, 'L');
            $lineY += 3.5;
        }
        $xPos += $w[2];
        
        // Continue with other cells
        $pdf->SetXY($xPos, $y);
        $pdf->Cell($w[3], $h, substr($row['guest_type'] ?? '-', 0, 10), 1, 0, 'C');
        $pdf->Cell($w[4], $h, substr($row['address'] ?? '-', 0, 25), 1, 0, 'L');
        $pdf->Cell($w[5], $h, substr($row['contact_no'] ?? '-', 0, 12), 1, 0, 'C');
        $pdf->Cell($w[6], $h, substr($row['tin_number'] ?? '-', 0, 10), 1, 0, 'C');
        $pdf->Cell($w[7], $h, 'Rm' . ($row['room_id'] ?? '-'), 1, 0, 'C');
        $pdf->Cell($w[8], $h, $originalRoom, 1, 0, 'C');
        $pdf->Cell($w[9], $h, substr($row['room_type'] ?? '-', 0, 12), 1, 0, 'C');
        $pdf->Cell($w[10], $h, substr($row['booking_type'] ?? '-', 0, 12), 1, 0, 'C');
        $pdf->Cell($w[11], $h, $checkIn, 1, 0, 'C');
        $pdf->Cell($w[12], $h, $checkOut, 1, 0, 'C');
        $pdf->Cell($w[13], $h, substr(($row['duration'] ?? '0') . ' ' . ($row['duration_unit'] ?? 'hrs'), 0, 10), 1, 0, 'C');
        $pdf->Cell($w[14], $h, substr((($row['extend_hours'] ?? 0) . 'h ' . ($row['extend_minutes'] ?? 0) . 'm'), 0, 10), 1, 0, 'C');
        $pdf->Cell($w[15], $h, substr($row['reason_for_stay'] ?? '-', 0, 18), 1, 0, 'L');
        $pdf->Cell($w[16], $h, substr($row['request'] ?? '-', 0, 12), 1, 0, 'L');
        
        // Vehicle info
        $vehicleInfo = trim(($row['vehicle_type'] ?? '') . ' ' . ($row['vehicle_description'] ?? ''));
        if (empty($vehicleInfo)) $vehicleInfo = '-';
        $pdf->Cell($w[17], $h, substr($vehicleInfo, 0, 18), 1, 0, 'L');
        
        $pdf->Cell($w[18], $h, substr($row['sales_channel'] ?? '-', 0, 12), 1, 0, 'C');
        $pdf->Cell($w[19], $h, substr($row['id_number'] ?? '-', 0, 10), 1, 0, 'C');
        
        $disc = floatval($row['discount_amount'] ?? 0);
        $pdf->Cell($w[20], $h, $disc > 0 ? number_format($disc, 2) : '-', 1, 0, 'R');
        $pdf->Cell($w[21], $h, number_format($totalAmt, 2), 1, 0, 'R');
        
        // Total Bookings column — one merged cell per guest group (rowspan)
        $guestKey = $resolveGuestCountKey($row);
        $groupInfo = $guestGroupInfo[$rowIndex] ?? ['isFirst' => true, 'mergeHeight' => $h];
        if ($guestKey !== '') {
            if (!empty($groupInfo['isFirst'])) {
                $totalBookings = $guestBookingCounts[$guestKey] ?? 1;
                $mergeHeight = $groupInfo['mergeHeight'] ?? $h;
                $totalX = $pdf->GetX();
                $totalY = $pdf->GetY();

                $pdf->Rect($totalX, $totalY, $w[22], $mergeHeight);
                $textY = $totalY + ($mergeHeight / 2) - 2;
                $pdf->SetXY($totalX, $textY);
                $pdf->SetFont('Arial', 'B', 5);
                $pdf->Cell($w[22], 4, (string)$totalBookings, 0, 0, 'C');
                $pdf->SetFont('Arial', '', 4.5);
            }
            $pdf->SetXY($leftMargin, $y + $h);
        } else {
            $pdf->Cell($w[22], $h, '-', 1, 1, 'C');
        }
    }
}

// Output PDF
$pdf->Output('I', 'Customer_Details_Report_' . $startDate . '_to_' . $endDate . '.pdf');
?>
