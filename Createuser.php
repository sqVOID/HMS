<?php
require_once 'access_check.php';
checkAccess('Createuser.php');

require_once 'config.php';

$statusMessage = '';
$errorMessages = [];

// Ensure the users table has a status column
try {
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    if ($check->rowCount() === 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'");
    }
} catch (PDOException $e) {
    // Ignore
}

// Expand access_level enum to include super_admin and auditor if needed
try {
    $conn->exec("ALTER TABLE users MODIFY COLUMN access_level ENUM('staff','admin','user','super_admin','auditor') NOT NULL DEFAULT 'user'");
} catch (PDOException $e) {
    // Ignore — may already be updated
}

// Ensure first_name and last_name columns exist
try {
    $chkFN = $conn->query("SHOW COLUMNS FROM users LIKE 'first_name'");
    if ($chkFN->rowCount() === 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN first_name VARCHAR(100) NOT NULL DEFAULT '' AFTER username");
    }
    $chkLN = $conn->query("SHOW COLUMNS FROM users LIKE 'last_name'");
    if ($chkLN->rowCount() === 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN last_name VARCHAR(100) NOT NULL DEFAULT '' AFTER first_name");
    }
} catch (PDOException $e) {
    // Ignore
}


function sanitizeText($value)
{
    return trim((string) $value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'create') {
        $newUsername = sanitizeText($_POST['username'] ?? '');
        $newFirstName = sanitizeText($_POST['firstname'] ?? '');
        $newLastName = sanitizeText($_POST['lastname'] ?? '');
        $systemLevel = sanitizeText($_POST['system_level'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if ($newUsername === '') {
            $errorMessages[] = 'Username is required.';
        }
        if ($newFirstName === '') {
            $errorMessages[] = 'First name is required.';
        }
        if ($newLastName === '') {
            $errorMessages[] = 'Last name is required.';
        }
        if (!in_array($systemLevel, ['admin', 'super_admin', 'user', 'auditor'], true)) {
            $errorMessages[] = 'Please select a valid system level.';
        }
        if ($password === '') {
            $errorMessages[] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errorMessages[] = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirmPw) {
            $errorMessages[] = 'Passwords do not match.';
        }

        if (empty($errorMessages)) {
            $dup = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $dup->execute([$newUsername]);
            if ($dup->fetch()) {
                $errorMessages[] = 'Username already exists. Please choose a different one.';
            } else {
                $stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, password, access_level, status) VALUES (?, ?, ?, ?, ?, 'Active')");
                $stmt->execute([$newUsername, $newFirstName, $newLastName, $password, $systemLevel]);
                header('Location: ' . $_SERVER['PHP_SELF'] . '?status=created');
                exit;
            }
        }

    } elseif ($action === 'toggle_status' && $userId > 0) {
        $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'Active', 'Inactive', 'Active') WHERE id = ?");
        $stmt->execute([$userId]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?status=updated');
        exit;

    } elseif ($action === 'update' && $userId > 0) {
        $editUsername = sanitizeText($_POST['edit_username'] ?? '');
        $editFirstName = sanitizeText($_POST['edit_firstname'] ?? '');
        $editLastName = sanitizeText($_POST['edit_lastname'] ?? '');
        $editLevel = sanitizeText($_POST['edit_system_level'] ?? '');
        $editPassword = $_POST['edit_password'] ?? '';

        if ($editUsername === '') {
            $errorMessages[] = 'Username is required.';
        }
        if ($editFirstName === '') {
            $errorMessages[] = 'First name is required.';
        }
        if ($editLastName === '') {
            $errorMessages[] = 'Last name is required.';
        }
        if (!in_array($editLevel, ['admin', 'super_admin', 'user', 'auditor'], true)) {
            $errorMessages[] = 'Please select a valid system level.';
        }
        if ($editPassword !== '' && strlen($editPassword) < 6) {
            $errorMessages[] = 'New password must be at least 6 characters.';
        }

        if (empty($errorMessages)) {
            // Check duplicate username (excluding current user)
            $dup = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
            $dup->execute([$editUsername, $userId]);
            if ($dup->fetch()) {
                $errorMessages[] = 'Username already exists.';
            } else {
                if ($editPassword !== '') {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, access_level = ?, password = ? WHERE id = ?");
                    $stmt->execute([$editUsername, $editFirstName, $editLastName, $editLevel, $editPassword, $userId]);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, access_level = ? WHERE id = ?");
                    $stmt->execute([$editUsername, $editFirstName, $editLastName, $editLevel, $userId]);
                }
                header('Location: ' . $_SERVER['PHP_SELF'] . '?status=updated');
                exit;
            }
        }

    } elseif ($action === 'delete' && $userId > 0) {
        if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
            $errorMessages[] = 'You cannot delete your own account.';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?status=deleted');
            exit;
        }
    }
}

