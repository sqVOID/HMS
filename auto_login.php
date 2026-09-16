<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['access_level'] = 'super_admin';
header('Location: Report.php');
exit;
?>
