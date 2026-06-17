# Cancellation Notification - Quick Start Guide

## For End Users (Admin/Super Admin)

### What You'll See
When there are pending cancellation requests, you'll see a **red badge with a number** on your profile avatar in the top-right corner of every page.

### How to Use
1. **Look for the badge** - Red circle with white number on your avatar
2. **Click your avatar** - This takes you directly to the Cancellation page
3. **Review requests** - See all pending cancellation requests
4. **Take action** - Approve or reject each request
5. **Badge updates** - Count decreases automatically after each action

### Example
```
Before:  R ⓷  (3 pending requests)
After:   R ⓵  (1 pending request after approving 2)
```

## For Developers

### Quick Setup Checklist
✅ All files created and modified
✅ Notification script added to all pages
✅ CSS styles added to all stylesheets
✅ API endpoint created
✅ Database connection configured

### Testing Steps
1. Log in as Admin or Super Admin
2. Open browser console (F12)
3. Navigate to any page (Booking, Dashboard, etc.)
4. Check console for: "Loading cancellation notifications..."
5. Verify badge appears if there are pending requests

### Manual Test
```sql
-- Add a test pending cancellation
INSERT INTO cancellation_requests (booking_id, status, reason, refund_amount, requested_at)
VALUES (1, 'Pending', 'Test reason', 100.00, NOW());
```

### Verify API
Open in browser: `http://your-domain/get_pending_cancellations_count.php`

Expected response:
```json
{
  "success": true,
  "count": 1
}
```

## Configuration

### Change Refresh Interval
Edit `cancellation-notification.js`:
```javascript
// Default: 30 seconds (30000 ms)
notificationCheckInterval = setInterval(loadCancellationNotification, 30000);

// Change to 60 seconds:
notificationCheckInterval = setInterval(loadCancellationNotification, 60000);
```

### Change Badge Color
Edit any CSS file (Booking.css, etc.):
```css
.notification-badge {
    background: #ef4444;  /* Red - change to any color */
}
```

### Disable Pulse Animation
Edit any CSS file:
```css
.notification-badge {
    animation: none;  /* Remove pulse effect */
}
```

## Troubleshooting

### Badge Not Showing
**Problem**: Badge doesn't appear even with pending requests

**Solutions**:
1. Check if logged in as Admin/Super Admin
2. Open browser console for errors
3. Verify script is loaded: Look for `cancellation-notification.js` in Network tab
4. Test API directly: Open `get_pending_cancellations_count.php` in browser
5. Check database: `SELECT * FROM cancellation_requests WHERE status='Pending'`

### Badge Shows Wrong Count
**Problem**: Badge shows incorrect number

**Solutions**:
1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh page (Ctrl+F5)
3. Check database query in `get_pending_cancellations_count.php`
4. Verify status values are exactly 'Pending' (case-sensitive)

### Badge Not Updating
**Problem**: Badge doesn't update after approve/reject

**Solutions**:
1. Check browser console for JavaScript errors
2. Verify `loadCancellationNotification()` is called in approve/reject functions
3. Wait 30 seconds for auto-refresh
4. Manually refresh page

### Click Not Working
**Problem**: Clicking avatar doesn't navigate to Cancelpage.php

**Solutions**:
1. Check if `onclick` handler is attached
2. Verify `Cancelpage.php` exists and is accessible
3. Check browser console for navigation errors
4. Test direct navigation: Type `Cancelpage.php` in address bar

## File Locations

### Core Files
- `get_pending_cancellations_count.php` - API endpoint
- `cancellation-notification.js` - Main script
- `test_notification.html` - Test page

### Modified Files
- All HTML pages in root directory
- All CSS files in root directory
- `Cancelpage.php` - Updated approve/reject functions

## Database Requirements

### Table Structure
```sql
CREATE TABLE IF NOT EXISTS cancellation_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT,
    status VARCHAR(20),  -- 'Pending', 'Approved', 'Rejected'
    reason TEXT,
    refund_amount DECIMAL(10,2),
    requested_at DATETIME,
    -- other columns...
);
```

### Required Data
- At least one row with `status = 'Pending'` to see badge
- Valid session with `access_level = 'admin'` or `'super_admin'`

## Security Notes

### Access Control
- Only Admin and Super Admin see notifications
- Regular users get `count: 0` response
- Access level checked server-side in PHP
- Session-based authentication required

### Best Practices
- Keep session timeout reasonable
- Use HTTPS in production
- Validate all user inputs
- Log all approve/reject actions

## Performance

### Optimization Tips
- 30-second refresh is optimal (not too frequent, not too slow)
- API response is lightweight (< 1KB)
- Minimal DOM manipulation
- No impact on page load speed

### Monitoring
- Check server logs for API call frequency
- Monitor database query performance
- Watch for JavaScript errors in console

## Support

### Getting Help
1. Check documentation: `CANCELLATION_NOTIFICATION_SETUP.md`
2. Review flow diagram: `notification-flow-diagram.txt`
3. Test with: `test_notification.html`
4. Check browser console for errors
5. Verify database connection

### Common Issues
- **No badge**: Not logged in as admin
- **Wrong count**: Database status values incorrect
- **Not updating**: JavaScript errors or API issues
- **Not clickable**: onclick handler not attached

## Next Steps

### After Installation
1. ✅ Test with admin account
2. ✅ Verify badge appears with pending requests
3. ✅ Test click navigation to Cancelpage.php
4. ✅ Test approve/reject updates badge
5. ✅ Monitor for 30 seconds to see auto-refresh

### Optional Enhancements
- Add sound notification
- Implement real-time updates (WebSockets)
- Add desktop notifications
- Create notification history log
- Send email alerts to admins

## Quick Reference

### Key Functions
```javascript
loadCancellationNotification()  // Fetch and display count
updateNotificationBadge(count)  // Create/update badge
removeNotificationBadge()       // Remove badge
startNotificationCheck()        // Start auto-refresh
stopNotificationCheck()         // Stop auto-refresh
```

### Key CSS Classes
```css
.user-profile          // Profile container
.user-avatar           // Avatar circle
.notification-badge    // Red badge
@keyframes pulse       // Pulse animation
```

### Key Files
```
get_pending_cancellations_count.php  // API
cancellation-notification.js         // Script
Booking.css (and others)             // Styles
```

## Success Criteria

✅ Badge appears for admin users with pending requests
✅ Badge shows correct count
✅ Badge updates every 30 seconds
✅ Clicking avatar navigates to Cancelpage.php
✅ Badge updates immediately after approve/reject
✅ No JavaScript errors in console
✅ No impact on page performance

---

**You're all set!** The notification system is ready to use. 🎉
