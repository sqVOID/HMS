<?php
/**
 * log_client_error.php
 * Receives JavaScript / frontend error reports and stores them in system_logs.
 * Called automatically by the global error capture script (hms_error_capture.js).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'system_logger.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

// Resolve actor from session
$_cf = trim($_SESSION['first_name'] ?? '');
$_cl = trim($_SESSION['last_name']  ?? '');
$_cActor = ($_cf !== '' || $_cl !== '') ? trim($_cf . ' ' . $_cl) : trim($_SESSION['username'] ?? 'Guest/Unknown');

// Sanitize fields from the payload
$errorType    = trim($body['error_type']  ?? 'JS_ERROR');        // JS_ERROR | FETCH_ERROR | PROMISE_REJECTION | JSON_PARSE | CONSOLE_ERROR
$message      = trim($body['message']     ?? 'Unknown error');
$source       = trim($body['source']      ?? '');                 // file/url where error occurred
$lineno       = intval($body['lineno']    ?? 0);
$colno        = intval($body['colno']     ?? 0);
$stack        = trim($body['stack']       ?? '');
$page         = trim($body['page']        ?? '');                 // current page URL
$category     = trim($body['category']   ?? 'client');           // client | fetch | promise | json

// Build human-readable description
$desc = "[{$errorType}] {$message}";
if ($page)   $desc .= " | Page: {$page}";
if ($source) $desc .= " | Source: {$source}";
if ($lineno) $desc .= " (line {$lineno}" . ($colno ? ", col {$colno}" : "") . ")";
if ($_cActor !== 'Guest/Unknown') $desc .= " | User: {$_cActor}";

// Build metadata
$meta = [
    'error_type' => $errorType,
    'message'    => substr($message, 0, 500),
    'page'       => $page,
    'user'       => $_cActor,
];
if ($source)              $meta['source'] = $source;
if ($lineno)              $meta['line']   = $lineno;
if ($colno)               $meta['col']    = $colno;
if ($stack)               $meta['stack']  = substr($stack, 0, 1000);

try {
    logError($conn, $category, strtoupper($errorType), $desc, $meta);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
