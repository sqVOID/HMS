<?php
require_once 'config.php';

$messages = [];
$errors = [];

try {
    // Check if bookings table exists
    $checkBookingsTable = $conn->query("SHOW TABLES LIKE 'bookings'");
    if ($checkBookingsTable->rowCount() > 0) {
        try {
            $conn->exec("ALTER TABLE `bookings` MODIFY COLUMN `status` ENUM('Confirming', 'Confirmed', 'Occupied', 'Available', 'Checked Out', 'Canceled', 'Extended') NOT NULL DEFAULT 'Available'");
            $messages[] = "Successfully updated the status column in the `bookings` table.";
        } catch (PDOException $e) {
            // Check if it's a 'Duplicate value' error, which means it might already be there
            if (strpos($e->getMessage(), "Duplicate value") !== false) {
                 $messages[] = "The 'Extended' status may already exist in the `bookings` table.";
            } else {
                $errors[] = "Error updating `bookings` table: " . $e->getMessage();
            }
        }
    } else {
        $messages[] = "`bookings` table not found. Skipping.";
    }

    // Check if reports table exists
    $checkReportsTable = $conn->query("SHOW TABLES LIKE 'reports'");
    if ($checkReportsTable->rowCount() > 0) {
        try {
            $conn->exec("ALTER TABLE `reports` MODIFY COLUMN `status` ENUM('Confirming', 'Confirmed', 'Occupied', 'Available', 'Checked Out', 'Canceled', 'Extended') DEFAULT 'Confirmed'");
            $messages[] = "Successfully updated the status column in the `reports` table.";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "Duplicate value") !== false) {
                $messages[] = "The 'Extended' status may already exist in the `reports` table.";
            } else {
                $errors[] = "Error updating `reports` table: " . $e->getMessage();
            }
        }
    } else {
        $messages[] = "`reports` table not found. Skipping.";
    }

} catch (PDOException $e) {
    $errors[] = "A database error occurred: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Status ENUM</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .message { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Update Status ENUM</h1>
    <?php foreach($messages as $message): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endforeach; ?>
    <?php foreach($errors as $error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endforeach; ?>
</body>
</html>
