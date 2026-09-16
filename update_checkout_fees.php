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
$penalty_amount = floatval($_POST['penalty_amount'] ?? 0);
$penalty_list = $_POST['penalty_list'] ?? '[]';
$additional_fees_payment_method = trim($_POST['additional_fees_payment_method'] ?? '');
$additional_fees_reference_no = trim($_POST['additional_fees_reference_no'] ?? '');

$clear_missing = intval($_POST['clear_missing'] ?? 0);
$clear_penalty = intval($_POST['clear_penalty'] ?? 0);

if ($clear_missing === 1) {
    $missing_items_fees = 0.0;
    $missing_items_list = null;
}
if ($clear_penalty === 1) {
    $penalty_amount = 0.0;
    $penalty_list = null;
}

if (($clear_missing === 1 || $clear_penalty === 1) && $missing_items_fees == 0 && $penalty_amount == 0) {
    $additional_fees_status = 'None';
}

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
    
    // If only updating status (marking as paid), keep existing fees (unless explicitly clearing)
    if ($additional_fees_status === 'Paid' && $missing_items_fees == 0 && $penalty_amount == 0 && $clear_missing === 0 && $clear_penalty === 0) {
        $getFeesStmt = $conn->prepare("SELECT missing_items_fees, missing_items_list, penalty_amount, penalty_list FROM bookings WHERE id = :id");
        $getFeesStmt->bindParam(':id', $booking_id, PDO::PARAM_INT);
        $getFeesStmt->execute();
        $existingFees = $getFeesStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingFees) {
            $missing_items_fees = floatval($existingFees['missing_items_fees'] ?? 0);
            $missing_items_list = $existingFees['missing_items_list'] ?? '[]';
            $penalty_amount = floatval($existingFees['penalty_amount'] ?? 0);
            $penalty_list = $existingFees['penalty_list'] ?? '[]';
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
    
    // Ensure every item in missing_items_list has a date timestamp
    if (!empty($missing_items_list)) {
        $decodedList = json_decode($missing_items_list, true);
        if (is_array($decodedList)) {
            $nowStr = date('Y-m-d H:i:s');
            foreach ($decodedList as &$item) {
                if (is_array($item) && empty($item['date'])) {
                    $item['date'] = $nowStr;
                }
            }
            unset($item);
            $missing_items_list = json_encode($decodedList);
        }
    }

    // Ensure every item in penalty_list has a date timestamp
    if (!empty($penalty_list) && $penalty_list !== '[]') {
        $decodedPenalty = json_decode($penalty_list, true);
        if (is_array($decodedPenalty)) {
            $nowStr = date('Y-m-d H:i:s');
            foreach ($decodedPenalty as &$pItem) {
                if (is_array($pItem) && empty($pItem['date'])) {
                    $pItem['date'] = $nowStr;
                }
            }
            unset($pItem);
            $penalty_list = json_encode($decodedPenalty);
        }
    }
    
    // Update booking with additional fees status, fees, payment method, reference number, and paid date
    // NOTE: total_amount is recalculated in update_booking.php (includes missing/penalty like Guest/Pet).
    // Do not adjust total_amount here — many bookings store 0 in total_amount while deposit holds the paid room rate.
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET additional_fees_status = :additional_fees_status, 
            missing_items_fees = :missing_items_fees,
            missing_items_list = :missing_items_list,
            penalty_amount = :penalty_amount,
            penalty_list = :penalty_list,
            additional_fees_payment_method = :additional_fees_payment_method,
            additional_fees_reference_no = :additional_fees_reference_no,
            additional_fees_paid_date = :additional_fees_paid_date
        WHERE id = :id
    ");
    $stmt->bindParam(':additional_fees_status', $additional_fees_status);
    $stmt->bindParam(':missing_items_fees', $missing_items_fees);
    $stmt->bindParam(':missing_items_list', $missing_items_list);
    $stmt->bindParam(':penalty_amount', $penalty_amount);
    $stmt->bindParam(':penalty_list', $penalty_list);
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

        // Also sync reports table if record exists
        try {
            $getBId = $conn->prepare("SELECT booking_id FROM bookings WHERE id = :id");
            $getBId->execute([':id' => $booking_id]);
            $bId = $getBId->fetchColumn();
            if ($bId) {
                $checkRepCol = $conn->query("SHOW COLUMNS FROM reports LIKE 'additional_fees_status'");
                if ($checkRepCol && $checkRepCol->rowCount() > 0) {
                    $stmtRep = $conn->prepare("
                        UPDATE reports 
                        SET additional_fees_status = :additional_fees_status, 
                            missing_items_fees = :missing_items_fees,
                            missing_items_list = :missing_items_list,
                            penalty_amount = :penalty_amount,
                            penalty_list = :penalty_list,
                            additional_fees_payment_method = :additional_fees_payment_method,
                            additional_fees_reference_no = :additional_fees_reference_no,
                            additional_fees_paid_date = :additional_fees_paid_date
                        WHERE booking_id = :booking_id
                    ");
                    $stmtRep->bindParam(':additional_fees_status', $additional_fees_status);
                    $stmtRep->bindParam(':missing_items_fees', $missing_items_fees);
                    $stmtRep->bindParam(':missing_items_list', $missing_items_list);
                    $stmtRep->bindParam(':penalty_amount', $penalty_amount);
                    if ($penalty_list === null || $penalty_list === '' || $penalty_list === '[]') {
                        $stmtRep->bindValue(':penalty_list', null, PDO::PARAM_NULL);
                    } else {
                        $stmtRep->bindParam(':penalty_list', $penalty_list);
                    }
                    if ($additional_fees_payment_method === null) {
                        $stmtRep->bindValue(':additional_fees_payment_method', null, PDO::PARAM_NULL);
                    } else {
                        $stmtRep->bindParam(':additional_fees_payment_method', $additional_fees_payment_method);
                    }
                    if ($additional_fees_reference_no === null) {
                        $stmtRep->bindValue(':additional_fees_reference_no', null, PDO::PARAM_NULL);
                    } else {
                        $stmtRep->bindParam(':additional_fees_reference_no', $additional_fees_reference_no);
                    }
                    if ($additional_fees_paid_date === null) {
                        $stmtRep->bindValue(':additional_fees_paid_date', null, PDO::PARAM_NULL);
                    } else {
                        $stmtRep->bindParam(':additional_fees_paid_date', $additional_fees_paid_date);
                    }
                    $stmtRep->bindParam(':booking_id', $bId);
                    $stmtRep->execute();
                }
            }
        } catch (PDOException $e) {
            error_log("Failed to sync reports table in update_checkout_fees: " . $e->getMessage());
        }

        $response['penalty_amount'] = $penalty_amount;
    } else {
        $response['message'] = 'Failed to update booking.';
    }
} catch(PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>

