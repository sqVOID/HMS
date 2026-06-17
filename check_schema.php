<?php
require_once 'config.php';
try {
    $stmt = $conn->query("DESCRIBE bookings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo implode("\n", $columns);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
