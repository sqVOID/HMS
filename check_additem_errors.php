<?php
// Temporary error checker for AddItem.php issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>AddItem.php Environment Check</h2>";

// 1. Check PHP version
echo "<h3>1. PHP Version</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Required: 7.0+ (Recommended: 7.4+)<br><br>";

// 2. Check database connection
echo "<h3>2. Database Connection</h3>";
try {
    require_once 'config.php';
    echo "✓ Database connection successful<br>";
    echo "Host: " . $host . "<br>";
    echo "Database: " . $dbname . "<br><br>";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br><br>";
}

// 3. Check uploads directory
echo "<h3>3. Uploads Directory</h3>";
$uploadDir = __DIR__ . '/uploads/additem';
if (is_dir($uploadDir)) {
    echo "✓ Directory exists: " . $uploadDir . "<br>";
    if (is_writable($uploadDir)) {
        echo "✓ Directory is writable<br><br>";
    } else {
        echo "✗ Directory is NOT writable (chmod 755 or 775 needed)<br><br>";
    }
} else {
    echo "✗ Directory does not exist: " . $uploadDir . "<br>";
    if (@mkdir($uploadDir, 0755, true)) {
        echo "✓ Successfully created directory<br><br>";
    } else {
        echo "✗ Failed to create directory<br><br>";
    }
}

// 4. Check required files
echo "<h3>4. Required Files</h3>";
$requiredFiles = ['access_check.php', 'auth.php', 'config.php', 'additem.css'];
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists<br>";
    } else {
        echo "✗ $file is missing<br>";
    }
}
echo "<br>";

// 5. Check additem_list table
echo "<h3>5. Database Table Check</h3>";
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'additem_list'");
    if ($stmt->rowCount() > 0) {
        echo "✓ additem_list table exists<br>";
        
        // Check table structure
        $stmt = $conn->query("DESCRIBE additem_list");
        echo "<br>Table structure:<br>";
        echo "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
        echo "</pre>";
    } else {
        echo "✗ additem_list table does not exist<br>";
        echo "Run the table creation query from AddItem.php<br>";
    }
} catch (Exception $e) {
    echo "✗ Error checking table: " . $e->getMessage() . "<br>";
}

echo "<br><h3>6. Server Information</h3>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Path: " . __DIR__ . "<br>";

echo "<br><p><strong>If all checks pass, try accessing AddItem.php again.</strong></p>";
echo "<p><a href='AddItem.php'>Go to AddItem.php</a></p>";
?>
