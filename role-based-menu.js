(function () {
    const ROLE_USER = 'user';
    const ROLE_STAFF = 'staff';
    const ROLE_ADMIN = 'admin';
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_AUDITOR = 'auditor';

    // Items hidden for staff (no Purchase Order)
    const STAFF_HIDE_SELECTORS = [
        '[data-page="PurchaseOrder.html"]',
        '[data-page="PurchaseOrder.php"]'
    ];

    // Items hidden for admin (no Create User)
    const ADMIN_HIDE_SELECTORS = [
        '[data-page="Createuser.php"]',
        '[data-page="Createuser.html"]'
    ];

    // Modification page — Super Admin only
    const MODIFICATION_HIDE_SELECTORS = [
        '[data-page="Modification.php"]'
    ];
    const MODIFICATION_MENU_STYLE_ID = 'menu-modification-access-style';

    function ensureModificationMenuStyle() {
        if (document.getElementById(MODIFICATION_MENU_STYLE_ID)) {
            return;
        }
        const style = document.createElement('style');
        style.id = MODIFICATION_MENU_STYLE_ID;
        style.textContent = `
            .sidebar-menu-item[data-page="Modification.php"] {
                display: none !important;
            }
        `;
        document.head.appendChild(style);
    }

    function setModificationMenuVisible(visible) {
        ensureModificationMenuStyle();
        const style = document.getElementById(MODIFICATION_MENU_STYLE_ID);
        if (!style) return;
        style.textContent = visible
            ? `.sidebar-menu-item[data-page="Modification.php"] { display: flex !important; }`
            : `.sidebar-menu-item[data-page="Modification.php"] { display: none !important; }`;
    }

    // Page access rules (lowercase keys)
    const PAGE_ACCESS_RULES = {
        'booking.html': [ROLE_USER, ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_AUDITOR],
        'reservationlist.php': [ROLE_USER, ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_AUDITOR],
        'cancelpage.php': [ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_AUDITOR],
        'report.php': [ROLE_USER, ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_AUDITOR],
        'report.html': [ROLE_USER, ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_AUDITOR],
        'roomlist.html': [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_AUDITOR],
        'promo.html': [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_AUDITOR],
        'inventory.html': [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_AUDITOR],
        'createuser.php': [ROLE_SUPER_ADMIN],
        'purchaseorder.html': [ROLE_ADMIN, ROLE_SUPER_ADMIN],
        'additem.php': [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN],
        'receive.html': [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN],
        'breakfast.html': [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_AUDITOR],
        'modification.php': [ROLE_SUPER_ADMIN],
        'deposittracking.php': [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_AUDITOR],
        'cashdeposit.php': [ROLE_STAFF, ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_AUDITOR]
    };

    // Hide all menu items initially to prevent flash
    function hideAllMenuItems() {
        const style = document.createElement('style');
        style.id = 'menu-hide-style';
        style.textContent = `
            .sidebar-menu-item:not([data-page="Booking.html"]):not([data-page="Reservationlist.php"]):not([data-page="Report.php"]):not([data-page="Cancelpage.php"]):not([data-page="Roomlist.html"]):not([data-page="Promo.html"]):not([data-page="Breakfast.html"]):not([data-page="inventory.html"]):not([data-page="CashDeposit.php"]) { 
                display: none !important; 
            }
            .sidebar-menu-item[onclick*="logout.php"] { 
                display: block !important; 
            }
        `;
        document.head.appendChild(style);
    }

    // Remove the temporary hiding style
    function removeHideStyle() {
        const style = document.getElementById('menu-hide-style');
        if (style) {
            style.remove();
        }
    }

    function isLogoutItem(item) {
        const onclickValue = (item.getAttribute('onclick') || '').toLowerCase();
        const textValue = (item.textContent || '').trim().toLowerCase();
        return onclickValue.includes('logout.php') || textValue === 'logout';
    }

    function isMinimizeItem(item) {
        return item.id === 'minimizeBtn';
    }

    function hideElements(selectors = []) {
        selectors.forEach((selector) => {
            document.querySelectorAll(selector).forEach((el) => {
                el.style.display = 'none';
            });
        });
    }

    function showElements(selectors = []) {
        selectors.forEach((selector) => {
            document.querySelectorAll(selector).forEach((el) => {
                el.style.display = ''; // Clear inline display:none
            });
        });
    }

    function redirectToLogin() {
        if (typeof window === 'undefined') return;
        const target = 'Login.html?error=' + encodeURIComponent('Please login to access the system.');
        if (!window.location.href.includes('Login.html')) {
            window.location.href = target;
        }
    }

    function redirectToBooking() {
        if (typeof window === 'undefined') return;
        const currentPage = getCurrentPageName();
        if (currentPage !== 'booking.html') {
            window.location.href = 'Booking.html';
        }
    }

    function getCurrentPageName() {
        return (window.location.pathname.split('/').pop() || '').toLowerCase();
    }

    function canAccessPage(pageName, userLevel) {
        const normalizedPage = pageName.toLowerCase();
        const normalizedLevel = (userLevel || '').toLowerCase();

        // Super Admin can access everything
        if (normalizedLevel === ROLE_SUPER_ADMIN) return true;

        // Check explicit rules
        if (PAGE_ACCESS_RULES[normalizedPage]) {
            return PAGE_ACCESS_RULES[normalizedPage].includes(normalizedLevel);
        }

        // Pages not in rules (Login.html, Signup.html …) are open
        return true;
    }

    function checkPageAccess(userLevel) {
        const currentPage = getCurrentPageName();

        // Skip for public pages
        if (currentPage === 'login.html' || currentPage === 'signup.html' || currentPage === '') {
            return true;
        }

        if (!canAccessPage(currentPage, userLevel)) {
            if (userLevel === ROLE_USER) {
                redirectToBooking();
            } else if (userLevel === ROLE_AUDITOR) {
                // Redirect auditor to Report.php
                if (currentPage !== 'report.php') {
                    window.location.href = 'Report.php';
                }
            } else {
                window.location.href = 'Report.php';
            }
            return false;
        }
        return true;
    }

    // Hide all sidebar items except Report, Booking, Reservation List, Logout, Minimize (for 'user' role)
    function hideAllButBookingAndLogout() {
        const allowedPages = new Set(['report.php', 'report.html', 'booking.html', 'booking.php', 'reservationlist.php']);
        document.querySelectorAll('.sidebar-menu-item').forEach((item) => {
            const page = (item.dataset.page || '').toLowerCase();
            if (!allowedPages.has(page) && !isLogoutItem(item) && !isMinimizeItem(item)) {
                item.style.display = 'none';
            }
        });
        const maintenanceMenu = document.getElementById('systemMaintenanceMenu');
        if (maintenanceMenu) maintenanceMenu.style.display = 'none';
    }

    // Hide all sidebar items except Report, Booking, Reservation List, Cancelpage, Roomlist, Promo, Breakfast, Inventory, Logout (for 'auditor' role)
    function hideAllButAuditorPages() {
        const allowedPages = new Set(['report.php', 'report.html', 'booking.html', 'booking.php', 'reservationlist.php', 'cancelpage.php', 'roomlist.html', 'promo.html', 'breakfast.html', 'inventory.html', 'cashdeposit.php']);
        
        // Hide main menu items that auditor shouldn't see
        document.querySelectorAll('.sidebar-menu-item').forEach((item) => {
            const page = (item.dataset.page || '').toLowerCase();
            if (!allowedPages.has(page) && !isLogoutItem(item) && !isMinimizeItem(item)) {
                // Hide the System Maintenance menu for auditors since they only need Inventory
                if (item.id === 'systemMaintenanceMenu') {
                    item.style.display = 'none';
                } else {
                    item.style.display = 'none';
                }
            }
        });
        
        // For auditors, create a direct Inventory menu item instead of showing the submenu
        const maintenanceMenu = document.getElementById('systemMaintenanceMenu');
        if (maintenanceMenu) {
            // Hide the collapsible maintenance menu
            maintenanceMenu.style.display = 'none';
            
            // Find the inventory submenu item
            const inventorySubmenuItem = document.querySelector('#systemMaintenanceSubmenu .submenu-item[data-page="inventory.html"]');
            
            if (inventorySubmenuItem) {
                // Check if we already created a direct inventory menu item
                let directInventoryItem = document.querySelector('.sidebar-menu-item[data-page="inventory.html"]:not(.submenu-item)');
                
                if (!directInventoryItem) {
                    // Clone the inventory submenu item and convert it to a main menu item
                    directInventoryItem = document.createElement('li');
                    directInventoryItem.className = 'sidebar-menu-item';
                    directInventoryItem.setAttribute('data-page', 'inventory.html');
                    directInventoryItem.setAttribute('onclick', "navigateToPage('inventory.html')");
                    directInventoryItem.innerHTML = inventorySubmenuItem.innerHTML;
                    
                    // Check if we're currently on inventory.html and add active class
                    const currentPage = getCurrentPageName();
                    if (currentPage === 'inventory.html') {
                        directInventoryItem.classList.add('active');
                    }
                    
                    // Insert it after the Promo menu item (or before Breakfast)
                    const promoItem = document.querySelector('.sidebar-menu-item[data-page="Promo.html"]');
                    const breakfastItem = document.querySelector('.sidebar-menu-item[data-page="Breakfast.html"]');
                    
                    if (promoItem && promoItem.nextElementSibling) {
                        promoItem.parentNode.insertBefore(directInventoryItem, promoItem.nextElementSibling);
                    } else if (breakfastItem) {
                        breakfastItem.parentNode.insertBefore(directInventoryItem, breakfastItem);
                    } else {
                        // Fallback: insert before maintenance menu
                        maintenanceMenu.parentNode.insertBefore(directInventoryItem, maintenanceMenu);
                    }
                }
            }
        }
    }

    function applyRoleRules(level) {
        // Remove the temporary hide style first
        removeHideStyle();

        const normalizedLevel = (level || '').toLowerCase();
        setModificationMenuVisible(normalizedLevel === ROLE_SUPER_ADMIN);
        
        switch (normalizedLevel) {
            case ROLE_USER:
                // Only Booking + Reservation List + Logout + Minimize
                hideAllButBookingAndLogout();
                break;

            case ROLE_AUDITOR:
                // Only Report + Booking + Reservation List + Logout
                hideAllButAuditorPages();
                break;

            case ROLE_STAFF:
                // Everything except Purchase Order
                hideElements(STAFF_HIDE_SELECTORS);
                // Also hide Create User (staff shouldn't see it)
                hideElements(ADMIN_HIDE_SELECTORS);
                break;

            case ROLE_ADMIN:
                // Everything except Create User
                hideElements(ADMIN_HIDE_SELECTORS);
                break;

            case ROLE_SUPER_ADMIN:
            default:
                // Full access — show everything, hide nothing
                showElements(ADMIN_HIDE_SELECTORS);
                break;
        }
    }

    async function fetchUserAccessLevel() {
        try {
            const response = await fetch('get_user_info.php', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            if (response.status === 401) {
                redirectToLogin();
                return null;
            }

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();
            const userData = data && data.user ? data.user : null;
            const level = userData && userData.access_level
                ? userData.access_level
                : data.access_level;
            return (level || '').toLowerCase();
        } catch (error) {
            console.warn('Unable to determine user access level:', error);
            redirectToLogin();
            return null;
        }
    }

    function injectCashDepositMenuItem() {
        document.querySelectorAll('[data-page="DepositTracking.php"]').forEach((item) => {
            const parent = item.parentNode;
            if (!parent) return;
            const existing = parent.querySelector('[data-page="CashDeposit.php"]');
            if (existing) return;

            const cashItem = document.createElement('li');
            cashItem.className = 'sidebar-menu-item';
            cashItem.setAttribute('data-page', 'CashDeposit.php');
            cashItem.setAttribute('onclick', "navigateToPage('CashDeposit.php')");

            const currentPage = (window.location.pathname.split('/').pop() || '').toLowerCase();
            if (currentPage === 'cashdeposit.php') {
                cashItem.classList.add('active');
            }

            cashItem.innerHTML = `
                <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round"
                    style="display: inline-block; vertical-align: middle; margin-right: 12px; width: 20px; height: 20px;">
                    <rect x="2" y="6" width="20" height="12" rx="2" />
                    <circle cx="12" cy="12" r="2" />
                    <path d="M6 12h.01M18 12h.01" />
                </svg>
                <span>Cash Deposit</span>
            `;

            parent.insertBefore(cashItem, item.nextSibling);
        });
    }

    // Hide menu items immediately when script loads
    hideAllMenuItems();
    ensureModificationMenuStyle();

    document.addEventListener('DOMContentLoaded', async () => {
        injectCashDepositMenuItem();
        const level = await fetchUserAccessLevel();
        if (level) {
            const hasAccess = checkPageAccess(level);
            if (hasAccess) {
                applyRoleRules(level);
            }
        } else {
            // If we can't get user level, remove hide style to show default menu
            removeHideStyle();
            setModificationMenuVisible(false);
        }

        // Create and inject logout modal
        createLogoutModal();
        
        // Intercept logout clicks to show modal
        interceptLogoutClicks();
    });

    // Create and inject modal HTML into the page
    function createLogoutModal() {
        const modalHTML = `
            <div id="logoutModalOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(8px); z-index: 999999; padding: 20px; align-items: center; justify-content: center;">
                <div style="background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); max-width: 480px; width: 100%; animation: slideIn 0.3s ease-out; position: relative;">
                    
                    <!-- Initial Choice Modal -->
                    <div id="logoutChoiceModal">
                        <div style="padding: 24px 24px 16px; border-bottom: 1px solid #e5e7eb; position: relative;">
                            <h2 style="font-size: 20px; font-weight: 600; color: #111827; margin-bottom: 4px;">Logout Required Action</h2>
                            <p style="font-size: 13px; color: #6b7280; line-height: 1.5; margin: 0;">Please complete your turnover or mark break before logging out.</p>
                            <button onclick="closeLogoutModal()" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 24px; color: #9ca3af; cursor: pointer; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px;">&times;</button>
                        </div>
                        <div style="padding: 24px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div onclick="showTurnoverModal()" style="background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 12px; padding: 24px 16px; text-align: center; cursor: pointer; transition: all 0.2s;">
                                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #4ba85fff 0%, #4ba85fff 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 24px; color: white;">₱</div>
                                    <div style="font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 4px;">Cash Turnover</div>
                                    <div style="font-size: 12px; color: #6b7280;">Submit cash collection</div>
                                </div>
                                <div onclick="showBreakModal()" style="background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 12px; padding: 24px 16px; text-align: center; cursor: pointer; transition: all 0.2s;">
                                    <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 24px; color: white;">B</div>
                                    <div style="font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 4px;">Break</div>
                                    <div style="font-size: 12px; color: #6b7280;">Take a short break</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cash Turnover Modal -->
                    <div id="logoutTurnoverModal" style="display: none;">
                        <div style="padding: 24px 24px 16px; border-bottom: 1px solid #e5e7eb;">
                            <h2 style="font-size: 20px; font-weight: 600; color: #111827; margin-bottom: 4px;">Cash Turnover Summary</h2>
                            <p style="font-size: 13px; color: #6b7280; margin: 0;">Please enter the cash amounts collected during your shift.</p>
                        </div>
                        <div style="padding: 24px;">
                            <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                                <div style="font-size: 13px; font-weight: 600; color: #92400e; margin-bottom: 8px;">Session Summary</div>
                                <div style="font-size: 12px; color: #78350f; margin-bottom: 4px;">Employee: <span id="logoutEmployeeName">Loading...</span></div>
                                <div style="font-size: 12px; color: #78350f; margin-bottom: 4px;">Login Time: <span id="logoutLoginTime">Loading...</span></div>
                                <div style="font-size: 12px; color: #78350f; ">Current Time: <span id="logoutCurrentTime">Loading...</span></div>
                            </div>

                            <div style="margin-bottom: 16px;">
                                <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 8px;">Cash Amount</label>
                                <input type="number" id="logoutCashAmount" placeholder="0.00" step="0.01" min="0" oninput="calculateLogoutTotal()" style="width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; font-family: 'Poppins', sans-serif; color: #374151; box-sizing: border-box;">
                            </div>

                            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 14px; font-weight: 600; color: #374151;">Total Amount:</span>
                                    <span style="font-size: 20px; font-weight: 700; color: #1f1f1e;">₱<span id="logoutTotalAmount">0.00</span></span>
                                </div>
                            </div>

                            <div style="display: flex; gap: 12px;">
                                <button onclick="backToLogoutChoice()" style="flex: 1; padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: 'Poppins', sans-serif; cursor: pointer; background: #f3f4f6; color: #374151;">Back</button>
                                <button onclick="submitLogoutTurnover()" style="flex: 1; padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: 'Poppins', sans-serif; cursor: pointer; background: #1a1a1a; color: white;">Submit & Logout</button>
                            </div>
                        </div>
                    </div>

                    <!-- Break Modal -->
                    <div id="logoutBreakModal" style="display: none;">
                        <div style="padding: 24px 24px 16px; border-bottom: 1px solid #e5e7eb;">
                            <h2 style="font-size: 20px; font-weight: 600; color: #111827; margin-bottom: 4px;">Break Record</h2>
                            <p style="font-size: 13px; color: #6b7280; margin: 0;">Confirm your break time before logging out.</p>
                        </div>
                        <div style="padding: 24px;">
                            <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                                <div style="font-size: 13px; font-weight: 600; color: #92400e; margin-bottom: 8px;">Break Information</div>
                                <div style="font-size: 12px; color: #78350f; line-height: 1.5;">
                                    Employee: <span id="logoutBreakEmployeeName">Loading...</span><br>
                                    Break Time: <span id="logoutBreakTime">Loading...</span>
                                </div>
                            </div>

                            <div style="display: flex; gap: 12px;">
                                <button onclick="backToLogoutChoice()" style="flex: 1; padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: 'Poppins', sans-serif; cursor: pointer; background: #f3f4f6; color: #374151;">Back</button>
                                <button onclick="confirmLogoutBreak()" style="flex: 1; padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: 'Poppins', sans-serif; cursor: pointer; background: #1a1a1a; color: white;">Confirm Break</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <style>
                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateY(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                #logoutCashAmount::placeholder {
                    color: #9ca3af;
                    opacity: 1;
                }
            </style>
        `;

        // Inject modal into body
        const div = document.createElement('div');
        div.innerHTML = modalHTML;
        document.body.appendChild(div.firstElementChild);
    }

    // Show logout modal
    window.showLogoutModal = function() {
        document.getElementById('logoutModalOverlay').style.display = 'flex';
        loadLogoutUserInfo();
        updateLogoutTimes();
        setInterval(updateLogoutTimes, 1000);
    };

    // Close logout modal
    window.closeLogoutModal = function() {
        document.getElementById('logoutModalOverlay').style.display = 'none';
    };

    // Show turnover modal
    window.showTurnoverModal = function() {
        document.getElementById('logoutChoiceModal').style.display = 'none';
        document.getElementById('logoutTurnoverModal').style.display = 'block';
    };

    // Show break modal
    window.showBreakModal = function() {
        document.getElementById('logoutChoiceModal').style.display = 'none';
        document.getElementById('logoutBreakModal').style.display = 'block';
    };

    // Back to choice
    window.backToLogoutChoice = function() {
        document.getElementById('logoutTurnoverModal').style.display = 'none';
        document.getElementById('logoutBreakModal').style.display = 'none';
        document.getElementById('logoutChoiceModal').style.display = 'block';
    };

    // Calculate total
    window.calculateLogoutTotal = function() {
        const cash = parseFloat(document.getElementById('logoutCashAmount').value) || 0;
        document.getElementById('logoutTotalAmount').textContent = cash.toFixed(2);
    };

    // Update times
    function updateLogoutTimes() {
        const now = new Date();
        const timeStr = now.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        
        const currentTimeEl = document.getElementById('logoutCurrentTime');
        const breakTimeEl = document.getElementById('logoutBreakTime');
        
        if (currentTimeEl) currentTimeEl.textContent = timeStr;
        if (breakTimeEl) breakTimeEl.textContent = timeStr;
    }

    // Load user info
    async function loadLogoutUserInfo() {
        try {
            const response = await fetch('get_user_info.php');
            const data = await response.json();
            if (data.success) {
                const displayName = data.display_name || data.first_name || data.username || 'User';
                const employeeEls = document.querySelectorAll('#logoutEmployeeName, #logoutBreakEmployeeName');
                employeeEls.forEach(el => el.textContent = displayName);
            }

            // Load login time
            const sessionResponse = await fetch('get_current_session.php');
            const sessionData = await sessionResponse.json();
            if (sessionData.success && sessionData.login_at) {
                const loginDate = new Date(sessionData.login_at);
                const loginStr = loginDate.toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
                const loginTimeEl = document.getElementById('logoutLoginTime');
                if (loginTimeEl) loginTimeEl.textContent = loginStr;
            }
        } catch (error) {
            console.error('Error loading user info:', error);
        }
    }

    // Submit turnover
    window.submitLogoutTurnover = async function() {
        const cash = parseFloat(document.getElementById('logoutCashAmount').value) || 0;

        if (cash === 0) {
            alert('Please enter cash amount.');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'turnover');
            formData.append('cash_amount', cash);
            formData.append('total_amount', cash);

            const response = await fetch('process_logout.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                window.location.href = 'logout.php';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to submit turnover. Please try again.');
        }
    };

    // Confirm break
    window.confirmLogoutBreak = async function() {
        try {
            const formData = new FormData();
            formData.append('action', 'break');

            const response = await fetch('process_logout.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                window.location.href = 'logout.php';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to record break. Please try again.');
        }
    };

    // Intercept all logout clicks and show modal instead of navigating
    function interceptLogoutClicks() {
        document.addEventListener('click', function(e) {
            const target = e.target.closest('[onclick*="logout.php"], [href*="logout.php"]');
            if (target) {
                e.preventDefault();
                e.stopPropagation();
                showLogoutModal();
                return false;
            }
        }, true);
    }
})();
