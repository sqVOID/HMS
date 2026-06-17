  <?php

require_once 'auth.php';
checkPageAccess('Report.php'); // Enforce access control - users without proper access will be redirected
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>Reports</title>
    <link rel="stylesheet" href="Report.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="role-based-menu.js?v=18" defer></script>
    <script src="cancellation-notification.js?v=15" defer></script>
    <script>
        // Define navigation functions early to prevent "not defined" errors on tablets
        function navigateToPage(page) {
            // Prevent navigation if already on the current page
            const currentPage = window.location.pathname.split('/').pop().toLowerCase();
            const targetPage = page.toLowerCase();
            if (currentPage === targetPage) {
                return; // Don't reload if we're already on this page
            }

            // Close sidebar on mobile when navigating
            if (window.innerWidth <= 1024) {
                toggleSidebar();
            }
            window.location.href = page;
        }

        function toggleSystemMaintenance() {
            const submenu = document.getElementById('systemMaintenanceSubmenu');
            const menuItem = document.getElementById('systemMaintenanceMenu');
            if (!submenu || !menuItem) return;

            const isOpen = submenu.classList.contains('open');
            if (isOpen) {
                submenu.classList.remove('open');
                menuItem.classList.remove('expanded');
            } else {
                submenu.classList.add('open');
                menuItem.classList.add('expanded');
            }
        }

        function toggleSidebar() {
            const panel = document.querySelector('.left-panel');
            const overlay = document.querySelector('.mobile-menu-overlay');

            if (panel) {
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
        }

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
    </script>
    <style>
        .date-range-select {
            padding: 10px 16px;
            border: 1px solid #e0bf02ff;
            border-radius: 6px;
            background: #ffffff;
            color: #a78e00ff;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            font-size: 14px;
        }

        .date-range-select:focus {
            outline: none;
            border-color: #e0bf02ff;
        }

        #customRangeFields {
            display: none;
            gap: 12px;
            align-items: center;
            margin-left: 12px;
        }

        #customRangeFields.show {
            display: flex;
        }

        .revenue-card {
            background: #fff;
            border: 1px solid #e0e6ff;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            min-height: 90px;
        }

        .revenue-card .label {
            font-size: 13px;
            color: #7a7f9a;
            margin-bottom: 6px;
        }

        .revenue-card .value {
            font-size: 20px;
            font-weight: 700;
            color: #1a1f36;
        }

        /* Sales Metrics Card Styling */
        .sales-metric-card {
            background: #f8f8f5;
            border: 1px solid #d4d0b8;
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
            min-height: 100px;
            transition: all 0.2s ease;
        }

        .sales-metric-card:hover {
            border-color: #bfbb9f;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }

        .sales-metric-card .label {
            font-size: 13px;
            color: #5a5520;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .sales-metric-card .value {
            font-size: 22px;
            font-weight: 700;
            color: #3a3515;
        }

        .header-title {
            font-size: 18px;
            font-weight: 700;
            color: #222;
            font-family: "Poppins", "Segoe UI", Arial, sans-serif;
            margin-top: 0px;
            margin-bottom: 30px;
        }
    </style>
</head>

<body>
    <!-- Mobile hamburger button -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">☰</button>

    <!-- Mobile menu overlay -->
    <div class="mobile-menu-overlay" onclick="toggleSidebar()"></div>

    <div class="split-container">
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
                    <!-- <li class="sidebar-menu-item" data-page="Message.html" onclick="navigateToPage('Message.html')">
                        <img src="Icon/messageicon_system.svg" class="sidebar-icon" alt="Message">
                        <span>Message</span>
                    </li>
                -->
                    <li class="sidebar-menu-item" onclick="window.location.href='logout.php'">
                        <img src="Icon/logouticon_system.svg" class="sidebar-icon" alt="Logout">
                        <span>Logout</span>
                    </li>
                    <!-- <li class="sidebar-menu-item" id="minimizeBtn" onclick="toggleSidebarMinimize()">
                        <svg class="sidebar-icon" id="minimizeIcon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="transition: transform 0.3s ease;">
                            <path d="M12 5L7 10L12 15M17 5L12 10L17 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span id="minimizeText">Minimize</span>
                    </li> -->
                </ul>
            </nav>
        </div>
        <div class="right-panel">
            <div class="header-bar">
                <div class="user-profile">
                    <div class="user-avatar" id="userAvatar">
                        <?php
                        $username = $_SESSION['username'] ?? 'User';
                        $firstName = $_SESSION['first_name'] ?? '';
                        $displayName = $firstName !== '' ? $firstName : $username;
                        echo strtoupper(substr($displayName, 0, 1));
                        ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="user-role">
                            <?php
                            $accessLevel = $_SESSION['access_level'] ?? 'user';
                            echo htmlspecialchars(ucwords(str_replace('_', ' ', $accessLevel)), ENT_QUOTES, 'UTF-8');
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-container">
                <h2 class="header-title">Dashboard</h2>
                <div
                    style="padding: 24px; background: #fff; border-radius: 8px; margin: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 12px;">
                        <h3 style="margin: 0; font-size: 20px; color: #222;">Booking Statistics</h3>
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <select id="dateRangeSelect" class="date-range-select">
                                <option value="today">Today</option>
                                <option value="last_week">Last 7 Days</option>
                                <option value="last_month">Last 30 Days</option>
                                <option value="custom">Custom</option>
                            </select>
                            <div id="customRangeFields">
                                <input type="date" id="customRangeStart"
                                    style="padding:8px 12px; border:1px solid #ddd; border-radius:6px; font-family:'Poppins',sans-serif;">
                                <input type="date" id="customRangeEnd"
                                    style="padding:8px 12px; border:1px solid #ddd; border-radius:6px; font-family:'Poppins',sans-serif;">
                                <button id="applyCustomRangeBtn"
                                    style="background:#afad4cff; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer;">Apply</button>
                            </div>
                            <!-- Reports Dropdown -->
                            <div style="position: relative; display: inline-block;">
                                <button id="reportsDropdownBtn" onclick="toggleReportsDropdown()"
                                    style="background: #afad4cff; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; transition: background 0.3s; display: flex; align-items: center; gap: 8px;">
                                    Reports
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" style="transition: transform 0.3s;">
                                        <path d="M3 4.5L6 7.5L9 4.5" stroke="white" stroke-width="1.5"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                                <div id="reportsDropdownMenu"
                                    style="display: none; position: absolute; top: 100%; right: 0; margin-top: 8px; background: white; border: 1px solid #ddd; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 220px; z-index: 1000;">

                                    <?php
                                    // Define role-based access permissions for reports
                                    $userAccessLevel = $_SESSION['access_level'] ?? 'user';

                                    // Define which roles can access which reports
                                    $canViewDailySales = in_array($userAccessLevel, ['super_admin', 'admin', 'auditor', 'user']);
                                    $canViewDailySales2 = false;
                                    $canViewBookingReport = in_array($userAccessLevel, ['super_admin', 'admin', 'auditor']);
                                    $canViewDetailedBookingReport = in_array($userAccessLevel, ['super_admin', 'admin', 'auditor']);
                                    $canViewPerPaymentReport = in_array($userAccessLevel, ['super_admin', 'admin', 'auditor']);
                                    $canViewPaymentTypeReport = in_array($userAccessLevel, ['super_admin', 'admin', 'auditor']);
                                    $canViewCustomerDetailsReport = in_array($userAccessLevel, ['super_admin', 'admin', 'auditor']);
                                    $canViewRunningSalesReport = in_array($userAccessLevel, ['super_admin', 'admin', 'auditor']);

                                    // Count visible reports to determine border radius
                                    $visibleReports = 0;
                                    if ($canViewDailySales)
                                        $visibleReports++;
                                    if ($canViewDailySales2)
                                        $visibleReports++;
                                    if ($canViewBookingReport)
                                        $visibleReports++;
                                    if ($canViewDetailedBookingReport)
                                        $visibleReports++;
                                    if ($canViewPerPaymentReport)
                                        $visibleReports++;
                                    if ($canViewPaymentTypeReport)
                                        $visibleReports++;
                                    if ($canViewCustomerDetailsReport)
                                        $visibleReports++;
                                    if ($canViewRunningSalesReport)
                                        $visibleReports++;

                                    $currentReport = 0;
                                    ?>

                                    <?php if ($canViewDailySales):
                                        $currentReport++; ?>
                                        <button onclick="openDailySalesModal(); toggleReportsDropdown();"
                                            style="width: 100%; text-align: left; background: none; border: none; padding: 12px 16px; cursor: pointer; font-size: 14px; font-weight: 500; font-family: 'Poppins', sans-serif; color: #333; transition: background 0.2s; <?php echo ($currentReport == 1) ? 'border-radius: 6px 6px 0 0;' : ''; ?> <?php echo ($currentReport == $visibleReports) ? 'border-radius: 0 0 6px 6px;' : ''; ?> <?php echo ($visibleReports == 1) ? 'border-radius: 6px;' : ''; ?>"
                                            onmouseover="this.style.background='#f5f5f5'"
                                            onmouseout="this.style.background='none'">
                                            Daily Sales
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canViewDailySales2):
                                        $currentReport++; ?>
                                        <button onclick="openDailySales2Modal(); toggleReportsDropdown();"
                                            style="width: 100%; text-align: left; background: none; border: none; padding: 12px 16px; cursor: pointer; font-size: 14px; font-weight: 500; font-family: 'Poppins', sans-serif; color: #333; transition: background 0.2s; <?php echo ($currentReport == 1) ? 'border-radius: 6px 6px 0 0;' : ''; ?> <?php echo ($currentReport == $visibleReports) ? 'border-radius: 0 0 6px 6px;' : ''; ?> <?php echo ($visibleReports == 1) ? 'border-radius: 6px;' : ''; ?>"
                                            onmouseover="this.style.background='#f5f5f5'"
                                            onmouseout="this.style.background='none'">
                                            Daily Sales 2.0
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canViewBookingReport):
                                        $currentReport++; ?>
                                        <button onclick="exportToExcel(); toggleReportsDropdown();"
                                            style="width: 100%; text-align: left; background: none; border: none; padding: 12px 16px; cursor: pointer; font-size: 14px; font-weight: 500; font-family: 'Poppins', sans-serif; color: #333; transition: background 0.2s; <?php echo ($currentReport == 1) ? 'border-radius: 6px 6px 0 0;' : ''; ?> <?php echo ($currentReport == $visibleReports) ? 'border-radius: 0 0 6px 6px;' : ''; ?> <?php echo ($visibleReports == 1) ? 'border-radius: 6px;' : ''; ?>"
                                            onmouseover="this.style.background='#f5f5f5'"
                                            onmouseout="this.style.background='none'">
                                            Booking Report
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canViewDetailedBookingReport):
                                        $currentReport++; ?>
                                        <button onclick="openDetailedBookingReportModal(); toggleReportsDropdown();"
                                            style="width: 100%; text-align: left; background: none; border: none; padding: 12px 16px; cursor: pointer; font-size: 14px; font-weight: 500; font-family: 'Poppins', sans-serif; color: #333; transition: background 0.2s; <?php echo ($currentReport == 1) ? 'border-radius: 6px 6px 0 0;' : ''; ?> <?php echo ($currentReport == $visibleReports) ? 'border-radius: 0 0 6px 6px;' : ''; ?> <?php echo ($visibleReports == 1) ? 'border-radius: 6px;' : ''; ?>"
                                            onmouseover="this.style.background='#f5f5f5'"
                                            onmouseout="this.style.background='none'">
                                            Detailed Booking Report
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canViewPerPaymentReport):
                                        $currentReport++; ?>
                                        <button onclick="openPaymentTypeReportModal(); toggleReportsDropdown();"
                                            style="width: 100%; text-align: left; background: none; border: none; padding: 12px 16px; cursor: pointer; font-size: 14px; font-weight: 500; font-family: 'Poppins', sans-serif; color: #333; transition: background 0.2s; <?php echo ($currentReport == 1) ? 'border-radius: 6px 6px 0 0;' : ''; ?> <?php echo ($currentReport == $visibleReports) ? 'border-radius: 0 0 6px 6px;' : ''; ?> <?php echo ($visibleReports == 1) ? 'border-radius: 6px;' : ''; ?>"
                                            onmouseover="this.style.background='#f5f5f5'"
                                            onmouseout="this.style.background='none'">
                                            Per Payment Report
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canViewPaymentTypeReport):
                                        $currentReport++; ?>
                                        <button onclick="openPaymentTypeRawReportModal(); toggleReportsDropdown();"
                                            style="width: 100%; text-align: left; background: none; border: none; padding: 12px 16px; cursor: pointer; font-size: 14px; font-weight: 500; font-family: 'Poppins', sans-serif; color: #333; transition: background 0.2s; <?php echo ($currentReport == 1) ? 'border-radius: 6px 6px 0 0;' : ''; ?> <?php echo ($currentReport == $visibleReports) ? 'border-radius: 0 0 6px 6px;' : ''; ?> <?php echo ($visibleReports == 1) ? 'border-radius: 6px;' : ''; ?>"
                                            onmouseover="this.style.background='#f5f5f5'"
                                            onmouseout="this.style.background='none'">
                                            Payment Type Report
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canViewCustomerDetailsReport):
                                        $currentReport++; ?>
                                        <button onclick="openCustomerDetailsReportModal(); toggleReportsDropdown();"
                                            style="width: 100%; text-align: left; background: none; border: none; padding: 12px 16px; cursor: pointer; font-size: 14px; font-weight: 500; font-family: 'Poppins', sans-serif; color: #333; transition: background 0.2s; <?php echo ($currentReport == 1) ? 'border-radius: 6px 6px 0 0;' : ''; ?> <?php echo ($currentReport == $visibleReports) ? 'border-radius: 0 0 6px 6px;' : ''; ?> <?php echo ($visibleReports == 1) ? 'border-radius: 6px;' : ''; ?>"
                                            onmouseover="this.style.background='#f5f5f5'"
                                            onmouseout="this.style.background='none'">
                                            Customer Details Report
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canViewRunningSalesReport):
                                        $currentReport++; ?>
                                        <button onclick="openRunningSalesReportModal(); toggleReportsDropdown();"
                                            style="width: 100%; text-align: left; background: none; border: none; padding: 12px 16px; cursor: pointer; font-size: 14px; font-weight: 500; font-family: 'Poppins', sans-serif; color: #333; transition: background 0.2s; <?php echo ($currentReport == 1) ? 'border-radius: 6px 6px 0 0;' : ''; ?> <?php echo ($currentReport == $visibleReports) ? 'border-radius: 0 0 6px 6px;' : ''; ?> <?php echo ($visibleReports == 1) ? 'border-radius: 6px;' : ''; ?>"
                                            onmouseover="this.style.background='#f5f5f5'"
                                            onmouseout="this.style.background='none'">
                                            Running Sales Report
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
                    // Sales Metrics - Only visible to admin and super_admin
                    $userAccessLevel = $_SESSION['access_level'] ?? 'user';
                    if (in_array($userAccessLevel, ['admin', 'super_admin'])):
                        ?>
                        <!-- Sales Metrics -->
                        <div id="salesMetrics"
                            style="margin-top: 32px; background: #fff; border-radius: 8px; padding: 24px; border: 1px solid #e0e6ff; margin-bottom: 32px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                                <h3 style="margin:0; font-size:18px; color:#222;">Sales Metrics</h3>
                            </div>
                            <!-- First Row: 4 cards -->
                            <div
                                style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px;">
                                <!-- 1. Running Sales (MTD) -->
                                <div class="sales-metric-card">
                                    <div class="label">Running Sales (MTD)</div>
                                    <div class="value" id="runningSalesMTDDisplay">₱0.00</div>
                                    <!--   <small style="color: #7a7f9a; font-size: 11px; display: block; margin-top: 4px;" id="mtdSalesDateRange">Auto-calculated</small> -->
                                </div>
                                <!-- 2. Avg Daily Sales -->
                                <div class="sales-metric-card">
                                    <div class="label">AVG Daily Sales</div>
                                    <div class="value" id="avgDailySalesDisplay">₱0.00</div>
                                    <small style="color: #7a7f9a; font-size: 11px; display: none; margin-top: 4px;"
                                        id="dailySalesSelectedDate">Up to <?php echo date('M j, Y'); ?></small>
                                </div>
                                <!-- 3. EMSO -->
                                <div class="sales-metric-card">
                                    <div class="label">EMSO</div>
                                    <div class="value" id="emsoDisplay">₱0.00</div>
                                    <!--   <small style="color: #7a7f9a; font-size: 11px; display: block; margin-top: 4px;">Auto-calculated</small> -->
                                </div>
                                <!-- 4. EMSO vs Last Month -->
                                <div class="sales-metric-card">
                                    <div class="label">EMSO vs Last Month</div>
                                    <div class="value" id="emsoVsLastMonthDisplay">
                                        <span style="font-size: 20px; color: #3a3515;">0.0%</span>
                                    </div>
                                    <!--  <small style="color: #7a7f9a; font-size: 11px; display: block; margin-top: 4px;">Auto-calculated</small> -->
                                </div>
                            </div>

                            <!-- Second Row: 3 cards -->
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                                <!-- 5. Target -->
                                <div class="sales-metric-card">
                                    <div class="label"
                                        style="display: flex; justify-content: space-between; align-items: center;">
                                        <span>Target</span>
                                        <button id="editTargetBtn" onclick="toggleEditTarget()"
                                            style="background: none; border: 1px solid #bfbb9f; padding: 6px 10px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 4px; font-size: 11px; color: #5a5520; transition: all 0.2s;"
                                            onmouseover="this.style.borderColor='#afad4cff'; this.style.color='#3a3515';"
                                            onmouseout="this.style.borderColor='#bfbb9f'; this.style.color='#5a5520';">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                            <span id="editTargetBtnLabel">Edit</span>
                                        </button>
                                    </div>
                                    <div class="value" id="targetDisplay">₱0.00</div>
                                    <input type="number" id="targetInput"
                                        style="display:none; width:100%; padding:8px; border:1px solid #bfbb9f; border-radius:4px; font-size:16px; font-family:'Poppins',sans-serif; margin-top:6px; box-sizing: border-box;"
                                        placeholder="0.00">
                                    <!--  <small style="color: #7a7f9a; font-size: 11px; display: block; margin-top: 4px;">Manual entry</small> -->
                                </div>
                                <!-- 6. Achievement -->
                                <div class="sales-metric-card">
                                    <div class="label">Achievement</div>
                                    <div class="value" id="achievementDisplay">
                                        <span style="font-size: 20px; color: #3a3515;">0.0%</span>
                                    </div>
                                    <!-- <small style="color: #7a7f9a; font-size: 11px; display: block; margin-top: 4px;">Auto-calculated</small> -->
                                </div>
                                <!-- 7. Benchmark -->
                                <div class="sales-metric-card">
                                    <div class="label">Benchmark</div>
                                    <div class="value" id="benchmarkDisplay">
                                        <span style="font-size: 20px; color: #3a3515;">0.0%</span>
                                    </div>
                                    <!--  <small style="color: #7a7f9a; font-size: 11px; display: block; margin-top: 4px;">Auto-calculated</small> -->
                                </div>
                            </div>
                            <div id="targetActions" style="display:none; margin-top:20px; text-align:right; gap:12px;">
                                <button onclick="cancelEditTarget()"
                                    style="background: white; color: #222; border: 1px solid #ddd; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; font-family: 'Poppins', sans-serif; margin-right:8px;">
                                    Cancel
                                </button>
                                <button onclick="saveTarget()"
                                    style="background: #afad4cff; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; font-family: 'Poppins', sans-serif;">
                                    Save Target
                                </button>
                            </div>
                        </div>


                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 32px;">
                        <div
                            style="background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #e0e6ff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            <h4 style="margin: 0 0 16px 0; font-size: 16px; color: #222;">Hotel Rooms</h4>
                            <div style="position: relative; width: 150px; height: 150px; margin: 0 auto;">
                                <svg viewBox="0 0 36 36" style="transform: rotate(-90deg);">
                                    <path
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        fill="none" stroke="#e0e0e0" stroke-width="3" />
                                    <path id="occupancyCircle"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        fill="none" stroke="#afa84cff" stroke-width="3" stroke-dasharray="0, 100"
                                        stroke-linecap="round" />
                                </svg>
                                <div
                                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                                    <div id="occupancyRateValue"
                                        style="font-size: 28px; font-weight: 700; color: #6d6825ff;">0%</div>
                                </div>
                            </div>
                            <div
                                style="display: flex; justify-content: space-around; margin-top: 16px; font-size: 13px;">
                                <div style="text-align: center;">
                                    <div style="color: #6d6825ff; font-weight: 600;" id="occupiedCount">0</div>
                                    <div style="color: #777;">Occupied</div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="color: #999; font-weight: 600;" id="availableCount">0</div>
                                    <div style="color: #777;">Available</div>
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Check-ins/Check-outs -->
                        <div style="display: grid; grid-template-rows: 1fr 1fr; gap: 16px;">
                            <div
                                style="background: #fff; padding: 16px 20px; border-radius: 8px; border: 1px solid #e0e6ff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden;">
                                <h4
                                    style="margin: 0 0 12px 0; font-size: 14px; color: #222; display: flex; align-items: center; gap: 8px;">
                                    <span
                                        style="width: 8px; height: 8px; background: #4CAF50; border-radius: 50%;"></span>
                                    Upcoming Reservation Check-Ins (Next 7 Days)
                                </h4>
                                <div id="upcomingCheckInsContainer"
                                    style="max-height: 100px; overflow-y: auto; font-size: 13px;">
                                    <div style="color: #777;">Loading...</div>
                                </div>
                            </div>
                            <div
                                style="background: #fff; padding: 16px 20px; border-radius: 8px; border: 1px solid #e0e6ff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden;">
                                <h4
                                    style="margin: 0 0 12px 0; font-size: 14px; color: #222; display: flex; align-items: center; gap: 8px;">
                                    <span
                                        style="width: 8px; height: 8px; background: #ff9800; border-radius: 50%;"></span>
                                    Upcoming Check-Outs (Next 3 Days)
                                </h4>
                                <div id="upcomingCheckOutsContainer"
                                    style="max-height: 100px; overflow-y: auto; font-size: 13px;">
                                    <div style="color: #777;">Loading...</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Count Cards Grid -->
                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 32px;">
                        <!-- Total Rooms -->
                        <div class="sales-metric-card">
                            <div class="label">Total Rooms</div>
                            <div class="value" id="totalRoomsCount">0</div>
                        </div>
                        <!-- Available -->
                        <div class="sales-metric-card">
                            <div class="label">Available</div>
                            <div class="value" id="availableRoomsCount">0</div>
                        </div>
                        <!-- Out of Order -->
                        <div class="sales-metric-card">
                            <div class="label">Out of Order</div>
                            <div class="value" id="outOfOrderRoomsCount">0</div>
                        </div>
                        <!-- Check-In -->
                        <div class="sales-metric-card">
                            <div class="label">Check-In</div>
                            <div class="value" id="checkInCount">0</div>
                        </div>
                        <!-- Check-Out -->
                        <div class="sales-metric-card">
                            <div class="label">Check-Out</div>
                            <div class="value" id="checkOutCount">0</div>
                        </div>
                        <!-- Canceled -->
                        <div class="sales-metric-card">
                            <div class="label">Canceled</div>
                            <div class="value" id="canceledCount">0</div>
                        </div>
                        <!-- Promo -->
                        <div class="sales-metric-card">
                            <div class="label">Promo</div>
                            <div class="value" id="promoCount">0</div>
                        </div>
                        <!-- Walk-in -->
                        <div class="sales-metric-card">
                            <div class="label">Walk-in</div>
                            <div class="value" id="walkinCount">0</div>
                        </div>
                        <!-- Reservation -->
                        <div class="sales-metric-card">
                            <div class="label">Reservation</div>
                            <div class="value" id="reservationCount">0</div>
                        </div>
                    </div>

                    <?php
                    // Room Sales and Food Tracking - Only visible to admin and super_admin
                    if (in_array($userAccessLevel, ['admin', 'super_admin'])):
                        ?>
                        <!-- Room Sales and Food Revenue in 2 columns -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">

                            <!-- Room Sales by Hour (Left Column) -->
                            <div
                                style="background: #fff; border: 1px solid #e0e6ff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                <h4 style="margin: 0 0 16px 0; font-size: 16px; color: #222; font-weight: 600;">Room Sales
                                </h4>

                                <table
                                    style="width: 100%; border-collapse: collapse; font-family: 'Poppins', sans-serif; margin-bottom: 20px;">
                                    <thead>
                                        <tr style="background: #f8f8f5; border-bottom: 2px solid #e0e6ff;">
                                            <th
                                                style="padding: 10px 8px; text-align: left; font-weight: 600; color: #555; font-size: 13px; border: 1px solid #e0e6ff;">
                                                Time</th>
                                            <th
                                                style="padding: 10px 8px; text-align: center; font-weight: 600; color: #555; font-size: 13px; border: 1px solid #e0e6ff;">
                                                3hrs</th>
                                            <th
                                                style="padding: 10px 8px; text-align: center; font-weight: 600; color: #555; font-size: 13px; border: 1px solid #e0e6ff;">
                                                6hrs</th>
                                            <th
                                                style="padding: 10px 8px; text-align: center; font-weight: 600; color: #555; font-size: 13px; border: 1px solid #e0e6ff;">
                                                12hrs</th>
                                            <th
                                                style="padding: 10px 8px; text-align: center; font-weight: 600; color: #555; font-size: 13px; border: 1px solid #e0e6ff;">
                                                24hrs</th>
                                            <th
                                                style="padding: 10px 8px; text-align: center; font-weight: 600; color: #555; font-size: 13px; border: 1px solid #e0e6ff;">
                                                36hrs</th>
                                            <th
                                                style="padding: 10px 8px; text-align: center; font-weight: 600; color: #555; font-size: 13px; border: 1px solid #e0e6ff;">
                                                48hrs</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="background: #fffef8;">
                                            <td
                                                style="padding: 10px 8px; font-weight: 600; color: #555; font-size: 13px; border: 1px solid #e0e6ff;">
                                                Total</td>
                                            <td id="room_sales_total_3h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_total_6h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_total_12h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_total_24h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_total_36h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_total_48h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                        </tr>
                                        <tr style="background: #fff;">
                                            <td
                                                style="padding: 10px 8px; font-weight: 600; color: #555; font-size: 13px; border: 1px solid #e0e6ff;">
                                                AM</td>
                                            <td id="room_sales_am_3h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_am_6h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_am_12h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_am_24h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_am_36h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_am_48h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                        </tr>
                                        <tr style="background: #fffef8;">
                                            <td
                                                style="padding: 10px 8px; font-weight: 600; color: #555; font-size: 13px; border: 1px solid #e0e6ff;">
                                                PM</td>
                                            <td id="room_sales_pm_3h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_pm_6h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_pm_12h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_pm_24h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_pm_36h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                            <td id="room_sales_pm_48h"
                                                style="padding: 10px 8px; text-align: center; font-weight: 700; color: #3a3515; font-size: 14px; border: 1px solid #e0e6ff;">
                                                0</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Quick Stats -->
                                <!--
                                    QUICK STATS CALCULATIONS:
                                    
                                    1. Total Bookings:
                                       - Counts all bookings (base bookings + extensions) within the selected date range
                                       - Includes bookings from both 'reports' and 'bookings' tables
                                       - Each booking_id is counted only once (duplicates are filtered out)
                                       - Formula: COUNT(DISTINCT booking_id) WHERE check_in_date IN [filterStart, filterEnd]
                                    
                                    2. Avg. Rate (Average Rate):
                                       - Calculates the average revenue per booking
                                       - Formula: Total Revenue / Total Bookings
                                       - Total Revenue = SUM(room_price) from all bookings in the date range
                                       - Result is rounded to nearest whole number and displayed in Philippine Peso (₱)
                                    
                                    3. vs Yesterday (Revenue Change):
                                       - Compares current period revenue against previous period
                                       - Previous period length matches current period length
                                       - Examples:
                                         * Today vs Yesterday (1 day comparison)
                                         * Last 7 Days vs Previous 7 Days
                                         * Last 30 Days vs Previous 30 Days
                                       - Formula: ((currentRevenue - prevRevenue) / prevRevenue) * 100
                                       - Display:
                                         * ↑ (Green) = Revenue increased
                                         * ↓ (Red) = Revenue decreased
                                         * — (Gray) = No change or no previous data
                                    
                                    4. Peak Hour:
                                       - Identifies the most common check-in hour within the date range
                                       - Analyzes all check_in timestamps and counts bookings per hour
                                       - Displays as a 2-hour range (e.g., "2 PM-4 PM")
                                       - Formula: MODE(HOUR(check_in)) then format as range [peakHour, peakHour+2]
                                       - Shows "N/A" if no bookings in the selected period
                                    
                                    Data Sources:
                                    - Backend: get_room_sales_tracking.php
                                    - Tables: 'reports' and 'bookings'
                                    - Includes: Base bookings, room extensions, and their respective revenues
                                    - Extension handling: Parses extension_stack JSON and extension_time_at timestamps
                                -->
                                <div style="border-top: 2px solid #e0e6ff; padding-top: 16px;">
                                    <h5 style="margin: 0 0 12px 0; font-size: 14px; color: #555; font-weight: 600;">Quick
                                        Stats</h5>
                                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">

                                        <!-- Total Bookings Today -->
                                        <div
                                            style="background: #f8f8f5; border: 1px solid #d4d0b8; border-radius: 6px; padding: 10px; text-align: center;">
                                            <div
                                                style="color: #5a5520; font-size: 11px; font-weight: 500; margin-bottom: 4px;">
                                                Total Bookings</div>
                                            <div id="room_sales_total_bookings_val"
                                                style="color: #3a3515; font-size: 18px; font-weight: 700;">0</div>
                                        </div>

                                        <!-- Average Rate -->
                                        <div
                                            style="background: #f8f8f5; border: 1px solid #d4d0b8; border-radius: 6px; padding: 10px; text-align: center;">
                                            <div
                                                style="color: #5a5520; font-size: 11px; font-weight: 500; margin-bottom: 4px;">
                                                Avg. Rate</div>
                                            <div id="room_sales_avg_rate_val"
                                                style="color: #3a3515; font-size: 18px; font-weight: 700;">₱0</div>
                                        </div>

                                        <!-- Revenue vs Yesterday -->
                                        <div
                                            style="background: #f8f8f5; border: 1px solid #d4d0b8; border-radius: 6px; padding: 10px; text-align: center;">
                                            <div
                                                style="color: #5a5520; font-size: 11px; font-weight: 500; margin-bottom: 4px;">
                                                vs Yesterday</div>
                                            <div id="room_sales_vs_yesterday_val"
                                                style="color: #6c757d; font-size: 18px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                                <span>—</span>
                                            </div>
                                        </div>

                                        <!-- Peak Hour -->
                                        <div
                                            style="background: #f8f8f5; border: 1px solid #d4d0b8; border-radius: 6px; padding: 10px; text-align: center;">
                                            <div
                                                style="color: #5a5520; font-size: 11px; font-weight: 500; margin-bottom: 4px;">
                                                Peak Hour</div>
                                            <div id="room_sales_peak_hour_val"
                                                style="color: #3a3515; font-size: 18px; font-weight: 700;">N/A</div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- Food Revenue Flowchart (Right Column) -->
                            <div
                                style="background: #fff; border: 1px solid #e0e6ff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                    <h4 style="margin: 0; font-size: 16px; color: #222; font-weight: 600;">Food Tracking
                                    </h4>
                                    <div style="position: relative;">
                                        <button id="addFoodBtn" onclick="toggleFoodDropdown()"
                                            style="background: #afad4cff; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; font-family: 'Poppins', sans-serif; transition: background 0.3s; display: flex; align-items: center; gap: 6px;"
                                            onmouseover="this.style.background='#8a8838ff'"
                                            onmouseout="this.style.background='#afad4cff'">
                                            <span style="font-size: 16px;">+</span>
                                            Add Food
                                        </button>
                                        <!-- Checkbox Container -->
                                        <div id="foodDropdownContainer"
                                            style="display: none; position: absolute; top: 100%; right: 0; margin-top: 8px; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 16px; min-width: 280px; max-height: 400px; z-index: 1000; overflow-y: auto;">
                                            <label
                                                style="display: block; margin-bottom: 12px; font-size: 14px; color: #333; font-weight: 600;">Select
                                                Food Items</label>
                                            
                                            <!-- Select All Checkbox -->
                                            <div style="margin-bottom: 12px; padding: 8px; background: #f5f5f5; border-radius: 6px;">
                                                <label style="display: flex; align-items: center; cursor: pointer; font-weight: 600; color: #555;">
                                                    <input type="checkbox" id="selectAllFoodCheckbox" onchange="toggleSelectAllFood()"
                                                        style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer; accent-color: #afad4cff;">
                                                    <span style="font-size: 13px;">Select All</span>
                                                </label>
                                            </div>
                                            
                                            <!-- Food Items Checkboxes Container -->
                                            <div id="foodCheckboxesContainer" style="margin-bottom: 12px; max-height: 250px; overflow-y: auto;">
                                                <div style="text-align: center; padding: 20px; color: #999; font-size: 13px;">
                                                    Loading food items...
                                                </div>
                                            </div>
                                            
                                            <button onclick="saveFoodSelection()"
                                                style="width: 100%; background: #afad4cff; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; font-family: 'Poppins', sans-serif; transition: background 0.3s;"
                                                onmouseover="this.style.background='#8a8838ff'"
                                                onmouseout="this.style.background='#afad4cff'">
                                                Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Flowchart Structure -->
                                <div style="display: flex; flex-direction: column; gap: 12px;">

                                    <!-- Total Food (Top Level) -->
                                    <div
                                        style="background: linear-gradient(135deg, #afad4cff 0%, #8a8838ff 100%); border-radius: 8px; padding: 16px; text-align: center; box-shadow: 0 2px 6px rgba(175, 173, 76, 0.3);">
                                        <div style="color: #fff; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
                                            Total Food</div>
                                        <div id="totalFoodRevenue" style="color: #fff; font-size: 24px; font-weight: 700;">
                                            ₱0</div>
                                    </div>

                                    <!-- Connector Line -->
                                    <div id="foodConnectorLine"
                                        style="width: 2px; height: 12px; background: linear-gradient(to bottom, #afad4cff, #e0e6ff); margin: 0 auto; display: none;">
                                    </div>

                                    <!-- Food Items (Second Level) - Dynamic -->
                                    <div id="foodItemsContainer"
                                        style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                        <!-- Food items will be loaded dynamically here -->
                                        <div
                                            style="grid-column: 1 / -1; text-align: center; padding: 20px; color: #999; font-size: 13px;">
                                            No food items added yet. Click "Add Food" to get started.
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    <?php endif; ?>





                    <!-- Revenue Overview -->
                    <div id="revenueOverview"
                        style="margin-top: 32px; background: #fafafa; border-radius: 8px; padding: 24px; border: 1px solid #eee; margin-bottom: 30px;">
                        <div
                            style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:16px; align-items:center;">
                            <h3 style="margin:0; font-size:18px; color:#222;">Revenue Overview</h3>
                            <div><strong>Total Revenue:</strong> <span id="selectedRangeTotal"
                                    style="font-size: 24px; font-weight: 700; color: #4CAF50;">₱0.00</span></div>
                        </div>
                        <div style="margin-top:24px; overflow-x:auto;">
                            <table
                                style="width:100%; border-collapse:collapse; font-family:'Poppins',sans-serif; font-size:14px;">
                                <thead>
                                    <tr style="background:#FFF3C5;">
                                        <th style="text-align:left; padding:10px;">Booking ID</th>
                                        <th style="text-align:left; padding:10px;">Guest</th>
                                        <th style="text-align:left; padding:10px;">Payment</th>
                                        <th style="text-align:left; padding:10px;">Reference No.</th>
                                        <th style="text-align:left; padding:10px;">Status</th>
                                        <th style="text-align:left; padding:10px;">Checked-Out At</th>
                                        <th style="text-align:left; padding:10px;">Payment Date</th>
                                        <th style="text-align:left; padding:10px;">Total Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="bookingRevenueTable">
                                    <tr>
                                        <td colspan="8" style="text-align:center; padding:16px; color:#777;">Loading
                                            revenue data...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div style="margin-top: 32px;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                            <h3 style="margin:0; font-size:18px; color:#222;">Additional Fees Overview</h3>
                            <small id="additionalFeesCount" style="color:#666;">0 record(s)</small>
                        </div>
                        <div style="overflow-x:auto; margin:0 -24px; padding:0 24px;">
                            <table
                                style="width:100%; border-collapse:collapse; font-family:'Poppins',sans-serif; font-size:14px; table-layout:auto;">
                                <thead>
                                    <tr style="background:#FFF3C5;">
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            Booking ID</th>
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            Guest</th>
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            Room</th>
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            Payment</th>
                                        <th style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd;">
                                            Missing Items</th>
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            Total Fee</th>
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            Date</th>
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            Status</th>
                                    </tr>
                                </thead>
                                <tbody id="additionalFeesTableBody">
                                    <tr>
                                        <td colspan="8" style="text-align:center; padding:16px; color:#777;">Loading
                                            additional fees...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Supplier Report -->
                    <div style="margin-top: 32px;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                            <h3 style="margin:0; font-size:18px; color:#222;">Supplier Report</h3>
                            <small id="supplierReportCount" style="color:#666;">0 record(s)</small>
                        </div>
                        <div style="overflow-x:auto; margin:0 -24px; padding:0 24px;">
                            <table
                                style="width:100%; border-collapse:collapse; font-family:'Poppins',sans-serif; font-size:14px; table-layout:auto;">
                                <thead>
                                    <tr style="background:#FFF3C5;">
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            PO Number</th>
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            Supplier</th>
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            Date</th>
                                        <th style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd;">
                                            Items</th>
                                        <th style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd;">
                                            Quantity</th>
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            Total</th>
                                        <th
                                            style="text-align:left; padding:8px 6px; border-bottom:1px solid #ddd; white-space:nowrap;">
                                            Status</th>
                                    </tr>
                                </thead>
                                <tbody id="supplierReportTableBody">
                                    <tr>
                                        <td colspan="7" style="text-align:center; padding:16px; color:#777;">Loading
                                            supplier data...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Export Report Modal -->
    <div id="exportModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 500px;">
            <h2 class="modal-title">Export Report</h2>
            <p style="margin: 0 0 24px 0; color: #666; font-size: 14px;">Choose your date to export</p>
            <div class="modal-content">
                <div class="form-group">
                    <label class="form-label">Start</label>
                    <input type="date" id="exportStartDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #c4c008ff; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <div class="form-group">
                    <label class="form-label">End</label>
                    <input type="date" id="exportEndDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #c4c008ff; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <button id="closeExportModalBtn" class="modal-btn-back"
                    style="background: white; color: #222; border: 1px solid #bbbe00ff; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; width: 100%; margin-top: 12px;">
                    Back
                </button>
            </div>
            <div style="margin-top: 24px;">
                <button id="exportReportBtn" class="modal-btn-next"
                    style="background: #a0af4cff; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; width: 100%;">
                    Export
                </button>
            </div>
        </div>
    </div>

    <!-- Daily Sales Modal -->
    <div id="dailySalesModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 500px;">
            <h2 class="modal-title">Daily Sales</h2>
            <p style="margin: 0 0 24px 0; color: #666; font-size: 14px;">Choose a date to export</p>
            <div class="modal-content">
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" id="dailySalesStartDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #c4c008ff; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <button id="closeDailySalesModalBtn" class="modal-btn-back"
                    style="background: white; color: #222; border: 1px solid #bbbe00ff; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; width: 100%; margin-top: 12px;">
                    Back
                </button>
            </div>
            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <?php
                // Only show Export button for super_admin, admin, and auditor
                $userAccessLevel = $_SESSION['access_level'] ?? 'user';
                $canExport = in_array($userAccessLevel, ['super_admin', 'admin', 'auditor']);
                if ($canExport):
                    ?>
                    <button id="dailySalesExportBtn" class="modal-btn-next"
                        style="background: #a0af4cff; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                        Export
                    </button>
                <?php endif; ?>
                <button id="dailySalesViewPdfBtn" class="modal-btn-next"
                    style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                    View PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Daily Sales 2.0 Modal -->
    <div id="dailySales2Modal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 500px;">
            <h2 class="modal-title">Daily Sales 2.0</h2>
            <p style="margin: 0 0 24px 0; color: #666; font-size: 14px;">Choose a date range to export</p>
            <div class="modal-content">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" id="dailySales2StartDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #c4c008ff; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <div class="form-group" style="margin-top: 16px;">
                    <label class="form-label">End Date</label>
                    <input type="date" id="dailySales2EndDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #c4c008ff; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <button id="closeDailySales2ModalBtn" class="modal-btn-back"
                    style="background: white; color: #222; border: 1px solid #bbbe00ff; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; width: 100%; margin-top: 12px;">
                    Back
                </button>
            </div>
            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <?php
                // Only show Export button for super_admin, admin, and auditor
                if ($canExport):
                    ?>
                    <button id="dailySales2ExportBtn" class="modal-btn-next"
                        style="background: #a0af4cff; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                        Export
                    </button>
                <?php endif; ?>
                <button id="dailySales2ViewPdfBtn" class="modal-btn-next"
                    style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                    View PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Type Report Modal -->
    <div id="paymentTypeReportModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 500px;">
            <h2 class="modal-title">Per Payment Report</h2>
            <p style="margin: 0 0 24px 0; color: #666; font-size: 14px;">Choose your date to export</p>
            <div class="modal-content">
                <div class="form-group">
                    <label class="form-label">Start</label>
                    <input type="date" id="paymentTypeStartDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #4c9faf; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <div class="form-group">
                    <label class="form-label">End</label>
                    <input type="date" id="paymentTypeEndDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #4c9faf; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <button id="closePaymentTypeReportModalBtn" class="modal-btn-back"
                    style="background: white; color: #222; border: 1px solid #4c9faf; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; width: 100%; margin-top: 12px;">
                    Back
                </button>
            </div>
            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <?php
                // Only show Export button for super_admin, admin, and auditor
                if ($canExport):
                    ?>
                    <button id="paymentTypeExportBtn" class="modal-btn-next"
                        style="background: #4c9faf; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                        Export
                    </button>
                <?php endif; ?>
                <button id="paymentTypeViewPdfBtn" class="modal-btn-next"
                    style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                    View PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Type Raw Report Modal -->
    <div id="paymentTypeRawReportModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 500px;">
            <h2 class="modal-title">Payment Type Report</h2>
            <p style="margin: 0 0 24px 0; color: #666; font-size: 14px;">Choose your date to export</p>
            <div class="modal-content">
                <div class="form-group">
                    <label class="form-label">Start</label>
                    <input type="date" id="paymentTypeRawStartDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #4c9faf; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <div class="form-group">
                    <label class="form-label">End</label>
                    <input type="date" id="paymentTypeRawEndDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #4c9faf; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <button id="closePaymentTypeRawReportModalBtn" class="modal-btn-back"
                    style="background: white; color: #222; border: 1px solid #4c9faf; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; width: 100%; margin-top: 12px;">
                    Back
                </button>
            </div>
            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <?php
                // Only show Export button for super_admin, admin, and auditor
                if ($canExport):
                    ?>
                    <button id="paymentTypeRawExportBtn" class="modal-btn-next"
                        style="background: #4c9faf; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                        Export
                    </button>
                <?php endif; ?>
                <button id="paymentTypeRawViewPdfBtn" class="modal-btn-next"
                    style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                    View PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Detailed Booking Report Modal -->
    <div id="detailedBookingReportModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 500px;">
            <h2 class="modal-title">Detailed Booking Report</h2>
            <p style="margin: 0 0 24px 0; color: #666; font-size: 14px;">Choose your date to export</p>
            <div class="modal-content">
                <div class="form-group">
                    <label class="form-label">Start</label>
                    <input type="date" id="detailedBookingStartDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #c4c008ff; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <div class="form-group">
                    <label class="form-label">End</label>
                    <input type="date" id="detailedBookingEndDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #c4c008ff; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <button id="closeDetailedBookingReportModalBtn" class="modal-btn-back"
                    style="background: white; color: #222; border: 1px solid #bbbe00ff; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; width: 100%; margin-top: 12px;">
                    Back
                </button>
            </div>
            <div style="margin-top: 24px;">
                <button id="detailedBookingExportBtn" class="modal-btn-next"
                    style="background: #a0af4cff; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; width: 100%;">
                    Export
                </button>
            </div>
        </div>
    </div>

    <!-- Customer Details Report Modal -->
    <div id="customDetailReportModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 500px;">
            <h2 class="modal-title">Customer Details Report</h2>
            <p style="margin: 0 0 24px 0; color: #666; font-size: 14px;">Choose your date to export</p>
            <div class="modal-content">
                <div class="form-group">
                    <label class="form-label">Start</label>
                    <input type="date" id="customerDetailsStartDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #c4c008ff; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <div class="form-group">
                    <label class="form-label">End</label>
                    <input type="date" id="customerDetailsEndDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #c4c008ff; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <button id="closeCustomerDetailsReportModalBtn" class="modal-btn-back"
                    style="background: white; color: #222; border: 1px solid #bbbe00ff; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; width: 100%; margin-top: 12px;">
                    Back
                </button>
            </div>
            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <?php if ($canExport): ?>
                    <button id="customerDetailsExportBtn" class="modal-btn-next"
                        style="background: #a0af4cff; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                        Export
                    </button>
                <?php endif; ?>
                <button id="customerDetailsViewPdfBtn" class="modal-btn-next"
                    style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                    View PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Running Sales Report Modal -->
    <div id="runningSalesReportModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 500px;">
            <h2 class="modal-title">Running Sales Report</h2>
            <p style="margin: 0 0 24px 0; color: #666; font-size: 14px;">Choose your date to export cash deposit reports</p>
            <div class="modal-content">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" id="runningSalesStartDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #c4c008ff; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" id="runningSalesEndDate" class="form-input"
                        style="padding: 10px 16px; border: 1px solid #c4c008ff; border-radius: 6px; font-family: 'Poppins', sans-serif; width: 100%; box-sizing: border-box;">
                </div>
                <button id="closeRunningSalesReportModalBtn" class="modal-btn-back"
                    style="background: white; color: #222; border: 1px solid #bbbe00ff; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; width: 100%; margin-top: 12px;">
                    Back
                </button>
            </div>
            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <?php if ($canExport): ?>
                    <button id="runningSalesExportBtn" class="modal-btn-next"
                        style="background: #a0af4cff; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                        Export
                    </button>
                <?php endif; ?>
                <button id="runningSalesViewPdfBtn" class="modal-btn-next"
                    style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; font-family: 'Poppins', sans-serif; flex: 1;">
                    View PDF
                </button>
            </div>
        </div>
    </div>

    <script>

        let selectedRevenueRange = 'today';
        let customRevenueRange = { start: '', end: '' };

        function navigateToPage(page) {
            // Prevent navigation if already on the current page
            const currentPage = window.location.pathname.split('/').pop().toLowerCase();
            const targetPage = page.toLowerCase();
            if (currentPage === targetPage) {
                return; // Don't reload if we're already on this page
            }

            // Close sidebar on mobile when navigating
            if (window.innerWidth <= 1024) {
                toggleSidebar();
            }
            window.location.href = page;
        }

        // Toggle mobile sidebar
        function toggleSidebar() {
            const panel = document.querySelector('.left-panel');
            const overlay = document.querySelector('.mobile-menu-overlay');

            if (panel) {
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
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (event) {
            const panel = document.querySelector('.left-panel');
            const toggle = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth <= 1024 && panel && panel.classList.contains('open')) {
                if (!panel.contains(event.target) && !toggle.contains(event.target)) {
                    toggleSidebar();
                }
            }
        });

        // Toggle Maintenance menu
        function toggleSystemMaintenance() {
            const submenu = document.getElementById('systemMaintenanceSubmenu');
            const menuItem = document.getElementById('systemMaintenanceMenu');
            const isOpen = submenu.classList.contains('open');

            if (isOpen) {
                submenu.classList.remove('open');
                menuItem.classList.remove('expanded');
            } else {
                submenu.classList.add('open');
                menuItem.classList.add('expanded');
            }
        }

        function setActiveMenuItem() {
            const currentPage = window.location.pathname.split('/').pop() || 'Report.php';
            const menuItems = document.querySelectorAll('.sidebar-menu-item:not(.collapsible-menu)');
            const submenuItems = document.querySelectorAll('.submenu-item');

            // Check if current page is in Maintenance submenu
            const isSystemMaintenancePage = ['inventory.html', 'PurchaseOrder.html', 'AddItem.php'].includes(currentPage);

            if (isSystemMaintenancePage) {
                // Expand Maintenance menu
                const submenu = document.getElementById('systemMaintenanceSubmenu');
                const menuItem = document.getElementById('systemMaintenanceMenu');
                if (submenu && menuItem) {
                    submenu.classList.add('open');
                    menuItem.classList.add('expanded');
                }

                // Set active submenu item
                submenuItems.forEach(item => {
                    const pageName = item.getAttribute('data-page');
                    if (pageName === currentPage) {
                        item.classList.add('active');
                    } else {
                        item.classList.remove('active');
                    }
                });
            } else {
                // Set active regular menu item
                menuItems.forEach(item => {
                    const pageName = item.getAttribute('data-page');
                    if (pageName === currentPage) {
                        item.classList.add('active');
                    } else {
                        item.classList.remove('active');
                    }
                });
            }
        }


        async function loadReportStats() {
            try {
                const params = new URLSearchParams();
                params.append('range', selectedRevenueRange);
                if (selectedRevenueRange === 'custom' && customRevenueRange.start && customRevenueRange.end) {
                    params.append('start_date', customRevenueRange.start);
                    params.append('end_date', customRevenueRange.end);
                }
                const response = await fetch(`get_report_stats.php?${params.toString()}`);
                const result = await response.json();

                if (result.success) {
                    const stats = result.stats || {};
                    document.getElementById('checkInCount').textContent = stats.check_in || 0;
                    document.getElementById('checkOutCount').textContent = stats.check_out || 0;
                    document.getElementById('totalRoomsCount').textContent = stats.total_rooms || 0;
                    document.getElementById('canceledCount').textContent = stats.canceled || 0;
                    document.getElementById('promoCount').textContent = stats.promo || 0;
                    document.getElementById('walkinCount').textContent = stats.walkin || 0;
                    document.getElementById('reservationCount').textContent = stats.reservation || 0;
                    document.getElementById('availableRoomsCount').textContent = stats.available_rooms || 0;
                    document.getElementById('outOfOrderRoomsCount').textContent = stats.out_of_order_rooms || 0;

                    // Update guest type counts
                    //document.getElementById('guestTypeSolo').textContent = stats.guest_type_solo || 0;
                    //document.getElementById('guestTypeDuo').textContent = stats.guest_type_duo || 0;
                    //document.getElementById('guestTypeFamily').textContent = stats.guest_type_family || 0;
                    //document.getElementById('guestTypeGroup').textContent = stats.guest_type_group || 0;
                    //document.getElementById('guestTypeCompany').textContent = stats.guest_type_company || 0;

                    updateSelectedRangeSummary(stats.selected_range);
                    renderBookingRevenueTable(result.booking_amounts || []);
                    updateOccupancyChart(stats.occupancy || {});
                    renderUpcomingCheckIns(result.upcoming_checkins || []);
                    renderUpcomingCheckOuts(result.upcoming_checkouts || []);

                    // Load food tracking data
                    loadFoodTracking();

                    // Load room sales tracking data
                    loadRoomSalesTracking();

                    // Debug logging for payment methods
                    console.log('=== PAYMENT METHOD DEBUG ===');
                    console.log('Payment Methods Data:', stats.payment_methods);
                    console.log('Debug Info:', result.debug);
                    console.log('===========================');
                } else {
                    console.error('Error loading report stats:', result.message);
                    alert('Error loading statistics: ' + result.message);
                }
            } catch (error) {
                console.error('Error loading report stats:', error);
                alert('Error loading statistics: ' + error.message);
            }
        }

        // Load food tracking data
        async function loadFoodTracking() {
            try {
                const params = new URLSearchParams();
                params.append('range', selectedRevenueRange);
                if (selectedRevenueRange === 'custom' && customRevenueRange.start && customRevenueRange.end) {
                    params.append('start_date', customRevenueRange.start);
                    params.append('end_date', customRevenueRange.end);
                }
                const response = await fetch(`get_food_tracking.php?${params.toString()}`);
                const result = await response.json();

                if (result.success) {
                    updateFoodTrackingDisplay(result.food_counts || []);
                } else {
                    console.error('Error loading food tracking:', result.message);
                }
            } catch (error) {
                console.error('Error loading food tracking:', error);
            }
        }

        // Update food tracking display
        function updateFoodTrackingDisplay(foodCounts) {
            const container = document.getElementById('foodItemsContainer');
            const connectorLine = document.getElementById('foodConnectorLine');
            const totalRevenueElem = document.getElementById('totalFoodRevenue');

            if (!container) return;

            // Merge with existing added food items from localStorage
            const savedItems = localStorage.getItem('reportFoodItems');
            let addedItems = [];
            if (savedItems) {
                try {
                    addedItems = JSON.parse(savedItems);
                } catch (error) {
                    console.error('Error parsing saved food items:', error);
                    addedItems = [];
                }
            }

            // Create a map of food items with their counts from database
            const foodMap = {};
            foodCounts.forEach(item => {
                const foodName = item.food_name.toUpperCase();
                foodMap[foodName] = item.count;
            });

            // Update counts for added items based on database data
            addedItems.forEach(item => {
                const foodName = item.food_name.toUpperCase();
                if (foodMap[foodName] !== undefined) {
                    item.count = foodMap[foodName];
                    delete foodMap[foodName]; // Remove from map so we don't duplicate
                } else {
                    item.count = 0; // Not found in current date range
                }
            });

            // Add any remaining items from database that weren't in the added items
            Object.keys(foodMap).forEach(foodName => {
                addedItems.push({
                    food_id: 'db_' + foodName,
                    food_name: foodName,
                    count: foodMap[foodName]
                });
            });

            // Filter out items with zero count (optional - you can keep them to show 0)
            // addedItems = addedItems.filter(item => item.count > 0);

            if (addedItems.length === 0) {
                container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 20px; color: #999; font-size: 13px;">No food items tracked in this date range.</div>';
                connectorLine.style.display = 'none';
                if (totalRevenueElem) totalRevenueElem.textContent = '0';
                return;
            }

            // Show connector line
            connectorLine.style.display = 'block';

            // Clear container
            container.innerHTML = '';

            // Render each food item
            addedItems.forEach(item => {
                const itemDiv = document.createElement('div');
                itemDiv.style.position = 'relative';
                itemDiv.innerHTML = `
                    <div style="position: absolute; top: -12px; left: 50%; width: 2px; height: 12px; background: #e0e6ff; transform: translateX(-50%);"></div>
                    <div style="background: #f8f8f5; border: 2px solid #d4d0b8; border-radius: 6px; padding: 10px; text-align: center; transition: all 0.2s; position: relative;" 
                         onmouseover="this.style.borderColor='#afad4cff'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.1)';" 
                         onmouseout="this.style.borderColor='#d4d0b8'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <button onclick="removeFoodItem('${item.food_id}')" style="position: absolute; top: 4px; right: 4px; background: #e57373; color: white; border: none; border-radius: 3px; width: 18px; height: 18px; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; line-height: 1; transition: background 0.2s;" onmouseover="this.style.background='#ef5350'" onmouseout="this.style.background='#e57373'" title="Remove item">×</button>
                        <div style="color: #5a5520; font-size: 11px; font-weight: 600; margin-bottom: 4px;">${item.food_name}</div>
                        <div style="color: #3a3515; font-size: 16px; font-weight: 700;">${item.count}</div>
                    </div>
                `;
                container.appendChild(itemDiv);
            });

            // Calculate and display total food count (sum of all item quantities)
            const totalCount = addedItems.reduce((sum, item) => sum + (item.count || 0), 0);
            if (totalRevenueElem) totalRevenueElem.textContent = totalCount;
        }

        // Load Room Sales Tracking data
        async function loadRoomSalesTracking() {
            try {
                const params = new URLSearchParams();
                params.append('range', selectedRevenueRange);
                if (selectedRevenueRange === 'custom' && customRevenueRange.start && customRevenueRange.end) {
                    params.append('start_date', customRevenueRange.start);
                    params.append('end_date', customRevenueRange.end);
                }
                const response = await fetch(`get_room_sales_tracking.php?${params.toString()}`);
                const result = await response.json();

                if (result.success) {
                    updateRoomSalesDisplay(result.counts || {}, result.stats || {});
                } else {
                    console.error('Error loading room sales tracking:', result.message);
                }
            } catch (error) {
                console.error('Error loading room sales tracking:', error);
            }
        }

        // Update Room Sales table and stats display
        function updateRoomSalesDisplay(counts, stats) {
            const categories = ['3h', '6h', '12h', '24h', '36h', '48h'];
            const mappedCategories = {
                '3h': '3hrs',
                '6h': '6hrs',
                '12h': '12hrs',
                '24h': '24hrs',
                '36h': '36hrs',
                '48h': '48hrs'
            };

            // Update table cells
            categories.forEach(cat => {
                const dbCat = mappedCategories[cat];
                const catData = counts[dbCat] || { AM: 0, PM: 0, Total: 0 };

                const totalElem = document.getElementById(`room_sales_total_${cat}`);
                const amElem = document.getElementById(`room_sales_am_${cat}`);
                const pmElem = document.getElementById(`room_sales_pm_${cat}`);

                if (totalElem) totalElem.textContent = catData.Total;
                if (amElem) amElem.textContent = catData.AM;
                if (pmElem) pmElem.textContent = catData.PM;
            });

            // Update Quick Stats values
            const totalBookingsElem = document.getElementById('room_sales_total_bookings_val');
            const avgRateElem = document.getElementById('room_sales_avg_rate_val');
            const vsYesterdayElem = document.getElementById('room_sales_vs_yesterday_val');
            const peakHourElem = document.getElementById('room_sales_peak_hour_val');

            if (totalBookingsElem) {
                totalBookingsElem.textContent = stats.total_bookings !== undefined ? stats.total_bookings : 0;
            }

            if (avgRateElem) {
                const avgRate = parseFloat(stats.avg_rate || 0);
                avgRateElem.textContent = '₱' + Math.round(avgRate).toLocaleString('en-PH');
            }

            if (vsYesterdayElem) {
                const trend = stats.revenue_change_trend || 'flat';
                const percent = stats.revenue_change_percent !== undefined ? stats.revenue_change_percent : 0;

                let trendIcon = '—';
                let trendColor = '#6c757d'; // Gray for flat

                if (trend === 'up') {
                    trendIcon = '↑';
                    trendColor = '#4CAF50'; // Green
                } else if (trend === 'down') {
                    trendIcon = '↓';
                    trendColor = '#dc3545'; // Red
                }

                if (trend === 'flat') {
                    vsYesterdayElem.style.color = trendColor;
                    vsYesterdayElem.innerHTML = `<span>—</span>`;
                } else {
                    vsYesterdayElem.style.color = trendColor;
                    vsYesterdayElem.innerHTML = `<span>${trendIcon}</span> <span>${percent}%</span>`;
                }
            }

            if (peakHourElem) {
                peakHourElem.textContent = stats.peak_hour || 'N/A';
            }
        }


        function exportToExcel() {
            // Show the export modal
            const modal = document.getElementById('exportModal');
            if (modal) {
                modal.style.display = 'flex';

                // Pre-fill dates if custom range is selected
                const startInput = document.getElementById('exportStartDate');
                const endInput = document.getElementById('exportEndDate');
                if (selectedRevenueRange === 'custom' && customRevenueRange.start && customRevenueRange.end) {
                    if (startInput) startInput.value = customRevenueRange.start;
                    if (endInput) endInput.value = customRevenueRange.end;
                } else {
                    // Clear inputs if not custom
                    if (startInput) startInput.value = '';
                    if (endInput) endInput.value = '';
                }
            }
        }

        function performExport() {
            const startInput = document.getElementById('exportStartDate');
            const endInput = document.getElementById('exportEndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            // Validate that end date is not before start date
            if (new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date.');
                return;
            }

            // Close modal
            const modal = document.getElementById('exportModal');
            if (modal) {
                modal.style.display = 'none';
            }

            // Export with selected dates
            const params = new URLSearchParams();
            params.append('range', 'custom');
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.location.href = `export_report.php?${params.toString()}`;
        }

        function closeExportModal() {
            const modal = document.getElementById('exportModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Reports Dropdown Toggle Function
        function toggleReportsDropdown() {
            const dropdown = document.getElementById('reportsDropdownMenu');
            const btn = document.getElementById('reportsDropdownBtn');
            if (dropdown) {
                const isVisible = dropdown.style.display === 'block';
                dropdown.style.display = isVisible ? 'none' : 'block';

                // Rotate arrow icon
                const arrow = btn.querySelector('svg');
                if (arrow) {
                    arrow.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
                }
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (event) {
            const dropdown = document.getElementById('reportsDropdownMenu');
            const btn = document.getElementById('reportsDropdownBtn');
            if (dropdown && btn) {
                if (!btn.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.style.display = 'none';
                    const arrow = btn.querySelector('svg');
                    if (arrow) {
                        arrow.style.transform = 'rotate(0deg)';
                    }
                }
            }
        });

        function openDailySalesModal() {
            const modal = document.getElementById('dailySalesModal');
            if (modal) {
                modal.style.display = 'flex';
                // Reset inputs
                const startInput = document.getElementById('dailySalesStartDate');
                const endInput = document.getElementById('dailySalesEndDate');
                if (startInput) startInput.value = '';
                if (endInput) endInput.value = '';

                // Auto-sync: When start date changes, copy to end date
                if (startInput && endInput) {
                    startInput.addEventListener('change', function () {
                        endInput.value = startInput.value;
                    });
                }
            }
        }

        function closeDailySalesModal() {
            const modal = document.getElementById('dailySalesModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function performDailySalesExport() {
            const startInput = document.getElementById('dailySalesStartDate');
            const startDate = startInput?.value || '';

            if (!startDate) {
                alert('Please select a date.');
                return;
            }

            closeDailySalesModal();

            const params = new URLSearchParams();
            params.append('range', 'custom');
            params.append('start_date', startDate);
            params.append('end_date', startDate); // Use same date for both
            window.location.href = `export_daily_sales.php?${params.toString()}`;
        }

        function viewDailySalesPdf() {
            const startInput = document.getElementById('dailySalesStartDate');
            const startDate = startInput?.value || '';

            if (!startDate) {
                alert('Please select a date.');
                return;
            }

            closeDailySalesModal();

            const params = new URLSearchParams();
            params.append('range', 'custom');
            params.append('start_date', startDate);
            params.append('end_date', startDate); // Use same date for both
            window.open(`export_daily_sales_pdf.php?${params.toString()}`, '_blank');
        }

        // Daily Sales 2.0 Modal Functions
        function openDailySales2Modal() {
            const modal = document.getElementById('dailySales2Modal');
            if (modal) {
                modal.style.display = 'flex';
                // Reset inputs
                const startInput = document.getElementById('dailySales2StartDate');
                const endInput = document.getElementById('dailySales2EndDate');
                if (startInput) startInput.value = '';
                if (endInput) endInput.value = '';
            }
        }

        function closeDailySales2Modal() {
            const modal = document.getElementById('dailySales2Modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function performDailySales2Export() {
            const startInput = document.getElementById('dailySales2StartDate');
            const endInput = document.getElementById('dailySales2EndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            if (new Date(startDate) > new Date(endDate)) {
                alert('Start date cannot be later than end date.');
                return;
            }

            closeDailySales2Modal();

            // Export to Excel
            const params = new URLSearchParams();
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.open(`export_daily_sales2.php?${params.toString()}`, '_blank');
        }

        function viewDailySales2Pdf() {
            const startInput = document.getElementById('dailySales2StartDate');
            const endInput = document.getElementById('dailySales2EndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            if (new Date(startDate) > new Date(endDate)) {
                alert('Start date cannot be later than end date.');
                return;
            }

            closeDailySales2Modal();

            const params = new URLSearchParams();
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.open(`export_daily_sales2_pdf.php?${params.toString()}`, '_blank');
        }

        function formatCurrency(value) {
            const number = parseFloat(value) || 0;
            return '\u20B1' + number.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Payment Type Report Modal Functions
        function openPaymentTypeReportModal() {
            const modal = document.getElementById('paymentTypeReportModal');
            if (modal) {
                modal.style.display = 'flex';
                // Reset inputs
                const startInput = document.getElementById('paymentTypeStartDate');
                const endInput = document.getElementById('paymentTypeEndDate');
                if (startInput) startInput.value = '';
                if (endInput) endInput.value = '';
            }
        }

        function closePaymentTypeReportModal() {
            const modal = document.getElementById('paymentTypeReportModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function performPaymentTypeExport() {
            const startInput = document.getElementById('paymentTypeStartDate');
            const endInput = document.getElementById('paymentTypeEndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            if (new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date.');
                return;
            }

            closePaymentTypeReportModal();

            const params = new URLSearchParams();
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.location.href = `export_payment_type_report.php?${params.toString()}`;
        }

        function viewPaymentTypeReportPdf() {
            const startInput = document.getElementById('paymentTypeStartDate');
            const endInput = document.getElementById('paymentTypeEndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            if (new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date.');
                return;
            }

            closePaymentTypeReportModal();

            const params = new URLSearchParams();
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.open(`export_payment_type_report_pdf.php?${params.toString()}`, '_blank');
        }

        // Payment Type Raw Report Modal Functions
        function openPaymentTypeRawReportModal() {
            const modal = document.getElementById('paymentTypeRawReportModal');
            if (modal) {
                modal.style.display = 'flex';
                // Reset inputs
                const startInput = document.getElementById('paymentTypeRawStartDate');
                const endInput = document.getElementById('paymentTypeRawEndDate');
                if (startInput) startInput.value = '';
                if (endInput) endInput.value = '';
            }
        }

        function closePaymentTypeRawReportModal() {
            const modal = document.getElementById('paymentTypeRawReportModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function performPaymentTypeRawExport() {
            const startInput = document.getElementById('paymentTypeRawStartDate');
            const endInput = document.getElementById('paymentTypeRawEndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            if (new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date.');
                return;
            }

            closePaymentTypeRawReportModal();

            const params = new URLSearchParams();
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.location.href = `export_payment_type_raw.php?${params.toString()}`;
        }

        function viewPaymentTypeRawReportPdf() {
            const startInput = document.getElementById('paymentTypeRawStartDate');
            const endInput = document.getElementById('paymentTypeRawEndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            if (new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date.');
                return;
            }

            closePaymentTypeRawReportModal();

            const params = new URLSearchParams();
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.open(`export_payment_type_raw_pdf.php?${params.toString()}`, '_blank');
        }

        // Detailed Booking Report Modal Functions
        function openDetailedBookingReportModal() {
            const modal = document.getElementById('detailedBookingReportModal');
            if (modal) {
                modal.style.display = 'flex';
                // Reset inputs
                const startInput = document.getElementById('detailedBookingStartDate');
                const endInput = document.getElementById('detailedBookingEndDate');
                if (startInput) startInput.value = '';
                if (endInput) endInput.value = '';
            }
        }

        function closeDetailedBookingReportModal() {
            const modal = document.getElementById('detailedBookingReportModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function performDetailedBookingExport() {
            const startInput = document.getElementById('detailedBookingStartDate');
            const endInput = document.getElementById('detailedBookingEndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            if (new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date.');
                return;
            }

            closeDetailedBookingReportModal();

            const params = new URLSearchParams();
            params.append('range', 'custom');
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.location.href = `export_detailed_booking_report.php?${params.toString()}`;
        }

        // Customer Details Report Modal Functions
        function openCustomerDetailsReportModal() {
            const modal = document.getElementById('customDetailReportModal');
            if (modal) {
                modal.style.display = 'flex';
                // Reset inputs
                const startInput = document.getElementById('customerDetailsStartDate');
                const endInput = document.getElementById('customerDetailsEndDate');
                if (startInput) startInput.value = '';
                if (endInput) endInput.value = '';
            }
        }

        function closeCustomerDetailsReportModal() {
            const modal = document.getElementById('customDetailReportModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function performCustomerDetailsExport() {
            const startInput = document.getElementById('customerDetailsStartDate');
            const endInput = document.getElementById('customerDetailsEndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            if (new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date.');
                return;
            }

            closeCustomerDetailsReportModal();

            const params = new URLSearchParams();
            params.append('range', 'custom');
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.location.href = `export_customer_details.php?${params.toString()}`;
        }

        function viewCustomerDetailsPdf() {
            const startInput = document.getElementById('customerDetailsStartDate');
            const endInput = document.getElementById('customerDetailsEndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            if (new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date.');
                return;
            }

            closeCustomerDetailsReportModal();

            const params = new URLSearchParams();
            params.append('range', 'custom');
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.open(`export_customer_details_pdf.php?${params.toString()}`, '_blank');
        }

        // Running Sales Report Modal Functions
        function openRunningSalesReportModal() {
            const modal = document.getElementById('runningSalesReportModal');
            if (modal) {
                modal.style.display = 'flex';
                // Reset inputs and set default dates
                const startInput = document.getElementById('runningSalesStartDate');
                const endInput = document.getElementById('runningSalesEndDate');
                const today = new Date().toISOString().split('T')[0];
                if (startInput) startInput.value = today;
                if (endInput) endInput.value = today;
            }
        }

        function closeRunningSalesReportModal() {
            const modal = document.getElementById('runningSalesReportModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function performRunningSalesExport() {
            const startInput = document.getElementById('runningSalesStartDate');
            const endInput = document.getElementById('runningSalesEndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            if (new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date.');
                return;
            }

            closeRunningSalesReportModal();

            const params = new URLSearchParams();
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.location.href = `export_running_sales_report.php?${params.toString()}`;
        }

        function viewRunningSalesPdf() {
            const startInput = document.getElementById('runningSalesStartDate');
            const endInput = document.getElementById('runningSalesEndDate');
            const startDate = startInput?.value || '';
            const endDate = endInput?.value || '';

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            if (new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be before start date.');
                return;
            }

            closeRunningSalesReportModal();

            const params = new URLSearchParams();
            params.append('start_date', startDate);
            params.append('end_date', endDate);
            window.open(`export_running_sales_report_pdf.php?${params.toString()}`, '_blank');
        }

        function escapeHtml(text) {
            if (text == null) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatMissingItems(items) {
            if (!Array.isArray(items) || items.length === 0) {
                return '—';
            }
            return items.map(item => {
                const name = item.name || item; // Handle object or string
                return name;
            }).join(', ');
        }





        function updateSelectedRangeSummary(range = {}) {
            const labelEl = document.getElementById('selectedRangeLabel');
            const totalEl = document.getElementById('selectedRangeTotal');
            if (labelEl) {
                let dateLabel = '';
                if (range.start && range.end) {
                    dateLabel = ` (${range.start} - ${range.end})`;
                }
                labelEl.textContent = `${range.label || 'Today'}${dateLabel}`;
            }
            if (totalEl) {
                totalEl.textContent = formatCurrency(range.total || 0);
            }
        }

        function updateOccupancyChart(occupancy = {}) {
            const rate = occupancy.rate || 0;
            const occupied = occupancy.occupied || 0;
            const available = occupancy.available || 0;

            document.getElementById('occupancyRateValue').textContent = rate + '%';
            document.getElementById('occupiedCount').textContent = occupied;
            document.getElementById('availableCount').textContent = available;

            // Animate the circle
            const circle = document.getElementById('occupancyCircle');
            if (circle) {
                circle.style.transition = 'stroke-dasharray 1s ease-in-out';
                circle.setAttribute('stroke-dasharray', `${rate}, 100`);
            }
        }

        function renderUpcomingCheckIns(checkins = []) {
            const container = document.getElementById('upcomingCheckInsContainer');
            if (!container) return;

            if (checkins.length === 0) {
                container.innerHTML = '<div style="color: #777; padding: 8px 0;">No upcoming check-ins</div>';
                return;
            }

            container.innerHTML = checkins.map(item => {
                const rawDate = item.reservation_date || item.check_in;
                const checkInDate = rawDate
                    ? new Date(rawDate).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
                    : '';
                return `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f0f0f0;">
                        <div>
                            <span style="font-weight: 500;">${escapeHtml(item.guest_name)}</span>
                            <span style="color: #777; margin-left: 8px;">Room ${escapeHtml(item.room_id)}</span>
                        </div>
                        <div style="color: #4CAF50; font-weight: 500;">${checkInDate}</div>
                    </div>
                `;
            }).join('');
        }

        function renderUpcomingCheckOuts(checkouts = []) {
            const container = document.getElementById('upcomingCheckOutsContainer');
            if (!container) return;

            if (checkouts.length === 0) {
                container.innerHTML = '<div style="color: #777; padding: 8px 0;">No upcoming check-outs</div>';
                return;
            }

            container.innerHTML = checkouts.map(item => {
                const checkOutDate = new Date(item.checked_out_at || item.check_out).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
                return `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f0f0f0;">
                        <div>
                            <span style="font-weight: 500;">${escapeHtml(item.guest_name)}</span>
                            <span style="color: #777; margin-left: 8px;">Room ${escapeHtml(item.room_id)}</span>
                        </div>
                        <div style="color: #ff9800; font-weight: 500;">${checkOutDate}</div>
                    </div>
                `;
            }).join('');
        }

        function renderBookingRevenueTable(records = []) {
            const tbody = document.getElementById('bookingRevenueTable');
            if (!tbody) return;
            if (!records.length) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:16px; color:#777;">No booking data for this range.</td></tr>`;
                return;
            }
            tbody.innerHTML = '';
            records.forEach(record => {
                const tr = document.createElement('tr');
                const paymentMethod = escapeHtml(record.payment_method || record.payment_status || '—');
                const referenceDisplay = escapeHtml(record.reference_no || '—');
                const statusDisplay = escapeHtml(record.status || '—');
                const paymentDateDisplay = escapeHtml(record.payment_date_display || '—');
                const amountDisplay = record.total_amount_display || formatCurrency(record.total_amount || 0);

                tr.innerHTML = `
                    <td style="padding:10px; border-bottom:1px solid #eee;">${escapeHtml(record.booking_id || '—')}</td>
                    <td style="padding:10px; border-bottom:1px solid #eee;">${escapeHtml(record.guest_name || '—')}</td>
                    <td style="padding:10px; border-bottom:1px solid #eee;">${paymentMethod}</td>
                    <td style="padding:10px; border-bottom:1px solid #eee;">${referenceDisplay}</td>
                    <td style="padding:10px; border-bottom:1px solid #eee;">${statusDisplay}</td>
                    <td style="padding:10px; border-bottom:1px solid #eee; white-space:nowrap;"></td>
                    <td style="padding:10px; border-bottom:1px solid #eee; white-space:nowrap;">${paymentDateDisplay}</td>
                    <td style="padding:10px; border-bottom:1px solid #eee;">${amountDisplay}</td>
                `;
                const checkedOutCell = tr.children[5];
                if (checkedOutCell) {
                    checkedOutCell.innerHTML = record.check_out_display || '—';
                }
                tbody.appendChild(tr);
            });
        }

        function updateRangeDropdown() {
            const select = document.getElementById('dateRangeSelect');
            if (select) {
                select.value = selectedRevenueRange;
            }
            const customFields = document.getElementById('customRangeFields');
            if (customFields) {
                if (selectedRevenueRange === 'custom') {
                    customFields.classList.add('show');
                } else {
                    customFields.classList.remove('show');
                }
            }
        }

        function initRevenueFilters() {
            const select = document.getElementById('dateRangeSelect');
            if (select) {
                select.addEventListener('change', () => {
                    const range = select.value || 'today';
                    if (range !== 'custom') {
                        customRevenueRange = { start: '', end: '' };
                        const startInput = document.getElementById('customRangeStart');
                        const endInput = document.getElementById('customRangeEnd');
                        if (startInput) startInput.value = '';
                        if (endInput) endInput.value = '';
                    }
                    selectedRevenueRange = range;
                    updateRangeDropdown();
                    loadReportStats();
                    loadAdditionalFees();
                    loadSupplierReport();
                });
            }
            const applyBtn = document.getElementById('applyCustomRangeBtn');
            if (applyBtn) {
                applyBtn.addEventListener('click', () => {
                    const start = document.getElementById('customRangeStart')?.value;
                    const end = document.getElementById('customRangeEnd')?.value;
                    if (!start || !end) {
                        alert('Please select both start and end dates.');
                        return;
                    }
                    customRevenueRange = { start, end };
                    selectedRevenueRange = 'custom';
                    updateRangeDropdown();
                    loadReportStats();
                    loadAdditionalFees();
                    loadSupplierReport();
                });
            }
            updateRangeDropdown();
        }


        async function loadAdditionalFees() {
            const tbody = document.getElementById('additionalFeesTableBody');
            const countLabel = document.getElementById('additionalFeesCount');
            try {
                const params = new URLSearchParams();
                params.append('range', selectedRevenueRange);
                if (selectedRevenueRange === 'custom' && customRevenueRange.start && customRevenueRange.end) {
                    params.append('start_date', customRevenueRange.start);
                    params.append('end_date', customRevenueRange.end);
                }
                const response = await fetch(`get_additional_fees.php?${params.toString()}`);
                const result = await response.json();

                if (!tbody) return;

                if (result.success && Array.isArray(result.records) && result.records.length > 0) {
                    if (countLabel) {
                        countLabel.textContent = `${result.records.length} record(s)`;
                    }
                    tbody.innerHTML = '';
                    result.records.forEach(record => {
                        const tr = document.createElement('tr');
                        const statusColor = record.additional_fees_status === 'Paid'
                            ? '#4CAF50'
                            : record.additional_fees_status === 'Pending'
                                ? '#ffa500'
                                : '#999';

                        // Format guest name - escape HTML for security
                        const guestDisplay = escapeHtml(record.guest_name || '—');

                        // Format date - use checked_out_at if available, fallback to check_out
                        const dateTimestamp = record.checked_out_at || record.check_out;
                        const dateDisplay = dateTimestamp ? new Date(dateTimestamp).toLocaleDateString() : '—';

                        tr.innerHTML = `
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">${escapeHtml(record.booking_id || '—')}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">${guestDisplay}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">${escapeHtml(record.room_id || '—')}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">${escapeHtml(record.payment_status || '—')}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee;">${formatMissingItems(record.missing_items)}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">${formatCurrency(record.missing_items_fees)}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">${dateDisplay}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">
                                <span style="display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; background:${statusColor}; color:#fff;">
                                    ${escapeHtml(record.additional_fees_status || 'None')}
                                </span>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    if (countLabel) {
                        countLabel.textContent = '0 record(s)';
                    }
                    tbody.innerHTML = `
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:16px; color:#777;">
                                ${result.message || 'No additional fees found.'}
                            </td>
                        </tr>
                    `;
                }
            } catch (error) {
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="8" style="text-align:center; padding:16px; color:#d32f2f;">
                                Failed to load additional fees: ${error.message}
                            </td>
                        </tr>
                    `;
                }
            }
        }

        async function loadSupplierReport() {
            const tbody = document.getElementById('supplierReportTableBody');
            const countLabel = document.getElementById('supplierReportCount');
            try {
                const params = new URLSearchParams();
                params.append('range', selectedRevenueRange);
                if (selectedRevenueRange === 'custom' && customRevenueRange.start && customRevenueRange.end) {
                    params.append('start_date', customRevenueRange.start);
                    params.append('end_date', customRevenueRange.end);
                }
                const response = await fetch(`get_purchase_orders.php?${params.toString()}`);
                const result = await response.json();

                if (!tbody) return;

                if (result.success && Array.isArray(result.purchase_orders) && result.purchase_orders.length > 0) {
                    if (countLabel) {
                        countLabel.textContent = `${result.purchase_orders.length} record(s)`;
                    }
                    tbody.innerHTML = '';
                    result.purchase_orders.forEach(po => {
                        const tr = document.createElement('tr');
                        const statusColor = po.status === 'Approved' || po.status === 'Received'
                            ? '#4CAF50'
                            : po.status === 'Pending'
                                ? '#ffa500'
                                : '#999';

                        const dateDisplay = po.po_date ? new Date(po.po_date).toLocaleDateString() : '—';

                        // Parse items
                        let itemsDisplay = '—';
                        let quantityDisplay = '—';
                        if (po.items) {
                            try {
                                const items = typeof po.items === 'string' ? JSON.parse(po.items) : po.items;
                                if (Array.isArray(items) && items.length > 0) {
                                    const itemNames = items.map(item => item.name || 'Item');
                                    const itemQuantities = items.map(item => item.quantity || 0);
                                    itemsDisplay = itemNames.join(', ');
                                    quantityDisplay = itemQuantities.join(', ');
                                }
                            } catch (e) {
                                console.error('Error parsing items:', e);
                            }
                        }

                        tr.innerHTML = `
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">${escapeHtml(po.po_number || '—')}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">${escapeHtml(po.requestor || '—')}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">${dateDisplay}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee;">${escapeHtml(itemsDisplay)}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee;">${escapeHtml(quantityDisplay)}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">${formatCurrency(po.total)}</td>
                            <td style="padding:8px 6px; border-bottom:1px solid #eee; white-space:nowrap;">
                                <span style="display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; background:${statusColor}; color:#fff;">
                                    ${escapeHtml(po.status || 'Pending')}
                                </span>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    if (countLabel) {
                        countLabel.textContent = '0 record(s)';
                    }
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" style="text-align:center; padding:16px; color:#777;">
                                ${result.message || 'No supplier records found.'}
                            </td>
                        </tr>
                    `;
                }
            } catch (error) {
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" style="text-align:center; padding:16px; color:#d32f2f;">
                                Failed to load supplier report: ${error.message}
                            </td>
                        </tr>
                    `;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            setActiveMenuItem();
            initRevenueFilters();
            loadReportStats();
            loadAdditionalFees();
            loadSupplierReport();

            // Export modal event listeners
            const exportBtn = document.getElementById('exportReportBtn');
            const closeBtn = document.getElementById('closeExportModalBtn');
            const modal = document.getElementById('exportModal');

            if (exportBtn) {
                exportBtn.addEventListener('click', performExport);
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', closeExportModal);
            }

            // Close modal when clicking outside
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) {
                        closeExportModal();
                    }
                });
            }

            // Close modal on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
                    closeExportModal();
                }
            });

            // Daily Sales modal event listeners
            const exportDailyBtn = document.getElementById('dailySalesExportBtn');
            const viewPdfDailyBtn = document.getElementById('dailySalesViewPdfBtn');
            const closeDailyBtn = document.getElementById('closeDailySalesModalBtn');
            const dailyModal = document.getElementById('dailySalesModal');

            if (exportDailyBtn) {
                exportDailyBtn.addEventListener('click', performDailySalesExport);
            }

            if (viewPdfDailyBtn) {
                viewPdfDailyBtn.addEventListener('click', viewDailySalesPdf);
            }

            if (closeDailyBtn) {
                closeDailyBtn.addEventListener('click', closeDailySalesModal);
            }

            // Close modal when clicking outside
            if (dailyModal) {
                dailyModal.addEventListener('click', function (e) {
                    if (e.target === dailyModal) {
                        closeDailySalesModal();
                    }
                });
            }

            // Close daily modal on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && dailyModal && dailyModal.style.display === 'flex') {
                    closeDailySalesModal();
                }
            });

            // Daily Sales 2.0 modal event listeners
            const exportDaily2Btn = document.getElementById('dailySales2ExportBtn');
            const viewPdfDaily2Btn = document.getElementById('dailySales2ViewPdfBtn');
            const closeDaily2Btn = document.getElementById('closeDailySales2ModalBtn');
            const daily2Modal = document.getElementById('dailySales2Modal');

            if (exportDaily2Btn) {
                exportDaily2Btn.addEventListener('click', performDailySales2Export);
            }

            if (viewPdfDaily2Btn) {
                viewPdfDaily2Btn.addEventListener('click', viewDailySales2Pdf);
            }

            if (closeDaily2Btn) {
                closeDaily2Btn.addEventListener('click', closeDailySales2Modal);
            }

            // Close modal when clicking outside
            if (daily2Modal) {
                daily2Modal.addEventListener('click', function (e) {
                    if (e.target === daily2Modal) {
                        closeDailySales2Modal();
                    }
                });
            }

            // Close daily 2.0 modal on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && daily2Modal && daily2Modal.style.display === 'flex') {
                    closeDailySales2Modal();
                }
            });

            // Payment Type Report modal event listeners
            const exportPaymentTypeBtn = document.getElementById('paymentTypeExportBtn');
            const viewPdfPaymentTypeBtn = document.getElementById('paymentTypeViewPdfBtn');
            const closePaymentTypeBtn = document.getElementById('closePaymentTypeReportModalBtn');
            const paymentTypeModal = document.getElementById('paymentTypeReportModal');

            if (exportPaymentTypeBtn) {
                exportPaymentTypeBtn.addEventListener('click', performPaymentTypeExport);
            }

            if (viewPdfPaymentTypeBtn) {
                viewPdfPaymentTypeBtn.addEventListener('click', viewPaymentTypeReportPdf);
            }

            if (closePaymentTypeBtn) {
                closePaymentTypeBtn.addEventListener('click', closePaymentTypeReportModal);
            }

            // Close modal when clicking outside
            if (paymentTypeModal) {
                paymentTypeModal.addEventListener('click', function (e) {
                    if (e.target === paymentTypeModal) {
                        closePaymentTypeReportModal();
                    }
                });
            }

            // Close payment type modal on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && paymentTypeModal && paymentTypeModal.style.display === 'flex') {
                    closePaymentTypeReportModal();
                }
            });

            // Payment Type Raw Report modal event listeners
            const exportPaymentTypeRawBtn = document.getElementById('paymentTypeRawExportBtn');
            const viewPdfPaymentTypeRawBtn = document.getElementById('paymentTypeRawViewPdfBtn');
            const closePaymentTypeRawBtn = document.getElementById('closePaymentTypeRawReportModalBtn');
            const paymentTypeRawModal = document.getElementById('paymentTypeRawReportModal');

            if (exportPaymentTypeRawBtn) {
                exportPaymentTypeRawBtn.addEventListener('click', performPaymentTypeRawExport);
            }

            if (viewPdfPaymentTypeRawBtn) {
                viewPdfPaymentTypeRawBtn.addEventListener('click', viewPaymentTypeRawReportPdf);
            }

            if (closePaymentTypeRawBtn) {
                closePaymentTypeRawBtn.addEventListener('click', closePaymentTypeRawReportModal);
            }

            // Close modal when clicking outside
            if (paymentTypeRawModal) {
                paymentTypeRawModal.addEventListener('click', function (e) {
                    if (e.target === paymentTypeRawModal) {
                        closePaymentTypeRawReportModal();
                    }
                });
            }

            // Close payment type raw modal on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && paymentTypeRawModal && paymentTypeRawModal.style.display === 'flex') {
                    closePaymentTypeRawReportModal();
                }
            });

            // Detailed Booking Report modal event listeners
            const exportDetailedBookingBtn = document.getElementById('detailedBookingExportBtn');
            const closeDetailedBookingBtn = document.getElementById('closeDetailedBookingReportModalBtn');
            const detailedBookingModal = document.getElementById('detailedBookingReportModal');

            if (exportDetailedBookingBtn) {
                exportDetailedBookingBtn.addEventListener('click', performDetailedBookingExport);
            }

            if (closeDetailedBookingBtn) {
                closeDetailedBookingBtn.addEventListener('click', closeDetailedBookingReportModal);
            }

            // Close modal when clicking outside
            if (detailedBookingModal) {
                detailedBookingModal.addEventListener('click', function (e) {
                    if (e.target === detailedBookingModal) {
                        closeDetailedBookingReportModal();
                    }
                });
            }

            // Close detailed booking modal on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && detailedBookingModal && detailedBookingModal.style.display === 'flex') {
                    closeDetailedBookingReportModal();
                }
            });

            // Customer Details Report modal event listeners
            const exportCustomerDetailsBtn = document.getElementById('customerDetailsExportBtn');
            const viewPdfCustomerDetailsBtn = document.getElementById('customerDetailsViewPdfBtn');
            const closeCustomerDetailsBtn = document.getElementById('closeCustomerDetailsReportModalBtn');
            const customerDetailsModal = document.getElementById('customDetailReportModal');

            if (exportCustomerDetailsBtn) {
                exportCustomerDetailsBtn.addEventListener('click', performCustomerDetailsExport);
            }

            if (viewPdfCustomerDetailsBtn) {
                viewPdfCustomerDetailsBtn.addEventListener('click', viewCustomerDetailsPdf);
            }

            if (closeCustomerDetailsBtn) {
                closeCustomerDetailsBtn.addEventListener('click', closeCustomerDetailsReportModal);
            }

            // Close modal when clicking outside
            if (customerDetailsModal) {
                customerDetailsModal.addEventListener('click', function (e) {
                    if (e.target === customerDetailsModal) {
                        closeCustomerDetailsReportModal();
                    }
                });
            }

            // Close customer details modal on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && customerDetailsModal && customerDetailsModal.style.display === 'flex') {
                    closeCustomerDetailsReportModal();
                }
            });

            // Running Sales Report modal event listeners
            const exportRunningSalesBtn = document.getElementById('runningSalesExportBtn');
            const viewPdfRunningSalesBtn = document.getElementById('runningSalesViewPdfBtn');
            const closeRunningSalesBtn = document.getElementById('closeRunningSalesReportModalBtn');
            const runningSalesModal = document.getElementById('runningSalesReportModal');

            if (exportRunningSalesBtn) {
                exportRunningSalesBtn.addEventListener('click', performRunningSalesExport);
            }

            if (viewPdfRunningSalesBtn) {
                viewPdfRunningSalesBtn.addEventListener('click', viewRunningSalesPdf);
            }

            if (closeRunningSalesBtn) {
                closeRunningSalesBtn.addEventListener('click', closeRunningSalesReportModal);
            }

            // Close modal when clicking outside
            if (runningSalesModal) {
                runningSalesModal.addEventListener('click', function (e) {
                    if (e.target === runningSalesModal) {
                        closeRunningSalesReportModal();
                    }
                });
            }

            // Close running sales modal on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && runningSalesModal && runningSalesModal.style.display === 'flex') {
                    closeRunningSalesReportModal();
                }
            });
        });

        // Sales Metrics Functions
        let isEditingTarget = false;

        // Load sales metrics on page load
        async function loadSalesMetrics() {
            try {
                const url = `get_sales_metrics.php?date=<?php echo date('Y-m-d'); ?>`;

                const response = await fetch(url);
                const result = await response.json();

                if (result.success && result.data) {
                    updateSalesMetricsDisplay(result.data);
                }
            } catch (error) {
                console.error('Error loading sales metrics:', error);
            }
        }

        function updateSalesMetricsDisplay(data) {
            // Auto-calculated metrics
            const runningSalesMTDRounded = Math.round(parseFloat(data.running_sales_mtd || 0));
            document.getElementById('runningSalesMTDDisplay').textContent =
                '₱' + runningSalesMTDRounded.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Avg Daily Sales (average from month start to selected date)
            // Round to whole number
            const avgDailySalesRounded = Math.round(parseFloat(data.avg_daily_sales || 0));
            document.getElementById('avgDailySalesDisplay').textContent =
                '₱' + avgDailySalesRounded.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Update the date label
            if (data.selected_date) {
                const dateObj = new Date(data.selected_date + 'T00:00:00');
                const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                document.getElementById('dailySalesSelectedDate').textContent = 'Up to ' + formattedDate;
            }

            const emsoRounded = Math.round(parseFloat(data.emso || 0));
            document.getElementById('emsoDisplay').textContent =
                '₱' + emsoRounded.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // EMSO vs Last Month
            const emsoVsLastMonth = parseFloat(data.emso_vs_last_month || 0);
            const emsoVsLastMonthColor = data.emso_vs_last_month_color || '#6c757d';
            const emsoVsLastMonthPrefix = emsoVsLastMonth >= 0 ? '+' : '';
            document.getElementById('emsoVsLastMonthDisplay').innerHTML =
                '<span style="font-size: 16px; color: ' + emsoVsLastMonthColor + ';">' +
                emsoVsLastMonthPrefix + emsoVsLastMonth.toFixed(1) + '%</span>';

            // Manual target
            document.getElementById('targetDisplay').textContent =
                '₱' + parseFloat(data.target || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Calculate Achievement percentage (Running Sales MTD vs Target)
            const mtdSales = Math.round(parseFloat(data.running_sales_mtd || 0));
            const target = parseFloat(data.target || 0);
            let achievementPercent = 0;
            let achievementColor = '#6c757d';

            if (target > 0) {
                achievementPercent = (mtdSales / target) * 100;
                // Color coding for achievement
                if (achievementPercent >= 100) {
                    achievementColor = '#28a745'; // Green for 100%+
                } else if (achievementPercent >= 80) {
                    achievementColor = '#ffc107'; // Yellow for 80-99%
                } else if (achievementPercent >= 50) {
                    achievementColor = '#fd7e14'; // Orange for 50-79%
                } else {
                    achievementColor = '#dc3545'; // Red for <50%
                }
            }

            document.getElementById('achievementDisplay').innerHTML =
                '<span style="font-size: 16px; color: ' + achievementColor + ';">' +
                achievementPercent.toFixed(1) + '%</span>';

            // Benchmark
            const benchmark = parseFloat(data.benchmark || 0);
            const benchmarkColor = data.benchmark_color || '#6c757d';
            document.getElementById('benchmarkDisplay').innerHTML =
                '<span style="font-size: 16px; color: ' + benchmarkColor + ';">' +
                benchmark.toFixed(1) + '%</span>';
        }

        function toggleEditTarget() {
            isEditingTarget = !isEditingTarget;

            if (isEditingTarget) {
                // Switch to edit mode
                document.getElementById('editTargetBtnLabel').textContent = 'Cancel';
                document.getElementById('editTargetBtn').style.borderColor = '#dc3545';
                document.getElementById('editTargetBtn').style.color = '#dc3545';

                // Hide display, show input for target only
                document.getElementById('targetDisplay').style.display = 'none';
                const targetInput = document.getElementById('targetInput');
                targetInput.style.display = 'block';

                // Populate with current value (remove ₱ and commas)
                const displayText = document.getElementById('targetDisplay').textContent;
                targetInput.value = parseFloat(displayText.replace('₱', '').replace(/,/g, '')) || 0;

                document.getElementById('targetActions').style.display = 'block';
            } else {
                cancelEditTarget();
            }
        }

        function cancelEditTarget() {
            isEditingTarget = false;

            // Reset button
            document.getElementById('editTargetBtnLabel').textContent = 'Edit';
            document.getElementById('editTargetBtn').style.borderColor = '#ddd';
            document.getElementById('editTargetBtn').style.color = '#666';

            // Show display, hide input
            document.getElementById('targetDisplay').style.display = 'block';
            document.getElementById('targetInput').style.display = 'none';

            document.getElementById('targetActions').style.display = 'none';
        }

        async function saveTarget() {
            const target = parseFloat(document.getElementById('targetInput').value) || 0;

            try {
                const response = await fetch('save_target.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ target: target })
                });

                // Get the raw response text first to debug
                const responseText = await response.text();
                console.log('Raw response:', responseText);

                // Try to parse as JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response was:', responseText);
                    alert('Server returned an invalid response. Check console for details.');
                    return;
                }

                if (result.success) {
                    document.getElementById('targetDisplay').textContent =
                        '₱' + target.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    cancelEditTarget();
                    alert('Target saved successfully!');
                } else {
                    alert('Error saving target: ' + result.message);
                }
            } catch (error) {
                console.error('Error saving target:', error);
                alert('Error saving target: ' + error.message);
            }
        }

        // Load sales metrics when page loads
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (in_array($userAccessLevel, ['admin', 'super_admin'])): ?>
                loadSalesMetrics();
            <?php endif; ?>
        });
    </script>

    <script>
        // Store added food items
        let addedFoodItems = [];
        // Store all available food items from database
        let allAvailableFoodItems = [];

        // Toggle food dropdown
        function toggleFoodDropdown() {
            const dropdown = document.getElementById('foodDropdownContainer');
            const isVisible = dropdown.style.display === 'block';

            if (isVisible) {
                dropdown.style.display = 'none';
            } else {
                dropdown.style.display = 'block';
                loadFoodItems();
            }
        }

        // Load food items from breakfast database
        async function loadFoodItems() {
            try {
                const response = await fetch('get_breakfast.php');
                const result = await response.json();

                if (result.success && result.items) {
                    allAvailableFoodItems = result.items;
                    updateFoodDropdown();
                } else {
                    alert('No food items available. Please add items in the Breakfast page first.');
                    document.getElementById('foodDropdownContainer').style.display = 'none';
                }
            } catch (error) {
                console.error('Error loading food items:', error);
                alert('Error loading food items: ' + error.message);
                document.getElementById('foodDropdownContainer').style.display = 'none';
            }
        }

        // Update dropdown to show checkboxes for items that haven't been added
        function updateFoodDropdown() {
            const container = document.getElementById('foodCheckboxesContainer');
            const selectAllCheckbox = document.getElementById('selectAllFoodCheckbox');
            container.innerHTML = '';

            // Get list of already added food IDs
            const addedFoodIds = addedFoodItems.map(item => item.food_id);

            // Filter out already added items
            const availableItems = allAvailableFoodItems.filter(item => !addedFoodIds.includes(item.id.toString()));

            if (availableItems.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 20px; color: #999; font-size: 13px;">All items have been added</div>';
                selectAllCheckbox.disabled = true;
                selectAllCheckbox.checked = false;
                return;
            }

            selectAllCheckbox.disabled = false;
            selectAllCheckbox.checked = false;

            availableItems.forEach(item => {
                const checkboxWrapper = document.createElement('div');
                checkboxWrapper.style.cssText = 'margin-bottom: 8px; padding: 8px; border-radius: 6px; transition: background 0.2s;';
                checkboxWrapper.onmouseover = function() { this.style.background = '#f9f9f9'; };
                checkboxWrapper.onmouseout = function() { this.style.background = 'transparent'; };

                const label = document.createElement('label');
                label.style.cssText = 'display: flex; align-items: center; cursor: pointer;';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'food-item-checkbox';
                checkbox.value = item.id;
                checkbox.dataset.name = item.food_name;
                checkbox.dataset.price = item.price;
                checkbox.style.cssText = 'width: 18px; height: 18px; margin-right: 10px; cursor: pointer; accent-color: #afad4cff;';
                checkbox.onchange = updateSelectAllCheckbox;

                const span = document.createElement('span');
                span.style.cssText = 'font-size: 13px; color: #333;';
                span.textContent = `${item.food_name} (₱${parseFloat(item.price).toFixed(2)})`;

                label.appendChild(checkbox);
                label.appendChild(span);
                checkboxWrapper.appendChild(label);
                container.appendChild(checkboxWrapper);
            });
        }

        // Toggle Select All checkbox
        function toggleSelectAllFood() {
            const selectAllCheckbox = document.getElementById('selectAllFoodCheckbox');
            const checkboxes = document.querySelectorAll('.food-item-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        }

        // Update Select All checkbox state based on individual checkboxes
        function updateSelectAllCheckbox() {
            const selectAllCheckbox = document.getElementById('selectAllFoodCheckbox');
            const checkboxes = document.querySelectorAll('.food-item-checkbox');
            const checkedCheckboxes = document.querySelectorAll('.food-item-checkbox:checked');
            
            if (checkboxes.length === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCheckboxes.length === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCheckboxes.length === checkboxes.length) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
        }

        // Save food selection
        function saveFoodSelection() {
            const checkboxes = document.querySelectorAll('.food-item-checkbox:checked');

            if (checkboxes.length === 0) {
                alert('Please select at least one food item');
                return;
            }

            let addedCount = 0;
            const addedNames = [];

            checkboxes.forEach(checkbox => {
                const foodData = {
                    food_id: checkbox.value,
                    food_name: checkbox.dataset.name,
                    price: parseFloat(checkbox.dataset.price) || 0,
                    count: 0
                };

                // Check if item already exists (shouldn't happen with filtering, but just in case)
                const existingItem = addedFoodItems.find(item => item.food_id === foodData.food_id);
                if (!existingItem) {
                    // Add to array
                    addedFoodItems.push(foodData);
                    addedCount++;
                    addedNames.push(foodData.food_name);
                }
            });

            if (addedCount > 0) {
                // Save to localStorage for persistence
                localStorage.setItem('reportFoodItems', JSON.stringify(addedFoodItems));

                const message = addedCount === 1 
                    ? `Food item "${addedNames[0]}" added to report!`
                    : `${addedCount} food items added to report:\n${addedNames.join(', ')}`;
                alert(message);

                // Update dropdown to remove the added items
                updateFoodDropdown();

                // Refresh the food items display with actual data
                loadFoodTracking();
            } else {
                alert('Selected items are already in the report!');
            }
        }

        // Remove a food item from the report
        function removeFoodItem(foodId) {
            const itemToRemove = addedFoodItems.find(item => item.food_id === foodId);
            
            if (!itemToRemove) {
                alert('Food item not found!');
                return;
            }

            const confirmRemove = confirm(`Are you sure you want to remove "${itemToRemove.food_name}" from the report?`);
            
            if (confirmRemove) {
                // Remove from array
                addedFoodItems = addedFoodItems.filter(item => item.food_id !== foodId);
                
                // Update localStorage
                localStorage.setItem('reportFoodItems', JSON.stringify(addedFoodItems));
                
                // Refresh the food items display
                loadFoodTracking();
                
                // Update the dropdown if it's open
                const dropdown = document.getElementById('foodDropdownContainer');
                if (dropdown && dropdown.style.display === 'block') {
                    updateFoodDropdown();
                }
                
                alert(`Food item "${itemToRemove.food_name}" removed from report!`);
            }
        }

        // Load food items from localStorage on page load
        document.addEventListener('DOMContentLoaded', function () {
            const savedItems = localStorage.getItem('reportFoodItems');
            if (savedItems) {
                try {
                    addedFoodItems = JSON.parse(savedItems);
                    // Load food tracking data from database
                    loadFoodTracking();
                } catch (error) {
                    console.error('Error loading saved food items:', error);
                    addedFoodItems = [];
                }
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (event) {
            const dropdown = document.getElementById('foodDropdownContainer');
            const button = document.getElementById('addFoodBtn');

            if (dropdown && button) {
                const isClickInside = dropdown.contains(event.target) || button.contains(event.target);

                if (!isClickInside && dropdown.style.display === 'block') {
                    dropdown.style.display = 'none';
                }
            }
        });
    </script>
</body>

</html>