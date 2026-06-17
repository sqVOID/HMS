<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>Cancellation Approval</title>
    <link rel="stylesheet" href="Booking.css?v=15">
    <style>
        /* Date filter input */
        .date-filter-container {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 0 12px;
            height: 40px;
            gap: 6px;
        }

        .date-filter-label {
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #7d5c1e;
            font-weight: 600;
            white-space: nowrap;
        }

        .date-filter-input {
            border: none;
            outline: none;
            background: transparent;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #222;
            cursor: pointer;
        }

        .date-filter-clear {
            background: none;
            border: none;
            cursor: pointer;
            color: #999;
            font-size: 16px;
            padding: 0;
            line-height: 1;
            display: none;
        }

        .date-filter-clear:hover {
            color: #e53935;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="role-based-menu.js?v=18"></script>
    <script src="auto_logout.js?v=3" defer></script>
    <script src="cancellation-notification.js?v=3" defer></script>
    <script>
        // Global variable to store user access level
        let userAccessLevel = 'user';

        // Define navigation functions early to prevent "not defined" errors on tablets
        function navigateToPage(page) {
            // Close sidebar on mobile when navigating
            if (window.innerWidth <= 1024) {
                const panel = document.querySelector('.left-panel');
                if (panel) {
                    panel.classList.remove('open');
                }
            }
            window.location.href = page;
        }

        function toggleSystemMaintenance() {
            const submenu = document.getElementById('systemMaintenanceSubmenu');
            const menuItem = document.getElementById('systemMaintenanceMenu');
            if (!submenu || !menuItem) return;

            const isOpen = submenu.classList.contains('open');
            if (isOpen) {
                submenu.classList.remove('open');
                menuItem.classList.remove('expanded');
            } else {
                submenu.classList.add('open');
                menuItem.classList.add('expanded');
            }
        }

        function toggleSidebar() {
            const leftPanel = document.querySelector('.left-panel');
            if (!leftPanel) return;

            if (leftPanel.classList.contains('open')) {
                leftPanel.classList.remove('open');
            } else {
                leftPanel.classList.add('open');
            }
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

        function searchBooking() {
            const bookingId = document.getElementById('bookingIdInput').value.trim();
            const dateValue = document.getElementById('dateFilterInput').value;

            if (bookingId === '' && dateValue === '') {
                loadCancellationRequests();
                return;
            }

            if (dateValue) {
                searchByDate(dateValue);
            } else {
                loadCancellationRequests(bookingId);
            }
        }

        function searchByDate(dateValue) {
            const tbody = document.getElementById('cancellationTableBody');
            tbody.innerHTML = '<tr><td colspan="13" style="text-align: center; padding: 20px;">Loading...</td></tr>';

            const url = `get_cancellation_requests.php?date=${encodeURIComponent(dateValue)}`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCancellationRequests(data.data);
                    } else {
                        tbody.innerHTML = '<tr><td colspan="13" style="text-align: center; padding: 20px; color: red;">Error loading data</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    tbody.innerHTML = '<tr><td colspan="13" style="text-align: center; padding: 20px; color: red;">Network error</td></tr>';
                });
        }

        function clearDateFilter() {
            const dateInput = document.getElementById('dateFilterInput');
            const clearBtn = document.getElementById('dateClearBtn');
            dateInput.value = '';
            clearBtn.style.display = 'none';
            loadCancellationRequests();
        }

        async function loadCancellationRequests(bookingId = null) {
            const status = document.getElementById('selectedStatus').textContent;
            const statusParam = status === 'All Status' ? 'all' : status;

            try {
                const response = await fetch(`get_cancellation_requests.php?status=${statusParam}`);
                const data = await response.json();

                if (data.success) {
                    let requests = data.data;

                    // Filter by booking ID if provided
                    if (bookingId) {
                        requests = requests.filter(r => r.booking_number && r.booking_number.includes(bookingId));
                    }

                    displayCancellationRequests(requests);
                } else {
                    alert('Failed to load cancellation requests');
                }
            } catch (error) {
                console.error('Error loading cancellation requests:', error);
                alert('Error loading cancellation requests');
            }
        }

        function displayCancellationRequests(requests) {
            const tbody = document.getElementById('cancellationTableBody');

            if (requests.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="13" style="text-align: center; padding: 40px; color: #999;">
                            No cancellation requests found.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = requests.map(req => {
                const statusColor = req.status === 'Approved' ? '#4CAF50' :
                    req.status === 'Rejected' ? '#f44336' : '#ff9800';

                // Check if user is auditor to disable action buttons
                const isAuditor = userAccessLevel === 'auditor';
                const disabledStyle = isAuditor ? 'opacity: 0.5; cursor: not-allowed; pointer-events: none;' : 'cursor: pointer;';

                const actionButtons = req.status === 'Pending' ? `
                    <button onclick="approveCancel(${req.id})" 
                          style="padding: 0px 0px; background: transparent; color: white; border: none; border-radius: 100px; ${disabledStyle} font-family: 'Poppins', sans-serif; font-size: 12px;"
                          ${isAuditor ? 'disabled title="Auditors cannot approve/reject cancellations"' : ''}>
                       <img src="Icon/canceliconapprove_system.svg" alt="Approve" class="action-icon" style="${isAuditor ? 'opacity: 0.5;' : ''}">
                    </button>
                    <button onclick="rejectCancel(${req.id})" 
                        style="padding: 0px 0px; background: transparent; color: white; border: none; border-radius: 100px; ${disabledStyle} font-family: 'Poppins', sans-serif; font-size: 12px;"
                        ${isAuditor ? 'disabled title="Auditors cannot approve/reject cancellations"' : ''}>
                       <img src="Icon/canceliconreject_system.svg" alt="Reject" class="action-icon" style="${isAuditor ? 'opacity: 0.5;' : ''}">
                    </button>
                ` : `<span style="color: #999; font-size: 12px;">${req.status}</span>`;

                // Compute duration display, including any extensions from the booking
                let durationDisplay = 'N/A';
                try {
                    const baseDurationRaw = req.base_duration ?? req.duration ?? null;
                    const baseDuration = parseInt(baseDurationRaw) || 0;
                    const unit = (req.duration_unit || 'hours').toString().toLowerCase();

                    const extendHours = parseInt(req.extend_hours || 0) || 0;
                    const extendMinutes = parseInt(req.extend_minutes || 0) || 0;

                    // Convert base duration to hours (nights → 12 hours each)
                    let baseHours = 0;
                    if (!isNaN(baseDuration)) {
                        if (unit === 'night' || unit === 'nights') {
                            baseHours = baseDuration * 12;
                        } else {
                            baseHours = baseDuration;
                        }
                    }

                    // Default: if cancellation_requests.duration already has a formatted string,
                    // use it as fallback when there is no extension.
                    if (extendHours > 0 || extendMinutes > 0) {
                        let totalHours = baseHours + extendHours;
                        let minutes = extendMinutes;

                        if (minutes >= 60) {
                            totalHours += Math.floor(minutes / 60);
                            minutes = minutes % 60;
                        }

                        if (minutes > 0) {
                            durationDisplay = `${totalHours}:${minutes.toString().padStart(2, '0')} Hours (Extended)`;
                        } else {
                            durationDisplay = `${totalHours} Hours (Extended)`;
                        }
                    } else {
                        if (req.duration && req.duration !== '') {
                            durationDisplay = req.duration;
                        } else if (baseHours > 0) {
                            if (unit === 'night' || unit === 'nights') {
                                durationDisplay = `${baseDuration} Night${baseDuration !== 1 ? 's' : ''}`;
                            } else {
                                durationDisplay = `${baseHours} Hours`;
                            }
                        }
                    }
                } catch (e) {
                    console.error('Error computing cancellation duration display:', e, req);
                    durationDisplay = req.duration || 'N/A';
                }

                return `
                    <tr>
                        <td style="min-width: 100px;  max-width: 100px;">${req.booking_number || 'N/A'}</td>
                        <td style="min-width: 120px;">${req.guest_name || 'N/A'}</td>
                        <td style="min-width: 120px;">${req.room_type || 'N/A'}</td>
                        <td style="min-width: 85px; max-width: 95px;">${req.check_in ? new Date(req.check_in).toLocaleDateString() : 'N/A'}</td>
                        <td style="min-width: 85px; max-width: 95px;">${req.check_out ? new Date(req.check_out).toLocaleDateString() : 'N/A'}</td>
                        <td style="min-width: 90px; max-width: 110px;">${durationDisplay}</td>
                        <td style="min-width: 85px; max-width: 100px;">₱${parseFloat(req.refund_amount || 0).toFixed(2)}</td>
                      
                        <td style="min-width: 85px; max-width: 100px;">₱${parseFloat(req.amount_due || 0).toFixed(2)}</td>
                        <td style="min-width: 85px; max-width: 100px;">₱${parseFloat(req.amount_paid || 0).toFixed(2)}</td>
                          <td style="min-width: 85px; max-width: 100px;">₱${parseFloat(req.total_amount || 0).toFixed(2)}</td>
                    
                        <td style="min-width: 250px; max-width: 400px; word-wrap: break-word; white-space: normal; padding: 12px;">${req.reason || 'N/A'}</td>
                        <td style="min-width: 0px; max-width: 0px;"><span style="padding: 4px 12px; background: ${statusColor}; color: white; border-radius: 5px; font-size: 12px; font-weight: 500;">${req.status}</span></td>
                        <td style="min-width: 50px;">${actionButtons}</td>
                    </tr>
                `;
            }).join('');
        }

        async function approveCancel(requestId) {
            // Prevent auditors from approving
            if (userAccessLevel === 'auditor') {
                alert('Auditors cannot approve cancellations. This action is restricted to Admin and Super Admin only.');
                return;
            }

            if (confirm('Are you sure you want to approve this cancellation?')) {
                try {
                    const response = await fetch('update_cancellation_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            request_id: requestId,
                            status: 'Approved',
                            admin_notes: ''
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert(data.message);
                        loadCancellationRequests();
                        // Refresh notification count
                        if (typeof loadCancellationNotification === 'function') {
                            loadCancellationNotification();
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error approving cancellation:', error);
                    alert('Error approving cancellation');
                }
            }
        }

        async function rejectCancel(requestId) {
            // Prevent auditors from rejecting
            if (userAccessLevel === 'auditor') {
                alert('Auditors cannot reject cancellations. This action is restricted to Admin and Super Admin only.');
                return;
            }

            if (confirm('Are you sure you want to reject this cancellation?')) {
                try {
                    const response = await fetch('update_cancellation_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            request_id: requestId,
                            status: 'Rejected',
                            admin_notes: ''
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert(data.message);
                        loadCancellationRequests();
                        // Refresh notification count
                        if (typeof loadCancellationNotification === 'function') {
                            loadCancellationNotification();
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error rejecting cancellation:', error);
                    alert('Error rejecting cancellation');
                }
            }
        }

        function filterByStatus(status) {
            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.status-filter-btn');
            buttons.forEach(btn => btn.classList.remove('active'));

            // Add active class to clicked button
            event.target.classList.add('active');

            // Add your filter logic here
            console.log('Filtering by status:', status);
        }

        function toggleStatusDropdown() {
            const dropdown = document.getElementById('statusDropdown');
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }

        function selectStatus(status) {
            document.getElementById('selectedStatus').textContent = status;
            document.getElementById('statusDropdown').style.display = 'none';
            loadCancellationRequests();
        }

        // View Reason Modal
        function viewReason(reason, bookingNumber) {
            document.getElementById('reasonModalBookingNumber').textContent = bookingNumber;
            document.getElementById('reasonModalText').textContent = reason;
            document.getElementById('reasonModal').style.display = 'flex';
        }

        function closeReasonModal() {
            document.getElementById('reasonModal').style.display = 'none';
        }

        // Close dropdown when clicking outside
        window.onclick = function (event) {
            if (!event.target.matches('.types-dropdown-btn') && !event.target.matches('.types-arrow')) {
                const dropdown = document.getElementById('statusDropdown');
                if (dropdown && dropdown.style.display === 'block') {
                    dropdown.style.display = 'none';
                }
            }
        }

        // Load user profile information
        async function loadUserInfo() {
            try {
                const response = await fetch('get_user_info.php');
                const result = await response.json();

                if (result.success) {
                    const displayName = result.display_name || result.first_name || result.username || 'User';
                    const accessLevel = result.access_level || 'user';

                    // Store access level globally
                    userAccessLevel = accessLevel;

                    // Update avatar with first letter
                    const avatarEl = document.getElementById('userAvatar');
                    if (avatarEl) {
                        avatarEl.textContent = displayName.charAt(0).toUpperCase();
                    }

                    // Update username
                    const nameEl = document.getElementById('userName');
                    if (nameEl) {
                        nameEl.textContent = displayName;
                    }

                    // Update role (format: super_admin -> Super Admin)
                    const roleEl = document.getElementById('userRole');
                    if (roleEl) {
                        const formattedRole = accessLevel.split('_').map(word =>
                            word.charAt(0).toUpperCase() + word.slice(1)
                        ).join(' ');
                        roleEl.textContent = formattedRole;
                    }
                } else {
                    console.error('Failed to load user info');
                }
            } catch (error) {
                console.error('Error loading user info:', error);
            }
        }

        // Load cancellation requests on page load
        document.addEventListener('DOMContentLoaded', async function () {
            // Load user info first, then load cancellation requests
            await loadUserInfo(); // Load user profile information
            loadCancellationRequests(); // Now load requests with correct access level

            // Date filter: auto-trigger on change
            const dateInput = document.getElementById('dateFilterInput');
            const clearBtn = document.getElementById('dateClearBtn');
            if (dateInput) {
                dateInput.addEventListener('change', function () {
                    if (this.value) {
                        clearBtn.style.display = 'inline';
                        searchByDate(this.value);
                    } else {
                        clearDateFilter();
                    }
                });
            }
        });
    </script>


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
            <img src="Icon/MoonClave_Logo.svg" alt="MoonClave Hotel Logo" class="logo-img">
            <nav class="sidebar-menu">
                <ul>
                    <li class="sidebar-menu-item" style="display: none;" data-page="Createuser.php"
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

                        <li class="sidebar-menu-item" data-page="CashDeposit.php"
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
                        <img src="Icon/reservationicon_system.svg" class="sidebar-icon" alt="Reservation List">
                        <span>Reservation List</span>
                    </li>
                    <li class="sidebar-menu-item active" data-page="Cancelpage.php"
                        onclick="navigateToPage('Cancelpage.php')">
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
                </ul>
            </nav>
        </div>
        <div class="right-panel">
            <div class="header-bar">
                <div class="user-profile">
                    <div class="user-avatar" id="userAvatar">U</div>
                    <div class="user-info">
                        <div class="user-name" id="userName">Loading...</div>
                        <div class="user-role" id="userRole">User</div>
                    </div>
                </div>
            </div>
            <div class="content-container">
                <h2 class="header-title">Cancellation Approval</h2>

                <!-- Search and Filter Section -->
                <div style="margin-top: 20px; margin-bottom: 20px;">
                    <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                        <!-- Date Filter -->

                        <div style="flex: 1; min-width: 250px;">
                            <label
                                style="display: block; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 8px; font-family: 'Poppins', sans-serif;">Booking
                                ID:</label>
                            <input type="text" id="bookingIdInput" placeholder="Enter Booking ID"
                                style="width: 100%; padding: 10px 16px; border: 1px solid #e0e0e0; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 14px; box-sizing: border-box;">
                        </div>




                        <button onclick="searchBooking()"
                            style="padding: 10px 24px; background: #000000ff; color: #fff; border: none; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; height: 40px; white-space: nowrap;">
                            Search
                        </button>

                        <div class="date-filter-container">
                            <input type="date" id="dateFilterInput" class="date-filter-input"
                                title="Filter by requested date">
                            <button class="date-filter-clear" id="dateClearBtn" title="Clear date"
                                onclick="clearDateFilter()">&#x2715;</button>
                        </div>

                        <div class="types-dropdown-wrapper">
                            <button class="types-dropdown-btn" onclick="toggleStatusDropdown()">
                                <span id="selectedStatus">All Status</span>
                                <span class="types-arrow">&#9662;</span>
                            </button>
                            <div id="statusDropdown" class="types-dropdown-menu" style="display: none;">
                                <div class="types-dropdown-item" onclick="selectStatus('All Status')">All Status</div>
                                <div class="types-dropdown-item" onclick="selectStatus('Pending')">Pending</div>
                                <div class="types-dropdown-item" onclick="selectStatus('Approved')">Approved</div>
                                <div class="types-dropdown-item" onclick="selectStatus('Rejected')">Rejected</div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Cancellation Table -->
                <div class="table-wrapper" style="margin-top: 24px; overflow-x: auto;">
                    <table class="booking-table" style="width: 100%; table-layout: auto;">
                        <thead>
                            <tr>
                                <th style="min-width: 120px;">Booking ID</th>
                                <th style="min-width: 120px;">Guest Name</th>
                                <th style="min-width: 120px;">Room Type</th>
                                <th style="min-width: 85px; max-width: 95px;">Check-in</th>
                                <th style="min-width: 85px; max-width: 95px;">Check-out</th>
                                <th style="min-width: 90px; max-width: 110px;">Duration</th>
                                <th style="min-width: 85px; max-width: 100px;">Refund Amount</th>
                                <th style="min-width: 85px; max-width: 100px;">Amount Due</th>
                                <th style="min-width: 85px; max-width: 100px;">Amount Paid</th>
                                <th style="min-width: 85px; max-width: 100px;">Total Amount</th>
                                <th style="min-width: 300px; max-width: 350px;">Reason</th>
                                <th style="min-width: 100px; max-width: 100px;">Status</th>
                                <th style="min-width: 90px; max-width: 90px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="cancellationTableBody">
                            <!-- Sample row - replace with dynamic data -->
                            <tr>
                                <td colspan="13" style="text-align: center; padding: 40px; color: #999;">
                                    No cancellation requests found. Enter a Booking ID to search.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Reason Modal -->
    <div id="reasonModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; font-family: 'Poppins', sans-serif;">
        <div
            style="background: white; border-radius: 5px; padding: 0; max-width: 600px; width: 90%; max-height: 80vh; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
            <!-- Modal Header -->
            <div
                style="background: linear-gradient(135deg, #ffffffff 0%, #ffffffff 100%); color: black; padding: 20px 30px; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;border-bottom: 2px solid #e5e7eb;">
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 600;">Cancellation Reason</h3>
                    <p id="reasonModalBookingNumber" style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;"></p>
                </div>
                <button onclick="closeReasonModal()"
                    style="background: transparent; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>

            <!-- Modal Body -->
            <div style="padding: 30px; max-height: 400px; overflow-y: auto;">
                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                    <p id="reasonModalText"
                        style="margin: 0; font-size: 14px; line-height: 1.6; color: #374151; white-space: pre-wrap; word-wrap: break-word;">
                    </p>
                </div>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 20px 30px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end;">
                <button onclick="closeReasonModal()"
                    style="padding: 10px 24px; background: #000000; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'Poppins', sans-serif; transition: background 0.2s;"
                    onmouseover="this.style.background='#333333'" onmouseout="this.style.background='#000000'">
                    Close
                </button>
            </div>
        </div>
    </div>
</body>

</html>