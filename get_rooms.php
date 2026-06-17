<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Ensure columns exist bef ore querying
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM rooms");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('room_size', $existingColumns)) {
            $conn->exec("ALTER TABLE rooms ADD COLUMN room_size VARCHAR(100) DEFAULT NULL");
        }
        if (!in_array('bed_type', $existingColumns)) {
            $conn->exec("ALTER TABLE rooms ADD COLUMN bed_type VARCHAR(100) DEFAULT NULL");
        }
        if (!in_array('guest_capacity', $existingColumns)) {
            $conn->exec("ALTER TABLE rooms ADD COLUMN guest_capacity VARCHAR(50) DEFAULT NULL");
        }
        if (!in_array('price_per_night', $existingColumns)) {
            $conn->exec("ALTER TABLE rooms ADD COLUMN price_per_night DECIMAL(10,2) DEFAULT 0.00");
        }
    } catch(PDOException $e) {
        // Columns might already exist, continue
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
    
    // Select rooms with current status from bookings if available
    // If room has an active booking (Confirming, Confirmed, Occupied, Reserved), use booking status
    // Map 'Confirming' and 'Confirmed' to 'Occupied' for Roomlist display (detailed status still in Booking.html)
    // Reserved status shows as Reserved in Roomlist
    // Otherwise, use room's own status
    $stmt = $conn->prepare("
        SELECT 
            r.id, 
            r.room_id, 
            r.room_type, 
            r.room_size, 
            r.bed_type, 
            r.guest_capacity, 
            CASE 
                WHEN b.status = 'Confirming' THEN 'Occupied'
                WHEN b.status = 'Confirmed' THEN 'Occupied'
                WHEN b.status = 'Reserved' THEN 'Reserved'
                ELSE COALESCE(b.status, r.status)
            END as status,
            r.room_image, 
            r.created_at, 
            r.updated_at 
        FROM rooms r
        LEFT JOIN bookings b ON r.room_id = b.room_id 
            AND b.status IN ('Confirming', 'Confirmed', 'Occupied', 'Reserved')
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch duration pricing for each room
    $durationStmt = $conn->prepare("SELECT duration_hours, price FROM room_durations WHERE room_id = :room_id ORDER BY duration_hours ASC");
    
    foreach ($rooms as &$room) {
        $durationStmt->execute([':room_id' => $room['id']]);
        $durations = $durationStmt->fetchAll(PDO::FETCH_ASSOC);
        $room['durations'] = $durations;
    }
    
    echo json_encode(['success' => true, 'rooms' => $rooms]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>