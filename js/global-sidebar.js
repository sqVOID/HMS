/**
 * Global Sidebar JavaScript
 * Handles sidebar navigation, collapsing, mobile menu, and role-based visibility
 */

// ============================================
// NAVIGATION
// ============================================
function navigateToPage(page) {
    // Prevent navigation if already on the current page
    const currentPage = window.location.pathname.split('/').pop().toLowerCase();
    const targetPage = page.toLowerCase();
    
    if (currentPage === targetPage) {
        return; // Don't reload if we're already on this page
    }

    // Close sidebar on mobile when navigating
    if (window.innerWidth <= 1024) {
        const panel = document.querySelector('.left-panel');
        if (panel) panel.classList.remove('open');
        
        const overlay = document.querySelector('.mobile-menu-overlay');
        if (overlay) overlay.classList.remove('active');
        
        document.body.classList.remove('sidebar-open');
        document.body.style.overflow = '';
    }
    
    window.location.href = page;
}

// ============================================
// SIDEBAR COLLAPSE (DESKTOP)
// ============================================
function toggleSidebarMinimize() {
    const panel = document.getElementById('leftPanel');
    const icon = document.getElementById('collapseIcon');
    const tooltip = document.getElementById('collapseTooltip');
    
    if (!panel) return;

    if (panel.classList.contains('minimized')) {
        panel.classList.remove('minimized');
        if (icon) icon.src = 'Icon/left-arrow_minimize.svg';
        if (tooltip) tooltip.textContent = 'Minimize';
        localStorage.setItem('sidebarMinimized', 'false');
    } else {
        panel.classList.add('minimized');
        if (icon) icon.src = 'Icon/right-arrow_minimize.svg';
        if (tooltip) tooltip.textContent = 'Expand';
        localStorage.setItem('sidebarMinimized', 'true');
    }
}

// ============================================
// MOBILE SIDEBAR TOGGLE
// ============================================
function toggleSidebar() {
    const panel = document.querySelector('.left-panel');
    const overlay = document.querySelector('.mobile-menu-overlay');

    if (!panel) return;

    const isOpening = !panel.classList.contains('open');
    panel.classList.toggle('open');

    if (overlay) {
        overlay.classList.toggle('active');
    }

    // Prevent body scroll when sidebar is open on mobile
    if (window.innerWidth <= 1024) {
        if (isOpening) {
            document.body.classList.add('sidebar-open');
            document.body.style.overflow = 'hidden';
        } else {
            document.body.classList.remove('sidebar-open');
            document.body.style.overflow = '';
        }
    }
}

// ============================================
// SYSTEM MAINTENANCE SUBMENU
// ============================================
function toggleSystemMaintenance() {
    const submenu = document.getElementById('systemMaintenanceSubmenu');
    const menuItem = document.getElementById('systemMaintenanceMenu');
    
    if (!submenu || !menuItem) return;

    const isOpen = submenu.classList.contains('open');
    
    if (isOpen) {
        submenu.classList.remove('open');
        menuItem.classList.remove('expanded');
        localStorage.setItem('maintenanceMenuOpen', 'false');
    } else {
        submenu.classList.add('open');
        menuItem.classList.add('expanded');
        localStorage.setItem('maintenanceMenuOpen', 'true');
    }
}

