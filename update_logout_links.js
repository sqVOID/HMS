/**
 * Update Logout Links
 * This script updates all logout links to redirect to LogoutModal.php
 * Add this script to pages that have logout functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Find all elements that trigger logout
    const logoutElements = document.querySelectorAll('[onclick*="logout.php"], [href*="logout.php"]');
    
    logoutElements.forEach(element => {
        // Update onclick attributes
        if (element.hasAttribute('onclick')) {
            const onclickValue = element.getAttribute('onclick');
            element.setAttribute('onclick', onclickValue.replace('logout.php', 'LogoutModal.php'));
        }
        
        // Update href attributes
        if (element.hasAttribute('href')) {
            element.setAttribute('href', 'LogoutModal.php');
        }
        
        // For sidebar menu items with window.location
        element.addEventListener('click', function(e) {
            const onclickAttr = this.getAttribute('onclick');
            if (onclickAttr && onclickAttr.includes('logout.php')) {
                e.preventDefault();
                window.location.href = 'LogoutModal.php';
            }
        });
    });
    
    // Also update any sidebar menu items with data-page="logout"
    const logoutMenuItems = document.querySelectorAll('[data-page*="logout"]');
    logoutMenuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            window.location.href = 'LogoutModal.php';
        });
    });
});
