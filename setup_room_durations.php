<?php
/**
 * Setup script to create room_durations table for duration-based pricing
 * Run this once to set up the database structure
 */
require_once 'config.php';

try {
    // Create room_durations table
    $sql = "CREATE TABLE IF NOT EXISTS room_durations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        duration_hours INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_room_duration (room_id, duration_hours),
        INDEX idx_room_id (room_id),
        INDEX idx_duration_hours (duration_hours)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    echo "Table 'room_durations' created successfully!\n";
    
    // Migrate existing price_per_night data to duration pricing (optional)
    // This assumes 24 hours = 1 night
    $migrate = false; // Set to true if you want to migrate existing data
    
    if ($migrate) {
        $stmt = $conn->query("SELECT id, price_per_night FROM rooms WHERE price_per_night > 0");
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $insertStmt = $conn->prepare("INSERT INTO room_durations (room_id, duration_hours, price) VALUES (:room_id, 24, :price) ON DUPLICATE KEY UPDATE price = :price");
        
        foreach ($rooms as $room) {
            $insertStmt->execute([
                ':room_id' => $room['id'],
                ':price' => $room['price_per_night']
            ]);
        }
        
        echo "Migrated " . count($rooms) . " existing room prices to duration pricing (24 hours).\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

