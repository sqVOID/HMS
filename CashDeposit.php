<?php
require_once 'access_check.php';
checkAccess('CashDeposit.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>Running Cash Deposit</title>

    <!-- Base Layout Style -->
    <link rel="stylesheet" href="Inventory.css">
    <!-- Sub-page specific base style -->
    <link rel="stylesheet" href="CashDeposit.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="role-based-menu.js?v=18" defer></script>
    <script src="auto_logout.js" defer></script>
    <script src="cancellation-notification.js?v=17" defer></script>

    <style>
        /* Custom Premium Style overrides for Cash Deposit */
        .summary-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .premium-card {
            background: #ffffff;
            color: #222;
            border-radius: 10px;
            padding: 20px 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
        }

        .premium-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            border-color: #1b5e20;
        }

        .premium-card.cash-card {
            border-left: 4px solid #8b8b8bff;
        }

        .premium-card.shift-card {
            border-left: 4px solid #8b8b8bff;
        }

        .card-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b7280;
            margin-bottom: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-icon {
            width: 16px;
            height: 16px;
            opacity: 0.7;
        }

        .card-value {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 4px;
            font-variant-numeric: tabular-nums;
            color: #111827;
            line-height: 1.2;
        }

        .card-meta {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
            margin-top: 6px;
        }

        .card-meta strong {
            color: #374151;
            font-weight: 600;
        }

        .deposit-recorded-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #dcfce7;
            color: #16a34a;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 12px;
            margin-top: 8px;
            letter-spacing: 0.3px;
        }

        .deposit-recorded-badge svg {
            width: 14px;
            height: 14px;
        }

        .add-breakdown-btn {
            background: #ffffff;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            font-family: 'Poppins', sans-serif;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .add-breakdown-btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #1f2937;
        }

        .add-breakdown-btn svg {
            width: 16px;
            height: 16px;
            stroke-width: 2.5;
        }

        .breakdown-container {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
        }

        .breakdown-row {
            display: grid;
            grid-template-columns: 120px 1fr 36px;
            gap: 12px;
            margin-bottom: 12px;
            align-items: center;
        }

        .breakdown-row:last-of-type {
            margin-bottom: 0;
        }

        .breakdown-amount, .breakdown-description {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            outline: none;
            background: #ffffff;
        }

        .breakdown-amount:focus, .breakdown-description:focus {
            border-color: #1b5e20;
            box-shadow: 0 0 0 1px #1b5e20;
        }

        .breakdown-remove-btn {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 6px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 18px;
            font-weight: 600;
        }

        .breakdown-remove-btn:hover {
            background: #fecaca;
        }

        .breakdown-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-top: 1px solid #e5e7eb;
            margin-top: 16px;
            font-weight: 600;
        }

        .breakdown-total-row span:first-child {
            color: #374151;
            font-size: 13px;
        }

        .breakdown-total-row span:last-child {
            color: #1f2937;
            font-size: 16px;
            font-weight: 700;
        }

        .shift-time-display {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .shift-time-label {
            font-size: 11px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .shift-time-value {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }

        .shift-divider {
            color: #d1d5db;
            margin: 0 8px;
            font-weight: 300;
        }

        /* Override standard action-bar */
        .deposit-action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-filter-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .date-picker-label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-right: 4px;
        }

        .date-picker-input {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            padding: 0 12px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            color: #222;
            cursor: pointer;
            outline: none;
            height: 38px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .date-picker-input:focus {
            border-color: #1b5e20;
        }

        .delete-btn-danger {
            background-color: transparent;
            color: #dc2626;
            border: 1px solid #fee2e2;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .delete-btn-danger:hover {
            background-color: #fee2e2;
            border-color: #fecaca;
        }

        /*
        .note-tooltip {
            position: relative;
            cursor: pointer;
            text-decoration: underline dotted;
            color: #475569;
        }

        .note-tooltip:hover::after {
            content: attr(data-note);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: normal;
            width: 200px;
            z-index: 100;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        } */

        /* Navigation helpers override */
        .left-panel.minimized .logo-img {
            display: none;
        }
    </style>

    <script>
        // Global variables for active shift details
        let currentShiftData = {
            success: false,
            shift_date: '',
            shift_start: '',
            shift_end: '',
            cash_total: 0,
            transaction_count: 0
        };

        let activeUser = {
            username: 'Unknown',
            display_name: 'Unknown',
            access_level: 'staff'
        };

        // Navigation
        function navigateToPage(page) {
            const currentPage = window.location.pathname.split('/').pop().toLowerCase();
            const targetPage = page.toLowerCase();
            if (currentPage === targetPage) return;

            if (window.innerWidth <= 1024) {
                const panel = document.querySelector('.left-panel');
                if (panel) panel.classList.remove('open');
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
            leftPanel.classList.toggle('open');
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

        // Format Utilities
        function formatMoney(amount) {
            return '₱' + parseFloat(amount || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatDateTimeStr(dtStr) {
            if (!dtStr) return 'N/A';
            try {
                const date = new Date(dtStr.replace(/-/g, '/')); // browser compatibility
                return date.toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            } catch (e) {
                return dtStr;
            }
        }

        function formatDateStr(dateStr) {
            if (!dateStr) return 'N/A';
            try {
                const date = new Date(dateStr);
                return date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
            } catch (e) {
                return dateStr;
            }
        }

        function formatTimeOnly(dtStr) {
            if (!dtStr) return 'N/A';
            try {
                const date = new Date(dtStr.replace(/-/g, '/'));
                return date.toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            } catch (e) {
                return dtStr;
            }
        }

        // Fetch User Information
        async function loadUserInfo() {
            try {
                const response = await fetch('get_user_info.php');
                const result = await response.json();
                if (result.success) {
                    activeUser.username = result.username || 'Unknown';
                    activeUser.display_name = result.display_name || result.first_name || result.username || 'User';
                    activeUser.access_level = result.access_level || 'staff';

                    const avatarEl = document.getElementById('userAvatar');
                    if (avatarEl) avatarEl.textContent = activeUser.display_name.charAt(0).toUpperCase();

                    const nameEl = document.getElementById('userName');
                    if (nameEl) nameEl.textContent = activeUser.display_name;

                    const roleEl = document.getElementById('userRole');
                    if (roleEl) {
                        roleEl.textContent = activeUser.access_level.split('_').map(w =>
                            w.charAt(0).toUpperCase() + w.slice(1)
                        ).join(' ');
                    }
                }
            } catch (error) {
                console.error('Error loading user info:', error);
            }
        }

        // Fetch Running Sales
        async function fetchRunningSales() {
            try {
                const response = await fetch('get_cash_running_sales.php');
                const data = await response.json();
                if (data.success) {
                    currentShiftData = data;

                    // Update expected card
                    const expectedCashValue = document.getElementById('expectedCashValue');
                    const expectedTxCount = document.getElementById('expectedTxCount');
                    if (expectedCashValue) expectedCashValue.textContent = formatMoney(data.cash_total);
                    if (expectedTxCount) expectedTxCount.textContent = data.transaction_count;

                    // Show/hide deposit recorded badge
                    const badge = document.getElementById('depositRecordedBadge');
                    if (badge) {
                        if (data.deposit_recorded) {
                            badge.style.display = 'inline-flex';
                            // Update badge text based on whether there are new transactions
                            const badgeText = badge.querySelector('span');
                            if (badgeText) {
                                badgeText.textContent = data.cash_total > 0 ? 'Previous Deposit Recorded' : 'Deposit Recorded';
                            }
                        } else {
                            badge.style.display = 'none';
                        }
                    }

                    // Update Shift times separately
                    const shiftStart = formatTimeOnly(data.shift_start);
                    const shiftEnd = formatTimeOnly(data.shift_end);
                    const shiftStartTime = document.getElementById('shiftStartTime');
                    const shiftEndTime = document.getElementById('shiftEndTime');
                    if (shiftStartTime) shiftStartTime.textContent = shiftStart;
                    if (shiftEndTime) shiftEndTime.textContent = shiftEnd;

                    // Update subtitle with full date range
                    const shiftPeriodText = `${formatDateTimeStr(data.shift_start)} → ${formatDateTimeStr(data.shift_end)}`;
                    let subtitleText = `Current Shift: ${shiftPeriodText}`;
                    
                    // Show message if deposit already recorded
                    if (data.deposit_recorded) {
                        if (data.cash_total > 0) {
                            subtitleText += ` — ✓ Deposit #CD-${data.deposit_id} recorded (showing new transactions)`;
                        } else {
                            subtitleText += ` — ✓ Deposit #CD-${data.deposit_id} recorded`;
                        }
                    }
                    
                    const shiftPeriodSubtitle = document.getElementById('shiftPeriodSubtitle');
                    if (shiftPeriodSubtitle) shiftPeriodSubtitle.textContent = subtitleText;

                    // Set modal values defaults
                    const modalShiftDate = document.getElementById('modalShiftDate');
                    const modalShiftStart = document.getElementById('modalShiftStart');
                    const modalShiftEnd = document.getElementById('modalShiftEnd');
                    const modalExpectedSales = document.getElementById('modalExpectedSales');
                    const modalExpectedDisplay = document.getElementById('modalExpectedDisplay');
                    
                    if (modalShiftDate) modalShiftDate.value = data.shift_date;
                    if (modalShiftStart) modalShiftStart.value = data.shift_start;
                    if (modalShiftEnd) modalShiftEnd.value = data.shift_end;
                    if (modalExpectedSales) modalExpectedSales.value = data.cash_total;
                    if (modalExpectedDisplay) modalExpectedDisplay.value = formatMoney(data.cash_total);

                    // Reset modal inputs
                    const modalDeposited = document.getElementById('modalDeposited');
                    if (modalDeposited) modalDeposited.value = '';
                    updateVarianceCalculation();
                } else {
                    console.error('Failed to load running sales:', data.error);
                }
            } catch (error) {
                console.error('Error fetching running sales:', error);
            }
        }

        // Fetch Cash Deposit Records
        async function fetchDeposits() {
            const shiftDateFilter = document.getElementById('dateFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const searchVal = document.getElementById('searchInput').value.trim().toLowerCase();

            const tbody = document.getElementById('depositTableBody');
            tbody.innerHTML = '<tr><td colspan="10" class="empty-state">Loading cash deposits...</td></tr>';

            let url = 'get_cash_deposits.php';
            if (shiftDateFilter) {
                url += `?shift_date=${encodeURIComponent(shiftDateFilter)}`;
            }

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    let list = data.deposits || [];

                    // Apply Client Side Filter for Status and Search
                    if (statusFilter && statusFilter !== 'all') {
                        list = list.filter(d => d.status === statusFilter);
                    }
                    if (searchVal) {
                        list = list.filter(d =>
                            d.created_by.toLowerCase().includes(searchVal) ||
                            d.reason.toLowerCase().includes(searchVal) ||
                            d.notes.toLowerCase().includes(searchVal) ||
                            d.cash_deposited.toString().includes(searchVal)
                        );
                    }

                    renderDeposits(list);
                } else {
                    tbody.innerHTML = `<tr><td colspan="10" class="empty-state" style="color: #dc2626;">Error: ${data.error || 'Failed to retrieve deposits'}</td></tr>`;
                }
            } catch (error) {
                console.error('Error fetching deposits:', error);
                tbody.innerHTML = '<tr><td colspan="10" class="empty-state" style="color: #dc2626;">Network error occurred while fetching deposits.</td></tr>';
            }
        }

        // Render Deposits to Table
        function renderDeposits(list) {
            const tbody = document.getElementById('depositTableBody');
            if (list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="empty-state">No deposit records found.</td></tr>';
                return;
            }

            const isAdminOrSuper = activeUser.access_level === 'admin' || activeUser.access_level === 'super_admin';
            const isSuperAdmin = activeUser.access_level === 'super_admin';

            tbody.innerHTML = list.map(item => {
                const isExact = item.status === 'exact';
                const varianceClass = item.status;
                // For OVER show +, for SHORT show nothing (just the amount), for EXACT show nothing
                const sign = item.variance > 0.01 ? '+' : '';
                // Use absolute value for SHORT to remove minus sign
                const varianceAmount = item.variance < -0.01 ? Math.abs(item.variance) : item.variance;

                let actionBtns = '';
                if (isAdminOrSuper) {
                    // Add View button for SHORT or OVER deposits (available for Admin and Super Admin)
                    const viewBtn = (item.status === 'short' || item.status === 'over') 
                        ? `<button class="table-action-btn secondary" onclick="viewBreakdown(${item.id})" title="View Breakdown">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>`
                        : '';
                    
                    // Edit and Delete buttons only for Super Admin
                    const editDeleteBtns = isSuperAdmin 
                        ? `<button class="table-action-btn secondary" onclick="editDeposit(${item.id})" title="Edit">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            <button class="table-action-btn danger" onclick="deleteDeposit(${item.id})" title="Delete">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>`
                        : '';
                    
                    actionBtns = `
                        <div style="display: flex; gap: 6px; justify-content: center;">
                            ${viewBtn}
                            ${editDeleteBtns}
                        </div>
                    `;
                } else {
                    actionBtns = '<span style="color:#94a3b8; font-size:11px;">N/A</span>';
                }

                const reasonEscaped = item.reason ? item.reason.replace(/"/g, '&quot;') : '';
                const notesEscaped = item.notes ? item.notes.replace(/"/g, '&quot;') : '';

                // Format reason cell with breakdown if available
                let reasonDisplay = '';
                if (item.breakdown) {
                    try {
                        const breakdown = JSON.parse(item.breakdown);
                        if (breakdown.length > 0) {
                            // Display descriptions directly, separated by commas
                            const descriptions = breakdown.map(b => b.description).join(', ');
                            const breakdownSummary = breakdown.map(b => `₱${parseFloat(b.amount).toFixed(2)} - ${b.description}`).join('\\n');
                            reasonDisplay = `<span class="note-tooltip" data-note="${breakdownSummary}">${descriptions}</span>`;
                        }
                    } catch (e) {
                        console.error('Error parsing breakdown:', e);
                    }
                }
                
                if (!reasonDisplay && item.reason) {
                    reasonDisplay = `<span class="note-tooltip" data-note="${reasonEscaped}">${item.reason.substring(0, 20)}${item.reason.length > 20 ? '...' : ''}</span>`;
                }
                
                if (!reasonDisplay) {
                    reasonDisplay = '<span style="color:#94a3b8;">-</span>';
                }
                
                const reasonCell = reasonDisplay;
                const notesCell = item.notes ? `<span class="note-tooltip" data-note="${notesEscaped}">${item.notes.substring(0, 20)}${item.notes.length > 20 ? '...' : ''}</span>` : '<span style="color:#94a3b8;">-</span>';

                return `
                    <tr>
                        <td>#CD-${item.id}</td>
                        <td><strong>${formatDateStr(item.shift_date)}</strong></td>
                        <td style="font-size: 11px; color: #555;">
                            ${formatDateTimeStr(item.shift_start)}<br>to ${formatDateTimeStr(item.shift_end)}
                        </td>
                        <td>${formatMoney(item.cash_expected)}</td>
                        <td><strong>${formatMoney(item.cash_deposited)}</strong></td>
                        <td>
                            <span class="variance-text ${varianceClass}">
                                ${sign}${formatMoney(varianceAmount)}
                            </span>
                        </td>
                        <td><span class="status-badge ${item.status}">${item.status}</span></td>
                        <td>${reasonCell}</td>
                        <td>${notesCell}</td>
                        <td>
                            <div style="font-weight:600;">${item.created_by}</div>
                            <div style="font-size:10px; color:#888;">${formatDateTimeStr(item.created_at)}</div>
                        </td>
                        <td style="text-align: center;">${actionBtns}</td>
                    </tr>
                `;
            }).join('');
        }

        // Realtime calculation in modal
        function updateVarianceCalculation() {
            const expected = parseFloat(document.getElementById('modalExpectedSales').value) || 0;
            const depositedInput = document.getElementById('modalDeposited').value;

            if (depositedInput === '') {
                document.getElementById('varianceDisplayBox').className = 'variance-box exact';
                document.getElementById('varianceLabelText').textContent = 'Variance';
                document.getElementById('varianceAmountText').textContent = '₱0.00';
                document.getElementById('shortageBreakdownGroup').style.display = 'none';
                return;
            }

            const deposited = parseFloat(depositedInput) || 0;
            const variance = parseFloat((deposited - expected).toFixed(2));

            const box = document.getElementById('varianceDisplayBox');
            const labelText = document.getElementById('varianceLabelText');
            const amountText = document.getElementById('varianceAmountText');
            const breakdownGroup = document.getElementById('shortageBreakdownGroup');

            const sign = variance > 0 ? '+' : '';

            if (variance > 0.01) {
                box.className = 'variance-box over';
                labelText.textContent = 'Variance: OVER';
                amountText.textContent = `${sign}${formatMoney(variance)}`;
                
                // Show breakdown section for OVER variance
                breakdownGroup.style.display = 'block';
                
                // Update breakdown label for OVER
                const breakdownLabel = document.getElementById('breakdownLabel');
                if (breakdownLabel) {
                    breakdownLabel.innerHTML = 'Overage Breakdown <span class="required">*</span>';
                }
                
                // Update instruction text for OVER
                const breakdownRequiredTotal = document.getElementById('breakdownRequiredTotal');
                if (breakdownRequiredTotal) {
                    breakdownRequiredTotal.textContent = formatMoney(variance);
                }
                
                const instructionDiv = document.getElementById('breakdownInstructionText');
                if (instructionDiv) {
                    instructionDiv.innerHTML = `Add line items that explain the overage. Total must equal: <strong id="breakdownRequiredTotal">${formatMoney(variance)}</strong>`;
                }
                
                // Update placeholder text for overage
                const descInputs = document.querySelectorAll('.breakdown-description');
                descInputs.forEach(input => {
                    if (!input.value) {
                        input.placeholder = 'Description (e.g. Tips received)';
                    }
                });
                
                // Initialize with one breakdown row if empty
                const container = document.getElementById('breakdownItemsContainer');
                if (container.children.length === 0) {
                    container.innerHTML = `
                        <div class="breakdown-row">
                            <input type="number" step="0.01" min="0" placeholder="Amount" class="breakdown-amount" oninput="calculateBreakdownTotal()">
                            <input type="text" placeholder="Description (e.g. Tips received)" class="breakdown-description">
                            <button type="button" class="breakdown-remove-btn" onclick="removeBreakdownRow(this)" title="Remove">×</button>
                        </div>
                    `;
                }
            } else if (variance < -0.01) {
                box.className = 'variance-box short';
                labelText.textContent = 'Variance: SHORT';
                amountText.textContent = `${formatMoney(Math.abs(variance))}`; // Remove minus sign
                
                // Show breakdown section for SHORT variance
                breakdownGroup.style.display = 'block';
                
                // Update breakdown label for SHORT
                const breakdownLabel = document.getElementById('breakdownLabel');
                if (breakdownLabel) {
                    breakdownLabel.innerHTML = 'Shortage Breakdown <span class="required">*</span>';
                }
                
                // Update instruction text for SHORT
                const breakdownRequiredTotal = document.getElementById('breakdownRequiredTotal');
                if (breakdownRequiredTotal) {
                    breakdownRequiredTotal.textContent = formatMoney(Math.abs(variance));
                }
                
                const instructionDiv = document.getElementById('breakdownInstructionText');
                if (instructionDiv) {
                    instructionDiv.innerHTML = `Add line items that explain the shortage. Total must equal: <strong id="breakdownRequiredTotal">${formatMoney(Math.abs(variance))}</strong>`;
                }
                
                // Update placeholder text for shortage
                const descInputs = document.querySelectorAll('.breakdown-description');
                descInputs.forEach(input => {
                    if (!input.value) {
                        input.placeholder = 'Description (e.g. Electric bills)';
                    }
                });
                
                // Initialize with one breakdown row if empty
                const container = document.getElementById('breakdownItemsContainer');
                if (container.children.length === 0) {
                    container.innerHTML = `
                        <div class="breakdown-row">
                            <input type="number" step="0.01" min="0" placeholder="Amount" class="breakdown-amount" oninput="calculateBreakdownTotal()">
                            <input type="text" placeholder="Description (e.g. Electric bills)" class="breakdown-description">
                            <button type="button" class="breakdown-remove-btn" onclick="removeBreakdownRow(this)" title="Remove">×</button>
                        </div>
                    `;
                }
            } else {
                box.className = 'variance-box exact';
                labelText.textContent = 'Variance: EXACT';
                amountText.textContent = '₱0.00';
                breakdownGroup.style.display = 'none';
            }
        }

        // Breakdown management - simplified
        function addBreakdownRow() {
            const container = document.getElementById('breakdownItemsContainer');
            const rowDiv = document.createElement('div');
            rowDiv.className = 'breakdown-row';
            rowDiv.innerHTML = `
                <input type="number" step="0.01" min="0" placeholder="Amount" 
                    class="breakdown-amount" oninput="calculateBreakdownTotal()">
                <input type="text" placeholder="Description (e.g., Electric bills)" 
                    class="breakdown-description">
                <button type="button" class="breakdown-remove-btn" 
                    onclick="removeBreakdownRow(this)" title="Remove">×</button>
            `;
            container.appendChild(rowDiv);
        }

        function removeBreakdownRow(button) {
            const row = button.closest('.breakdown-row');
            if (row) {
                row.remove();
                calculateBreakdownTotal();
            }
        }

        function calculateBreakdownTotal() {
            const amounts = document.querySelectorAll('.breakdown-amount');
            let total = 0;
            amounts.forEach(input => {
                const val = parseFloat(input.value) || 0;
                total += val;
            });
            
            document.getElementById('breakdownTotal').textContent = formatMoney(total);
            
            // Validate against required total
            const expected = parseFloat(document.getElementById('modalExpectedSales').value) || 0;
            const deposited = parseFloat(document.getElementById('modalDeposited').value) || 0;
            const variance = deposited - expected;
            
            const validationMsg = document.getElementById('breakdownValidationMessage');
            if (Math.abs(variance) > 0.01) {
                // Validate for both SHORT and OVER variances
                const requiredAmount = Math.abs(variance);
                const diff = Math.abs(total - requiredAmount);
                if (diff > 0.01) {
                    validationMsg.style.display = 'block';
                    const varianceType = variance < 0 ? 'shortage' : 'overage';
                    validationMsg.textContent = `⚠ Breakdown total must equal the ${varianceType} amount`;
                } else {
                    validationMsg.style.display = 'none';
                }
            }
        }

        function getBreakdownData() {
            const items = [];
            const rows = document.querySelectorAll('.breakdown-row');
            
            rows.forEach(row => {
                const amount = parseFloat(row.querySelector('.breakdown-amount').value) || 0;
                const description = row.querySelector('.breakdown-description').value.trim();
                if (amount > 0 && description) {
                    items.push({ amount, description });
                }
            });
            
            return items;
        }

        function clearBreakdownItems() {
            const container = document.getElementById('breakdownItemsContainer');
            container.innerHTML = `
                <div class="breakdown-row">
                    <input type="number" step="0.01" min="0" placeholder="Amount" class="breakdown-amount" oninput="calculateBreakdownTotal()">
                    <input type="text" placeholder="Description (e.g. Electric bills)" class="breakdown-description">
                    <button type="button" class="breakdown-remove-btn" onclick="removeBreakdownRow(this)" title="Remove">×</button>
                </div>
            `;
            calculateBreakdownTotal();
        }

        // Open Modal
        function openDepositModal() {
            document.getElementById('depositModalOverlay').classList.add('open');
            document.getElementById('modalDepositId').value = ''; // Clear edit ID
            document.getElementById('modalTitle').textContent = 'Record Cash Deposit';
            document.getElementById('modalSubmitBtn').textContent = 'Save Deposit';
            document.getElementById('modalDeposited').focus();
        }

        // Open Modal for Editing
        async function editDeposit(id) {
            try {
                // Fetch the deposit details
                const response = await fetch(`get_cash_deposits.php?id=${id}`);
                const data = await response.json();

                if (data.success && data.deposits && data.deposits.length > 0) {
                    const deposit = data.deposits[0];

                    // Populate the modal with existing data
                    document.getElementById('modalDepositId').value = deposit.id;
                    document.getElementById('modalShiftDate').value = deposit.shift_date;
                    document.getElementById('modalShiftStart').value = deposit.shift_start;
                    document.getElementById('modalShiftEnd').value = deposit.shift_end;
                    document.getElementById('modalExpectedSales').value = deposit.cash_expected;
                    document.getElementById('modalExpectedDisplay').value = formatMoney(deposit.cash_expected);
                    document.getElementById('modalDeposited').value = deposit.cash_deposited;
                    document.getElementById('modalNotes').value = deposit.notes || '';

                    // Clear and populate breakdown items if they exist
                    clearBreakdownItems();
                    if (deposit.breakdown) {
                        try {
                            const breakdownItems = JSON.parse(deposit.breakdown);
                            const container = document.getElementById('breakdownItemsContainer');
                            container.innerHTML = ''; // Clear default row
                            
                            breakdownItems.forEach(item => {
                                const rowDiv = document.createElement('div');
                                rowDiv.className = 'breakdown-row';
                                rowDiv.innerHTML = `
                                    <input type="number" step="0.01" min="0" placeholder="Amount" 
                                        class="breakdown-amount" value="${item.amount}" oninput="calculateBreakdownTotal()">
                                    <input type="text" placeholder="Description (e.g., Electric bills)" 
                                        class="breakdown-description" value="${item.description}">
                                    <button type="button" class="breakdown-remove-btn" 
                                        onclick="removeBreakdownRow(this)" title="Remove">×</button>
                                `;
                                container.appendChild(rowDiv);
                            });
                            calculateBreakdownTotal();
                        } catch (e) {
                            console.error('Error parsing breakdown data:', e);
                        }
                    }

                    // Update modal title and button text
                    document.getElementById('modalTitle').textContent = `Edit Cash Deposit #CD-${deposit.id}`;
                    document.getElementById('modalSubmitBtn').textContent = 'Update Deposit';

                    // Trigger variance calculation
                    updateVarianceCalculation();

                    // Open the modal
                    document.getElementById('depositModalOverlay').classList.add('open');
                } else {
                    alert('Failed to load deposit details: ' + (data.error || 'Record not found'));
                }
            } catch (error) {
                console.error('Error loading deposit for edit:', error);
                alert('Failed to load deposit details due to a network error.');
            }
        }

        // Close Modal
        function closeDepositModal() {
            document.getElementById('depositModalOverlay').classList.remove('open');
            document.getElementById('depositForm').reset();
            clearBreakdownItems();
            // Re-fetch running sales to make sure we have latest
            fetchRunningSales();
        }

        // View Breakdown Modal
        async function viewBreakdown(id) {
            try {
                const response = await fetch(`get_cash_deposits.php?id=${id}`);
                const data = await response.json();

                if (data.success && data.deposits && data.deposits.length > 0) {
                    const deposit = data.deposits[0];
                    
                    // Populate the view modal
                    document.getElementById('viewDepositId').textContent = `#CD-${deposit.id}`;
                    document.getElementById('viewShiftDate').textContent = formatDateStr(deposit.shift_date);
                    document.getElementById('viewShiftTime').textContent = `${formatTimeOnly(deposit.shift_start)} - ${formatTimeOnly(deposit.shift_end)}`;
                    document.getElementById('viewCashExpected').textContent = formatMoney(deposit.cash_expected);
                    document.getElementById('viewCashDeposited').textContent = formatMoney(deposit.cash_deposited);
                    
                    const variance = parseFloat(deposit.variance);
                    const varianceType = deposit.status === 'short' ? 'SHORT' : 'OVER';
                    const varianceClass = deposit.status === 'short' ? 'short' : 'over';
                    const varianceAmount = deposit.status === 'short' ? Math.abs(variance) : variance;
                    const sign = deposit.status === 'over' ? '+' : '';
                    
                    document.getElementById('viewVarianceType').textContent = varianceType;
                    document.getElementById('viewVarianceType').className = `variance-badge ${varianceClass}`;
                    document.getElementById('viewVarianceAmount').textContent = `${sign}${formatMoney(varianceAmount)}`;
                    
                    // Display breakdown items
                    const breakdownTableBody = document.getElementById('viewBreakdownTableBody');
                    breakdownTableBody.innerHTML = '';
                    
                    if (deposit.breakdown) {
                        try {
                            const breakdownItems = JSON.parse(deposit.breakdown);
                            let total = 0;
                            
                            breakdownItems.forEach(item => {
                                total += parseFloat(item.amount);
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e5e7eb; font-size: 14px; color: #1f2937;">${item.description}</td>
                                    <td style="padding: 12px 14px; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600; font-size: 15px; color: #1f2937;">${formatMoney(item.amount)}</td>
                                `;
                                breakdownTableBody.appendChild(row);
                            });
                            
                            // Add total row
                            const totalRow = document.createElement('tr');
                            totalRow.innerHTML = `
                                <td style="padding: 12px 14px; font-weight: 600; border-top: 2px solid #e5e7eb; font-size: 14px; color: #1f2937;">Total</td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 700; border-top: 2px solid #e5e7eb; font-size: 16px; color: #1f2937;">${formatMoney(total)}</td>
                            `;
                            breakdownTableBody.appendChild(totalRow);
                        } catch (e) {
                            console.error('Error parsing breakdown data:', e);
                            breakdownTableBody.innerHTML = '<tr><td colspan="2" style="padding: 10px; color: #ef4444;">Error loading breakdown data</td></tr>';
                        }
                    } else {
                        breakdownTableBody.innerHTML = '<tr><td colspan="2" style="padding: 10px; color: #94a3b8;">No breakdown data available</td></tr>';
                    }
                    
                    // Open the modal
                    document.getElementById('viewBreakdownModalOverlay').classList.add('open');
                } else {
                    alert('Failed to load deposit details: ' + (data.error || 'Record not found'));
                }
            } catch (err) {
                console.error('Error loading deposit for view:', err);
                alert('Failed to load deposit details due to a network error.');
            }
        }

        // Close View Breakdown Modal
        function closeViewBreakdownModal() {
            document.getElementById('viewBreakdownModalOverlay').classList.remove('open');
        }

        // Save Deposit Submission (Create or Update)
        async function saveDeposit(e) {
            e.preventDefault();

            const depositId = document.getElementById('modalDepositId').value;
            const shiftDate = document.getElementById('modalShiftDate').value;
            const shiftStart = document.getElementById('modalShiftStart').value;
            const shiftEnd = document.getElementById('modalShiftEnd').value;
            const cashExpected = parseFloat(document.getElementById('modalExpectedSales').value) || 0;
            const cashDeposited = parseFloat(document.getElementById('modalDeposited').value);
            const notes = document.getElementById('modalNotes').value.trim();

            if (isNaN(cashDeposited) || cashDeposited < 0) {
                alert('Please enter a valid deposit amount (must be 0 or greater).');
                return;
            }

            // Prevent saving when both expected and deposited are 0
            if (cashExpected === 0 && cashDeposited === 0) {
                alert('Cannot save deposit. Running cash is ₱0.00. Please wait for cash sales to be recorded.');
                return;
            }

            // Prevent saving when deposited is 0 but expected is not 0 (unless editing existing deposit)
            if (cashDeposited === 0 && cashExpected > 0 && !depositId) {
                alert('Physical Cash Deposited cannot be ₱0.00 when there are cash sales to deposit.');
                return;
            }

            const variance = parseFloat((cashDeposited - cashExpected).toFixed(2));

            // Validate breakdown for SHORT or OVER variance
            let breakdownData = null;
            if (Math.abs(variance) > 0.01) {
                const breakdown = getBreakdownData();
                const breakdownTotal = breakdown.reduce((sum, item) => sum + item.amount, 0);
                const varianceAmount = Math.abs(variance);
                
                if (Math.abs(breakdownTotal - varianceAmount) > 0.01) {
                    const varianceType = variance < 0 ? 'shortage' : 'overage';
                    alert(`Breakdown total (₱${breakdownTotal.toFixed(2)}) must equal the ${varianceType} amount (₱${varianceAmount.toFixed(2)})`);
                    return;
                }
                
                if (breakdown.length === 0) {
                    const varianceType = variance < 0 ? 'shortage' : 'overage';
                    alert(`Please add at least one breakdown item for the ${varianceType}.`);
                    return;
                }
                
                breakdownData = breakdown;
            }

            const submitBtn = document.getElementById('modalSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = depositId ? 'Updating...' : 'Saving...';

            const payload = {
                shift_date: shiftDate,
                shift_start: shiftStart,
                shift_end: shiftEnd,
                cash_expected: cashExpected,
                cash_deposited: cashDeposited,
                reason: '', // Empty reason since we removed the field
                notes: notes,
                created_by: activeUser.display_name,
                breakdown: breakdownData
            };

            // Add ID if editing
            if (depositId) {
                payload.id = parseInt(depositId);
            }

            try {
                const method = depositId ? 'PUT' : 'POST';
                const response = await fetch('save_cash_deposit.php', {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                if (result.success) {
                    alert(depositId ? 'Cash deposit updated successfully.' : 'Cash deposit saved successfully.');
                    closeDepositModal();
                    // Refresh both running sales and deposits list
                    await fetchRunningSales();
                    fetchDeposits();
                } else {
                    alert('Error: ' + (result.error || 'Failed to save record.'));
                }
            } catch (error) {
                console.error('Error saving deposit:', error);
                alert('Failed to save deposit due to a network connection error.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = depositId ? 'Update Deposit' : 'Save Deposit';
            }
        }

        // Delete Deposit Record
        async function deleteDeposit(id) {
            if (!confirm(`Are you sure you want to permanently delete Cash Deposit record #CD-${id}?`)) {
                return;
            }

            try {
                const response = await fetch('save_cash_deposit.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const result = await response.json();

                if (result.success) {
                    alert('Cash deposit record deleted.');
                    // Refresh both the deposits list and running sales
                    await fetchRunningSales();
                    fetchDeposits();
                } else {
                    alert('Error: ' + (result.error || 'Failed to delete record.'));
                }
            } catch (error) {
                console.error('Error deleting deposit:', error);
                alert('Connection error occurred while deleting.');
            }
        }

        // Trigger filters clear
        function resetFilters() {
            document.getElementById('dateFilter').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('searchInput').value = '';
            fetchDeposits();
        }

        // Initialization
        document.addEventListener('DOMContentLoaded', async () => {
            // Load user data first
            await loadUserInfo();

            // Load live calculations
            await fetchRunningSales();

            // Load database list
            fetchDeposits();

            // Set up form submission listener
            document.getElementById('depositForm').addEventListener('submit', saveDeposit);

            // Setup realtime validation triggers
            document.getElementById('modalDeposited').addEventListener('input', updateVarianceCalculation);

            // Setup filters listeners
            document.getElementById('dateFilter').addEventListener('change', fetchDeposits);
            document.getElementById('statusFilter').addEventListener('change', fetchDeposits);

            // Setup search keyup trigger with 250ms debounce
            let searchTimeout = null;
            document.getElementById('searchInput').addEventListener('keyup', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(fetchDeposits, 250);
            });
        });
    </script>
</head>

<body class="deposit-tracking-page">
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

    <div class="split-container">
        <!-- ── Sidebar ─────────────────────────────────────────────────────── -->
        <div class="left-panel" id="leftPanel">
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
                        <img src="Icon/reservationicon_system.svg" class="sidebar-icon" alt="Reservation List">
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

        <!-- ── Main content ────────────────────────────────────────────────── -->
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
                <div class="header-title-row">
                    <div>
                        <h2 class="header-title">Running Cash Deposit</h2>
                    <!--    <div class="running-time-subtitle" id="shiftPeriodSubtitle">Loading shift window...</div> -->
                    </div>
                    <div>
                        <button class="add-deposit-btn" onclick="openDepositModal()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Record Deposit
                        </button>
                    </div>
                </div>

                <!-- Premium Metrics Cards -->
                <div class="summary-cards-grid">
                    <!-- Live Running Expected Sales -->
                    <div class="premium-card cash-card">
                        <div class="card-label">
                            <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="5" width="20" height="14" rx="2"/>
                                <line x1="2" y1="10" x2="22" y2="10"/>
                            </svg>
                            Running Cash
                        </div>
                        <div class="card-value" id="expectedCashValue">₱0.00</div>
                        <div class="card-meta">
                            <span id="expectedTxCount">0</span> transactions in current shift
                        </div>
                        <div id="depositRecordedBadge" style="display: none;" class="deposit-recorded-badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Deposit Recorded</span>
                        </div>
                    </div>

                    <!-- Active Shift Information -->
                    <div class="premium-card shift-card">
                        <div class="card-label">
                            <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            Active Shift
                        </div>
                        <div class="shift-time-display">
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                                <div>
                                    <div class="shift-time-label">Start</div>
                                    <div class="shift-time-value" id="shiftStartTime">8:00 AM</div>
                                </div>
                                <span class="shift-divider">→</span>
                                <div>
                                    <div class="shift-time-label">End</div>
                                    <div class="shift-time-value" id="shiftEndTime">8:00 AM</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search & Filters -->
                <div class="deposit-action-bar">
                    <div class="search-filter-group">
                        <div>
                            <span class="date-picker-label">Shift Date:</span>
                            <input type="date" id="dateFilter" class="date-picker-input" title="Filter by shift date">
                        </div>

                        <div>
                            <select id="statusFilter" class="status-filter-select">
                                <option value="all">All Discrepancies</option>
                                <option value="exact">Exact Deposits Only</option>
                                <option value="short">Short Deposits Only</option>
                                <option value="over">Over Deposits Only</option>
                            </select>
                        </div>

                        <div>
                            <input type="text" id="searchInput" class="search-input-simple"
                                placeholder="Search records...">
                        </div>

                        <!-- <button class="table-action-btn secondary" style="width: auto; height: 38px; padding: 0 16px;"
                            onclick="resetFilters()">
                            Clear Filters
                        </button> -->
                    </div>

                    <!-- <button class="table-action-btn secondary" style="width: auto; height: 38px; padding: 0 16px;" onclick="fetchRunningSales(); fetchDeposits();">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; display: inline-block; vertical-align: middle;">
                            <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
                        </svg>
                        Sync Live
                    </button> -->
                </div>

                <!-- Deposits Log Table -->
                <div class="table-wrapper">
                    <table class="deposit-table">
                        <thead>
                            <tr>
                                <th>Deposit ID</th>
                                <th>Shift Date</th>
                                <th>Shift Window</th>
                                <th>Expected Cash</th>
                                <th>Deposited Cash</th>
                                <th>Variance</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Notes</th>
                                <th>Encoder</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="depositTableBody">
                            <tr>
                                <td colspan="11" class="empty-state">Loading cash deposits...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div><!-- /.content-container -->
        </div><!-- /.right-panel -->
    </div><!-- /.split-container -->

    <!-- Add Cash Deposit Modal -->
    <div class="modal-overlay" id="depositModalOverlay">
        <div class="modal-container">
            <h3 class="modal-title" id="modalTitle">Record Cash Deposit</h3>

            <form id="depositForm">
                <!-- Hidden deposit ID for editing -->
                <input type="hidden" id="modalDepositId">
                <!-- Hidden shift attributes -->
                <input type="hidden" id="modalShiftDate">
                <input type="hidden" id="modalShiftStart">
                <input type="hidden" id="modalShiftEnd">
                <input type="hidden" id="modalExpectedSales">

                <div class="modal-content">

                    <!-- Two-column layout for Cash Sales and Physical Cash Deposited -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Cash Sales</label>
                            <input type="text" id="modalExpectedDisplay" class="form-input readonly-display" readonly>
                          <!--  <div class="form-hint">Calculated automatically based on shift cash payments from history.</div> -->
                        </div>

                        <div class="form-group">
                            <label class="form-label">Physical Cash Deposited <span class="required">*</span></label>
                            <input type="number" step="0.01" min="0" id="modalDeposited" class="form-input"
                                placeholder="0.00" required>
                        <!--    <div class="form-hint">Amount physically placed in the bank drop or turnover box.</div> -->
                        </div>
                    </div>

                    <!-- Variance Card Display -->
                    <div class="variance-box exact" id="varianceDisplayBox">
                        <div class="variance-label" id="varianceLabelText">Variance</div>
                        <div class="variance-amount" id="varianceAmountText">₱0.00</div>
                    </div>

                    <!-- Shortage Breakdown Section (only for SHORT variance) -->
                    <div class="form-group" id="shortageBreakdownGroup" style="display: none;">
                        <label class="form-label" id="breakdownLabel">Shortage Breakdown <span class="required">*</span></label>
                        <div id="breakdownInstructionText" style="font-size: 12px; color: #6b7280; margin-bottom: 12px;">
                            Add line items that explain the shortage. Total must equal: <strong id="breakdownRequiredTotal">₱0.00</strong>
                        </div>
                        
                        <div class="breakdown-container">
                            <div id="breakdownItemsContainer">
                                <!-- Initial breakdown item -->
                                <div class="breakdown-row">
                                    <input type="number" step="0.01" min="0" placeholder="Amount" class="breakdown-amount" oninput="calculateBreakdownTotal()">
                                    <input type="text" placeholder="Description (e.g. Electric bills)" class="breakdown-description">
                                    <button type="button" class="breakdown-remove-btn" onclick="removeBreakdownRow(this)" title="Remove">×</button>
                                </div>
                            </div>
                            
                            <button type="button" class="add-breakdown-btn" onclick="addBreakdownRow()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <line x1="12" y1="5" x2="12" y2="19"/>
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                Add Item
                            </button>
                            
                            <div class="breakdown-total-row">
                                <span>Total Breakdown:</span>
                                <span id="breakdownTotal">₱0.00</span>
                            </div>
                        </div>
                        
                        <div id="breakdownValidationMessage" style="display: none; margin-top: 8px; padding: 8px 12px; background: #fee2e2; border: 1px solid #fecaca; border-radius: 6px; font-size: 12px; color: #dc2626;">
                            ⚠ Breakdown total must equal the shortage amount
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Optional Notes</label>
                        <textarea id="modalNotes" class="form-textarea"
                            placeholder="Any additional information."></textarea>
                    </div>

                </div>

                <div class="modal-buttons">
                    <button type="button" class="modal-btn-cancel" onclick="closeDepositModal()">Cancel</button>
                    <button type="submit" id="modalSubmitBtn" class="modal-btn-save">Save Deposit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Breakdown Modal -->
    <div class="modal-overlay" id="viewBreakdownModalOverlay">
        <div class="modal-container" style="max-width: 600px;">
            <h3 class="modal-title">Breakdown Details - <span id="viewDepositId">#CD-1</span></h3>
            
            <div class="modal-body">
                <!-- Deposit Info -->
                <div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <div style="font-size: 11px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; font-weight: 600;">Shift Date</div>
                            <div style="font-weight: 500; font-size: 15px; color: #1f2937;" id="viewShiftDate">-</div>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; font-weight: 600;">Shift Time</div>
                            <div style="font-weight: 500; font-size: 15px; color: #1f2937;" id="viewShiftTime">-</div>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <div style="font-size: 11px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; font-weight: 600;">Cash Sales</div>
                            <div style="font-weight: 600; font-size: 16px; color: #1f2937;" id="viewCashExpected">₱0.00</div>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; font-weight: 600;">Cash Deposited</div>
                            <div style="font-weight: 600; font-size: 16px; color: #1f2937;" id="viewCashDeposited">₱0.00</div>
                        </div>
                    </div>
                </div>

                <!-- Variance Display -->
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #f9fafb; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 14px; color: #6b7280; font-weight: 500;">Variance:</span>
                        <span class="variance-badge short" id="viewVarianceType">SHORT</span>
                    </div>
                    <div style="font-size: 20px; font-weight: 700; color: #1f2937;" id="viewVarianceAmount">₱0.00</div>
                </div>

                <!-- Breakdown Table -->
                <div>
                    <h4 style="font-size: 13px; font-weight: 600; margin-bottom: 12px; color: #1f2937;">Breakdown Items</h4>
                    <div style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f9fafb;">
                                    <th style="padding: 12px 14px; text-align: left; font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Description</th>
                                    <th style="padding: 12px 14px; text-align: right; font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="viewBreakdownTableBody">
                                <!-- Breakdown items will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-buttons">
                <button type="button" class="modal-btn-cancel" onclick="closeViewBreakdownModal()">Close</button>
            </div>
        </div>
    </div>
</body>

</html>