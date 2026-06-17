<?php

require_once 'config.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'column_added' => false
];

try {
    // Check if reports table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'reports'");
    if ($checkTable->rowCount() === 0) {
        $response['message'] = 'Reports table does not exist.';
        echo json_encode($response);
        exit;
    }
    
    // Check if additional_guest column already exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM reports LIKE 'additional_guest'");
    if ($checkColumn->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'additional_guest column already exists in reports table.';
        $response['column_added'] = false;
    } else {
        // Add the column after breakfast column
        $alterStmt = $conn->exec("
            ALTER TABLE reports 
            ADD COLUMN additional_guest INT DEFAULT 0 
            AFTER breakfast
        ");
        
        $response['success'] = true;
        $response['column_added'] = true;
        $response['message'] = 'Successfully added additional_guest column to reports table.';
    }
    
} catch(PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>
