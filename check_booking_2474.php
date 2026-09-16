<?php
/**
 * Check Specific Booking ID 2474
 * 
 * This script checks the synchronization status of booking ID 2474
 */

require_once 'config.php';

echo "<h2>Booking ID 2474 Status Check</h2>\n";
echo "<pre>\n";

try {
    $booking_id_to_check = 2474;
    
    // Get booking data
    echo "=== BOOKINGS TABLE ===\n";
    $bookingStmt = $conn->prepare("
        SELECT id, booking_id, guest_name, room_id, status, check_in, check_out
        FROM bookings 
        WHERE id = :id
    ");
    $bookingStmt->execute([':id' => $booking_id_to_check]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($booking) {
        echo "Found in bookings table:\n";
        echo "  ID (Primary Key): " . $booking['id'] . "\n";
        echo "  Booking ID (Code): " . $booking['booking_id'] . "\n";
        echo "  Guest Name: " . $booking['guest_name'] . "\n";
        echo "  Room ID: " . $booking['room_id'] . "\n";
        echo "  Status: " . $booking['status'] . "\n";
        echo "  Check-in: " . $booking['check_in'] . "\n";
        echo "  Check-out: " . $booking['check_out'] . "\n\n";
        
        $booking_code = $booking['booking_id'];
        
        // Get report data using the booking_id code
        echo "=== REPORTS TABLE ===\n";
        $reportStmt = $conn->prepare("
            SELECT id, booking_id, guest_name, room_id, status, check_in, check_out
            FROM reports 
            WHERE booking_id = :booking_id
        ");
        $reportStmt->execute([':booking_id' => $booking_code]);
        $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($report) {
            echo "Found in reports table:\n";
            echo "  ID (Primary Key): " . $report['id'] . "\n";
            echo "  Booking ID (Code): " . $report['booking_id'] . "\n";
            echo "  Guest Name: " . $report['guest_name'] . "\n";
            echo "  Room ID: " . $report['room_id'] . "\n";
            echo "  Status: " . $report['status'] . "\n";
            echo "  Check-in: " . $report['check_in'] . "\n";
            echo "  Check-out: " . $report['check_out'] . "\n\n";
            
            // Compare IDs
            echo "=== COMPARISON ===\n";
            if ($booking['id'] == $report['id']) {
                echo "✅ IDs MATCH!\n";
                echo "   Bookings ID: " . $booking['id'] . "\n";
                echo "   Reports ID:  " . $report['id'] . "\n";
            } else {
                echo "❌ IDs DO NOT MATCH!\n";
                echo "   Bookings ID: " . $booking['id'] . " ← Correct\n";
                echo "   Reports ID:  " . $report['id'] . " ← Wrong (should be " . $booking['id'] . ")\n\n";
                echo "🔧 FIX NEEDED:\n";
                echo "   Run: php fix_reports_id_sync.php\n";
                echo "   This will update reports.id from " . $report['id'] . " to " . $booking['id'] . "\n";
            }
        } else {
            echo "❌ NOT FOUND in reports table!\n";
            echo "   This booking exists in bookings but not in reports.\n";
            echo "   This might be normal if the booking hasn't been confirmed yet.\n";
        }
        
    } else {
        echo "❌ Booking ID {$booking_id_to_check} NOT FOUND in bookings table!\n";
        
        // Check if it exists in reports
        $reportStmt = $conn->prepare("
            SELECT id, booking_id, guest_name, room_id, status
            FROM reports 
            WHERE id = :id
        ");
        $reportStmt->execute([':id' => $booking_id_to_check]);
        $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($report) {
            echo "\nBut found in reports table:\n";
            echo "  ID: " . $report['id'] . "\n";
            echo "  Booking ID: " . $report['booking_id'] . "\n";
            echo "  Guest Name: " . $report['guest_name'] . "\n";
            echo "  Room ID: " . $report['room_id'] . "\n";
            echo "  Status: " . $report['status'] . "\n";
            echo "\nThis might be a checked-out or deleted booking.\n";
        }
    }
    
} catch (PDOException $e) {
    echo "\n❌ Database error: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
?>
