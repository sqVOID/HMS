<?php
/**
 * Navigation Helper
 * Provides navigation menu based on user access level
 */
require_once 'auth.php';

function renderNavigation($currentPage = '') {
    $userLevel = getUserAccessLevel();
    
    // Define menu items with their access requirements
    $menuItems = [
        [
            'page' => 'Report.php',
            'icon' => 'dashboardicon_system.svg',
            'label' => 'Dashboard',
            'access' => ['staff', 'admin']
        ],
        [
            'page' => 'Booking.html',
            'icon' => 'bookingicon_system.svg',
            'label' => 'Booking',
            'access' => ['user', 'staff', 'admin']
        ],
        [
            'page' => 'Roomlist.html',
            'icon' => 'roomlisticon_system.svg',
            'label' => 'Room List',
            'access' => ['staff', 'admin']
        ],
        [
            'page' => 'Promo.html',
            'icon' => 'promoicon_system.svg',
            'label' => 'Promo',
            'access' => ['staff', 'admin']
        ],
    ];
    
    // Maintenance submenu items
    $maintenanceItems = [
        [
            'page' => 'inventory.html',
            'icon' => 'inventoryicon_system.svg',
            'label' => 'Inventory',
            'access' => ['staff', 'admin']
        ],
        [
            'page' => 'PurchaseOrder.html',
            'icon' => 'purchaseordericon_system.svg',
            'label' => 'Purchase Order',
            'access' => ['admin'] // Only admin
        ],
        [
            'page' => 'AddItem.php',
            'icon' => 'additemicon_system.svg',
            'label' => 'Add Item',
            'access' => ['staff', 'admin']
        ],
    ];
    
    // Other menu items
    $otherItems = [
        [
            'page' => 'Receive.html',
            'icon' => 'receiveicon_system.svg',
            'label' => 'Receive',
            'access' => ['staff', 'admin']
        ],
        [
            'page' => 'Breakfast.html',
            'icon' => 'breakfasticon_system.svg',
            'label' => 'Breakfast',
            'access' => ['staff', 'admin']
        ],
    ];
    
    // Filter menu items based on access
    $filteredMenuItems = array_filter($menuItems, function($item) use ($userLevel) {
        return in_array($userLevel, $item['access']);
    });
    
    $filteredMaintenanceItems = array_filter($maintenanceItems, function($item) use ($userLevel) {
        return in_array($userLevel, $item['access']);
    });
    
    $filteredOtherItems = array_filter($otherItems, function($item) use ($userLevel) {
        return in_array($userLevel, $item['access']);
    });
    
    // Render navigation
    echo '<nav class="sidebar-menu">';
    echo '<ul>';
    
    // Main menu items
    foreach ($filteredMenuItems as $item) {
        $isActive = ($currentPage === $item['page']) ? 'active' : '';
        echo '<li class="sidebar-menu-item ' . $isActive . '" data-page="' . htmlspecialchars($item['page']) . '" onclick="navigateToPage(\'' . htmlspecialchars($item['page']) . '\')">';
        echo '<img src="Icon/' . htmlspecialchars($item['icon']) . '" class="sidebar-icon" alt="' . htmlspecialchars($item['label']) . '">';
        echo '<span>' . htmlspecialchars($item['label']) . '</span>';
        echo '</li>';
    }
    
    // Maintenance submenu (only show if there are items)
    if (!empty($filteredMaintenanceItems)) {
        echo '<li class="sidebar-menu-item collapsible-menu" id="systemMaintenanceMenu">';
        echo '<div class="menu-header" onclick="toggleSystemMaintenance()">';
        echo '<img src="Icon/systemmaitenanceicon_system.svg" class="sidebar-icon" alt="Maintenance">';
        echo '<span>Maintenance</span>';
        echo '<svg class="menu-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
        echo '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
        echo '</svg>';
        echo '</div>';
        echo '<ul class="submenu" id="systemMaintenanceSubmenu">';
        
        foreach ($filteredMaintenanceItems as $item) {
            $isActive = ($currentPage === $item['page']) ? 'active' : '';
            echo '<li class="submenu-item ' . $isActive . '" data-page="' . htmlspecialchars($item['page']) . '" onclick="navigateToPage(\'' . htmlspecialchars($item['page']) . '\')">';
            echo '<img src="Icon/' . htmlspecialchars($item['icon']) . '" class="sidebar-icon" alt="' . htmlspecialchars($item['label']) . '">';
            echo '<span>' . htmlspecialchars($item['label']) . '</span>';
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</li>';
    }
    
    // Other menu items
    foreach ($filteredOtherItems as $item) {
        $isActive = ($currentPage === $item['page']) ? 'active' : '';
        echo '<li class="sidebar-menu-item ' . $isActive . '" data-page="' . htmlspecialchars($item['page']) . '" onclick="navigateToPage(\'' . htmlspecialchars($item['page']) . '\')">';
        echo '<img src="Icon/' . htmlspecialchars($item['icon']) . '" class="sidebar-icon" alt="' . htmlspecialchars($item['label']) . '">';
        echo '<span>' . htmlspecialchars($item['label']) . '</span>';
        echo '</li>';
    }
    
    // Logout button
    echo '<li class="sidebar-menu-item" onclick="window.location.href=\'logout.php\'">';
    echo '<img src="Icon/logouticon_system.svg" class="sidebar-icon" alt="Logout">';
    echo '<span>Logout</span>';
    echo '</li>';
    
    echo '</ul>';
    echo '</nav>';
}
?>

