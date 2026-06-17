<?php
/**
 * Access Check Handler
 * Include this in HTML files converted to PHP
 * Usage: require_once 'access_check.php'; checkAccess('PageName.html');
 */

require_once 'auth.php';

function checkAccess($pageName) {
    checkPageAccess($pageName);
}
?>

