<?php
/**
 * Convert HTML pages to PHP with access control
 * Run this script once to create PHP versions of all HTML pages
 */

$pages = [
    'Report.html' => ['staff', 'admin'],
    'Roomlist.html' => ['staff', 'admin'],
    'Promo.html' => ['staff', 'admin'],
    'inventory.html' => ['staff', 'admin'],
    'Receive.html' => ['staff', 'admin'],
    'Breakfast.html' => ['staff', 'admin'],
];

$converted = [];
$errors = [];

foreach ($pages as $htmlFile => $access) {
    $phpFile = str_replace('.html', '.php', $htmlFile);
    
    if (!file_exists($htmlFile)) {
        $errors[] = "File not found: $htmlFile";
        continue;
    }
    
    // Read HTML content
    $content = file_get_contents($htmlFile);
    
    // Add PHP access control at the beginning
    $phpHeader = "<?php\nrequire_once 'access_check.php';\ncheckAccess('$htmlFile');\n?>\n";
    
    // Insert after DOCTYPE if it exists, otherwise at the beginning
    if (strpos($content, '<!DOCTYPE') === 0) {
        $content = $phpHeader . $content;
    } else {
        $content = $phpHeader . $content;
    }
    
    // Write PHP file
    if (file_put_contents($phpFile, $content)) {
        $converted[] = $phpFile;
    } else {
        $errors[] = "Failed to create: $phpFile";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Convert Pages to PHP</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; margin: 10px 0; }
        .error { color: red; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Page Conversion Results</h1>
    
    <?php if (!empty($converted)): ?>
        <h2>✓ Converted Files:</h2>
        <ul>
            <?php foreach ($converted as $file): ?>
                <li class="success"><?php echo htmlspecialchars($file); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <h2>✗ Errors:</h2>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li class="error"><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <p><strong>Note:</strong> Update your navigation links to use .php extensions instead of .html</p>
</body>
</html>

