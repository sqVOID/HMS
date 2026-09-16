<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'system_logger.php';
require_once 'report_helpers.php';
require_once 'fpdf.php';

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Resolve who is exporting
$_exp_first = trim($_SESSION['first_name'] ?? '');
$_exp_last  = trim($_SESSION['last_name'] ?? '');
$_exp_user  = ($_exp_first !== '' || $_exp_last !== '') ? trim($_exp_first . ' ' . $_exp_last) : trim($_SESSION['username'] ?? 'Unknown');

logActivity($conn, 'report', 'REPORT_VIEW_PDF',
    "Payment Type PDF viewed by {$_exp_user} for {$startDate} to {$endDate}",
    ['start_date' => $startDate, 'end_date' => $endDate, 'format' => 'PDF', 'viewed_by' => $_exp_user]
);

// Include the full data processing from the HTML backup file
// We'll extract just the data rows and totals
ob_start(); // Start output buffering to capture any output
include 'export_payment_type_report_pdf_html_backup.php';
$htmlOutput = ob_get_clean(); // Get and clear buffer

// The included file should have populated $dataRows and $grandTotal
// If not, we need to re-process the data

// CREATE PDF
class PDF extends FPDF {
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4'); // Portrait orientation
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);

// Title
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'PER PAYMENT TYPE REPORT', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, $startDate . ' - ' . $endDate, 0, 1, 'C');
$pdf->Ln(3);

// Table Header
$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(76, 175, 80);
$pdf->SetTextColor(255, 255, 255);

// Use full page width with margins
$leftMargin = 10;
$rightMargin = 10;
$pageWidth = 210; // A4 width
$availableWidth = $pageWidth - $leftMargin - $rightMargin; // 190mm

$w = [28, 38, 60, 18, 22, 30, 24]; // Column widths totaling 220mm, will scale down
$totalW = array_sum($w);
// Scale columns proportionally to fit available width
$scale = $availableWidth / $totalW;
$w = array_map(function($width) use ($scale) {
    return $width * $scale;
}, $w);

$pdf->SetX($leftMargin);

$pdf->Cell($w[0], 7, 'Booking ID', 1, 0, 'C', true);
$pdf->Cell($w[1], 7, 'Payment Date', 1, 0, 'C', true);
$pdf->Cell($w[2], 7, 'Customer Name', 1, 0, 'C', true);
$pdf->Cell($w[3], 7, 'Room', 1, 0, 'C', true);
$pdf->Cell($w[4], 7, 'Status', 1, 0, 'C', true);
$pdf->Cell($w[5], 7, 'Payment Method', 1, 0, 'C', true);
$pdf->Cell($w[6], 7, 'Amount Paid', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Arial', '', 6);
$pdf->SetTextColor(0, 0, 0);

if (empty($dataRows)) {
    $pdf->SetX($leftMargin);
    $pdf->Cell($availableWidth, 10, 'No payment records found', 1, 1, 'C');
} else {
    foreach ($dataRows as $row) {
        $h = 6; // Row height
        
        // Handle page break
        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
            // Reprint header
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetFillColor(76, 175, 80);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetX($leftMargin);
            $pdf->Cell($w[0], 7, 'Booking ID', 1, 0, 'C', true);
            $pdf->Cell($w[1], 7, 'Payment Date', 1, 0, 'C', true);
            $pdf->Cell($w[2], 7, 'Customer Name', 1, 0, 'C', true);
            $pdf->Cell($w[3], 7, 'Room', 1, 0, 'C', true);
            $pdf->Cell($w[4], 7, 'Status', 1, 0, 'C', true);
            $pdf->Cell($w[5], 7, 'Payment Method', 1, 0, 'C', true);
            $pdf->Cell($w[6], 7, 'Amount Paid', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 6);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        // Store Y position and calculate row height first
        $startY = $pdf->GetY();
        $currentX = $leftMargin;
        
        // Calculate how many lines the guest name will need
        $guestNameText = $row['guest_name'];
        $guestNameLines = explode("\n", $guestNameText);
        $numLines = count($guestNameLines);
        $lineHeight = 4;
        $topPadding = 1.5; // Top padding inside cell
        $bottomPadding = 1.5; // Bottom padding inside cell
        $cellHeight = max($h, ($numLines * $lineHeight) + $topPadding + $bottomPadding);
        
        // Draw all cells with consistent height
        $pdf->SetXY($currentX, $startY);
        $pdf->Cell($w[0], $cellHeight, $row['booking_id'], 1, 0, 'C');
        $currentX += $w[0];
        
        $pdf->SetXY($currentX, $startY);
        $pdf->Cell($w[1], $cellHeight, $row['payment_date_time'], 1, 0, 'C');
        $currentX += $w[1];
        
        // Guest Name - draw border first, then add text lines
        $pdf->SetXY($currentX, $startY);
        $pdf->Cell($w[2], $cellHeight, '', 1, 0, 'L'); // Empty cell with border
        // Now overlay the text lines with proper padding
        $textY = $startY + $topPadding;
        foreach ($guestNameLines as $line) {
            $pdf->SetXY($currentX + 1, $textY);
            $pdf->Cell($w[2] - 2, $lineHeight, substr(trim($line), 0, 30), 0, 0, 'L');
            $textY += $lineHeight;
        }
        $currentX += $w[2];
        
        $pdf->SetXY($currentX, $startY);
        $pdf->Cell($w[3], $cellHeight, $row['room_id'], 1, 0, 'C');
        $currentX += $w[3];
        
        $pdf->SetXY($currentX, $startY);
        $pdf->Cell($w[4], $cellHeight, $row['status'], 1, 0, 'C');
        $currentX += $w[4];
        
        $pdf->SetXY($currentX, $startY);
        $pdf->Cell($w[5], $cellHeight, $row['payment_method'], 1, 0, 'C');
        $currentX += $w[5];
        
        $pdf->SetXY($currentX, $startY);
        $pdf->Cell($w[6], $cellHeight, number_format($row['amount'], 2), 1, 0, 'R');
        
        // Move to next row
        $pdf->SetY($startY + $cellHeight);
    }
    
    // Grand Total
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(232, 245, 233);
    $pdf->SetX($leftMargin);
    $pdf->Cell($w[0]+$w[1]+$w[2]+$w[3]+$w[4]+$w[5], 7, 'GRAND TOTAL:', 1, 0, 'R', true);
    $pdf->Cell($w[6], 7, number_format($grandTotal, 2), 1, 1, 'R', true);
}

// Output PDF
$pdf->Output('I', 'Payment_Type_Report_' . $startDate . '_to_' . $endDate . '.pdf');
?>
