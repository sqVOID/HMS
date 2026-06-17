<?php
/**
 * One-time script to update existing reports with missing items fees
 * to mark their additional_fees_status as 'Paid'
 */

require_once 'config.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'updated_count' => 0
];

try {
    // Check if reports table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
    if ($checkTable->rowCount() === 0) {
        $response['message'] = 'Reports table does not exist.';
        echo json_encode($response);
        exit;
    }
    
    // Check if the required columns exist
    $checkColumns = $conn->query("SHOW COLUMNS FROM reports LIKE 'additional_fees_status'");
    if ($checkColumns->rowCount() === 0) {
        $response['message'] = 'additional_fees_status column does not exist in reports table.';
        echo json_encode($response);
        exit;
    }
    
    // Update all records where missing_items_fees > 0 but additional_fees_status is 'None' or NULL
    $updateStmt = $conn->prepare("
        UPDATE reports 
        SET additional_fees_status = 'Paid' 
        WHERE COALESCE(missing_items_fees, 0) > 0 
          AND (additional_fees_status = 'None' OR additional_fees_status IS NULL)
    ");
    
    $updateStmt->execute();
    $updatedCount = $updateStmt->rowCount();
    
    $response['success'] = true;
    $response['updated_count'] = $updatedCount;
    $response['message'] = "Successfully updated {$updatedCount} record(s). All additional fees are now marked as 'Paid'.";
    
} catch(PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>
