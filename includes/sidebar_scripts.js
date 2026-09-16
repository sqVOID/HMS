/**
 * Global Sidebar Scripts
 * Reusable JavaScript functions for sidebar functionality
 */

// ── Sidebar collapse (DingTalk style) ─────────────────────
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

// ── Navigation ───────────────────────────────────────────
window.navigateToPage = page => {
    // Don't reload if clicking the active page
    const currentPage = window.location.pathname.split('/').pop();
    if (currentPage === page) {
        // Just close mobile menu if open
        if (window.innerWidth <= 1024) {
            const panel = document.querySelector('.left-panel');
            if (panel) panel.classList.remove('open');
        }
        return; // Don't navigate
    }
    
    // Close mobile sidebar before navigating
    if (window.innerWidth <= 1024) {
        const panel = document.querySelector('.left-panel');
        if (panel) panel.classList.remove('open');
    }
    window.location.href = page;
};

// ── Mobile Sidebar Toggle ────────────────────────────────
function toggleSidebar() {
    const panel = document.querySelector('.left-panel');
    if (panel) panel.classList.toggle('open');
}

// ── Click Outside to Close (Mobile) ──────────────────────
document.addEventListener('click', function (e) {
    if (window.innerWidth <= 1024) {
        const panel = document.querySelector('.left-panel');
        const toggleBtn = document.querySelector('.mobile-menu-toggle');
        if (panel && panel.classList.contains('open')) {
            if (!panel.contains(e.target) && !toggleBtn.contains(e.target)) {
                panel.classList.remove('open');
            }
        }
    }
});

// ── Maintenance Submenu ──────────────────────────────────
function toggleSystemMaintenance() {
    const submenu = document.getElementById('systemMaintenanceSubmenu');
    const menuItem = document.getElementById('systemMaintenanceMenu');
    if (submenu && menuItem) {
        if (submenu.classList.contains('open')) {
            submenu.classList.remove('open');
            menuItem.classList.remove('expanded');
        } else {
            submenu.classList.add('open');
            menuItem.classList.add('expanded');
        }
    }
}

// ── Active menu item ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const currentPage = window.location.pathname.split('/').pop() || 'Report.php';
    const menuItems = document.querySelectorAll('.sidebar-menu-item:not(.collapsible-menu)');
    const submenuItems = document.querySelectorAll('.submenu-item');
    const maintenance = ['inventory.html', 'PurchaseOrder.html', 'AddItem.php'];

    if (maintenance.includes(currentPage)) {
        const submenu = document.getElementById('systemMaintenanceSubmenu');
        const menuItem = document.getElementById('systemMaintenanceMenu');
        if (submenu && menuItem) { 
            submenu.classList.add('open'); 
            menuItem.classList.add('expanded'); 
        }
        submenuItems.forEach(item => {
            item.classList.toggle('active', item.getAttribute('data-page') === currentPage);
        });
    } else {
        menuItems.forEach(item => {
            item.classList.toggle('active', item.getAttribute('data-page') === currentPage);
        });
    }
});
