<?php
require_once 'config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method!';
    echo json_encode($response);
    exit;
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$additional_fees_status = trim($_POST['additional_fees_status'] ?? 'Pending');
$missing_items_fees = floatval($_POST['missing_items_fees'] ?? 0);
$missing_items_list = $_POST['missing_items_list'] ?? '[]';
$additional_fees_payment_method = trim($_POST['additional_fees_payment_method'] ?? '');
$additional_fees_reference_no = trim($_POST['additional_fees_reference_no'] ?? '');

$allowedStatuses = ['None', 'Pending', 'Paid'];
if (!in_array($additional_fees_status, $allowedStatuses, true)) {
    $additional_fees_status = 'Pending';
}

if ($booking_id <= 0) {
    $response['message'] = 'Booking ID is required!';
    echo json_encode($response);
    exit;
}

try {
    // Ensure missing_items_fees column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'missing_items_fees'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN missing_items_fees DECIMAL(10,2) DEFAULT 0");
        }
    } catch(PDOException $e) {
        error_log("Failed to check/add missing_items_fees column: " . $e->getMessage());
    }
    
    // Ensure missing_items_list column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'missing_items_list'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN missing_items_list TEXT NULL DEFAULT NULL");
        }
    } catch(PDOException $e) {
        error_log("Failed to check/add missing_items_list column: " . $e->getMessage());
    }
    
    // Ensure additional_fees_status column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'additional_fees_status'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN additional_fees_status VARCHAR(50) DEFAULT 'None'");
        }
    } catch(PDOException $e) {
        error_log("Failed to check/add additional_fees_status column: " . $e->getMessage());
    }
    
    // Ensure additional_fees_payment_method column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'additional_fees_payment_method'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN additional_fees_payment_method VARCHAR(50) NULL DEFAULT NULL AFTER additional_fees_status");
        }
    } catch(PDOException $e) {
        error_log("Failed to check/add additional_fees_payment_method column: " . $e->getMessage());
    }
    
    // Ensure additional_fees_reference_no column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'additional_fees_reference_no'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN additional_fees_reference_no VARCHAR(255) NULL DEFAULT NULL AFTER additional_fees_payment_method");
        }
    } catch(PDOException $e) {
        error_log("Failed to check/add additional_fees_reference_no column: " . $e->getMessage());
    }
    
    // Ensure additional_fees_paid_date column exists
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'additional_fees_paid_date'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE bookings ADD COLUMN additional_fees_paid_date DATETIME NULL DEFAULT NULL AFTER additional_fees_reference_no");
        }
    } catch(PDOException $e) {
        error_log("Failed to check/add additional_fees_paid_date column: " . $e->getMessage());
    }
    
    // If only updating status (marking as paid), keep existing fees
    if ($additional_fees_status === 'Paid' && $missing_items_fees == 0) {
        $getFeesStmt = $conn->prepare("SELECT missing_items_fees, missing_items_list FROM bookings WHERE id = :id");
        $getFeesStmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
        $getFeesStmt->execute();
        $existingFees = $getFeesStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingFees) {
            $missing_items_fees = floatval($existingFees['missing_items_fees'] ?? 0);
            $missing_items_list = $existingFees['missing_items_list'] ?? '[]';
        }
    }
    
    // Normalize payment method - set to NULL if empty
    if ($additional_fees_payment_method === '' || $additional_fees_payment_method === 'Select Method') {
        $additional_fees_payment_method = null;
    }
    
    // Normalize reference number - set to NULL if empty
    if ($additional_fees_reference_no === '') {
        $additional_fees_reference_no = null;
    }
    
    // Set paid date when status is changed to 'Paid'
    $additional_fees_paid_date = null;
    if ($additional_fees_status === 'Paid') {
        $additional_fees_paid_date = date('Y-m-d H:i:s');
    }
    
    // Update booking with additional fees status, fees, payment method, reference number, and paid date
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET additional_fees_status = :additional_fees_status, 
            missing_items_fees = :missing_items_fees,
            missing_items_list = :missing_items_list,
            additional_fees_payment_method = :additional_fees_payment_method,
            additional_fees_reference_no = :additional_fees_reference_no,
            additional_fees_paid_date = :additional_fees_paid_date
        WHERE id = :id
    ");
    $stmt->bindParam(':additional_fees_status', $additional_fees_status);
    $stmt->bindParam(':missing_items_fees', $missing_items_fees);
    $stmt->bindParam(':missing_items_list', $missing_items_list);
    if ($additional_fees_payment_method === null) {
        $stmt->bindValue(':additional_fees_payment_method', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':additional_fees_payment_method', $additional_fees_payment_method);
    }
    if ($additional_fees_reference_no === null) {
        $stmt->bindValue(':additional_fees_reference_no', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':additional_fees_reference_no', $additional_fees_reference_no);
    }
    if ($additional_fees_paid_date === null) {
        $stmt->bindValue(':additional_fees_paid_date', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':additional_fees_paid_date', $additional_fees_paid_date);
    }
    $stmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        if ($additional_fees_status === 'Paid') {
            $response['message'] = 'Additional fees marked as paid.';
        } else {
            $response['message'] = 'Additional fees status updated.';
        }
        $response['missing_items_fees'] = $missing_items_fees;
    } else {
        $response['message'] = 'Failed to update booking.';
    }
} catch(PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>

