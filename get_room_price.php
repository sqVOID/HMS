<?php
/**
 * Helper function to get room price by duration
 * Returns the price for a specific room and duration
 */
require_once 'config.php';

header('Content-Type: application/json');

try {
    $roomId = $_GET['room_id'] ?? null;
    $durationHours = isset($_GET['duration_hours']) ? intval($_GET['duration_hours']) : null;
    
    if (!$roomId || !$durationHours) {
        echo json_encode(['success' => false, 'message' => 'room_id and duration_hours are required']);
        exit;
    }
    
    // Ensure room_durations table exists
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS room_durations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            duration_hours INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_room_duration (room_id, duration_hours),
            INDEX idx_room_id (room_id),
            INDEX idx_duration_hours (duration_hours)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch(PDOException $e) {
        // Table might already exist, continue
    }
    
    // Get exact match first
    $stmt = $conn->prepare("SELECT price FROM room_durations WHERE room_id = :room_id AND duration_hours = :duration_hours");
    $stmt->execute([
        ':room_id' => $roomId,
        ':duration_hours' => $durationHours
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'price' => floatval($result['price']),
            'duration_hours' => $durationHours
        ]);
    } else {
        // If no exact match, find the closest lower duration (for pricing tiers)
        $stmt = $conn->prepare("SELECT price, duration_hours FROM room_durations WHERE room_id = :room_id AND duration_hours <= :duration_hours ORDER BY duration_hours DESC LIMIT 1");
        $stmt->execute([
            ':room_id' => $roomId,
            ':duration_hours' => $durationHours
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'price' => floatval($result['price']),
                'duration_hours' => intval($result['duration_hours']),
                'note' => 'Using closest available duration pricing'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No pricing found for this room and duration'
            ]);
        }
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

