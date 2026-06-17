<?php
// Debug version with error reporting enabled
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set timezone to match your local timezone (adjust as needed)
date_default_timezone_set('Asia/Manila'); // Change this to your timezone

// Prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();

// Check if user is admin or super_admin
$access_level = $_SESSION['access_level'] ?? 'user';
echo "Access level: " . $access_level . "\n";

if ($access_level !== 'admin' && $access_level !== 'super_admin') {
    echo json_encode(['success' => true, 'notifications' => [], 'debug' => 'Access denied']);
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'hotel_management';
$username = 'root';
$password = '';

try {
    $conn = new mysqli($host, $username, $password, $dbname);

    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed', 'notifications' => []]);
        exit;
    }
    
    // Set MySQL timezone to match PHP timezone
    $conn->query("SET time_zone = '+08:00'"); // Adjust to your timezone offset

    // First check if requested_by and requested_at columns exist
    $checkColumns = $conn->query("SHOW COLUMNS FROM cancellation_requests LIKE 'requested_by'");
    $hasRequestedBy = ($checkColumns && $checkColumns->num_rows > 0);
    
    $checkColumns2 = $conn->query("SHOW COLUMNS FROM cancellation_requests LIKE 'requested_at'");
    $hasRequestedAt = ($checkColumns2 && $checkColumns2->num_rows > 0);

    $debug_info = [
        'hasRequestedBy' => $hasRequestedBy,
        'hasRequestedAt' => $hasRequestedAt
    ];

    // Build SQL based on available columns
    if ($hasRequestedBy && $hasRequestedAt) {
        $sql = "SELECT 
                    cr.id,
                    cr.booking_id,
                    cr.guest_name,
                    cr.requested_by,
                    cr.requested_at,
                    cr.status,
                    b.booking_id as booking_number,
                    u.first_name,
                    u.username
                FROM cancellation_requests cr
                LEFT JOIN bookings b ON cr.booking_id = b.id
                LEFT JOIN users u ON LOWER(cr.requested_by) = LOWER(u.username)
                WHERE cr.status = 'Pending'
                ORDER BY cr.requested_at DESC
                LIMIT 10";
    } else {
        // Fallback to created_at if requested_at doesn't exist
        $sql = "SELECT 
                    cr.id,
                    cr.booking_id,
                    cr.guest_name,
                    cr.guest_name as requested_by,
                    cr.created_at as requested_at,
                    cr.status,
                    b.booking_id as booking_number,
                    '' as first_name,
                    cr.guest_name as username
                FROM cancellation_requests cr
                LEFT JOIN bookings b ON cr.booking_id = b.id
                WHERE cr.status = 'Pending'
                ORDER BY cr.id DESC
                LIMIT 10";
    }

    $debug_info['sql'] = $sql;

    $result = $conn->query($sql);
    
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error, 'debug' => $debug_info]);
        exit;
    }

    $debug_info['num_rows'] = $result->num_rows;

    $notifications = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Calculate time ago from requested_at
            try {
                $requestedAt = new DateTime($row['requested_at']);
                $now = new DateTime();
                $diff = $now->diff($requestedAt);
                
                if ($diff->d > 0) {
                    $timeAgo = $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
                } elseif ($diff->h > 0) {
                    $timeAgo = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                } elseif ($diff->i > 0) {
                    $timeAgo = $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
                } else {
                    $timeAgo = 'Just now';
                }
            } catch (Exception $e) {
                $timeAgo = 'Recently';
            }
            
            $requestedBy = !empty($row['requested_by']) ? $row['requested_by'] : $row['guest_name'];
            
            // Use first name if available, otherwise fall back to username or guest name
            $displayName = '';
            if (!empty($row['first_name'])) {
                $displayName = $row['first_name'];
            } elseif (!empty($row['username'])) {
                $displayName = $row['username'];
            } else {
                $displayName = $requestedBy;
            }
            
            $notifications[] = [
                'id' => (int)$row['id'],
                'guest_name' => $row['guest_name'] ?? 'Unknown',
                'requested_by' => $requestedBy ?? 'Unknown',
                'display_name' => $displayName ?? 'Unknown',
                'booking_number' => $row['booking_number'] ?? 'N/A',
                'time_ago' => $timeAgo,
                'requested_at' => $row['requested_at'] ?? date('Y-m-d H:i:s'),
                'raw_data' => $row // Include raw data for debugging
            ];
        }
    }

    echo json_encode(['success' => true, 'notifications' => $notifications, 'debug' => $debug_info], JSON_UNESCAPED_UNICODE);

    $conn->close();
} catch (Exception $e) {
    // Return error details for debugging
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'notifications' => []]);
}
?>