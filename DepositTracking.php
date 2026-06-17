<?php
require_once 'access_check.php';
checkAccess('DepositTracking.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>Deposit Tracking</title>
    <link rel="stylesheet" href="Inventory.css">
    <link rel="stylesheet" href="DepositTracking.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="role-based-menu.js?v=18" defer></script>
    <script src="auto_logout.js" defer></script>
    <script src="cancellation-notification.js?v=16" defer></script>
</head>

<body>
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

    <div class="split-container">
        <div class="left-panel" id="leftPanel">
            <button class="sidebar-collapse-btn" id="collapseBtn" onclick="toggleSidebarMinimize()">
                <img id="collapseIcon" src="Icon/left-arrow_minimize.svg" alt="Minimize"
                    style="width: 24px; height: 18px;">
                <span class="collapse-tooltip" id="collapseTooltip">Minimize</span>
            </button>
            <img src="Icon/MoonClave_Logo.svg" alt="Luna Group Logo" class="logo-img">
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
                    <li class="sidebar-menu-item active" data-page="DepositTracking.php"
                        onclick="navigateToPage('DepositTracking.php')">
                        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round"
                            style="display: inline-block; vertical-align: middle; margin-right: 12px; width: 20px; height: 20px;">
                            <path d="M21 12V7H3v10h9" />
                            <path d="M16 5V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v2" />
                            <path d="M18 17v5" />
                            <path d="m15 19 3 3 3-3" />
                            <circle cx="12" cy="12" r="2" />
                        </svg>
                        <span>Deposit Tracking</span>
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
                </ul>
            </nav>
        </div>

        <div class="right-panel">
            <div class="header-bar" style="justify-content: flex-end;">
                <div class="user-profile">
                    <div class="user-avatar" id="userAvatar">U</div>
                    <div class="user-info">
                        <div class="user-name" id="userName">Loading...</div>
                        <div class="user-role" id="userRole">User</div>
                    </div>
                </div>
            </div>

            <div class="content-container">
                <div class="header-title-row">
                    <div>
                        <h2 class="header-title">Deposit Tracking</h2>
                        <div class="running-time-subtitle" id="runningTimeDisplay">—</div>
                    </div>
                </div>
                <!--
                <div class="frontend-notice">
                    Front-end preview only — data is saved in your browser (localStorage). Database integration coming later.
                </div>
                -->
                <div class="action-bar">
                    <button class="add-deposit-btn" onclick="openAddModal()">
                        <span style="font-size:18px;">+</span> Add Deposit
                    </button>
                    <input type="text" id="searchInput" class="search-input-simple" placeholder="Search..."
                        oninput="renderTable()">
                    <select id="statusFilterSelect" class="status-filter-select" onchange="setStatusFilter(this.value)">
                        <option value="all">All</option>
                        <option value="pending">Pending</option>
                        <option value="exact">Exact</option>
                        <option value="short">Short</option>
                        <option value="over">Over</option>
                    </select>
                </div>

                <div class="table-wrapper">
                    <table class="deposit-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Expected Amount</th>
                                <th>Actual Amount</th>
                                <th>Variance Amount</th>
                                <th>Reason</th>
                                <th>Deposit Date &amp; Time</th>
                                <th>Target Date &amp; Time</th>
                                <th>Reconciled Date &amp; Time</th>
                                <th>Reconciled By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="depositTableBody">
                            <tr>
                                <td colspan="11" class="empty-state">No deposits yet. Click "Add Deposit" to start.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Deposit Modal -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-container">
            <h2 class="modal-title">Add Deposit</h2>
            <form id="addDepositForm" onsubmit="saveDeposit(event)">
                <div class="modal-content">
                    <div class="form-group">
                        <label class="form-label" for="addReference">Description</label>
                        <input type="text" id="addReference" class="form-input"
                            placeholder="e.g. Morning Shift Deposit">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="addExpected">Deposit Amount (₱) <span
                                class="required">*</span></label>
                        <input type="number" id="addExpected" class="form-input" step="0.01" min="0.01"
                            placeholder="10000.00" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="addDepositDateTime">Deposit Date &amp; Time <span
                                    class="required">*</span></label>
                            <input type="datetime-local" id="addDepositDateTime" class="form-input" required>
                            <!--<span class="form-hint">Uses current running time by default</span>-->
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="addTargetDateTime">Target Date &amp; Time <span
                                    class="required">*</span></label>
                            <input type="datetime-local" id="addTargetDateTime" class="form-input" required>
                            <!-- <span class="form-hint">When this deposit should be verified</span> -->
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="addNotes">Notes</label>
                        <textarea id="addNotes" class="form-textarea" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn-cancel" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="modal-btn-save">Save Deposit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Deposit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-container">
            <h2 class="modal-title">Edit Deposit</h2>
            <form id="editDepositForm" onsubmit="updateDeposit(event)">
                <input type="hidden" id="editId">
                <div class="modal-content">
                    <div class="form-group">
                        <label class="form-label" for="editReference">Reference / Label</label>
                        <input type="text" id="editReference" class="form-input"
                            placeholder="e.g. Morning Shift Deposit">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editExpected">Deposit Amount (₱) <span
                                class="required">*</span></label>
                        <input type="number" id="editExpected" class="form-input" step="0.01" min="0.01"
                            placeholder="10000.00" required>
                    </div>
                    <div class="form-group" id="editActualGroup" style="display:none;">
                        <label class="form-label" for="editActual">Actual Amount Received (₱) <span
                                class="required">*</span></label>
                        <input type="number" id="editActual" class="form-input" step="0.01" min="0"
                            placeholder="10000.00">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="editDepositDateTime">Deposit Date &amp; Time <span
                                    class="required">*</span></label>
                            <input type="datetime-local" id="editDepositDateTime" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editTargetDateTime">Target Date &amp; Time <span
                                    class="required">*</span></label>
                            <input type="datetime-local" id="editTargetDateTime" class="form-input" required>
                        </div>
                    </div>
                    <div class="form-group" id="editReconcileDateTimeGroup" style="display:none;">
                        <label class="form-label" for="editReconcileDateTime">Reconcile Date &amp; Time <span
                                class="required">*</span></label>
                        <input type="datetime-local" id="editReconcileDateTime" class="form-input">
                    </div>
                    <div class="form-group" id="editReasonGroup" style="display:none;">
                        <label class="form-label" for="editReason">Reason for Variance <span
                                class="required">*</span></label>
                        <textarea id="editReason" class="form-textarea"
                            placeholder="Explain why the amount is short or over..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editNotes">Notes</label>
                        <textarea id="editNotes" class="form-textarea" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="modal-btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reconcile Modal -->
    <div id="reconcileModal" class="modal-overlay">
        <div class="modal-container">
            <h2 class="modal-title">Reconcile Deposit</h2>
            <form id="reconcileForm" onsubmit="saveReconcile(event)">
                <input type="hidden" id="reconcileId">
                <div class="modal-content">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-item-label">Reference</div>
                            <div class="detail-item-value" id="reconcileRef">—</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-item-label">Expected Amount</div>
                            <div class="detail-item-value" id="reconcileExpected">—</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-item-label">Deposit Date &amp; Time</div>
                            <div class="detail-item-value" id="reconcileDepositDT">—</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-item-label">Target Date &amp; Time</div>
                            <div class="detail-item-value" id="reconcileTargetDT">—</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reconcileDateTime">Reconcile Date &amp; Time <span
                                class="required">*</span></label>
                        <input type="datetime-local" id="reconcileDateTime" class="form-input" required>
                        <span class="form-hint">Current running time is used by default</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reconcileActual">Actual Amount Received (₱) <span
                                class="required">*</span></label>
                        <input type="number" id="reconcileActual" class="form-input" step="0.01" min="0"
                            placeholder="Enter actual amount" required oninput="updateVariancePreview()">
                    </div>

                    <div id="variancePreview" class="variance-box exact" style="display:none;">
                        <div class="variance-label" id="varianceLabel">Variance</div>
                        <div class="variance-amount" id="varianceAmount">₱0.00</div>
                    </div>

                    <div class="form-group" id="reasonGroup" style="display:none;">
                        <label class="form-label" for="reconcileReason">Reason for Variance <span
                                class="required">*</span></label>
                        <textarea id="reconcileReason" class="form-textarea"
                            placeholder="Explain why the amount is short or over..."></textarea>
                    </div>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn-cancel" onclick="closeReconcileModal()">Cancel</button>
                    <button type="submit" class="modal-btn-save">Confirm Reconcile</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal-container">
            <h2 class="modal-title">Deposit Details</h2>
            <div class="modal-content" id="viewModalContent"></div>
            <div class="modal-buttons">
                <button type="button" class="modal-btn-cancel" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        const STORAGE_KEY = 'hms_deposit_tracking_v1';
        let deposits = [];
        let currentFilter = 'all';
        let currentUserName = 'User';
        let runningTimeInterval = null;
        let tableTimerInterval = null;

        document.addEventListener('DOMContentLoaded', function () {
            setActiveMenuItem();
            loadUserInfo();
            loadDeposits();
            startRunningTime();
            startTableTimers();
        });

        /* ── Running Time ─────────────────────────────────────────── */
        function startRunningTime() {
            updateRunningTime();
            if (runningTimeInterval) clearInterval(runningTimeInterval);
            runningTimeInterval = setInterval(updateRunningTime, 1000);
        }

        function updateRunningTime() {
            const el = document.getElementById('runningTimeDisplay');
            if (el) el.textContent = formatRunningTime(new Date());
        }

        function formatRunningTime(date) {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const month = months[date.getMonth()];
            const day = date.getDate();
            const year = date.getFullYear();
            let hours = date.getHours();
            const mins = String(date.getMinutes()).padStart(2, '0');
            const secs = String(date.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            return `${month} ${day}, ${year} — ${hours}:${mins}:${secs} ${ampm}`;
        }

        function toDatetimeLocalValue(date) {
            const d = new Date(date);
            const pad = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }

        function formatDisplayDateTime(isoStr) {
            if (!isoStr) return '—';
            const date = new Date(isoStr);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const month = months[date.getMonth()];
            const day = date.getDate();
            const year = date.getFullYear();
            let hours = date.getHours();
            const mins = String(date.getMinutes()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            return `${month} ${day}, ${year}<br><span style="color:#666;font-size:11px;">${hours}:${mins} ${ampm}</span>`;
        }

        function formatCurrency(value) {
            const n = Number(value);
            return '₱' + (isNaN(n) ? 0 : n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatDuration(ms) {
            if (ms < 0) ms = Math.abs(ms);
            const totalSecs = Math.floor(ms / 1000);
            const days = Math.floor(totalSecs / 86400);
            const hrs = Math.floor((totalSecs % 86400) / 3600);
            const mins = Math.floor((totalSecs % 3600) / 60);
            const secs = totalSecs % 60;
            const parts = [];
            if (days > 0) parts.push(`${days}d`);
            if (hrs > 0 || days > 0) parts.push(`${hrs}h`);
            parts.push(`${mins}m`);
            parts.push(`${secs}s`);
            return parts.join(' ');
        }

        function startTableTimers() {
            if (tableTimerInterval) clearInterval(tableTimerInterval);
            tableTimerInterval = setInterval(updateElapsedCells, 1000);
        }

        function updateElapsedCells() {
            document.querySelectorAll('[data-elapsed-from]').forEach(el => {
                const from = el.getAttribute('data-elapsed-from');
                const to = el.getAttribute('data-elapsed-to');
                const mode = el.getAttribute('data-elapsed-mode');
                const now = Date.now();
                const fromMs = new Date(from).getTime();
                const toMs = to ? new Date(to).getTime() : now;

                if (mode === 'elapsed') {
                    el.textContent = 'Elapsed ' + formatDuration(now - fromMs);
                } else if (mode === 'countdown') {
                    const remaining = toMs - now;
                    if (remaining > 0) {
                        el.textContent = 'Target in ' + formatDuration(remaining);
                        el.classList.remove('overdue');
                    } else {
                        el.textContent = 'Overdue by ' + formatDuration(Math.abs(remaining));
                        el.classList.add('overdue');
                    }
                }
            });
        }

        /* ── Storage ──────────────────────────────────────────────── */
        function loadDeposits() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                deposits = raw ? JSON.parse(raw) : [];
            } catch (e) {
                deposits = [];
            }
            renderTable();
            updateSummary();
        }

        function saveDeposits() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(deposits));
            renderTable();
            updateSummary();
        }

        function generateId() {
            return 'dep_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
        }

        /* ── Add Deposit ──────────────────────────────────────────── */
        function openAddModal() {
            document.getElementById('addDepositForm').reset();
            const now = toDatetimeLocalValue(new Date());
            document.getElementById('addDepositDateTime').value = now;
            document.getElementById('addTargetDateTime').value = now;
            document.getElementById('addModal').classList.add('open');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('open');
        }

        function saveDeposit(e) {
            e.preventDefault();
            const expected = parseFloat(document.getElementById('addExpected').value);
            const depositDT = document.getElementById('addDepositDateTime').value;
            const targetDT = document.getElementById('addTargetDateTime').value;

            if (isNaN(expected) || expected <= 0) {
                alert('Please enter a valid expected amount.');
                return;
            }
            if (new Date(targetDT) <= new Date(depositDT)) {
                alert('Target date & time must be after the deposit date & time.');
                return;
            }

            deposits.unshift({
                id: generateId(),
                reference: document.getElementById('addReference').value.trim() || 'Untitled Deposit',
                expectedAmount: expected,
                depositDateTime: new Date(depositDT).toISOString(),
                targetDateTime: new Date(targetDT).toISOString(),
                notes: document.getElementById('addNotes').value.trim(),
                status: 'pending',
                actualAmount: null,
                reconcileDateTime: null,
                variance: null,
                reason: null,
                createdBy: currentUserName,
                createdAt: new Date().toISOString()
            });

            saveDeposits();
            closeAddModal();
        }

        /* ── Reconcile ────────────────────────────────────────────── */
        function openReconcileModal(id) {
            const dep = deposits.find(d => d.id === id);
            if (!dep) return;

            document.getElementById('reconcileId').value = id;
            document.getElementById('reconcileRef').textContent = dep.reference;
            document.getElementById('reconcileExpected').textContent = formatCurrency(dep.expectedAmount);
            document.getElementById('reconcileDepositDT').innerHTML = formatDisplayDateTime(dep.depositDateTime);
            document.getElementById('reconcileTargetDT').innerHTML = formatDisplayDateTime(dep.targetDateTime);
            document.getElementById('reconcileDateTime').value = toDatetimeLocalValue(new Date());
            document.getElementById('reconcileActual').value = '';
            document.getElementById('reconcileReason').value = '';
            document.getElementById('variancePreview').style.display = 'none';
            document.getElementById('reasonGroup').style.display = 'none';
            document.getElementById('reconcileModal').classList.add('open');
        }

        function closeReconcileModal() {
            document.getElementById('reconcileModal').classList.remove('open');
        }

        function updateVariancePreview() {
            const id = document.getElementById('reconcileId').value;
            const dep = deposits.find(d => d.id === id);
            if (!dep) return;

            const actual = parseFloat(document.getElementById('reconcileActual').value);
            const preview = document.getElementById('variancePreview');
            const reasonGroup = document.getElementById('reasonGroup');
            const label = document.getElementById('varianceLabel');
            const amount = document.getElementById('varianceAmount');

            if (isNaN(actual)) {
                preview.style.display = 'none';
                reasonGroup.style.display = 'none';
                return;
            }

            const variance = actual - dep.expectedAmount;
            preview.style.display = 'block';
            preview.className = 'variance-box';

            if (variance === 0) {
                preview.classList.add('exact');
                label.textContent = 'Exact Match';
                amount.textContent = formatCurrency(0);
                reasonGroup.style.display = 'none';
                document.getElementById('reconcileReason').required = false;
            } else if (variance < 0) {
                preview.classList.add('short');
                label.textContent = 'Short by';
                amount.textContent = formatCurrency(Math.abs(variance));
                reasonGroup.style.display = 'block';
                document.getElementById('reconcileReason').required = true;
            } else {
                preview.classList.add('over');
                label.textContent = 'Over by';
                amount.textContent = formatCurrency(variance);
                reasonGroup.style.display = 'block';
                document.getElementById('reconcileReason').required = true;
            }
        }

        function saveReconcile(e) {
            e.preventDefault();
            const id = document.getElementById('reconcileId').value;
            const dep = deposits.find(d => d.id === id);
            if (!dep) return;

            const actual = parseFloat(document.getElementById('reconcileActual').value);
            const reason = document.getElementById('reconcileReason').value.trim();
            const reconcileDT = document.getElementById('reconcileDateTime').value;
            const variance = actual - dep.expectedAmount;

            if (isNaN(actual) || actual < 0) {
                alert('Please enter a valid actual amount.');
                return;
            }
            if (variance !== 0 && !reason) {
                alert('Please provide a reason for the variance.');
                return;
            }

            dep.actualAmount = actual;
            dep.reconcileDateTime = new Date(reconcileDT).toISOString();
            dep.reconciledBy = currentUserName;
            dep.variance = variance;
            dep.reason = variance !== 0 ? reason : null;
            dep.status = variance === 0 ? 'exact' : (variance < 0 ? 'short' : 'over');

            saveDeposits();
            closeReconcileModal();
        }

        /* ── Edit Deposit ─────────────────────────────────────────── */
        function openEditModal(id) {
            const dep = deposits.find(d => d.id === id);
            if (!dep) return;

            document.getElementById('editId').value = id;
            document.getElementById('editReference').value = dep.reference;
            document.getElementById('editExpected').value = dep.expectedAmount;
            document.getElementById('editDepositDateTime').value = toDatetimeLocalValue(dep.depositDateTime);
            document.getElementById('editTargetDateTime').value = toDatetimeLocalValue(dep.targetDateTime);
            document.getElementById('editNotes').value = dep.notes || '';

            const actualGroup = document.getElementById('editActualGroup');
            const reconcileDateTimeGroup = document.getElementById('editReconcileDateTimeGroup');
            const reasonGroup = document.getElementById('editReasonGroup');

            if (dep.status !== 'pending') {
                actualGroup.style.display = 'block';
                document.getElementById('editActual').value = dep.actualAmount;
                document.getElementById('editActual').required = true;

                reconcileDateTimeGroup.style.display = 'block';
                document.getElementById('editReconcileDateTime').value = toDatetimeLocalValue(dep.reconcileDateTime);
                document.getElementById('editReconcileDateTime').required = true;

                if (dep.status === 'short' || dep.status === 'over') {
                    reasonGroup.style.display = 'block';
                    document.getElementById('editReason').value = dep.reason || '';
                    document.getElementById('editReason').required = true;
                } else {
                    reasonGroup.style.display = 'none';
                    document.getElementById('editReason').required = false;
                }
            } else {
                actualGroup.style.display = 'none';
                document.getElementById('editActual').required = false;
                reconcileDateTimeGroup.style.display = 'none';
                document.getElementById('editReconcileDateTime').required = false;
                reasonGroup.style.display = 'none';
                document.getElementById('editReason').required = false;
            }

            document.getElementById('editModal').classList.add('open');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('open');
        }

        function updateDeposit(e) {
            e.preventDefault();
            const id = document.getElementById('editId').value;
            const dep = deposits.find(d => d.id === id);
            if (!dep) return;

            const expected = parseFloat(document.getElementById('editExpected').value);
            const depositDT = document.getElementById('editDepositDateTime').value;
            const targetDT = document.getElementById('editTargetDateTime').value;
            const notes = document.getElementById('editNotes').value.trim();

            if (isNaN(expected) || expected <= 0) {
                alert('Please enter a valid expected amount.');
                return;
            }
            if (new Date(targetDT) <= new Date(depositDT)) {
                alert('Target date & time must be after the deposit date & time.');
                return;
            }

            dep.reference = document.getElementById('editReference').value.trim() || 'Untitled Deposit';
            dep.expectedAmount = expected;
            dep.depositDateTime = new Date(depositDT).toISOString();
            dep.targetDateTime = new Date(targetDT).toISOString();
            dep.notes = notes;

            if (dep.status !== 'pending') {
                const actual = parseFloat(document.getElementById('editActual').value);
                const reconcileDT = document.getElementById('editReconcileDateTime').value;
                const reason = document.getElementById('editReason').value.trim();
                const variance = actual - expected;

                if (isNaN(actual) || actual < 0) {
                    alert('Please enter a valid actual amount.');
                    return;
                }
                if (variance !== 0 && !reason) {
                    alert('Please provide a reason for the variance.');
                    return;
                }

                dep.actualAmount = actual;
                dep.reconcileDateTime = new Date(reconcileDT).toISOString();
                dep.variance = variance;
                dep.reason = variance !== 0 ? reason : null;
                dep.status = variance === 0 ? 'exact' : (variance < 0 ? 'short' : 'over');
            }

            saveDeposits();
            closeEditModal();
        }

        /* ── View / Delete ────────────────────────────────────────── */
        function openViewModal(id) {
            const dep = deposits.find(d => d.id === id);
            if (!dep) return;

            const statusLabels = { pending: 'Pending', exact: 'Exact Match', short: 'Short', over: 'Over' };
            let html = `
                <div class="detail-grid">
                    <div class="detail-item"><div class="detail-item-label">Reference</div><div class="detail-item-value">${escapeHtml(dep.reference)}</div></div>
                    <div class="detail-item"><div class="detail-item-label">Status</div><div class="detail-item-value"><span class="status-badge ${dep.status}">${statusLabels[dep.status]}</span></div></div>
                    <div class="detail-item"><div class="detail-item-label">Expected Amount</div><div class="detail-item-value">${formatCurrency(dep.expectedAmount)}</div></div>
                    <div class="detail-item"><div class="detail-item-label">Actual Amount</div><div class="detail-item-value">${dep.actualAmount !== null ? formatCurrency(dep.actualAmount) : '—'}</div></div>
                    <div class="detail-item"><div class="detail-item-label">Deposit Date &amp; Time</div><div class="detail-item-value">${formatDisplayDateTime(dep.depositDateTime)}</div></div>
                    <div class="detail-item"><div class="detail-item-label">Target Date &amp; Time</div><div class="detail-item-value">${formatDisplayDateTime(dep.targetDateTime)}</div></div>
                    ${dep.reconcileDateTime ? `<div class="detail-item"><div class="detail-item-label">Reconciled At</div><div class="detail-item-value">${formatDisplayDateTime(dep.reconcileDateTime)}</div></div>` : ''}
                    ${dep.reconciledBy ? `<div class="detail-item"><div class="detail-item-label">Reconciled By</div><div class="detail-item-value">${escapeHtml(dep.reconciledBy)}</div></div>` : ''}
                    ${dep.variance !== null ? `<div class="detail-item"><div class="detail-item-label">Variance</div><div class="detail-item-value">${dep.variance === 0 ? 'Exact' : (dep.variance < 0 ? 'Short ' + formatCurrency(Math.abs(dep.variance)) : 'Over ' + formatCurrency(dep.variance))}</div></div>` : ''}
                    <div class="detail-item"><div class="detail-item-label">Created By</div><div class="detail-item-value">${escapeHtml(dep.createdBy)}</div></div>
                    ${dep.notes ? `<div class="detail-item full-width"><div class="detail-item-label">Notes</div><div class="detail-item-value">${escapeHtml(dep.notes)}</div></div>` : ''}
                    ${dep.reason ? `<div class="detail-item full-width"><div class="detail-item-label">Variance Reason</div><div class="detail-item-value">${escapeHtml(dep.reason)}</div></div>` : ''}
                </div>`;

            document.getElementById('viewModalContent').innerHTML = html;
            document.getElementById('viewModal').classList.add('open');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('open');
        }

        function deleteDeposit(id) {
            if (!confirm('Delete this deposit record?')) return;
            deposits = deposits.filter(d => d.id !== id);
            saveDeposits();
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        /* ── Table & Summary ──────────────────────────────────────── */
        function setStatusFilter(filter) {
            currentFilter = filter;
            renderTable();
        }

        function updateSummary() {
            // Summary cards are removed
        }

        function renderTable() {
            const tbody = document.getElementById('depositTableBody');
            const search = (document.getElementById('searchInput').value || '').toLowerCase().trim();
            let filtered = deposits;

            if (currentFilter !== 'all') {
                filtered = filtered.filter(d => d.status === currentFilter);
            }
            if (search) {
                filtered = filtered.filter(d =>
                    (d.reference || '').toLowerCase().includes(search) ||
                    (d.notes || '').toLowerCase().includes(search) ||
                    (d.createdBy || '').toLowerCase().includes(search)
                );
            }

            if (filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="11" class="empty-state">No deposits found.</td></tr>`;
                return;
            }

            const statusLabels = { pending: 'Pending', exact: 'Exact', short: 'Short', over: 'Over' };
            const now = Date.now();

            tbody.innerHTML = filtered.map(dep => {
                const varianceText = dep.variance === null ? '—'
                    : dep.variance === 0 ? '<span class="variance-text exact">Exact</span>'
                        : dep.variance < 0 ? `<span class="variance-text short">Short ${formatCurrency(Math.abs(dep.variance))}</span>`
                            : `<span class="variance-text over">Over ${formatCurrency(dep.variance)}</span>`;

                const expectedText = formatCurrency(dep.expectedAmount);
                const actualText = dep.actualAmount !== null ? formatCurrency(dep.actualAmount) : '—';

                const depositCell = formatDisplayDateTime(dep.depositDateTime);

                const targetCell = formatDisplayDateTime(dep.targetDateTime);

                const reconciledDateText = dep.reconcileDateTime ? formatDisplayDateTime(dep.reconcileDateTime) : '—';
                const reconciledByText = dep.reconciledBy ? escapeHtml(dep.reconciledBy) : '—';
                const reasonText = dep.reason ? escapeHtml(dep.reason) : '—';

                const actions = dep.status === 'pending'
                    ? `<button class="table-action-btn primary" onclick="openReconcileModal('${dep.id}')" title="Reconcile">
                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                       </button>
                       <button class="table-action-btn secondary" onclick="openEditModal('${dep.id}')" title="Edit">
                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                       </button>
                       <button class="table-action-btn secondary" onclick="openViewModal('${dep.id}')" title="View Details">
                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                       </button>
                       <button class="table-action-btn danger" onclick="deleteDeposit('${dep.id}')" title="Delete">
                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                       </button>`
                    : `<button class="table-action-btn secondary" onclick="openEditModal('${dep.id}')" title="Edit">
                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                       </button>
                       <button class="table-action-btn secondary" onclick="openViewModal('${dep.id}')" title="View Details">
                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                       </button>
                       <button class="table-action-btn danger" onclick="deleteDeposit('${dep.id}')" title="Delete">
                           <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                       </button>`;

                return `<tr>
                    <td><strong>${escapeHtml(dep.reference)}</strong></td>
                    <td>${expectedText}</td>
                    <td>${actualText}</td>
                    <td>${varianceText}</td>
                    <td>${reasonText}</td>
                    <td>${depositCell}</td>
                    <td>${targetCell}</td>
                    <td>${reconciledDateText}</td>
                    <td>${reconciledByText}</td>
                    <td><span class="status-badge ${dep.status}">${statusLabels[dep.status]}</span></td>
                    <td><div style="display: flex; gap: 4px; align-items: center;">${actions}</div></td>
                </tr>`;
            }).join('');

            updateElapsedCells();
        }

        /* ── Sidebar & User ─────────────────────────────────────────── */
        function navigateToPage(page) {
            if (window.innerWidth <= 1024) {
                const panel = document.querySelector('.left-panel');
                if (panel) panel.classList.remove('open');
            }
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

        function setActiveMenuItem() {
            const currentPage = window.location.pathname.split('/').pop() || 'DepositTracking.php';
            document.querySelectorAll('.sidebar-menu-item:not(.collapsible-menu), .submenu-item').forEach(item => {
                const pageName = item.getAttribute('data-page');
                item.classList.toggle('active', pageName === currentPage);
            });
        }

        async function loadUserInfo() {
            try {
                const response = await fetch('get_user_info.php');
                const result = await response.json();
                if (result.success) {
                    const displayName = result.display_name || result.first_name || result.username || 'User';
                    currentUserName = displayName;
                    const avatarEl = document.getElementById('userAvatar');
                    if (avatarEl) avatarEl.textContent = displayName.charAt(0).toUpperCase();
                    const nameEl = document.getElementById('userName');
                    if (nameEl) nameEl.textContent = displayName;
                    const roleEl = document.getElementById('userRole');
                    if (roleEl) {
                        const level = result.access_level || 'user';
                        roleEl.textContent = level.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
                    }
                }
            } catch (error) {
                console.error('Error loading user info:', error);
            }
        }

        document.addEventListener('click', function (event) {
            if (window.innerWidth <= 1024) {
                const panel = document.querySelector('.left-panel');
                const toggleBtn = document.querySelector('.mobile-menu-toggle');
                if (panel && panel.classList.contains('open')) {
                    if (!panel.contains(event.target) && !toggleBtn.contains(event.target)) {
                        panel.classList.remove('open');
                    }
                }
            }
        });
    </script>
</body>

</html>