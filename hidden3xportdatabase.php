<?php
/**
 * exportdatabase.php
 * 
 * Exports the entire hotel_management database as a downloadable .sql file.
 * Access via: http://localhost/HMS/exportdatabase.php
 */

require_once 'config.php';
// $host, $dbname, $username, $password, and $conn are all provided by config.php

// ─── Build mysqldump command ───────────────────────────────────────────────
// Use credentials from config.php so this works on both localhost and Hostinger
$dump_host = $host;
$dump_user = $username;
$dump_pass = $password;
$dump_db = $dbname;

// Detect mysqldump binary — XAMPP on Windows, system path on Linux/Hostinger
$mysqldump_path = 'mysqldump'; // default: system PATH (Linux/Hostinger)
if (PHP_OS_FAMILY === 'Windows') {
    $win_path = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    if (file_exists($win_path)) {
        $mysqldump_path = $win_path;
    }
}

$filename = $dump_db . '_backup_' . date('Y-m-d_H-i-s') . '.sql';
$output_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

// Check if exec() is available (disabled on many shared hosting providers)
$exec_disabled = !function_exists('exec') || in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));

if (!$exec_disabled) {
    // Build command
    if ($dump_pass !== '') {
        $cmd = sprintf(
            '"%s" --host=%s --user=%s --password=%s --single-transaction --routines --triggers --add-drop-table --skip-set-charset --default-character-set=utf8mb4 %s > "%s" 2>&1',
            $mysqldump_path,
            escapeshellarg($dump_host),
            escapeshellarg($dump_user),
            escapeshellarg($dump_pass),
            escapeshellarg($dump_db),
            $output_file
        );
    } else {
        $cmd = sprintf(
            '"%s" --host=%s --user=%s --single-transaction --routines --triggers --add-drop-table --skip-set-charset --default-character-set=utf8mb4 %s > "%s" 2>&1',
            $mysqldump_path,
            $dump_host,
            $dump_user,
            $dump_db,
            $output_file
        );
    }

    // Run the dump
    exec($cmd, $output, $return_code);
    
    // If successful, process the file to fix collations, then stream it
    if ($return_code === 0 && file_exists($output_file) && filesize($output_file) > 0) {
        // Fix incompatible collations in the exported file
        $sql_content = file_get_contents($output_file);
        $sql_content = preg_replace(
            '/utf8mb4_uca1400_ai_ci/',
            'utf8mb4_general_ci',
            $sql_content
        );
        $sql_content = preg_replace(
            '/utf8mb4_uca1400_/',
            'utf8mb4_unicode_',
            $sql_content
        );
        file_put_contents($output_file, $sql_content);
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($output_file));
        header('Pragma: no-cache');
        header('Expires: 0');
        
        readfile($output_file);
        @unlink($output_file); // Clean up temp file
        exit;
    }
}

// ─── Fallback: PHP-based dump (for disabled exec() or mysqldump failure) ───
ob_start();
phpDump($conn, $dump_db, $filename);
$sql_content = ob_get_clean();

if (empty(trim($sql_content))) {
    http_response_code(500);
    die('Export failed. Unable to generate database backup.');
}

// Stream the PHP-generated dump
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($sql_content));
header('Pragma: no-cache');
echo $sql_content;
exit;


// ══════════════════════════════════════════════════════════════════════════
//  PHP-based fallback dump (no mysqldump binary required)
// ══════════════════════════════════════════════════════════════════════════
function phpDump(PDO $pdo, string $dbname, string $filename): void
{
    $now = date('Y-m-d H:i:s');

    echo "-- ============================================================\n";
    echo "-- HMS Database Export\n";
    echo "-- Database : $dbname\n";
    echo "-- Exported : $now\n";
    echo "-- Host     : localhost\n";
    echo "-- ============================================================\n\n";

    echo "SET FOREIGN_KEY_CHECKS=0;\n";
    echo "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
    echo "SET time_zone='+08:00';\n\n";

    // Get all tables
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        dumpTable($pdo, $table);
    }

    // Get views
    $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($views as $view) {
        dumpView($pdo, $view);
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    echo "-- Export complete\n";
}

function dumpTable(PDO $pdo, string $table): void
{
    echo "\n-- ──────────────────────────────────────────\n";
    echo "-- Table structure for `$table`\n";
    echo "-- ──────────────────────────────────────────\n\n";

    echo "DROP TABLE IF EXISTS `$table`;\n";

    // CREATE TABLE statement
    $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    $createStatement = $create['Create Table'];
    
    // Replace incompatible collations with widely-supported alternatives
    $createStatement = preg_replace(
        '/utf8mb4_uca1400_ai_ci/',
        'utf8mb4_general_ci',
        $createStatement
    );
    $createStatement = preg_replace(
        '/utf8mb4_uca1400_/',
        'utf8mb4_unicode_',
        $createStatement
    );
    
    echo $createStatement . ";\n\n";

    // Count rows
    $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    if ($count == 0) {
        echo "-- (no data)\n\n";
        return;
    }

    echo "-- Data for table `$table`\n\n";

    // Fetch data in chunks to avoid memory issues
    $chunkSize = 500;
    $offset = 0;

    while (true) {
        $rows = $pdo->query("SELECT * FROM `$table` LIMIT $chunkSize OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows))
            break;

        // Get column names
        $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
        $values_list = [];

        foreach ($rows as $row) {
            $vals = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $vals[] = 'NULL';
                } elseif (is_numeric($val) && !preg_match('/^0\d/', $val)) {
                    $vals[] = $val;
                } else {
                    $vals[] = "'" . addslashes($val) . "'";
                }
            }
            $values_list[] = '(' . implode(', ', $vals) . ')';
        }

        echo "INSERT INTO `$table` ($columns) VALUES\n";
        echo implode(",\n", $values_list) . ";\n\n";

        $offset += $chunkSize;
        if (count($rows) < $chunkSize)
            break;
    }
}

function dumpView(PDO $pdo, string $view): void
{
    echo "\n-- ──────────────────────────────────────────\n";
    echo "-- View: `$view`\n";
    echo "-- ──────────────────────────────────────────\n\n";

    $create = $pdo->query("SHOW CREATE VIEW `$view`")->fetch(PDO::FETCH_ASSOC);
    echo "DROP VIEW IF EXISTS `$view`;\n";
    echo $create['Create View'] . ";\n\n";
}
?>