<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'system_logger.php';
require_once 'fpdf.php';

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Resolve who is viewing
$_exp_first = trim($_SESSION['first_name'] ?? '');
$_exp_last  = trim($_SESSION['last_name'] ?? '');
$_exp_user  = ($_exp_first !== '' || $_exp_last !== '') ? trim($_exp_first . ' ' . $_exp_last) : trim($_SESSION['username'] ?? 'Unknown');

logActivity($conn, 'report', 'REPORT_VIEW_PDF',
    "Running Sales PDF viewed by {$_exp_user} for {$startDate} to {$endDate}",
    ['start_date' => $startDate, 'end_date' => $endDate, 'format' => 'PDF', 'viewed_by' => $_exp_user]
);

// Fetch cash deposits directly
$stmt = $conn->prepare("
    SELECT 
        id,
        shift_date,
        shift_start,
        shift_end,
        cash_expected,
        cash_deposited,
        variance,
        status,
        reason,
        notes,
        breakdown,
        created_by,
        created_at
    FROM cash_deposits
    WHERE shift_date BETWEEN :start_date AND :end_date
    ORDER BY shift_date ASC, shift_start ASC
");

$stmt->execute([
    ':start_date' => $startDate,
    ':end_date' => $endDate
]);

$deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatShiftDateTime($value)
{
    if (empty($value)) {
        return '-';
    }
    return date('m/d/Y g:i A', strtotime($value));
}

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
$pdf->Cell(0, 10, 'RUNNING SALES REPORT', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, $startDate . ' - ' . $endDate, 0, 1, 'C');
$pdf->Ln(3);

// Table Header
$pdf->SetFont('Arial', 'B', 6);
$pdf->SetFillColor(76, 175, 80);
$pdf->SetTextColor(255, 255, 255);

// Use full page width with margins
$leftMargin = 10;
$rightMargin = 10;
$pageWidth = 210;
$availableWidth = $pageWidth - $leftMargin - $rightMargin; // 190mm

// Column widths - 8 columns (removed Reason, Notes, and Breakdown)
$w = [20, 24, 26, 26, 28, 28, 28, 20];
$totalW = array_sum($w);
$scale = $availableWidth / $totalW;
$w = array_map(function($width) use ($scale) {
    return $width * $scale;
}, $w);

$pdf->SetX($leftMargin);

$pdf->Cell($w[0], 7, 'Deposit ID', 1, 0, 'C', true);
$pdf->Cell($w[1], 7, 'Shift Date', 1, 0, 'C', true);
$pdf->Cell($w[2], 7, 'Shift Start', 1, 0, 'C', true);
$pdf->Cell($w[3], 7, 'Shift End', 1, 0, 'C', true);
$pdf->Cell($w[4], 7, 'Cash Expected', 1, 0, 'C', true);
$pdf->Cell($w[5], 7, 'Cash Deposited', 1, 0, 'C', true);
$pdf->Cell($w[6], 7, 'Total Variance', 1, 0, 'C', true);
$pdf->Cell($w[7], 7, 'Status', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Arial', '', 5);
$pdf->SetTextColor(0, 0, 0);

if (empty($deposits)) {
    $pdf->SetX($leftMargin);
    $pdf->Cell($availableWidth, 10, 'No deposit records found', 1, 1, 'C');
} else {
    foreach ($deposits as $deposit) {
        $h = 6;
        
        // Handle page break
        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
            // Reprint header
            $pdf->SetFont('Arial', 'B', 6);
            $pdf->SetFillColor(76, 175, 80);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetX($leftMargin);
            $pdf->Cell($w[0], 7, 'Deposit ID', 1, 0, 'C', true);
            $pdf->Cell($w[1], 7, 'Shift Date', 1, 0, 'C', true);
            $pdf->Cell($w[2], 7, 'Shift Start', 1, 0, 'C', true);
            $pdf->Cell($w[3], 7, 'Shift End', 1, 0, 'C', true);
            $pdf->Cell($w[4], 7, 'Cash Expected', 1, 0, 'C', true);
            $pdf->Cell($w[5], 7, 'Cash Deposited', 1, 0, 'C', true);
            $pdf->Cell($w[6], 7, 'Total Variance', 1, 0, 'C', true);
            $pdf->Cell($w[7], 7, 'Status', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 5);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        // Format data
        $shiftDate = !empty($deposit['shift_date']) ? date('m/d/Y', strtotime($deposit['shift_date'])) : '-';
        $shiftStart = !empty($deposit['shift_start']) ? formatShiftDateTime($deposit['shift_start']) : '-';
        $shiftEnd = !empty($deposit['shift_end']) ? formatShiftDateTime($deposit['shift_end']) : '-';
        
        $cashExpected = floatval($deposit['cash_expected'] ?? 0);
        $cashDeposited = floatval($deposit['cash_deposited'] ?? 0);
        $variance = floatval($deposit['variance'] ?? 0);
        
        $status = $deposit['status'] ?? '-';
        
        $pdf->SetX($leftMargin);
        $pdf->Cell($w[0], $h, 'CD-' . ($deposit['id'] ?? '-'), 1, 0, 'C');
        $pdf->Cell($w[1], $h, $shiftDate, 1, 0, 'C');
        $pdf->Cell($w[2], $h, $shiftStart, 1, 0, 'C');
        $pdf->Cell($w[3], $h, $shiftEnd, 1, 0, 'C');
        $pdf->Cell($w[4], $h, number_format($cashExpected, 2), 1, 0, 'R');
        $pdf->Cell($w[5], $h, number_format($cashDeposited, 2), 1, 0, 'R');
        $pdf->Cell($w[6], $h, number_format($variance, 2), 1, 0, 'R');
        $pdf->Cell($w[7], $h, substr(strtoupper($status), 0, 10), 1, 1, 'C');
    }
    
    // Summary Totals
    $totalExpected = 0;
    $totalDeposited = 0;
    $totalVariance = 0;
    foreach ($deposits as $deposit) {
        $totalExpected += floatval($deposit['cash_expected'] ?? 0);
        $totalDeposited += floatval($deposit['cash_deposited'] ?? 0);
        $totalVariance += floatval($deposit['variance'] ?? 0);
    }
    
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(232, 245, 233);
    $pdf->SetX($leftMargin);
    $pdf->Cell($w[0]+$w[1]+$w[2]+$w[3], 7, 'GRAND TOTAL:', 1, 0, 'R', true);
    $pdf->Cell($w[4], 7, number_format($totalExpected, 2), 1, 0, 'R', true);
    $pdf->Cell($w[5], 7, number_format($totalDeposited, 2), 1, 0, 'R', true);
    $pdf->Cell($w[6], 7, number_format($totalVariance, 2), 1, 0, 'R', true);
    $pdf->Cell($w[7], 7, '', 1, 1, 'C', true);
}

// Output PDF
$pdf->Output('I', 'Running_Sales_Report_' . $startDate . '_to_' . $endDate . '.pdf');
?>
