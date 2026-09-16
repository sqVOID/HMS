<?php
require_once 'access_check.php';
checkAccess('Createuser.php');

require_once 'config.php';
require_once 'system_logger.php';

$_enc_first = trim($_SESSION['first_name'] ?? '');
$_enc_last  = trim($_SESSION['last_name'] ?? '');
if ($_enc_first !== '' || $_enc_last !== '') {
    $encoder = trim($_enc_first . ' ' . $_enc_last);
} else {
    $encoder = trim($_SESSION['username'] ?? 'Super Admin');
}

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
                logWarning($conn, 'auth', 'USER_CREATE_FAILED', "Failed to create user '{$newUsername}': Username already exists", [
                    'username'  => $newUsername,
                    'failed_by' => $encoder
                ]);
            } else {
                $stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, password, access_level, status) VALUES (?, ?, ?, ?, ?, 'Active')");
                $stmt->execute([$newUsername, $newFirstName, $newLastName, $password, $systemLevel]);
                $newId = $conn->lastInsertId();

                logActivity($conn, 'auth', 'USER_CREATE', "New user account '{$newUsername}' ({$newFirstName} {$newLastName}, Role: {$systemLevel}) created by {$encoder}", [
                    'new_user_id'  => $newId,
                    'username'     => $newUsername,
                    'first_name'   => $newFirstName,
                    'last_name'    => $newLastName,
                    'access_level' => $systemLevel,
                    'created_by'   => $encoder
                ], $newId);

                header('Location: ' . $_SERVER['PHP_SELF'] . '?status=created');
                exit;
            }
        } else {
            logWarning($conn, 'auth', 'USER_CREATE_FAILED', "Failed to create user: " . implode(', ', $errorMessages), [
                'username'  => $newUsername,
                'errors'    => $errorMessages,
                'failed_by' => $encoder
            ]);
        }

    } elseif ($action === 'toggle_status' && $userId > 0) {
        $uInfo = $conn->prepare("SELECT username, first_name, last_name, status FROM users WHERE id = ?");
        $uInfo->execute([$userId]);
        $targetUser = $uInfo->fetch(PDO::FETCH_ASSOC);
        $targetName = $targetUser ? trim(($targetUser['first_name'] ?? '') . ' ' . ($targetUser['last_name'] ?? '')) : '';
        if ($targetName === '') $targetName = $targetUser['username'] ?? "User #{$userId}";
        $newStatus = ($targetUser['status'] ?? 'Active') === 'Active' ? 'Inactive' : 'Active';

        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);

        logActivity($conn, 'auth', 'USER_STATUS_CHANGE', "User account '{$targetName}' status changed to {$newStatus} by {$encoder}", [
            'target_user_id' => $userId,
            'target_username'=> $targetUser['username'] ?? '',
            'new_status'     => $newStatus,
            'changed_by'     => $encoder
        ], $userId);

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
                logWarning($conn, 'auth', 'USER_UPDATE_FAILED', "Failed to update user #{$userId}: Username '{$editUsername}' already exists", [
                    'target_user_id' => $userId,
                    'username'       => $editUsername
                ], $userId);
            } else {
                if ($editPassword !== '') {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, access_level = ?, password = ? WHERE id = ?");
                    $stmt->execute([$editUsername, $editFirstName, $editLastName, $editLevel, $editPassword, $userId]);
                    $logMsg = "User account '{$editUsername}' ({$editFirstName} {$editLastName}) updated (details & password changed) by {$encoder}";
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, access_level = ? WHERE id = ?");
                    $stmt->execute([$editUsername, $editFirstName, $editLastName, $editLevel, $userId]);
                    $logMsg = "User account '{$editUsername}' ({$editFirstName} {$editLastName}, Role: {$editLevel}) updated by {$encoder}";
                }

                logActivity($conn, 'auth', 'USER_UPDATE', $logMsg, [
                    'target_user_id' => $userId,
                    'username'       => $editUsername,
                    'first_name'     => $editFirstName,
                    'last_name'      => $editLastName,
                    'access_level'   => $editLevel,
                    'updated_by'     => $encoder
                ], $userId);

                header('Location: ' . $_SERVER['PHP_SELF'] . '?status=updated');
                exit;
            }
        } else {
            logWarning($conn, 'auth', 'USER_UPDATE_FAILED', "Failed to update user #{$userId}: " . implode(', ', $errorMessages), [
                'target_user_id' => $userId,
                'errors'         => $errorMessages
            ], $userId);
        }

    } elseif ($action === 'delete' && $userId > 0) {
        if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
            $errorMessages[] = 'You cannot delete your own account.';
            logWarning($conn, 'auth', 'USER_DELETE_BLOCKED', "User {$encoder} attempted to delete their own account", [
                'user_id' => $userId
            ], $userId);
        } else {
            $uInfo = $conn->prepare("SELECT username, first_name, last_name FROM users WHERE id = ?");
            $uInfo->execute([$userId]);
            $targetUser = $uInfo->fetch(PDO::FETCH_ASSOC);
            $targetName = $targetUser ? trim(($targetUser['first_name'] ?? '') . ' ' . ($targetUser['last_name'] ?? '')) : '';
            if ($targetName === '') $targetName = $targetUser['username'] ?? "User #{$userId}";

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);

            logActivity($conn, 'auth', 'USER_DELETE', "User account '{$targetName}' (ID: {$userId}) deleted by {$encoder}", [
                'target_user_id' => $userId,
                'username'       => $targetUser['username'] ?? '',
                'deleted_by'     => $encoder
            ], $userId);

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

