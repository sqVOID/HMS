<?php
// Turn off all error reporting and output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any accidental output
ob_start();

// Suppress any output from config
$old_error_reporting = error_reporting(0);
$old_display_errors = ini_get('display_errors');
ini_set('display_errors', 0);

require_once 'config.php';

// Restore settings (though we'll clean buffer anyway)
error_reporting($old_error_reporting);
ini_set('display_errors', $old_display_errors);

// Get room ID from query parameter
$id = $_GET['id'] ?? '';

if (empty($id)) {
    ob_clean();
    http_response_code(400);
    header('Content-Type: text/plain');
    exit('Room ID is required');
}

try {
    // Get room image from database
    $stmt = $conn->prepare("SELECT room_image FROM rooms WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Fetch the row
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && isset($row['room_image']) && !empty($row['room_image'])) {
        $image_data = $row['room_image'];
        
        // Handle stream resource if returned as such
        if (is_resource($image_data)) {
            $image_data = stream_get_contents($image_data);
        }
        
        // Clean any BOM or extra characters at the start
        // Remove BOM if present (UTF-8 BOM: EF BB BF)
        if (substr($image_data, 0, 3) === "\xEF\xBB\xBF") {
            $image_data = substr($image_data, 3);
        }
        
        // Check first bytes
        $first_byte = ord($image_data[0]);
        $second_byte = isset($image_data[1]) ? ord($image_data[1]) : 0;
        
        // PNG signature: 89 50 4E 47 0D 0A 1A 0A
        // JPEG signature: FF D8
        $is_valid_png = ($first_byte === 0x89 && isset($image_data[1]) && $image_data[1] === 'P' && isset($image_data[2]) && $image_data[2] === 'N' && isset($image_data[3]) && $image_data[3] === 'G');
        $is_valid_jpeg = ($first_byte === 0xFF && $second_byte === 0xD8);
        
        // If not starting with valid signature, search for it
        if (!$is_valid_png && !$is_valid_jpeg) {
            // Try to find PNG signature (more aggressive search)
            $png_pos = strpos($image_data, "\x89PNG");
            if ($png_pos !== false && $png_pos < 20) {
                $image_data = substr($image_data, $png_pos);
                $is_valid_png = true;
            } else {
                // Try to find JPEG signature
                $jpeg_pos = strpos($image_data, "\xFF\xD8");
                if ($jpeg_pos !== false && $jpeg_pos < 20) {
                    $image_data = substr($image_data, $jpeg_pos);
                    $is_valid_jpeg = true;
                } else {
                    // Last resort: try to find any valid image header in first 100 bytes
                    for ($i = 0; $i < min(100, strlen($image_data) - 8); $i++) {
                        if (substr($image_data, $i, 8) === "\x89PNG\r\n\x1a\n") {
                            $image_data = substr($image_data, $i);
                            $is_valid_png = true;
                            break;
                        }
                        if (substr($image_data, $i, 2) === "\xFF\xD8") {
                            $image_data = substr($image_data, $i);
                            $is_valid_jpeg = true;
                            break;
                        }
                    }
                }
            }
        }
        
        // Verify we have valid image data
        if (!empty($image_data) && strlen($image_data) > 10) {
            // Detect image type from the image data
            $image_info = @getimagesizefromstring($image_data);
            
            if ($image_info !== false && isset($image_info['mime'])) {
                $content_type = $image_info['mime'];
            } else {
                // If getimagesizefromstring fails, try to determine from signature
                $first_byte = ord($image_data[0]);
                if ($first_byte === 0x89) {
                    $content_type = 'image/png';
                } elseif ($first_byte === 0xFF) {
                    $content_type = 'image/jpeg';
                } else {
                    $content_type = 'image/png'; // Default
                }
            }
            
            // Clear output buffer completely - do this before any headers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Set appropriate headers for image
            header('Content-Type: ' . $content_type);
            header('Content-Length: ' . strlen($image_data));
            header('Cache-Control: public, max-age=3600');
            header('Pragma: public');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
            
            // Output the image data
            echo $image_data;
            exit;
        }
    }
    
    // Return 404 for missing images
    ob_clean();
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Image not found');
    
} catch(PDOException $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: text/plain');
    error_log('Image retrieval error: ' . $e->getMessage());
    exit('Database error');
} catch(Exception $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: text/plain');
    error_log('Image retrieval error: ' . $e->getMessage());
    exit('Error');
}
?>

