<?php
/**
 * Authentication Helper File
 * Include this file at the top of protected pages to check if user is logged in
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: Login.html');
    exit;
}

// Page access configuration
// super_admin : can access EVERYTHING
// admin       : can access everything EXCEPT Createuser.php
// auditor     : can access Report.php, Booking.html, Reservationlist.php, Cancelpage.php, Roomlist.html, Promo.html, Breakfast.html, inventory.html
// user        : can only access Booking.html
function getPageAccessRules() {
    return [
        'Booking.html'       => ['user', 'staff', 'admin', 'super_admin', 'auditor'],
        'Reservationlist.php'=> ['user', 'staff', 'admin', 'super_admin', 'auditor'],
        'Report.php'         => ['user', 'staff', 'admin', 'super_admin', 'auditor'],
        'Report.html'        => ['user', 'staff', 'admin', 'super_admin', 'auditor'],
        'Roomlist.html'      => ['staff', 'admin', 'super_admin', 'auditor'],
        'Promo.html'         => ['staff', 'admin', 'super_admin', 'auditor'],
        'inventory.html'     => ['staff', 'admin', 'super_admin', 'auditor'],
        'Createuser.php'     => ['super_admin'],           // Super Admin only
        'UserSessions.php'   => ['admin', 'super_admin'],  // Admin + Super Admin
        'LogoutModal.php'    => ['user', 'staff', 'admin', 'super_admin', 'auditor'], // All users
        'Modification.php'   => ['super_admin'],
        'Cancelpage.php'     => ['admin', 'super_admin', 'auditor'],
        'PurchaseOrder.html' => ['admin', 'super_admin'],  // Admin + Super Admin
        'AddItem.php'        => ['staff', 'admin', 'super_admin'],
        'Receive.html'       => ['staff', 'admin', 'super_admin'],
        'Breakfast.html'     => ['staff', 'admin', 'super_admin', 'auditor'],
        'DepositTracking.php'=> ['staff', 'admin', 'super_admin', 'auditor'],
        'CashDeposit.php'    => ['staff', 'admin', 'super_admin', 'auditor'],
    ];
}

// Check if current user can access a specific page
function canAccessPage($pageName) {
    $userLevel   = $_SESSION['access_level'] ?? 'user';
    $accessRules = getPageAccessRules();

    // Super Admin can access everything
    if ($userLevel === 'super_admin') {
        return true;
    }

    // Check explicit rules
    if (isset($accessRules[$pageName])) {
        return in_array($userLevel, $accessRules[$pageName]);
    }

    // Default: deny if not listed
    return false;
}

// Check page access and redirect if denied
function checkPageAccess($pageName) {
    if (!canAccessPage($pageName)) {
        $userLevel = $_SESSION['access_level'] ?? 'user';

        if ($userLevel === 'user') {
            header('Location: Booking.html');
        } elseif ($userLevel === 'auditor') {
            header('Location: Report.php');
        } else {
            header('Location: Report.php');
        }
        exit;
    }
}

// Get current user info
function getCurrentUser() {
    return [
        'id'           => $_SESSION['user_id']     ?? null,
        'username'     => $_SESSION['username']    ?? null,
        'access_level' => $_SESSION['access_level'] ?? null
    ];
}

// Get user's access level
function getUserAccessLevel() {
    return $_SESSION['access_level'] ?? 'user';
}

// Check if user is super admin
function isSuperAdmin() {
    return ($_SESSION['access_level'] ?? '') === 'super_admin';
}

// Check if user is admin or super admin
function isAdmin() {
    return in_array($_SESSION['access_level'] ?? '', ['admin', 'super_admin']);
}

// Check if user is staff, admin, or super admin
function isStaffOrAdmin() {
    return in_array($_SESSION['access_level'] ?? '', ['staff', 'admin', 'super_admin']);
}
?>