// ============================================
// ROLE-BASED MENU VISIBILITY
// ============================================
function initializeRoleBasedMenu() {
    // Get user role from a global variable or data attribute
    const userRole = window.USER_ROLE || document.body.getAttribute('data-user-role') || 'user';
    
    // Find all menu items with role restrictions
    const menuItems = document.querySelectorAll('[data-roles]');
    
    menuItems.forEach(item => {
        const allowedRoles = item.getAttribute('data-roles').split(',').map(r => r.trim());
        
        // Show item if user role is in allowed roles
        if (allowedRoles.includes(userRole) || allowedRoles.includes('*')) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

// ============================================
// ACTIVE PAGE HIGHLIGHTING
// ============================================
function initializeActiveMenu() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const menuItems = document.querySelectorAll('.sidebar-menu-item:not(.collapsible-menu)');
    const submenuItems = document.querySelectorAll('.submenu-item');
    
    // Pages that belong to maintenance submenu
    const maintenancePages = ['inventory.html', 'PurchaseOrder.html', 'AddItem.php'];

    // Check if current page is in maintenance submenu
    if (maintenancePages.includes(currentPage)) {
        const submenu = document.getElementById('systemMaintenanceSubmenu');
        const menuItem = document.getElementById('systemMaintenanceMenu');
        
        if (submenu && menuItem) {
            submenu.classList.add('open');
            menuItem.classList.add('expanded');
        }
        
        // Highlight active submenu item
        submenuItems.forEach(item => {
            const page = item.getAttribute('data-page');
            if (page === currentPage) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    } else {
        // Highlight active main menu item
        menuItems.forEach(item => {
            const page = item.getAttribute('data-page');
            if (page === currentPage) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }
}

// ============================================
// CLOSE SIDEBAR ON OUTSIDE CLICK (MOBILE)
// ============================================
function initializeMobileClickOutside() {
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            const panel = document.querySelector('.left-panel');
            const toggleBtn = document.querySelector('.mobile-menu-toggle');
            
            if (panel && panel.classList.contains('open')) {
                if (!panel.contains(e.target) && !toggleBtn.contains(e.target)) {
                    panel.classList.remove('open');
                    
                    const overlay = document.querySelector('.mobile-menu-overlay');
                    if (overlay) overlay.classList.remove('active');
                    
                    document.body.classList.remove('sidebar-open');
                    document.body.style.overflow = '';
                }
            }
        }
    });
}

// ============================================
// RESTORE SIDEBAR STATE FROM LOCAL STORAGE
// ============================================
function restoreSidebarState() {
    // Restore minimized state (desktop only)
    if (window.innerWidth > 1024) {
        const isMinimized = localStorage.getItem('sidebarMinimized') === 'true';
        if (isMinimized) {
            const panel = document.getElementById('leftPanel');
            const icon = document.getElementById('collapseIcon');
            const tooltip = document.getElementById('collapseTooltip');
            
            if (panel) panel.classList.add('minimized');
            if (icon) icon.src = 'Icon/right-arrow_minimize.svg';
            if (tooltip) tooltip.textContent = 'Expand';
        }
    }
    
    // Restore maintenance menu state
    const maintenanceOpen = localStorage.getItem('maintenanceMenuOpen') === 'true';
    if (maintenanceOpen) {
        const submenu = document.getElementById('systemMaintenanceSubmenu');
        const menuItem = document.getElementById('systemMaintenanceMenu');
        
        if (submenu) submenu.classList.add('open');
        if (menuItem) menuItem.classList.add('expanded');
    }
}

// ============================================
// RESPONSIVE BEHAVIOR
// ============================================
function handleResize() {
    const panel = document.querySelector('.left-panel');
    const overlay = document.querySelector('.mobile-menu-overlay');
    
    if (window.innerWidth > 1024) {
        // Desktop: restore sidebar state
        if (panel) panel.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('sidebar-open');
        document.body.style.overflow = '';
        
        restoreSidebarState();
    } else {
        // Mobile: remove minimized state
        if (panel) panel.classList.remove('minimized');
    }
}

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    initializeActiveMenu();
    initializeRoleBasedMenu();
    initializeMobileClickOutside();
    restoreSidebarState();
});

// Handle window resize
let resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(handleResize, 250);
});

// ============================================
// EXPORT FUNCTIONS FOR EXTERNAL USE
// ============================================
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        navigateToPage,
        toggleSidebarMinimize,
        toggleSidebar,
        toggleSystemMaintenance
    };
}
