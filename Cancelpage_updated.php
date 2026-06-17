<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>Cancellation Approval - MoonClave Hotel</title>
    <link rel="stylesheet" href="Booking.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .cancellation-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .search-filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            min-width: 300px;
        }
        
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
        }
        
        .search-btn {
            padding: 10px 20px;
            background: #4a90e2;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
        }
        
        .search-btn:hover {
            background: #357abd;
        }
        
        .status-filters {
            display: flex;
            gap: 10px;
        }
        
        .status-filter-btn {
            padding: 10px 20px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .status-filter-btn.active {
            background: #4a90e2;
            color: white;
            border-color: #4a90e2;
        }
        
        .cancellation-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .cancellation-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .cancellation-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e9ecef;
        }
        
        .cancellation-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .cancellation-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-approve, .btn-reject, .btn-view {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
        }
        
        .btn-approve:hover {
            background: #218838;
        }
        
        .btn-reject {
            background: #dc3545;
            color: white;
        }
        
        .btn-reject:hover {
            background: #c82333;
        }
        
        .btn-view {
            background: #6c757d;
            color: white;
        }
        
        .btn-view:hover {
            background: #5a6268;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 20px;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        
        .detail-value {
            color: #333;
        }
        
        .admin-notes-section {
            margin-top: 20px;
        }
        
        .admin-notes-section textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            resize: vertical;
            min-height: 80px;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
    <div class="cancellation-container">
        <div class="page-header">
            <h1>Cancellation Approval</h1>
        </div>
        
        <div class="search-filter-section">
            <div class="search-box">
                <input type="text" id="bookingIdInput" class="search-input" placeholder="Enter Booking ID to search...">
                <button class="search-btn" onclick="searchCancellations()">Search</button>
            </div>
            
            <div class="status-filters">
                <button class="status-filter-btn active" onclick="filterByStatus('all')">All Status</button>
                <button class="status-filter-btn" onclick="filterByStatus('Pending')">Pending</button>
                <button class="status-filter-btn" onclick="filterByStatus('Approved')">Approved</button>
                <button class="status-filter-btn" onclick="filterByStatus('Rejected')">Rejected</button>
            </div>
        </div>
        
        <div class="cancellation-table">
            <table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Guest Name</th>
                        <th>Room Type</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Duration</th>
                        <th>Amount</th>
                        <th>Refund Amount</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="cancellationTableBody">
                    <tr>
                        <td colspan="11" class="no-data">No cancellation requests found. Enter a Booking ID to search.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- View/Action Modal -->
    <div id="cancellationModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cancellation Request Details</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <div id="modalDetails"></div>
            
            <div class="admin-notes-section" id="adminNotesSection">
                <label for="adminNotes" style="display: block; margin-bottom: 8px; font-weight: 600;">Admin Notes:</label>
                <textarea id="adminNotes" placeholder="Enter notes (optional)..."></textarea>
            </div>
            
            <div class="modal-actions" id="modalActions"></div>
        </div>
    </div>
    
    <script>
        let currentFilter = 'all';
        let currentCancellation = null;
        
        // Load cancellations on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCancellations();
        });
        
        function loadCancellations() {
            const bookingId = document.getElementById('bookingIdInput').value.trim();
            const url = `get_cancellation_requests.php?status=${currentFilter}${bookingId ? '&booking_id=' + bookingId : ''}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCancellations(data.data);
                    } else {
                        alert('Error loading cancellations: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load cancellations');
                });
        }
        
        function displayCancellations(cancellations) {
            const tbody = document.getElementById('cancellationTableBody');
            
            if (cancellations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="no-data">No cancellation requests found</td></tr>';
                return;
            }
            
            tbody.innerHTML = cancellations.map(c => `
                <tr>
                    <td>${c.booking_reference || c.booking_id}</td>
                    <td>${c.guest_name}</td>
                    <td>${c.room_type}</td>
                    <td>${formatDate(c.check_in)}</td>
                    <td>${formatDate(c.check_out)}</td>
                    <td>${c.duration}</td>
                    <td>₱${parseFloat(c.total_charges).toFixed(2)}</td>
                    <td>₱${parseFloat(c.refund_amount).toFixed(2)}</td>
                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${c.reason}">${c.reason}</td>
                    <td><span class="status-badge status-${c.status.toLowerCase()}">${c.status}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-view" onclick='viewCancellation(${JSON.stringify(c)})'>View</button>
                            ${c.status === 'Pending' ? `
                                <button class="btn-approve" onclick="approveCancellation(${c.id})">Approve</button>
                                <button class="btn-reject" onclick="rejectCancellation(${c.id})">Reject</button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
        }
        
        function viewCancellation(cancellation) {
            currentCancellation = cancellation;
            const modal = document.getElementById('cancellationModal');
            const details = document.getElementById('modalDetails');
            const actions = document.getElementById('modalActions');
            const notesSection = document.getElementById('adminNotesSection');
            
            details.innerHTML = `
                <div class="detail-row">
                    <span class="detail-label">Booking ID:</span>
                    <span class="detail-value">${cancellation.booking_reference || cancellation.booking_id}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Guest Name:</span>
                    <span class="detail-value">${cancellation.guest_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Room:</span>
                    <span class="detail-value">#${cancellation.room_number} - ${cancellation.room_type}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Check-in:</span>
                    <span class="detail-value">${formatDate(cancellation.check_in)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Check-out:</span>
                    <span class="detail-value">${formatDate(cancellation.check_out)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span class="detail-value">${cancellation.duration}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Room Rate:</span>
                    <span class="detail-value">₱${parseFloat(cancellation.room_rate).toFixed(2)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Charges:</span>
                    <span class="detail-value">₱${parseFloat(cancellation.total_charges).toFixed(2)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment:</span>
                    <span class="detail-value">-₱${parseFloat(cancellation.payment_amount).toFixed(2)}</span>
                </div>
                ${cancellation.reservation_amount > 0 ? `
                <div class="detail-row">
                    <span class="detail-label">Reservation:</span>
                    <span class="detail-value">-₱${parseFloat(cancellation.reservation_amount).toFixed(2)}</span>
                </div>
                ` : ''}
                <div class="detail-row">
                    <span class="detail-label">Subtotal:</span>
                    <span class="detail-value">₱${parseFloat(cancellation.subtotal).toFixed(2)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Refund Amount:</span>
                    <span class="detail-value" style="color: #28a745; font-weight: 600;">₱${parseFloat(cancellation.refund_amount).toFixed(2)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Reason:</span>
                    <span class="detail-value">${cancellation.reason}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value"><span class="status-badge status-${cancellation.status.toLowerCase()}">${cancellation.status}</span></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Requested By:</span>
                    <span class="detail-value">${cancellation.requested_by}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Requested At:</span>
                    <span class="detail-value">${formatDateTime(cancellation.requested_at)}</span>
                </div>
                ${cancellation.reviewed_by ? `
                <div class="detail-row">
                    <span class="detail-label">Reviewed By:</span>
                    <span class="detail-value">${cancellation.reviewed_by}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Reviewed At:</span>
                    <span class="detail-value">${formatDateTime(cancellation.reviewed_at)}</span>
                </div>
                ` : ''}
                ${cancellation.admin_notes ? `
                <div class="detail-row">
                    <span class="detail-label">Admin Notes:</span>
                    <span class="detail-value">${cancellation.admin_notes}</span>
                </div>
                ` : ''}
            `;
            
            if (cancellation.status === 'Pending') {
                notesSection.style.display = 'block';
                document.getElementById('adminNotes').value = '';
                actions.innerHTML = `
                    <button class="btn-approve" onclick="approveCancellation(${cancellation.id})">Approve</button>
                    <button class="btn-reject" onclick="rejectCancellation(${cancellation.id})">Reject</button>
                    <button class="btn-view" onclick="closeModal()">Close</button>
                `;
            } else {
                notesSection.style.display = 'none';
                actions.innerHTML = `
                    <button class="btn-view" onclick="closeModal()">Close</button>
                `;
            }
            
            modal.style.display = 'flex';
        }
        
        function approveCancellation(cancellationId) {
            if (!confirm('Are you sure you want to APPROVE this cancellation request?')) {
                return;
            }
            
            const adminNotes = document.getElementById('adminNotes').value.trim();
            
            fetch('update_cancellation_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    cancellation_id: cancellationId,
                    status: 'Approved',
                    admin_notes: adminNotes
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal();
                    loadCancellations();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to approve cancellation');
            });
        }
        
        function rejectCancellation(cancellationId) {
            if (!confirm('Are you sure you want to REJECT this cancellation request?')) {
                return;
            }
            
            const adminNotes = document.getElementById('adminNotes').value.trim();
            
            if (!adminNotes) {
                alert('Please provide a reason for rejection in the Admin Notes field');
                return;
            }
            
            fetch('update_cancellation_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    cancellation_id: cancellationId,
                    status: 'Rejected',
                    admin_notes: adminNotes
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal();
                    loadCancellations();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to reject cancellation');
            });
        }
        
        function searchCancellations() {
            loadCancellations();
        }
        
        function filterByStatus(status) {
            currentFilter = status;
            
            // Update button states
            document.querySelectorAll('.status-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            loadCancellations();
        }
        
        function closeModal() {
            document.getElementById('cancellationModal').style.display = 'none';
            currentCancellation = null;
        }
        
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
        }
        
        function formatDateTime(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleString('en-US', { 
                month: '2-digit', 
                day: '2-digit', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Close modal when clicking outside
        document.getElementById('cancellationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
