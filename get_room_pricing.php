<?php
header('Content-Type: application/json');

try {
    $roomType = $_GET['room_type'] ?? '';
    $roomId = $_GET['room_id'] ?? '';
    
    if (empty($roomType) || empty($roomId)) {
        echo json_encode(['success' => false, 'error' => 'Room type and room ID are required']);
        exit;
    }
    
    require_once 'config.php';
    
    // First, get the database room ID from the room_id (like "999")
    $roomQuery = "SELECT id FROM rooms WHERE room_id = :room_id LIMIT 1";
    $roomStmt = $conn->prepare($roomQuery);
    
    if (!$roomStmt) {
        throw new Exception('Prepare failed');
    }
    
    $roomStmt->bindParam(':room_id', $roomId);
    $roomStmt->execute();
    
    $roomData = $roomStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$roomData) {
        throw new Exception('Room not found');
    }
    
    $roomDbId = $roomData['id'];
    
    // Ensure duration_unit exists for day-based room rates
    try {
        $conn->exec("ALTER TABLE room_durations ADD COLUMN duration_unit VARCHAR(10) NOT NULL DEFAULT 'hours'");
    } catch (Exception $e) {
        // Column may already exist
    }

    // Query to get pricing for this specific room from room_durations table
    $query = "SELECT 
                duration_hours as hours,
                price,
                duration_unit
              FROM room_durations 
              WHERE room_id = :room_db_id 
              ORDER BY duration_hours ASC";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Prepare failed');
    }
    
    $stmt->bindParam(':room_db_id', $roomDbId, PDO::PARAM_INT);
    $stmt->execute();
    
    $pricing = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $unit = (isset($row['duration_unit']) && $row['duration_unit'] === 'days') ? 'days' : 'hours';
        $pricing[] = [
            'hours' => $row['hours'],
            'price' => floatval($row['price']),
            'duration_unit' => $unit,
            'unit' => $unit
        ];
    }
    
    echo json_encode([
        'success' => true,
        'room_type' => $roomType,
        'room_id' => $roomId,
        'room_db_id' => $roomDbId,
        'pricing' => $pricing
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
