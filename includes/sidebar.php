<?php
/**
 * Global Sidebar Component
 * Reusable sidebar for all pages in the HMS system
 */
?>
<button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

<div class="left-panel" id="leftPanel">
    <!-- DingTalk-style collapse tab -->
    <button class="sidebar-collapse-btn" id="collapseBtn" onclick="toggleSidebarMinimize()">
        <img id="collapseIcon" src="Icon/left-arrow_minimize.svg" alt="Minimize"
            style="width: 24px; height: 18px;">
        <span class="collapse-tooltip" id="collapseTooltip">Minimize</span>
    </button>
    <img src="Icon/MoonClave_Logo.svg" alt="Luna Group Logo" class="logo-img">
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

            <li class="sidebar-menu-item" data-page="CashDeposit.php"
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
                <img src="Icon/reservationicon_system.svg" class="sidebar-icon" alt="Reservation">
                <span>Reservation List</span>
            </li>

            <li class="sidebar-menu-item" data-page="Cancelpage.php" onclick="navigateToPage('Cancelpage.php')">
                <img src="Icon/cancelicon_system.svg" class="sidebar-icon" alt="Cancellation Approval">
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
            <li class="sidebar-menu-item" style="display: none;" data-page="SystemLog.php" onclick="navigateToPage('SystemLog.php')">
                <img src="Icon/dashboardicon_system.svg" class="sidebar-icon" alt="System Log">
                <span>System Log</span>
            </li>
            <li class="sidebar-menu-item" onclick="window.location.href='logout.php'">
                <img src="Icon/logouticon_system.svg" class="sidebar-icon" alt="Logout">
                <span>Logout</span>
            </li>
        </ul>
    </nav>
</div>
