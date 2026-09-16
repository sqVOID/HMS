<?php
// Debug script to see what's in the database
require_once 'config.php';

$id = $_GET['id'] ?? '19';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Debug Image for Room ID: $id</h2>";

try {
    $stmt = $conn->prepare("SELECT id, room_id, room_type, LENGTH(room_image) as img_size FROM rooms WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($room) {
        echo "<p><strong>Room Found:</strong> ID={$room['id']}, Room ID={$room['room_id']}, Type={$room['room_type']}</p>";
        echo "<p><strong>Image Size:</strong> {$room['img_size']} bytes</p>";
        
        // Get the actual image data
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
            
            // Show first 50 bytes in hex
            $first_50 = substr($image_data, 0, 50);
            $hex = bin2hex($first_50);
            echo "<p><strong>First 50 bytes (hex):</strong> <code>$hex</code></p>";
            
            // Show first 50 bytes as text (for debugging)
            $text = '';
            for ($i = 0; $i < min(50, strlen($image_data)); $i++) {
                $byte = ord($image_data[$i]);
                if ($byte >= 32 && $byte <= 126) {
                    $text .= htmlspecialchars($image_data[$i]);
                } else {
                    $text .= '[' . $byte . ']';
                }
            }
            echo "<p><strong>First 50 bytes (text):</strong> <code>$text</code></p>";
            
            // Check for PNG signature
            if (substr($image_data, 0, 8) === "\x89PNG\r\n\x1a\n") {
                echo "<p style='color:green;'><strong>✓ Valid PNG signature found!</strong></p>";
            } elseif (substr($image_data, 0, 2) === "\xFF\xD8") {
                echo "<p style='color:green;'><strong>✓ Valid JPEG signature found!</strong></p>";
            } else {
                echo "<p style='color:red;'><strong>✗ Invalid image signature!</strong></p>";
                
                // Try to find PNG signature
                $png_pos = strpos($image_data, "\x89PNG");
                if ($png_pos !== false) {
                    echo "<p>PNG signature found at position: $png_pos</p>";
                    echo "<p>Bytes before PNG: " . bin2hex(substr($image_data, 0, $png_pos)) . "</p>";
                }
                
                // Try to find JPEG signature
                $jpeg_pos = strpos($image_data, "\xFF\xD8");
                if ($jpeg_pos !== false) {
                    echo "<p>JPEG signature found at position: $jpeg_pos</p>";
                    echo "<p>Bytes before JPEG: " . bin2hex(substr($image_data, 0, $jpeg_pos)) . "</p>";
                }
            }
            
            // Try to validate as image
            $image_info = @getimagesizefromstring($image_data);
            if ($image_info !== false) {
                echo "<p style='color:green;'><strong>✓ Image is valid!</strong></p>";
                echo "<p><strong>Type:</strong> {$image_info['mime']}</p>";
                echo "<p><strong>Dimensions:</strong> {$image_info[0]} x {$image_info[1]}</p>";
            } else {
                echo "<p style='color:red;'><strong>✗ Image validation failed!</strong></p>";
            }
            
            // Try to display
            echo "<h3>Attempting to display image:</h3>";
            echo "<img src='get_room_image.php?id=$id' alt='Room Image' style='max-width: 300px; border: 1px solid #ccc;' onerror='this.style.display=\"none\"; this.nextElementSibling.style.display=\"block\";'>";
            echo "<p style='display:none; color:red;'>Image failed to load</p>";
        } else {
            echo "<p style='color:red;'>No image data found!</p>";
        }
    } else {
        echo "<p style='color:red;'>Room not found!</p>";
    }
} catch(PDOException $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

