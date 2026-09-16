<?php
// Breakfast Page Error Checker
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Breakfast Page Environment Check</h2>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// 1. Check PHP version
echo "<h3>1. PHP Version</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Required: 7.0+ (Recommended: 7.4+)<br><br>";

// 2. Check database connection
echo "<h3>2. Database Connection</h3>";
try {
    require_once 'config.php';
    echo "<span class='success'>✓ Database connection successful</span><br>";
    echo "Host: " . $host . "<br>";
    echo "Database: " . $dbname . "<br><br>";
} catch (Exception $e) {
    echo "<span class='error'>✗ Database connection failed: " . $e->getMessage() . "</span><br><br>";
    echo "<p><strong>Action Required:</strong> Update config.php with your Hostinger database credentials.</p>";
    exit;
}

// 3. Check breakfast table
echo "<h3>3. Breakfast Table Check</h3>";
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'breakfast'");
    if ($stmt->rowCount() > 0) {
        echo "<span class='success'>✓ breakfast table exists</span><br>";
        
        // Check table structure
        $stmt = $conn->query("DESCRIBE breakfast");
        echo "<br>Table structure:<br>";
        echo "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
        echo "</pre>";
        
        // Count items
        $count_stmt = $conn->query("SELECT COUNT(*) as count FROM breakfast");
        $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "Total breakfast items: <strong>$count</strong><br><br>";
    } else {
        echo "<span class='error'>✗ breakfast table does not exist</span><br>";
        echo "Creating table...<br>";
        
        $createTable = $conn->exec("
            CREATE TABLE IF NOT EXISTS breakfast (
                id INT AUTO_INCREMENT PRIMARY KEY,
                food_name VARCHAR(255) NOT NULL,
                description TEXT,
                price DECIMAL(10, 2) NOT NULL,
                food_image VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        echo "<span class='success'>✓ Table created successfully</span><br><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Error checking table: " . $e->getMessage() . "</span><br><br>";
}

// 4. Check uploads directory
echo "<h3>4. Uploads Directory</h3>";
$uploadDir = __DIR__ . '/uploads/breakfast';
if (is_dir($uploadDir)) {
    echo "<span class='success'>✓ Directory exists: " . $uploadDir . "</span><br>";
    if (is_writable($uploadDir)) {
        echo "<span class='success'>✓ Directory is writable</span><br><br>";
    } else {
        echo "<span class='error'>✗ Directory is NOT writable (chmod 755 or 775 needed)</span><br><br>";
    }
} else {
    echo "<span class='error'>✗ Directory does not exist: " . $uploadDir . "</span><br>";
    if (@mkdir($uploadDir, 0755, true)) {
        echo "<span class='success'>✓ Successfully created directory</span><br><br>";
    } else {
        echo "<span class='error'>✗ Failed to create directory</span><br>";
        echo "<p><strong>Action Required:</strong> Manually create the 'uploads/breakfast' folder via FTP/File Manager and set permissions to 755.</p><br>";
    }
}

// 5. Check required files
echo "<h3>5. Required Files</h3>";
$requiredFiles = [
    'Breakfast.html' => 'Main page',
    'breakfast.html' => 'Lowercase version (case-sensitive servers)',
    'Breakfast.css' => 'Stylesheet',
    'get_breakfast.php' => 'Get items API',
    'add_breakfast_item.php' => 'Add item API',
    'update_breakfast_item.php' => 'Update item API',
    'delete_breakfast_item.php' => 'Delete item API',
    'config.php' => 'Database config',
    'role-based-menu.js' => 'Menu script',
    'auto_logout.js' => 'Auto logout script'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<span class='success'>✓ $file</span> - $description<br>";
    } else {
        echo "<span class='error'>✗ $file is missing</span> - $description<br>";
    }
}
echo "<br>";

// 6. Test API endpoints
echo "<h3>6. Test API Endpoints</h3>";

// Test get_breakfast.php
echo "<strong>Testing get_breakfast.php:</strong><br>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/get_breakfast.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "<span class='success'>✓ API working - Returns " . count($data['items']) . " items</span><br>";
    } else {
        echo "<span class='error'>✗ API returned error: " . ($data['message'] ?? 'Unknown error') . "</span><br>";
    }
} else {
    echo "<span class='error'>✗ API returned HTTP $httpCode</span><br>";
}
echo "<br>";

// 7. Server information
echo "<h3>7. Server Information</h3>";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Path: " . __DIR__ . "<br>";
echo "Host: " . $_SERVER['HTTP_HOST'] . "<br>";
echo "Case Sensitive: " . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'No (Windows)' : 'Yes (Linux/Unix)') . "<br><br>";

// 8. File case sensitivity check
echo "<h3>8. File Name Case Sensitivity</h3>";
if (file_exists('Breakfast.html') && file_exists('breakfast.html')) {
    echo "<span class='success'>✓ Both Breakfast.html and breakfast.html exist</span><br>";
} elseif (file_exists('Breakfast.html')) {
    echo "<span class='success'>✓ Breakfast.html exists</span><br>";
    echo "<span class='error'>⚠ breakfast.html (lowercase) does not exist</span><br>";
    echo "<p><strong>Note:</strong> On Linux servers, file names are case-sensitive. If users access 'breakfast.html', they'll get a 404.</p>";
    echo "<p><strong>Solution:</strong> Create a lowercase copy or use .htaccess redirect.</p>";
} elseif (file_exists('breakfast.html')) {
    echo "<span class='success'>✓ breakfast.html exists</span><br>";
    echo "<span class='error'>⚠ Breakfast.html (uppercase) does not exist</span><br>";
} else {
    echo "<span class='error'>✗ Neither Breakfast.html nor breakfast.html found!</span><br>";
}
echo "<br>";

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>If all checks pass above, the breakfast page should work correctly.</p>";
echo "<p><a href='Breakfast.html' style='padding:10px 20px;background:#4CAF50;color:white;text-decoration:none;border-radius:5px;'>Go to Breakfast Page</a></p>";
echo "<br>";
echo "<p style='color:#999;font-size:12px;'>After everything works, delete this file for security.</p>";
?>