if (isset($_GET['status'])) {
    $map = [
        'created' => 'New user account has been created.',
        'updated' => 'User status updated.',
        'deleted' => 'User account removed successfully.',
    ];
    $statusMessage = $map[$_GET['status']] ?? '';
}

$users = [];
$result = $conn->query("SELECT id, username, first_name, last_name, access_level, status, created_at FROM users ORDER BY created_at DESC");
if ($result) {
    $users = $result->fetchAll(PDO::FETCH_ASSOC);
}

// If there were errors on the create form, keep the form open after reload
$formHasErrors = !empty($errorMessages);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>Create User</title>
    <link rel="stylesheet" href="createuser.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="role-based-menu.js?v=18"></script>
    <script src="auto_logout.js" defer></script>
    <script src="cancellation-notification.js?v=15" defer></script>
</head>

<body>
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

    <div class="split-container">

        <div class="left-panel" id="leftPanel">
            <!-- DingTalk-style collapse tab -->
            <button class="sidebar-collapse-btn" id="collapseBtn" onclick="toggleSidebarMinimize()">
                <img id="collapseIcon" src="Icon/left-arrow_minimize.svg" alt="Minimize"
                    style="width: 24px; height: 18px;">
                <span class="collapse-tooltip" id="collapseTooltip">Minimize</span>
            </button>
            <img src="Icon/MoonClave_Logo.svg" alt="Luna Group Logo" class="logo-img">
            <nav class="sidebar-menu">
                <ul>
                    <li class="sidebar-menu-item active" style="display: none;" data-page="Createuser.php"
                        onclick="navigateToPage('Createuser.php')">
                        <img src="Icon/createaccounticon_system.svg" class="sidebar-icon" alt="Create User">
                        <span>Create User</span>
                    </li>
                    <li class="sidebar-menu-item" data-page="Report.php" onclick="navigateToPage('Report.php')">
                        <img src="Icon/dashboardicon_system.svg" class="sidebar-icon" alt="Dashboard">
                        <span>Dashboard</span>
                    </li>
                    <li class="sidebar-menu-item" data-page="Booking.html" onclick="navigateToPage('Booking.html')">
                        <img src="Icon/bookingicon_system.svg" class="sidebar-icon" alt="Booking">
                        <span>Booking</span>
                    </li>

                    <li class="sidebar-menu-item" data-page="Modification.php"
                        onclick="navigateToPage('Modification.php')">
                        <img src="Icon/modicon_system.svg" class="sidebar-icon" alt="Modification">
                        <span>Modification</span>
                    </li>

                        <li class="sidebar-menu-item active" data-page="CashDeposit.php"
                        onclick="navigateToPage('CashDeposit.php')">
                        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round"
                            style="display: inline-block; vertical-align: middle; margin-right: 12px; width: 20px; height: 20px;">
                            <rect x="2" y="5" width="20" height="14" rx="2"/>
                            <line x1="2" y1="10" x2="22" y2="10"/>
                        </svg>
                        <span>Running Cash</span>
                    </li>

                    <li class="sidebar-menu-item" data-page="Reservationlist.php"
                        onclick="navigateToPage('Reservationlist.php')">
                        <img src="Icon/reservationicon_system.svg" class="sidebar-icon" alt="Reservation">
                        <span>Reservation List</span>
                    </li>


                    <li class="sidebar-menu-item" data-page="Cancelpage.php" onclick="navigateToPage('Cancelpage.php')">
                        <img src="Icon/cancelicon_system.svg" class="sidebar-icon" alt="Cancellation Approval">
                        <span>Cancellation</span>
                    </li>


                    <li class="sidebar-menu-item" data-page="Roomlist.html" onclick="navigateToPage('Roomlist.html')">
                        <img src="Icon/roomlisticon_system.svg" class="sidebar-icon" alt="Room List">
                        <span>Room List</span>
                    </li>
                    <li class="sidebar-menu-item" data-page="Promo.html" onclick="navigateToPage('Promo.html')">
                        <img src="Icon/promoicon_system.svg" class="sidebar-icon" alt="Promo">
                        <span>Promo</span>
                    </li>
                    <li class="sidebar-menu-item collapsible-menu" id="systemMaintenanceMenu">
                        <div class="menu-header" onclick="toggleSystemMaintenance()">
                            <img src="Icon/systemmaitenanceicon_system.svg" class="sidebar-icon" alt="Maintenance">
                            <span>Maintenance</span>
                            <svg class="menu-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <ul class="submenu" id="systemMaintenanceSubmenu">
                            <li class="submenu-item" data-page="inventory.html"
                                onclick="navigateToPage('inventory.html')">
                                <img src="Icon/inventoryicon_system.svg" class="sidebar-icon" alt="Inventory">
                                <span>Inventory</span>
                            </li>
                            <li class="submenu-item" data-page="PurchaseOrder.html"
                                onclick="navigateToPage('PurchaseOrder.html')">
                                <img src="Icon/purchaseordericon_system.svg" class="sidebar-icon" alt="Purchase Order">
                                <span>Purchase Order</span>
                            </li>
                            <li class="submenu-item" data-page="AddItem.php" onclick="navigateToPage('AddItem.php')">
                                <img src="Icon/additemicon_system.svg" class="sidebar-icon" alt="Add Item">
                                <span>Add Item</span>
                            </li>
                        </ul>
                    </li>
                    <li class="sidebar-menu-item" data-page="Receive.html" onclick="navigateToPage('Receive.html')">
                        <img src="Icon/receiveicon_system.svg" class="sidebar-icon" alt="Receive">
                        <span>Receive</span>
                    </li>
                    <li class="sidebar-menu-item" data-page="Breakfast.html" onclick="navigateToPage('Breakfast.html')">
                        <img src="Icon/breakfasticon_system.svg" class="sidebar-icon" alt="Breakfast">
                        <span>Meal</span>
                    </li>
                    <li class="sidebar-menu-item" onclick="window.location.href='logout.php'">
                        <img src="Icon/logouticon_system.svg" class="sidebar-icon" alt="Logout">
                        <span>Logout</span>
                    </li>
                    <!-- <li class="sidebar-menu-item" id="minimizeBtn" onclick="toggleSidebarMinimize()">
                        <svg class="sidebar-icon" id="minimizeIcon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="transition: transform 0.3s ease;">
                            <path d="M12 5L7 10L12 15M17 5L12 10L17 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span id="minimizeText">Minimize</span>
                    </li> -->
                </ul>
            </nav>
        </div>

        <!-- ─── RIGHT PANEL ──────────────────────────────────────── -->
        <div class="right-panel">
            <div class="header-bar">
                <div class="user-profile">
                    <div class="user-avatar" id="userAvatar">
                        <?php
                        $username = $_SESSION['username'] ?? 'Admin';
                        $firstName = $_SESSION['first_name'] ?? '';
                        $displayName = $firstName !== '' ? $firstName : $username;
                        echo strtoupper(substr($displayName, 0, 1));
                        ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="user-role">
                            <?php
                            $role = $_SESSION['access_level'] ?? 'admin';
                            echo htmlspecialchars(ucwords(str_replace('_', ' ', $role)), ENT_QUOTES, 'UTF-8');
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-container">
                <h2 class="header-title">Create User</h2>

                <!-- ── STATUS / ERROR ALERTS ── -->
                <?php if (!empty($statusMessage)): ?>
                    <p class="alert-box alert-success"><?php echo htmlspecialchars($statusMessage); ?></p>
                    <?php
                endif; ?>
                <?php if (!empty($errorMessages)): ?>
                    <ul class="alert-box alert-error">
                        <?php foreach ($errorMessages as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php
                        endforeach; ?>
                    </ul>
                    <?php
                endif; ?>

                <!-- ── CREATE ACCOUNT BUTTON (always visible) ── -->
                <button class="create-account-btn" id="toggleFormBtn" onclick="toggleForm()">
                    Create account
                </button>

                <!-- ── FORM CARD (hidden by default) ── -->
                <div class="form-card" id="formCard" style="display:<?php echo $formHasErrors ? 'block' : 'none'; ?>;">
                    <form class="create-user-form" method="post" id="createUserForm" autocomplete="off">
                        <input type="hidden" name="action" value="create">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" placeholder="Enter Username" required>
                            </div>

                            <div class="form-group">
                                <label for="firstname">First Name</label>
                                <input type="text" id="firstname" name="firstname" placeholder="Enter First Name"
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="lastname">Last Name</label>
                                <input type="text" id="lastname" name="lastname" placeholder="Enter Last Name" required>
                            </div>

                            <div class="form-group">
                                <label for="system_level">System Level</label>
                                <select id="system_level" name="system_level" required>
                                    <option value="" disabled selected>Select Level</option>
                                    <option value="super_admin">Super Admin</option>
                                    <option value="auditor">Auditor</option>
                                    <option value="admin">Admin</option>
                                    <option value="user">User</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" placeholder="Enter Password"
                                    required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password"
                                    placeholder="Enter Confirm Password" required>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="create-btn">Create</button>
                        </div>
                    </form>
                </div>

                <!-- ── TABLE CARD ── -->
                <div class="table-card">
                    <div class="table-toolbar">
                        <div class="search-bar-container">
                            <input type="text" id="searchInput" class="search-bar-input" placeholder="Search">
                            <button class="search-bar-btn" type="button">
                                <img src="Icon/searchicon_system.svg" alt="Search" class="search-bar-icon">
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive-wrapper">
                        <table class="user-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>System Level</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php if (empty($users)): ?>
                                    <tr class="empty-row">
                                        <td colspan="4">No users found.</td>
                                    </tr>
                                    <?php
                                else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr
                                            data-username="<?php echo htmlspecialchars(strtolower($user['username']), ENT_QUOTES, 'UTF-8'); ?>">
                                            <td><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($user['access_level']), ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td>
                                                <button
                                                    class="status-badge <?php echo $user['status'] === 'Active' ? 'badge-active' : 'badge-inactive'; ?>">
                                                    <?php echo htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8'); ?>
                                                </button>
                                            </td>
                                            <td>
                                                <!-- Edit -->
                                                <button type="button" class="action-btn btn-edit" onclick="openEditModal(
                                                        <?php echo (int) $user['id']; ?>,
                                                        '<?php echo addslashes(htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8')); ?>',
                                                        '<?php echo addslashes(htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8')); ?>',
                                                        '<?php echo addslashes(htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8')); ?>',
                                                        '<?php echo addslashes($user['access_level']); ?>'
                                                    )">Edit</button>
                                                <!-- Toggle Status -->

                                                <!-- Delete -->
                                                <button type="button" class="action-btn btn-delete"
                                                    onclick="deleteUser(<?php echo (int) $user['id']; ?>)">Delete</button>
                                                <form id="delete-form-<?php echo (int) $user['id']; ?>" method="post"
                                                    style="display:none;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id"
                                                        value="<?php echo (int) $user['id']; ?>">
                                                </form>
                                                <form method="post" style="display:inline;"
                                                    id="toggle-form-<?php echo (int) $user['id']; ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id"
                                                        value="<?php echo (int) $user['id']; ?>">
                                                    <button type="submit" class="action-btn btn-toggle">
                                                        <?php echo $user['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php
                                    endforeach; ?>
                                    <?php
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="edit-modal-overlay" id="editModal">
        <div class="edit-modal-box">
            <button class="edit-modal-close" type="button" onclick="closeEditModal()">×</button>
            <h3 class="edit-modal-title">Edit User</h3>
            <form method="post" id="editUserForm" autocomplete="off">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="editUserId">

                <div class="edit-form-group">
                    <label for="editUsername">Username</label>
                    <input type="text" id="editUsername" name="edit_username" placeholder="Enter Username" required>
                </div>

                <div class="edit-form-group">
                    <label for="editFirstName">First Name</label>
                    <input type="text" id="editFirstName" name="edit_firstname" placeholder="Enter First Name" required>
                </div>

                <div class="edit-form-group">
                    <label for="editLastName">Last Name</label>
                    <input type="text" id="editLastName" name="edit_lastname" placeholder="Enter Last Name" required>
                </div>

                <div class="form-group">
                    <label for="editSystemLevel">System Level</label>
                    <select id="editSystemLevel" name="edit_system_level" required>
                        <option value="" disabled>Select Level</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="auditor">Auditor</option>
                        <option value="admin">Admin</option>
                        <option value="user">User</option>
                    </select>
                </div>

                <div class="edit-form-group">
                    <label for="editPassword">New Password <span
                            style="font-weight:400;color:#888;font-size:12px;">(leave blank to keep
                            current)</span></label>
                    <input type="password" id="editPassword" name="edit_password"
                        placeholder="Enter new password (optional)">
                </div>

                <div class="edit-modal-actions">
                    <button type="button" class="edit-cancel-btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="edit-save-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ── Form toggle ──────────────────────────────────────────
        function toggleForm() {
            const card = document.getElementById('formCard');
            const btn = document.getElementById('toggleFormBtn');
            if (card.style.display === 'none' || card.style.display === '') {
                card.style.display = 'block';
                btn.textContent = 'Close form';
            } else {
                card.style.display = 'none';
                btn.textContent = 'Create account';
            }
        }

        // ── Search ───────────────────────────────────────────────
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');

        function filterRows() {
            const query = searchInput.value.trim().toLowerCase();
            const rows = tableBody.querySelectorAll('tr');
            let visible = 0;

            rows.forEach(row => {
                if (row.classList.contains('empty-row')) {
                    row.style.display = query ? 'none' : '';
                    return;
                }
                const name = row.dataset.username || '';
                const show = name.includes(query);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            let noResultRow = document.getElementById('noResultsRow');
            if (!noResultRow) {
                noResultRow = document.createElement('tr');
                noResultRow.id = 'noResultsRow';
                noResultRow.innerHTML = '<td colspan="4" style="text-align:center;padding:20px;">No matching users.</td>';
                tableBody.appendChild(noResultRow);
            }
            noResultRow.style.display = visible === 0 && query ? '' : 'none';
        }

        if (searchInput) searchInput.addEventListener('input', filterRows);

        // ── Delete ───────────────────────────────────────────────
        function deleteUser(id) {
            if (confirm('Are you sure you want to delete this user?')) {
                document.getElementById('delete-form-' + id).submit();
            }
        }

        // ── Sidebar collapse (DingTalk style) ─────────────────────
        function toggleSidebarMinimize() {
            const panel = document.getElementById('leftPanel');
            const icon = document.getElementById('collapseIcon');
            const tooltip = document.getElementById('collapseTooltip');
            if (!panel) return;

            if (panel.classList.contains('minimized')) {
                panel.classList.remove('minimized');
                if (icon) icon.src = 'Icon/left-arrow_minimize.svg';
                if (tooltip) tooltip.textContent = 'Minimize';
            } else {
                panel.classList.add('minimized');
                if (icon) icon.src = 'Icon/right-arrow_minimize.svg';
                if (tooltip) tooltip.textContent = 'Expand';
            }
        }

        // ── Edit Modal ───────────────────────────────────────────
        function openEditModal(id, username, firstname, lastname, level) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editUsername').value = username;
            document.getElementById('editFirstName').value = firstname;
            document.getElementById('editLastName').value = lastname;
            document.getElementById('editPassword').value = '';
            const sel = document.getElementById('editSystemLevel');
            for (let i = 0; i < sel.options.length; i++) {
                sel.options[i].selected = sel.options[i].value === level;
            }
            document.getElementById('editModal').classList.add('visible');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('visible');
        }

        // Close modal when clicking outside the box
        document.getElementById('editModal').addEventListener('click', function (e) {
            if (e.target === this) closeEditModal();
        });

        // ── Sidebar Minimize ─────────────────────────────────────
        function toggleSidebarMinimize() {
            const leftPanel = document.querySelector('.left-panel');
            const minimizeText = document.getElementById('minimizeText');
            const minimizeIcon = document.getElementById('minimizeIcon');
            if (!leftPanel) return;

            if (leftPanel.classList.contains('minimized')) {
                leftPanel.classList.remove('minimized');
                if (minimizeText) minimizeText.textContent = 'Minimize';
                if (minimizeIcon) minimizeIcon.innerHTML = '<path d="M12 5L7 10L12 15M17 5L12 10L17 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
            } else {
                leftPanel.classList.add('minimized');
                if (minimizeText) minimizeText.textContent = 'Expand';
                if (minimizeIcon) minimizeIcon.innerHTML = '<path d="M8 5L13 10L8 15M3 5L8 10L3 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
            }
        }

        // ── Navigation ───────────────────────────────────────────
        window.navigateToPage = page => {
            if (window.innerWidth <= 1024) {
                const panel = document.querySelector('.left-panel');
                if (panel) panel.classList.remove('open');
            }
            window.location.href = page;
        };

        function toggleSidebar() {
            const panel = document.querySelector('.left-panel');
            if (panel) panel.classList.toggle('open');
        }

        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 1024) {
                const panel = document.querySelector('.left-panel');
                const toggleBtn = document.querySelector('.mobile-menu-toggle');
                if (panel && panel.classList.contains('open')) {
                    if (!panel.contains(e.target) && !toggleBtn.contains(e.target)) {
                        panel.classList.remove('open');
                    }
                }
            }
        });

        // ── Maintenance Submenu ──────────────────────────────────
        function toggleSystemMaintenance() {
            const submenu = document.getElementById('systemMaintenanceSubmenu');
            const menuItem = document.getElementById('systemMaintenanceMenu');
            if (submenu.classList.contains('open')) {
                submenu.classList.remove('open');
                menuItem.classList.remove('expanded');
            } else {
                submenu.classList.add('open');
                menuItem.classList.add('expanded');
            }
        }

        // ── Active menu item ─────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            const currentPage = window.location.pathname.split('/').pop() || 'Createuser.php';
            const menuItems = document.querySelectorAll('.sidebar-menu-item:not(.collapsible-menu)');
            const submenuItems = document.querySelectorAll('.submenu-item');
            const maintenance = ['inventory.html', 'PurchaseOrder.html', 'AddItem.php'];

            if (maintenance.includes(currentPage)) {
                const submenu = document.getElementById('systemMaintenanceSubmenu');
                const menuItem = document.getElementById('systemMaintenanceMenu');
                if (submenu && menuItem) { submenu.classList.add('open'); menuItem.classList.add('expanded'); }
                submenuItems.forEach(item => item.classList.toggle('active', item.getAttribute('data-page') === currentPage));
            } else {
                menuItems.forEach(item => item.classList.toggle('active', item.getAttribute('data-page') === currentPage));
            }
        });
    </script>
</body>

</html>