<?php
/**
 * Extension Stack Report Generator
 * Generates detailed reports for extension tracking and analysis
 */

require_once 'config.php';
require_once 'report_helpers.php';
require_once 'extension_stack_manager.php';

class ExtensionStackReport {
    private $conn;
    private $manager;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
        $this->manager = new ExtensionStackManager($connection);
    }
    
    /**
     * Generate comprehensive extension report
     */
    public function generateExtensionReport($startDate = null, $endDate = null, $format = 'html') {
        try {
            // Get statistics
            $statsResult = $this->manager->getExtensionStatistics($startDate, $endDate);
            if (!$statsResult['success']) {
                return $statsResult;
            }
            
            $stats = $statsResult['statistics'];
            
            // Get detailed booking data
            $detailedData = $this->getDetailedExtensionData($startDate, $endDate);
            
            if ($format === 'json') {
                return [
                    'success' => true,
                    'report_data' => [
                        'summary' => $stats,
                        'detailed_bookings' => $detailedData,
                        'date_range' => [
                            'start' => $startDate,
                            'end' => $endDate
                        ]
                    ]
                ];
            }
            
            // Generate HTML report
            $html = $this->generateHTMLReport($stats, $detailedData, $startDate, $endDate);
            
            return [
                'success' => true,
                'html' => $html,
                'summary' => $stats
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error generating report: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get detailed extension data for bookings
     */
    private function getDetailedExtensionData($startDate = null, $endDate = null) {
        $whereClause = "WHERE extension_stack IS NOT NULL AND extension_stack != ''";
        $params = [];
        
        if ($startDate && $endDate) {
            $whereClause .= " AND DATE(check_in) BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        }
        
        $stmt = $this->conn->prepare("
            SELECT 
                id,
                booking_id,
                guest_name,
                room_id,
                room_type,
                check_in,
                check_out,
                duration,
                duration_unit,
                room_price,
                extension_stack,
                extend_hours,
                extend_minutes,
                extend_price,
                extend_regular_rate,
                extend_bundle_rate,
                extend_bundle_breakfast,
                extension_time_at,
                total_amount,
                paid_status
            FROM bookings 
            $whereClause
            ORDER BY check_in DESC
        ");
        
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $detailedData = [];
        
        foreach ($bookings as $booking) {
            $stack = booking_extension_stack_decode($booking['extension_stack']);
            $timestamps = explode('|', $booking['extension_time_at'] ?? '');
            
            $extensionDetails = [];
            foreach ($stack as $index => $segment) {
                $extensionDetails[] = [
                    'segment' => $index + 1,
                    'hours' => $segment['h'],
                    'minutes' => $segment['m'],
                    'duration_formatted' => $this->formatDuration($segment['h'], $segment['m']),
                    'price' => $segment['price'],
                    'regular_rate' => $segment['reg'],
                    'bundle_rate' => $segment['bun'],
                    'breakfast' => $segment['bf'],
                    'timestamp' => $timestamps[$index] ?? null,
                    'timestamp_formatted' => isset($timestamps[$index]) ? 
                        date('M j, Y g:i A', strtotime($timestamps[$index])) : 'N/A'
                ];
            }
            
            $detailedData[] = [
                'booking' => $booking,
                'extension_segments' => $extensionDetails,
                'segment_count' => count($stack),
                'total_extension_revenue' => floatval($booking['extend_price']),
                'original_duration' => $this->formatDuration(
                    convertDurationToHours($booking['duration'], $booking['duration_unit']), 
                    0
                ),
                'extended_duration' => $this->formatDuration(
                    intval($booking['extend_hours']), 
                    intval($booking['extend_minutes'])
                )
            ];
        }
        
        return $detailedData;
    }
    
    /**
     * Generate HTML report
     */
    private function generateHTMLReport($stats, $detailedData, $startDate, $endDate) {
        $dateRangeText = $startDate && $endDate ? 
            "From " . date('M j, Y', strtotime($startDate)) . " to " . date('M j, Y', strtotime($endDate)) :
            "All Time";
            
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Extension Stack Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .summary { background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 30px; }
                .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
                .summary-item { background: white; padding: 15px; border-radius: 5px; text-align: center; }
                .summary-item h3 { margin: 0 0 10px 0; color: #333; }
                .summary-item .value { font-size: 24px; font-weight: bold; color: #007bff; }
                .booking-card { border: 1px solid #ddd; margin-bottom: 20px; border-radius: 5px; }
                .booking-header { background: #007bff; color: white; padding: 15px; }
                .booking-content { padding: 15px; }
                .extension-segments { margin-top: 15px; }
                .segment { background: #f8f9fa; padding: 10px; margin: 5px 0; border-left: 4px solid #007bff; }
                .segment-header { font-weight: bold; color: #333; }
                .segment-details { margin-top: 5px; font-size: 14px; color: #666; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f2f2f2; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Extension Stack Report</h1>
                <p><strong>Report Period:</strong> {$dateRangeText}</p>
                <p><strong>Generated:</strong> " . date('M j, Y g:i A') . "</p>
            </div>
            
            <div class='summary'>
                <h2>Summary Statistics</h2>
                <div class='summary-grid'>
                    <div class='summary-item'>
                        <h3>Total Bookings with Extensions</h3>
                        <div class='value'>{$stats['total_bookings_with_extensions']}</div>
                    </div>
                    <div class='summary-item'>
                        <h3>Total Extension Revenue</h3>
                        <div class='value'>₱" . number_format($stats['total_extension_revenue'], 2) . "</div>
                    </div>
                    <div class='summary-item'>
                        <h3>Total Extension Hours</h3>
                        <div class='value'>{$stats['total_extension_hours']}</div>
                    </div>
                    <div class='summary-item'>
                        <h3>Avg Extensions per Booking</h3>
                        <div class='value'>{$stats['average_extensions_per_booking']}</div>
                    </div>
                </div>
            </div>
            
            <h2>Detailed Extension Records</h2>";
            
        foreach ($detailedData as $data) {
            $booking = $data['booking'];
            $segments = $data['extension_segments'];
            
            $html .= "
            <div class='booking-card'>
                <div class='booking-header'>
                    <strong>Booking ID:</strong> {$booking['booking_id']} | 
                    <strong>Guest:</strong> {$booking['guest_name']} | 
                    <strong>Room:</strong> {$booking['room_id']} ({$booking['room_type']})
                </div>
                <div class='booking-content'>
                    <table>
                        <tr>
                            <td><strong>Check-in:</strong></td>
                            <td>" . date('M j, Y g:i A', strtotime($booking['check_in'])) . "</td>
                            <td><strong>Check-out:</strong></td>
                            <td>" . date('M j, Y g:i A', strtotime($booking['check_out'])) . "</td>
                        </tr>
                        <tr>
                            <td><strong>Original Duration:</strong></td>
                            <td>{$data['original_duration']}</td>
                            <td><strong>Extended Duration:</strong></td>
                            <td>{$data['extended_duration']}</td>
                        </tr>
                        <tr>
                            <td><strong>Room Price:</strong></td>
                            <td>₱" . number_format($booking['room_price'], 2) . "</td>
                            <td><strong>Extension Revenue:</strong></td>
                            <td>₱" . number_format($data['total_extension_revenue'], 2) . "</td>
                        </tr>
                        <tr>
                            <td><strong>Total Amount:</strong></td>
                            <td>₱" . number_format($booking['total_amount'], 2) . "</td>
                            <td><strong>Payment Status:</strong></td>
                            <td>{$booking['paid_status']}</td>
                        </tr>
                    </table>
                    
                    <div class='extension-segments'>
                        <h4>Extension Segments ({$data['segment_count']} total)</h4>";
                        
            foreach ($segments as $segment) {
                $html .= "
                        <div class='segment'>
                            <div class='segment-header'>
                                Segment {$segment['segment']}: {$segment['duration_formatted']} - ₱" . number_format($segment['price'], 2) . "
                            </div>
                            <div class='segment-details'>
                                <strong>Timestamp:</strong> {$segment['timestamp_formatted']} | 
                                <strong>Regular Rate:</strong> ₱" . number_format($segment['regular_rate'], 2) . " | 
                                <strong>Bundle Rate:</strong> ₱" . number_format($segment['bundle_rate'], 2);
                                
                if ($segment['breakfast']) {
                    $html .= " | <strong>Breakfast:</strong> {$segment['breakfast']}";
                }
                
                $html .= "
                            </div>
                        </div>";
            }
            
            $html .= "
                    </div>
                </div>
            </div>";
        }
        
        $html .= "
            <div class='no-print' style='margin-top: 30px; text-align: center;'>
                <button onclick='window.print()'>Print Report</button>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Export extension data to CSV
     */
    public function exportToCSV($startDate = null, $endDate = null) {
        try {
            $detailedData = $this->getDetailedExtensionData($startDate, $endDate);
            
            $csvData = [];
            $csvData[] = [
                'Booking ID', 'Guest Name', 'Room ID', 'Room Type', 'Check In', 'Check Out',
                'Original Duration', 'Extension Segments', 'Total Extension Hours', 'Total Extension Minutes',
                'Extension Revenue', 'Room Price', 'Total Amount', 'Payment Status',
                'Extension Timestamps', 'Extension Details'
            ];
            
            foreach ($detailedData as $data) {
                $booking = $data['booking'];
                $segments = $data['extension_segments'];
                
                $extensionDetails = [];
                $timestamps = [];
                
                foreach ($segments as $segment) {
                    $extensionDetails[] = "Segment {$segment['segment']}: {$segment['duration_formatted']} - ₱" . number_format($segment['price'], 2);
                    $timestamps[] = $segment['timestamp_formatted'];
                }
                
                $csvData[] = [
                    $booking['booking_id'],
                    $booking['guest_name'],
                    $booking['room_id'],
                    $booking['room_type'],
                    $booking['check_in'],
                    $booking['check_out'],
                    $data['original_duration'],
                    $data['segment_count'],
                    $booking['extend_hours'],
                    $booking['extend_minutes'],
                    $booking['extend_price'],
                    $booking['room_price'],
                    $booking['total_amount'],
                    $booking['paid_status'],
                    implode(' | ', $timestamps),
                    implode(' | ', $extensionDetails)
                ];
            }
            
            return [
                'success' => true,
                'csv_data' => $csvData,
                'filename' => 'extension_stack_report_' . date('Y-m-d_H-i-s') . '.csv'
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error exporting CSV: ' . $e->getMessage()];
        }
    }
    
    /**
     * Format duration for display
     */
    private function formatDuration($hours, $minutes) {
        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } elseif ($minutes > 0) {
            return "{$minutes}m";
        }
        return "0m";
    }
}

// Handle direct access
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    $report = new ExtensionStackReport($conn);
    
    $action = $_GET['action'] ?? 'html_report';
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    $format = $_GET['format'] ?? 'html';
    
    switch ($action) {
        case 'json_report':
            header('Content-Type: application/json');
            echo json_encode($report->generateExtensionReport($startDate, $endDate, 'json'));
            break;
            
        case 'csv_export':
            $csvResult = $report->exportToCSV($startDate, $endDate);
            if ($csvResult['success']) {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $csvResult['filename'] . '"');
                
                $output = fopen('php://output', 'w');
                foreach ($csvResult['csv_data'] as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
            } else {
                header('Content-Type: application/json');
                echo json_encode($csvResult);
            }
            break;
            
        case 'html_report':
        default:
            $reportResult = $report->generateExtensionReport($startDate, $endDate, 'html');
            if ($reportResult['success']) {
                echo $reportResult['html'];
            } else {
                echo "<h1>Error</h1><p>" . htmlspecialchars($reportResult['message']) . "</p>";
            }
            break;
    }
}
?>