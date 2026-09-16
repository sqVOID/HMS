<?php

date_default_timezone_set('Asia/Manila');

require __DIR__ . '/config.php';



$backupDir = 'D:\\Backup\\backup';
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0755, true)) {
        echo "Failed to create backup directory: $backupDir\n";
        exit(1);
    }
}


$nextNum = 1;
$existingBackups = glob($backupDir . DIRECTORY_SEPARATOR . 'backup#*.sql');
if (!empty($existingBackups)) {
    $numbers = array();
    foreach ($existingBackups as $file) {
        if (preg_match('/backup#(\d+)-/', basename($file), $matches)) {
            $numbers[] = (int)$matches[1];
        }
    }
    if (!empty($numbers)) {
        $nextNum = max($numbers) + 1;
    }
}

// Format: YYYY-MM.DD.HH-h.MMam/pm
$dateStr = date('Y-m.d.H');
$timeStr = date('h.i');
$ampm = date('a');
$timestamp = "{$dateStr}-{$timeStr}{$ampm}";

$filename = $backupDir . DIRECTORY_SEPARATOR . "backup#{$nextNum}-{$timestamp}.sql";

$preferred = 'C:\\xampp\\mysql\bin\mysqldump.exe';
$mysqldump = is_file($preferred) ? $preferred : 'mysqldump';

$hostArg = escapeshellarg($host);
$userArg = escapeshellarg($username);
$passArg = escapeshellarg($password);
$dbArg   = escapeshellarg($dbname);

$filenameForCmd = '"' . $filename . '"';

$cmd = sprintf(
    '%s --host=%s --user=%s --password=%s --single-transaction --quick --skip-lock-tables %s > %s',
    $mysqldump,
    $hostArg,
    $userArg,
    $passArg,
    $dbArg,
    $filenameForCmd
);

$logFile = $backupDir . DIRECTORY_SEPARATOR . 'backup_run.log';
$time = date('Y-m-d H:i:s');
file_put_contents($logFile, "\n--- Backup run: $time\nCommand: $cmd\n", FILE_APPEND);
echo "Running dump to: $filename\n";
exec($cmd . ' 2>&1', $output, $returnVar);
file_put_contents($logFile, implode("\n", $output) . "\nReturn code: $returnVar\n", FILE_APPEND);

if ($returnVar !== 0) {
    echo "mysqldump failed (exit code $returnVar). Output:\n";
    echo implode("\n", $output) . "\n";
 
    if (file_exists($filename) && filesize($filename) === 0) {
        @unlink($filename);
    }
    exit(1);
}

echo "Backup completed successfully: $filename\n";
exit(0);

?>
