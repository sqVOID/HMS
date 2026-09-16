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

    <!-- Global Layout Styles -->
    <link rel="stylesheet" href="includes/global_layout.css">
    <!-- Base Layout Style -->
    <link rel="stylesheet" href="Inventory.css">
    <!-- Sub-page specific base style -->
    <link rel="stylesheet" href="CashDeposit.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="role-based-menu.js?v=18" defer></script>
    <script src="auto_logout.js" defer></script>
    <script src="cancellation-notification.js?v=17" defer></script>
    <!-- Global Sidebar Scripts -->
    <script src="includes/sidebar_scripts.js" defer></script>

    <style>
        /* Custom Premium Style overrides for Cash Deposit */
        .summary-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }

        .premium-card {
            background: #ffffff;
            color: #222;
            border-radius: 6px;
            padding: 10px 14px;
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

        .premium-card.pending-card {
            border-left: 4px solid #8b8b8bff;

        }

        .card-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #6b7280;
            margin-bottom: 4px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .card-icon {
            width: 12px;
            height: 12px;
            opacity: 0.7;
        }

        .card-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 2px;
            font-variant-numeric: tabular-nums;
            color: #111827;
            line-height: 1.1;
        }

        .card-meta {
            font-size: 10px;
            color: #6b7280;
            line-height: 1.3;
            margin-top: 3px;
        }

        .card-meta strong {
            color: #374151;
            font-weight: 600;
        }

        .deposit-recorded-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: #dcfce7;
            color: #16a34a;
            font-size: 9px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 8px;
            margin-top: 4px;
            letter-spacing: 0.2px;
        }

        .deposit-recorded-badge svg {
            width: 10px;
            height: 10px;
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

        .breakdown-amount,
        .breakdown-description {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            outline: none;
            background: #ffffff;
        }

        .breakdown-amount:focus,
        .breakdown-description:focus {
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
            gap: 2px;
        }

        .shift-time-label {
            font-size: 9px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 500;
        }

        .shift-time-value {
            font-size: 11px;
            font-weight: 600;
            color: #1f2937;
        }

        .shift-divider {
            color: #d1d5db;
            margin: 0 4px;
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
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            margin-right: 4px;
        }

        .date-picker-input {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            padding: 0 8px;
            font-size: 11px;
            font-family: 'Poppins', sans-serif;
            color: #222;
            cursor: pointer;
            outline: none;
            height: 32px;
            width: 130px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .date-picker-input:focus {
            border-color: #1b5e20;
        }

        .status-filter-select {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            padding: 0 8px;
            font-size: 11px;
            font-family: 'Poppins', sans-serif;
            color: #222;
            cursor: pointer;
            outline: none;
            height: 32px;
            width: 150px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .status-filter-select:focus {
            border-color: #1b5e20;
        }

        .search-input-simple {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            padding: 0 10px;
            font-size: 11px;
            font-family: 'Poppins', sans-serif;
            color: #222;
            outline: none;
            height: 32px;
            width: 180px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .search-input-simple:focus {
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

        /* Secondary button style for Report button */
        .secondary-btn {
            background: #ffffff !important;
            border: 1.5px solid #cbd5e1 !important;
            color: #475569 !important;
        }

        .secondary-btn:hover {
            background: #f8fafc !important;
            border-color: #94a3af !important;
            color: #1e293b !important;
        }

        /* Report Modal specific styles */
        .report-date-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .report-date-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            display: block;
        }

        /* View Breakdown Modal Styles matching viewBookingModal at Booking.html */
        #viewBreakdownModalOverlay.modal-overlay {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }

        #viewBreakdownModalOverlay.modal-overlay.open {
            display: flex;
        }

        #viewBreakdownModalOverlay .modal-content {
            background-color: #ffffff;
            margin: 1% auto;
            padding: 0;
            border-radius: 0px;
            width: 65%;
            max-width: 850px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        #viewBreakdownModalOverlay .modal-header {
            padding: 12px 20px;
            background: #1f2937;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 0px;
        }

        #viewBreakdownModalOverlay .modal-header h2 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
        }

        #viewBreakdownModalOverlay .modal-header .close {
            color: #ffffff;
            font-size: 28px;
            font-weight: 300;
            cursor: pointer;
            transition: color 0.2s;
            line-height: 1;
        }

        #viewBreakdownModalOverlay .modal-header .close:hover {
            color: #d1d5db;
        }

        #viewBreakdownModalOverlay .modal-body {
            padding: 10px;
            max-height: 78vh;
            overflow-y: auto;
            background: #f9fafb;
            display: flex;
            flex-direction: column;
        }

        #viewBreakdownModalOverlay .booking-details-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e5e7eb;
            font-family: 'Poppins', sans-serif;
        }

        #viewBreakdownModalOverlay .sections-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        #viewBreakdownModalOverlay .info-section {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
        }

        #viewBreakdownModalOverlay .info-section.full-width {
            grid-column: 1 / -1;
            margin-bottom: 16px;
        }

        #viewBreakdownModalOverlay .section-title {
            font-size: 0.7rem;
            font-weight: 700;
            color: #10b981;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Poppins', sans-serif;
        }

        #viewBreakdownModalOverlay .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        #viewBreakdownModalOverlay .info-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        #viewBreakdownModalOverlay .info-item.full-width {
            grid-column: 1 / -1;
        }

        #viewBreakdownModalOverlay .info-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: #6b7280;
            font-family: 'Poppins', sans-serif;
        }

        #viewBreakdownModalOverlay .info-value {
            font-size: 0.75rem;
            color: #111827;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            line-height: 1.3;
        }

        #viewBreakdownModalOverlay .modal-footer {
            padding: 10px 20px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            border-radius: 0px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        #viewBreakdownModalOverlay .modal-btn {
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
            border: none;
        }

        #viewBreakdownModalOverlay .modal-btn-secondary {
            background: #374151;
            color: #fff;
            border: 1px solid #374151;
        }

        #viewBreakdownModalOverlay .modal-btn-secondary:hover {
            background: #1f2937;
            border-color: #1f2937;
        }

        @media (max-width: 768px) {
            #viewBreakdownModalOverlay .modal-content {
                width: 95%;
                margin: 2% auto;
            }

            #viewBreakdownModalOverlay .info-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            #viewBreakdownModalOverlay .sections-grid {
                grid-template-columns: 1fr;
            }

            #viewBreakdownModalOverlay .modal-header,
            #viewBreakdownModalOverlay .modal-body,
            #viewBreakdownModalOverlay .modal-footer {
                padding-left: 15px;
                padding-right: 15px;
            }
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 24px;
            padding: 16px 0;
        }

        .pagination-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            color: #475569;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #1e293b;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .pagination-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: #94a3b8;
        }

        .pagination-btn svg {
            width: 16px;
            height: 16px;
        }

        .pagination-info {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            font-family: 'Poppins', sans-serif;
            min-width: 200px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .pagination-container {
                gap: 12px;
            }

            .pagination-btn {
                padding: 8px 14px;
                font-size: 13px;
            }

            .pagination-info {
                font-size: 12px;
                min-width: 150px;
            }
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

        // Pagination variables
        let currentPage = 1;
        const itemsPerPage = 15;
        let allDeposits = [];

        // Format Utilities
        function formatMoney(amount) {
            return '₱' + parseFloat(amount || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatDateTimeLocal(dtStr) {
            if (!dtStr) return '';
            let formatted = dtStr.replace(' ', 'T');
            if (formatted.length > 16) {
                formatted = formatted.substring(0, 16);
            }
            return formatted;
        }

        function formatDateOnly(dtStr) {
            if (!dtStr) return '';
            try {
                // Extract just the date part (YYYY-MM-DD)
                return dtStr.split(' ')[0];
            } catch (e) {
                return '';
            }
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

                    // Show/Hide deposit date filters based on access level (Super Admin only)
                    const depositDateFromContainer = document.getElementById('depositDateFrom')?.parentElement;
                    const depositDateToContainer = document.getElementById('depositDateTo')?.parentElement;
                    const totalDepositCard = document.getElementById('totalDepositCard');
                    
                    if (activeUser.access_level === 'super_admin') {
                        if (depositDateFromContainer) depositDateFromContainer.style.display = '';
                        if (depositDateToContainer) depositDateToContainer.style.display = '';
                        if (totalDepositCard) totalDepositCard.style.display = '';
                    } else {
                        if (depositDateFromContainer) depositDateFromContainer.style.display = 'none';
                        if (depositDateToContainer) depositDateToContainer.style.display = 'none';
                        if (totalDepositCard) totalDepositCard.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error('Error loading user info:', error);
            }
        }

        // Fetch Running Sales
        async function fetchRunningSales(customStart, customEnd) {
            try {
                let url = 'get_cash_running_sales.php';
                if (customStart && customEnd) {
                    url += `?shift_start=${encodeURIComponent(customStart)}&shift_end=${encodeURIComponent(customEnd)}`;
                }
                const response = await fetch(url);
                const data = await response.json();
                if (data.success) {
                    currentShiftData = data;

                    // Update shiftCardDateFilter input value if not manually selected
                    const shiftCardDateFilter = document.getElementById('shiftCardDateFilter');
                    if (shiftCardDateFilter && !customStart) {
                        shiftCardDateFilter.value = data.shift_date;
                    }

                    // Update expected card
                    const expectedCashValue = document.getElementById('expectedCashValue');
                    const expectedTxCount = document.getElementById('expectedTxCount');
                    if (expectedCashValue) expectedCashValue.textContent = formatMoney(data.running_cash);
                    if (expectedTxCount) expectedTxCount.textContent = data.running_tx_count;

                    // Update pending deposit card
                    const pendingDepositValue = document.getElementById('pendingDepositValue');
                    if (pendingDepositValue) pendingDepositValue.textContent = formatMoney(data.pending_deposit);
                    const pendingDepositMeta = document.getElementById('pendingDepositMeta');
                    if (pendingDepositMeta) {
                        const fromLabel = data.pending_from_label
                            ? new Date(data.pending_from_label + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                            : 'Jan 1';
                        pendingDepositMeta.textContent = `Undeposited since ${fromLabel}`;
                    }

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

                    if (modalShiftDate) modalShiftDate.value = data.shift_date;
                    if (modalShiftStart) modalShiftStart.value = formatDateOnly(data.shift_start);
                    if (modalShiftEnd) modalShiftEnd.value = formatDateOnly(data.shift_end);
                    if (modalExpectedSales) modalExpectedSales.value = data.cash_total;

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
            const startDateFilter = document.getElementById('startDateFilter').value;
            const endDateFilter = document.getElementById('endDateFilter').value;
            const depositDateFrom = document.getElementById('depositDateFrom').value;
            const depositDateTo = document.getElementById('depositDateTo').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const searchVal = document.getElementById('searchInput').value.trim().toLowerCase();

            const tbody = document.getElementById('depositTableBody');
            tbody.innerHTML = '<tr><td colspan="12" class="empty-state">Loading cash deposits...</td></tr>';

            let url = 'get_cash_deposits.php';
            const params = [];
            if (startDateFilter) {
                params.push(`start_date=${encodeURIComponent(startDateFilter)}`);
            }
            if (endDateFilter) {
                params.push(`end_date=${encodeURIComponent(endDateFilter)}`);
            }
            if (depositDateFrom) {
                params.push(`deposit_date_from=${encodeURIComponent(depositDateFrom)}`);
            }
            if (depositDateTo) {
                params.push(`deposit_date_to=${encodeURIComponent(depositDateTo)}`);
            }
            if (params.length > 0) {
                url += '?' + params.join('&');
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
                    updatePaginationControls();
                } else {
                    tbody.innerHTML = `<tr><td colspan="12" class="empty-state" style="color: #dc2626;">Error: ${data.error || 'Failed to retrieve deposits'}</td></tr>`;
                }
            } catch (error) {
                console.error('Error fetching deposits:', error);
                tbody.innerHTML = '<tr><td colspan="12" class="empty-state" style="color: #dc2626;">Network error occurred while fetching deposits.</td></tr>';
            }
        }

        // Render Deposits to Table
        function renderDeposits(list) {
            // Store all deposits for pagination
            allDeposits = list;
            currentPage = 1; // Reset to first page when new data is loaded
            
            // Calculate and update total deposits
            updateTotalDepositCard(list);
            
            renderCurrentPage();
        }

        // Update Total Deposit Card
        function updateTotalDepositCard(deposits) {
            const totalDepositValue = document.getElementById('totalDepositValue');
            const totalDepositCount = document.getElementById('totalDepositCount');
            
            // Calculate total deposited amount
            const totalAmount = deposits.reduce((sum, deposit) => sum + parseFloat(deposit.cash_deposited || 0), 0);
            const depositCount = deposits.length;
            
            if (totalDepositValue) {
                totalDepositValue.textContent = formatMoney(totalAmount);
            }
            if (totalDepositCount) {
                totalDepositCount.textContent = depositCount;
            }
        }

        // Render current page of deposits
        function renderCurrentPage() {
            const tbody = document.getElementById('depositTableBody');
            
            if (allDeposits.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" class="empty-state">No deposit records found.</td></tr>';
                updatePaginationControls();
                return;
            }

            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pageItems = allDeposits.slice(startIndex, endIndex);

            const isAdminOrSuper = activeUser.access_level === 'admin' || activeUser.access_level === 'super_admin';
            const isSuperAdmin = activeUser.access_level === 'super_admin';

            tbody.innerHTML = pageItems.map(item => {
                const isExact = item.status === 'exact';
                const varianceClass = item.status;
                // For OVER show +, for SHORT show nothing (just the amount), for EXACT show nothing
                const sign = item.variance > 0.01 ? '' : '';
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
                        <td><strong>${formatDateStr(item.deposit_date || item.shift_date)}</strong></td>
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
            
            updatePaginationControls();
        }

        // Update pagination controls
        function updatePaginationControls() {
            const totalPages = Math.ceil(allDeposits.length / itemsPerPage);
            const paginationInfo = document.getElementById('paginationInfo');
            const prevBtn = document.getElementById('prevPageBtn');
            const nextBtn = document.getElementById('nextPageBtn');
            
            if (paginationInfo) {
                if (allDeposits.length === 0) {
                    paginationInfo.innerHTML = 'No records';
                } else {
                    paginationInfo.innerHTML = `Page ${currentPage} of ${totalPages}<br><span style="font-size: 12px; color: #64748b;">(${allDeposits.length} Records)</span>`;
                }
            }
            
            if (prevBtn) {
                prevBtn.disabled = currentPage === 1;
            }
            
            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages || allDeposits.length === 0;
            }
        }

        // Navigate to previous page
        function previousPage() {
            if (currentPage > 1) {
                currentPage--;
                renderCurrentPage();
            }
        }

        // Navigate to next page
        function nextPage() {
            const totalPages = Math.ceil(allDeposits.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                renderCurrentPage();
            }
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
                const exactReasonGroup = document.getElementById('exactReasonGroup');
                const modalReason = document.getElementById('modalReason');
                if (exactReasonGroup) exactReasonGroup.style.display = 'none';
                if (modalReason) modalReason.required = false;
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

            // Reason field for EXACT status
            const exactReasonGroup = document.getElementById('exactReasonGroup');
            const modalReason = document.getElementById('modalReason');
            if (Math.abs(variance) <= 0.01 && depositedInput !== '') {
                if (exactReasonGroup) exactReasonGroup.style.display = 'block';
                if (modalReason) modalReason.required = true;
            } else {
                if (exactReasonGroup) exactReasonGroup.style.display = 'none';
                if (modalReason) {
                    modalReason.required = false;
                }
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

            // Pre-populate with current shift values as default (which are always at 8:00 AM)
            document.getElementById('modalDepositDate').value = currentShiftData.shift_date || '';
            document.getElementById('modalShiftStart').value = currentShiftData.shift_start ? formatDateOnly(currentShiftData.shift_start) : '';
            document.getElementById('modalShiftEnd').value = currentShiftData.shift_end ? formatDateOnly(currentShiftData.shift_end) : '';
            document.getElementById('modalExpectedSales').value = currentShiftData.cash_total || '';
            document.getElementById('modalDeposited').value = '';
            document.getElementById('modalReason').value = '';
            updateVarianceCalculation();

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
                    document.getElementById('modalShiftStart').value = formatDateOnly(deposit.shift_start);
                    document.getElementById('modalShiftEnd').value = formatDateOnly(deposit.shift_end);
                    document.getElementById('modalExpectedSales').value = deposit.cash_expected;
                    document.getElementById('modalDeposited').value = deposit.cash_deposited;
                    document.getElementById('modalNotes').value = deposit.notes || '';
                    document.getElementById('modalDepositDate').value = deposit.deposit_date || deposit.shift_date || '';
                    document.getElementById('modalReason').value = deposit.reason || '';

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
            const filterInput = document.getElementById('shiftCardDateFilter');
            if (filterInput && filterInput.value) {
                handleShiftCardDateChange();
            } else {
                fetchRunningSales();
            }
        }

        // View Breakdown Modal
        async function viewBreakdown(id) {
            try {
                const response = await fetch(`get_cash_deposits.php?id=${id}`);
                const data = await response.json();

                if (data.success && data.deposits && data.deposits.length > 0) {
                    const deposit = data.deposits[0];

                    // Populate the view modal
                    if (document.getElementById('viewDepositId')) {
                        document.getElementById('viewDepositId').textContent = `#CD-${deposit.id}`;
                    }
                    if (document.getElementById('viewDepositIdTitle')) {
                        document.getElementById('viewDepositIdTitle').textContent = deposit.id;
                    }
                    if (document.getElementById('viewDepositDate')) {
                        document.getElementById('viewDepositDate').textContent = formatDateStr(deposit.deposit_date || deposit.shift_date);
                    }
                    if (document.getElementById('viewShiftDate')) {
                        document.getElementById('viewShiftDate').textContent = formatDateStr(deposit.shift_date);
                    }
                    if (document.getElementById('viewShiftTime')) {
                        document.getElementById('viewShiftTime').textContent = `${formatTimeOnly(deposit.shift_start)} - ${formatTimeOnly(deposit.shift_end)}`;
                    }
                    if (document.getElementById('viewCreatedBy')) {
                        document.getElementById('viewCreatedBy').textContent = deposit.created_by || 'System';
                    }
                    if (document.getElementById('viewCashExpected')) {
                        document.getElementById('viewCashExpected').textContent = formatMoney(deposit.cash_expected);
                    }
                    if (document.getElementById('viewCashDeposited')) {
                        document.getElementById('viewCashDeposited').textContent = formatMoney(deposit.cash_deposited);
                    }

                    const variance = parseFloat(deposit.variance);
                    const varianceType = deposit.status === 'short' ? 'SHORT' : (deposit.status === 'over' ? 'OVER' : 'EXACT');
                    const varianceClass = deposit.status === 'short' ? 'short' : (deposit.status === 'over' ? 'over' : 'exact');
                    const varianceAmount = deposit.status === 'short' ? Math.abs(variance) : variance;
                    const sign = deposit.status === 'over' ? '+' : '';

                    if (document.getElementById('viewVarianceType')) {
                        document.getElementById('viewVarianceType').textContent = varianceType;
                        document.getElementById('viewVarianceType').className = `status-badge ${varianceClass}`;
                    }
                    if (document.getElementById('viewVarianceAmount')) {
                        document.getElementById('viewVarianceAmount').textContent = `${sign}${formatMoney(varianceAmount)}`;
                    }

                    // Populate Reason and Notes
                    if (document.getElementById('viewReason')) {
                        document.getElementById('viewReason').textContent = (deposit.reason && deposit.reason.trim()) ? deposit.reason : '-';
                    }
                    if (document.getElementById('viewNotes')) {
                        document.getElementById('viewNotes').textContent = (deposit.notes && deposit.notes.trim()) ? deposit.notes : '-';
                    }

                    // Display breakdown items
                    const breakdownTableBody = document.getElementById('viewBreakdownTableBody');
                    if (breakdownTableBody) {
                        breakdownTableBody.innerHTML = '';

                        if (deposit.breakdown) {
                            try {
                                const breakdownItems = JSON.parse(deposit.breakdown);
                                let total = 0;

                                breakdownItems.forEach(item => {
                                    total += parseFloat(item.amount);
                                    const row = document.createElement('tr');
                                    row.style.borderBottom = '1px solid #e5e7eb';
                                    row.innerHTML = `
                                        <td style="padding: 8px 12px; font-size: 0.75rem; color: #111827; font-family: 'Poppins', sans-serif;">${item.description}</td>
                                        <td style="padding: 8px 12px; text-align: right; font-weight: 600; font-size: 0.75rem; color: #111827; font-family: 'Poppins', sans-serif;">${formatMoney(item.amount)}</td>
                                    `;
                                    breakdownTableBody.appendChild(row);
                                });

                                // Add total row
                                const totalRow = document.createElement('tr');
                                totalRow.style.background = '#f9fafb';
                                totalRow.innerHTML = `
                                    <td style="padding: 10px 12px; font-weight: 700; border-top: 2px solid #e5e7eb; font-size: 0.75rem; color: #111827; font-family: 'Poppins', sans-serif;">Total</td>
                                    <td style="padding: 10px 12px; text-align: right; font-weight: 700; border-top: 2px solid #e5e7eb; font-size: 0.8rem; color: #111827; font-family: 'Poppins', sans-serif;">${formatMoney(total)}</td>
                                `;
                                breakdownTableBody.appendChild(totalRow);
                            } catch (e) {
                                console.error('Error parsing breakdown data:', e);
                                breakdownTableBody.innerHTML = '<tr><td colspan="2" style="padding: 10px; color: #ef4444; font-size: 0.75rem;">Error loading breakdown data</td></tr>';
                            }
                        } else {
                            breakdownTableBody.innerHTML = '<tr><td colspan="2" style="padding: 10px; color: #9ca3af; font-size: 0.75rem; font-style: italic; text-align: center;">No breakdown items recorded</td></tr>';
                        }
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
            const shiftStartDate = document.getElementById('modalShiftStart').value; // Just the date (YYYY-MM-DD)
            const shiftEndDate = document.getElementById('modalShiftEnd').value; // Just the date (YYYY-MM-DD)
            const shiftStart = shiftStartDate + ' 08:00:00'; // Append 8:00 AM
            const shiftEnd = shiftEndDate + ' 08:00:00'; // Append 8:00 AM
            const shiftDate = shiftStartDate;
            const depositDate = document.getElementById('modalDepositDate').value;
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

            // Validate reason for exact status
            let reason = '';
            if (Math.abs(variance) <= 0.01) {
                reason = document.getElementById('modalReason').value.trim();
                if (!reason) {
                    alert('Please enter a Reason/Description for the exact deposit.');
                    return;
                }
            }

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
                deposit_date: depositDate,
                cash_expected: cashExpected,
                cash_deposited: cashDeposited,
                reason: reason,
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
                    // Refresh deposits list (running sales are refreshed inside closeDepositModal)
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
                    const filterInput = document.getElementById('shiftCardDateFilter');
                    if (filterInput && filterInput.value) {
                        handleShiftCardDateChange();
                    } else {
                        await fetchRunningSales();
                    }
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
            if (document.getElementById('startDateFilter')) document.getElementById('startDateFilter').value = '';
            if (document.getElementById('endDateFilter')) document.getElementById('endDateFilter').value = '';
            if (document.getElementById('depositDateFrom')) document.getElementById('depositDateFrom').value = '';
            if (document.getElementById('depositDateTo')) document.getElementById('depositDateTo').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('searchInput').value = '';

            // Also reset the shift card date filter if set
            const cardDateFilter = document.getElementById('shiftCardDateFilter');
            if (cardDateFilter) {
                cardDateFilter.value = '';
                document.getElementById('filterDateBtn').style.display = 'block';
                cardDateFilter.style.display = 'none';
                fetchRunningSales();
            }

            fetchDeposits();
        }

        function showShiftCardDateFilter() {
            document.getElementById('filterDateBtn').style.display = 'none';
            const filterInput = document.getElementById('shiftCardDateFilter');
            filterInput.style.display = 'block';
            filterInput.focus();
            if (typeof filterInput.showPicker === 'function') {
                filterInput.showPicker();
            }
        }

        function handleShiftCardDateChange() {
            const dateInput = document.getElementById('shiftCardDateFilter');
            if (!dateInput) return;

            if (!dateInput.value) {
                // If date was cleared, go back to Filter Date button and live shift
                document.getElementById('filterDateBtn').style.display = 'block';
                dateInput.style.display = 'none';
                fetchRunningSales();
                return;
            }

            const selectedDate = dateInput.value; // "YYYY-MM-DD"
            const parts = selectedDate.split('-');
            if (parts.length !== 3) return;

            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);

            // Construct shift start date time (8:00 AM of selected date)
            const startDt = new Date(year, month, day, 8, 0, 0);
            const endDt = new Date(startDt);
            endDt.setDate(endDt.getDate() + 1);

            const startStr = `${parts[0]}-${parts[1]}-${parts[2]} 08:00:00`;

            const endYear = endDt.getFullYear();
            const endMonth = String(endDt.getMonth() + 1).padStart(2, '0');
            const endDay = String(endDt.getDate()).padStart(2, '0');
            const endStr = `${endYear}-${endMonth}-${endDay} 08:00:00`;

            // Update modal fields with selected dates
            const modalShiftStart = document.getElementById('modalShiftStart');
            const modalShiftEnd = document.getElementById('modalShiftEnd');
            if (modalShiftStart) modalShiftStart.value = selectedDate; // Start date
            if (modalShiftEnd) modalShiftEnd.value = `${endYear}-${endMonth}-${endDay}`; // End date (next day)

            fetchRunningSales(startStr, endStr);
        }

        // Handle custom shift start date/time adjustments
        function handleShiftStartChange() {
            calculateExpectedSalesIfBothDatesSet();
        }

        function handleShiftEndChange() {
            calculateExpectedSalesIfBothDatesSet();
        }

        function calculateExpectedSalesIfBothDatesSet() {
            const startInput = document.getElementById('modalShiftStart');
            const endInput = document.getElementById('modalShiftEnd');
            
            if (!startInput || !endInput) return;
            if (!startInput.value || !endInput.value) return;

            const startDate = startInput.value; // "YYYY-MM-DD"
            const endDate = endInput.value; // "YYYY-MM-DD"

            // Append 8:00 AM time to both dates
            const startParam = `${startDate} 08:00:00`;
            const endParam = `${endDate} 08:00:00`;

            fetchExpectedSalesForShift(startParam, endParam);
        }

        async function fetchExpectedSalesForShift(startStr, endStr) {
            const expectedSalesInput = document.getElementById('modalExpectedSales');
            if (!expectedSalesInput) return;

            try {
                const response = await fetch(`get_cash_running_sales.php?shift_start=${encodeURIComponent(startStr)}&shift_end=${encodeURIComponent(endStr)}`);
                const data = await response.json();
                if (data.success) {
                    expectedSalesInput.value = data.cash_total;
                    updateVarianceCalculation();
                }
            } catch (error) {
                console.error('Error fetching expected sales for custom shift:', error);
            }
        }

        // Open Report Modal
        function openReportModal() {
            // Get the active shift date as default
            const shiftDate = currentShiftData.shift_date || '';
            document.getElementById('reportFromDate').value = shiftDate;
            document.getElementById('reportToDate').value = shiftDate;
            document.getElementById('reportModalOverlay').classList.add('open');
        }

        // Close Report Modal
        function closeReportModal() {
            document.getElementById('reportModalOverlay').classList.remove('open');
        }

        // Generate Report
        function generateReport() {
            const fromDate = document.getElementById('reportFromDate').value;
            const toDate = document.getElementById('reportToDate').value;

            if (!fromDate || !toDate) {
                alert('Please select both From Date and To Date');
                return;
            }

            if (fromDate > toDate) {
                alert('From Date cannot be greater than To Date');
                return;
            }

            // Open report in new window
            const url = `cash_deposit_report.php?from=${encodeURIComponent(fromDate)}&to=${encodeURIComponent(toDate)}`;
            window.open(url, '_blank', 'width=1000,height=800,scrollbars=yes');
            
            // Close the modal
            closeReportModal();
        }

        // Initialization
        document.addEventListener('DOMContentLoaded', async () => {
            // Load user data first
            await loadUserInfo();

            // Hide Report button if not Super Admin
            if (activeUser.access_level !== 'super_admin') {
                const reportBtn = document.querySelector('.secondary-btn[onclick="openReportModal()"]');
                if (reportBtn) {
                    reportBtn.style.display = 'none';
                }
            }

            // Load live calculations
            await fetchRunningSales();

            // Load database list
            fetchDeposits();

            // Set up form submission listener
            document.getElementById('depositForm').addEventListener('submit', saveDeposit);

            // Setup realtime validation triggers
            document.getElementById('modalDeposited').addEventListener('input', updateVarianceCalculation);
            document.getElementById('modalExpectedSales').addEventListener('input', updateVarianceCalculation);

            // Setup filters listeners
            document.getElementById('startDateFilter').addEventListener('change', fetchDeposits);
            document.getElementById('endDateFilter').addEventListener('change', fetchDeposits);
            document.getElementById('depositDateFrom').addEventListener('change', fetchDeposits);
            document.getElementById('depositDateTo').addEventListener('change', fetchDeposits);
            document.getElementById('statusFilter').addEventListener('change', fetchDeposits);

            // Setup shift start date change listener
            document.getElementById('modalShiftStart').addEventListener('change', handleShiftStartChange);
            document.getElementById('modalShiftStart').addEventListener('input', handleShiftStartChange);

            // Setup shift end date change listener
            document.getElementById('modalShiftEnd').addEventListener('change', handleShiftEndChange);
            document.getElementById('modalShiftEnd').addEventListener('input', handleShiftEndChange);

            // Setup shift card date filter change listener
            const shiftCardDateFilter = document.getElementById('shiftCardDateFilter');
            if (shiftCardDateFilter) {
                shiftCardDateFilter.addEventListener('change', handleShiftCardDateChange);
                shiftCardDateFilter.addEventListener('input', handleShiftCardDateChange);
            }

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
    <div class="split-container">
        <!-- Global Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Right Panel -->
        <div class="right-panel">
            <!-- Global Header -->
            <?php include 'includes/header.php'; ?>

            <div class="content-container">
                <div class="header-title-row">
                    <div>
                        <h2 class="header-title">Running Cash Deposit</h2>
                        <!--    <div class="running-time-subtitle" id="shiftPeriodSubtitle">Loading shift window...</div> -->
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button class="add-deposit-btn secondary-btn" onclick="openReportModal()" title="Generate Deposit Report">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                            Report
                        </button>
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
                            <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <rect x="2" y="5" width="20" height="14" rx="2" />
                                <line x1="2" y1="10" x2="22" y2="10" />
                            </svg>
                            Running Cash
                        </div>
                        <div class="card-value" id="expectedCashValue">₱0.00</div>
                        <div class="card-meta">
                            <span id="expectedTxCount">0</span> transactions in current shift
                        </div>
                        <div id="depositRecordedBadge" style="display: none;" class="deposit-recorded-badge">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <span>Deposit Recorded</span>
                        </div>
                    </div>

                    <!-- Pending Deposit Card -->
                    <div class="premium-card pending-card">
                        <div class="card-label">
                            <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                            Pending Deposit
                        </div>
                        <div class="card-value" id="pendingDepositValue">₱0.00</div>
                        <div class="card-meta" id="pendingDepositMeta">
                            Undeposited since Jan 1
                        </div>
                    </div>

                    <!-- Active Shift Information -->
                    <div class="premium-card shift-card">
                        <div class="card-label"
                            style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <span style="display: flex; align-items: center; gap: 6px;">
                                <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg>
                                Active Shift
                            </span>
                            <button type="button" id="filterDateBtn" class="table-action-btn secondary"
                                style="height: 28px; padding: 0 10px; font-size: 11px; margin: 0; width: auto; display: none;"
                                onclick="showShiftCardDateFilter()">Filter Date</button>
                            <input type="date" id="shiftCardDateFilter" class="date-picker-input"
                                style="display: none; height: 28px; padding: 0 8px; font-size: 11px;"
                                title="Filter shift date">
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

                    <!-- Total Deposit Card -->
                    <div class="premium-card cash-card" id="totalDepositCard" style="display: none;">
                        <div class="card-label">
                            Total Deposit
                        </div>
                        <div class="card-value" id="totalDepositValue">₱0.00</div>
                        <div class="card-meta" id="totalDepositMeta">
                            <span id="totalDepositCount">0</span> deposits recorded
                        </div>
                    </div>
                </div>

                <!-- Search & Filters -->
                <div class="deposit-action-bar">
                    <div class="search-filter-group">
                        <div>
                            <input type="text" id="searchInput" class="search-input-simple"
                                placeholder="Search records...">
                        </div>
                    </div>

                    <div class="search-filter-group" style="gap: 8px;">
                        <div>
                            <span class="date-picker-label">Deposit From:</span>
                            <input type="date" id="depositDateFrom" class="date-picker-input"
                                title="Filter by deposit date from">
                        </div>
                        <div>
                            <span class="date-picker-label">Deposit To:</span>
                            <input type="date" id="depositDateTo" class="date-picker-input"
                                title="Filter by deposit date to">
                        </div>

                        
                        <div>
                            <span class="date-picker-label">Shift Start Date:</span>
                            <input type="date" id="startDateFilter" class="date-picker-input"
                                title="Filter by shift start date">
                        </div>
                        <div>
                            <span class="date-picker-label">Shift End Date:</span>
                            <input type="date" id="endDateFilter" class="date-picker-input"
                                title="Filter by shift end date">
                        </div>

                        <div>
                            <select id="statusFilter" class="status-filter-select">
                                <option value="all">All Discrepancies</option>
                                <option value="exact">Exact Deposits Only</option>
                                <option value="short">Short Deposits Only</option>
                                <option value="over">Over Deposits Only</option>
                            </select>
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
                                <th>Deposit Date</th>
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
                                <td colspan="12" class="empty-state">Loading cash deposits...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <div class="pagination-container">
                    <button id="prevPageBtn" class="pagination-btn" onclick="previousPage()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Previous
                    </button>
                    <span id="paginationInfo" class="pagination-info">Page 1 of 1</span>
                    <button id="nextPageBtn" class="pagination-btn" onclick="nextPage()">
                        Next
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
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

                <div class="modal-content">

                    <!-- Deposit Date Form Group -->
                    <div class="form-group">
                        <label class="form-label">Deposit Date <span class="required">*</span></label>
                        <input type="date" id="modalDepositDate" class="form-input" required>
                    </div>

                    <!-- Shift Start and Shift End Inputs -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Shift Start Date <span class="required">*</span></label>
                            <input type="date" id="modalShiftStart" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Shift End Date <span class="required">*</span></label>
                            <input type="date" id="modalShiftEnd" class="form-input" required>
                        </div>
                    </div>

                    <!-- Two-column layout for Cash Sales and Physical Cash Deposited -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Cash Sales <span class="required">*</span></label>
                            <input type="number" step="0.01" min="0" id="modalExpectedSales" class="form-input"
                                placeholder="0.00" required readonly
                                style="background-color: #f3f4f6; cursor: not-allowed;">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Physical Cash Deposited <span class="required">*</span></label>
                            <input type="number" step="0.01" min="0" id="modalDeposited" class="form-input"
                                placeholder="0.00" required>
                        </div>
                    </div>

                    <!-- Variance Card Display -->
                    <div class="variance-box exact" id="varianceDisplayBox">
                        <div class="variance-label" id="varianceLabelText">Variance</div>
                        <div class="variance-amount" id="varianceAmountText">₱0.00</div>
                    </div>

                    <!-- Reason/Description Section (only for EXACT variance) -->
                    <div class="form-group" id="exactReasonGroup" style="display: none;">
                        <label class="form-label">Reason<span class="required">*</span></label>
                        <input type="text" id="modalReason" class="form-input" placeholder="e.g. Reason">
                    </div>

                    <!-- Shortage Breakdown Section (only for SHORT variance) -->
                    <div class="form-group" id="shortageBreakdownGroup" style="display: none;">
                        <label class="form-label" id="breakdownLabel">Shortage Breakdown <span
                                class="required">*</span></label>
                        <div id="breakdownInstructionText"
                            style="font-size: 12px; color: #6b7280; margin-bottom: 12px;">
                            Add line items that explain the shortage. Total must equal: <strong
                                id="breakdownRequiredTotal">₱0.00</strong>
                        </div>

                        <div class="breakdown-container">
                            <div id="breakdownItemsContainer">
                                <!-- Initial breakdown item -->
                                <div class="breakdown-row">
                                    <input type="number" step="0.01" min="0" placeholder="Amount"
                                        class="breakdown-amount" oninput="calculateBreakdownTotal()">
                                    <input type="text" placeholder="Description (e.g. Electric bills)"
                                        class="breakdown-description">
                                    <button type="button" class="breakdown-remove-btn"
                                        onclick="removeBreakdownRow(this)" title="Remove">×</button>
                                </div>
                            </div>

                            <button type="button" class="add-breakdown-btn" onclick="addBreakdownRow()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <line x1="12" y1="5" x2="12" y2="19" />
                                    <line x1="5" y1="12" x2="19" y2="12" />
                                </svg>
                                Add Item
                            </button>

                            <div class="breakdown-total-row">
                                <span>Total Breakdown:</span>
                                <span id="breakdownTotal">₱0.00</span>
                            </div>
                        </div>

                        <div id="breakdownValidationMessage"
                            style="display: none; margin-top: 8px; padding: 8px 12px; background: #fee2e2; border: 1px solid #fecaca; border-radius: 6px; font-size: 12px; color: #dc2626;">
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
        <div class="modal-content">
            <div class="modal-header">
                <h2>Breakdown Details</h2>
                <span class="close" onclick="closeViewBreakdownModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="booking-details-title" id="viewDepositDetailsTitle">
                    Deposit ID: #<span id="viewDepositIdTitle">-</span>
                </div>

                <!-- 2-Column Grid Layout for Sections -->
                <div class="sections-grid">
                    <!-- Shift & Date Information Section -->
                    <div class="info-section">
                        <h3 class="section-title">Shift & Date Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Deposit Date:</span>
                                <span class="info-value" id="viewDepositDate">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Shift Date:</span>
                                <span class="info-value" id="viewShiftDate">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Shift Time:</span>
                                <span class="info-value" id="viewShiftTime">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Encoder / Created By:</span>
                                <span class="info-value" id="viewCreatedBy">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary Section -->
                    <div class="info-section">
                        <h3 class="section-title">Financial Summary</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Cash Sales (Expected):</span>
                                <span class="info-value" id="viewCashExpected">₱0.00</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Cash Deposited:</span>
                                <span class="info-value" id="viewCashDeposited">₱0.00</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Variance Status:</span>
                                <span class="info-value">
                                    <span class="status-badge short" id="viewVarianceType">SHORT</span>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Variance Amount:</span>
                                <span class="info-value" id="viewVarianceAmount">₱0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deposit Notes Section (Full Width) -->
                <div class="info-section full-width">
                    <h3 class="section-title">Deposit Notes</h3>
                    <div class="info-grid">
                        <div class="info-item full-width">
                            <span class="info-label">Notes:</span>
                            <span class="info-value" id="viewNotes">-</span>
                        </div>
                    </div>
                </div>

                <!-- Breakdown Items Section (Full Width) -->
                <div class="info-section full-width" style="margin-bottom: 0;">
                    <h3 class="section-title">Breakdown Items</h3>
                    <div style="border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-top: 6px;">
                        <table style="width: 100%; border-collapse: collapse; font-family: 'Poppins', sans-serif;">
                            <thead>
                                <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                    <th style="padding: 10px 12px; text-align: left; font-size: 0.65rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Description</th>
                                    <th style="padding: 10px 12px; text-align: right; font-size: 0.65rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="viewBreakdownTableBody">
                                <!-- Breakdown items will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeViewBreakdownModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Report Modal -->
    <div class="modal-overlay" id="reportModalOverlay">
        <div class="modal-container" style="max-width: 500px;">
            <h3 class="modal-title">Generate Cash Deposit Report</h3>
            
            <div class="modal-body">
                <div class="report-date-group">
                    <div class="form-group">
                        <label class="report-date-label">From Date<span class="required">*</span></label>
                        <input type="date" id="reportFromDate" class="form-input" required>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 4px; font-weight: 500;">
                            @ 8:00 AM
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="report-date-label">To Date<span class="required">*</span></label>
                        <input type="date" id="reportToDate" class="form-input" required>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 4px; font-weight: 500;">
                            @ 8:00 AM (next day)
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-buttons">
                <button type="button" class="modal-btn-cancel" onclick="closeReportModal()">Cancel</button>
                <button type="button" class="modal-btn-save" onclick="generateReport()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                        stroke-width="2.5" style="vertical-align: middle; margin-right: 4px;">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    Generate Report
                </button>
            </div>
        </div>
    </div>

</body>

</html>