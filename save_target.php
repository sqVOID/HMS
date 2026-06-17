<?php
// Start output buffering to prevent any accidental output
ob_start();

// Prevent browser caching
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header('Content-Type: application/json');

// Clear any previous output
ob_clean();

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Turn off error reporting to prevent notices from corrupting JSON
error_reporting(0);
ini_set('display_errors', 0);

try {
    // Check if user is authenticated
    if (!isset($_SESSION['username'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Not authenticated'
        ]);
        exit;
    }

    // Check access level - only admin, super_admin, and auditor can edit target
    $allowedRoles = ['super_admin', 'admin', 'auditor'];
    $userAccessLevel = $_SESSION['access_level'] ?? 'user';
    
    if (!in_array($userAccessLevel, $allowedRoles)) {
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. You do not have permission to edit sales target.'
        ]);
        exit;
    }

    require_once 'config.php';

    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['target'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid input data'
        ]);
        exit;
    }

    // Validate and sanitize input
    $target = floatval($data['target']);
    $updated_by = $_SESSION['username'];

    // Create sales_metrics table if it doesn't exist
    $createTable = "CREATE TABLE IF NOT EXISTS sales_metrics (
        id INT PRIMARY KEY AUTO_INCREMENT,
        target DECIMAL(15,2) DEFAULT 0.00,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100)
    )";
    
    $conn->exec($createTable);

    // Check if record exists
    $checkQuery = "SELECT id FROM sales_metrics LIMIT 1";
    $checkResult = $conn->query($checkQuery);

    if ($checkResult && $checkResult->rowCount() > 0) {
        // Update existing record
        $updateQuery = "UPDATE sales_metrics 
                        SET target = :target, 
                            updated_by = :updated_by,
                            updated_at = CURRENT_TIMESTAMP
                        ORDER BY id DESC
                        LIMIT 1";
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->bindParam(':target', $target);
        $stmt->bindParam(':updated_by', $updated_by);
    } else {
        // Insert new record
        $insertQuery = "INSERT INTO sales_metrics (target, updated_by) VALUES (:target, :updated_by)";
        
        $stmt = $conn->prepare($insertQuery);
        $stmt->bindParam(':target', $target);
        $stmt->bindParam(':updated_by', $updated_by);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Target saved successfully',
            'data' => [
                'target' => $target,
                'updated_by' => $updated_by,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error saving target: ' . implode(', ', $stmt->errorInfo())
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
