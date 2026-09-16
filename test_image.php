<?php
// Test script to verify image retrieval
require_once 'config.php';

$id = $_GET['id'] ?? '14'; // Default to room ID 14 for testing

echo "<h2>Testing Image Retrieval for Room ID: $id</h2>";

try {
    // Test 1: Check if room exists and get image size
    $stmt = $conn->prepare("SELECT id, room_id, room_type, LENGTH(room_image) as img_size FROM rooms WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($room) {
        echo "<p><strong>Room Found:</strong> ID={$room['id']}, Room ID={$room['room_id']}, Type={$room['room_type']}</p>";
        echo "<p><strong>Image Size:</strong> {$room['img_size']} bytes</p>";
        
        // Test 2: Try to retrieve image data
        $stmt2 = $conn->prepare("SELECT room_image FROM rooms WHERE id = :id");
        $stmt2->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt2->execute();
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($row && isset($row['room_image'])) {
            $image_data = $row['room_image'];
            
            if (is_resource($image_data)) {
                $image_data = stream_get_contents($image_data);
            }
            
            $data_size = strlen($image_data);
            echo "<p><strong>Retrieved Data Size:</strong> $data_size bytes</p>";
            
            // Check first few bytes for image signature
            $first_bytes = substr($image_data, 0, 10);
            $hex = bin2hex($first_bytes);
            echo "<p><strong>First 10 bytes (hex):</strong> $hex</p>";
            
            // Check if it's a valid image
            $image_info = @getimagesizefromstring($image_data);
            if ($image_info !== false) {
                echo "<p><strong>Image Type:</strong> {$image_info['mime']}</p>";
                echo "<p><strong>Image Dimensions:</strong> {$image_info[0]} x {$image_info[1]}</p>";
                
                // Display the image
                echo "<h3>Image Display:</h3>";
                echo "<img src='get_room_image.php?id=$id' alt='Room Image' style='max-width: 300px; border: 1px solid #ccc;'>";
                echo "<p><a href='get_room_image.php?id=$id' target='_blank'>Open image directly</a></p>";
            } else {
                echo "<p style='color: red;'><strong>ERROR:</strong> Invalid image data!</p>";
                echo "<p>First 100 characters: " . htmlspecialchars(substr($image_data, 0, 100)) . "</p>";
            }
        } else {
            echo "<p style='color: red;'><strong>ERROR:</strong> No image data found in row!</p>";
        }
    } else {
        echo "<p style='color: red;'><strong>ERROR:</strong> Room not found!</p>";
    }
    
} catch(PDOException $e) {
    echo "<p style='color: red;'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

