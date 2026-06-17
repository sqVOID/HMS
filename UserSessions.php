<?php
require_once 'access_check.php';
checkAccess('UserSessions.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>User Sessions</title>
    <link rel="stylesheet" href="Booking.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="role-based-menu.js?v=18"></script>
    <script src="auto_logout.js" defer></script>
    <style>
        .filters-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }
        .filter-group input, .filter-group select {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
        }
        .filter-btn {
            padding: 8px 20px;
            background: #000;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .session-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        .session-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .session-table th {
            background: #f9fafb;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }
        .session-table td {
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid #f3f4f6;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        .status-active { background: #10b981; color: white; }
        .status-logged_out { background: #6b7280; color: white; }
        .status-on_break { background: #f59e0b; color: white; }
        .status-turnover { background: #3b82f6; color: white; }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

    <div class="split-container">
        <!-- Sidebar (same as other pages) -->
        <div class="left-panel" id="leftPanel">
            <button class="sidebar-collapse-btn" id="collapseBtn" onclick="toggleSidebarMinimize()">
                <img id="collapseIcon" src="Icon/left-arrow_minimize.svg" alt="Minimize" style="width: 24px; height: 18px;">
                <span class="collapse-tooltip" id="collapseTooltip">Minimize</span>
            </button>
            <img src="Icon/MoonClave_Logo.svg" alt="Luna Group Logo" class="logo-img">
            <nav class="sidebar-menu">
                <ul>
                    <li class="sidebar-menu-item" style="display: none;" data-page="Createuser.php" onclick="navigateToPage('Createuser.php')">
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
                    <li class="sidebar-menu-item" data-page="Modification.php" onclick="navigateToPage('Modification.php')">
                        <img src="Icon/modicon_system.svg" class="sidebar-icon" alt="Modification">
                        <span>Modification</span>
                    </li>
                    <li class="sidebar-menu-item" data-page="Reservationlist.php" onclick="navigateToPage('Reservationlist.php')">
                        <img src="Icon/reservationicon_system.svg" class="sidebar-icon" alt="Reservation">
                        <span>Reservation List</span>
                    </li>
                    <li class="sidebar-menu-item" data-page="Cancelpage.php" onclick="navigateToPage('Cancelpage.php')">
                        <img src="Icon/cancelicon_system.svg" class="sidebar-icon" alt="Cancellation">
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
                            <svg class="menu-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <ul class="submenu" id="systemMaintenanceSubmenu">
                            <li class="submenu-item" data-page="inventory.html" onclick="navigateToPage('inventory.html')">
                                <img src="Icon/inventoryicon_system.svg" class="sidebar-icon" alt="Inventory">
                                <span>Inventory</span>
                            </li>
                            <li class="submenu-item" data-page="PurchaseOrder.html" onclick="navigateToPage('PurchaseOrder.html')">
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
                        <span>Breakfast</span>
                    </li>
                    <li class="sidebar-menu-item active" data-page="UserSessions.php" onclick="navigateToPage('UserSessions.php')">
                        <img src="Icon/dashboardicon_system.svg" class="sidebar-icon" alt="User Sessions">
                        <span>User Sessions</span>
                    </li>
                    <li class="sidebar-menu-item" onclick="window.location.href='logout.php'">
                        <img src="Icon/logouticon_system.svg" class="sidebar-icon" alt="Logout">
                        <span>Logout</span>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
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
                <h2 class="header-title">User Sessions Log</h2>

                <div class="filters-container">
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="date" id="startDate">
                    </div>
                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="date" id="endDate">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="logged_out">Logged Out</option>
                            <option value="on_break">On Break</option>
                            <option value="turnover">Turnover</option>
                        </select>
                    </div>
                    <button class="filter-btn" onclick="loadSessions()">Filter</button>
                    <button class="filter-btn" style="background: #6b7280;" onclick="clearFilters()">Clear</button>
                </div>

                <div class="session-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Login At</th>
                                <th>Logout At</th>
                                <th>Break At</th>
                                <th>Turnover At</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="sessionsTableBody">
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                                    Loading sessions...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function navigateToPage(page) {
            window.location.href = page;
        }

        function toggleSidebar() {
            const panel = document.querySelector('.left-panel');
            if (panel) panel.classList.toggle('open');
        }

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

        async function loadSessions() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const status = document.getElementById('statusFilter').value;

            let url = 'get_user_sessions.php?limit=200';
            if (startDate) url += `&start_date=${startDate}`;
            if (endDate) url += `&end_date=${endDate}`;
            if (status) url += `&status=${status}`;

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    displaySessions(data.sessions);
                } else {
                    alert('Error loading sessions: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load sessions');
            }
        }

        function displaySessions(sessions) {
            const tbody = document.getElementById('sessionsTableBody');

            if (!sessions || sessions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                            No sessions found.
                        </td>
                    </tr>`;
                return;
            }

            tbody.innerHTML = sessions.map(s => {
                const fullName = [s.first_name, s.last_name].filter(Boolean).join(' ') || '-';
                const role = (s.access_level || '').split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
                const duration = s.session_duration_minutes ? `${s.session_duration_minutes} min` : '-';
                const statusClass = `status-${s.session_status}`;

                return `
                    <tr>
                        <td>${s.username || '-'}</td>
                        <td>${fullName}</td>
                        <td>${role}</td>
                        <td>${formatDateTime(s.login_at)}</td>
                        <td>${s.logout_at ? formatDateTime(s.logout_at) : '-'}</td>
                        <td>${s.break_at ? formatDateTime(s.break_at) : '-'}</td>
                        <td>${s.turnover_at ? formatDateTime(s.turnover_at) : '-'}</td>
                        <td>${duration}</td>
                        <td><span class="status-badge ${statusClass}">${s.session_status}</span></td>
                    </tr>`;
            }).join('');
        }

        function formatDateTime(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function clearFilters() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            document.getElementById('statusFilter').value = '';
            loadSessions();
        }

        // Load sessions on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadSessions();
        });
    </script>
</body>
</html>
