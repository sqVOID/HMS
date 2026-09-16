<?php
require_once 'access_check.php';
checkAccess('Reservationlist.php');
?>
<!DOCTYPE html>
<html lang="en-US">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>Reservation List</title>
    <!-- Global Layout Styles -->
    <link rel="stylesheet" href="includes/global_layout.css">
    <!-- Page-specific Styles -->
    <link rel="stylesheet" href="Booking.css?v=4">
    <link rel="stylesheet" href="includes/flatpickr.min.css">
    <link rel="stylesheet" href="includes/booking_flatpickr.css">
    <style>
        /* Date filter input */
        .date-filter-container {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            padding: 0 8px;
            height: 32px;
            gap: 6px;
        }

        .date-filter-label {
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            color: #475569;
            font-weight: 600;
            white-space: nowrap;
        }

        .date-filter-input {
            border: none;
            outline: none;
            background: transparent;
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            color: #222;
            cursor: pointer;
            width: 120px;
        }

        .date-filter-clear {
            background: none;
            border: none;
            cursor: pointer;
            color: #999;
            font-size: 14px;
            padding: 0;
            line-height: 1;
            display: none;
        }

        .date-filter-clear:hover {
            color: #e53935;
        }

        /* Types dropdown (Status filter) */
        .types-dropdown-wrapper {
            position: relative;
            display: inline-block;
        }

        .types-dropdown-btn {
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
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .types-dropdown-btn:hover,
        .types-dropdown-btn:focus {
            border-color: #1b5e20;
        }

        .types-arrow {
            font-size: 10px;
            margin-left: 6px;
        }

        .types-dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            margin-top: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        }

        .types-dropdown-item {
            padding: 8px 12px;
            font-size: 11px;
            font-family: 'Poppins', sans-serif;
            color: #222;
            cursor: pointer;
            transition: background 0.2s;
        }

        .types-dropdown-item:hover {
            background: #f8fafc;
        }

        /* Reservation edit modal (matches Modification.php style) */
        .reservation-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .reservation-modal-content {
            background-color: #ffffff;
            margin: 3% auto;
            padding: 0;
            border-radius: 4px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .reservation-modal-header {
            padding: 20px 24px;
            background: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .reservation-modal-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #212121;
        }

        .reservation-modal-close {
            color: #757575;
            font-size: 24px;
            font-weight: 300;
            cursor: pointer;
            line-height: 1;
        }

        .reservation-modal-close:hover {
            color: #212121;
        }

        .reservation-modal-body {
            padding: 24px;
            margin: 0;
            max-height: 60vh;
            overflow-y: auto;
        }

        .reservation-detail-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 16px;
        }

        .reservation-detail-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .reservation-detail-item.full-width {
            grid-column: 1 / -1;
        }

        .reservation-detail-label {
            font-size: 11px;
            font-weight: 600;
            color: #757575;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .reservation-detail-input {
            font-size: 14px;
            color: #212121;
            font-weight: 400;
            padding: 8px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
        }

        .reservation-detail-input:focus {
            outline: none;
            border-color: #5e5ce6;
        }

        .reservation-detail-readonly {
            font-size: 14px;
            color: #424242;
            padding: 10px 12px;
            background: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            min-height: 20px;
        }

        .reservation-detail-divider {
            height: 1px;
            background: #e0e0e0;
            margin: 16px 0;
        }

        .reservation-modal-footer {
            padding: 16px 24px;
            background: #fafafa;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .reservation-modal-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 3px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
        }

        .reservation-modal-btn-primary {
            background: #292929;
            color: white;
        }

        .reservation-modal-btn-secondary {
            background: white;
            color: #424242;
            border: 1px solid #d0d0d0;
        }

        .reservation-action-btns {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .reservation-room-dropdown {
            position: relative;
            width: 100%;
        }

        .reservation-room-dropdown-btn {
            padding: 8px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            font-size: 14px;
            color: #212121;
            box-sizing: border-box;
            min-height: 38px;
        }

        .reservation-room-dropdown-btn:hover {
            border-color: #5e5ce6;
        }

        .reservation-room-dropdown-list {
            display: none;
            position: absolute;
            margin-top: 4px;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 220px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            z-index: 10001;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .reservation-room-group-title {
            padding: 8px 14px;
            background: #f3f4f6;
            font-weight: 600;
            font-size: 12px;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            user-select: none;
        }

        .reservation-room-group-item {
            padding: 10px 18px;
            font-size: 14px;
            cursor: pointer;
            color: #111827;
            border-bottom: 1px solid #f9fafb;
            transition: background-color 0.15s ease;
        }

        .reservation-room-group-item:hover {
            background-color: #f3f4f6;
        }

        .reservation-room-group-item small {
            color: #6b7280;
            margin-left: 8px;
            font-size: 11px;
        }

        .reservation-datetime-cell {
            line-height: 1.35;
            white-space: nowrap;
        }

        .reservation-datetime-time {
            display: block;
            font-size: 12px;
            color: #888;
            margin-top: 2px;
        }

        /* Reservation list table — override Booking.css column widths */
        .reservation-list-table {
            table-layout: fixed;
            width: 100%;
            min-width: 1180px;
        }

        .reservation-list-table th,
        .reservation-list-table td {
            padding: 6px 4px !important;
            vertical-align: middle;
        }

        .reservation-list-table th:nth-child(1),
        .reservation-list-table td:nth-child(1) {
            width: 112px;
            min-width: 112px !important;
        }

        .reservation-list-table th:nth-child(2),
        .reservation-list-table td:nth-child(2) {
            width: 130px;
            min-width: 130px !important;
        }

        .reservation-list-table th:nth-child(3),
        .reservation-list-table td:nth-child(3) {
            width: 95px;
            min-width: 95px !important;
        }

        .reservation-list-table th:nth-child(4),
        .reservation-list-table td:nth-child(4) {
            width: 68px;
            min-width: 68px !important;
        }

        .reservation-list-table th:nth-child(5),
        .reservation-list-table td:nth-child(5) {
            width: 102px;
            min-width: 102px !important;
            max-width: 102px;
        }

        .reservation-list-table th:nth-child(6),
        .reservation-list-table td:nth-child(6),
        .reservation-list-table th:nth-child(7),
        .reservation-list-table td:nth-child(7) {
            width: 78px;
            min-width: 78px !important;
        }

        .reservation-list-table th:nth-child(8),
        .reservation-list-table td:nth-child(8) {
            width: 82px;
            min-width: 82px !important;
        }

        .reservation-list-table th:nth-child(9),
        .reservation-list-table td:nth-child(9) {
            width: 88px;
            min-width: 88px !important;
        }

        .reservation-list-table th:nth-child(10),
        .reservation-list-table td:nth-child(10) {
            width: 102px;
            min-width: 102px !important;
            max-width: 102px;
        }

        .reservation-list-table th:nth-child(11),
        .reservation-list-table td:nth-child(11) {
            width: 92px;
            min-width: 92px !important;
        }

        .reservation-list-table th:nth-child(12),
        .reservation-list-table td:nth-child(12) {
            width: 82px;
            min-width: 82px !important;
        }

        .reservation-list-table th:nth-child(13),
        .reservation-list-table td:nth-child(13) {
            width: 210px;
            min-width: 210px !important;
        }

        .reservation-date-cell {
            text-align: center;
        }

        .res-date-edit {
            display: none;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            width: 100%;
            min-width: 0;
        }

        .res-date-edit input.res-date-input[type="hidden"],
        .res-date-edit input.flatpickr-input[type="hidden"] {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            background: none !important;
        }

        .res-date-edit .res-date-input:not([type="hidden"]),
        .res-date-edit input.res-date-input:not([type="hidden"]),
        .res-date-edit .form-control:not([type="hidden"]) {
            display: block;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100%;
            height: 36px;
            margin: 0;
            padding: 7px 10px 7px 34px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 8px !important;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2'/%3E%3Cpath d='M16 2v4M8 2v4M3 10h18'/%3E%3C/svg%3E") no-repeat 10px center !important;
            background-size: 15px 15px !important;
            font-family: 'Poppins', 'Segoe UI', Arial, sans-serif !important;
            font-size: 12px !important;
            font-weight: 500;
            color: #222 !important;
            line-height: 1.2;
            box-sizing: border-box;
            cursor: pointer;
            box-shadow: none !important;
        }

        .res-date-edit .res-date-input:not([type="hidden"]):focus,
        .res-date-edit .form-control:not([type="hidden"]):focus {
            outline: none !important;
            border-color: #256d27 !important;
            box-shadow: 0 0 0 3px rgba(37, 109, 39, 0.12) !important;
        }

        .res-date-edit-actions {
            display: flex;
            justify-content: center;
            gap: 6px;
        }

        .res-date-edit-actions button {
            flex: 1;
            min-width: 0;
            padding: 6px 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            font-weight: 500;
        }

        .res-date-save-btn {
            background: #16a34a;
            color: #fff;
        }

        .res-date-cancel-btn {
            background: #e5e7eb;
            color: #111;
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="includes/flatpickr.min.js"></script>
    <script src="role-based-menu.js?v=18"></script>
    <script src="auto_logout.js?v=4" defer></script>
    <script src="cancellation-notification.js?v=4" defer></script>
    <!-- Global Sidebar Scripts -->
    <script src="includes/sidebar_scripts.js" defer></script>
    <script>
        // Pagination variables
        let currentPage = 1;
        const itemsPerPage = 15;
        let allReservations = [];

        // ── Status dropdown ────────────────────────────────────────────────────
        function toggleStatusDropdown() {
            const dropdown = document.getElementById('statusDropdown');
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }

        function selectStatus(status) {
            document.getElementById('selectedStatus').textContent = status;
            document.getElementById('statusDropdown').style.display = 'none';
            loadReservations();
        }

        // Close dropdown on outside click
        window.onclick = function (event) {
            if (!event.target.matches('.types-dropdown-btn') && !event.target.matches('.types-arrow')) {
                const dropdown = document.getElementById('statusDropdown');
                if (dropdown && dropdown.style.display === 'block') {
                    dropdown.style.display = 'none';
                }
            }
        };

        // ── Search ─────────────────────────────────────────────────────────────
        function searchReservation() {
            loadReservations();
        }

        function searchByDate(dateValue) {
            const tbody = document.getElementById('reservationTableBody');
            tbody.innerHTML = '<tr><td colspan="13" style="text-align: center; padding: 20px;">Loading...</td></tr>';

            const status = document.getElementById('selectedStatus').textContent;
            const statusParam = status === 'All Status' ? 'all' : status;
            const search = document.getElementById('bookingIdInput').value.trim();

            const url = `get_reservations.php?status=${encodeURIComponent(statusParam)}&search=${encodeURIComponent(search)}&date=${encodeURIComponent(dateValue)}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayReservations(data.data);
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
            loadReservations();
        }

        // ── Main data loader ───────────────────────────────────────────────────
        async function loadReservations() {
            const status = document.getElementById('selectedStatus').textContent;
            const statusParam = status === 'All Status' ? 'all' : status;
            const search = document.getElementById('bookingIdInput').value.trim();

            try {
                const url = `get_reservations.php?status=${encodeURIComponent(statusParam)}&search=${encodeURIComponent(search)}`;
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    displayReservations(data.data);
                } else {
                    alert('Failed to load reservations: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error loading reservations:', error);
                alert('Error loading reservations');
            }
        }

        // ── Helpers ────────────────────────────────────────────────────────────
        function formatDate(dt) {
            if (!dt) return 'N/A';
            const d = new Date(dt);
            return isNaN(d.getTime()) ? 'N/A' : d.toLocaleDateString();
        }

        function formatDateTime(dt) {
            if (!dt) return 'N/A';
            const d = new Date(dt);
            if (isNaN(d.getTime())) return 'N/A';
            return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function formatTableDateTime(dt) {
            if (!dt) return 'N/A';
            const d = new Date(dt);
            if (isNaN(d.getTime())) return 'N/A';
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const yyyy = d.getFullYear();
            const dateStr = `${mm}/${dd}/${yyyy}`;
            const timeStr = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            return `<span class="reservation-datetime-cell">${dateStr}<span class="reservation-datetime-time">${timeStr}</span></span>`;
        }

        function toDatetimeLocalValue(dt) {
            if (!dt) return '';
            const d = new Date(dt);
            if (isNaN(d.getTime())) return '';
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const hh = String(d.getHours()).padStart(2, '0');
            const mi = String(d.getMinutes()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd} ${hh}:${mi}`;
        }

        function updateFlatpickrArrows(instance) {
            if (!instance || !instance.calendarContainer) return;
            const prev = instance.calendarContainer.querySelector('.flatpickr-prev-month');
            const next = instance.calendarContainer.querySelector('.flatpickr-next-month');
            if (prev) {
                prev.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>';
                prev.title = 'Previous month';
            }
            if (next) {
                next.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>';
                next.title = 'Next month';
            }
        }

        function attachCustomFooter(instance) {
            if (!instance || !instance.calendarContainer) return;
            const rContainer = instance.calendarContainer.querySelector('.flatpickr-rContainer') || instance.calendarContainer;
            if (rContainer.querySelector('.flatpickr-custom-footer')) return;
            const footer = document.createElement('div');
            footer.className = 'flatpickr-custom-footer';
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.textContent = 'Clear';
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                instance._customTimePicked = false;
                instance.clear();
                syncTimeColumnsState(instance);
            });
            const todayBtn = document.createElement('button');
            todayBtn.type = 'button';
            todayBtn.textContent = 'Today';
            todayBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                instance._customTimePicked = true;
                instance.setDate(new Date(), true);
                syncTimeColumnsState(instance);
            });
            footer.appendChild(clearBtn);
            footer.appendChild(todayBtn);
            rContainer.appendChild(footer);
        }

        function attachCustomTimeColumns(instance) {
            if (!instance || !instance.calendarContainer) return;
            const container = instance.calendarContainer;
            let panel = container.querySelector('.custom-time-columns-panel');
            if (!panel) {
                panel = document.createElement('div');
                panel.className = 'custom-time-columns-panel';
                panel.innerHTML = `
                    <div class="custom-time-col hours-col"><div class="time-header-box selected-hour-box">12</div><div class="time-scroll-list hours-list"></div></div>
                    <div class="custom-time-col minutes-col"><div class="time-header-box selected-minute-box">00</div><div class="time-scroll-list minutes-list"></div></div>
                    <div class="custom-time-col ampm-col"><div class="time-header-box selected-ampm-box">am</div><div class="time-scroll-list ampm-list"></div></div>
                `;
                container.appendChild(panel);
            }
            syncTimeColumnsState(instance);
        }

        function setTimeComponent(instance, type, value) {
            let d = instance.selectedDates && instance.selectedDates[0] ? new Date(instance.selectedDates[0]) : new Date();
            let hours24 = d.getHours();
            let isPM = hours24 >= 12;
            if (type === 'hour') {
                const target12 = parseInt(value, 10);
                hours24 = isPM ? (target12 === 12 ? 12 : target12 + 12) : (target12 === 12 ? 0 : target12);
                d.setHours(hours24);
            } else if (type === 'minute') {
                d.setMinutes(parseInt(value, 10));
            } else if (type === 'ampm') {
                const isTargetPM = String(value).toLowerCase() === 'pm';
                if (isTargetPM && !isPM) d.setHours(hours24 + 12);
                else if (!isTargetPM && isPM) d.setHours(hours24 - 12);
            }
            instance._customTimePicked = true;
            instance.setDate(d, true);
            syncTimeColumnsState(instance);
        }

        function syncTimeColumnsState(instance) {
            if (!instance || !instance.calendarContainer) return;
            const panel = instance.calendarContainer.querySelector('.custom-time-columns-panel');
            if (!panel) return;
            const d = instance.selectedDates && instance.selectedDates[0] ? new Date(instance.selectedDates[0]) : new Date();
            const hours24 = d.getHours();
            const hours12 = hours24 % 12 || 12;
            const minutes = d.getMinutes();
            const ampm = hours24 >= 12 ? 'pm' : 'am';
            const hourBox = panel.querySelector('.selected-hour-box');
            const minBox = panel.querySelector('.selected-minute-box');
            const ampmBox = panel.querySelector('.selected-ampm-box');
            if (hourBox) hourBox.textContent = String(hours12).padStart(2, '0');
            if (minBox) minBox.textContent = String(minutes).padStart(2, '0');
            if (ampmBox) ampmBox.textContent = ampm;
            const hoursList = panel.querySelector('.hours-list');
            if (hoursList) {
                hoursList.innerHTML = '';
                for (let i = 1; i <= 11; i++) {
                    let h = (hours12 + i) % 12;
                    if (h === 0) h = 12;
                    const item = document.createElement('div');
                    item.className = 'time-item';
                    item.textContent = String(h).padStart(2, '0');
                    item.addEventListener('click', function (e) {
                        e.preventDefault(); e.stopPropagation();
                        setTimeComponent(instance, 'hour', h);
                    });
                    hoursList.appendChild(item);
                }
            }
            const minsList = panel.querySelector('.minutes-list');
            if (minsList) {
                minsList.innerHTML = '';
                for (let i = 1; i < 60; i++) {
                    const m = (minutes + i) % 60;
                    const item = document.createElement('div');
                    item.className = 'time-item';
                    item.textContent = String(m).padStart(2, '0');
                    item.addEventListener('click', function (e) {
                        e.preventDefault(); e.stopPropagation();
                        setTimeComponent(instance, 'minute', m);
                    });
                    minsList.appendChild(item);
                }
            }
            const ampmList = panel.querySelector('.ampm-list');
            if (ampmList) {
                ampmList.innerHTML = '';
                const altAmPm = ampm === 'am' ? 'pm' : 'am';
                const item = document.createElement('div');
                item.className = 'time-item';
                item.textContent = altAmPm;
                item.addEventListener('click', function (e) {
                    e.preventDefault(); e.stopPropagation();
                    setTimeComponent(instance, 'ampm', altAmPm);
                });
                ampmList.appendChild(item);
            }
        }

        function lockFlatpickrToCurrentYear(instance) {
            const yearEl = instance && instance.currentYearElement;
            if (!yearEl) return;
            yearEl.readOnly = true;
            yearEl.disabled = true;
            yearEl.tabIndex = -1;
        }

        function initResDatePicker(input) {
            if (!input || typeof flatpickr === 'undefined' || input._flatpickr) return;
            const now = new Date();
            const year = now.getFullYear();
            const existing = input.value;
            flatpickr(input, {
                enableTime: true,
                animate: true,
                position: 'below left',
                dateFormat: 'Y-m-d H:i',
                altInput: true,
                altFormat: 'm/d/Y h:i K',
                altInputClass: 'res-date-input',
                allowInput: true,
                appendTo: document.body,
                minDate: new Date(year, 0, 1),
                maxDate: new Date(year, 11, 31, 23, 59, 59),
                time_24hr: false,
                disableMobile: true,
                defaultHour: now.getHours(),
                defaultMinute: now.getMinutes(),
                defaultDate: existing || null,
                locale: {
                    weekdays: {
                        shorthand: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                        longhand: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
                    }
                },
                onReady: function (_d, _s, instance) {
                    if (instance.altInput) instance.altInput.placeholder = 'mm/dd/yyyy --:-- --';
                    lockFlatpickrToCurrentYear(instance);
                    updateFlatpickrArrows(instance);
                    attachCustomTimeColumns(instance);
                    attachCustomFooter(instance);
                },
                onOpen: function (_d, _s, instance) {
                    lockFlatpickrToCurrentYear(instance);
                    updateFlatpickrArrows(instance);
                    attachCustomTimeColumns(instance);
                    attachCustomFooter(instance);
                },
                onMonthChange: function (_d, _s, instance) { updateFlatpickrArrows(instance); },
                onYearChange: function (_d, _s, instance) { updateFlatpickrArrows(instance); },
                onChange: function (selectedDates, _s, instance) {
                    if (!instance._customTimePicked && selectedDates && selectedDates.length > 0) {
                        const curNow = new Date();
                        const sel = selectedDates[0];
                        sel.setHours(curNow.getHours(), curNow.getMinutes(), 0, 0);
                        instance._customTimePicked = true;
                        instance.setDate(sel, false);
                    }
                    syncTimeColumnsState(instance);
                }
            });
        }

        async function saveReservationDate(id) {
            const input = document.getElementById(`resDateInput_${id}`);
            if (!input) return;
            const newVal = input.value;
            if (!newVal) {
                alert('Please select a reservation date/time.');
                return;
            }
            const selectedYear = parseInt(String(newVal).slice(0, 4), 10);
            if (selectedYear !== new Date().getFullYear()) {
                alert('Reservation date must be in the current year.');
                return;
            }

            const btnSave = document.getElementById(`resDateSave_${id}`);
            const btnCancel = document.getElementById(`resDateCancel_${id}`);
            if (btnSave) btnSave.disabled = true;
            if (btnCancel) btnCancel.disabled = true;

            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('reservation_date', newVal); // accepts datetime-local

                const resp = await fetch('update_reservation_date.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await resp.json();
                if (!result.success) {
                    alert('Failed to update reservation date: ' + (result.message || 'Unknown error'));
                    return;
                }
                // Refresh list to show latest formatted date
                await loadReservations();
            } catch (e) {
                console.error(e);
                alert('Error updating reservation date.');
            } finally {
                if (btnSave) btnSave.disabled = false;
                if (btnCancel) btnCancel.disabled = false;
            }
        }

        function cancelEditReservationDate(id) {
            const actionWrap = document.getElementById(`resDateAction_${id}`);
            const edit = document.getElementById(`resDateEdit_${id}`);
            if (actionWrap) actionWrap.style.display = 'flex';
            if (edit) edit.style.display = 'none';
        }

        function startEditReservationDate(id) {
            const actionWrap = document.getElementById(`resDateAction_${id}`);
            const edit = document.getElementById(`resDateEdit_${id}`);
            const input = document.getElementById(`resDateInput_${id}`);
            if (actionWrap) actionWrap.style.display = 'none';
            if (edit) edit.style.display = 'flex';
            if (input) {
                initResDatePicker(input);
                if (input._flatpickr) input._flatpickr.open();
                else input.focus();
            }
        }

        function formatMoney(val) {
            return '₱' + parseFloat(val || 0).toFixed(2);
        }

        function buildDurationDisplay(row) {
            try {
                const baseDuration = parseInt(row.duration) || 0;
                const unit = (row.duration_unit || 'hours').toLowerCase();
                const extendHours = parseInt(row.extend_hours || 0) || 0;
                const extendMinutes = parseInt(row.extend_minutes || 0) || 0;

                let baseHours = 0;
                if (unit === 'night' || unit === 'nights') {
                    baseHours = baseDuration * 12;
                } else {
                    baseHours = baseDuration;
                }

                if (extendHours > 0 || extendMinutes > 0) {
                    let totalHours = baseHours + extendHours;
                    let minutes = extendMinutes;
                    if (minutes >= 60) {
                        totalHours += Math.floor(minutes / 60);
                        minutes = minutes % 60;
                    }
                    return minutes > 0
                        ? `${totalHours}:${minutes.toString().padStart(2, '0')} Hours (Extended)`
                        : `${totalHours} Hours (Extended)`;
                }

                if (unit === 'night' || unit === 'nights') {
                    return `${baseDuration} Night${baseDuration !== 1 ? 's' : ''}`;
                }
                return baseHours > 0 ? `${baseHours} Hours` : 'N/A';
            } catch (e) {
                return row.duration || 'N/A';
            }
        }

        // ── Downpayment badge/detail ───────────────────────────────────────────
        function buildDownpaymentBadge(row) {
            const dpStatus = row.downpayment_status || 'None';
            let color = '#ff9800';
            if (dpStatus === 'Paid') color = '#4CAF50';
            else if (dpStatus === 'None') color = '#aaa';

            const amount = formatMoney(row.downpayment_amount);
            return `<span style="padding: 3px 10px; background: ${color}; color: white; border-radius: 5px; font-size: 11px; font-weight: 500;">${dpStatus}</span>`;
        }

        // ── Reservation status badge (Normal / Done / Cancel / Rebooked) ─────
        function reservationStatusColor(label) {
            switch ((label || '').toLowerCase()) {
                case 'normal':
                    return '#424242';
                case 'done':
                    return '#424242';
                case 'cancel':
                    return '#424242';
                case 'rebooked':
                    return '#424242';
                default:
                    return '#424242';
            }
        }

        // Map raw booking status / payment / rebooked flag to the 4 UI labels
        function deriveReservationStatus(row) {
            const rawStatus = (row.status || '').toLowerCase();
            const rebookedFlag = String(row.rebooked_flag || '0') === '1';

            // Treat canceled rows as Cancel
            if (rawStatus === 'canceled') {
                return 'Cancel';
            }

            // Checked-out rows (set from the checkout modal) are "Done"
            if (rawStatus === 'checked out') {
                return 'Done';
            }

            // If back-end flagged this reservation as rebooked (and it's not
            // canceled or fully done), show Rebooked.
            if (rebookedFlag) {
                return 'Rebooked';
            }

            // Everything else is considered a normal reservation
            return 'Normal';


        }

        // ── Render table rows ─────────────────────────────────────────────────
        function displayReservations(reservations) {
            // Store all reservations for pagination
            allReservations = reservations || [];
            currentPage = 1; // Reset to first page when new data is loaded
            
            renderCurrentPage();
        }

        // Render current page of reservations
        function renderCurrentPage() {
            const tbody = document.getElementById('reservationTableBody');

            // Check if current user is auditor (with fallback to false if not loaded yet)
            const isAuditor = (window.currentAccessLevel || '').toLowerCase() === 'auditor';
            const isSuperAdmin = window.isSuperAdmin === true;

            if (!allReservations || allReservations.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="13" style="text-align: center; padding: 40px; color: #999;">
                            No reservation records found.
                        </td>
                    </tr>`;
                updatePaginationControls();
                return;
            }

            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pageItems = allReservations.slice(startIndex, endIndex);

            tbody.innerHTML = pageItems.map(row => {
                const uiStatus = deriveReservationStatus(row);
                const sColor = reservationStatusColor(uiStatus);
                const durationDisplay = buildDurationDisplay(row);
                const dpBadge = buildDownpaymentBadge(row);

                // Disable Edit/Cancel actions when reservation is Done or Cancel OR when user is auditor
                const isActionDisabled = (uiStatus === 'Done' || uiStatus === 'Cancel' || isAuditor);
                const disabledAttr = isActionDisabled ? 'disabled' : '';
                const disabledCursor = isActionDisabled ? 'cursor:not-allowed; opacity:0.6;' : 'cursor:pointer;';
                const buttonTitle = isAuditor ? 'Auditor - View Only' : (uiStatus === 'Done' || uiStatus === 'Cancel' ? 'Action not available' : '');

                // Determine check-out display: if status is "Done" and check_out is N/A, use check_out_at
                let checkOutDisplay = formatDate(row.check_out);
                if (uiStatus === 'Done' && (!row.check_out || row.check_out === 'N/A') && row.check_out_at) {
                    checkOutDisplay = formatDate(row.check_out_at);
                }

                return `
                    <tr>
                        <td>${row.booking_id || 'N/A'}</td>
                        <td>${row.guest_name || 'N/A'}</td>
                        <td>${row.room_type || 'N/A'}</td>
                        <td>${row.room_id || 'N/A'}</td>
                        <td class="reservation-date-cell">
                            <div id="resDateWrap_${row.id}">
                                <span>${formatTableDateTime(row.reservation_date)}</span>
                            </div>
                        </td>
                        <td>${formatDate(row.check_in)}</td>
                        <td>${checkOutDisplay}</td>
                        <td>${durationDisplay}</td>
                        <td>${dpBadge}</td>
                        <td class="reservation-date-cell">${formatTableDateTime(row.downpayment_date)}</td>
                        <td>${formatMoney(row.total_amount_reservation)}</td>
                        <td>
                            <span style="padding: 4px 10px; background: ${sColor}; color: white; border-radius: 5px; font-size: 11px; font-weight: 500;">
                                ${uiStatus}
                            </span>
                        </td>
                        <td style="text-align: center;">
                           <div id="resDateAction_${row.id}" class="reservation-action-btns">
                                <button type="button"
                                    onclick="!(${isActionDisabled}) && startEditReservationDate(${row.id})"
                                    title="${buttonTitle || 'Edit reservation date'}"
                                    ${disabledAttr}
                                    style="border:1px solid #e5e7eb;background: #000;border-radius:6px;padding:4px 12px;${disabledCursor}font-size:12px; color: #fff;">
                                    Edit
                                </button>
                                ${isSuperAdmin ? `
                                <button type="button"
                                    onclick="!(${isActionDisabled}) && openReservationDetailsModal(${row.id})"
                                    title="${buttonTitle || 'Edit reservation details (Super Admin)'}"
                                    ${disabledAttr}
                                    style="border:1px solid #e5e7eb;background:#000;border-radius:6px;padding:4px 10px;${disabledCursor}font-size:12px;color:#fff;">
                                    Details
                                </button>` : ''}
                                <button type="button"
                                    onclick="!(${isActionDisabled}) && cancelReservation(${row.id})"
                                    title="${buttonTitle || 'Cancel this reservation'}"
                                    ${disabledAttr}
                                    style="background:#C10101;border-radius:6px;padding:4px 10px;${disabledCursor}font-size:12px;color:#fff; border:1px solid #e5e7eb;">
                                    Cancel
                                </button>
                            </div>
                            <div id="resDateEdit_${row.id}" class="res-date-edit">
                                <input id="resDateInput_${row.id}" type="text" class="res-date-input"
                                    placeholder="mm/dd/yyyy --:-- --" autocomplete="off"
                                    value="${toDatetimeLocalValue(row.reservation_date)}">
                                <div class="res-date-edit-actions">
                                    <button id="resDateSave_${row.id}" type="button" class="res-date-save-btn"
                                        onclick="saveReservationDate(${row.id})">Save</button>
                                    <button id="resDateCancel_${row.id}" type="button" class="res-date-cancel-btn"
                                        onclick="cancelEditReservationDate(${row.id})">Cancel</button>
                                </div>
                            </div>
                        </td>
                    </tr>`;
            }).join('');
            
            updatePaginationControls();
        }

        // Update pagination controls
        function updatePaginationControls() {
            const totalPages = Math.ceil(allReservations.length / itemsPerPage);
            const paginationInfo = document.getElementById('paginationInfo');
            const prevBtn = document.getElementById('prevPageBtn');
            const nextBtn = document.getElementById('nextPageBtn');
            
            if (paginationInfo) {
                if (allReservations.length === 0) {
                    paginationInfo.innerHTML = 'No records';
                } else {
                    paginationInfo.innerHTML = `Page ${currentPage} of ${totalPages}<br><span style="font-size: 12px; color: #64748b;">(${allReservations.length} Records)</span>`;
                }
            }
            
            if (prevBtn) {
                prevBtn.disabled = currentPage === 1;
            }
            
            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages || allReservations.length === 0;
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
            const totalPages = Math.ceil(allReservations.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                renderCurrentPage();
            }
        }

        // ── Super Admin: edit reservation details modal ───────────────────────
        let currentReservationDetails = null;
        let reservationRoomsCache = null;
        let reservationRoomsLoadPromise = null;

        async function loadReservationRoomsIfNeeded() {
            if (reservationRoomsCache) {
                return reservationRoomsCache;
            }
            if (reservationRoomsLoadPromise) {
                return reservationRoomsLoadPromise;
            }

            reservationRoomsLoadPromise = fetch('get_rooms.php')
                .then(resp => resp.json())
                .then(result => {
                    if (!result.success || !Array.isArray(result.rooms)) {
                        throw new Error(result.message || 'Failed to load rooms');
                    }
                    reservationRoomsCache = result.rooms;
                    return reservationRoomsCache;
                })
                .catch(err => {
                    reservationRoomsLoadPromise = null;
                    throw err;
                });

            return reservationRoomsLoadPromise;
        }

        function getReservationRoomsGrouped() {
            const grouped = {};
            (reservationRoomsCache || []).forEach(room => {
                const roomType = (room.room_type || 'Other').trim();
                const roomId = String(room.room_id || '').trim();
                if (!roomId) return;
                if (!grouped[roomType]) grouped[roomType] = [];
                grouped[roomType].push({
                    room_id: roomId,
                    status: room.status || 'Available'
                });
            });

            Object.keys(grouped).forEach(type => {
                grouped[type].sort((a, b) => {
                    const numA = parseInt(String(a.room_id).replace(/\D/g, ''), 10) || 0;
                    const numB = parseInt(String(b.room_id).replace(/\D/g, ''), 10) || 0;
                    if (numA === numB) return String(a.room_id).localeCompare(String(b.room_id));
                    return numA - numB;
                });
            });

            return grouped;
        }

        function populateReservationRoomTypeSelect(selectedType) {
            const typeSelect = document.getElementById('reservationModalRoomType');
            if (!typeSelect) return;

            const grouped = getReservationRoomsGrouped();
            const types = Object.keys(grouped).sort((a, b) => a.localeCompare(b));

            typeSelect.innerHTML = '<option value="">Select room type</option>';
            types.forEach(type => {
                const opt = document.createElement('option');
                opt.value = type;
                opt.textContent = type;
                typeSelect.appendChild(opt);
            });

            if (selectedType && types.includes(selectedType)) {
                typeSelect.value = selectedType;
            } else if (selectedType) {
                const opt = document.createElement('option');
                opt.value = selectedType;
                opt.textContent = selectedType;
                typeSelect.appendChild(opt);
                typeSelect.value = selectedType;
            }
        }

        function toggleReservationRoomDropdown() {
            const list = document.getElementById('reservationRoomDropdownList');
            if (!list) return;
            list.style.display = list.style.display === 'none' ? 'block' : 'none';
        }

        function closeReservationRoomDropdown() {
            const list = document.getElementById('reservationRoomDropdownList');
            if (list) list.style.display = 'none';
        }

        function selectReservationRoomOption(roomId, roomType) {
            const hiddenId = document.getElementById('reservationModalRoomId');
            const selectedText = document.getElementById('reservationRoomSelectedText');
            const typeSelect = document.getElementById('reservationModalRoomType');

            if (hiddenId) hiddenId.value = roomId;
            if (selectedText) selectedText.textContent = roomId || 'Select room ID';
            if (typeSelect && roomType) typeSelect.value = roomType;
            closeReservationRoomDropdown();

            const unit = document.getElementById('reservationModalDurationUnit')?.value || 'hours';
            populateReservationDurationDropdown(roomType || typeSelect?.value || '', roomId, 0, unit);
        }

        function getReservationRoomRecord(roomType, roomId) {
            if (!reservationRoomsCache || !roomId) return null;
            const rid = String(roomId).trim();
            const rtype = (roomType || '').trim();
            return reservationRoomsCache.find(r =>
                String(r.room_id || '').trim() === rid &&
                (!rtype || String(r.room_type || '').trim() === rtype)
            ) || reservationRoomsCache.find(r => String(r.room_id || '').trim() === rid);
        }

        function normalizeReservationDurationUnit(unit) {
            const u = (unit || 'hours').toLowerCase();
            return (u === 'night' || u === 'nights') ? 'night' : 'hours';
        }

        function populateReservationDurationDropdown(roomType, roomId, selectedDuration, durationUnit) {
            const select = document.getElementById('reservationModalDuration');
            const unitSelect = document.getElementById('reservationModalDurationUnit');
            if (!select) return;

            const unit = normalizeReservationDurationUnit(durationUnit || unitSelect?.value);
            if (unitSelect) unitSelect.value = unit;

            select.innerHTML = '<option value="0">Select Duration</option>';

            const room = getReservationRoomRecord(roomType, roomId);
            const durations = (room?.durations || []).slice().sort((a, b) =>
                parseFloat(a.duration_hours ?? a.hours) - parseFloat(b.duration_hours ?? b.hours)
            );

            const selectedVal = parseFloat(selectedDuration) || 0;
            let found = false;

            if (unit === 'night') {
                durations.forEach(d => {
                    const hours = parseFloat(d.duration_hours ?? d.hours);
                    const price = parseFloat(d.price);
                    if (!hours || hours % 12 !== 0 || !(price >= 0)) return;
                    const nights = hours / 12;
                    const option = document.createElement('option');
                    option.value = nights;
                    option.dataset.price = price;
                    option.dataset.hours = hours;
                    option.textContent = `${nights} Night${nights !== 1 ? 's' : ''} - ₱${price.toFixed(2)}`;
                    if (selectedVal === nights) {
                        option.selected = true;
                        found = true;
                    }
                    select.appendChild(option);
                });
            } else {
                durations.forEach(d => {
                    const hours = parseFloat(d.duration_hours ?? d.hours);
                    const price = parseFloat(d.price);
                    if (!hours || !(price >= 0)) return;
                    const option = document.createElement('option');
                    option.value = hours;
                    option.dataset.price = price;
                    option.textContent = `${hours} Hrs - ₱${price.toFixed(2)}`;
                    if (selectedVal === hours) {
                        option.selected = true;
                        found = true;
                    }
                    select.appendChild(option);
                });
            }

            if (!found && selectedVal > 0) {
                const option = document.createElement('option');
                option.value = selectedVal;
                if (unit === 'night') {
                    option.textContent = `${selectedVal} Night${selectedVal !== 1 ? 's' : ''} (Custom)`;
                } else {
                    option.textContent = `${selectedVal} Hrs (Custom)`;
                }
                option.selected = true;
                select.appendChild(option);
            }
        }

        function onReservationDurationUnitChange() {
            const roomType = document.getElementById('reservationModalRoomType')?.value || '';
            const roomId = document.getElementById('reservationModalRoomId')?.value || '';
            const unit = document.getElementById('reservationModalDurationUnit')?.value || 'hours';
            populateReservationDurationDropdown(roomType, roomId, 0, unit);
        }

        function formatDownpaymentDateDisplay(dt) {
            if (!dt) return 'N/A';
            const d = new Date(dt);
            if (isNaN(d.getTime())) return 'N/A';
            const dateStr = d.toLocaleDateString();
            const timeStr = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            return `${dateStr} ${timeStr}`;
        }

        function buildReservationRoomIdDropdown(roomType, selectedRoomId) {
            const listEl = document.getElementById('reservationRoomDropdownList');
            const selectedText = document.getElementById('reservationRoomSelectedText');
            const hiddenId = document.getElementById('reservationModalRoomId');
            if (!listEl || !selectedText || !hiddenId) return;

            listEl.innerHTML = '';
            const grouped = getReservationRoomsGrouped();
            const typesToShow = roomType ? [roomType] : Object.keys(grouped).sort((a, b) => a.localeCompare(b));

            if (!typesToShow.length) {
                selectedText.textContent = 'No rooms available';
                hiddenId.value = '';
                return;
            }

            typesToShow.forEach(type => {
                const rooms = grouped[type] || [];
                if (!rooms.length) return;

                const groupTitle = document.createElement('div');
                groupTitle.className = 'reservation-room-group-title';
                groupTitle.textContent = type;

                const roomsContainer = document.createElement('div');
                roomsContainer.style.display = roomType ? 'block' : 'none';
                roomsContainer.classList.add('reservation-rooms-container');

                rooms.forEach(room => {
                    const item = document.createElement('div');
                    item.className = 'reservation-room-group-item';
                    item.innerHTML = `${room.room_id}<small>${room.status}</small>`;
                    item.onclick = function (e) {
                        e.stopPropagation();
                        selectReservationRoomOption(room.room_id, type);
                    };
                    roomsContainer.appendChild(item);
                });

                if (!roomType) {
                    groupTitle.onclick = function (e) {
                        e.stopPropagation();
                        const isExpanded = roomsContainer.style.display === 'block';
                        roomsContainer.style.display = isExpanded ? 'none' : 'block';
                    };
                }

                listEl.appendChild(groupTitle);
                listEl.appendChild(roomsContainer);
            });

            if (selectedRoomId) {
                hiddenId.value = selectedRoomId;
                selectedText.textContent = selectedRoomId;
            } else {
                hiddenId.value = '';
                selectedText.textContent = 'Select room ID';
            }
        }

        function onReservationRoomTypeChange() {
            const roomType = document.getElementById('reservationModalRoomType')?.value || '';
            buildReservationRoomIdDropdown(roomType, '');
            closeReservationRoomDropdown();
            const unit = document.getElementById('reservationModalDurationUnit')?.value || 'hours';
            populateReservationDurationDropdown(roomType, '', 0, unit);
        }

        function toReservationDatetimeLocal(dt) {
            if (!dt) return '';
            const d = new Date(dt);
            if (isNaN(d.getTime())) return '';
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const hh = String(d.getHours()).padStart(2, '0');
            const mi = String(d.getMinutes()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
        }

        async function openReservationDetailsModal(id) {
            if (!window.isSuperAdmin) {
                alert('Only Super Admin can edit reservation details.');
                return;
            }

            try {
                await loadReservationRoomsIfNeeded();
                const resp = await fetch(`get_reservation_details.php?id=${encodeURIComponent(id)}`);
                const result = await resp.json();
                if (!result.success || !result.data) {
                    alert(result.message || 'Failed to load reservation details');
                    return;
                }
                showReservationDetailsModal(result.data);
            } catch (e) {
                console.error(e);
                alert('Error loading reservation details');
            }
        }

        function showReservationDetailsModal(data) {
            currentReservationDetails = data;
            document.getElementById('reservationEditId').value = data.id || '';
            document.getElementById('reservationModalBookingId').textContent = data.booking_id || '-';
            populateReservationRoomTypeSelect(data.room_type || '');
            buildReservationRoomIdDropdown(data.room_type || '', data.room_id || '');
            document.getElementById('reservationModalReservationDate').value = toReservationDatetimeLocal(data.reservation_date);
            document.getElementById('reservationModalGuestName').value = data.guest_name || '';
            document.getElementById('reservationModalGuestType').value = data.guest_type || 'Solo';
            document.getElementById('reservationModalContactNo').value = data.contact_no || '';
            document.getElementById('reservationModalAddress').value = data.address || '';
            document.getElementById('reservationModalReasonForStay').value = data.reason_for_stay || '';
            document.getElementById('reservationModalRequest').value = data.request || '';
            document.getElementById('reservationModalReferral').value = data.referral_name || '';
            document.getElementById('reservationModalCheckIn').value = toReservationDatetimeLocal(data.check_in);
            document.getElementById('reservationModalCheckOut').value = toReservationDatetimeLocal(data.check_out);
            const durationUnit = normalizeReservationDurationUnit(data.duration_unit);
            document.getElementById('reservationModalDurationUnit').value = durationUnit;
            populateReservationDurationDropdown(
                data.room_type || '',
                data.room_id || '',
                data.duration || 0,
                durationUnit
            );
            document.getElementById('reservationModalDownpaymentDate').textContent =
                formatDownpaymentDateDisplay(data.downpayment_date);
            document.getElementById('reservationModalPromo').value = data.promo || '';
            document.getElementById('reservationModalDownpaymentCash').value = parseFloat(data.downpayment_cash || 0).toFixed(2);
            document.getElementById('reservationModalDownpaymentGcash').value = parseFloat(data.downpayment_gcash || 0).toFixed(2);
            document.getElementById('reservationModalDownpaymentMaya').value = parseFloat(data.downpayment_maya || 0).toFixed(2);
            document.getElementById('reservationModalDownpaymentInstapay').value = parseFloat(data.downpayment_instapay || 0).toFixed(2);
            document.getElementById('reservationModalDownpaymentOnlineBanking').value = parseFloat(data.downpayment_online_banking || 0).toFixed(2);
            document.getElementById('reservationModalDownpaymentAirbnb').value = parseFloat(data.downpayment_airbnb || 0).toFixed(2);
            document.getElementById('reservationModalDownpaymentGcashRef').value = data.downpayment_gcash_ref || '';
            document.getElementById('reservationModalDownpaymentMayaRef').value = data.downpayment_maya_ref || '';
            document.getElementById('reservationModalDownpaymentInstapayRef').value = data.downpayment_instapay_ref || '';
            document.getElementById('reservationModalDownpaymentOnlineBankingRef').value = data.downpayment_online_banking_ref || '';
            document.getElementById('reservationModalDownpaymentAirbnbRef').value = data.downpayment_airbnb_ref || '';
            document.getElementById('reservationModalTotalAmount').value = parseFloat(data.total_amount_reservation || 0).toFixed(2);

            const reasonField = document.getElementById('reservationModalModificationReason');
            const originalReason = data.modification_reason || '';
            reasonField.value = originalReason;
            reasonField.setAttribute('data-original-value', originalReason);

            document.getElementById('reservationDetailsModal').style.display = 'block';
        }

        function closeReservationDetailsModal() {
            document.getElementById('reservationDetailsModal').style.display = 'none';
            closeReservationRoomDropdown();
            currentReservationDetails = null;
        }

        async function saveReservationDetails() {
            if (!window.isSuperAdmin) {
                alert('Only Super Admin can edit reservation details.');
                return;
            }

            const reasonField = document.getElementById('reservationModalModificationReason');
            const modificationReason = reasonField.value.trim();
            const originalReason = reasonField.getAttribute('data-original-value') || '';
            if (!originalReason && !modificationReason) {
                alert('Modification reason is required.');
                reasonField.focus();
                return;
            }

            const payload = {
                id: document.getElementById('reservationEditId').value,
                modification_reason: modificationReason,
                room_type: (document.getElementById('reservationModalRoomType')?.value || '').trim(),
                room_id: (document.getElementById('reservationModalRoomId')?.value || '').trim(),
                reservation_date: document.getElementById('reservationModalReservationDate').value,
                guest_name: document.getElementById('reservationModalGuestName').value.trim(),
                guest_type: document.getElementById('reservationModalGuestType').value,
                contact_no: document.getElementById('reservationModalContactNo').value.trim(),
                address: document.getElementById('reservationModalAddress').value.trim(),
                reason_for_stay: document.getElementById('reservationModalReasonForStay').value.trim(),
                request: document.getElementById('reservationModalRequest').value.trim(),
                referral_name: document.getElementById('reservationModalReferral').value.trim(),
                check_in: document.getElementById('reservationModalCheckIn').value,
                check_out: document.getElementById('reservationModalCheckOut').value,
                duration: parseFloat(document.getElementById('reservationModalDuration').value) || 0,
                duration_unit: normalizeReservationDurationUnit(
                    document.getElementById('reservationModalDurationUnit').value
                ),
                promo: document.getElementById('reservationModalPromo').value.trim(),
                downpayment_cash: parseFloat(document.getElementById('reservationModalDownpaymentCash').value) || 0,
                downpayment_gcash: parseFloat(document.getElementById('reservationModalDownpaymentGcash').value) || 0,
                downpayment_maya: parseFloat(document.getElementById('reservationModalDownpaymentMaya').value) || 0,
                downpayment_instapay: parseFloat(document.getElementById('reservationModalDownpaymentInstapay').value) || 0,
                downpayment_online_banking: parseFloat(document.getElementById('reservationModalDownpaymentOnlineBanking').value) || 0,
                downpayment_airbnb: parseFloat(document.getElementById('reservationModalDownpaymentAirbnb').value) || 0,
                downpayment_gcash_ref: document.getElementById('reservationModalDownpaymentGcashRef').value.trim(),
                downpayment_maya_ref: document.getElementById('reservationModalDownpaymentMayaRef').value.trim(),
                downpayment_instapay_ref: document.getElementById('reservationModalDownpaymentInstapayRef').value.trim(),
                downpayment_online_banking_ref: document.getElementById('reservationModalDownpaymentOnlineBankingRef').value.trim(),
                downpayment_airbnb_ref: document.getElementById('reservationModalDownpaymentAirbnbRef').value.trim(),
                total_amount_reservation: parseFloat(document.getElementById('reservationModalTotalAmount').value) || 0
            };

            if (!payload.guest_name) {
                alert('Guest name is required.');
                return;
            }
            if (!payload.room_type || !payload.room_id) {
                alert('Room type and room ID are required.');
                return;
            }
            if (!payload.reservation_date) {
                alert('Reservation date is required.');
                return;
            }
            if (!payload.duration || payload.duration <= 0) {
                alert('Please select a duration.');
                return;
            }

            const saveBtn = document.getElementById('reservationModalSaveBtn');
            if (saveBtn) saveBtn.disabled = true;

            try {
                const resp = await fetch('update_reservation_details.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await resp.json();
                if (!result.success) {
                    alert(result.message || 'Failed to update reservation details');
                    return;
                }
                alert(result.message || 'Reservation details updated successfully');
                closeReservationDetailsModal();
                await loadReservations();
            } catch (e) {
                console.error(e);
                alert('Error updating reservation details');
            } finally {
                if (saveBtn) saveBtn.disabled = false;
            }
        }

        // ── Cancel reservation (Normal → Cancel) ──────────────────────────────
        async function cancelReservation(id) {
            if (!id) return;
            if (!confirm('Are you sure you want to cancel this reservation?')) {
                return;
            }

            try {
                // Optimistically disable action buttons for this row after user confirms
                const actionWrap = document.getElementById(`resDateAction_${id}`);
                if (actionWrap) {
                    const buttons = actionWrap.querySelectorAll('button');
                    buttons.forEach(btn => {
                        btn.disabled = true;
                        btn.style.cursor = 'not-allowed';
                        btn.style.opacity = '0.6';
                    });
                }

                const formData = new FormData();
                formData.append('id', id);

                const resp = await fetch('cancel_reservation.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await resp.json();
                if (!result.success) {
                    alert('Failed to cancel reservation: ' + (result.message || 'Unknown error'));
                    // Re-enable buttons if cancellation failed
                    if (actionWrap) {
                        const buttons = actionWrap.querySelectorAll('button');
                        buttons.forEach(btn => {
                            btn.disabled = false;
                            btn.style.cursor = 'pointer';
                            btn.style.opacity = '1';
                        });
                    }
                    return;
                }
                alert(result.message || 'Reservation canceled successfully');
                await loadReservations();
            } catch (e) {
                console.error('Error canceling reservation:', e);
                alert('Error canceling reservation');
            }
        }

        // ── User profile (using PHP session from header) ───────────────────────
        // Access level is set from PHP session
        window.currentAccessLevel = '<?php echo strtolower($_SESSION['access_level'] ?? 'user'); ?>';
        window.isSuperAdmin = window.currentAccessLevel === 'super_admin';

        // ── Init ──────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', async function () {
            // Load reservations
            loadReservations();

            // Allow pressing Enter in search input
            const searchInput = document.getElementById('bookingIdInput');
            if (searchInput) {
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') searchReservation();
                }); 
            }

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
    <div class="split-container">
        <!-- Global Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Right Panel -->
        <div class="right-panel">
            <!-- Global Header -->
            <?php include 'includes/header.php'; ?>

            <div class="content-container">
                <h2 class="header-title">Reservation List</h2>

                <!-- Search & Filter -->
                <div style="margin-top: 20px; margin-bottom: 0px;">
                    <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                        <!-- Date Filter -->


                        <div style="min-width: 180px; max-width: 250px;">
                            <input type="text" id="bookingIdInput" placeholder="Enter Booking ID or Name"
                                style="width: 100%; padding: 0 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 11px; box-sizing: border-box; height: 32px; outline: none;">
                        </div>

                        <button onclick="searchReservation()"
                            style="padding: 0 20px; background: #000000; color: #fff; border: none; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 11px; font-weight: 600; cursor: pointer; height: 32px; white-space: nowrap;">
                            Search
                        </button>

                        <div class="date-filter-container">
                            <input type="date" id="dateFilterInput" class="date-filter-input"
                                title="Filter by reservation date">
                            <button class="date-filter-clear" id="dateClearBtn" title="Clear date"
                                onclick="clearDateFilter()">&#x2715;</button>
                        </div>

                        <!-- Status dropdown -->
                        <div class="types-dropdown-wrapper">
                            <button class="types-dropdown-btn" onclick="toggleStatusDropdown()">
                                <span id="selectedStatus">All Status</span>
                                <span class="types-arrow">&#9662;</span>
                            </button>
                            <div id="statusDropdown" class="types-dropdown-menu" style="display: none;">
                                <div class="types-dropdown-item" onclick="selectStatus('All Status')">All Status</div>
                                <div class="types-dropdown-item" onclick="selectStatus('Reserved')">Reserved</div>
                                <div class="types-dropdown-item" onclick="selectStatus('Confirmed')">Confirmed</div>
                                <div class="types-dropdown-item" onclick="selectStatus('Confirming')">Confirming</div>
                                <div class="types-dropdown-item" onclick="selectStatus('Checked In')">Checked In</div>
                                <div class="types-dropdown-item" onclick="selectStatus('Checked Out')">Checked Out</div>
                                <div class="types-dropdown-item" onclick="selectStatus('Canceled')">Canceled</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reservation Table -->
                <div class="table-wrapper" style="margin-top: 0px; overflow-x: auto;">
                    <table class="booking-table reservation-list-table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Guest Name</th>
                                <th>Room Type</th>
                                <th>Room ID</th>
                                <th>Reservation Date</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Duration</th>
                                <th>Downpayment</th>
                                <th>Payment Date &amp; Time</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="reservationTableBody">
                            <tr>
                                <td colspan="13" style="text-align: center; padding: 40px; color: #999;">
                                    Loading reservations...
                                </td>
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

    <!-- Super Admin: Edit Reservation Details Modal -->
    <div id="reservationDetailsModal" class="reservation-modal">
        <div class="reservation-modal-content">
            <div class="reservation-modal-header">
                <h2>Reservation Details</h2>
                <span class="reservation-modal-close" onclick="closeReservationDetailsModal()">&times;</span>
            </div>
            <div class="reservation-modal-body">
                <input type="hidden" id="reservationEditId">

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Booking ID</span>
                        <span id="reservationModalBookingId" style="font-size:14px;color:#212121;">-</span>
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Reservation Date</span>
                        <input type="datetime-local" class="reservation-detail-input" id="reservationModalReservationDate">
                    </div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Room Type</span>
                        <select class="reservation-detail-input" id="reservationModalRoomType" onchange="onReservationRoomTypeChange()">
                            <option value="">Select room type</option>
                        </select>
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Room ID</span>
                        <div class="reservation-room-dropdown" id="reservationRoomDropdownContainer">
                            <div id="reservationRoomCustomSelectBtn" class="reservation-room-dropdown-btn" onclick="toggleReservationRoomDropdown()">
                                <span id="reservationRoomSelectedText">Select room ID</span>
                                <span style="font-size:10px;color:#666;">▼</span>
                            </div>
                            <div id="reservationRoomDropdownList" class="reservation-room-dropdown-list"></div>
                            <input type="hidden" id="reservationModalRoomId" value="">
                        </div>
                    </div>
                </div>

                <div class="reservation-detail-row full-width">
                    <div class="reservation-detail-item full-width">
                        <span class="reservation-detail-label">Modification Reason <span style="color:red;">*</span></span>
                        <textarea class="reservation-detail-input" id="reservationModalModificationReason" rows="2"
                            placeholder="Enter reason for this reservation edit" style="resize:vertical;min-height:50px;"></textarea>
                    </div>
                </div>

                <div class="reservation-detail-divider"></div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Guest Name</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalGuestName">
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Type of Guest</span>
                        <select class="reservation-detail-input" id="reservationModalGuestType">
                            <option value="Solo">Solo</option>
                            <option value="Duo">Duo</option>
                            <option value="Family">Family</option>
                            <option value="Group">Group</option>
                            <option value="Company">Company</option>
                        </select>
                    </div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Contact No.</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalContactNo">
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Address</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalAddress">
                    </div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Reason for Stay</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalReasonForStay">
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Request</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalRequest">
                    </div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Check-In</span>
                        <input type="datetime-local" class="reservation-detail-input" id="reservationModalCheckIn">
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Check-Out</span>
                        <input type="datetime-local" class="reservation-detail-input" id="reservationModalCheckOut">
                    </div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Duration</span>
                        <select class="reservation-detail-input" id="reservationModalDuration">
                            <option value="0">Select Duration</option>
                        </select>
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Duration Unit</span>
                        <select class="reservation-detail-input" id="reservationModalDurationUnit"
                            onchange="onReservationDurationUnitChange()">
                            <option value="hours">Hours</option>
                            <option value="night">Night</option>
                        </select>
                    </div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Referral Code</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalReferral">
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Promo</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalPromo">
                    </div>
                </div>

                <div class="reservation-detail-divider"></div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Downpayment Date</span>
                        <span id="reservationModalDownpaymentDate" class="reservation-detail-readonly">N/A</span>
                    </div>
                    <div class="reservation-detail-item"></div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Downpayment Cash</span>
                        <input type="number" step="0.01" class="reservation-detail-input" id="reservationModalDownpaymentCash">
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Downpayment G-cash</span>
                        <input type="number" step="0.01" class="reservation-detail-input" id="reservationModalDownpaymentGcash">
                    </div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">G-cash Reference No.</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalDownpaymentGcashRef">
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Downpayment Maya</span>
                        <input type="number" step="0.01" class="reservation-detail-input" id="reservationModalDownpaymentMaya">
                    </div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Maya Reference No.</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalDownpaymentMayaRef">
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Downpayment Instapay</span>
                        <input type="number" step="0.01" class="reservation-detail-input" id="reservationModalDownpaymentInstapay">
                    </div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Instapay Reference No.</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalDownpaymentInstapayRef">
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Downpayment Online Banking</span>
                        <input type="number" step="0.01" class="reservation-detail-input" id="reservationModalDownpaymentOnlineBanking">
                    </div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Online Banking Reference No.</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalDownpaymentOnlineBankingRef">
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Downpayment Airbnb</span>
                        <input type="number" step="0.01" class="reservation-detail-input" id="reservationModalDownpaymentAirbnb">
                    </div>
                </div>

                <div class="reservation-detail-row">
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Airbnb Reference No.</span>
                        <input type="text" class="reservation-detail-input" id="reservationModalDownpaymentAirbnbRef">
                    </div>
                    <div class="reservation-detail-item">
                        <span class="reservation-detail-label">Total Amount (Reservation)</span>
                        <input type="number" step="0.01" class="reservation-detail-input" id="reservationModalTotalAmount">
                    </div>
                </div>
            </div>
            <div class="reservation-modal-footer">
                <button type="button" class="reservation-modal-btn reservation-modal-btn-secondary" onclick="closeReservationDetailsModal()">Close</button>
                <button type="button" id="reservationModalSaveBtn" class="reservation-modal-btn reservation-modal-btn-primary" onclick="saveReservationDetails()">Update</button>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('click', function (event) {
            const modal = document.getElementById('reservationDetailsModal');
            if (event.target === modal) {
                closeReservationDetailsModal();
            }

            const roomDropdown = document.getElementById('reservationRoomDropdownContainer');
            if (roomDropdown && !roomDropdown.contains(event.target)) {
                closeReservationRoomDropdown();
            }
        });
    </script>
</body>

</html>