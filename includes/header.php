<?php
/**
 * Global Header Component
 * Reusable header with user profile notification for all pages in the HMS system
 * 
 * Note: Session must be started by the parent file before including this component
 */

// Get user information from session (assumes session is already started by parent)
$username = $_SESSION['username'] ?? 'Admin';
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';
$displayName = $firstName !== '' ? $firstName : $username;
$fullName = trim($firstName . ' ' . $lastName);
$role = $_SESSION['access_level'] ?? 'admin';
$userInitial = strtoupper(substr($displayName, 0, 1));
?>

<div class="header-bar">
    <div class="user-profile">
        <div class="user-avatar" id="userAvatar">
            <?php echo htmlspecialchars($userInitial, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="user-role">
                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $role)), ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
    </div>
</div>
