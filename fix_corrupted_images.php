<?php
/**
 * This script identifies corrupted images in the database
 * Note: Corrupted images need to be re-uploaded through the web interface
 */
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Checking for Corrupted Images</h2>";

try {
    $stmt = $conn->prepare("SELECT id, room_id, room_type, LENGTH(room_image) as img_size FROM rooms WHERE room_image IS NOT NULL");
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($rooms) . " rooms with images.</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Room ID</th><th>Type</th><th>Size (bytes)</th><th>Status</th><th>First Bytes</th></tr>";
    
    $corrupted_count = 0;
    
    foreach ($rooms as $room) {
        // Get the image data
        $stmt2 = $conn->prepare("SELECT room_image FROM rooms WHERE id = :id");
        $stmt2->bindParam(':id', $room['id'], PDO::PARAM_INT);
        $stmt2->execute();
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($row && isset($row['room_image'])) {
            $image_data = $row['room_image'];
            
            if (is_resource($image_data)) {
                $image_data = stream_get_contents($image_data);
            }
            
            // Check if it's a valid image
            $is_valid = @getimagesizefromstring($image_data) !== false;
            
            // Get first few bytes
            $first_bytes = substr($image_data, 0, 20);
            $hex = bin2hex(substr($image_data, 0, 10));
            
            // Check for corruption (starts with question marks or null bytes in wrong places)
            $is_corrupted = (substr($image_data, 0, 4) === '????') || 
                           (substr($hex, 0, 8) === '3f3f3f3f') ||
                           !$is_valid;
            
            $status = $is_corrupted ? '<span style="color:red;">CORRUPTED</span>' : '<span style="color:green;">OK</span>';
            
            if ($is_corrupted) {
                $corrupted_count++;
            }
            
            echo "<tr>";
            echo "<td>{$room['id']}</td>";
            echo "<td>{$room['room_id']}</td>";
            echo "<td>{$room['room_type']}</td>";
            echo "<td>{$room['img_size']}</td>";
            echo "<td>$status</td>";
            echo "<td style='font-family: monospace; font-size: 10px;'>" . htmlspecialchars($first_bytes) . "<br>Hex: $hex</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    
    echo "<h3>Summary</h3>";
    echo "<p><strong>Total rooms with images:</strong> " . count($rooms) . "</p>";
    echo "<p><strong>Corrupted images:</strong> <span style='color:red;'>$corrupted_count</span></p>";
    echo "<p><strong>Valid images:</strong> <span style='color:green;'>" . (count($rooms) - $corrupted_count) . "</span></p>";
    
    if ($corrupted_count > 0) {
        echo "<h3 style='color:red;'>Action Required:</h3>";
        echo "<p>You need to <strong>re-upload</strong> the images for the corrupted rooms through the web interface.</p>";
        echo "<p>The fix has been applied to prevent future corruption, but existing corrupted data cannot be automatically recovered.</p>";
    }
    
} catch(PDOException $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

