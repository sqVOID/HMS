<?php
/**
 * Extension Stack Manager
 * Provides functions to manage extension stacks in bookings and reports
 */

require_once 'config.php';
require_once 'report_helpers.php';

class ExtensionStackManager {
    private $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get extension stack for a booking
     */
    public function getExtensionStack($bookingId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT extension_stack, extend_hours, extend_minutes, extend_price,
                       extend_regular_rate, extend_bundle_rate, extend_bundle_breakfast,
                       extension_time_at
                FROM bookings 
                WHERE id = :booking_id
            ");
            $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                return ['success' => false, 'message' => 'Booking not found'];
            }
            
            $stack = booking_extension_stack_bootstrap_from_row($result);
            
            return [
                'success' => true,
                'stack' => $stack,
                'aggregated' => booking_extension_stack_aggregate_segments($stack),
                'extension_time_at' => $result['extension_time_at'],
                'raw_data' => $result
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Add extension segment to stack
     */
    public function addExtensionSegment($bookingId, $hours, $minutes, $price, $regularRate = 0, $bundleRate = 0, $breakfast = null) {
        try {
            // Get current stack
            $currentData = $this->getExtensionStack($bookingId);
            if (!$currentData['success']) {
                return $currentData;
            }
            
            $stack = $currentData['stack'];
            
            // Add new segment
            $newSegment = [
                'h' => intval($hours),
                'm' => intval($minutes),
                'price' => floatval($price),
                'reg' => floatval($regularRate),
                'bun' => floatval($bundleRate),
                'bf' => ($breakfast && trim($breakfast) !== '') ? trim($breakfast) : null
            ];
            
            $stack[] = $newSegment;
            
            // Aggregate totals
            $aggregated = booking_extension_stack_aggregate_segments($stack);
            $encodedStack = booking_extension_stack_encode($stack);
            
            // Update extension timestamp
            $currentTimestamp = $currentData['raw_data']['extension_time_at'] ?? '';
            $newTimestamp = date('Y-m-d H:i:s');
            $updatedTimestamp = $currentTimestamp ? $currentTimestamp . '|' . $newTimestamp : $newTimestamp;
            
            // Update booking
            $stmt = $this->conn->prepare("
                UPDATE bookings 
                SET extension_stack = :stack,
                    extend_hours = :hours,
                    extend_minutes = :minutes,
                    extend_price = :price,
                    extend_regular_rate = :reg_rate,
                    extend_bundle_rate = :bun_rate,
                    extend_bundle_breakfast = :breakfast,
                    extension_time_at = :timestamp
                WHERE id = :booking_id
            ");
            
            $stmt->execute([
                ':stack' => $encodedStack,
                ':hours' => $aggregated['h'],
                ':minutes' => $aggregated['m'],
                ':price' => $aggregated['price'],
                ':reg_rate' => $aggregated['reg'],
                ':bun_rate' => $aggregated['bun'],
                ':breakfast' => $aggregated['bf'],
                ':timestamp' => $updatedTimestamp,
                ':booking_id' => $bookingId
            ]);
            
            return [
                'success' => true,
                'message' => 'Extension segment added successfully',
                'stack' => $stack,
                'aggregated' => $aggregated,
                'new_segment' => $newSegment
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Remove last extension segment from stack
     */
    public function removeLastExtensionSegment($bookingId) {
        try {
            // Get current stack
            $currentData = $this->getExtensionStack($bookingId);
            if (!$currentData['success']) {
                return $currentData;
            }
            
            $stack = $currentData['stack'];
            
            if (empty($stack)) {
                return ['success' => false, 'message' => 'No extension segments to remove'];
            }
            
            // Remove last segment
            $removedSegment = array_pop($stack);
            
            // Aggregate remaining segments
            $aggregated = booking_extension_stack_aggregate_segments($stack);
            $encodedStack = booking_extension_stack_encode($stack);
            
            // Update booking
            $stmt = $this->conn->prepare("
                UPDATE bookings 
                SET extension_stack = :stack,
                    extend_hours = :hours,
                    extend_minutes = :minutes,
                    extend_price = :price,
                    extend_regular_rate = :reg_rate,
                    extend_bundle_rate = :bun_rate,
                    extend_bundle_breakfast = :breakfast
                WHERE id = :booking_id
            ");
            
            $stmt->execute([
                ':stack' => $encodedStack,
                ':hours' => $aggregated['h'],
                ':minutes' => $aggregated['m'],
                ':price' => $aggregated['price'],
                ':reg_rate' => $aggregated['reg'],
                ':bun_rate' => $aggregated['bun'],
                ':breakfast' => $aggregated['bf'],
                ':booking_id' => $bookingId
            ]);
            
            return [
                'success' => true,
                'message' => 'Last extension segment removed successfully',
                'removed_segment' => $removedSegment,
                'remaining_stack' => $stack,
                'aggregated' => $aggregated
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get extension history with detailed breakdown
     */
    public function getExtensionHistory($bookingId) {
        try {
            $stackData = $this->getExtensionStack($bookingId);
            if (!$stackData['success']) {
                return $stackData;
            }
            
            $stack = $stackData['stack'];
            $timestamps = explode('|', $stackData['extension_time_at'] ?? '');
            
            $history = [];
            foreach ($stack as $index => $segment) {
                $history[] = [
                    'segment_number' => $index + 1,
                    'hours' => $segment['h'],
                    'minutes' => $segment['m'],
                    'price' => $segment['price'],
                    'regular_rate' => $segment['reg'],
                    'bundle_rate' => $segment['bun'],
                    'breakfast' => $segment['bf'],
                    'timestamp' => $timestamps[$index] ?? null,
                    'formatted_duration' => $this->formatDuration($segment['h'], $segment['m']),
                    'formatted_price' => '₱' . number_format($segment['price'], 2)
                ];
            }
            
            return [
                'success' => true,
                'history' => $history,
                'total_segments' => count($stack),
                'aggregated' => $stackData['aggregated']
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get extension statistics for reporting
     */
    public function getExtensionStatistics($startDate = null, $endDate = null) {
        try {
            $whereClause = "WHERE extension_stack IS NOT NULL AND extension_stack != ''";
            $params = [];
            
            if ($startDate && $endDate) {
                $whereClause .= " AND DATE(check_in) BETWEEN :start_date AND :end_date";
                $params[':start_date'] = $startDate;
                $params[':end_date'] = $endDate;
            }
            
            $stmt = $this->conn->prepare("
                SELECT 
                    booking_id,
                    guest_name,
                    room_id,
                    room_type,
                    check_in,
                    check_out,
                    extension_stack,
                    extend_hours,
                    extend_minutes,
                    extend_price,
                    extension_time_at
                FROM bookings 
                $whereClause
                ORDER BY check_in DESC
            ");
            
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $statistics = [
                'total_bookings_with_extensions' => count($results),
                'total_extension_revenue' => 0,
                'total_extension_hours' => 0,
                'average_extensions_per_booking' => 0,
                'bookings' => []
            ];
            
            foreach ($results as $booking) {
                $stack = booking_extension_stack_decode($booking['extension_stack']);
                $segmentCount = count($stack);
                
                $statistics['total_extension_revenue'] += floatval($booking['extend_price']);
                $statistics['total_extension_hours'] += intval($booking['extend_hours']);
                
                $statistics['bookings'][] = [
                    'booking_id' => $booking['booking_id'],
                    'guest_name' => $booking['guest_name'],
                    'room_id' => $booking['room_id'],
                    'room_type' => $booking['room_type'],
                    'check_in' => $booking['check_in'],
                    'extension_segments' => $segmentCount,
                    'total_extension_hours' => intval($booking['extend_hours']),
                    'total_extension_price' => floatval($booking['extend_price']),
                    'extension_timestamps' => $booking['extension_time_at']
                ];
            }
            
            if (count($results) > 0) {
                $totalSegments = array_sum(array_column($statistics['bookings'], 'extension_segments'));
                $statistics['average_extensions_per_booking'] = round($totalSegments / count($results), 2);
            }
            
            return [
                'success' => true,
                'statistics' => $statistics
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
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

// Usage examples:
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    // This code runs only when the file is accessed directly
    
    header('Content-Type: application/json');
    
    $manager = new ExtensionStackManager($conn);
    
    // Handle different actions via GET/POST
    $action = $_GET['action'] ?? $_POST['action'] ?? 'help';
    
    switch ($action) {
        case 'get_stack':
            $bookingId = $_GET['booking_id'] ?? $_POST['booking_id'] ?? null;
            if (!$bookingId) {
                echo json_encode(['success' => false, 'message' => 'Booking ID required']);
                break;
            }
            echo json_encode($manager->getExtensionStack($bookingId));
            break;
            
        case 'get_history':
            $bookingId = $_GET['booking_id'] ?? $_POST['booking_id'] ?? null;
            if (!$bookingId) {
                echo json_encode(['success' => false, 'message' => 'Booking ID required']);
                break;
            }
            echo json_encode($manager->getExtensionHistory($bookingId));
            break;
            
        case 'get_statistics':
            $startDate = $_GET['start_date'] ?? $_POST['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? $_POST['end_date'] ?? null;
            echo json_encode($manager->getExtensionStatistics($startDate, $endDate));
            break;
            
        case 'help':
        default:
            echo json_encode([
                'success' => true,
                'message' => 'Extension Stack Manager API',
                'available_actions' => [
                    'get_stack' => 'Get extension stack for a booking (requires booking_id)',
                    'get_history' => 'Get detailed extension history (requires booking_id)',
                    'get_statistics' => 'Get extension statistics (optional start_date, end_date)',
                ],
                'usage_examples' => [
                    'Get stack: ?action=get_stack&booking_id=123',
                    'Get history: ?action=get_history&booking_id=123',
                    'Get stats: ?action=get_statistics&start_date=2026-01-01&end_date=2026-01-31'
                ]
            ]);
            break;
    }
}
?>