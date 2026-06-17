<?php
// Prevent HTML error output - ensure JSON response even on errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Must be logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'config.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'counts' => [],
    'stats' => []
];

try {
    // Get date range from request
    $selectedRangeKey = strtolower($_GET['range'] ?? 'today');
    $customStart = $_GET['start_date'] ?? null;
    $customEnd = $_GET['end_date'] ?? null;

    // Build date range
    $filterStart = '';
    $filterEnd = '';

    switch ($selectedRangeKey) {
        case 'today':
            $filterStart = date('Y-m-d');
            $filterEnd = date('Y-m-d');
            break;
        case 'last_week':
            $filterStart = date('Y-m-d', strtotime('-7 days'));
            $filterEnd = date('Y-m-d');
            break;
        case 'last_month':
            $filterStart = date('Y-m-d', strtotime('-30 days'));
            $filterEnd = date('Y-m-d');
            break;
        case 'custom':
            if ($customStart && $customEnd) {
                $filterStart = $customStart;
                $filterEnd = $customEnd;
            } else {
                $filterStart = date('Y-m-d');
                $filterEnd = date('Y-m-d');
            }
            break;
        default:
            $filterStart = date('Y-m-d');
            $filterEnd = date('Y-m-d');
    }

    // Previous period calculation for comparison (vs Yesterday / Previous Period)
    $diff = strtotime($filterEnd) - strtotime($filterStart);
    // Previous period starts ($diff + 1 day) before filterStart
    $prevEnd = date('Y-m-d', strtotime($filterStart . ' -1 day'));
    $prevStart = date('Y-m-d', strtotime($prevEnd . ' -' . $diff . ' seconds'));

    // Broad start date to capture previous period base bookings & extensions
    $broadStart = $prevStart;

    // Helper function to map hours to columns
    function mapHoursToCategory($hours) {
        if ($hours <= 3) return '3hrs';
        if ($hours <= 6) return '6hrs';
        if ($hours <= 12) return '12hrs';
        if ($hours <= 24) return '24hrs';
        if ($hours <= 36) return '36hrs';
        return '48hrs';
    }

    // Structure for results
    $categories = ['3hrs', '6hrs', '12hrs', '24hrs', '36hrs', '48hrs'];
    $counts = [];
    foreach ($categories as $cat) {
        $counts[$cat] = [
            'AM' => 0,
            'PM' => 0,
            'Total' => 0
        ];
    }

    $processedBookings = [];
    
    // Quick stats variables
    $currentRevenue = 0;
    $prevRevenue = 0;
    $currentBookingCount = 0;
    $checkInHours = [];

    // Helper function to process a row
    $processRow = function($row) use (
        &$counts, &$processedBookings, &$currentRevenue, &$prevRevenue, 
        &$currentBookingCount, &$checkInHours, $filterStart, $filterEnd, $prevStart, $prevEnd
    ) {
        $bookingId = $row['booking_id'];
        if (in_array($bookingId, $processedBookings)) {
            return;
        }
        $processedBookings[] = $bookingId;

        $checkInDate = date('Y-m-d', strtotime($row['check_in']));
        $checkInHour = intval(date('H', strtotime($row['check_in'])));
        $checkInAmPm = ($checkInHour < 12) ? 'AM' : 'PM';

        $baseDuration = intval($row['duration'] ?? 0);
        $promoValue = $row['promo'] ?? '';

        // Determine base booking hours
        $baseHours = 0;
        if ($baseDuration > 0) {
            $baseHours = $baseDuration;
        } elseif (!empty($promoValue) && $promoValue !== 'None' && $promoValue !== 'Select Promo') {
            if (preg_match('/(\d+)\s*hrs?/i', $promoValue, $matches)) {
                $baseHours = intval($matches[1]);
            } else {
                $baseHours = 12; // default
            }
        }

        // 1. Process base booking for current period
        if ($checkInDate >= $filterStart && $checkInDate <= $filterEnd) {
            if ($baseHours > 0) {
                $cat = mapHoursToCategory($baseHours);
                $counts[$cat][$checkInAmPm]++;
                $counts[$cat]['Total']++;
                
                $currentRevenue += floatval($row['room_price'] ?? 0);
                $currentBookingCount++;
                $checkInHours[] = $checkInHour;
            }
        }
        // 2. Process base booking for previous period
        elseif ($checkInDate >= $prevStart && $checkInDate <= $prevEnd) {
            if ($baseHours > 0) {
                $prevRevenue += floatval($row['room_price'] ?? 0);
            }
        }

        // 3. Process extensions
        if (!empty($row['extension_stack']) && $row['extension_stack'] !== '[]') {
            $stack = json_decode($row['extension_stack'], true);
            $timestamps = explode('|', $row['extension_time_at'] ?? '');
            
            if (is_array($stack)) {
                foreach ($stack as $index => $segment) {
                    $timestamp = $timestamps[$index] ?? null;
                    if (!$timestamp) {
                        continue;
                    }
                    
                    $tsDate = date('Y-m-d', strtotime($timestamp));
                    $extHours = intval($segment['h'] ?? 0);
                    $extPrice = floatval($segment['price'] ?? 0);
                    
                    if ($extHours > 0) {
                        $extHour = intval(date('H', strtotime($timestamp)));
                        $extAmPm = ($extHour < 12) ? 'AM' : 'PM';
                        $cat = mapHoursToCategory($extHours);

                        // If extension falls in current period
                        if ($tsDate >= $filterStart && $tsDate <= $filterEnd) {
                            $counts[$cat][$extAmPm]++;
                            $counts[$cat]['Total']++;
                            
                            $currentRevenue += $extPrice;
                        }
                        // If extension falls in previous period
                        elseif ($tsDate >= $prevStart && $tsDate <= $prevEnd) {
                            $prevRevenue += $extPrice;
                        }
                    }
                }
            }
        }
    };

    // Query reports table
    $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
    if ($checkTable->rowCount() > 0) {
        $stmt = $conn->prepare("
            SELECT 
                booking_id, check_in, duration, duration_unit, promo, 
                extension_stack, extension_time_at, room_price, extend_price
            FROM reports
            WHERE 
                DATE(check_in) >= :broad_start
                OR (extension_stack IS NOT NULL AND extension_stack != '' AND extension_stack != '[]')
        ");
        $stmt->execute([':broad_start' => $broadStart]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            $processRow($row);
        }
    }

    // Query bookings table
    $checkBookingsTable = $conn->query("SHOW TABLES LIKE 'bookings'");
    if ($checkBookingsTable->rowCount() > 0) {
        $stmtBookings = $conn->prepare("
            SELECT 
                booking_id, check_in, duration, duration_unit, promo, 
                extension_stack, extension_time_at, room_price, extend_price
            FROM bookings
            WHERE 
                DATE(check_in) >= :broad_start
                OR (extension_stack IS NOT NULL AND extension_stack != '' AND extension_stack != '[]')
        ");
        $stmtBookings->execute([':broad_start' => $broadStart]);
        $resultsBookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);
        foreach ($resultsBookings as $row) {
            $processRow($row);
        }
    }

    // Calculate Peak Hour Range
    $peakHourRange = 'N/A';
    if (!empty($checkInHours)) {
        $hourCounts = array_count_values($checkInHours);
        arsort($hourCounts);
        $peakHour = key($hourCounts);
        
        $startHour = $peakHour;
        $endHour = ($peakHour + 2) % 24;
        
        $formatHour = function($h) {
            if ($h == 0) return '12 AM';
            if ($h == 12) return '12 PM';
            if ($h > 12) return ($h - 12) . ' PM';
            return $h . ' AM';
        };
        
        $peakHourRange = $formatHour($startHour) . '-' . $formatHour($endHour);
    }

    // Calculate percentage difference vs previous period
    $percentDiff = 0;
    $trend = 'flat';
    if ($prevRevenue > 0) {
        $percentDiff = round((($currentRevenue - $prevRevenue) / $prevRevenue) * 100, 1);
        $trend = ($percentDiff >= 0) ? 'up' : 'down';
    } elseif ($currentRevenue > 0) {
        $percentDiff = 100;
        $trend = 'up';
    }
    
    // Average Rate
    $avgRate = 0;
    if ($currentBookingCount > 0) {
        $avgRate = round($currentRevenue / $currentBookingCount, 2);
    }

    $response['success'] = true;
    $response['counts'] = $counts;
    $response['stats'] = [
        'total_bookings' => $currentBookingCount,
        'avg_rate' => $avgRate,
        'revenue_change_percent' => abs($percentDiff),
        'revenue_change_trend' => $trend,
        'peak_hour' => $peakHourRange,
        'current_revenue' => $currentRevenue,
        'prev_revenue' => $prevRevenue
    ];
    $response['message'] = 'Room sales tracking data loaded successfully.';

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
