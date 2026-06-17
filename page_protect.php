<?php
/**
 * Page Protection Wrapper
 * Include this at the top of any page to check access
 * Usage: require_once 'page_protect.php'; checkPageAccess('PageName.html');
 */

require_once 'auth.php';

// Get current page name from the file that includes this
$currentPage = basename($_SERVER['PHP_SELF']);

// If it's an HTML file being accessed, get it from the request
if (empty($currentPage) || $currentPage === 'page_protect.php') {
    $currentPage = basename($_SERVER['REQUEST_URI']);
    $currentPage = explode('?', $currentPage)[0]; // Remove query string
}

// Check access for this page
checkPageAccess($currentPage);
?>