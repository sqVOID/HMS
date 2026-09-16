<?php
require_once 'config.php';
header('Content-Type: text/plain');

echo "Checking room_durations table...\n";

try {
    $stmt = $conn->query("SELECT * FROM room_durations");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rows)) {
        echo "room_durations table is empty.\n";
    } else {
        echo "Found " . count($rows) . " rows:\n";
        print_r($rows);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nChecking rooms table (id vs room_id):\n";
try {
    $stmt = $conn->query("SELECT id, room_id, room_type FROM rooms");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rooms);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