// Handle status filter
$statusFilter = $_GET['filter_status'] ?? '';
$users = [];
$showPlaceholder = true;

if ($statusFilter !== '') {
    $showPlaceholder = false;
    if ($statusFilter === 'all') {
        // Order by status (Active first, Inactive last), then by created_at
        $result = $conn->query("SELECT id, username, first_name, last_name, access_level, status, created_at FROM users ORDER BY status ASC, created_at DESC");
    } else {
        $stmt = $conn->prepare("SELECT id, username, first_name, last_name, access_level, status, created_at FROM users WHERE status = ? ORDER BY created_at DESC");
        $stmt->execute([ucfirst($statusFilter)]);
        $result = $stmt;
    }
    
    if ($result) {
        $users = $result->fetchAll(PDO::FETCH_ASSOC);
    }
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
    <!-- Global Layout Styles -->
    <link rel="stylesheet" href="includes/global_layout.css">
    <!-- Page-specific Styles -->
    <link rel="stylesheet" href="createuser.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="role-based-menu.js?v=20"></script>
    <script src="auto_logout.js" defer></script>
    <script src="cancellation-notification.js?v=20" defer></script>
    <!-- Global Sidebar Scripts -->
    <script src="includes/sidebar_scripts.js?v=20" defer></script>
</head>

<body>
    <div class="split-container">
        <!-- Global Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Right Panel -->
        <div class="right-panel">
            <!-- Global Header -->
            <?php include 'includes/header.php'; ?>
            
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
                        <div class="filter-container">
                            <label for="statusFilter" style="margin-right: 8px; font-weight: 500;">Status:</label>
                            <select id="statusFilter" class="status-filter-select" onchange="applyStatusFilter()">
                                <option value="">-- Select Status --</option>
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active Status</option>
                                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive Status</option>
                            </select>
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
                                <?php if ($showPlaceholder): ?>
                                    <tr class="empty-row">
                                        <td colspan="4" style="text-align:center;padding:20px;color:#666;">Please filter first to display the data</td>
                                    </tr>
                                <?php elseif (empty($users)): ?>
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
                                                    <button type="submit" class="action-btn <?php echo $user['status'] === 'Active' ? 'btn-deactivate' : 'btn-toggle'; ?>">
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
        // ── Status Filter ────────────────────────────────────────
        function applyStatusFilter() {
            const select = document.getElementById('statusFilter');
            const value = select.value;
            if (value) {
                // Preserve existing status parameter if present
                const urlParams = new URLSearchParams(window.location.search);
                const statusParam = urlParams.get('status');
                let url = '?filter_status=' + encodeURIComponent(value);
                if (statusParam) {
                    url += '&status=' + encodeURIComponent(statusParam);
                }
                window.location.href = url;
            }
        }

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
    </script>
</body>

</html>