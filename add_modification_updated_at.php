<?php
// Script to add modification_updated_at column to bookings and reports tables

$host = 'localhost';
$username = 'u217102909_gcmoisn';
$password = 'BwQ562IFe_';
$dbname = 'u217102909_gcmoisn';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Adding modification_updated_at column to tables</h2>";

// Add to bookings table
$sql1 = "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS modification_updated_at DATETIME NULL DEFAULT NULL AFTER modification_reason";
if ($conn->query($sql1) === TRUE) {
    echo "<p style='color: green;'>✓ Successfully added modification_updated_at to bookings table</p>";
} else {
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "<p style='color: orange;'>⚠ Column modification_updated_at already exists in bookings table</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding to bookings: " . $conn->error . "</p>";
    }
}

// Add to reports table
$sql2 = "ALTER TABLE reports ADD COLUMN IF NOT EXISTS modification_updated_at DATETIME NULL DEFAULT NULL AFTER modification_reason";
if ($conn->query($sql2) === TRUE) {
    echo "<p style='color: green;'>✓ Successfully added modification_updated_at to reports table</p>";
} else {
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "<p style='color: orange;'>⚠ Column modification_updated_at already exists in reports table</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding to reports: " . $conn->error . "</p>";
    }
}

// Verify the columns were added
echo "<h3>Verification:</h3>";

$verify1 = $conn->query("SHOW COLUMNS FROM bookings LIKE 'modification_updated_at'");
if ($verify1 && $verify1->num_rows > 0) {
    echo "<p style='color: green;'>✓ Column exists in bookings table</p>";
    $row = $verify1->fetch_assoc();
    echo "<pre>Type: " . $row['Type'] . ", Null: " . $row['Null'] . ", Default: " . $row['Default'] . "</pre>";
} else {
    echo "<p style='color: red;'>✗ Column NOT found in bookings table</p>";
}

$verify2 = $conn->query("SHOW COLUMNS FROM reports LIKE 'modification_updated_at'");
if ($verify2 && $verify2->num_rows > 0) {
    echo "<p style='color: green;'>✓ Column exists in reports table</p>";
    $row = $verify2->fetch_assoc();
    echo "<pre>Type: " . $row['Type'] . ", Null: " . $row['Null'] . ", Default: " . $row['Default'] . "</pre>";
} else {
    echo "<p style='color: red;'>✗ Column NOT found in reports table</p>";
}

$conn->close();

echo "<h3>Done!</h3>";
echo "<p><a href='Modification.php'>Go to Modification Page</a></p>";
?>
