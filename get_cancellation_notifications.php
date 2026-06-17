<?php
// Start output buffering
ob_start();

// Disable all error output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

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
if ($access_level !== 'admin' && $access_level !== 'super_admin') {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true, 'notifications' => []]);
    ob_end_flush();
    exit;
}

require_once 'config.php';

try {
    // Set MySQL timezone to match PHP timezone
    $conn->exec("SET time_zone = '+08:00'"); // Adjust to your timezone offset

    // First check if requested_by and requested_at columns exist
    $checkColumns = $conn->query("SHOW COLUMNS FROM cancellation_requests LIKE 'requested_by'");
    $hasRequestedBy = ($checkColumns && $checkColumns->rowCount() > 0);

    $checkColumns2 = $conn->query("SHOW COLUMNS FROM cancellation_requests LIKE 'requested_at'");
    $hasRequestedAt = ($checkColumns2 && $checkColumns2->rowCount() > 0);

    // Build SQL based on available columns
    if ($hasRequestedBy && $hasRequestedAt) {
        $sql = "SELECT 
                    cr.id,
                    cr.booking_id,
                    cr.guest_name,
                    cr.requested_by,
                    cr.requested_at,
                    b.booking_id as booking_number,
                    u.first_name
                FROM cancellation_requests cr
                LEFT JOIN bookings b ON cr.booking_id = b.id
                LEFT JOIN users u ON cr.requested_by COLLATE utf8mb4_unicode_ci = u.username
                WHERE cr.status = 'Pending'
                ORDER BY cr.requested_at DESC
                LIMIT 10";
    }
    else {
        // Fallback to created_at if requested_at doesn't exist
        $sql = "SELECT 
                    cr.id,
                    cr.booking_id,
                    cr.guest_name,
                    cr.guest_name as requested_by,
                    cr.created_at as requested_at,
                    b.booking_id as booking_number,
                    NULL as first_name
                FROM cancellation_requests cr
                LEFT JOIN bookings b ON cr.booking_id = b.id
                WHERE cr.status = 'Pending'
                ORDER BY cr.id DESC
                LIMIT 10";
    }

    $stmt = $conn->query($sql);

    $notifications = [];
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Calculate time ago from requested_at
            try {
                $requestedAt = new DateTime($row['requested_at']);
                $now = new DateTime();
                $diff = $now->diff($requestedAt);

                if ($diff->d > 0) {
                    $timeAgo = $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
                }
                elseif ($diff->h > 0) {
                    $timeAgo = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                }
                elseif ($diff->i > 0) {
                    $timeAgo = $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
                }
                else {
                    $timeAgo = 'Just now';
                }
            }
            catch (Exception $e) {
                $timeAgo = 'Recently';
            }

            $requestedBy = !empty($row['requested_by']) ? $row['requested_by'] : $row['guest_name'];
            // Use first_name if available, otherwise fall back to requested_by (username)
            $displayName = !empty($row['first_name']) ? $row['first_name'] : $requestedBy;

            $notifications[] = [
                'id' => (int)$row['id'],
                'guest_name' => $row['guest_name'] ?? 'Unknown',
                'requested_by' => $displayName,
                'booking_number' => $row['booking_number'] ?? 'N/A',
                'time_ago' => $timeAgo,
                'requested_at' => $row['requested_at'] ?? date('Y-m-d H:i:s')
            ];
        }
    }

    // Clear output buffer
    if (ob_get_length()) ob_clean();

    echo json_encode(['success' => true, 'notifications' => $notifications], JSON_UNESCAPED_UNICODE);
    
    ob_end_flush();
}
catch (Exception $e) {
    // Clear output buffer
    if (ob_get_length()) ob_clean();
    
    // Return empty array on any error to prevent breaking the UI
    echo json_encode(['success' => true, 'notifications' => []]);
    
    ob_end_flush();
}
?>
