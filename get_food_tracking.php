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
    'food_counts' => [],
    'total_count' => 0
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

    // Array to hold food item counts
    $foodCounts = [];
    $processedBookings = [];

    // Check if reports table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
    $hasReportsTable = $checkTable->rowCount() > 0;

    if ($hasReportsTable) {
        // Fetch all bookings from reports table with food
        $stmt = $conn->prepare("
            SELECT 
                booking_id,
                breakfast,
                breakfast_date,
                additional_food,
                additional_food_date,
                extend_bundle_breakfast,
                extend_bundle_breakfast_date
            FROM reports
            WHERE 
                (breakfast IS NOT NULL AND breakfast != '' AND breakfast != 'None')
                OR (additional_food IS NOT NULL AND additional_food != '' AND additional_food != 'None')
                OR (extend_bundle_breakfast IS NOT NULL AND extend_bundle_breakfast != '' AND extend_bundle_breakfast != 'None')
        ");
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process each booking
        foreach ($results as $row) {
            $bookingId = $row['booking_id'];
            if (in_array($bookingId, $processedBookings)) {
                continue;
            }

            $hasProcessedFood = false;

            // Process breakfast
            if (
                !empty($row['breakfast']) && $row['breakfast'] != 'None' &&
                isDateInRange($row['breakfast_date'], $filterStart, $filterEnd)
            ) {
                processFoodItem($row['breakfast'], $foodCounts);
                $hasProcessedFood = true;
            }

            // Process additional_food
            if (
                !empty($row['additional_food']) && $row['additional_food'] != 'None' &&
                isDateInRange($row['additional_food_date'], $filterStart, $filterEnd)
            ) {
                processFoodItem($row['additional_food'], $foodCounts);
                $hasProcessedFood = true;
            }

            // Process extend_bundle_breakfast
            if (
                !empty($row['extend_bundle_breakfast']) && $row['extend_bundle_breakfast'] != 'None' &&
                isDateInRange($row['extend_bundle_breakfast_date'], $filterStart, $filterEnd)
            ) {
                processFoodItem($row['extend_bundle_breakfast'], $foodCounts);
                $hasProcessedFood = true;
            }

            if ($hasProcessedFood) {
                $processedBookings[] = $bookingId;
            }
        }
    }

    // Check bookings table as well for active bookings
    $checkBookingsTable = $conn->query("SHOW TABLES LIKE 'bookings'");
    $hasBookingsTable = $checkBookingsTable->rowCount() > 0;

    if ($hasBookingsTable) {
        $stmtBookings = $conn->prepare("
            SELECT 
                booking_id,
                breakfast,
                breakfast_date,
                additional_food,
                additional_food_date,
                extend_bundle_breakfast,
                extend_bundle_breakfast_date
            FROM bookings
            WHERE 
                (breakfast IS NOT NULL AND breakfast != '' AND breakfast != 'None')
                OR (additional_food IS NOT NULL AND additional_food != '' AND additional_food != 'None')
                OR (extend_bundle_breakfast IS NOT NULL AND extend_bundle_breakfast != '' AND extend_bundle_breakfast != 'None')
        ");
        $stmtBookings->execute();

        $resultsBookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

        // Process each booking
        foreach ($resultsBookings as $row) {
            $bookingId = $row['booking_id'];
            if (in_array($bookingId, $processedBookings)) {
                continue;
            }

            $hasProcessedFood = false;

            // Process breakfast
            if (
                !empty($row['breakfast']) && $row['breakfast'] != 'None' &&
                isDateInRange($row['breakfast_date'], $filterStart, $filterEnd)
            ) {
                processFoodItem($row['breakfast'], $foodCounts);
                $hasProcessedFood = true;
            }

            // Process additional_food
            if (
                !empty($row['additional_food']) && $row['additional_food'] != 'None' &&
                isDateInRange($row['additional_food_date'], $filterStart, $filterEnd)
            ) {
                processFoodItem($row['additional_food'], $foodCounts);
                $hasProcessedFood = true;
            }

            // Process extend_bundle_breakfast
            if (
                !empty($row['extend_bundle_breakfast']) && $row['extend_bundle_breakfast'] != 'None' &&
                isDateInRange($row['extend_bundle_breakfast_date'], $filterStart, $filterEnd)
            ) {
                processFoodItem($row['extend_bundle_breakfast'], $foodCounts);
                $hasProcessedFood = true;
            }

            if ($hasProcessedFood) {
                $processedBookings[] = $bookingId;
            }
        }
    }

    // Convert associative array to indexed array for JSON response
    $foodCountsArray = [];
    foreach ($foodCounts as $foodName => $count) {
        $foodCountsArray[] = [
            'food_name' => $foodName,
            'count' => $count
        ];
    }

    // Calculate total count (sum of all food quantities)
    $totalCount = array_sum($foodCounts);

    $response['success'] = true;
    $response['food_counts'] = $foodCountsArray;
    $response['total_count'] = $totalCount;
    $response['message'] = 'Food tracking data loaded successfully.';

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);

// Helper function to check if any of the dates in the JSON array or single string date falls in range
function isDateInRange($dateVal, $filterStart, $filterEnd)
{
    if (empty($dateVal)) {
        return false;
    }

    // Try to decode JSON array
    $dates = json_decode($dateVal, true);
    if (!is_array($dates)) {
        // Fallback for single date string
        $dates = [$dateVal];
    }

    $startTimestamp = strtotime($filterStart);
    $endTimestamp = strtotime($filterEnd . ' 23:59:59');

    foreach ($dates as $d) {
        $ts = strtotime($d);
        if ($ts !== false && $ts >= $startTimestamp && $ts <= $endTimestamp) {
            return true;
        }
    }

    return false;
}

// Helper function to process food items
function processFoodItem($foodString, &$foodCounts)
{
    if (empty($foodString) || $foodString === 'None') {
        return;
    }

    // --- Handle legacy JSON array format ---
    // e.g. [{"ID":"LEGACY-...","TYPE":"FOOD","QUANTITY":1,"SELECTEDITEM":"LONGGANISA","PRICE":150}]
    $trimmed = trim($foodString);
    if (substr($trimmed, 0, 1) === '[') {
        $jsonItems = json_decode($trimmed, true);
        if (is_array($jsonItems)) {
            foreach ($jsonItems as $jsonItem) {
                if (!is_array($jsonItem)) continue;
                // Support both upper and lower case keys
                $selectedItem = $jsonItem['SELECTEDITEM'] ?? $jsonItem['selecteditem'] ?? $jsonItem['food_name'] ?? null;
                $quantity     = intval($jsonItem['QUANTITY']  ?? $jsonItem['quantity']  ?? 1);
                if (!empty($selectedItem)) {
                    $foodName = ucwords(strtolower(trim($selectedItem)));
                    if (!isset($foodCounts[$foodName])) {
                        $foodCounts[$foodName] = 0;
                    }
                    $foodCounts[$foodName] += $quantity;
                }
            }
            return; // Fully handled as JSON, no further parsing needed
        }
    }

    // Split by pipe or newlines
    $items = preg_split('/[|\n\r]+/', $foodString);

    foreach ($items as $item) {
        $item = trim($item);
        if (empty($item)) {
            continue;
        }

        // Parse format: "2 Cornbeef (Promo) | 1 Longganisa (Promo)"
        // or "1 Cornbeef = ₱150.00"
        // Extract food name and quantity

        // Pattern 1: "2 Cornbeef (Promo)"
        if (preg_match('/^(\d+)\s+(.+?)(?:\s+\(Promo\))?$/i', $item, $matches)) {
            $quantity = intval($matches[1]);
            $foodName = trim($matches[2]);
            // Remove price if present
            $foodName = preg_replace('/\s*[-=]\s*₱?[\d,]+\.?\d*/i', '', $foodName);
            $foodName = ucwords(strtolower($foodName));
        }
        // Pattern 2: "Cornbeef = ₱150.00" or "Cornbeef - ₱150.00"
        elseif (preg_match('/^(.+?)\s*[-=]\s*₱?[\d,]+\.?\d*/i', $item, $matches)) {
            $quantity = 1;
            $foodName = trim($matches[1]);
            $foodName = preg_replace('/\s*\(Promo\)/i', '', $foodName);
            $foodName = ucwords(strtolower($foodName));
        }
        // Pattern 3: Just the food name
        else {
            $quantity = 1;
            $foodName = preg_replace('/\s*\(Promo\)/i', '', $item);
            $foodName = preg_replace('/\s*[-=]\s*₱?[\d,]+\.?\d*/i', '', $foodName);
            $foodName = ucwords(strtolower($foodName));
        }

        if (!empty($foodName)) {
            if (!isset($foodCounts[$foodName])) {
                $foodCounts[$foodName] = 0;
            }
            $foodCounts[$foodName] += $quantity;
        }
    }
}
?>