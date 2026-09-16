<?php
/**
 * Migration script to backfill payment_status_cash, payment_status_g_cash, and payment_status_maya
 * columns in the reports table for existing checked-out bookings.
 */

require_once 'config.php';

echo "Starting payment status columns backfill...\n\n";

try {
    // Get all checked-out reports with NULL payment status columns
    $stmt = $conn->query("
        SELECT 
            id,
            booking_id,
            deposit_details,
            downpayment_cash,
            downpayment_gcash,
            downpayment_maya,
            payment_status_cash,
            payment_status_g_cash,
            payment_status_maya
        FROM reports
        WHERE status = 'Checked Out'
        AND (payment_status_cash IS NULL OR payment_status_g_cash IS NULL OR payment_status_maya IS NULL)
    ");
    
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalRecords = count($reports);
    
    echo "Found $totalRecords records to update.\n\n";
    
    $updatedCount = 0;
    
    foreach ($reports as $report) {
        $id = $report['id'];
        $bookingId = $report['booking_id'];
        
        // Parse deposit_details for checkout payment
        $depositDetails = $report['deposit_details'] ?? '';
        $checkoutCash = 0;
        $checkoutGcash = 0;
        $checkoutMaya = 0;
        
        if (!empty($depositDetails)) {
            // Extract amount
            $amount = 0;
            if (preg_match('/([0-9,]+\.?[0-9]*)/', $depositDetails, $m)) {
                $amount = floatval(str_replace(',', '', $m[1]));
            }
            
            if ($amount > 0) {
                // Determine payment method from deposit_details text
                if (stripos($depositDetails, 'Cash') !== false) {
                    $checkoutCash = $amount;
                } elseif (stripos($depositDetails, 'G-cash') !== false || stripos($depositDetails, 'Gcash') !== false) {
                    $checkoutGcash = $amount;
                } elseif (stripos($depositDetails, 'Maya') !== false) {
                    $checkoutMaya = $amount;
                } else {
                    // Default to cash if no method specified
                    $checkoutCash = $amount;
                }
            }
        }
        
        // Get downpayment amounts
        $downCash = floatval($report['downpayment_cash'] ?? 0);
        $downGcash = floatval($report['downpayment_gcash'] ?? 0);
        $downMaya = floatval($report['downpayment_maya'] ?? 0);
        
        // Calculate totals
        $totalCash = $checkoutCash + $downCash;
        $totalGcash = $checkoutGcash + $downGcash;
        $totalMaya = $checkoutMaya + $downMaya;
        
        // Build payment_status columns
        $psCash = null;
        $psGcash = null;
        $psMaya = null;
        
        if ($totalCash > 0) {
            $psCash = 'Cash (₱' . number_format($totalCash, 2) . ')';
        }
        if ($totalGcash > 0) {
            $psGcash = 'G-cash (₱' . number_format($totalGcash, 2) . ')';
        }
        if ($totalMaya > 0) {
            $psMaya = 'Maya (₱' . number_format($totalMaya, 2) . ')';
        }
        
        // Only update if we have at least one payment method
        if ($psCash || $psGcash || $psMaya) {
            $updateStmt = $conn->prepare("
                UPDATE reports
                SET 
                    payment_status_cash = :payment_status_cash,
                    payment_status_g_cash = :payment_status_g_cash,
                    payment_status_maya = :payment_status_maya
                WHERE id = :id
            ");
            
            $updateStmt->bindParam(':payment_status_cash', $psCash);
            $updateStmt->bindParam(':payment_status_g_cash', $psGcash);
            $updateStmt->bindParam(':payment_status_maya', $psMaya);
            $updateStmt->bindParam(':id', $id);
            
            if ($updateStmt->execute()) {
                $updatedCount++;
                echo "✓ Updated booking $bookingId (ID: $id)\n";
                if ($psCash) echo "  - Cash: $psCash\n";
                if ($psGcash) echo "  - G-Cash: $psGcash\n";
                if ($psMaya) echo "  - Maya: $psMaya\n";
            } else {
                echo "✗ Failed to update booking $bookingId (ID: $id)\n";
            }
        } else {
            echo "⊘ Skipped booking $bookingId (ID: $id) - no payment data found\n";
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Total records found: $totalRecords\n";
    echo "Records updated: $updatedCount\n";
    echo "Records skipped: " . ($totalRecords - $updatedCount) . "\n";
    echo "\nBackfill complete!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
