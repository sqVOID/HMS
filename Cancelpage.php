<?php
require_once 'access_check.php';
checkAccess('Cancelpage.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>Cancellation Approval</title>
    <!-- Global Layout Styles -->
    <link rel="stylesheet" href="includes/global_layout.css">
    <!-- Page-specific Styles -->
    <link rel="stylesheet" href="Booking.css?v=15">
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
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="role-based-menu.js?v=18"></script>
    <script src="auto_logout.js?v=3" defer></script>
    <script src="cancellation-notification.js?v=3" defer></script>
    <!-- Global Sidebar Scripts -->
    <script src="includes/sidebar_scripts.js" defer></script>
    <script>
        // Global variable to store user access level (from PHP session)
        let userAccessLevel = '<?php echo strtolower($_SESSION['access_level'] ?? 'user'); ?>';

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

        // Load cancellation requests on page load
        document.addEventListener('DOMContentLoaded', async function () {
            // Load cancellation requests with correct access level
            loadCancellationRequests();

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
        <?php include 'includes/sidebar.php'; ?>
        <div class="right-panel">
            <?php include 'includes/header.php'; ?>
            <div class="content-container">
                <h2 class="header-title">Cancellation Approval</h2>

                <!-- Search and Filter Section -->
                <div style="margin-top: 20px; margin-bottom: 0px;">
                    <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                        <!-- Date Filter -->

                        <div style="min-width: 180px; max-width: 250px;">
                            <input type="text" id="bookingIdInput" placeholder="Enter Booking ID"
                                style="width: 100%; padding: 0 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 11px; box-sizing: border-box; height: 32px; outline: none;">
                        </div>




                        <button onclick="searchBooking()"
                            style="padding: 0 20px; background: #000000ff; color: #fff; border: none; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 11px; font-weight: 600; cursor: pointer; height: 32px; white-space: nowrap;">
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
                <div class="table-wrapper" style="margin-top: 0px; overflow-x: auto;">
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