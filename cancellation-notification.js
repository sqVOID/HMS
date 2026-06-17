/**
 * Cancellation Notification System
 * Displays notification badge for pending cancellation requests
 * Only visible to Admin and Super Admin users
 */

let notificationCheckInterval = null;
let isLoadingNotification = false;

// Load and display cancellation notification count
async function loadCancellationNotification() {
    // Prevent multiple simultaneous requests
    if (isLoadingNotification) {
        return;
    }
    
    isLoadingNotification = true;
    
    try {
        // Add cache-busting parameter
        const timestamp = new Date().getTime();
        const response = await fetch(`get_pending_cancellations_count.php?t=${timestamp}`, {
            method: 'GET',
            cache: 'no-cache',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.count > 0) {
            updateNotificationBadge(data.count);
        } else {
            removeNotificationBadge();
        }
    } catch (error) {
        console.error('Error loading cancellation notifications:', error);
        // Don't remove badge on error - keep existing state
    } finally {
        isLoadingNotification = false;
    }
}

// Update or create notification badge
function updateNotificationBadge(count) {
    const userAvatar = document.getElementById('userAvatar');
    if (!userAvatar) {
        // Retry after a short delay if avatar not found
        setTimeout(() => {
            const retryAvatar = document.getElementById('userAvatar');
            if (retryAvatar) {
                updateNotificationBadge(count);
            }
        }, 500);
        return;
    }
    
    let badge = userAvatar.querySelector('.notification-badge');
    
    if (!badge) {
        badge = document.createElement('div');
        badge.className = 'notification-badge';
        userAvatar.appendChild(badge);
        
        // Make badge clickable to toggle notification panel
        badge.style.cursor = 'pointer';
        badge.onclick = function(e) {
            e.stopPropagation();
            toggleNotificationPanel();
        };
        badge.title = 'Click to view pending cancellation requests';
    }
    
    badge.textContent = count > 99 ? '99+' : count;
    badge.style.display = 'flex'; // Ensure it's visible
}

// Remove notification badge
function removeNotificationBadge() {
    const userAvatar = document.getElementById('userAvatar');
    if (!userAvatar) return;
    
    const badge = userAvatar.querySelector('.notification-badge');
    if (badge) {
        badge.remove();
    }
    
    // Remove notification panel if it exists
    const panel = document.getElementById('notificationPanel');
    if (panel) {
        panel.remove();
    }
}

// Toggle notification panel
function toggleNotificationPanel() {
    let panel = document.getElementById('notificationPanel');
    
    if (panel) {
        // Close panel if already open
        panel.remove();
        return;
    }
    
    // Create and show panel
    createNotificationPanel();
    loadNotificationDetails();
}

// Create notification panel HTML
function createNotificationPanel() {
    const userProfile = document.querySelector('.user-profile');
    if (!userProfile) return;
    
    const panel = document.createElement('div');
    panel.id = 'notificationPanel';
    panel.className = 'notification-panel';
    panel.innerHTML = `
        <div class="notification-header">
            <h3>Notification</h3>
        </div>
        <div class="notification-body" id="notificationBody">
            <div class="notification-loading">Loading...</div>
        </div>
    `;
    
    userProfile.appendChild(panel);
    
    // Close panel when clicking outside
    setTimeout(() => {
        document.addEventListener('click', closeNotificationOnOutsideClick);
    }, 100);
}

// Close notification panel when clicking outside
function closeNotificationOnOutsideClick(e) {
    const panel = document.getElementById('notificationPanel');
    const badge = document.querySelector('.notification-badge');
    
    if (panel && !panel.contains(e.target) && !badge?.contains(e.target)) {
        panel.remove();
        document.removeEventListener('click', closeNotificationOnOutsideClick);
    }
}

// Load notification details
async function loadNotificationDetails() {
    const notificationBody = document.getElementById('notificationBody');
    if (!notificationBody) return;
    
    try {
        const timestamp = new Date().getTime();
        const response = await fetch(`get_cancellation_notifications.php?t=${timestamp}`, {
            method: 'GET',
            cache: 'no-cache',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.notifications.length > 0) {
            displayNotifications(data.notifications);
        } else {
            notificationBody.innerHTML = '<div class="notification-empty">No pending cancellation requests</div>';
        }
    } catch (error) {
        console.error('Error loading notification details:', error);
        notificationBody.innerHTML = '<div class="notification-error">Failed to load notifications</div>';
    }
}

// Display notifications in panel
function displayNotifications(notifications) {
    const notificationBody = document.getElementById('notificationBody');
    if (!notificationBody) return;
    
    notificationBody.innerHTML = notifications.map(notif => {
        // Get first letter of requested_by for avatar
        const avatarLetter = notif.requested_by ? notif.requested_by.charAt(0).toUpperCase() : 'U';
        
        return `
        <div class="notification-item" onclick="navigateToCancellation()">
            <div class="notification-avatar">${avatarLetter}</div>
            <div class="notification-content">
                <div class="notification-text">
                    <strong>${notif.requested_by}</strong> has request a Cancellation
                </div>
                <div class="notification-time">${notif.time_ago}</div>
            </div>
        </div>
    `;
    }).join('');
}

// Navigate to cancellation page
function navigateToCancellation() {
    window.location.href = 'Cancelpage.php';
}

// Start periodic notification check (every 30 seconds)
function startNotificationCheck() {
    // Stop any existing interval
    if (notificationCheckInterval) {
        clearInterval(notificationCheckInterval);
    }
    
    // Initial load with a small delay to ensure DOM is ready
    setTimeout(() => {
        loadCancellationNotification();
    }, 100);
    
    // Check every 30 seconds
    notificationCheckInterval = setInterval(loadCancellationNotification, 30000);
}

// Stop notification check
function stopNotificationCheck() {
    if (notificationCheckInterval) {
        clearInterval(notificationCheckInterval);
        notificationCheckInterval = null;
    }
}

// Initialize on page load - use multiple event listeners for reliability
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startNotificationCheck);
} else {
    // DOM already loaded
    startNotificationCheck();
}

// Also try on window load as backup
window.addEventListener('load', function() {
    // Only start if not already started
    if (!notificationCheckInterval) {
        startNotificationCheck();
    }
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    stopNotificationCheck();
});

// Expose function globally so it can be called from other scripts
window.loadCancellationNotification = loadCancellationNotification;
