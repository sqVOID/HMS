<?php
session_start();
require_once 'access_check.php';
checkAccess('Modification.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="icon" type="image/svg+xml" href="Icon/MoonClaveLogo3.svg">
    <title>Modification</title>
    <link rel="stylesheet" href="Booking.css?v=5">
    <style>
        /* Modification page column width overrides */

        /* Col 1: Room ID — more space */
        .booking-table th:nth-child(1),
        .booking-table td:nth-child(1) {
            width: 8% !important;
            min-width: 120px !important;
        }

        /* Col 2: Guest Names — give more room */
        .booking-table th:nth-child(2),
        .booking-table td:nth-child(2) {
            width: 9% !important;
            min-width: 130px !important;
        }

        /* Col 3: Check-In — same size as Check-Out */
        .booking-table th:nth-child(3),
        .booking-table td:nth-child(3) {
            width: 3% !important;
            min-width: 80px !important;
        }

        /* Col 4: Duration — same size as Check-Out */
        .booking-table th:nth-child(4),
        .booking-table td:nth-child(4) {
            width: 3% !important;
            min-width: 75px !important;
        }

        /* Col 5: Check-Out */
        .booking-table th:nth-child(5),
        .booking-table td:nth-child(5) {
            width: 3% !important;
            min-width: 80px !important;
        }

        /* Col 9: Payment Method — narrow like Payment Status */
        .booking-table th:nth-child(9),
        .booking-table td:nth-child(9) {
            width: 7% !important;
            min-width: 85px !important;
        }

        /* Col 10: Additional — give more room */
        .booking-table th:nth-child(10),
        .booking-table td:nth-child(10) {
            width: 8% !important;
            min-width: 110px !important;
        }

        /* Col 11: Reason — give more room */
        .booking-table th:nth-child(11),
        .booking-table td:nth-child(11) {
            width: 8% !important;
            min-width: 110px !important;
        }

        /* Col 12: Payment Status — compact */
        .booking-table th:nth-child(12),
        .booking-table td:nth-child(12) {
            width: 7% !important;
            min-width: 85px !important;
        }

        /* Date filter input */
        .date-filter-container {
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            padding: 0;
            height: 36px;
            gap: 0;
            overflow: hidden;
        }

        .date-filter-label {
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            color: #555;
            font-weight: 500;
            white-space: nowrap;
            padding: 0 10px;
            background: #f8f8f8;
            height: 100%;
            display: flex;
            align-items: center;
            border-right: 1px solid #e5e5e5;
        }

        .date-filter-input {
            border: none;
            outline: none;
            background: transparent;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            color: #333;
            cursor: pointer;
            padding: 0 10px;
            height: 100%;
            min-width: 130px;
        }

        .date-filter-input::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.5;
            padding: 0;
            margin: 0;
        }

        .date-filter-input::-webkit-calendar-picker-indicator:hover {
            opacity: 0.8;
        }

        .date-filter-clear {
            background: none;
            border: none;
            border-left: 1px solid #e5e5e5;
            cursor: pointer;
            color: #999;
            font-size: 18px;
            padding: 0 10px;
            height: 100%;
            line-height: 1;
            display: none;
            transition: all 0.2s ease;
        }

        .date-filter-clear:hover {
            background: #f5f5f5;
            color: #d32f2f;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="role-based-menu.js?v=18"></script>
    <script src="auto_logout.js?v=5" defer></script>
    <script src="cancellation-notification.js?v=5" defer></script>
    <script>
        // Date formatting helpers matching Booking.html
        function formatDateTime(date) {
            if (typeof date === 'string') {
                date = new Date(date);
            }
            if (isNaN(date.getTime())) {
                return '';
            }
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            let hours = date.getHours();
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            const hoursFormatted = String(hours).padStart(2, '0');
            return `${year}-${month}-${day} ${hoursFormatted}:${minutes} ${ampm}`;
        }

        function formatTableDateTime(datetimeString) {
            if (!datetimeString || datetimeString === '-') return '-';
            const formatted = formatDateTime(datetimeString);
            if (!formatted) return '-';
            const parts = formatted.split(' ');
            if (parts.length < 3) return formatted;
            return `${parts[0]}<br>${parts[1]} ${parts[2]}`;
        }

        function navigateToPage(page) {
            if (window.innerWidth <= 1024) {
                const panel = document.querySelector('.left-panel');
                if (panel) {
                    panel.classList.remove('open');
                }
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
            const leftPanel = document.querySelector('.left-panel');
            if (!leftPanel) return;

            if (leftPanel.classList.contains('minimized')) {
                leftPanel.classList.remove('minimized');
            } else {
                leftPanel.classList.add('minimized');
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

        let breakfastData = [];
        let inventoryItems = [];
        let modalAdditionalCharges = [];
        let modalItemCounter = 0;
        let payHistAdditionalCharges = {};
        let payHistBreakfasts = {};
        let payHistExtendBreakfasts = {};
        let payHistItemCounter = 0;
        let modalBreakfasts = [];
        let breakfastCounter = 0;

        // Fetch breakfast and inventory on load
        document.addEventListener('DOMContentLoaded', function () {
            fetch('get_breakfast.php')
                .then(res => res.json())
                .then(data => { if (data.success) breakfastData = data.items; });

            fetch('get_additems.php')
                .then(res => res.json())
                .then(data => { if (data.success) inventoryItems = data.items; });
        });

        function addModalBreakfastDropdown(selectedValue = '', isPromo = false) {
            breakfastCounter++;
            const id = `modal-bf-${breakfastCounter}`;
            modalBreakfasts.push({ id, value: selectedValue, promo: isPromo });
            renderModalBreakfast();
        }

        function removeModalBreakfastDropdown(id) {
            modalBreakfasts = modalBreakfasts.filter(b => b.id !== id);
            renderModalBreakfast();
        }

        function updateModalBreakfast(id, newValue) {
            const item = modalBreakfasts.find(b => b.id === id);
            if (item) {
                item.value = newValue;
                item.promo = newValue.includes('(Promo)');
            }
            renderModalBreakfast();
        }

        function renderModalBreakfast() {
            const container = document.getElementById('modalBreakfastContainer');
            if (!container) return;

            container.innerHTML = '';

            modalBreakfasts.forEach((bf, index) => {
                const el = document.createElement('div');
                el.style.display = 'flex';
                el.style.gap = '8px';

                const select = document.createElement('select');
                select.className = 'detail-input';
                select.style.flex = '1';
                select.style.padding = '8px';
                select.onchange = (e) => updateModalBreakfast(bf.id, e.target.value);

                const optNone = document.createElement('option');
                optNone.value = '';
                optNone.textContent = 'None';
                select.appendChild(optNone);

                const promoSuffix = ' (Promo)';
                breakfastData.forEach(food => {
                    const itemName = food.food_name;

                    const optPromo = document.createElement('option');
                    optPromo.value = `1 ${itemName}${promoSuffix}`;
                    optPromo.textContent = `1 ${itemName}${promoSuffix}`;
                    if (bf.value === optPromo.value) optPromo.selected = true;
                    select.appendChild(optPromo);
                });

                // Allow custom legacy values to show up properly
                if (bf.value && bf.value !== 'None' && !Array.from(select.options).some(opt => opt.value === bf.value)) {
                    const optCustom = document.createElement('option');
                    optCustom.value = bf.value;
                    optCustom.textContent = bf.value;
                    optCustom.selected = true;
                    select.appendChild(optCustom);
                }

                el.appendChild(select);

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = '×';
                btn.style.padding = '8px 12px';
                btn.style.background = '#f44336';
                btn.style.color = 'white';
                btn.style.border = 'none';
                btn.style.borderRadius = '4px';
                btn.style.cursor = 'pointer';
                btn.onclick = () => removeModalBreakfastDropdown(bf.id);
                el.appendChild(btn);

                container.appendChild(el);
            });

            // Update hidden input
            const valid = modalBreakfasts.map(b => b.value).filter(val => val && val !== 'None');
            document.getElementById('modalBreakfast').value = valid.length > 0 ? valid.join(' | ') : '';
        }

        function addModalAdditionalItem() {
            const itemId = `modal-additional-${++modalItemCounter}`;
            modalAdditionalCharges.push({
                id: itemId,
                type: 'food',
                selectedItem: '',
                quantity: 1,
                price: 0
            });
            renderModalAdditionalItems();
        }

        function removeModalAdditionalItem(itemId) {
            modalAdditionalCharges = modalAdditionalCharges.filter(item => item.id !== itemId);
            renderModalAdditionalItems();
        }

        function updateModalItemType(itemId, newType) {
            const item = modalAdditionalCharges.find(i => i.id === itemId);
            if (item) {
                item.type = newType;
                item.selectedItem = '';
                item.quantity = 1;
                item.price = 0;
            }
            renderModalAdditionalItems();
        }

        function updateModalSelectedItem(itemId, selectedValue) {
            const item = modalAdditionalCharges.find(i => i.id === itemId);
            if (!item) return;

            item.selectedItem = selectedValue;
            if (item.type === 'food') {
                const foodItem = breakfastData.find(f => f.food_name === selectedValue);
                item.price = foodItem ? parseFloat(foodItem.price) * item.quantity : 0;
            } else if (item.type === 'item') {
                const invItem = inventoryItems.find(i => i.product_name === selectedValue || i.name === selectedValue);
                item.price = invItem ? parseFloat(invItem.price) * item.quantity : 0;
            }
            renderModalAdditionalItems();
        }

        function updateModalQuantity(itemId, quantity) {
            const item = modalAdditionalCharges.find(i => i.id === itemId);
            if (!item) return;

            item.quantity = parseInt(quantity) || 1;
            if (item.type === 'food' && item.selectedItem) {
                const foodItem = breakfastData.find(f => f.food_name === item.selectedItem);
                item.price = foodItem ? parseFloat(foodItem.price) * item.quantity : 0;
            } else if (item.type === 'item' && item.selectedItem) {
                const invItem = inventoryItems.find(i => i.product_name === item.selectedItem || i.name === item.selectedItem);
                item.price = invItem ? parseFloat(invItem.price) * item.quantity : 0;
            }
            renderModalAdditionalItems();
        }

        function renderModalAdditionalItems() {
            const container = document.getElementById('modalAdditionalItemsList');
            const chargesEl = document.getElementById('modalAdditionalCharges');

            if (modalAdditionalCharges.length === 0) {
                if (container) {
                    container.innerHTML = '<div style="text-align: center; padding: 10px; color: #999; font-size: 13px;">No additional food/items</div>';
                }
                if (chargesEl) chargesEl.value = '[]';
                return;
            }

            if (!container) {
                if (chargesEl) {
                    const validCharges = modalAdditionalCharges.filter(item =>
                        (item.selectedItem || item.itemName) &&
                        (item.selectedItem || item.itemName) !== 'Select Food' &&
                        (item.selectedItem || item.itemName) !== 'Select Item'
                    );
                    chargesEl.value = JSON.stringify(validCharges);
                }
                return;
            }

            container.innerHTML = modalAdditionalCharges.map(item => {
                let itemSelectOptions = '';
                const selectedValue = item.selectedItem || item.itemName || '';

                if (item.type === 'food') {
                    itemSelectOptions = `<option value="">Select Food</option>` +
                        breakfastData.map(f => `<option value="${f.food_name}" ${selectedValue === f.food_name ? 'selected' : ''}>${f.food_name} - ₱${parseFloat(f.price).toFixed(2)}</option>`).join('');
                    if (selectedValue && !breakfastData.find(f => f.food_name.toLowerCase() === selectedValue.toLowerCase())) {
                        itemSelectOptions += `<option value="${selectedValue}" selected>${selectedValue}</option>`;
                    }
                } else if (item.type === 'item') {
                    itemSelectOptions = `<option value="">Select Item</option>` +
                        inventoryItems.map(i => {
                            const itemName = i.product_name || i.name;
                            return `<option value="${itemName}" ${selectedValue === itemName ? 'selected' : ''}>${itemName} - ₱${parseFloat(i.price || 0).toFixed(2)}</option>`;
                        }).join('');
                    if (selectedValue && !inventoryItems.find(i => (i.product_name || i.name).toLowerCase() === selectedValue.toLowerCase())) {
                        itemSelectOptions += `<option value="${selectedValue}" selected>${selectedValue}</option>`;
                    }
                }

                return `
                    <div style="border: 1px solid #ddd; padding: 12px; border-radius: 6px; margin-bottom: 8px;">
                        <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                            <select class="detail-input" onchange="updateModalItemType('${item.id}', this.value)" style="flex: 1; padding: 8px;">
                                <option value="food" ${item.type === 'food' ? 'selected' : ''}>Food</option>
                                <option value="item" ${item.type === 'item' ? 'selected' : ''}>Item</option>
                            </select>
                            <select class="detail-input" onchange="updateModalSelectedItem('${item.id}', this.value)" style="flex: 1; padding: 8px;">
                                ${itemSelectOptions}
                            </select>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="number" min="1" value="${item.quantity || 1}" onchange="updateModalQuantity('${item.id}', this.value)" placeholder="Qty" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; flex: 1;">
                            <div style="padding: 8px 12px; background: #f5f5f5; border-radius: 4px; font-weight: 600; flex: 1;">₱${(item.price || 0).toFixed(2)}</div>
                            <button type="button" onclick="removeModalAdditionalItem('${item.id}')" style="padding: 8px 12px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">Remove</button>
                        </div>
                    </div>
                `;
            }).join('');

            const validCharges = modalAdditionalCharges.filter(item =>
                (item.selectedItem || item.itemName) &&
                (item.selectedItem || item.itemName) !== 'Select Food' &&
                (item.selectedItem || item.itemName) !== 'Select Item'
            );
            document.getElementById('modalAdditionalCharges').value = JSON.stringify(validCharges);
        }

        function getPayHistChargesArray(paymentIndex) {
            if (!payHistAdditionalCharges[paymentIndex]) {
                payHistAdditionalCharges[paymentIndex] = [];
            }
            return payHistAdditionalCharges[paymentIndex];
        }

        function addPayHistAdditionalItem(paymentIndex) {
            const itemId = `payhist-add-${paymentIndex}-${++payHistItemCounter}`;
            getPayHistChargesArray(paymentIndex).push({
                id: itemId,
                type: 'food',
                selectedItem: '',
                quantity: 1,
                price: 0
            });
            renderPayHistAdditionalItems(paymentIndex);
        }

        function removePayHistAdditionalItem(paymentIndex, itemId) {
            payHistAdditionalCharges[paymentIndex] = getPayHistChargesArray(paymentIndex)
                .filter(item => item.id !== itemId);
            renderPayHistAdditionalItems(paymentIndex);
        }

        function updatePayHistItemType(paymentIndex, itemId, newType) {
            const item = getPayHistChargesArray(paymentIndex).find(i => i.id === itemId);
            if (item) {
                item.type = newType;
                item.selectedItem = '';
                item.quantity = 1;
                item.price = 0;
            }
            renderPayHistAdditionalItems(paymentIndex);
        }

        function updatePayHistSelectedItem(paymentIndex, itemId, selectedValue) {
            const item = getPayHistChargesArray(paymentIndex).find(i => i.id === itemId);
            if (!item) return;

            item.selectedItem = selectedValue;
            if (item.type === 'food') {
                const foodItem = breakfastData.find(f => f.food_name === selectedValue);
                item.price = foodItem ? parseFloat(foodItem.price) * item.quantity : 0;
            } else if (item.type === 'item') {
                const invItem = inventoryItems.find(i => i.product_name === selectedValue || i.name === selectedValue);
                item.price = invItem ? parseFloat(invItem.price) * item.quantity : 0;
            }
            renderPayHistAdditionalItems(paymentIndex);
        }

        function updatePayHistQuantity(paymentIndex, itemId, quantity) {
            const item = getPayHistChargesArray(paymentIndex).find(i => i.id === itemId);
            if (!item) return;

            item.quantity = parseInt(quantity, 10) || 1;
            if (item.type === 'food' && item.selectedItem) {
                const foodItem = breakfastData.find(f => f.food_name === item.selectedItem);
                item.price = foodItem ? parseFloat(foodItem.price) * item.quantity : 0;
            } else if (item.type === 'item' && item.selectedItem) {
                const invItem = inventoryItems.find(i => i.product_name === item.selectedItem || i.name === item.selectedItem);
                item.price = invItem ? parseFloat(invItem.price) * item.quantity : 0;
            }
            renderPayHistAdditionalItems(paymentIndex);
        }

        function renderPayHistAdditionalItems(paymentIndex) {
            const container = document.getElementById(`payHist_add_charges_list_${paymentIndex}`);
            const charges = getPayHistChargesArray(paymentIndex);
            const locked = isPaymentCardLocked(paymentIndex);
            const disabledAttr = locked ? 'disabled' : '';

            if (container) {
                if (charges.length === 0) {
                    container.innerHTML = '<div style="text-align:center;padding:8px;color:#9ca3af;font-size:12px;">No additional food/items</div>';
                } else {
                    container.innerHTML = charges.map(item => {
                        let itemSelectOptions = '';
                        const selectedValue = item.selectedItem || item.itemName || '';

                        if (item.type === 'food') {
                            itemSelectOptions = `<option value="">Select Food</option>` +
                                breakfastData.map(f =>
                                    `<option value="${f.food_name}" ${selectedValue === f.food_name ? 'selected' : ''}>${f.food_name} - ₱${parseFloat(f.price).toFixed(2)}</option>`
                                ).join('');
                            if (selectedValue && !breakfastData.find(f => f.food_name.toLowerCase() === selectedValue.toLowerCase())) {
                                itemSelectOptions += `<option value="${selectedValue}" selected>${selectedValue}</option>`;
                            }
                        } else if (item.type === 'item') {
                            itemSelectOptions = `<option value="">Select Item</option>` +
                                inventoryItems.map(i => {
                                    const itemName = i.product_name || i.name;
                                    return `<option value="${itemName}" ${selectedValue === itemName ? 'selected' : ''}>${itemName} - ₱${parseFloat(i.price || 0).toFixed(2)}</option>`;
                                }).join('');
                            if (selectedValue && !inventoryItems.find(i => (i.product_name || i.name).toLowerCase() === selectedValue.toLowerCase())) {
                                itemSelectOptions += `<option value="${selectedValue}" selected>${selectedValue}</option>`;
                            }
                        }

                        return `
                        <div style="border:1px solid #e5e7eb;padding:10px;border-radius:6px;margin-bottom:8px;background:#fafafa;">
                            <div style="display:flex;gap:8px;margin-bottom:8px;">
                                <select class="detail-input" onchange="updatePayHistItemType(${paymentIndex}, '${item.id}', this.value)" style="flex:1;padding:8px;" ${disabledAttr}>
                                    <option value="food" ${item.type === 'food' ? 'selected' : ''}>Food</option>
                                    <option value="item" ${item.type === 'item' ? 'selected' : ''}>Item</option>
                                </select>
                                <select class="detail-input" onchange="updatePayHistSelectedItem(${paymentIndex}, '${item.id}', this.value)" style="flex:1;padding:8px;" ${disabledAttr}>
                                    ${itemSelectOptions}
                                </select>
                            </div>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="number" min="1" value="${item.quantity || 1}" onchange="updatePayHistQuantity(${paymentIndex}, '${item.id}', this.value)" placeholder="Qty" class="detail-input" style="flex:1;" ${disabledAttr}>
                                <div style="padding:8px 12px;background:#f3f4f6;border-radius:4px;font-weight:600;flex:1;font-size:13px;">₱${(item.price || 0).toFixed(2)}</div>
                                <button type="button" onclick="removePayHistAdditionalItem(${paymentIndex}, '${item.id}')" style="padding:8px 12px;background:#f44336;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;" ${disabledAttr}>Remove</button>
                            </div>
                        </div>`;
                    }).join('');
                }

                const addBtn = document.getElementById(`payHist_add_charges_btn_${paymentIndex}`);
                if (addBtn) {
                    addBtn.disabled = locked;
                    addBtn.style.opacity = locked ? '0.5' : '1';
                    addBtn.style.cursor = locked ? 'not-allowed' : 'pointer';
                }
            }

            const validCharges = getPayHistChargesArray(paymentIndex).filter(item =>
                (item.selectedItem || item.itemName) &&
                (item.selectedItem || item.itemName) !== 'Select Food' &&
                (item.selectedItem || item.itemName) !== 'Select Item'
            );
            const jsonEl = document.getElementById(`payHist_add_charges_json_${paymentIndex}`);
            if (jsonEl) jsonEl.value = JSON.stringify(validCharges);

            updatePayHistAdditionals(paymentIndex);
        }

        function getCheckInPaymentIndex(booking, totalPayments) {
            if (isReservationWithDownpayment(booking) && totalPayments > 1) return 1;
            return 0;
        }

        function isExtensionPaymentIndex(booking, paymentIndex, totalPayments) {
            const extendPrice = parseFloat(booking.extend_price) || 0;
            if (extendPrice <= 0) return false;
            if (isReservationWithDownpayment(booking) && totalPayments > 1) {
                return totalPayments > 2 && paymentIndex >= 2;
            }
            return totalPayments >= 2 && paymentIndex >= 1;
        }

        function getPerPaymentMetaForDisplay(booking, paymentIndex, totalPayments) {
            if (isReservationWithDownpayment(booking) && totalPayments > 1 && paymentIndex === 0) {
                return { duration: '', promo: '', breakfast: '' };
            }

            if (isExtensionPaymentIndex(booking, paymentIndex, totalPayments)) {
                return { duration: '', promo: '', breakfast: '' };
            }

            const checkInIdx = getCheckInPaymentIndex(booking, totalPayments);
            if (paymentIndex === checkInIdx) {
                const promoVal = booking.promo || '';
                const hasPromo = promoVal && !['None', 'Regular', 'Select Bundle', '-'].includes(promoVal);
                return {
                    duration: hasPromo ? '0' : (booking.duration || ''),
                    promo: hasPromo ? promoVal : (promoVal !== 'None' ? promoVal : ''),
                    breakfast: booking.breakfast || ''
                };
            }

            return { duration: '', promo: '', breakfast: '' };
        }

        function getFirstExtensionPaymentIndex(booking, totalPayments) {
            for (let i = 0; i < totalPayments; i++) {
                if (isExtensionPaymentIndex(booking, i, totalPayments)) return i;
            }
            return -1;
        }

        function getPerPaymentExtendForDisplay(booking, paymentIndex, totalPayments) {
            const empty = {
                extend_hours: '',
                extend_minutes: '',
                extend_regular_rate: '',
                extend_bundle_rate: '',
                extend_bundle_breakfast: ''
            };
            const firstExtIdx = getFirstExtensionPaymentIndex(booking, totalPayments);
            if (firstExtIdx < 0 || paymentIndex !== firstExtIdx) return empty;

            const extH = parseInt(booking.extend_hours, 10) || 0;
            const extM = parseInt(booking.extend_minutes, 10) || 0;
            const extRegular = parseFloat(booking.extend_regular_rate) || 0;
            const extBundle = parseFloat(booking.extend_bundle_rate) || 0;
            let breakfast = '';
            const ebb = booking.extend_bundle_breakfast;
            if (ebb && ebb !== '0' && ebb !== 'None' && ebb !== '-') {
                breakfast = String(ebb);
            }

            return {
                extend_hours: extH > 0 ? String(extH) : '',
                extend_minutes: extM > 0 ? String(extM) : '',
                extend_regular_rate: extRegular > 0 ? extRegular.toFixed(2) : '',
                extend_bundle_rate: extBundle > 0 ? extBundle.toFixed(2) : '',
                extend_bundle_breakfast: breakfast
            };
        }

        function collectPayHistExtendBreakfastString(paymentIndex) {
            const items = payHistExtendBreakfasts[paymentIndex] || [];
            return items.map(b => b.value).filter(v => v && v !== 'None').join(' | ');
        }

        function getPayHistExtendForIndex(idx) {
            const hoursEl = document.getElementById(`payHist_extend_hours_${idx}`);
            const minsEl = document.getElementById(`payHist_extend_minutes_${idx}`);
            const regularEl = document.getElementById(`payHist_extend_regular_${idx}`);
            const bundleEl = document.getElementById(`payHist_extend_bundle_${idx}`);
            return {
                extend_hours: hoursEl ? hoursEl.value : '',
                extend_minutes: minsEl ? minsEl.value : '',
                extend_regular_rate: regularEl ? regularEl.value : '',
                extend_bundle_rate: bundleEl ? bundleEl.value : '',
                extend_bundle_breakfast: collectPayHistExtendBreakfastString(idx)
            };
        }

        function calcExtendPriceFromFields(ext) {
            const regular = parseFloat(ext.extend_regular_rate) || 0;
            const bundle = parseFloat(ext.extend_bundle_rate) || 0;
            if (regular > 0) return regular;
            if (bundle > 0) return bundle;
            const hours = parseInt(ext.extend_hours, 10) || 0;
            const minutes = parseInt(ext.extend_minutes, 10) || 0;
            return (hours * 200) + (minutes === 30 ? 100 : 0);
        }

        function collectPayHistBreakfastString(paymentIndex) {
            const items = payHistBreakfasts[paymentIndex] || [];
            return items.map(b => b.value).filter(v => v && v !== 'None').join(' | ');
        }

        function getPayHistMetaForIndex(idx) {
            const durationEl = document.getElementById(`payHist_duration_${idx}`);
            const promoEl = document.getElementById(`payHist_promo_${idx}`);
            return {
                duration: durationEl ? durationEl.value : '',
                promo: promoEl ? promoEl.value : '',
                breakfast: collectPayHistBreakfastString(idx)
            };
        }

        function getBookingForPaymentLines(booking, paymentIndex, totalPayments) {
            if (!document.getElementById(`payHist_duration_${paymentIndex}`)) return booking;

            if (isExtensionPaymentIndex(booking, paymentIndex, totalPayments)) {
                const ext = getPayHistExtendForIndex(paymentIndex);
                const extBreakfast = ext.extend_bundle_breakfast || '';
                const extBundle = parseFloat(ext.extend_bundle_rate) || 0;
                return {
                    ...booking,
                    extend_hours: parseInt(ext.extend_hours, 10) || 0,
                    extend_minutes: parseInt(ext.extend_minutes, 10) || 0,
                    extend_regular_rate: parseFloat(ext.extend_regular_rate) || 0,
                    extend_bundle_rate: extBundle,
                    extend_bundle_breakfast: extBreakfast,
                    extend_price: calcExtendPriceFromFields(ext),
                    breakfast: extBreakfast,
                    promo: extBundle > 0 ? (booking.promo || '') : booking.promo,
                    duration: ''
                };
            }

            const meta = getPayHistMetaForIndex(paymentIndex);
            return {
                ...booking,
                duration: meta.duration,
                promo: meta.promo,
                breakfast: meta.breakfast,
                extend_bundle_breakfast: ''
            };
        }

        function populatePayHistMetaSelects(idx, booking, totalPayments) {
            const meta = getPerPaymentMetaForDisplay(booking, idx, totalPayments);
            const isExt = isExtensionPaymentIndex(booking, idx, totalPayments);
            const durSel = document.getElementById(`payHist_duration_${idx}`);
            const promoSel = document.getElementById(`payHist_promo_${idx}`);
            const modalDur = document.getElementById('modalDuration');
            const modalPromo = document.getElementById('modalPromo');

            if (durSel && modalDur) {
                const modalReady = modalDur.options.length > 0 && modalDur.options[0].textContent !== 'Loading...';
                if (modalReady) {
                    durSel.innerHTML = modalDur.innerHTML;
                } else if (!durSel.options.length || durSel.options.length <= 1) {
                    durSel.innerHTML = '<option value="">—</option>';
                }
                durSel.value = isExt ? '' : meta.duration;
                if (meta.duration && !Array.from(durSel.options).some(o => o.value === String(meta.duration))) {
                    const opt = document.createElement('option');
                    opt.value = meta.duration;
                    opt.textContent = `${meta.duration} hours (Promo)`;
                    opt.selected = true;
                    durSel.insertBefore(opt, durSel.firstChild);
                }
            }

            if (promoSel && modalPromo) {
                promoSel.innerHTML = modalPromo.innerHTML;
                promoSel.value = meta.promo || '';
                if (meta.promo && !Array.from(promoSel.options).some(o => o.value === meta.promo)) {
                    const opt = document.createElement('option');
                    opt.value = meta.promo;
                    opt.textContent = meta.promo;
                    opt.selected = true;
                    promoSel.appendChild(opt);
                }
            }
        }

        function initPayHistBreakfast(booking, paymentIndex, totalPayments) {
            const meta = getPerPaymentMetaForDisplay(booking, paymentIndex, totalPayments);
            payHistBreakfasts[paymentIndex] = [];
            if (meta.breakfast && meta.breakfast !== 'None') {
                meta.breakfast.split('|').map(s => s.trim()).filter(Boolean).forEach(part => {
                    payHistBreakfasts[paymentIndex].push({
                        id: `payhist-bf-${paymentIndex}-${++payHistItemCounter}`,
                        value: part,
                        promo: part.includes('(Promo)')
                    });
                });
            }
            if (!payHistBreakfasts[paymentIndex].length) {
                payHistBreakfasts[paymentIndex].push({
                    id: `payhist-bf-${paymentIndex}-${++payHistItemCounter}`,
                    value: '',
                    promo: false
                });
            }
            renderPayHistBreakfast(paymentIndex);
        }

        function addPayHistBreakfastDropdown(paymentIndex, selectedValue = '') {
            if (isPaymentCardLocked(paymentIndex)) return;
            if (!payHistBreakfasts[paymentIndex]) payHistBreakfasts[paymentIndex] = [];
            payHistBreakfasts[paymentIndex].push({
                id: `payhist-bf-${paymentIndex}-${++payHistItemCounter}`,
                value: selectedValue,
                promo: selectedValue.includes('(Promo)')
            });
            renderPayHistBreakfast(paymentIndex);
            updatePayHistMeta(paymentIndex);
        }

        function removePayHistBreakfastDropdown(paymentIndex, id) {
            if (isPaymentCardLocked(paymentIndex)) return;
            payHistBreakfasts[paymentIndex] = (payHistBreakfasts[paymentIndex] || []).filter(b => b.id !== id);
            if (!payHistBreakfasts[paymentIndex].length) {
                payHistBreakfasts[paymentIndex].push({
                    id: `payhist-bf-${paymentIndex}-${++payHistItemCounter}`,
                    value: '',
                    promo: false
                });
            }
            renderPayHistBreakfast(paymentIndex);
            updatePayHistMeta(paymentIndex);
        }

        function updatePayHistBreakfastItem(paymentIndex, id, newValue) {
            const item = (payHistBreakfasts[paymentIndex] || []).find(b => b.id === id);
            if (item) {
                item.value = newValue;
                item.promo = newValue.includes('(Promo)');
            }
            renderPayHistBreakfast(paymentIndex);
            updatePayHistMeta(paymentIndex);
        }

        function renderPayHistBreakfast(paymentIndex) {
            const container = document.getElementById(`payHist_breakfast_list_${paymentIndex}`);
            if (!container) return;

            const locked = isPaymentCardLocked(paymentIndex);
            container.innerHTML = '';

            (payHistBreakfasts[paymentIndex] || []).forEach(bf => {
                const el = document.createElement('div');
                el.style.display = 'flex';
                el.style.gap = '8px';
                el.style.marginBottom = '8px';

                const select = document.createElement('select');
                select.className = 'detail-input';
                select.style.flex = '1';
                select.style.padding = '8px';
                select.disabled = locked;
                select.onchange = (e) => updatePayHistBreakfastItem(paymentIndex, bf.id, e.target.value);

                const optNone = document.createElement('option');
                optNone.value = '';
                optNone.textContent = 'None';
                select.appendChild(optNone);

                const promoSuffix = ' (Promo)';
                breakfastData.forEach(food => {
                    const itemName = food.food_name;
                    const optPromo = document.createElement('option');
                    optPromo.value = `1 ${itemName}${promoSuffix}`;
                    optPromo.textContent = `1 ${itemName}${promoSuffix}`;
                    if (bf.value === optPromo.value) optPromo.selected = true;
                    select.appendChild(optPromo);
                });

                if (bf.value && bf.value !== 'None' && !Array.from(select.options).some(opt => opt.value === bf.value)) {
                    const optCustom = document.createElement('option');
                    optCustom.value = bf.value;
                    optCustom.textContent = bf.value;
                    optCustom.selected = true;
                    select.appendChild(optCustom);
                }

                el.appendChild(select);

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = '×';
                btn.disabled = locked;
                btn.style.padding = '8px 12px';
                btn.style.background = '#f44336';
                btn.style.color = 'white';
                btn.style.border = 'none';
                btn.style.borderRadius = '4px';
                btn.style.cursor = locked ? 'not-allowed' : 'pointer';
                btn.style.opacity = locked ? '0.5' : '1';
                btn.onclick = () => removePayHistBreakfastDropdown(paymentIndex, bf.id);
                el.appendChild(btn);

                container.appendChild(el);
            });

            const jsonEl = document.getElementById(`payHist_breakfast_json_${paymentIndex}`);
            if (jsonEl) jsonEl.value = collectPayHistBreakfastString(paymentIndex);

            const addBtn = document.getElementById(`payHist_breakfast_btn_${paymentIndex}`);
            if (addBtn) {
                addBtn.disabled = locked;
                addBtn.style.opacity = locked ? '0.5' : '1';
                addBtn.style.cursor = locked ? 'not-allowed' : 'pointer';
            }
        }

        function initPayHistExtendFields(booking, paymentIndex, totalPayments) {
            const ext = getPerPaymentExtendForDisplay(booking, paymentIndex, totalPayments);
            const hoursEl = document.getElementById(`payHist_extend_hours_${paymentIndex}`);
            const minsEl = document.getElementById(`payHist_extend_minutes_${paymentIndex}`);
            const regularEl = document.getElementById(`payHist_extend_regular_${paymentIndex}`);
            const bundleEl = document.getElementById(`payHist_extend_bundle_${paymentIndex}`);

            if (hoursEl) hoursEl.value = ext.extend_hours;
            if (minsEl) minsEl.value = ext.extend_minutes;
            if (regularEl) regularEl.value = ext.extend_regular_rate;
            if (bundleEl) bundleEl.value = ext.extend_bundle_rate;

            payHistExtendBreakfasts[paymentIndex] = [];
            if (ext.extend_bundle_breakfast) {
                ext.extend_bundle_breakfast.split('|').map(s => s.trim()).filter(Boolean).forEach(part => {
                    payHistExtendBreakfasts[paymentIndex].push({
                        id: `payhist-ext-bf-${paymentIndex}-${++payHistItemCounter}`,
                        value: part,
                        promo: part.includes('(Promo)')
                    });
                });
            }
            if (!payHistExtendBreakfasts[paymentIndex].length) {
                payHistExtendBreakfasts[paymentIndex].push({
                    id: `payhist-ext-bf-${paymentIndex}-${++payHistItemCounter}`,
                    value: '',
                    promo: false
                });
            }
            renderPayHistExtendBreakfast(paymentIndex);
        }

        function addPayHistExtendBreakfastDropdown(paymentIndex, selectedValue = '') {
            if (isPaymentCardLocked(paymentIndex)) return;
            if (!payHistExtendBreakfasts[paymentIndex]) payHistExtendBreakfasts[paymentIndex] = [];
            payHistExtendBreakfasts[paymentIndex].push({
                id: `payhist-ext-bf-${paymentIndex}-${++payHistItemCounter}`,
                value: selectedValue,
                promo: selectedValue.includes('(Promo)')
            });
            renderPayHistExtendBreakfast(paymentIndex);
            updatePayHistExtend(paymentIndex);
        }

        function removePayHistExtendBreakfastDropdown(paymentIndex, id) {
            if (isPaymentCardLocked(paymentIndex)) return;
            payHistExtendBreakfasts[paymentIndex] = (payHistExtendBreakfasts[paymentIndex] || []).filter(b => b.id !== id);
            if (!payHistExtendBreakfasts[paymentIndex].length) {
                payHistExtendBreakfasts[paymentIndex].push({
                    id: `payhist-ext-bf-${paymentIndex}-${++payHistItemCounter}`,
                    value: '',
                    promo: false
                });
            }
            renderPayHistExtendBreakfast(paymentIndex);
            updatePayHistExtend(paymentIndex);
        }

        function updatePayHistExtendBreakfastItem(paymentIndex, id, newValue) {
            const item = (payHistExtendBreakfasts[paymentIndex] || []).find(b => b.id === id);
            if (item) {
                item.value = newValue;
                item.promo = newValue.includes('(Promo)');
            }
            renderPayHistExtendBreakfast(paymentIndex);
            updatePayHistExtend(paymentIndex);
        }

        function renderPayHistExtendBreakfast(paymentIndex) {
            const container = document.getElementById(`payHist_ext_breakfast_list_${paymentIndex}`);
            if (!container) return;

            const locked = isPaymentCardLocked(paymentIndex);
            container.innerHTML = '';

            (payHistExtendBreakfasts[paymentIndex] || []).forEach(bf => {
                const el = document.createElement('div');
                el.style.display = 'flex';
                el.style.gap = '8px';
                el.style.marginBottom = '8px';

                const select = document.createElement('select');
                select.className = 'detail-input';
                select.style.flex = '1';
                select.style.padding = '8px';
                select.disabled = locked;
                select.onchange = (e) => updatePayHistExtendBreakfastItem(paymentIndex, bf.id, e.target.value);

                const optNone = document.createElement('option');
                optNone.value = '';
                optNone.textContent = 'None';
                select.appendChild(optNone);

                const promoSuffix = ' (Promo)';
                breakfastData.forEach(food => {
                    const itemName = food.food_name;
                    const optPromo = document.createElement('option');
                    optPromo.value = `1 ${itemName}${promoSuffix}`;
                    optPromo.textContent = `1 ${itemName}${promoSuffix}`;
                    if (bf.value === optPromo.value) optPromo.selected = true;
                    select.appendChild(optPromo);
                });

                if (bf.value && bf.value !== 'None' && !Array.from(select.options).some(opt => opt.value === bf.value)) {
                    const optCustom = document.createElement('option');
                    optCustom.value = bf.value;
                    optCustom.textContent = bf.value;
                    optCustom.selected = true;
                    select.appendChild(optCustom);
                }

                el.appendChild(select);

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = '×';
                btn.disabled = locked;
                btn.style.padding = '8px 12px';
                btn.style.background = '#f44336';
                btn.style.color = 'white';
                btn.style.border = 'none';
                btn.style.borderRadius = '4px';
                btn.style.cursor = locked ? 'not-allowed' : 'pointer';
                btn.style.opacity = locked ? '0.5' : '1';
                btn.onclick = () => removePayHistExtendBreakfastDropdown(paymentIndex, bf.id);
                el.appendChild(btn);

                container.appendChild(el);
            });

            const jsonEl = document.getElementById(`payHist_ext_breakfast_json_${paymentIndex}`);
            if (jsonEl) jsonEl.value = collectPayHistExtendBreakfastString(paymentIndex);

            const addBtn = document.getElementById(`payHist_ext_breakfast_btn_${paymentIndex}`);
            if (addBtn) {
                addBtn.disabled = locked;
                addBtn.style.opacity = locked ? '0.5' : '1';
                addBtn.style.cursor = locked ? 'not-allowed' : 'pointer';
            }
        }

        function updatePayHistExtend(idx) {
            refreshPayHistPaymentForBreakdowns();
        }

        function syncModalMetaFromPayments() {
            const booking = window.currentBooking;
            if (!booking) return;

            let count = 0;
            while (document.getElementById(`payHist_duration_${count}`) !== null) count++;
            if (count === 0) return;

            const checkInIdx = getCheckInPaymentIndex(booking, count);
            const checkInMeta = getPayHistMetaForIndex(checkInIdx);

            const durEl = document.getElementById('modalDuration');
            const promoEl = document.getElementById('modalPromo');
            const breakfastEl = document.getElementById('modalBreakfast');
            if (durEl) durEl.value = checkInMeta.duration;
            if (promoEl) promoEl.value = checkInMeta.promo;
            if (breakfastEl) breakfastEl.value = checkInMeta.breakfast;
        }

        function collectPaymentBookingMeta() {
            const booking = window.currentBooking || {};
            let count = 0;
            while (document.getElementById(`payHist_duration_${count}`) !== null) count++;

            if (count === 0) {
                return {
                    duration: document.getElementById('modalDuration')?.value || '',
                    promo: document.getElementById('modalPromo')?.value || '',
                    breakfast: document.getElementById('modalBreakfast')?.value || ''
                };
            }

            const checkInIdx = getCheckInPaymentIndex(booking, count);
            const checkInMeta = getPayHistMetaForIndex(checkInIdx);
            const extIdx = getFirstExtensionPaymentIndex(booking, count);

            const result = {
                duration: checkInMeta.duration,
                promo: checkInMeta.promo,
                breakfast: checkInMeta.breakfast,
                extend_hours: 0,
                extend_minutes: 0,
                extend_price: 0,
                extend_regular_rate: 0,
                extend_bundle_rate: 0,
                extend_bundle_breakfast: ''
            };

            if (extIdx >= 0) {
                const ext = getPayHistExtendForIndex(extIdx);
                result.extend_hours = parseInt(ext.extend_hours, 10) || 0;
                result.extend_minutes = parseInt(ext.extend_minutes, 10) || 0;
                result.extend_regular_rate = parseFloat(ext.extend_regular_rate) || 0;
                result.extend_bundle_rate = parseFloat(ext.extend_bundle_rate) || 0;
                result.extend_bundle_breakfast = ext.extend_bundle_breakfast || '';
                result.extend_price = calcExtendPriceFromFields(ext);
            }

            return result;
        }

        function updatePayHistMeta(idx) {
            syncModalMetaFromPayments();
            refreshPayHistPaymentForBreakdowns();
        }

        function handlePayHistPromoChange(idx) {
            const promoSel = document.getElementById(`payHist_promo_${idx}`);
            const durSel = document.getElementById(`payHist_duration_${idx}`);
            if (promoSel && durSel && promoSel.value) {
                durSel.value = '0';
            }
            updatePayHistMeta(idx);
        }

        function initPayHistAdditionalCharges(booking, paymentIndex, paymentTotals) {
            const addData = getPerPaymentAdditionalsForDisplay(booking, paymentIndex, paymentTotals);
            payHistAdditionalCharges[paymentIndex] = (addData.charges || []).map(c => ({
                id: c.id || `payhist-add-${paymentIndex}-${++payHistItemCounter}`,
                type: c.type || 'food',
                selectedItem: c.selectedItem || c.name || '',
                quantity: parseInt(c.quantity, 10) || 1,
                price: parseFloat(c.price) || 0
            }));
            renderPayHistAdditionalItems(paymentIndex);
        }

        function formatBreakfastItems(breakfastStr, extendBreakfastStr) {
            // Combine regular breakfast and extend bundle breakfast
            const parts = [];
            if (breakfastStr && breakfastStr !== 'None' && breakfastStr !== '-' && breakfastStr !== '0') {
                parts.push(...breakfastStr.split('|').map(s => s.trim()).filter(s => s));
            }
            if (extendBreakfastStr && extendBreakfastStr !== 'None' && extendBreakfastStr !== '-' && extendBreakfastStr !== '0') {
                parts.push(...extendBreakfastStr.split('|').map(s => s.trim()).filter(s => s));
            }
            if (parts.length === 0) return '-';
            const counts = {};
            parts.forEach(part => {
                const match = part.match(/^(\d+)\s+(.+)$/);
                if (match) {
                    const qty = parseInt(match[1], 10);
                    const name = match[2];
                    counts[name] = (counts[name] || 0) + qty;
                } else {
                    counts[part] = (counts[part] || 0) + 1;
                }
            });
            const resultList = [];
            for (const [name, qty] of Object.entries(counts)) {
                resultList.push(`${qty} ${name}`);
            }
            return resultList.join(' | ');
        }

        function formatDurationDisplay(duration, promo, extendHours, extendMinutes) {
            // Parse extension data - handle both numeric and string values
            const extHrs = parseInt(extendHours) || 0;
            const extMins = parseInt(extendMinutes) || 0;

            // Determine base duration
            let baseDuration = 0;

            // First, try to get duration from promo (e.g., "Deluxe 24 Hrs", "Premium 12 Hrs")
            if (promo && promo !== 'None' && promo !== '-') {
                const promoMatch = promo.match(/(\d+)\s*hrs?/i);
                if (promoMatch) {
                    baseDuration = parseInt(promoMatch[1]);
                }
            }

            // If no promo duration found, use the duration field
            if (baseDuration === 0) {
                baseDuration = parseInt(duration, 10) || 0;
            }

            // If there's extension data, calculate total and show extended format
            if (extHrs > 0 || extMins > 0) {
                const totalHours = baseDuration + extHrs;
                const totalMinutes = extMins;

                if (totalMinutes > 0) {
                    return `${totalHours} Hrs ${totalMinutes} Mins (Extended)`;
                } else {
                    return `${totalHours} Hrs (Extended)`;
                }
            }

            // Otherwise show normal duration
            if (baseDuration > 0) {
                return baseDuration;
            } else {
                return duration !== null && duration !== '' ? duration : '-';
            }
        }

        function formatPaymentMethodDisplay(mod) {
            const methodsMap = new Map(); // key(lower) -> label

            const normalizeMethod = (raw) => {
                const s = (raw || '').toString().trim();
                if (!s) return null;
                const lower = s.toLowerCase();
                if (lower === 'cash') return { key: 'cash', label: 'Cash' };
                if (lower === 'g-cash' || lower === 'gcash' || lower === 'g cash') return { key: 'g-cash', label: 'G-Cash' };
                if (lower === 'maya') return { key: 'maya', label: 'Maya' };
                if (lower === 'instapay') return { key: 'instapay', label: 'Instapay' };
                if (lower === 'online banking' || lower === 'online_banking' || lower === 'onlinebanking') return { key: 'online_banking', label: 'Online Banking' };
                if (lower === 'airbnb') return { key: 'airbnb', label: 'Airbnb' };
                return { key: lower, label: s };
            };

            // Check individual payment status columns first (more accurate)
            if (mod.payment_status_cash && mod.payment_status_cash !== '' && mod.payment_status_cash !== 'NULL') {
                const norm = normalizeMethod('Cash');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.payment_status_g_cash && mod.payment_status_g_cash !== '' && mod.payment_status_g_cash !== 'NULL') {
                const norm = normalizeMethod('G-Cash');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.payment_status_maya && mod.payment_status_maya !== '' && mod.payment_status_maya !== 'NULL') {
                const norm = normalizeMethod('Maya');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            // Check Instapay, Online Banking, and Airbnb payment status columns
            if (mod.payment_status_instapay && mod.payment_status_instapay !== '' && mod.payment_status_instapay !== 'NULL') {
                const norm = normalizeMethod('Instapay');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.payment_status_online_banking && mod.payment_status_online_banking !== '' && mod.payment_status_online_banking !== 'NULL') {
                const norm = normalizeMethod('Online Banking');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.payment_status_airbnb && mod.payment_status_airbnb !== '' && mod.payment_status_airbnb !== 'NULL') {
                const norm = normalizeMethod('Airbnb');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            // Check deposit breakdown columns
            if (mod.deposit_cash && parseFloat(mod.deposit_cash) > 0) {
                const norm = normalizeMethod('Cash');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.deposit_g_cash && parseFloat(mod.deposit_g_cash) > 0) {
                const norm = normalizeMethod('G-Cash');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.deposit_maya && parseFloat(mod.deposit_maya) > 0) {
                const norm = normalizeMethod('Maya');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            // Check Instapay, Online Banking, and Airbnb deposit columns
            if (mod.deposit_instapay && parseFloat(mod.deposit_instapay) > 0) {
                const norm = normalizeMethod('Instapay');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.deposit_online_banking && parseFloat(mod.deposit_online_banking) > 0) {
                const norm = normalizeMethod('Online Banking');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.deposit_airbnb && parseFloat(mod.deposit_airbnb) > 0) {
                const norm = normalizeMethod('Airbnb');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            // Check downpayment columns (for reservations)
            if (mod.downpayment_cash && parseFloat(mod.downpayment_cash) > 0) {
                const norm = normalizeMethod('Cash');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.downpayment_gcash && parseFloat(mod.downpayment_gcash) > 0) {
                const norm = normalizeMethod('G-Cash');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.downpayment_maya && parseFloat(mod.downpayment_maya) > 0) {
                const norm = normalizeMethod('Maya');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            // Check Instapay, Online Banking, and Airbnb downpayment columns
            if (mod.downpayment_instapay && parseFloat(mod.downpayment_instapay) > 0) {
                const norm = normalizeMethod('Instapay');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.downpayment_online_banking && parseFloat(mod.downpayment_online_banking) > 0) {
                const norm = normalizeMethod('Online Banking');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            if (mod.downpayment_airbnb && parseFloat(mod.downpayment_airbnb) > 0) {
                const norm = normalizeMethod('Airbnb');
                if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
            }

            // Fallback: Add checkout payment method if exists and no methods found yet
            if (methodsMap.size === 0 && mod.payment_status && mod.payment_status !== '-' && mod.payment_status !== '') {
                // payment_status can be "G-cash (₱100.00), Cash (₱50.00)" etc.
                const parts = mod.payment_status
                    .toString()
                    .split(',')
                    .map(p => p.replace(/\([^)]*\)/g, '').trim())
                    .filter(Boolean);
                parts.forEach(p => {
                    const norm = normalizeMethod(p);
                    if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
                });
            }

            // Fallback: Add deposit payment method if exists and no methods found yet
            if (methodsMap.size === 0 && mod.deposit_details && mod.deposit_details !== '' && mod.deposit_details !== '0') {
                // Parse deposit_details to extract payment methods
                // Format: "1000.00 Cash" or "500.00 G-cash, 500.00 Maya"
                const details = mod.deposit_details.toString();
                // IMPORTANT: detect G-Cash first. "G-cash" contains the substring "cash".
                if (/\bg-?\s*cash\b/i.test(details)) {
                    const norm = normalizeMethod('G-Cash');
                    if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
                }
                // Detect Cash only as a standalone word (avoid matching inside "G-cash")
                if (/\bcash\b/i.test(details) && !/\bg-?\s*cash\b/i.test(details)) {
                    const norm = normalizeMethod('Cash');
                    if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
                }
                if (/maya/i.test(details)) {
                    const norm = normalizeMethod('Maya');
                    if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
                }
                // Check for Instapay, Online Banking, and Airbnb in deposit_details
                if (/instapay/i.test(details)) {
                    const norm = normalizeMethod('Instapay');
                    if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
                }
                if (/online\s*banking/i.test(details)) {
                    const norm = normalizeMethod('Online Banking');
                    if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
                }
                if (/airbnb/i.test(details)) {
                    const norm = normalizeMethod('Airbnb');
                    if (norm && !methodsMap.has(norm.key)) methodsMap.set(norm.key, norm.label);
                }
            }

            // Return combined methods or default
            return methodsMap.size > 0 ? Array.from(methodsMap.values()).join(' & ') : '-';
        }

        function searchByBookingId() {
            const searchInput = document.getElementById('searchInput').value.trim();

            if (!searchInput) {
                alert('Please enter a Booking ID');
                return;
            }

            console.log('Searching for Booking ID:', searchInput);

            // Show loading state

            const tbody = document.getElementById('modificationsTableBody');
            tbody.innerHTML = '<tr><td colspan="15" style="text-align: center; padding: 20px;">Searching...</td></tr>';

            // Fetch specific booking by ID
            const url = `get_modifications.php?booking_id=${encodeURIComponent(searchInput)}`;
            console.log('Fetching URL:', url);

            fetch(url)
                .then(response => response.text())
                .then(text => {
                    console.log('Raw response:', text);

                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        tbody.innerHTML = `<tr><td colspan="15" style="text-align: center; padding: 20px; color: red;">
                            Invalid response from server<br>
                            <small>${text.substring(0, 200)}</small>
                        </td></tr>`;
                        return;
                    }

                    console.log('Parsed data:', data);
                    tbody.innerHTML = '';

                    if (!data.success) {
                        console.error('API Error:', data);
                        tbody.innerHTML = `<tr><td colspan="15" style="text-align: center; padding: 20px; color: red;">
                            Error: ${data.error || 'Unknown error'}<br>
                            <small>File: ${data.file || 'unknown'} Line: ${data.line || 'unknown'}</small>
                        </td></tr>`;
                        return;
                    }

                    if (data.modifications && data.modifications.length > 0) {
                        console.log('Found', data.modifications.length, 'booking(s)');
                        data.modifications.forEach(mod => {
                            const row = document.createElement('tr');

                            // Format room display (Room Type + Room ID)
                            const roomDisplay = mod.room ? `${mod.room} ${mod.room_id}` : mod.room_id;

                            // Format payment status button - RED for Unpaid, GREEN for Paid
                            // Logic matches Booking.html: check both paid_status and additional_paid_status
                            let paidStatus = mod.payment_status || 'Unpaid';
                            if (mod.additional_paid_status === 'Unpaid') {
                                paidStatus = 'Unpaid';
                            }

                            let paidStatusClass = '';
                            let paidStatusStyle = '';
                            if (paidStatus === 'Paid') {
                                paidStatusClass = 'confirmed';
                                paidStatusStyle = 'background: #4CAF50 !important; color: white !important; border: 1px solid #4CAF50 !important;';
                            } else {
                                paidStatusClass = 'pending';
                                paidStatusStyle = 'background: #9c2e2e !important; color: white !important; border: 1px solid #6d2525 !important;';
                            }

                            // Format status button based on actual status
                            let statusClass = '';
                            let statusStyle = '';
                            let statusDisplay = mod.status || 'Available';

                            if (mod.status === 'Confirmed' || mod.status === 'Occupied') {
                                statusClass = 'confirmed';
                                statusStyle = 'background: #4a9eff !important; color: white !important; border: 1px solid #4a9eff !important;';
                                statusDisplay = 'Occupied';
                            } else if (mod.status === 'Available') {
                                statusClass = 'available';
                                statusStyle = 'background: #256d27 !important; color: white !important; border: 1px solid #256d27 !important;';
                            } else if (mod.status === 'Reserved') {
                                statusClass = 'reserved';
                                statusStyle = 'background: #ffa500 !important; color: white !important; border: 1px solid #ffa500 !important;';
                            } else if (mod.status === 'Out of Order') {
                                statusClass = 'out-of-order';
                                statusStyle = 'background: #fcadad !important; color: white !important; border: 1px solid #fcadad !important;';
                            } else {
                                statusClass = 'confirmed';
                                statusStyle = 'background: #256d27 !important; color: white !important; border: 1px solid #256d27 !important;';
                            }

                            row.innerHTML = `
                                <td>${roomDisplay || '-'}</td>
                                <td>${mod.guest_names || '-'}</td>
                                <td>${formatTableDateTime(mod.check_in)}</td>
                                <td>${formatDurationDisplay(mod.duration, mod.promo, mod.extend_hours, mod.extend_minutes)}</td>
                                <td>${formatTableDateTime(mod.check_out)}</td>
                                <td>${mod.referral_code || '-'}</td>
                                <td>${mod.promo || 'None'}</td>
                                <td>${formatBreakfastItems(mod.breakfast, mod.extend_bundle_breakfast)}</td>
                                <td>${formatPaymentMethodDisplay(mod)}</td>
                                <td>${mod.additional || '-'}</td>
                                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${mod.modification_reason || ''}">${mod.modification_reason || '-'}</td>
                                <td style="text-align: center;">${(mod.modification_updated_at && mod.modification_updated_at.trim() !== '') ? 'M' : ''}</td>
                                <td>
                                    <button class="booking-status-btn ${paidStatusClass}" style="${paidStatusStyle}">
                                        ${paidStatus}
                                    </button>
                                </td>
                                <td>
                                    <button class="booking-status-btn ${statusClass}" style="${statusStyle}">
                                        ${statusDisplay}
                                    </button>
                                </td>
                                <td>
                                    <div class="action-buttons-container" style="display: flex; gap: 6px; justify-content: center;">
                                        <button class="action-icon-btn edit-btn" title="Edit" onclick="editModification('${mod.id}')">
                                            <img src="Icon/editiconsystem.svg" alt="Edit" class="action-icon">
                                        </button>
                                        <button class="action-icon-btn extend-duration-btn" title="Extend Duration" onclick="openExtendDurationModal('${mod.id}')">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <g clip-path="url(#clip0_451_3112)">
                                                <path d="M12 5.33325C15.682 5.33325 18.6666 8.31792 18.6666 11.9999C18.6666 15.6819 15.682 18.6665 12 18.6665C8.31798 18.6665 5.33331 15.6819 5.33331 11.9999C5.33331 8.31792 8.31798 5.33325 12 5.33325ZM12 7.99992C11.8232 7.99992 11.6536 8.07016 11.5286 8.19518C11.4036 8.32021 11.3333 8.48978 11.3333 8.66659V11.9999C11.3334 12.1767 11.4036 12.3462 11.5286 12.4712L13.5286 14.4712C13.6544 14.5927 13.8228 14.6598 13.9976 14.6583C14.1724 14.6568 14.3396 14.5867 14.4632 14.4631C14.5868 14.3395 14.6569 14.1723 14.6584 13.9975C14.6599 13.8227 14.5928 13.6543 14.4713 13.5285L12.6666 11.7239V8.66659C12.6666 8.48978 12.5964 8.32021 12.4714 8.19518C12.3464 8.07016 12.1768 7.99992 12 7.99992Z" fill="white"/>
                                                </g>
                                                <defs>
                                                <clipPath id="clip0_451_3112">
                                                <rect width="24" height="24" fill="white"/>
                                                </clipPath>
                                                </defs>
                                            </svg>
                                        </button>
                                        <button class="action-icon-btn confirm-btn" style="display: none;" title="View Details / Checkout" onclick="viewDetails('${mod.id}')">
                                            <img src="Icon/checkiniconsystem.svg" alt="Check" class="action-icon">
                                        </button>
                                        <button class="action-icon-btn delete-btn" title="Delete / Cancel" onclick="deleteModification('${mod.id}', '${mod.source}')">
                                            <img src="Icon/cancaliconsystem.svg" alt="Close" class="action-icon">
                                        </button>
                                    </div>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="15" style="text-align: center; padding: 20px;">No booking found</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    tbody.innerHTML = `<tr><td colspan="15" style="text-align: center; padding: 20px; color: red;">
                        Network error: ${error.message}
                    </td></tr>`;
                });
        }

        // ── Date filter: auto-fetch on change ──────────────────────────────
        function searchByDateRange(fromDate, toDate) {
            const tbody = document.getElementById('modificationsTableBody');
            tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;padding:20px;">Loading...</td></tr>';

            let url = 'get_modifications.php?';
            if (fromDate) url += `date_from=${encodeURIComponent(fromDate)}`;
            if (toDate) url += `&date_to=${encodeURIComponent(toDate)}`;

            fetch(url)
                .then(r => r.text())
                .then(text => {
                    let data;
                    try { data = JSON.parse(text); }
                    catch (e) {
                        tbody.innerHTML = `<tr><td colspan="15" style="text-align:center;padding:20px;color:red;">Invalid server response</td></tr>`;
                        return;
                    }
                    renderModifications(data, tbody);
                })
                .catch(err => {
                    tbody.innerHTML = `<tr><td colspan="15" style="text-align:center;padding:20px;color:red;">Network error: ${err.message}</td></tr>`;
                });
        }

        function clearDateFilter() {
            const dateFromInput = document.getElementById('dateFromInput');
            const dateToInput = document.getElementById('dateToInput');
            const clearBtn = document.getElementById('dateClearBtn');
            dateFromInput.value = '';
            dateToInput.value = '';
            clearBtn.style.display = 'none';
            // Clear sessionStorage
            sessionStorage.removeItem('modificationDateFrom');
            sessionStorage.removeItem('modificationDateTo');
            document.getElementById('modificationsTableBody').innerHTML =
                '<tr><td colspan="15" style="text-align:center;padding:20px;color:#666;">Enter a Booking ID or select a date range to view records.</td></tr>';
        }

        // Set up date range filter event listeners
        document.addEventListener('DOMContentLoaded', function () {
            const dateFromInput = document.getElementById('dateFromInput');
            const dateToInput = document.getElementById('dateToInput');
            const clearBtn = document.getElementById('dateClearBtn');

            // Restore date filter from sessionStorage on page load
            const savedDateFrom = sessionStorage.getItem('modificationDateFrom');
            const savedDateTo = sessionStorage.getItem('modificationDateTo');

            if (savedDateFrom && savedDateTo) {
                dateFromInput.value = savedDateFrom;
                dateToInput.value = savedDateTo;
                clearBtn.style.display = 'block';
                // Auto-search with saved dates
                searchByDateRange(savedDateFrom, savedDateTo);
            }

            dateFromInput.addEventListener('change', function () {
                const fromDate = this.value;
                const toDate = dateToInput.value;

                if (fromDate && toDate) {
                    // Both dates selected - validate and search
                    if (new Date(fromDate) > new Date(toDate)) {
                        alert('From date cannot be after To date');
                        this.value = '';
                        return;
                    }
                    // Save to sessionStorage
                    sessionStorage.setItem('modificationDateFrom', fromDate);
                    sessionStorage.setItem('modificationDateTo', toDate);
                    clearBtn.style.display = 'block';
                    searchByDateRange(fromDate, toDate);
                } else if (fromDate && !toDate) {
                    // Only from date - set to date to same as from date for single day search
                    dateToInput.value = fromDate;
                    // Save to sessionStorage
                    sessionStorage.setItem('modificationDateFrom', fromDate);
                    sessionStorage.setItem('modificationDateTo', fromDate);
                    clearBtn.style.display = 'block';
                    searchByDateRange(fromDate, fromDate);
                }
            });

            dateToInput.addEventListener('change', function () {
                const fromDate = dateFromInput.value;
                const toDate = this.value;

                if (toDate && fromDate) {
                    // Both dates selected - validate and search
                    if (new Date(fromDate) > new Date(toDate)) {
                        alert('To date cannot be before From date');
                        this.value = '';
                        return;
                    }
                    // Save to sessionStorage
                    sessionStorage.setItem('modificationDateFrom', fromDate);
                    sessionStorage.setItem('modificationDateTo', toDate);
                    clearBtn.style.display = 'block';
                    searchByDateRange(fromDate, toDate);
                } else if (toDate && !fromDate) {
                    // Only to date - set from date to same as to date for single day search
                    dateFromInput.value = toDate;
                    // Save to sessionStorage
                    sessionStorage.setItem('modificationDateFrom', toDate);
                    sessionStorage.setItem('modificationDateTo', toDate);
                    clearBtn.style.display = 'block';
                    searchByDateRange(toDate, toDate);
                }
            });
        });

        // Shared render function used by both search paths
        function renderModifications(data, tbody) {
            tbody.innerHTML = '';
            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="15" style="text-align:center;padding:20px;color:red;">Error: ${data.error || 'Unknown error'}</td></tr>`;
                return;
            }
            if (!data.modifications || data.modifications.length === 0) {
                tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;padding:20px;">No records found</td></tr>';
                return;
            }
            data.modifications.forEach(mod => {
                const row = document.createElement('tr');
                const roomDisplay = mod.room ? `${mod.room} ${mod.room_id}` : mod.room_id;

                let paidStatus = mod.payment_status || 'Unpaid';
                if (mod.additional_paid_status === 'Unpaid') paidStatus = 'Unpaid';
                const paidStatusStyle = paidStatus === 'Paid'
                    ? 'background:#4CAF50!important;color:white!important;border:1px solid #4CAF50!important;'
                    : 'background:#9c2e2e!important;color:white!important;border:1px solid #6d2525!important;';
                const pStatusClass = paidStatus === 'Paid' ? 'confirmed' : 'pending';

                let statusDisplay = mod.status || 'Available';
                let statusStyle = '';
                let statusClass = '';
                if (mod.status === 'Confirmed' || mod.status === 'Occupied') {
                    statusClass = 'confirmed'; statusStyle = 'background:#4a9eff!important;color:white!important;border:1px solid #4a9eff!important;'; statusDisplay = 'Occupied';
                } else if (mod.status === 'Completed') {
                    statusClass = 'confirmed'; statusStyle = 'background:#256d27!important;color:white!important;border:1px solid #256d27!important;';
                } else if (mod.status === 'Reserved') {
                    statusClass = 'reserved'; statusStyle = 'background:#ffa500!important;color:white!important;border:1px solid #ffa500!important;';
                } else {
                    statusClass = 'available'; statusStyle = 'background:#256d27!important;color:white!important;border:1px solid #256d27!important;';
                }

                row.innerHTML = `
                    <td>${roomDisplay || '-'}</td>
                    <td>${mod.guest_names || '-'}</td>
                    <td>${formatTableDateTime(mod.check_in)}</td>
                    <td>${formatDurationDisplay(mod.duration, mod.promo, mod.extend_hours, mod.extend_minutes)}</td>
                    <td>${formatTableDateTime(mod.check_out)}</td>
                    <td>${mod.referral_code || '-'}</td>
                    <td>${mod.promo || 'None'}</td>
                    <td>${formatBreakfastItems(mod.breakfast, mod.extend_bundle_breakfast)}</td>
                    <td>${formatPaymentMethodDisplay(mod)}</td>
                    <td>${mod.additional || '-'}</td>
                    <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${mod.modification_reason || ''}">${mod.modification_reason || '-'}</td>
                    <td style="text-align: center;">${(mod.modification_updated_at && mod.modification_updated_at.trim() !== '') ? 'M' : ''}</td>
                    <td><button class="booking-status-btn ${pStatusClass}" style="${paidStatusStyle}">${paidStatus}</button></td>
                    <td><button class="booking-status-btn ${statusClass}" style="${statusStyle}">${statusDisplay}</button></td>
                    <td>
                        <div class="action-buttons-container" style="display:flex;gap:6px;justify-content:center;">
                            <button class="action-icon-btn edit-btn" title="Edit" onclick="editModification('${mod.id}')">
                                <img src="Icon/editiconsystem.svg" alt="Edit" class="action-icon">
                            </button>
                            <button class="action-icon-btn extend-duration-btn" title="Extend Duration" onclick="openExtendDurationModal('${mod.id}')">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_mod)"><path d="M12 5.33325C15.682 5.33325 18.6666 8.31792 18.6666 11.9999C18.6666 15.6819 15.682 18.6665 12 18.6665C8.31798 18.6665 5.33331 15.6819 5.33331 11.9999C5.33331 8.31792 8.31798 5.33325 12 5.33325ZM12 7.99992C11.8232 7.99992 11.6536 8.07016 11.5286 8.19518C11.4036 8.32021 11.3333 8.48978 11.3333 8.66659V11.9999C11.3334 12.1767 11.4036 12.3462 11.5286 12.4712L13.5286 14.4712C13.6544 14.5927 13.8228 14.6598 13.9976 14.6583C14.1724 14.6568 14.3396 14.5867 14.4632 14.4631C14.5868 14.3395 14.6569 14.1723 14.6584 13.9975C14.6599 13.8227 14.5928 13.6543 14.4713 13.5285L12.6666 11.7239V8.66659C12.6666 8.48978 12.5964 8.32021 12.4714 8.19518C12.3464 8.07016 12.1768 7.99992 12 7.99992Z" fill="white"/></g><defs><clipPath id="clip0_mod"><rect width="24" height="24" fill="white"/></clipPath></defs></svg>
                            </button>
                            <button class="action-icon-btn confirm-btn" style="display: none;" title="View Details / Checkout" onclick="viewDetails('${mod.id}')">
                                <img src="Icon/checkiniconsystem.svg" alt="Check" class="action-icon">
                            </button>
                            <button class="action-icon-btn delete-btn" title="Delete / Cancel" onclick="deleteModification('${mod.id}', '${mod.source}')">
                                <img src="Icon/cancaliconsystem.svg" alt="Close" class="action-icon">
                            </button>
                        </div>
                    </td>`;
                tbody.appendChild(row);
            });
        }

        // Allow Enter key to trigger search
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') { searchByBookingId(); }
                });
            }
            // Date filter: auto-trigger on change
            const dateInput = document.getElementById('dateFilterInput');
            const clearBtn = document.getElementById('dateClearBtn');
            if (dateInput) {
                dateInput.addEventListener('change', function () {
                    if (this.value) {
                        clearBtn.style.display = 'inline';
                        searchByDate(this.value);
                    } else {
                        clearDateFilter();
                    }
                });
            }
        });
    </script>
</head>

<body>
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

    <div class="split-container">
        <div class="left-panel" id="leftPanel">
            <button class="sidebar-collapse-btn" id="collapseBtn" onclick="toggleSidebarMinimize()">
                <img id="collapseIcon" src="Icon/left-arrow_minimize.svg" alt="Minimize"
                    style="width: 24px; height: 18px;">
                <span class="collapse-tooltip" id="collapseTooltip">Minimize</span>
            </button>
            <img src="Icon/MoonClave_Logo.svg" alt="MoonClave Hotel Logo" class="logo-img">
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
                    <li class="sidebar-menu-item active" data-page="Modification.php"
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
                    <li class="sidebar-menu-item" onclick="window.location.href='logout.php'">
                        <img src="Icon/logouticon_system.svg" class="sidebar-icon" alt="Logout">
                        <span>Logout</span>
                    </li>
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
                            $role = $_SESSION['access_level'] ?? 'user';
                            echo htmlspecialchars(ucwords(str_replace('_', ' ', $role)), ENT_QUOTES, 'UTF-8');
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-container">
                <h2 class="header-title">Modification</h2>

                <div class="calendar-addroom-container" style="width: 100%; justify-content: flex-end;">
                    <div class="booking-toolbar-right">
                        <!-- Date Range Filter -->
                        <div class="date-filter-container">
                            <span class="date-filter-label">From:</span>
                            <input type="date" id="dateFromInput" class="date-filter-input" title="Filter from date">
                            <span class="date-filter-label">To:</span>
                            <input type="date" id="dateToInput" class="date-filter-input" title="Filter to date">
                            <button class="date-filter-clear" id="dateClearBtn" title="Clear date range"
                                onclick="clearDateFilter()">×</button>
                        </div>
                        <!-- Booking ID Search -->
                        <div class="search-bar-container">
                            <input type="text" id="searchInput" class="search-bar-input" placeholder="Enter Booking ID">
                            <button class="search-bar-btn" onclick="searchByBookingId()">
                                <img src="Icon/searchicon_system.svg" alt="Search" class="search-bar-icon">
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-wrapper zoomed" style="overflow-x: auto;">
                    <table class="booking-table">
                        <thead>
                            <tr>
                                <th>Room ID</th>
                                <th>Guest Names</th>
                                <th>Check-In</th>
                                <th>Duration</th>
                                <th>Check-Out</th>
                                <th>Referral Code</th>
                                <th>Promo</th>
                                <th>Breakfast</th>
                                <th>Payment Method</th>
                                <th>Additional</th>
                                <th>Reason</th>
                                <th>Modified</th>
                                <th>Payment Status</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="modificationsTableBody">
                            <tr>
                                <td colspan="15" style="text-align: center; padding: 20px; color: #666;">
                                    Enter a Booking ID and click Search to view details
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Load modifications data
        function loadModifications() {
            fetch('get_modifications.php')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('modificationsTableBody');
                    tbody.innerHTML = '';

                    if (data.success && data.modifications && data.modifications.length > 0) {
                        data.modifications.forEach(mod => {
                            const row = document.createElement('tr');
                            let paidStatus = mod.payment_status || 'Unpaid';
                            if (mod.additional_paid_status === 'Unpaid') {
                                paidStatus = 'Unpaid';
                            }

                            const pStatusClass = paidStatus === 'Paid' ? 'confirmed' : 'pending';
                            const pStatusStyle = paidStatus === 'Paid'
                                ? 'background: #4CAF50 !important; color: white !important; border: 1px solid #4CAF50 !important;'
                                : 'background: #9c2e2e !important; color: white !important; border: 1px solid #6d2525 !important;';

                            row.innerHTML = `
                                <td>${mod.room ? `${mod.room} ${mod.room_id}` : mod.room_id}</td>
                                <td>${mod.guest_names || '-'}</td>
                                <td>${formatTableDateTime(mod.check_in)}</td>
                                <td>${formatDurationDisplay(mod.duration, mod.promo, mod.extend_hours, mod.extend_minutes)}</td>
                                <td>${formatTableDateTime(mod.check_out)}</td>
                                <td>${mod.referral_code || '-'}</td>
                                <td>${mod.promo || '-'}</td>
                                <td>${formatBreakfastItems(mod.breakfast, mod.extend_bundle_breakfast)}</td>
                                <td>${formatPaymentMethodDisplay(mod)}</td>
                                <td>${mod.additional || '-'}</td>
                                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${mod.modification_reason || ''}">${mod.modification_reason || '-'}</td>
                                <td style="text-align: center;">${(mod.modification_updated_at && mod.modification_updated_at.trim() !== '') ? 'M' : ''}</td>
                                <td>
                                    <button class="booking-status-btn ${pStatusClass}" style="${pStatusStyle}">
                                        ${paidStatus}
                                    </button>
                                </td>
                                <td>
                                    <button class="booking-status-btn ${getStatusClass(mod.status)}">
                                        ${mod.status || 'Pending'}
                                    </button>
                                </td>
                                <td>
                                    <div class="action-buttons-container" style="display: flex; gap: 6px; justify-content: center;">
                                        <button class="action-icon-btn edit-btn" title="Edit" onclick="editModification(${mod.id})">
                                            <img src="Icon/editiconsystem.svg" alt="Edit" class="action-icon">
                                        </button>
                                        <button class="action-icon-btn extend-duration-btn" title="Extend Duration" onclick="openExtendDurationModal(${mod.id})">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <g clip-path="url(#clip0_451_3112)">
                                                <path d="M12 5.33325C15.682 5.33325 18.6666 8.31792 18.6666 11.9999C18.6666 15.6819 15.682 18.6665 12 18.6665C8.31798 18.6665 5.33331 15.6819 5.33331 11.9999C5.33331 8.31792 8.31798 5.33325 12 5.33325ZM12 7.99992C11.8232 7.99992 11.6536 8.07016 11.5286 8.19518C11.4036 8.32021 11.3333 8.48978 11.3333 8.66659V11.9999C11.3334 12.1767 11.4036 12.3462 11.5286 12.4712L13.5286 14.4712C13.6544 14.5927 13.8228 14.6598 13.9976 14.6583C14.1724 14.6568 14.3396 14.5867 14.4632 14.4631C14.5868 14.3395 14.6569 14.1723 14.6584 13.9975C14.6599 13.8227 14.5928 13.6543 14.4713 13.5285L12.6666 11.7239V8.66659C12.6666 8.48978 12.5964 8.32021 12.4714 8.19518C12.3464 8.07016 12.1768 7.99992 12 7.99992Z" fill="white"/>
                                                </g>
                                                <defs>
                                                <clipPath id="clip0_451_3112">
                                                <rect width="24" height="24" fill="white"/>
                                                </clipPath>
                                                </defs>
                                            </svg>
                                        </button>
                                        <button class="action-icon-btn confirm-btn" style="display: none;" title="View Details / Checkout" onclick="viewDetails(${mod.id})">
                                            <img src="Icon/checkiniconsystem.svg" alt="Check" class="action-icon">
                                        </button>
                                        <button class="action-icon-btn delete-btn" title="Delete / Cancel" onclick="deleteModification('${mod.id}', '${mod.source}')">
                                            <img src="Icon/cancaliconsystem.svg" alt="Close" class="action-icon">
                                        </button>
                                    </div>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="15" style="text-align: center; padding: 20px;">No modifications found</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error loading modifications:', error);
                    document.getElementById('modificationsTableBody').innerHTML =
                        '<tr><td colspan="15" style="text-align: center; padding: 20px;">Error loading data</td></tr>';
                });
        }

        function viewDetails(bookingId) {
            console.log('View details:', bookingId);
            alert('View details for Booking ID: ' + bookingId);
        }

        function printReceipt(bookingId) {
            console.log('Print receipt:', bookingId);
            alert('Print receipt for Booking ID: ' + bookingId);
        }

        function getStatusClass(status) {
            const statusMap = {
                'Available': 'available',
                'Confirmed': 'confirmed',
                'Occupied': 'occupied',
                'Reserved': 'reserved',
                'Pending': 'pending',
                'Out of Order': 'out-of-order'
            };
            return statusMap[status] || 'pending';
        }

        function editModification(id) {
            console.log('Edit modification:', id);

            // Fetch full booking details
            fetch(`get_modifications.php?booking_id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.modifications && data.modifications.length > 0) {
                        const booking = data.modifications[0];
                        showEditModal(booking);
                    } else {
                        alert('Failed to load booking details');
                    }
                })
                .catch(error => {
                    console.error('Error loading booking details:', error);
                    alert('Error loading booking details');
                });
        }

        function showEditModal(booking) {
            const modal = document.getElementById('editModal');
            window.currentBooking = booking;

            // Store booking ID for update
            document.getElementById('editBookingId').value = booking.id || '';

            // Populate modal with booking data - using input fields
            document.getElementById('modalBookingId').textContent = booking.display_booking_id && booking.display_booking_id !== '-' ? booking.display_booking_id : booking.id || '-';
            document.getElementById('modalRoom').value = booking.room ? `${booking.room} ${booking.room_id}` : booking.room_id || '';

            // Modification Reason - store original value for validation
            const originalModificationReason = booking.modification_reason || '';
            document.getElementById('modalModificationReason').value = originalModificationReason;
            document.getElementById('modalModificationReason').setAttribute('data-original-value', originalModificationReason);

            // Show/hide hint and required indicator based on whether there's an existing reason
            const modificationReasonRequired = document.getElementById('modificationReasonRequired');
            const modificationReasonHint = document.getElementById('modificationReasonHint');
            if (originalModificationReason) {
                // Has existing reason - not required, show hint
                if (modificationReasonRequired) modificationReasonRequired.style.display = 'none';
                if (modificationReasonHint) modificationReasonHint.style.display = 'block';
            } else {
                // No existing reason - required
                if (modificationReasonRequired) modificationReasonRequired.style.display = 'inline';
                if (modificationReasonHint) modificationReasonHint.style.display = 'none';
            }

            // Booking Type
            const bookingType = booking.booking_type || 'Walk-in';
            if (bookingType === 'Walk-in') {
                document.getElementById('modalBookingTypeWalkin').checked = true;
            } else {
                document.getElementById('modalBookingTypeReservation').checked = true;
            }

            // Guest Type
            document.getElementById('modalGuestType').value = booking.guest_type || 'Solo';

            // Company fields
            document.getElementById('modalReasonForStay').value = booking.reason_for_stay || '';
            document.getElementById('modalContactPersonName').value = booking.contact_person_name || '';
            document.getElementById('modalContactNo').value = booking.contact_no || '';
            document.getElementById('modalAddress').value = booking.address || '';
            document.getElementById('modalTinNo').value = booking.tin_number || '';

            // Vehicle Details
            console.log('=== VEHICLE DETAILS DEBUG ===');
            console.log('vehicle_type:', booking.vehicle_type);
            console.log('plate_number:', booking.plate_number);
            console.log('vehicle_description:', booking.vehicle_description);
            console.log('=== END VEHICLE DETAILS DEBUG ===');

            document.getElementById('modalVehicleType').value = booking.vehicle_type || '';
            document.getElementById('modalPlateNumber').value = booking.plate_number || '';
            document.getElementById('modalVehicleDescription').value = booking.vehicle_description || '';

            // Transfer Details
            console.log('=== TRANSFER DETAILS DEBUG ===');
            console.log('transfer_room_from:', booking.transfer_room_from);
            console.log('transfer_refund_amount:', booking.transfer_refund_amount);
            console.log('=== END TRANSFER DETAILS DEBUG ===');

            document.getElementById('modalTransferRoomFrom').value = booking.transfer_room_from || '';
            document.getElementById('modalTransferRefundAmount').value = booking.transfer_refund_amount || '0';

            // Show/hide company fields based on guest type
            handleGuestTypeChange();

            document.getElementById('modalGuestNames').value = booking.guest_names || '';
            document.getElementById('modalRequest').value = booking.request || '';
            document.getElementById('modalCheckIn').value = booking.check_in ? booking.check_in.replace(' ', 'T').substring(0, 16) : '';
            document.getElementById('modalCheckOut').value = booking.check_out ? booking.check_out.replace(' ', 'T').substring(0, 16) : '';

            // Store current duration and promo to select after loading options
            const currentDuration = booking.duration || '';
            const currentPromo = booking.promo || '';
            const roomType = booking.room ? `${booking.room} ${booking.room_id}` : booking.room_id || '';

            document.getElementById('modalReferralCode').value = booking.referral_code || '';

            // Load promo options filtered by room type
            loadPromoOptions(currentPromo, roomType);

            // Populate breakfast dropdowns dynamically
            modalBreakfasts = [];
            const container = document.getElementById('modalBreakfastContainer');
            if (container) container.innerHTML = '';
            document.getElementById('modalBreakfast').value = booking.breakfast || '';

            if (booking.breakfast && booking.breakfast !== 'None') {
                // E.g., "1 Tocino (Promo) | 1 Tocino (Promo)"
                const parts = booking.breakfast.split('|').map(s => s.trim()).filter(s => s);
                parts.forEach(part => addModalBreakfastDropdown(part));
            } else {
                addModalBreakfastDropdown(''); // At least one empty
            }

            document.getElementById('modalAdditional').value = booking.additional || '';
            document.getElementById('modalAdditionalGuest').value = booking.additional_guest || '';
            document.getElementById('modalAdditionalPet').value = booking.additional_pet || '';

            // Parse additional food/items
            let parsedFood = [];
            let parsedItems = [];

            function parseLegacyFormat(str, type) {
                if (!str) return [];
                const items = [];
                const lines = str.split(/\r?\n/);
                for (const line of lines) {
                    const match = line.match(/^(\d+)\s+(.*?)\s*[=-]\s*[₱P]?([\d,]+\.?\d*)/);
                    if (match) {
                        items.push({
                            id: `legacy-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
                            type: type,
                            quantity: parseInt(match[1], 10),
                            selectedItem: match[2].trim(),
                            price: parseFloat(match[3].replace(/,/g, ''))
                        });
                    }
                }
                return items;
            }

            try {
                if (booking.additional_food) {
                    if (booking.additional_food.trim().startsWith('[')) {
                        parsedFood = JSON.parse(booking.additional_food);
                    } else {
                        parsedFood = parseLegacyFormat(booking.additional_food, 'food');
                    }
                }
            } catch (e) { }

            try {
                if (booking.additional_items) {
                    if (booking.additional_items.trim().startsWith('[')) {
                        parsedItems = JSON.parse(booking.additional_items);
                    } else {
                        parsedItems = parseLegacyFormat(booking.additional_items, 'item');
                    }
                }
            } catch (e) { }

            modalAdditionalCharges = [...(Array.isArray(parsedFood) ? parsedFood : []), ...(Array.isArray(parsedItems) ? parsedItems : [])];
            renderModalAdditionalItems();

            // Payment amount (read-only display) - calculate from individual payment method amounts
            const paymentBreakdown = [];
            let totalPayment = 0;

            // Check deposit columns (for check-in payments)
            if (booking.deposit_cash && parseFloat(booking.deposit_cash) > 0) {
                const cashAmt = parseFloat(booking.deposit_cash);
                paymentBreakdown.push('Cash: ₱' + cashAmt.toFixed(2));
                totalPayment += cashAmt;
            }
            if (booking.deposit_g_cash && parseFloat(booking.deposit_g_cash) > 0) {
                const gcashAmt = parseFloat(booking.deposit_g_cash);
                paymentBreakdown.push('G-cash: ₱' + gcashAmt.toFixed(2));
                totalPayment += gcashAmt;
            }
            if (booking.deposit_maya && parseFloat(booking.deposit_maya) > 0) {
                const mayaAmt = parseFloat(booking.deposit_maya);
                paymentBreakdown.push('Maya: ₱' + mayaAmt.toFixed(2));
                totalPayment += mayaAmt;
            }
            if (booking.deposit_instapay && parseFloat(booking.deposit_instapay) > 0) {
                const instapayAmt = parseFloat(booking.deposit_instapay);
                paymentBreakdown.push('Instapay: ₱' + instapayAmt.toFixed(2));
                totalPayment += instapayAmt;
            }
            if (booking.deposit_online_banking && parseFloat(booking.deposit_online_banking) > 0) {
                const onlineBankingAmt = parseFloat(booking.deposit_online_banking);
                paymentBreakdown.push('Online Banking: ₱' + onlineBankingAmt.toFixed(2));
                totalPayment += onlineBankingAmt;
            }
            if (booking.deposit_airbnb && parseFloat(booking.deposit_airbnb) > 0) {
                const airbnbAmt = parseFloat(booking.deposit_airbnb);
                paymentBreakdown.push('Airbnb: ₱' + airbnbAmt.toFixed(2));
                totalPayment += airbnbAmt;
            }

            // If no deposit amounts found, use the cash_amount, gcash_amount, etc. from the response
            if (totalPayment === 0) {
                if (booking.cash_amount && parseFloat(booking.cash_amount) > 0) {
                    const cashAmt = parseFloat(booking.cash_amount);
                    paymentBreakdown.push('Cash: ₱' + cashAmt.toFixed(2));
                    totalPayment += cashAmt;
                }
                if (booking.gcash_amount && parseFloat(booking.gcash_amount) > 0) {
                    const gcashAmt = parseFloat(booking.gcash_amount);
                    paymentBreakdown.push('G-cash: ₱' + gcashAmt.toFixed(2));
                    totalPayment += gcashAmt;
                }
                if (booking.maya_amount && parseFloat(booking.maya_amount) > 0) {
                    const mayaAmt = parseFloat(booking.maya_amount);
                    paymentBreakdown.push('Maya: ₱' + mayaAmt.toFixed(2));
                    totalPayment += mayaAmt;
                }
                if (booking.instapay_amount && parseFloat(booking.instapay_amount) > 0) {
                    const instapayAmt = parseFloat(booking.instapay_amount);
                    paymentBreakdown.push('Instapay: ₱' + instapayAmt.toFixed(2));
                    totalPayment += instapayAmt;
                }
                if (booking.online_banking_amount && parseFloat(booking.online_banking_amount) > 0) {
                    const onlineBankingAmt = parseFloat(booking.online_banking_amount);
                    paymentBreakdown.push('Online Banking: ₱' + onlineBankingAmt.toFixed(2));
                    totalPayment += onlineBankingAmt;
                }
                if (booking.airbnb_amount && parseFloat(booking.airbnb_amount) > 0) {
                    const airbnbAmt = parseFloat(booking.airbnb_amount);
                    paymentBreakdown.push('Airbnb: ₱' + airbnbAmt.toFixed(2));
                    totalPayment += airbnbAmt;
                }
            }

            const paymentDisplay = totalPayment > 0 ? paymentBreakdown.join(' + ') : '₱0.00';
            const modalPaymentAmountEl = document.getElementById('modalPaymentAmount');
            if (modalPaymentAmountEl) modalPaymentAmountEl.textContent = paymentDisplay;

            // Reservation amount (read-only display) - calculate from downpayment fields
            const reservationBreakdown = [];
            let totalReservation = 0;

            if (booking.downpayment_cash && parseFloat(booking.downpayment_cash) > 0) {
                const cashAmt = parseFloat(booking.downpayment_cash);
                reservationBreakdown.push('Cash: ₱' + cashAmt.toFixed(2));
                totalReservation += cashAmt;
            }
            if (booking.downpayment_gcash && parseFloat(booking.downpayment_gcash) > 0) {
                const gcashAmt = parseFloat(booking.downpayment_gcash);
                reservationBreakdown.push('G-cash: ₱' + gcashAmt.toFixed(2));
                totalReservation += gcashAmt;
            }
            if (booking.downpayment_maya && parseFloat(booking.downpayment_maya) > 0) {
                const mayaAmt = parseFloat(booking.downpayment_maya);
                reservationBreakdown.push('Maya: ₱' + mayaAmt.toFixed(2));
                totalReservation += mayaAmt;
            }
            if (booking.downpayment_instapay && parseFloat(booking.downpayment_instapay) > 0) {
                const instapayAmt = parseFloat(booking.downpayment_instapay);
                reservationBreakdown.push('Instapay: ₱' + instapayAmt.toFixed(2));
                totalReservation += instapayAmt;
            }
            if (booking.downpayment_online_banking && parseFloat(booking.downpayment_online_banking) > 0) {
                const onlineBankingAmt = parseFloat(booking.downpayment_online_banking);
                reservationBreakdown.push('Online Banking: ₱' + onlineBankingAmt.toFixed(2));
                totalReservation += onlineBankingAmt;
            }
            if (booking.downpayment_airbnb && parseFloat(booking.downpayment_airbnb) > 0) {
                const airbnbAmt = parseFloat(booking.downpayment_airbnb);
                reservationBreakdown.push('Airbnb: ₱' + airbnbAmt.toFixed(2));
                totalReservation += airbnbAmt;
            }

            const reservationDisplay = totalReservation > 0 ? reservationBreakdown.join(' + ') : '₱0.00';
            document.getElementById('modalReservationAmount').textContent = reservationDisplay;

            // Determine Reservation Payment Method based on which downpayment fields have values
            let reservationPaymentMethod = 'Cash'; // default
            const hasCash = booking.downpayment_cash && parseFloat(booking.downpayment_cash) > 0;
            const hasGcash = booking.downpayment_gcash && parseFloat(booking.downpayment_gcash) > 0;
            const hasMaya = booking.downpayment_maya && parseFloat(booking.downpayment_maya) > 0;
            const hasInstapay = booking.downpayment_instapay && parseFloat(booking.downpayment_instapay) > 0;
            const hasOnlineBanking = booking.downpayment_online_banking && parseFloat(booking.downpayment_online_banking) > 0;
            const hasAirbnb = booking.downpayment_airbnb && parseFloat(booking.downpayment_airbnb) > 0;

            if (hasCash && hasGcash && hasMaya) {
                reservationPaymentMethod = 'Cash, G-cash & Maya';
            } else if (hasCash && hasGcash) {
                reservationPaymentMethod = 'Cash & G-cash';
            } else if (hasCash && hasMaya) {
                reservationPaymentMethod = 'Cash & Maya';
            } else if (hasGcash && hasMaya) {
                reservationPaymentMethod = 'G-cash & Maya';
            } else if (hasGcash) {
                reservationPaymentMethod = 'G-cash';
            } else if (hasMaya) {
                reservationPaymentMethod = 'Maya';
            } else if (hasCash) {
                reservationPaymentMethod = 'Cash';
            } else if (hasInstapay) {
                reservationPaymentMethod = 'Instapay';
            } else if (hasOnlineBanking) {
                reservationPaymentMethod = 'Online Banking';
            } else if (hasAirbnb) {
                reservationPaymentMethod = 'Airbnb';
            }

            // Reservation Payment Method UI was removed; keep this safe for backward compatibility
            const reservationMethodInput = document.getElementById('modalReservationPaymentMethod');
            if (reservationMethodInput) {
                reservationMethodInput.value = reservationPaymentMethod;
            }

            // Payment status (read-only)
            let paidStatus = booking.payment_status || 'Unpaid';
            if (booking.additional_paid_status === 'Unpaid') {
                paidStatus = 'Unpaid';
            }
            document.getElementById('modalPaymentStatus').textContent = paidStatus;
            document.getElementById('modalPaymentStatus').className = paidStatus === 'Paid' ? 'status-badge paid' : 'status-badge unpaid';

            // Booking status (read-only)
            document.getElementById('modalStatus').textContent = booking.status || 'Pending';
            document.getElementById('modalStatus').className = `status-badge ${getStatusClass(booking.status)}`;

            // Encoder information (read-only)
            document.getElementById('modalEncoder').value = booking.encoder || '-';
            document.getElementById('modalEncoderCheckout').value = booking.encoder_checkout || '-';

            // Load duration options based on room type
            loadDurationOptions(booking.room, booking.room_id, currentDuration);

            // Check-in payment fields are now managed individually through the Payment Method history list.

            // Populate Reservation Payment amounts and checkboxes
            const resCashAmt = parseFloat(booking.downpayment_cash || 0);
            const resGcashAmt = parseFloat(booking.downpayment_gcash || 0);
            const resMayaAmt = parseFloat(booking.downpayment_maya || 0);
            const resInstapayAmt = parseFloat(booking.downpayment_instapay || 0);
            const resOnlineBankingAmt = parseFloat(booking.downpayment_online_banking || 0);
            const resAirbnbAmt = parseFloat(booking.downpayment_airbnb || 0);

            document.getElementById('modalReservationCash').value = resCashAmt > 0 ? resCashAmt : '';
            document.getElementById('modalReservationGcash').value = resGcashAmt > 0 ? resGcashAmt : '';
            document.getElementById('modalReservationMaya').value = resMayaAmt > 0 ? resMayaAmt : '';
            document.getElementById('modalReservationInstapay').value = resInstapayAmt > 0 ? resInstapayAmt : '';
            document.getElementById('modalReservationOnlineBanking').value = resOnlineBankingAmt > 0 ? resOnlineBankingAmt : '';
            document.getElementById('modalReservationAirbnb').value = resAirbnbAmt > 0 ? resAirbnbAmt : '';

            // Populate Reservation Reference Numbers
            document.getElementById('modalReservationGcashReference').value = booking.downpayment_gcash_ref || '';
            document.getElementById('modalReservationMayaReference').value = booking.downpayment_maya_ref || '';
            document.getElementById('modalReservationInstapayReference').value = booking.downpayment_instapay_ref || '';
            document.getElementById('modalReservationOnlineBankingReference').value = booking.downpayment_online_banking_ref || '';
            document.getElementById('modalReservationAirbnbReference').value = booking.downpayment_airbnb_ref || '';

            // Automatically check reservation checkboxes based on downpayment amounts (if present in DOM)
            const resCashChk = document.getElementById('checkReservationCash');
            const resGcashChk = document.getElementById('checkReservationGcash');
            const resMayaChk = document.getElementById('checkReservationMaya');
            const resInstapayChk = document.getElementById('checkReservationInstapay');
            const resObChk = document.getElementById('checkReservationOnlineBanking');
            const resAirbnbChk = document.getElementById('checkReservationAirbnb');

            if (resCashChk) resCashChk.checked = resCashAmt > 0;
            if (resGcashChk) resGcashChk.checked = resGcashAmt > 0;
            if (resMayaChk) resMayaChk.checked = resMayaAmt > 0;
            if (resInstapayChk) resInstapayChk.checked = resInstapayAmt > 0;
            if (resObChk) resObChk.checked = resOnlineBankingAmt > 0;
            if (resAirbnbChk) resAirbnbChk.checked = resAirbnbAmt > 0;

            // Trigger reservation checkbox change to show/hide fields and update display (only if section exists)
            if (resCashChk || resGcashChk || resMayaChk || resInstapayChk || resObChk || resAirbnbChk) {
                handleReservationCheckboxChange();
            }

            // Populate Discount fields
            if (document.getElementById('modalDiscountCount')) {
                document.getElementById('modalDiscountCount').value = booking.sc_pwd_count || '';
                document.getElementById('modalDiscountAmount').value = booking.discount_amount || '';
                document.getElementById('modalDiscountId').value = booking.id_number || '';
            }

            // Populate Cancellation fields
            if (document.getElementById('modalCancellationReason')) {
                document.getElementById('modalCancellationReason').value = booking.cancellation_reason || '';
            }
            if (document.getElementById('modalCancellationRefund')) {
                document.getElementById('modalCancellationRefund').value = booking.refund_amount || '';
            }

            // Populate Payment History section
            renderPaymentHistory(booking);

            // Show modal
            modal.style.display = 'block';
        }

        function parseChargeLinesFromBooking(str, type) {
            if (!str || !str.trim()) return [];
            const items = [];
            if (str.trim().startsWith('[')) {
                try {
                    const parsed = JSON.parse(str);
                    if (Array.isArray(parsed)) {
                        parsed.forEach(entry => {
                            const qty = parseInt(entry.quantity, 10) || 0;
                            const name = (entry.selectedItem || entry.name || '').trim();
                            const price = parseFloat(entry.price) || 0;
                            if (qty > 0 && name) {
                                items.push({ label: `${qty} ${name}`, amount: price, category: type });
                            }
                        });
                    }
                } catch (e) { /* fall through to legacy */ }
            }
            if (items.length === 0) {
                str.split(/\r?\n/).forEach(line => {
                    const match = line.trim().match(/^(\d+)\s+(.+?)\s*[=-]\s*[₱P]?([\d,]+\.?\d*)/);
                    if (match) {
                        items.push({
                            label: `${match[1]} ${match[2].trim()}`,
                            amount: parseFloat(match[3].replace(/,/g, '')),
                            category: type
                        });
                    }
                });
            }
            return items;
        }

        function getCombinedBreakfastString(booking) {
            if (!booking) return '';
            let breakfastStr = '';
            if (booking.breakfast && booking.breakfast !== 'None' && booking.breakfast !== 'NULL') {
                breakfastStr = String(booking.breakfast).trim();
            }

            const extendData = booking.extend_bundle_breakfast;
            if (extendData && extendData !== '0' && extendData !== 'None' && extendData !== '-') {
                const raw = String(extendData).trim();
                if (raw.startsWith('[')) {
                    try {
                        const parsed = JSON.parse(raw);
                        if (Array.isArray(parsed) && parsed.length > 0) {
                            const extraParts = parsed.map(entry => {
                                if (!entry) return '';
                                const rawItem = (entry.item || '').toString();
                                const quantity = parseInt(entry.quantity, 10) || 1;
                                if (!rawItem) return '';
                                const name = rawItem.split(' - ')[0].trim() || rawItem.trim();
                                return `${quantity} ${name} (Promo)`;
                            }).filter(Boolean);
                            if (extraParts.length > 0) {
                                const extraStr = extraParts.join(' | ');
                                breakfastStr = breakfastStr ? `${breakfastStr} | ${extraStr}` : extraStr;
                            }
                        }
                    } catch (e) { /* ignore */ }
                } else {
                    const extra = raw.split('|').map(s => s.trim()).filter(Boolean).join(' | ');
                    if (extra) {
                        breakfastStr = breakfastStr ? `${breakfastStr} | ${extra}` : extra;
                    }
                }
            }

            return breakfastStr;
        }

        /** Check-in breakfast only — never includes extend_bundle_breakfast. */
        function getCheckInBreakfastString(booking) {
            if (!booking || !booking.breakfast || booking.breakfast === 'None' || booking.breakfast === 'NULL') {
                return '';
            }
            return String(booking.breakfast).trim();
        }

        function getExtendBreakfastString(booking) {
            const ebb = booking?.extend_bundle_breakfast;
            if (!ebb || ebb === '0' || ebb === 'None' || ebb === '-') return '';
            const raw = String(ebb).trim();
            if (raw.startsWith('[')) {
                try {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        return parsed.map(entry => {
                            if (!entry) return '';
                            const rawItem = (entry.item || '').toString();
                            const quantity = parseInt(entry.quantity, 10) || 1;
                            if (!rawItem) return '';
                            const name = rawItem.split(' - ')[0].trim() || rawItem.trim();
                            return `${quantity} ${name} (Promo)`;
                        }).filter(Boolean).join(' | ');
                    }
                } catch (e) { /* ignore */ }
            }
            return raw.split('|').map(s => s.trim()).filter(Boolean).join(' | ');
        }

        function appendExtendBreakfastToLines(lines, booking) {
            const extBreakfast = getExtendBreakfastString(booking);
            if (!extBreakfast) return lines;
            parseBreakfastChargeLines(extBreakfast, true).forEach(line => lines.push(line));
            return lines;
        }

        function parseBreakfastChargeLines(breakfast, hasPromo) {
            if (!breakfast || breakfast.trim() === '' || breakfast === 'None') return [];
            const lines = [];
            const aggregated = {};
            breakfast.split('|').map(s => s.trim()).filter(Boolean).forEach(item => {
                let quantity = 0;
                let name = '';
                let price = 0;
                let isPromo = false;
                const promoMatch = item.match(/^(\d+)\s+(.+?)\s*\(Promo\)\s*$/i);
                const regularMatch = item.match(/^(\d+)\s+(.+?)\s+-\s+₱([\d,]+\.?\d*)$/);
                if (promoMatch) {
                    quantity = parseInt(promoMatch[1], 10);
                    name = promoMatch[2].trim();
                    price = 0;
                    isPromo = true;
                } else if (regularMatch) {
                    quantity = parseInt(regularMatch[1], 10);
                    name = regularMatch[2].trim();
                    price = parseFloat(regularMatch[3].replace(/,/g, ''));
                }
                if (quantity > 0 && name) {
                    if (hasPromo) {
                        price = 0;
                        isPromo = true;
                    }
                    const key = name.toUpperCase() + (isPromo ? '_PROMO' : '');
                    if (!aggregated[key]) {
                        aggregated[key] = { quantity: 0, name, amount: 0, isPromo };
                    }
                    aggregated[key].quantity += quantity;
                    aggregated[key].amount += price;
                }
            });
            Object.values(aggregated).forEach(entry => {
                const label = entry.isPromo
                    ? `${entry.quantity} ${entry.name} (Promo)`
                    : `${entry.quantity} ${entry.name}`;
                lines.push({ label, amount: entry.amount, category: 'breakfast' });
            });
            return lines;
        }

        function getRoomRateInfo(booking) {
            let displayDuration = parseInt(booking.duration, 10) || 0;
            let durationUnit = (booking.duration_unit || 'hours').toLowerCase();
            let roomPrice = parseFloat(booking.room_price) || 0;
            let hasPromo = false;

            if (booking.promo && booking.promo.trim() !== '' && booking.promo !== 'Regular' && booking.promo !== 'Select Bundle') {
                const promoMatch = booking.promo.match(/^(.+?)\s+(\d+)\s*hrs\s+-\s+₱([0-9,.]+)$/i);
                if (promoMatch) {
                    displayDuration = parseInt(promoMatch[2], 10);
                    roomPrice = parseFloat(promoMatch[3].replace(/,/g, ''));
                    durationUnit = 'hours';
                    hasPromo = true;
                }
            }

            const unitLabel = durationUnit === 'night' || durationUnit === 'nights' ? 'Nights' : 'Hours';
            const label = hasPromo
                ? `Room Rate (${displayDuration} ${unitLabel} - Promo)`
                : `Room Rate (${displayDuration} ${unitLabel})`;

            return { label, roomPrice, displayDuration, unitLabel, hasPromo };
        }

        function getTotalDownpayment(booking) {
            const sum = (parseFloat(booking.downpayment_cash) || 0)
                + (parseFloat(booking.downpayment_gcash) || 0)
                + (parseFloat(booking.downpayment_maya) || 0)
                + (parseFloat(booking.downpayment_instapay) || 0)
                + (parseFloat(booking.downpayment_online_banking) || 0)
                + (parseFloat(booking.downpayment_airbnb) || 0);
            if (sum > 0) return sum;
            return parseFloat(booking.downpayment_amount) || 0;
        }

        function isReservationWithDownpayment(booking) {
            return getTotalDownpayment(booking) > 0;
        }

        function inferChargeCountFromPayment(paymentTotal, baseAmount, unitPrice) {
            if (!paymentTotal || !baseAmount || !unitPrice) return 0;
            const portion = paymentTotal - baseAmount;
            if (portion > 0.01 && Math.abs(portion % unitPrice) < 0.02) {
                return Math.round(portion / unitPrice);
            }
            return 0;
        }

        function buildGuestChargeLine(guestCount) {
            if (!guestCount || guestCount <= 0) return null;
            return {
                label: `${guestCount} Guest${guestCount > 1 ? 's' : ''}`,
                amount: guestCount * 300,
                category: 'guest'
            };
        }

        function buildPetChargeLine(petCount) {
            if (!petCount || petCount <= 0) return null;
            return {
                label: `${petCount} Pet${petCount > 1 ? 's' : ''}`,
                amount: petCount * 500,
                category: 'pet'
            };
        }

        function getExtendRateLabel(booking) {
            const extendHours = parseInt(booking.extend_hours, 10) || 0;
            const extendMins = parseInt(booking.extend_minutes, 10) || 0;
            if (extendHours > 0 || extendMins > 0) {
                return `Extended Rate (${extendHours}h ${extendMins}m)`;
            }
            return 'Extended Rate';
        }

        /** One extension installment (e.g. 24h / ₱1699 bundle), not cumulative extend_price. */
        function getExtendBlockInfo(booking, paymentTotal, totalPayments) {
            const roomInfo = getRoomRateInfo(booking);
            const totalExtendPrice = parseFloat(booking.extend_price) || 0;
            const extendRegularRate = parseFloat(booking.extend_regular_rate) || 0;
            const extendBundleRate = parseFloat(booking.extend_bundle_rate) || 0;
            const extendHours = parseInt(booking.extend_hours, 10) || 0;
            const extendMins = parseInt(booking.extend_minutes, 10) || 0;
            const blockHours = extendHours > 0 ? extendHours : (roomInfo.displayDuration > 0 ? roomInfo.displayDuration : 24);
            const extensionPaymentCount = Math.max(1, (totalPayments || 1) - 1);

            let blockAmount = 0;

            if (extendBundleRate > 0) {
                blockAmount = extendBundleRate;
            } else if (extendRegularRate > 0) {
                const isCumulativeTotal = totalExtendPrice > 0 &&
                    Math.abs(extendRegularRate - totalExtendPrice) < 0.02 &&
                    extensionPaymentCount > 1;
                blockAmount = isCumulativeTotal ? (totalExtendPrice / extensionPaymentCount) : extendRegularRate;
            } else if (totalExtendPrice > 0) {
                blockAmount = extensionPaymentCount > 1 ? (totalExtendPrice / extensionPaymentCount) : totalExtendPrice;
            } else if (roomInfo.roomPrice > 0) {
                blockAmount = roomInfo.roomPrice;
            }

            if (paymentTotal > 0 && blockAmount > 0 && Math.abs(paymentTotal - blockAmount) < 0.02) {
                blockAmount = paymentTotal;
            } else if (paymentTotal > 0 && extendBundleRate > 0 && Math.abs(paymentTotal - extendBundleRate) < 0.02) {
                blockAmount = extendBundleRate;
            } else if (paymentTotal > 0 && totalExtendPrice > 0 && Math.abs(paymentTotal - totalExtendPrice) < 0.02) {
                blockAmount = totalExtendPrice;
            }

            return { hours: blockHours, minutes: extendMins, amount: blockAmount };
        }

        function getExtendBlockLabel(block) {
            if (block.hours > 0 || block.minutes > 0) {
                return `Extended Rate (${block.hours}h ${block.minutes}m)`;
            }
            return 'Extended Rate';
        }

        /** Split an extension payment into extend block + guest/pet using the actual amount paid. */
        function splitExtensionPaymentAmount(paymentTotal, defaultBlockAmount, booking) {
            if (!paymentTotal || paymentTotal <= 0) {
                return { blockAmount: defaultBlockAmount, guestCount: 0, petCount: 0 };
            }

            const maxGuests = parseInt(booking?.additional_guest, 10) || 0;
            const maxPets = parseInt(booking?.additional_pet, 10) || 0;

            if (Math.abs(paymentTotal - defaultBlockAmount) < 0.02) {
                return { blockAmount: paymentTotal, guestCount: 0, petCount: 0 };
            }

            if (maxGuests === 0 && maxPets === 0) {
                return { blockAmount: paymentTotal, guestCount: 0, petCount: 0 };
            }

            let guestCount = inferChargeCountFromPayment(paymentTotal, defaultBlockAmount, 300);
            if (guestCount > 0) {
                const petCount = inferChargeCountFromPayment(
                    paymentTotal,
                    defaultBlockAmount + (guestCount * 300),
                    500
                );
                return { blockAmount: defaultBlockAmount, guestCount, petCount };
            }

            // Extension only (no guest on top)
            if (Math.abs(paymentTotal - defaultBlockAmount) < 0.02) {
                return { blockAmount: paymentTotal, guestCount: 0, petCount: 0 };
            }

            // Peel guest/pet charges when block amount was misread as cumulative total
            for (let g = 0; g <= 10; g++) {
                const afterGuest = paymentTotal - (g * 300);
                if (afterGuest <= 0.01) continue;

                const petCount = inferChargeCountFromPayment(afterGuest, defaultBlockAmount, 500);
                const blockAfterPet = afterGuest - (petCount * 500);

                if (Math.abs(blockAfterPet - defaultBlockAmount) < 0.02 || blockAfterPet > 0.01) {
                    return {
                        blockAmount: blockAfterPet > 0.01 ? blockAfterPet : defaultBlockAmount,
                        guestCount: g,
                        petCount
                    };
                }
            }

            return { blockAmount: paymentTotal, guestCount: 0, petCount: 0 };
        }

        function buildWalkInExtensionPaymentLines(booking, paymentTotal, totalPayments) {
            const block = getExtendBlockInfo(booking, paymentTotal, totalPayments);
            const split = splitExtensionPaymentAmount(paymentTotal, block.amount, booking);

            const lines = [{
                label: getExtendBlockLabel(block),
                amount: split.blockAmount,
                category: 'extend'
            }];

            const guestLine = buildGuestChargeLine(split.guestCount);
            if (guestLine) lines.push(guestLine);

            const petLine = buildPetChargeLine(split.petCount);
            if (petLine) lines.push(petLine);

            appendExtendBreakfastToLines(lines, booking);
            return lines;
        }

        function buildAllocationPool(booking) {
            const pool = [];
            const roomInfo = getRoomRateInfo(booking);

            if (roomInfo.roomPrice > 0) {
                pool.push({
                    label: roomInfo.label,
                    amount: roomInfo.roomPrice,
                    category: 'room'
                });
            }

            const hasPromoBreakfast = roomInfo.hasPromo || (booking.promo && booking.promo.trim() !== '' && booking.promo !== 'Regular' && booking.promo !== 'Select Bundle');
            parseBreakfastChargeLines(getCheckInBreakfastString(booking), hasPromoBreakfast).forEach(line => {
                pool.push({ label: line.label, amount: line.amount, category: 'breakfast' });
            });

            const guestCount = parseInt(booking.additional_guest, 10) || 0;
            for (let g = 0; g < guestCount; g++) {
                pool.push({ label: '1 Guest', amount: 300, category: 'guest', unit: 'guest' });
            }

            const petCount = parseInt(booking.additional_pet, 10) || 0;
            for (let p = 0; p < petCount; p++) {
                pool.push({ label: '1 Pet', amount: 500, category: 'pet', unit: 'pet' });
            }

            parseChargeLinesFromBooking(booking.additional_food, 'food').forEach(line => {
                pool.push({ label: line.label, amount: line.amount, category: 'food' });
            });
            parseChargeLinesFromBooking(booking.additional_items, 'item').forEach(line => {
                pool.push({ label: line.label, amount: line.amount, category: 'item' });
            });

            return pool;
        }

        function allocatePoolToPayments(pool, paymentTotals) {
            const perPayment = paymentTotals.map(() => []);
            let idx = 0;

            for (let p = 0; p < paymentTotals.length && idx < pool.length; p++) {
                let budget = paymentTotals[p] || 0;
                while (idx < pool.length) {
                    const item = pool[idx];
                    if (budget + 0.02 >= item.amount) {
                        budget -= item.amount;
                        perPayment[p].push(item);
                        idx++;
                    } else {
                        break;
                    }
                }
            }

            return perPayment;
        }

        function consolidateAllocatedLines(items) {
            if (!items || items.length === 0) return [];

            const lines = [];
            let guestUnits = 0;
            let petUnits = 0;

            items.forEach(item => {
                if (item.unit === 'guest') {
                    guestUnits++;
                    return;
                }
                if (item.unit === 'pet') {
                    petUnits++;
                    return;
                }
                lines.push({
                    label: item.label,
                    amount: item.amount,
                    category: item.category
                });
            });

            const guestLine = buildGuestChargeLine(guestUnits);
            const petLine = buildPetChargeLine(petUnits);

            const ordered = [];
            lines.filter(l => l.category === 'room').forEach(l => ordered.push(l));
            lines.filter(l => l.category === 'breakfast').forEach(l => ordered.push(l));
            if (guestLine) ordered.push(guestLine);
            if (petLine) ordered.push(petLine);
            lines.filter(l => l.category === 'food').forEach(l => ordered.push(l));
            lines.filter(l => l.category === 'item').forEach(l => ordered.push(l));

            return ordered;
        }

        function chargeLineToChargeObject(line, type) {
            const match = (line.label || '').match(/^(\d+)\s+(.+)$/);
            if (!match) return null;
            return {
                id: `alloc-${type}-${Math.random().toString(36).slice(2, 9)}`,
                type,
                quantity: parseInt(match[1], 10),
                selectedItem: match[2].trim(),
                price: parseFloat(line.amount) || 0
            };
        }

        function formatChargesSummary(charges) {
            if (!charges || charges.length === 0) return '';
            return charges.map(c => `${c.quantity} ${c.selectedItem}`).join(', ');
        }

        function buildReservationAllocationPool(booking) {
            const pool = [];
            const roomInfo = getRoomRateInfo(booking);
            const downpaymentTotal = getTotalDownpayment(booking);
            const roomBalance = Math.max(0, roomInfo.roomPrice - downpaymentTotal);

            if (roomBalance > 0) {
                pool.push({
                    label: roomInfo.label,
                    amount: roomBalance,
                    category: 'room'
                });
            }

            const hasPromoBreakfast = roomInfo.hasPromo || (booking.promo && booking.promo.trim() !== '' && booking.promo !== 'Regular' && booking.promo !== 'Select Bundle');
            parseBreakfastChargeLines(getCheckInBreakfastString(booking), hasPromoBreakfast).forEach(line => {
                pool.push({ label: line.label, amount: line.amount, category: 'breakfast' });
            });

            const guestCount = parseInt(booking.additional_guest, 10) || 0;
            for (let g = 0; g < guestCount; g++) {
                pool.push({ label: '1 Guest', amount: 300, category: 'guest', unit: 'guest' });
            }

            const petCount = parseInt(booking.additional_pet, 10) || 0;
            for (let p = 0; p < petCount; p++) {
                pool.push({ label: '1 Pet', amount: 500, category: 'pet', unit: 'pet' });
            }

            parseChargeLinesFromBooking(booking.additional_food, 'food').forEach(line => {
                pool.push({ label: line.label, amount: line.amount, category: 'food' });
            });
            parseChargeLinesFromBooking(booking.additional_items, 'item').forEach(line => {
                pool.push({ label: line.label, amount: line.amount, category: 'item' });
            });

            return pool;
        }

        function appendAdditionalsToLines(lines, addOverride) {
            if (!addOverride) return lines;

            const guestLine = buildGuestChargeLine(addOverride.guests || 0);
            const petLine = buildPetChargeLine(addOverride.pets || 0);
            if (guestLine) lines.push(guestLine);
            if (petLine) lines.push(petLine);

            (addOverride.charges || []).forEach(c => {
                const qty = parseInt(c.quantity, 10) || 0;
                const name = (c.selectedItem || c.name || '').trim();
                const price = parseFloat(c.price) || 0;
                if (qty > 0 && name) {
                    lines.push({
                        label: `${qty} ${name}`,
                        amount: price,
                        category: c.type === 'food' ? 'food' : 'item'
                    });
                }
            });

            return lines;
        }

        function getPerPaymentAdditionalsFromAllocation(booking, paymentTotals) {
            const useReservationPool = isReservationWithDownpayment(booking) && paymentTotals.length > 1;
            const pool = useReservationPool
                ? buildReservationAllocationPool(booking)
                : buildAllocationPool(booking);
            const allocated = allocatePoolToPayments(pool, paymentTotals);
            return allocated.map(items => {
                let guests = 0;
                let pets = 0;
                const charges = [];
                items.forEach(item => {
                    if (item.unit === 'guest') guests++;
                    else if (item.unit === 'pet') pets++;
                    else if (item.category === 'food') {
                        const c = chargeLineToChargeObject(item, 'food');
                        if (c) charges.push(c);
                    } else if (item.category === 'item') {
                        const c = chargeLineToChargeObject(item, 'item');
                        if (c) charges.push(c);
                    }
                });
                return { guests, pets, charges };
            });
        }

        function getPerPaymentAdditionalsForDisplay(booking, paymentIndex, paymentTotals) {
            const extendPrice = parseFloat(booking.extend_price) || 0;
            const isWalkInExtensionSplit = !isReservationWithDownpayment(booking)
                && paymentTotals.length >= 2 && extendPrice > 0;

            if (isWalkInExtensionSplit && paymentIndex >= 1) {
                const guestCount = parseInt(booking.additional_guest, 10) || 0;
                const petCount = parseInt(booking.additional_pet, 10) || 0;
                if (guestCount === 0 && petCount === 0) {
                    return { guests: 0, pets: 0, charges: [] };
                }
                const paymentTotal = paymentTotals[paymentIndex] || 0;
                const block = getExtendBlockInfo(booking, paymentTotal, paymentTotals.length);
                const split = splitExtensionPaymentAmount(paymentTotal, block.amount, booking);
                return { guests: split.guestCount, pets: split.petCount, charges: [] };
            }

            const all = getPerPaymentAdditionalsFromAllocation(booking, paymentTotals);
            return all[paymentIndex] || { guests: 0, pets: 0, charges: [] };
        }

        function getPayHistAdditionalsForIndex(idx) {
            const guest = parseInt(document.getElementById(`payHist_add_guest_${idx}`)?.value, 10) || 0;
            const pet = parseInt(document.getElementById(`payHist_add_pet_${idx}`)?.value, 10) || 0;
            const jsonEl = document.getElementById(`payHist_add_charges_json_${idx}`);
            let charges = [];
            if (jsonEl && jsonEl.value) {
                try {
                    const parsed = JSON.parse(jsonEl.value);
                    if (Array.isArray(parsed)) charges = parsed;
                } catch (e) { /* ignore */ }
            }
            return { guests: guest, pets: pet, charges };
        }

        function parseAdditionalDateHistory(raw) {
            if (!raw) return [];
            const str = String(raw).trim();
            if (!str || str === 'NULL' || str === '0000-00-00 00:00:00' || str === '0000-00-00') return [];
            try {
                const parsed = JSON.parse(str);
                if (Array.isArray(parsed)) {
                    return parsed
                        .map(v => String(v || '').trim())
                        .filter(v => v && v !== 'NULL' && v !== '0000-00-00 00:00:00' && v !== '0000-00-00');
                }
            } catch (e) { /* fall back to legacy single datetime */ }
            return [str];
        }

        function getDateOnlyString(raw) {
            if (!raw) return '';
            const normalized = normalizePaymentDateTime(raw);
            return normalized.length >= 10 ? normalized.substring(0, 10) : '';
        }

        function getPaymentDateForIndex(booking, idx) {
            const dtEl = document.getElementById(`payHist_datetime_${idx}`);
            if (dtEl && dtEl.value) {
                const fromInput = getDateOnlyString(dtEl.value);
                if (fromInput) return fromInput;
            }
            const dateHist = (booking?.payment_date_time && String(booking.payment_date_time).trim())
                ? String(booking.payment_date_time).split('|').map(s => s.trim())
                : [];
            return getDateOnlyString(dateHist[idx] || '');
        }

        function countAdditionalDateMatchesOnPayment(booking, idx, dateFieldKey) {
            const paymentDate = getPaymentDateForIndex(booking, idx);
            if (!paymentDate) return 0;
            const raw = booking ? booking[dateFieldKey] : '';
            const dates = parseAdditionalDateHistory(raw);
            let count = 0;
            dates.forEach(d => {
                if (getDateOnlyString(d) === paymentDate) count++;
            });
            return count;
        }

        function getPayHistOriginalAdditionalsForIndex(idx, booking) {
            const guestEl = document.getElementById(`payHist_add_guest_${idx}`);
            const petEl = document.getElementById(`payHist_add_pet_${idx}`);
            const datasetGuests = parseInt(guestEl?.dataset?.originalValue ?? guestEl?.value, 10) || 0;
            const datasetPets = parseInt(petEl?.dataset?.originalValue ?? petEl?.value, 10) || 0;

            // Persisted history source of truth: once an additional was paid on this payment date,
            // keep it visible as removable even if additional_guest/additional_pet is now zero.
            const datedGuests = countAdditionalDateMatchesOnPayment(booking, idx, 'additional_guest_date');
            const datedPets = countAdditionalDateMatchesOnPayment(booking, idx, 'additional_pet_date');

            const guests = Math.max(datasetGuests, datedGuests);
            const pets = Math.max(datasetPets, datedPets);
            return { guests, pets };
        }

        function buildWalkInMultiPaymentLines(booking, paymentIndex, paymentTotals, additionalsOverride) {
            const pool = buildAllocationPool(booking);
            const allocated = allocatePoolToPayments(pool, paymentTotals);
            const fixedItems = (allocated[paymentIndex] || []).filter(i =>
                i.category === 'room' || i.category === 'breakfast'
            );

            if (!additionalsOverride) {
                return consolidateAllocatedLines(allocated[paymentIndex] || []);
            }

            const guestLine = buildGuestChargeLine(additionalsOverride.guests || 0);
            const petLine = buildPetChargeLine(additionalsOverride.pets || 0);
            const chargeLines = [];

            (additionalsOverride.charges || []).forEach(c => {
                const qty = parseInt(c.quantity, 10) || 0;
                const name = (c.selectedItem || c.name || '').trim();
                const price = parseFloat(c.price) || 0;
                if (qty > 0 && name) {
                    chargeLines.push({
                        label: `${qty} ${name}`,
                        amount: price,
                        category: c.type === 'food' ? 'food' : 'item'
                    });
                }
            });

            const ordered = [];
            fixedItems.forEach(l => ordered.push(l));
            if (guestLine) ordered.push(guestLine);
            if (petLine) ordered.push(petLine);
            chargeLines.forEach(l => ordered.push(l));
            return ordered;
        }

        function buildCheckInChargeLines(booking) {
            const lines = [];
            const roomInfo = getRoomRateInfo(booking);

            if (roomInfo.roomPrice > 0) {
                lines.push({ label: roomInfo.label, amount: roomInfo.roomPrice, category: 'room' });
            }

            const hasPromoBreakfast = roomInfo.hasPromo || (booking.promo && booking.promo.trim() !== '' && booking.promo !== 'Regular' && booking.promo !== 'Select Bundle');
            parseBreakfastChargeLines(getCheckInBreakfastString(booking), hasPromoBreakfast).forEach(line => lines.push(line));

            const guestCount = parseInt(booking.additional_guest, 10) || 0;
            if (guestCount > 0) {
                lines.push({
                    label: `${guestCount} Guest${guestCount > 1 ? 's' : ''}`,
                    amount: guestCount * 300,
                    category: 'guest'
                });
            }

            const petCount = parseInt(booking.additional_pet, 10) || 0;
            if (petCount > 0) {
                lines.push({
                    label: `${petCount} Pet${petCount > 1 ? 's' : ''}`,
                    amount: petCount * 500,
                    category: 'pet'
                });
            }

            parseChargeLinesFromBooking(booking.additional_food, 'food').forEach(line => lines.push(line));
            parseChargeLinesFromBooking(booking.additional_items, 'item').forEach(line => lines.push(line));

            return lines;
        }

        function getPaymentDateOnly(dateStr) {
            if (!dateStr) return '';
            const normalized = normalizePaymentDateTime(dateStr);
            return normalized.length >= 10 ? normalized.substring(0, 10) : normalized;
        }

        function normalizePaymentDateTime(dateStr) {
            if (!dateStr) return '';
            let s = String(dateStr).trim().replace('T', ' ');
            if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
                return s + ' 00:00:00';
            }
            if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(s)) {
                return s + ':00';
            }

            const d = new Date(s.includes(' ') ? s.replace(' ', 'T') : s);
            if (!isNaN(d.getTime())) {
                return d.getFullYear() + '-'
                    + String(d.getMonth() + 1).padStart(2, '0') + '-'
                    + String(d.getDate()).padStart(2, '0') + ' '
                    + String(d.getHours()).padStart(2, '0') + ':'
                    + String(d.getMinutes()).padStart(2, '0') + ':'
                    + String(d.getSeconds()).padStart(2, '0');
            }

            return s.length >= 19 ? s.substring(0, 19) : s;
        }

        function paymentDateTimesStrictMatch(paymentDt, discountDt) {
            const p = normalizePaymentDateTime(paymentDt);
            const d = normalizePaymentDateTime(discountDt);
            if (!p || !d) return false;
            if (p === d) return true;
            return p.substring(0, 16) === d.substring(0, 16);
        }

        function paymentDateOnlyMatch(paymentDt, discountDt) {
            const payDate = getPaymentDateOnly(paymentDt);
            const discDate = getPaymentDateOnly(discountDt);
            return payDate !== '' && payDate === discDate;
        }

        function parseDiscountHistoryEntries(booking) {
            const raw = (booking.discount_amount_history || '').trim();
            if (!raw) return [];
            return raw.split('|').map(entry => {
                const parts = entry.split(':', 2);
                return {
                    amount: parseFloat(parts[0]) || 0,
                    datetime: (parts[1] || '').trim()
                };
            }).filter(e => e.amount > 0);
        }

        function getDiscountAmountsPerPayment(booking, totalPayments, dateHist) {
            const amounts = new Array(totalPayments).fill(0);
            const history = parseDiscountHistoryEntries(booking);
            const totalDiscount = parseFloat(booking.discount_amount) || 0;

            if (history.length > 0) {
                const usedHist = new Array(history.length).fill(false);

                // Pass 1: match discount history to payment by exact date + time
                for (let i = 0; i < totalPayments; i++) {
                    if (!dateHist[i]) continue;
                    for (let h = 0; h < history.length; h++) {
                        if (usedHist[h]) continue;
                        if (paymentDateTimesStrictMatch(dateHist[i], history[h].datetime)) {
                            amounts[i] += history[h].amount;
                            usedHist[h] = true;
                            break;
                        }
                    }
                }
                if (amounts.some(a => a > 0)) {
                    applyReservationDiscountRules(booking, totalPayments, amounts);
                    return amounts;
                }

                // Pass 2: same calendar date only (when time is missing or differs)
                const usedHistDate = new Array(history.length).fill(false);
                for (let i = 0; i < totalPayments; i++) {
                    if (amounts[i] > 0 || !dateHist[i]) continue;
                    for (let h = 0; h < history.length; h++) {
                        if (usedHistDate[h]) continue;
                        if (paymentDateOnlyMatch(dateHist[i], history[h].datetime)) {
                            amounts[i] += history[h].amount;
                            usedHistDate[h] = true;
                            break;
                        }
                    }
                }
                if (amounts.some(a => a > 0)) {
                    applyReservationDiscountRules(booking, totalPayments, amounts);
                    return amounts;
                }

                // Pass 3: align by chronological order of payment vs discount timestamps
                const payOrder = [...Array(totalPayments).keys()]
                    .filter(i => dateHist[i])
                    .sort((a, b) => normalizePaymentDateTime(dateHist[a])
                        .localeCompare(normalizePaymentDateTime(dateHist[b])));
                const histOrder = history
                    .map((entry, h) => ({ entry, h }))
                    .sort((a, b) => normalizePaymentDateTime(a.entry.datetime)
                        .localeCompare(normalizePaymentDateTime(b.entry.datetime)));

                for (let k = 0; k < Math.min(payOrder.length, histOrder.length); k++) {
                    amounts[payOrder[k]] = histOrder[k].entry.amount;
                }
                if (amounts.some(a => a > 0)) {
                    applyReservationDiscountRules(booking, totalPayments, amounts);
                    return amounts;
                }
            }

            if (totalDiscount > 0) {
                if (totalPayments <= 1) {
                    amounts[0] = totalDiscount;
                } else if (isReservationWithDownpayment(booking)) {
                    amounts[1] = totalDiscount;
                } else if (dateHist.length === totalPayments && dateHist.every(d => d)) {
                    const firstIdx = [...Array(totalPayments).keys()]
                        .sort((a, b) => normalizePaymentDateTime(dateHist[a])
                            .localeCompare(normalizePaymentDateTime(dateHist[b])))[0];
                    amounts[firstIdx] = totalDiscount;
                } else {
                    const perPay = totalDiscount / totalPayments;
                    for (let i = 0; i < totalPayments; i++) amounts[i] = perPay;
                }
            }

            applyReservationDiscountRules(booking, totalPayments, amounts);
            return amounts;
        }

        /** Reservation 1st payment is downpayment only — discount always on 2nd payment. */
        function applyReservationDiscountRules(booking, totalPayments, amounts) {
            if (!isReservationWithDownpayment(booking) || totalPayments < 2) return;

            if (amounts[0] > 0) {
                amounts[1] = (amounts[1] || 0) + amounts[0];
                amounts[0] = 0;
            }

            const totalAssigned = amounts.reduce((s, a) => s + a, 0);
            const bookingDisc = parseFloat(booking.discount_amount) || 0;
            if (totalAssigned === 0 && bookingDisc > 0) {
                amounts[1] = bookingDisc;
            }
        }

        function paymentHasMatchedDiscountHistory(booking, paymentIndex, dateHist, paymentDiscount, totalPayments) {
            if (paymentDiscount <= 0) return false;

            if (isReservationWithDownpayment(booking) && totalPayments > 1 && paymentIndex === 0) {
                return false;
            }

            const history = parseDiscountHistoryEntries(booking);
            if (history.length === 0) return true;

            const payDt = dateHist[paymentIndex] || '';
            if (!payDt) return false;

            return history.some(entry =>
                paymentDateTimesStrictMatch(payDt, entry.datetime) ||
                paymentDateOnlyMatch(payDt, entry.datetime)
            );
        }

        function collectPayHistDateTimes() {
            const dateHist = [];
            let i = 0;
            while (document.getElementById(`payHist_cash_chk_${i}`) !== null) {
                const dtEl = document.getElementById(`payHist_datetime_${i}`);
                dateHist.push(dtEl && dtEl.value ? formatPayHistDateTime(dtEl) : '');
                i++;
            }
            return dateHist;
        }

        function collectPayHistPaymentTotals() {
            const totals = [];
            let i = 0;
            while (document.getElementById(`payHist_cash_chk_${i}`) !== null) {
                totals.push(getPayHistPaymentTotal(i));
                i++;
            }
            return totals;
        }

        function refreshPayHistDiscountPlacement() {
            const booking = window.currentBooking;
            if (!booking) return;

            const dateHist = collectPayHistDateTimes();
            const count = dateHist.length;
            if (count === 0) return;

            const discountPerPayment = getDiscountAmountsPerPayment(booking, count, dateHist);
            const allPaymentTotals = collectPayHistPaymentTotals();

            for (let i = 0; i < count; i++) {
                const paymentDiscount = discountPerPayment[i] || 0;
                const hasMatched = paymentHasMatchedDiscountHistory(booking, i, dateHist, paymentDiscount, count);
                const paymentTotal = allPaymentTotals[i] || 0;

                const discAmtEl = document.getElementById(`payHist_discount_amt_${i}`);
                if (discAmtEl) {
                    discAmtEl.value = paymentDiscount > 0 ? paymentDiscount.toFixed(2) : '';
                }

                const countEl = document.getElementById(`payHist_discount_count_${i}`);
                if (countEl) {
                    countEl.value = hasMatched ? (booking.sc_pwd_count || '') : '';
                }

                const idList = hasMatched ? parseDiscountIdList(booking.id_number) : [];
                updatePayHistDiscountIdFields(i, idList);

                const container = document.getElementById(`payHist_payment_for_${i}`);
                if (container) {
                    const lines = buildPaymentForLines(booking, i, count, paymentTotal, paymentDiscount, allPaymentTotals);
                    container.innerHTML = renderPaymentForBreakdownHtml(lines, paymentTotal);
                }
            }

            syncModalDiscountFromPayments();
        }

        function appendDiscountLine(lines, discountAmount) {
            const discount = parseFloat(discountAmount) || 0;
            if (discount > 0) {
                lines.push({ label: 'Discount', amount: -discount, category: 'discount' });
            }
            return lines;
        }

        function getNormalizedPaymentTotals(totalPayments, paymentTotal, paymentTotals) {
            if (Array.isArray(paymentTotals) && paymentTotals.length === totalPayments) {
                return paymentTotals;
            }
            return new Array(totalPayments).fill(paymentTotal);
        }

        function appendRemovedAdditionalsLines(lines, booking, paymentIndex, totalPayments, paymentTotal, paymentTotals, addOverride) {
            if (!addOverride) return lines;

            const original = getPayHistOriginalAdditionalsForIndex(paymentIndex, booking);
            const currentGuests = parseInt(addOverride.guests, 10) || 0;
            const currentPets = parseInt(addOverride.pets, 10) || 0;

            const removedGuests = Math.max(0, (parseInt(original.guests, 10) || 0) - currentGuests);
            const removedPets = Math.max(0, (parseInt(original.pets, 10) || 0) - currentPets);

            const removedGuestLine = buildGuestChargeLine(removedGuests);
            if (removedGuestLine) {
                lines.push({ ...removedGuestLine, label: `${removedGuestLine.label} (Removed)`, isRemoved: true });
            }

            const removedPetLine = buildPetChargeLine(removedPets);
            if (removedPetLine) {
                lines.push({ ...removedPetLine, label: `${removedPetLine.label} (Removed)`, isRemoved: true });
            }

            return lines;
        }

        function buildPaymentForLines(booking, paymentIndex, totalPayments, paymentTotal, paymentDiscount, paymentTotals) {
            booking = getBookingForPaymentLines(booking, paymentIndex, totalPayments);
            const extendPrice = parseFloat(booking.extend_price) || 0;
            const roomInfo = getRoomRateInfo(booking);
            const checkInLines = buildCheckInChargeLines(booking);
            const downpaymentTotal = getTotalDownpayment(booking);
            const isReservationSplit = isReservationWithDownpayment(booking) && totalPayments > 1;

            const applyDiscount = (lines) => appendDiscountLine(lines, paymentDiscount);

            // Reservation with only downpayment paid so far (single history entry)
            if (isReservationWithDownpayment(booking) && totalPayments === 1) {
                if (paymentTotal > 0 && Math.abs(paymentTotal - downpaymentTotal) < 0.02) {
                    return applyDiscount([{
                        label: 'Reservation',
                        amount: paymentTotal,
                        category: 'reservation'
                    }]);
                }
            }

            // Reservation: 1st payment = downpayment, 2nd = room rate balance, 3rd+ = extras/extend
            if (isReservationSplit) {
                if (paymentIndex === 0) {
                    return [{
                        label: 'Reservation',
                        amount: paymentTotal > 0 ? paymentTotal : downpaymentTotal,
                        category: 'reservation'
                    }];
                }

                if (paymentIndex === 1) {
                    const roomBalance = Math.max(0, roomInfo.roomPrice - downpaymentTotal);
                    const addOverride = document.getElementById(`payHist_add_guest_${paymentIndex}`)
                        ? getPayHistAdditionalsForIndex(paymentIndex)
                        : null;

                    const lines = [{
                        label: roomInfo.label,
                        amount: roomBalance,
                        category: 'room'
                    }];

                    if (addOverride) {
                        appendAdditionalsToLines(lines, addOverride);
                        appendRemovedAdditionalsLines(lines, booking, paymentIndex, totalPayments, paymentTotal, paymentTotals, addOverride);
                        return applyDiscount(lines);
                    }

                    const nonRoomCharges = checkInLines.filter(l =>
                        l.category !== 'room' && l.category !== 'discount'
                    );

                    if (totalPayments >= 3) {
                        lines[0].amount = paymentTotal > 0 ? paymentTotal : roomBalance;
                        return applyDiscount(lines);
                    }

                    if (nonRoomCharges.length === 0) {
                        lines[0].amount = paymentTotal > 0 ? paymentTotal : roomBalance;
                        return applyDiscount(lines);
                    }

                    nonRoomCharges.forEach(l => lines.push(l));
                    return applyDiscount(lines);
                }

                if (paymentIndex >= 2) {
                    const addOverride = document.getElementById(`payHist_add_guest_${paymentIndex}`)
                        ? getPayHistAdditionalsForIndex(paymentIndex)
                        : null;

                    if (addOverride) {
                        const lines = [];
                        appendAdditionalsToLines(lines, addOverride);
                        appendRemovedAdditionalsLines(lines, booking, paymentIndex, totalPayments, paymentTotal, paymentTotals, addOverride);
                        if (lines.length > 0) return applyDiscount(lines);
                    }
                }

                if (paymentIndex === 2 && extendPrice > 0) {
                    const extendHours = parseInt(booking.extend_hours, 10) || 0;
                    const extendMins = parseInt(booking.extend_minutes, 10) || 0;
                    let extendLabel = 'Extended Rate';
                    if (extendHours > 0 || extendMins > 0) {
                        extendLabel = `Extended Rate (${extendHours}h ${extendMins}m)`;
                    }
                    return applyDiscount([{ label: extendLabel, amount: extendPrice, category: 'extend' }]);
                }

                const extraLines = checkInLines.filter(l =>
                    l.category !== 'room' && l.category !== 'discount' && l.category !== 'reservation'
                );
                if (extraLines.length > 0) return applyDiscount(extraLines);

                return applyDiscount([{ label: roomInfo.label, amount: paymentTotal > 0 ? paymentTotal : null, category: 'room' }]);
            }

            // Walk-in / single payment: show full charges breakdown
            if (totalPayments <= 1) {
                const addOverride = document.getElementById('payHist_add_guest_0')
                    ? getPayHistAdditionalsForIndex(0)
                    : null;
                if (addOverride) {
                    const totals = (paymentTotals && paymentTotals.length) ? paymentTotals : [paymentTotal];
                    const lines = buildWalkInMultiPaymentLines(booking, 0, totals, addOverride);
                    appendRemovedAdditionalsLines(lines, booking, 0, totalPayments, paymentTotal, paymentTotals, addOverride);
                    if (extendPrice > 0) {
                        const extendHours = parseInt(booking.extend_hours, 10) || 0;
                        const extendMins = parseInt(booking.extend_minutes, 10) || 0;
                        let extendLabel = 'Extended Rate';
                        if (extendHours > 0 || extendMins > 0) {
                            extendLabel = `Extended Rate (${extendHours}h ${extendMins}m)`;
                        }
                        lines.push({ label: extendLabel, amount: extendPrice, category: 'extend' });
                    }
                    return applyDiscount(lines);
                }
                if (extendPrice > 0) {
                    const extendHours = parseInt(booking.extend_hours, 10) || 0;
                    const extendMins = parseInt(booking.extend_minutes, 10) || 0;
                    let extendLabel = 'Extended Rate';
                    if (extendHours > 0 || extendMins > 0) {
                        extendLabel = `Extended Rate (${extendHours}h ${extendMins}m)`;
                    }
                    checkInLines.push({ label: extendLabel, amount: extendPrice, category: 'extend' });
                }
                return applyDiscount(checkInLines);
            }

            // Walk-in with extension split across payments:
            // 1st payment = Room Rate + guest/add-ons for check-in period
            // 2nd payment = Extended Rate + guest/add-ons for extension period
            const isWalkInExtensionSplit = !isReservationSplit && totalPayments >= 2 && extendPrice > 0;

            if (isWalkInExtensionSplit) {
                const totals = (paymentTotals && paymentTotals.length === totalPayments)
                    ? paymentTotals
                    : new Array(totalPayments).fill(paymentTotal);
                const addOverride = document.getElementById(`payHist_add_guest_${paymentIndex}`)
                    ? getPayHistAdditionalsForIndex(paymentIndex)
                    : null;

                if (paymentIndex === 0) {
                    if (addOverride) {
                        const lines = buildWalkInMultiPaymentLines(booking, 0, totals, addOverride);
                        checkInLines.filter(l =>
                            l.category !== 'room' &&
                            l.category !== 'guest' &&
                            l.category !== 'pet' &&
                            l.category !== 'discount' &&
                            l.category !== 'extend' &&
                            l.category !== 'breakfast' &&
                            l.category !== 'food' &&
                            l.category !== 'item'

                        ).forEach(l => lines.push(l));
                        appendRemovedAdditionalsLines(lines, booking, paymentIndex, totalPayments, paymentTotal, totals, addOverride);
                        return applyDiscount(lines);
                    }

                    const lines = [];
                    if (roomInfo.roomPrice > 0) {
                        lines.push({ label: roomInfo.label, amount: roomInfo.roomPrice, category: 'room' });
                    }

                    const guestCount = inferChargeCountFromPayment(paymentTotal, roomInfo.roomPrice, 300);
                    const guestLine = buildGuestChargeLine(guestCount);
                    if (guestLine) lines.push(guestLine);

                    const petCount = inferChargeCountFromPayment(
                        paymentTotal,
                        roomInfo.roomPrice + (guestCount * 300),
                        500
                    );
                    const petLine = buildPetChargeLine(petCount);
                    if (petLine) lines.push(petLine);

                    checkInLines.filter(l =>
                        l.category !== 'room' &&
                        l.category !== 'guest' &&
                        l.category !== 'pet' &&
                        l.category !== 'discount' &&
                        l.category !== 'extend'
                    ).forEach(l => lines.push(l));

                    return applyDiscount(lines);
                }

                // 2nd+ payments: each extension installment uses one 24h block (not cumulative total)
                if (paymentIndex >= 1) {
                    if (addOverride) {
                        const block = getExtendBlockInfo(booking, paymentTotal, totalPayments);
                        const lines = [];
                        const blockAmt = block.amount > 0 ? block.amount : paymentTotal;
                        lines.push({
                            label: getExtendBlockLabel(block),
                            amount: blockAmt,
                            category: 'extend'
                        });
                        if ((addOverride.guests || 0) > 0) {
                            const guestLine = buildGuestChargeLine(addOverride.guests);
                            if (guestLine) lines.push(guestLine);
                        }
                        if ((addOverride.pets || 0) > 0) {
                            const petLine = buildPetChargeLine(addOverride.pets);
                            if (petLine) lines.push(petLine);
                        }
                        (addOverride.charges || []).forEach(c => {
                            const qty = parseInt(c.quantity, 10) || 0;
                            const name = (c.selectedItem || c.name || '').trim();
                            const price = parseFloat(c.price) || 0;
                            if (qty > 0 && name) {
                                lines.push({
                                    label: `${qty} ${name}`,
                                    amount: price,
                                    category: c.type === 'food' ? 'food' : 'item'
                                });
                            }
                        });
                        appendExtendBreakfastToLines(lines, booking);
                        appendRemovedAdditionalsLines(lines, booking, paymentIndex, totalPayments, paymentTotal, totals, addOverride);
                        return applyDiscount(lines);
                    }

                    return applyDiscount(buildWalkInExtensionPaymentLines(booking, paymentTotal, totalPayments));
                }
            }

            // Walk-in with multiple payments (no extension): allocate room/guest/pet/food/items per payment amount
            if (!isReservationSplit && totalPayments >= 2 && extendPrice <= 0) {
                const totals = (paymentTotals && paymentTotals.length === totalPayments)
                    ? paymentTotals
                    : new Array(totalPayments).fill(paymentTotal);
                const addOverride = document.getElementById(`payHist_add_guest_${paymentIndex}`)
                    ? getPayHistAdditionalsForIndex(paymentIndex)
                    : null;
                const lines = buildWalkInMultiPaymentLines(booking, paymentIndex, totals, addOverride);
                if (addOverride) {
                    appendRemovedAdditionalsLines(lines, booking, paymentIndex, totalPayments, paymentTotal, totals, addOverride);
                }
                if (lines.length > 0) return applyDiscount(lines);
            }

            // Walk-in with multiple payments + extension on 2nd only (legacy)
            if (paymentIndex === 1 && extendPrice > 0) {
                return applyDiscount([{ label: getExtendRateLabel(booking), amount: extendPrice, category: 'extend' }]);
            }

            if (paymentIndex === 0) {
                return applyDiscount(checkInLines);
            }

            // Extras-only payment: infer guest/pet from amount (e.g. ₱800 = 1 guest + 1 pet)
            const extraLines = [];
            const guestCount = Math.floor((paymentTotal + 0.02) / 300);
            const guestAmt = guestCount * 300;
            const petRemainder = paymentTotal - guestAmt;
            const petCount = petRemainder > 0.01 ? Math.round(petRemainder / 500) : 0;

            if (guestCount > 0 && Math.abs(guestAmt + (petCount * 500) - paymentTotal) < 0.02) {
                const guestLine = buildGuestChargeLine(guestCount);
                const petLine = buildPetChargeLine(petCount);
                if (guestLine) extraLines.push(guestLine);
                if (petLine) extraLines.push(petLine);
                if (extraLines.length > 0) return applyDiscount(extraLines);
            }

            const petOnly = buildPetChargeLine(Math.round(paymentTotal / 500));
            if (petOnly && Math.abs(petOnly.amount - paymentTotal) < 0.02) {
                return applyDiscount([petOnly]);
            }

            return applyDiscount([{ label: roomInfo.label, amount: paymentTotal > 0 ? paymentTotal : null, category: 'room' }]);
        }

        function renderPaymentForBreakdownHtml(lines, paymentTotal) {
            if (!lines || lines.length === 0) {
                return '<span style="color:#9ca3af;font-size:12px;">No charge breakdown available.</span>';
            }

            const expandedLines = [];
            lines.forEach(line => {
                const label = String(line?.label || '');
                const guestMatch = label.match(/^(\d+)\s+Guest(?:s)?(?:\s+\(Removed\))?$/i);
                const petMatch = label.match(/^(\d+)\s+Pet(?:s)?(?:\s+\(Removed\))?$/i);
                const isRemoved = !!line?.isRemoved;

                if (guestMatch && (parseInt(guestMatch[1], 10) || 0) > 1) {
                    const count = parseInt(guestMatch[1], 10) || 0;
                    for (let i = 0; i < count; i++) {
                        expandedLines.push({
                            ...line,
                            label: isRemoved ? '1 Guest (Removed)' : '1 Guest',
                            amount: 300
                        });
                    }
                    return;
                }

                if (petMatch && (parseInt(petMatch[1], 10) || 0) > 1) {
                    const count = parseInt(petMatch[1], 10) || 0;
                    for (let i = 0; i < count; i++) {
                        expandedLines.push({
                            ...line,
                            label: isRemoved ? '1 Pet (Removed)' : '1 Pet',
                            amount: 500
                        });
                    }
                    return;
                }

                expandedLines.push(line);
            });

            let rows = expandedLines.map(line => {
                const amountText = line.amount === null
                    ? '—'
                    : (line.amount < 0
                        ? `-₱${Math.abs(line.amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                        : `₱${line.amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
                const labelStyle = line.isRemoved
                    ? 'color:#9ca3af;text-decoration:line-through;'
                    : 'color:#6b7280;';
                const amountStyle = line.isRemoved
                    ? 'font-weight:600;color:#9ca3af;text-decoration:line-through;'
                    : 'font-weight:600;color:#111827;';
                return `
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:13px;">
                        <span style="${labelStyle}">${line.label}</span>
                        <span style="${amountStyle}">${amountText}</span>
                    </div>`;
            }).join('');

            // Payment amounts are already net of discount — use entered total, don't subtract discount again
            const displaySubtotal = paymentTotal > 0 ? paymentTotal : expandedLines.reduce((sum, line) => {
                return line.amount !== null && !line.isRemoved ? sum + line.amount : sum;
            }, 0);
            rows += `
                <div style="display:flex;justify-content:space-between;align-items:center;padding-top:8px;margin-top:6px;border-top:1px solid #e5e7eb;font-size:13px;">
                    <span style="font-weight:600;color:#374151;">Subtotal</span>
                    <span style="font-weight:700;color:#111827;">₱${displaySubtotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                </div>`;

            return `<div style="display:flex;flex-direction:column;gap:2px;">${rows}</div>`;
        }

        function parseReferenceList(val) {
            if (!val || !String(val).trim()) return [];
            return String(val).split(',').map(s => s.trim()).filter(Boolean);
        }

        function getBookingReferenceString(booking, methodKey) {
            const fieldMap = {
                gcash: ['reference_no_g_cash', 'deposit_gcash_ref', 'downpayment_gcash_ref'],
                maya: ['reference_no_maya', 'deposit_maya_ref', 'downpayment_maya_ref'],
                instapay: ['reference_no_instapay', 'deposit_instapay_ref', 'downpayment_instapay_ref'],
                ob: ['reference_no_online_banking', 'deposit_online_banking_ref', 'downpayment_online_banking_ref'],
                airbnb: ['reference_no_airbnb', 'deposit_airbnb_ref', 'downpayment_airbnb_ref']
            };
            const fields = fieldMap[methodKey] || [];
            for (const field of fields) {
                const value = booking[field];
                if (value && String(value).trim()) {
                    return String(value).trim();
                }
            }
            return '';
        }

        function getPaymentMethodRefForIndex(booking, methodKey, paymentIndex, histArrays) {
            const refStr = getBookingReferenceString(booking, methodKey);
            if (!refStr) return '';

            const refs = parseReferenceList(refStr);
            if (refs.length === 0) return '';
            if (refs.length === 1) return refs[0];

            const hist = histArrays[methodKey] || [];
            let methodOrdinal = 0;
            for (let j = 0; j < paymentIndex; j++) {
                if ((hist[j] || 0) > 0) methodOrdinal++;
            }
            return refs[methodOrdinal] || refs[refs.length - 1] || '';
        }

        /**
         * Render each payment entry as a full editable Payment Method UI card.
         * Each card shows: method checkboxes + amount inputs + datetime picker.
         * History is pipe-separated: "1490|1490" for cash across 2 payments.
         */
        function renderPaymentHistory(booking) {
            const container = document.getElementById('modalPaymentHistoryContent');
            if (!container) return;

            const parseHist = (val) => val && val.trim() ? val.split('|').map(s => parseFloat(s.trim()) || 0) : [];
            const parseDates = (val) => val && val.trim() ? val.split('|').map(s => s.trim()) : [];

            const cashHist = parseHist(booking.payment_amount_cash_history);
            const gcashHist = parseHist(booking.payment_amount_g_cash_history);
            const mayaHist = parseHist(booking.payment_amount_maya_history);
            const instHist = parseHist(booking.payment_amount_instapay_history);
            const obHist = parseHist(booking.payment_amount_online_banking_history);
            const airHist = parseHist(booking.payment_amount_airbnb_history);
            const dateHist = parseDates(booking.payment_date_time);

            const count = Math.max(
                cashHist.length, gcashHist.length, mayaHist.length,
                instHist.length, obHist.length, airHist.length, dateHist.length
            );

            const cashNote = document.getElementById('cashAmountNote');
            if (count === 0) {
                container.innerHTML = '<span style="color:#9ca3af;font-size:13px;">No payment history available.</span>';
                if (cashNote) cashNote.textContent = '';
                return;
            }

            const ordinals = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
            const allMethods = [
                { key: 'cash', label: 'Cash' },
                { key: 'gcash', label: 'G-cash' },
                { key: 'maya', label: 'Maya' },
                { key: 'instapay', label: 'Instapay' },
                { key: 'ob', label: 'Online Banking' },
                { key: 'airbnb', label: 'Airbnb' },
            ];
            const histArrays = { cash: cashHist, gcash: gcashHist, maya: mayaHist, instapay: instHist, ob: obHist, airbnb: airHist };

            let overallAmount = 0;
            for (let j = 0; j < count; j++) {
                overallAmount += (cashHist[j] || 0) + (gcashHist[j] || 0) + (mayaHist[j] || 0)
                    + (instHist[j] || 0) + (obHist[j] || 0) + (airHist[j] || 0);
            }
            const overallAmountDisplay = '₱' + overallAmount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const discountPerPayment = getDiscountAmountsPerPayment(booking, count, dateHist);
            const allPaymentTotals = [];
            for (let j = 0; j < count; j++) {
                allPaymentTotals.push(
                    (cashHist[j] || 0) + (gcashHist[j] || 0) + (mayaHist[j] || 0)
                    + (instHist[j] || 0) + (obHist[j] || 0) + (airHist[j] || 0)
                );
            }

            const payHistSectionStyle = 'border:1px solid #d1d5db;border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:12px;background:#fafafa;';

            let html = '<div class="payment-history-wrapper" style="display:flex;flex-direction:column;gap:16px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:8px;padding:16px;">';

            for (let i = 0; i < count; i++) {
                const ordinal = ordinals[i] || `#${i + 1}`;
                const dateStr = dateHist[i] || '';

                const paymentTotal = (cashHist[i] || 0) + (gcashHist[i] || 0) + (mayaHist[i] || 0)
                    + (instHist[i] || 0) + (obHist[i] || 0) + (airHist[i] || 0);
                const paymentDiscount = discountPerPayment[i] || 0;
                const hasMatchedDiscount = paymentHasMatchedDiscountHistory(booking, i, dateHist, paymentDiscount, count);
                const paymentForLines = buildPaymentForLines(booking, i, count, paymentTotal, paymentDiscount, allPaymentTotals);
                const paymentForHtml = renderPaymentForBreakdownHtml(paymentForLines, paymentTotal);
                const discountDisplayVal = paymentDiscount > 0 ? paymentDiscount.toFixed(2) : '';
                const discountCountVal = hasMatchedDiscount ? (booking.sc_pwd_count || '') : '';

                const addData = getPerPaymentAdditionalsForDisplay(booking, i, allPaymentTotals);
                const addGuestVal = addData.guests;
                const addPetVal = addData.pets;

                const gcashRefVal = getPaymentMethodRefForIndex(booking, 'gcash', i, histArrays);
                const mayaRefVal = getPaymentMethodRefForIndex(booking, 'maya', i, histArrays);
                const instapayRefVal = getPaymentMethodRefForIndex(booking, 'instapay', i, histArrays);
                const obRefVal = getPaymentMethodRefForIndex(booking, 'ob', i, histArrays);
                const airbnbRefVal = getPaymentMethodRefForIndex(booking, 'airbnb', i, histArrays);

                // Convert to datetime-local format YYYY-MM-DDTHH:MM
                let dtVal = '';
                if (dateStr) {
                    const d = new Date(dateStr.replace(' ', 'T'));
                    if (!isNaN(d)) {
                        dtVal = d.getFullYear() + '-'
                            + String(d.getMonth() + 1).padStart(2, '0') + '-'
                            + String(d.getDate()).padStart(2, '0') + 'T'
                            + String(d.getHours()).padStart(2, '0') + ':'
                            + String(d.getMinutes()).padStart(2, '0');
                    }
                }

                html += `
                <div class="payment-history-card" id="payHist_card_${i}" data-payment-index="${i}" style="display:flex;flex-direction:column;gap:12px;font-family:'Poppins',sans-serif;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:14px;box-shadow:0 1px 2px rgba(15,23,42,0.04);">
                    <!-- Payment Title + Unlock -->
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                        <span class="detail-label" style="font-size:13px;font-weight:600;color:#111827;text-transform:uppercase;letter-spacing:0.02em;margin:0;">${ordinal} Payment</span>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-family:'Poppins',sans-serif;font-size:12px;font-weight:500;color:#6b7280;white-space:nowrap;">
                            <input type="checkbox" id="payHist_unlock_${i}" onchange="togglePayHistLock(${i})" style="cursor:pointer;width:16px;height:16px;">
                            <span id="payHist_unlock_label_${i}">Locked</span>
                        </label>
                    </div>

                    <div id="payHist_editable_${i}" class="payHist-editable-section" style="display:flex;flex-direction:column;gap:12px;">

                    <!-- Payment For (Charges Breakdown) -->
                    <div class="detail-row" style="margin-bottom:0;display:grid;">
                        <div class="detail-item full-width" style="margin-bottom:0;">
                            <span class="detail-label" style="font-size:12px;">Charges Breakdown</span>
                            <div id="payHist_payment_for_${i}" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;margin-top:6px;">
                                ${paymentForHtml}
                            </div>
                        </div>
                    </div>

                    <!-- Duration / Promo / Breakfast -->
                    <div class="payHist-section-box" style="${payHistSectionStyle}">
                    <div class="detail-row" style="margin-bottom:0;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="detail-item" style="margin-bottom:0;">
                            <span class="detail-label">Duration</span>
                            <select class="detail-input" id="payHist_duration_${i}" onchange="updatePayHistMeta(${i})">
                                <option value="">—</option>
                            </select>
                        </div>
                        <div class="detail-item" style="margin-bottom:0;">
                            <span class="detail-label">Promo</span>
                            <select class="detail-input" id="payHist_promo_${i}" onchange="handlePayHistPromoChange(${i})">
                                <option value="">No Promo</option>
                            </select>
                        </div>
                    </div>

                    <div class="detail-row" style="margin-bottom:0;display:grid;">
                        <div class="detail-item full-width" style="margin-bottom:0;">
                            <span class="detail-label">Breakfast Promo</span>
                            <div id="payHist_breakfast_list_${i}" style="margin-top:6px;margin-bottom:8px;"></div>
                            <button type="button" id="payHist_breakfast_btn_${i}" onclick="addPayHistBreakfastDropdown(${i})"
                                style="width:100%;padding:8px 12px;background:#4CAF50;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;">
                                + Add Breakfast Promo
                            </button>
                            <input type="hidden" id="payHist_breakfast_json_${i}" value="">
                        </div>
                    </div>
                    </div>

                    <!-- Extension -->
                    <div class="payHist-section-box" style="${payHistSectionStyle}">
                    <div class="detail-row" style="margin-bottom:0;display:grid;">
                    </div>
                    <div class="detail-row" style="margin-bottom:0;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="detail-item" style="margin-bottom:0;">
                            <span class="detail-label">Extend Hours</span>
                            <input type="number" min="0" class="detail-input" id="payHist_extend_hours_${i}"
                                placeholder="0" oninput="updatePayHistExtend(${i})">
                        </div>
                        <div class="detail-item" style="margin-bottom:0;">
                            <span class="detail-label">Extend Minutes</span>
                            <input type="number" min="0" max="59" class="detail-input" id="payHist_extend_minutes_${i}"
                                placeholder="0" oninput="updatePayHistExtend(${i})">
                        </div>
                    </div>
                    <div class="detail-row" style="margin-bottom:0;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="detail-item" style="margin-bottom:0;">
                            <span class="detail-label">Extend Regular Rate</span>
                            <input type="number" step="0.01" min="0" class="detail-input" id="payHist_extend_regular_${i}"
                                placeholder="0.00" oninput="updatePayHistExtend(${i})">
                        </div>
                        <div class="detail-item" style="margin-bottom:0;">
                            <span class="detail-label">Extend Bundle Rate</span>
                            <input type="number" step="0.01" min="0" class="detail-input" id="payHist_extend_bundle_${i}"
                                placeholder="0.00" oninput="updatePayHistExtend(${i})">
                        </div>
                    </div>
                    <div class="detail-row" style="margin-bottom:0;display:grid;">
                        <div class="detail-item full-width" style="margin-bottom:0;">
                            <span class="detail-label">Extend Bundle Breakfast </span>
                            <div id="payHist_ext_breakfast_list_${i}" style="margin-top:6px;margin-bottom:8px;"></div>
                            <button type="button" id="payHist_ext_breakfast_btn_${i}" onclick="addPayHistExtendBreakfastDropdown(${i})"
                                style="width:100%;padding:8px 12px;background:#4CAF50;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;">
                                + Add Extend Breakfast
                            </button>
                            <input type="hidden" id="payHist_ext_breakfast_json_${i}" value="">
                        </div>
                    </div>
                    </div>

                    <!-- Discount Details -->
                    <div class="payHist-section-box" style="${payHistSectionStyle}">
                    <div class="detail-row" id="payHist_discount_main_${i}" style="margin-bottom:0;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div class="detail-item" style="margin-bottom:0;">
                            <span class="detail-label">Discount Amount</span>
                            <input type="number" step="0.01" min="0" class="detail-input" id="payHist_discount_amt_${i}"
                                value="${discountDisplayVal}" placeholder="Enter discount amount"
                                oninput="updatePayHistDiscountBreakdown(${i})">
                        </div>
                        <div class="detail-item" id="payHist_discount_id_col_${i}" style="margin-bottom:0;"></div>
                        <div class="detail-item" style="margin-bottom:0;">
                            <span class="detail-label">Discount Count</span>
                            <input type="number" min="0" max="3" class="detail-input" id="payHist_discount_count_${i}"
                                value="${discountCountVal}" placeholder="0"
                                oninput="updatePayHistDiscountIdFields(${i}); syncModalDiscountFromPayments();">
                        </div>
                    </div>
                    <div id="payHist_discount_ids_extra_${i}" class="detail-row" style="display:none;margin-bottom:0;"></div>
                    </div>

                    <!-- Additional Guest / Pet / Food -->
                    <div class="payHist-section-box" style="${payHistSectionStyle}">
                    <div class="detail-row" style="margin-bottom:0;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="detail-item" style="margin-bottom:0;">
                            <span class="detail-label">Additional Guest</span>
                            <input type="number" min="0" class="detail-input" id="payHist_add_guest_${i}"
                                value="${addGuestVal}" data-original-value="${addGuestVal}" placeholder="0"
                                oninput="updatePayHistAdditionals(${i})">
                        </div>
                        <div class="detail-item" style="margin-bottom:0;">
                            <span class="detail-label">Additional Pet</span>
                            <input type="number" min="0" class="detail-input" id="payHist_add_pet_${i}"
                                value="${addPetVal}" data-original-value="${addPetVal}" placeholder="0"
                                oninput="updatePayHistAdditionals(${i})">
                        </div>
                    </div>

                    <div class="detail-row" style="margin-bottom:0;display:grid;">
                        <div class="detail-item full-width" style="margin-bottom:0;">
                            <span class="detail-label">Additional Food/Item</span>
                            <div id="payHist_add_charges_list_${i}" style="margin-top:6px;margin-bottom:8px;"></div>
                            <button type="button" id="payHist_add_charges_btn_${i}" onclick="addPayHistAdditionalItem(${i})"
                                style="width:100%;padding:8px 12px;background:#4CAF50;color:white;border:none;border-radius:6px;font-size:13px;font-weight:600;">
                                + Add Additional
                            </button>
                            <input type="hidden" id="payHist_add_charges_json_${i}" value="[]">
                        </div>
                    </div>
                    </div>

                    <!-- Payment Method / Amount / Date -->
                    <div class="payHist-section-box" style="${payHistSectionStyle}">
                    <div class="detail-row" style="margin-bottom:0;display:grid;">
                        <div class="detail-item full-width" style="margin-bottom:0;">
                            <span class="detail-label" style="font-size:12px;">Payment Method</span>
                            <div style="margin-top:6px;">
                                <div style="display:flex;flex-wrap:wrap;gap:15px;">
                                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 400; color: #374151;">
                                        <input type="checkbox" id="payHist_cash_chk_${i}" ${cashHist[i] > 0 ? 'checked' : ''} onchange="togglePayHistAmount('cash', ${i})" style="cursor: pointer; width: 16px; height: 16px;">
                                        <span>Cash</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 400; color: #374151;">
                                        <input type="checkbox" id="payHist_gcash_chk_${i}" ${gcashHist[i] > 0 ? 'checked' : ''} onchange="togglePayHistAmount('gcash', ${i})" style="cursor: pointer; width: 16px; height: 16px;">
                                        <span>G-cash</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 400; color: #374151;">
                                        <input type="checkbox" id="payHist_maya_chk_${i}" ${mayaHist[i] > 0 ? 'checked' : ''} onchange="togglePayHistAmount('maya', ${i})" style="cursor: pointer; width: 16px; height: 16px;">
                                        <span>Maya</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 400; color: #374151;">
                                        <input type="checkbox" id="payHist_instapay_chk_${i}" ${instHist[i] > 0 ? 'checked' : ''} onchange="togglePayHistAmount('instapay', ${i})" style="cursor: pointer; width: 16px; height: 16px;">
                                        <span>Instapay</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 400; color: #374151;">
                                        <input type="checkbox" id="payHist_ob_chk_${i}" ${obHist[i] > 0 ? 'checked' : ''} onchange="togglePayHistAmount('ob', ${i})" style="cursor: pointer; width: 16px; height: 16px;">
                                        <span>Online Banking</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 400; color: #374151;">
                                        <input type="checkbox" id="payHist_airbnb_chk_${i}" ${airHist[i] > 0 ? 'checked' : ''} onchange="togglePayHistAmount('airbnb', ${i})" style="cursor: pointer; width: 16px; height: 16px;">
                                        <span>Airbnb</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Amount Inputs -->
                    <div class="detail-row" id="payHist_cash_row_${i}" style="display: ${cashHist[i] > 0 ? 'grid' : 'none'}; margin-bottom: 0;">
                        <div class="detail-item full-width" style="margin-bottom: 0;">
                            <span class="detail-label">Cash Amount</span>
                            <input type="number" step="0.01" min="0" class="detail-input" id="payHist_cash_amt_${i}" value="${cashHist[i] > 0 ? cashHist[i].toFixed(2) : ''}" placeholder="Enter cash amount" oninput="updatePayHistTotal(${i})" ${cashHist[i] > 0 ? '' : 'disabled'}>
                        </div>
                    </div>

                    <div class="detail-row" id="payHist_gcash_row_${i}" style="display: ${gcashHist[i] > 0 ? 'grid' : 'none'}; margin-bottom: 0;">
                        <div class="detail-item" style="margin-bottom: 0;">
                            <span class="detail-label">G-cash Amount</span>
                            <input type="number" step="0.01" min="0" class="detail-input" id="payHist_gcash_amt_${i}" value="${gcashHist[i] > 0 ? gcashHist[i].toFixed(2) : ''}" placeholder="Enter G-cash amount" oninput="updatePayHistTotal(${i})" ${gcashHist[i] > 0 ? '' : 'disabled'}>
                        </div>
                        <div class="detail-item" style="margin-bottom: 0;">
                            <span class="detail-label">G-cash Reference No.</span>
                            <input type="text" class="detail-input" id="payHist_gcash_ref_${i}" value="${gcashRefVal}" placeholder="Enter G-cash reference number" ${gcashHist[i] > 0 ? '' : 'disabled'}>
                        </div>
                    </div>

                    <div class="detail-row" id="payHist_maya_row_${i}" style="display: ${mayaHist[i] > 0 ? 'grid' : 'none'}; margin-bottom: 0;">
                        <div class="detail-item" style="margin-bottom: 0;">
                            <span class="detail-label">Maya Amount</span>
                            <input type="number" step="0.01" min="0" class="detail-input" id="payHist_maya_amt_${i}" value="${mayaHist[i] > 0 ? mayaHist[i].toFixed(2) : ''}" placeholder="Enter Maya amount" oninput="updatePayHistTotal(${i})" ${mayaHist[i] > 0 ? '' : 'disabled'}>
                        </div>
                        <div class="detail-item" style="margin-bottom: 0;">
                            <span class="detail-label">Maya Reference No.</span>
                            <input type="text" class="detail-input" id="payHist_maya_ref_${i}" value="${mayaRefVal}" placeholder="Enter Maya reference number" ${mayaHist[i] > 0 ? '' : 'disabled'}>
                        </div>
                    </div>

                    <div class="detail-row" id="payHist_instapay_row_${i}" style="display: ${instHist[i] > 0 ? 'grid' : 'none'}; margin-bottom: 0;">
                        <div class="detail-item" style="margin-bottom: 0;">
                            <span class="detail-label">Instapay Amount</span>
                            <input type="number" step="0.01" min="0" class="detail-input" id="payHist_instapay_amt_${i}" value="${instHist[i] > 0 ? instHist[i].toFixed(2) : ''}" placeholder="Enter Instapay amount" oninput="updatePayHistTotal(${i})" ${instHist[i] > 0 ? '' : 'disabled'}>
                        </div>
                        <div class="detail-item" style="margin-bottom: 0;">
                            <span class="detail-label">Instapay Reference No.</span>
                            <input type="text" class="detail-input" id="payHist_instapay_ref_${i}" value="${instapayRefVal}" placeholder="Enter Instapay reference number" ${instHist[i] > 0 ? '' : 'disabled'}>
                        </div>
                    </div>

                    <div class="detail-row" id="payHist_ob_row_${i}" style="display: ${obHist[i] > 0 ? 'grid' : 'none'}; margin-bottom: 0;">
                        <div class="detail-item" style="margin-bottom: 0;">
                            <span class="detail-label">Online Banking Amount</span>
                            <input type="number" step="0.01" min="0" class="detail-input" id="payHist_ob_amt_${i}" value="${obHist[i] > 0 ? obHist[i].toFixed(2) : ''}" placeholder="Enter Online Banking amount" oninput="updatePayHistTotal(${i})" ${obHist[i] > 0 ? '' : 'disabled'}>
                        </div>
                        <div class="detail-item" style="margin-bottom: 0;">
                            <span class="detail-label">Online Banking Reference No.</span>
                            <input type="text" class="detail-input" id="payHist_ob_ref_${i}" value="${obRefVal}" placeholder="Enter Online Banking reference number" ${obHist[i] > 0 ? '' : 'disabled'}>
                        </div>
                    </div>

                    <div class="detail-row" id="payHist_airbnb_row_${i}" style="display: ${airHist[i] > 0 ? 'grid' : 'none'}; margin-bottom: 0;">
                        <div class="detail-item" style="margin-bottom: 0;">
                            <span class="detail-label">Airbnb Amount</span>
                            <input type="number" step="0.01" min="0" class="detail-input" id="payHist_airbnb_amt_${i}" value="${airHist[i] > 0 ? airHist[i].toFixed(2) : ''}" placeholder="Enter Airbnb amount" oninput="updatePayHistTotal(${i})" ${airHist[i] > 0 ? '' : 'disabled'}>
                        </div>
                        <div class="detail-item" style="margin-bottom: 0;">
                            <span class="detail-label">Airbnb Reference No.</span>
                            <input type="text" class="detail-input" id="payHist_airbnb_ref_${i}" value="${airbnbRefVal}" placeholder="Enter Airbnb reference number" ${airHist[i] > 0 ? '' : 'disabled'}>
                        </div>
                    </div>

                    <div class="detail-row" style="margin-bottom: 0; display: grid;">
                        <div class="detail-item full-width" style="margin-bottom: 0;">
                            <span class="detail-label">Payment Date &amp; Time</span>
                            <input type="datetime-local" class="detail-input" id="payHist_datetime_${i}" value="${dtVal}"
                                onchange="refreshPayHistDiscountPlacement()">
                        </div>
                    </div>
                    </div>

                    ${i === count - 1 ? `
                    <div class="detail-row" style="margin-bottom: 0; display: grid;">
                        <div class="detail-item full-width" style="margin-bottom: 0;">
                            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;margin-top:6px;">
                                <div style="display:flex;justify-content:space-between;align-items:center;padding-top:0;font-size:13px;">
                                    <span style="font-weight:600;color:#374151;">Overall Amount</span>
                                    <span id="payHist_overall_amount" style="font-weight:700;color:#111827;">${overallAmountDisplay}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    ` : ''}

                    </div><!-- /payHist_editable -->
                </div>`;
            }

            html += '</div>';
            container.innerHTML = html;

            // Build discount ID fields, food/item dropdowns, and lock all payment cards
            payHistAdditionalCharges = {};
            payHistBreakfasts = {};
            payHistExtendBreakfasts = {};
            for (let i = 0; i < count; i++) {
                const pd = discountPerPayment[i] || 0;
                const hasMatched = paymentHasMatchedDiscountHistory(booking, i, dateHist, pd, count);
                const idList = hasMatched ? parseDiscountIdList(booking.id_number) : [];
                updatePayHistDiscountIdFields(i, idList);
                populatePayHistMetaSelects(i, booking, count);
                initPayHistBreakfast(booking, i, count);
                initPayHistExtendFields(booking, i, count);
                initPayHistAdditionalCharges(booking, i, allPaymentTotals);
                setPaymentCardLocked(i, true);
            }
            refreshPayHistPaymentForBreakdowns();

            // Update cash amount note
            if (cashNote) {
                cashNote.textContent = count > 1
                    ? `(total of ${count} payments — see Payment Method History below)`
                    : '';
            }
        }

        function isPaymentCardLocked(idx) {
            const unlockChk = document.getElementById(`payHist_unlock_${idx}`);
            if (!unlockChk) return false;
            return !unlockChk.checked;
        }

        function setPaymentCardLocked(idx, locked) {
            const methods = ['cash', 'gcash', 'maya', 'instapay', 'ob', 'airbnb'];
            const card = document.getElementById(`payHist_card_${idx}`);
            const label = document.getElementById(`payHist_unlock_label_${idx}`);

            methods.forEach(m => {
                const chk = document.getElementById(`payHist_${m}_chk_${idx}`);
                const amt = document.getElementById(`payHist_${m}_amt_${idx}`);
                const ref = document.getElementById(`payHist_${m}_ref_${idx}`);

                if (chk) chk.disabled = locked;
                if (amt) amt.disabled = locked || !(chk && chk.checked);
                if (ref) ref.disabled = locked || !(chk && chk.checked);
            });

            const dt = document.getElementById(`payHist_datetime_${idx}`);
            if (dt) dt.disabled = locked;

            const disc = document.getElementById(`payHist_discount_amt_${idx}`);
            if (disc) disc.disabled = locked;

            const discCount = document.getElementById(`payHist_discount_count_${idx}`);
            if (discCount) discCount.disabled = locked;

            document.querySelectorAll(`[id^="payHist_discount_id_${idx}_"]`).forEach(el => {
                el.disabled = locked;
            });

            const addGuest = document.getElementById(`payHist_add_guest_${idx}`);
            if (addGuest) addGuest.disabled = locked;

            const addPet = document.getElementById(`payHist_add_pet_${idx}`);
            if (addPet) addPet.disabled = locked;

            const durSel = document.getElementById(`payHist_duration_${idx}`);
            if (durSel) durSel.disabled = locked;

            const promoSel = document.getElementById(`payHist_promo_${idx}`);
            if (promoSel) promoSel.disabled = locked;

            ['payHist_extend_hours_', 'payHist_extend_minutes_', 'payHist_extend_regular_', 'payHist_extend_bundle_'].forEach(prefix => {
                const el = document.getElementById(`${prefix}${idx}`);
                if (el) el.disabled = locked;
            });

            renderPayHistBreakfast(idx);
            renderPayHistExtendBreakfast(idx);
            renderPayHistAdditionalItems(idx);

            if (card) {
                card.style.borderColor = locked ? '#e5e7eb' : '#408D69';
            }
            if (label) {
                label.textContent = locked ? 'Locked' : 'Unlocked';
                label.style.color = locked ? '#6b7280' : '#408D69';
            }
        }

        function togglePayHistLock(idx) {
            setPaymentCardLocked(idx, isPaymentCardLocked(idx));
        }

        /** Toggle amount input when a method checkbox is clicked */
        function togglePayHistAmount(method, idx) {
            if (isPaymentCardLocked(idx)) {
                const chk = document.getElementById(`payHist_${method}_chk_${idx}`);
                if (chk) chk.checked = !chk.checked;
                return;
            }

            const chk = document.getElementById(`payHist_${method}_chk_${idx}`);
            const row = document.getElementById(`payHist_${method}_row_${idx}`);
            const amt = document.getElementById(`payHist_${method}_amt_${idx}`);
            const ref = document.getElementById(`payHist_${method}_ref_${idx}`);
            if (!chk || !row || !amt) return;

            row.style.display = chk.checked ? 'grid' : 'none';
            amt.disabled = !chk.checked;
            if (ref) ref.disabled = !chk.checked;
            if (!chk.checked) {
                amt.value = '';
                if (ref) ref.value = '';
            }
            updatePayHistTotal(idx);
        }

        function formatPayHistDateTime(dtEl) {
            if (!dtEl || !dtEl.value) return '';
            const d = new Date(dtEl.value);
            if (isNaN(d)) return '';
            return d.getFullYear() + '-'
                + String(d.getMonth() + 1).padStart(2, '0') + '-'
                + String(d.getDate()).padStart(2, '0') + ' '
                + String(d.getHours()).padStart(2, '0') + ':'
                + String(d.getMinutes()).padStart(2, '0') + ':00';
        }

        function getPayHistPaymentTotal(idx) {
            const methods = ['cash', 'gcash', 'maya', 'instapay', 'ob', 'airbnb'];
            let total = 0;
            methods.forEach(m => {
                const chk = document.getElementById(`payHist_${m}_chk_${idx}`);
                const amt = document.getElementById(`payHist_${m}_amt_${idx}`);
                if (chk && chk.checked && amt) total += parseFloat(amt.value) || 0;
            });
            return total;
        }

        function parseDiscountIdList(val) {
            if (!val || !String(val).trim()) return [];
            return String(val).split(',').map(s => s.trim()).filter(Boolean);
        }

        function collectDiscountIdsForPayment(idx) {
            const count = parseInt(document.getElementById(`payHist_discount_count_${idx}`)?.value, 10) || 0;
            const ids = [];
            const fieldCount = count <= 1 ? 1 : Math.min(3, count);

            for (let j = 1; j <= fieldCount; j++) {
                const el = document.getElementById(`payHist_discount_id_${idx}_${j}`);
                if (el && el.value.trim()) ids.push(el.value.trim());
            }
            return ids.join(', ');
        }

        function updatePayHistDiscountIdFields(idx, initialIdList) {
            const countEl = document.getElementById(`payHist_discount_count_${idx}`);
            const mainRow = document.getElementById(`payHist_discount_main_${idx}`);
            const idCol = document.getElementById(`payHist_discount_id_col_${idx}`);
            const extraWrap = document.getElementById(`payHist_discount_ids_extra_${idx}`);
            if (!countEl || !mainRow || !idCol || !extraWrap) return;

            const count = Math.min(3, Math.max(0, parseInt(countEl.value, 10) || 0));
            const preserved = initialIdList !== undefined
                ? (Array.isArray(initialIdList) ? initialIdList : parseDiscountIdList(initialIdList))
                : parseDiscountIdList(collectDiscountIdsForPayment(idx));

            const esc = (v) => String(v || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

            if (count <= 1) {
                mainRow.style.gridTemplateColumns = '1fr 1fr 1fr';
                idCol.style.display = '';
                idCol.innerHTML = `
                    <span class="detail-label">Discount ID</span>
                    <input type="text" class="detail-input" id="payHist_discount_id_${idx}_1"
                        value="${esc(preserved[0])}" placeholder="Enter ID number"
                        oninput="syncModalDiscountFromPayments()">`;
                extraWrap.style.display = 'none';
                extraWrap.innerHTML = '';
            } else if (count === 2 || count === 3) {
                mainRow.style.gridTemplateColumns = '1fr 1fr';
                idCol.style.display = 'none';
                idCol.innerHTML = '';
                extraWrap.style.display = 'grid';
                extraWrap.style.gridTemplateColumns = `repeat(${count}, 1fr)`;
                extraWrap.style.gap = '12px';
                extraWrap.innerHTML = Array.from({ length: count }, (_, j) => {
                    const n = j + 1;
                    return `
                    <div class="detail-item" style="margin-bottom:0;">
                        <span class="detail-label">Discount ID ${n}</span>
                        <input type="text" class="detail-input" id="payHist_discount_id_${idx}_${n}"
                            value="${esc(preserved[j])}" placeholder="Enter ID number ${n}"
                            oninput="syncModalDiscountFromPayments()">
                    </div>`;
                }).join('');
            } else {
                mainRow.style.gridTemplateColumns = '1fr 1fr 1fr';
                idCol.style.display = '';
                idCol.innerHTML = `
                    <span class="detail-label">Discount ID</span>
                    <input type="text" class="detail-input" id="payHist_discount_id_${idx}_1"
                        value="" placeholder="Enter ID number"
                        oninput="syncModalDiscountFromPayments()">`;
                extraWrap.style.display = 'none';
                extraWrap.innerHTML = '';
            }

            if (isPaymentCardLocked(idx)) {
                setPaymentCardLocked(idx, true);
            }
        }

        function collectPaymentAdditionals() {
            let totalGuest = 0;
            let totalPet = 0;
            const allCharges = [];
            let i = 0;

            while (document.getElementById(`payHist_add_guest_${i}`) !== null) {
                totalGuest += parseInt(document.getElementById(`payHist_add_guest_${i}`).value, 10) || 0;
                totalPet += parseInt(document.getElementById(`payHist_add_pet_${i}`).value, 10) || 0;

                const jsonEl = document.getElementById(`payHist_add_charges_json_${i}`);
                if (jsonEl && jsonEl.value) {
                    try {
                        const parsed = JSON.parse(jsonEl.value);
                        if (Array.isArray(parsed)) allCharges.push(...parsed);
                    } catch (e) { /* ignore */ }
                }
                i++;
            }

            return {
                additional_guest: totalGuest,
                additional_pet: totalPet,
                additional_data: JSON.stringify(allCharges)
            };
        }

        function syncModalAdditionalsFromPayments() {
            const data = collectPaymentAdditionals();
            const guestEl = document.getElementById('modalAdditionalGuest');
            const petEl = document.getElementById('modalAdditionalPet');
            const chargesEl = document.getElementById('modalAdditionalCharges');

            if (guestEl) guestEl.value = data.additional_guest;
            if (petEl) petEl.value = data.additional_pet;
            if (chargesEl) {
                chargesEl.value = data.additional_data;
                try {
                    modalAdditionalCharges = JSON.parse(data.additional_data || '[]');
                    if (typeof renderModalAdditionalItems === 'function') {
                        renderModalAdditionalItems();
                    }
                } catch (e) { /* ignore */ }
            }
        }

        function updatePayHistAdditionals(idx) {
            const booking = window.currentBooking;
            const container = document.getElementById(`payHist_payment_for_${idx}`);
            if (!booking || !container) return;

            const allPaymentTotals = collectPayHistPaymentTotals();
            const paymentTotal = allPaymentTotals[idx] || 0;
            const count = allPaymentTotals.length;
            const discEl = document.getElementById(`payHist_discount_amt_${idx}`);
            const paymentDiscount = discEl ? (parseFloat(discEl.value) || 0) : 0;

            const lines = buildPaymentForLines(booking, idx, count, paymentTotal, paymentDiscount, allPaymentTotals);
            container.innerHTML = renderPaymentForBreakdownHtml(lines, paymentTotal);
            syncModalAdditionalsFromPayments();
        }

        function updatePayHistDiscountBreakdown(idx) {
            const booking = window.currentBooking;
            const container = document.getElementById(`payHist_payment_for_${idx}`);
            const discEl = document.getElementById(`payHist_discount_amt_${idx}`);
            if (!booking || !container || !discEl) return;

            const paymentDiscount = parseFloat(discEl.value) || 0;
            const allPaymentTotals = collectPayHistPaymentTotals();
            const paymentTotal = allPaymentTotals[idx] || 0;
            const count = allPaymentTotals.length;

            const lines = buildPaymentForLines(booking, idx, count, paymentTotal, paymentDiscount, allPaymentTotals);
            container.innerHTML = renderPaymentForBreakdownHtml(lines, paymentTotal);
            syncModalDiscountFromPayments();
        }

        function syncModalDiscountFromPayments() {
            let total = 0;
            let totalCount = 0;
            const allIds = [];
            let i = 0;

            while (document.getElementById(`payHist_discount_amt_${i}`) !== null) {
                const amt = parseFloat(document.getElementById(`payHist_discount_amt_${i}`).value) || 0;
                const count = parseInt(document.getElementById(`payHist_discount_count_${i}`)?.value, 10) || 0;
                const idStr = collectDiscountIdsForPayment(i);

                if (amt > 0) {
                    total += amt;
                    totalCount += count;
                    parseDiscountIdList(idStr).forEach(id => {
                        if (!allIds.includes(id)) allIds.push(id);
                    });
                }
                i++;
            }

            const discountId = allIds.join(', ');

            const modalDisc = document.getElementById('modalDiscountAmount');
            if (modalDisc) modalDisc.value = total > 0 ? total.toFixed(2) : '';

            const modalCount = document.getElementById('modalDiscountCount');
            if (modalCount) modalCount.value = totalCount > 0 ? totalCount : '';

            const modalId = document.getElementById('modalDiscountId');
            if (modalId) modalId.value = discountId;
        }

        function refreshPayHistPaymentForBreakdowns() {
            const booking = window.currentBooking;
            if (!booking) return;

            const allPaymentTotals = collectPayHistPaymentTotals();
            const count = allPaymentTotals.length;
            if (count === 0) return;

            const dateHist = collectPayHistDateTimes();
            const discountPerPayment = getDiscountAmountsPerPayment(booking, count, dateHist);

            for (let i = 0; i < count; i++) {
                const container = document.getElementById(`payHist_payment_for_${i}`);
                if (!container) continue;

                const paymentTotal = allPaymentTotals[i] || 0;
                const discEl = document.getElementById(`payHist_discount_amt_${i}`);
                const paymentDiscount = discEl
                    ? (parseFloat(discEl.value) || 0)
                    : (discountPerPayment[i] || 0);

                const lines = buildPaymentForLines(booking, i, count, paymentTotal, paymentDiscount, allPaymentTotals);
                container.innerHTML = renderPaymentForBreakdownHtml(lines, paymentTotal);
            }
        }

        /** Recalculate and display total for a payment entry */
        function updatePayHistTotal(idx) {
            const methods = ['cash', 'gcash', 'maya', 'instapay', 'ob', 'airbnb'];
            let total = 0;
            methods.forEach(m => {
                const chk = document.getElementById(`payHist_${m}_chk_${idx}`);
                const amt = document.getElementById(`payHist_${m}_amt_${idx}`);
                if (chk && chk.checked && amt) total += parseFloat(amt.value) || 0;
            });
            const el = document.getElementById(`payHist_total_${idx}`);
            if (el) el.textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            updatePayHistOverallAmount();
            refreshPayHistPaymentForBreakdowns();
        }

        /** Sum all payment entries and update Overall Amount on the last payment card */
        function updatePayHistOverallAmount() {
            const methods = ['cash', 'gcash', 'maya', 'instapay', 'ob', 'airbnb'];
            const el = document.getElementById('payHist_overall_amount');
            if (!el) return;

            let overall = 0;
            let i = 0;
            while (document.getElementById(`payHist_${methods[0]}_chk_${i}`) !== null) {
                methods.forEach(m => {
                    const chk = document.getElementById(`payHist_${m}_chk_${i}`);
                    const amt = document.getElementById(`payHist_${m}_amt_${i}`);
                    if (chk && chk.checked && amt) {
                        overall += parseFloat(amt.value) || 0;
                    }
                });
                i++;
            }

            el.textContent = '₱' + overall.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        /** Collect all editable payment history entries into pipe-separated strings */
        function collectPaymentHistory() {
            const methods = ['cash', 'gcash', 'maya', 'instapay', 'ob', 'airbnb'];
            const dbKeys = [
                'payment_amount_cash_history',
                'payment_amount_g_cash_history',
                'payment_amount_maya_history',
                'payment_amount_instapay_history',
                'payment_amount_online_banking_history',
                'payment_amount_airbnb_history'
            ];
            const arrs = methods.map(() => []);
            const dateArr = [];
            let i = 0;

            while (document.getElementById(`payHist_${methods[0]}_chk_${i}`) !== null) {
                methods.forEach((m, mi) => {
                    const chk = document.getElementById(`payHist_${m}_chk_${i}`);
                    const amt = document.getElementById(`payHist_${m}_amt_${i}`);
                    const val = (chk && chk.checked && amt) ? (parseFloat(amt.value) || 0) : 0;
                    arrs[mi].push(val.toFixed(2));
                });

                const dtEl = document.getElementById(`payHist_datetime_${i}`);
                if (dtEl && dtEl.value) {
                    const d = new Date(dtEl.value);
                    const fmt = d.getFullYear() + '-'
                        + String(d.getMonth() + 1).padStart(2, '0') + '-'
                        + String(d.getDate()).padStart(2, '0') + ' '
                        + String(d.getHours()).padStart(2, '0') + ':'
                        + String(d.getMinutes()).padStart(2, '0') + ':00';
                    dateArr.push(fmt);
                } else {
                    dateArr.push('');
                }
                i++;
            }

            if (i === 0) return {};  // no entries

            const result = { payment_date_time: dateArr.join('|') };
            methods.forEach((m, mi) => { result[dbKeys[mi]] = arrs[mi].join('|'); });
            return result;
        }

        /** Collect per-payment discount amounts into total + timestamped history. */
        function collectPaymentDiscounts() {
            const entries = [];
            let total = 0;
            let totalCount = 0;
            let i = 0;
            const allIds = [];

            while (document.getElementById(`payHist_discount_amt_${i}`) !== null) {
                const discEl = document.getElementById(`payHist_discount_amt_${i}`);
                const countEl = document.getElementById(`payHist_discount_count_${i}`);
                const dtEl = document.getElementById(`payHist_datetime_${i}`);
                const amt = parseFloat(discEl?.value) || 0;

                if (amt > 0) {
                    total += amt;
                    totalCount += parseInt(countEl?.value, 10) || 0;
                    parseDiscountIdList(collectDiscountIdsForPayment(i)).forEach(id => {
                        if (!allIds.includes(id)) allIds.push(id);
                    });

                    let dtFmt = formatPayHistDateTime(dtEl);
                    if (!dtFmt) dtFmt = new Date().toISOString().slice(0, 19).replace('T', ' ');
                    entries.push(amt.toFixed(2) + ':' + dtFmt);
                }
                i++;
            }

            return {
                discount_amount: total.toFixed(2),
                discount_amount_history: entries.length > 0 ? entries.join('|') : '',
                discount_count: totalCount,
                discount_id: allIds.join(', ')
            };
        }

        /** Collect reference numbers per payment method (comma-separated, aligned to payment order). */
        function collectPaymentReferences() {
            const refMethods = [
                { key: 'gcash', apiKey: 'gcash_reference', depositKey: 'deposit_gcash_ref' },
                { key: 'maya', apiKey: 'maya_reference', depositKey: null },
                { key: 'instapay', apiKey: 'instapay_reference', depositKey: 'deposit_instapay_ref' },
                { key: 'ob', apiKey: 'online_banking_reference', depositKey: 'deposit_online_banking_ref' },
                { key: 'airbnb', apiKey: 'airbnb_reference', depositKey: 'deposit_airbnb_ref' }
            ];

            const result = {};
            refMethods.forEach(({ key, apiKey, depositKey }) => {
                const refs = [];
                let i = 0;

                while (document.getElementById(`payHist_${key}_chk_${i}`) !== null) {
                    const chk = document.getElementById(`payHist_${key}_chk_${i}`);
                    const refEl = document.getElementById(`payHist_${key}_ref_${i}`);
                    if (chk && chk.checked && refEl) {
                        const val = refEl.value.trim();
                        if (val) refs.push(val);
                    }
                    i++;
                }

                const joined = refs.join(', ');
                result[apiKey] = joined;
                if (depositKey) {
                    result[depositKey] = joined;
                }
            });

            return result;
        }


        function loadDurationOptions(roomType, roomId, currentDuration) {
            const durationSelect = document.getElementById('modalDuration');

            // Use the room type directly
            const cleanRoomType = roomType || 'Unknown';

            // Fetch duration pricing from the server
            fetch(`get_room_pricing.php?room_type=${encodeURIComponent(cleanRoomType)}&room_id=${encodeURIComponent(roomId)}`)
                .then(response => response.json())
                .then(data => {
                    durationSelect.innerHTML = '';

                    let promoOptionAdded = false;

                    if (data.success && data.pricing && data.pricing.length > 0) {
                        let durationFound = false;
                        data.pricing.forEach(price => {
                            const option = document.createElement('option');
                            option.value = price.hours;
                            option.textContent = `${price.hours} hours - ₱${price.price}`;
                            if (price.hours == currentDuration) {
                                option.selected = true;
                                durationFound = true;
                            }
                            durationSelect.appendChild(option);
                        });

                        // Append currentDuration if it's a non-standard value not in pricing table
                        if (!durationFound && currentDuration != 0 && currentDuration != '0') {
                            const customOption = document.createElement('option');
                            customOption.value = currentDuration;
                            customOption.textContent = `${currentDuration} hours (Custom)`;
                            customOption.selected = true;
                            durationSelect.appendChild(customOption);
                        }
                    } else if (currentDuration != 0 && currentDuration != '0') {
                        // Fallback to manual input if no pricing found and not promo
                        const option = document.createElement('option');
                        option.value = currentDuration;
                        option.textContent = currentDuration ? `${currentDuration} hours (Custom)` : 'No pricing available';
                        option.selected = true;
                        durationSelect.appendChild(option);
                    }

                    // Always guarantee a Promo option is available at the end
                    const optionPromo = document.createElement('option');
                    optionPromo.value = "0";
                    optionPromo.textContent = "Promo (0 hours)";
                    if (currentDuration == 0 || currentDuration == '0') {
                        optionPromo.selected = true;
                    }
                    durationSelect.appendChild(optionPromo);
                    promoOptionAdded = true;

                })
                .catch(error => {
                    console.error('Error loading duration options:', error);
                    durationSelect.innerHTML = `<option value="${currentDuration}">${currentDuration || 'Error loading options'}</option>`;
                });
        }

        function loadPromoOptions(currentPromo, roomType) {
            const promoSelect = document.getElementById('modalPromo');

            // Extract clean room type
            let cleanRoomType = '';
            if (roomType) {
                const parts = roomType.split(' ');
                // Get the first word which is usually the room type (Deluxe, Premium, Transient)
                cleanRoomType = parts[0].toLowerCase();
            }

            console.log('Filtering promos for room type:', cleanRoomType);

            // Fetch available promos from the server
            fetch('get_promos.php')
                .then(response => response.json())
                .then(data => {
                    promoSelect.innerHTML = '<option value="">No Promo</option>';

                    if (data.success && data.promos && data.promos.length > 0) {
                        // Filter promos by room type - match the first word
                        const filteredPromos = data.promos.filter(promo => {
                            const promoTitle = promo.title.toLowerCase();
                            // Check if promo title starts with or contains the room type
                            return promoTitle.includes(cleanRoomType);
                        });

                        console.log('Filtered promos:', filteredPromos);

                        let packageNumber = 1;
                        filteredPromos.forEach(promo => {
                            // Create separate options for 12hrs and 24hrs
                            if (promo.price_12hrs && parseFloat(promo.price_12hrs.replace(/,/g, '')) > 0) {
                                const option12 = document.createElement('option');
                                const textTitle = `Package ${packageNumber} 12hrs - ₱${promo.price_12hrs}`;
                                option12.value = textTitle;
                                option12.textContent = textTitle;

                                if (currentPromo && (currentPromo === textTitle || currentPromo.includes(textTitle))) {
                                    option12.selected = true;
                                }
                                promoSelect.appendChild(option12);
                                packageNumber++;
                            }

                            if (promo.price_24hrs && parseFloat(promo.price_24hrs.replace(/,/g, '')) > 0) {
                                const option24 = document.createElement('option');
                                const textTitle = `Package ${packageNumber} 24hrs - ₱${promo.price_24hrs}`;
                                option24.value = textTitle;
                                option24.textContent = textTitle;

                                if (currentPromo && (currentPromo === textTitle || currentPromo.includes(textTitle))) {
                                    option24.selected = true;
                                }
                                promoSelect.appendChild(option24);
                                packageNumber++;
                            }
                        });

                        // If no matching promos found, show all promos as fallback
                        if (filteredPromos.length === 0) {
                            console.log('No matching promos, showing all');
                            let pkgNum = 1;
                            data.promos.forEach(promo => {
                                if (promo.price_12hrs && parseFloat(promo.price_12hrs.replace(/,/g, '')) > 0) {
                                    const option12 = document.createElement('option');
                                    const textTitle = `Package ${pkgNum} 12hrs - ₱${promo.price_12hrs}`;
                                    option12.value = textTitle;
                                    option12.textContent = textTitle;

                                    if (currentPromo && (currentPromo === textTitle || currentPromo.includes(textTitle))) {
                                        option12.selected = true;
                                    }
                                    promoSelect.appendChild(option12);
                                    pkgNum++;
                                }

                                if (promo.price_24hrs && parseFloat(promo.price_24hrs.replace(/,/g, '')) > 0) {
                                    const option24 = document.createElement('option');
                                    const textTitle = `Package ${pkgNum} 24hrs - ₱${promo.price_24hrs}`;
                                    option24.value = textTitle;
                                    option24.textContent = textTitle;

                                    if (currentPromo && (currentPromo === textTitle || currentPromo.includes(textTitle))) {
                                        option24.selected = true;
                                    }
                                    promoSelect.appendChild(option24);
                                    pkgNum++;
                                }
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading promo options:', error);
                    promoSelect.innerHTML = `<option value="${currentPromo}">${currentPromo || 'No Promo'}</option>`;
                });
        }

        function handleModalPromoChange() {
            const promoSelect = document.getElementById('modalPromo');
            const durationSelect = document.getElementById('modalDuration');

            // If a Promo package is selected, force Duration to "Promo (0 hours)"
            if (promoSelect.value && promoSelect.value !== '') {
                durationSelect.value = "0";
            }
        }

        function handleGuestTypeChange() {
            const guestType = document.getElementById('modalGuestType').value;
            const companyFields = document.getElementById('companyFields');
            const guestNamesLabel = document.getElementById('guestNamesLabel');

            if (guestType === 'Company') {
                companyFields.style.display = 'block';
                guestNamesLabel.textContent = 'Company Name';
            } else {
                companyFields.style.display = 'none';
                guestNamesLabel.textContent = 'Guest Names';
            }
        }


        function handlePaymentCheckboxChange() {
            // Get checkbox states
            const checkCash = document.getElementById('checkCash').checked;
            const checkGcash = document.getElementById('checkGcash').checked;
            const checkMaya = document.getElementById('checkMaya').checked;
            const checkInstapay = document.getElementById('checkInstapay').checked;
            const checkOnlineBanking = document.getElementById('checkOnlineBanking').checked;
            const checkAirbnb = document.getElementById('checkAirbnb').checked;

            // Show/hide corresponding payment rows
            document.getElementById('cashPaymentRow').style.display = checkCash ? 'grid' : 'none';
            document.getElementById('gcashPaymentRow').style.display = checkGcash ? 'grid' : 'none';
            document.getElementById('mayaPaymentRow').style.display = checkMaya ? 'grid' : 'none';
            document.getElementById('instapayPaymentRow').style.display = checkInstapay ? 'grid' : 'none';
            document.getElementById('onlineBankingPaymentRow').style.display = checkOnlineBanking ? 'grid' : 'none';
            document.getElementById('airbnbPaymentRow').style.display = checkAirbnb ? 'grid' : 'none';

            // Build payment method string for backward compatibility
            const methods = [];
            if (checkCash) methods.push('Cash');
            if (checkGcash) methods.push('G-cash');
            if (checkMaya) methods.push('Maya');
            if (checkInstapay) methods.push('Instapay');
            if (checkOnlineBanking) methods.push('Online Banking');
            if (checkAirbnb) methods.push('Airbnb');

            let paymentMethodString = '';
            if (methods.length === 0) {
                paymentMethodString = 'Cash'; // Default
            } else if (methods.length === 1) {
                paymentMethodString = methods[0];
            } else if (methods.length === 2) {
                paymentMethodString = methods.join(' & ');
            } else if (methods.length === 3 && checkCash && checkGcash && checkMaya) {
                paymentMethodString = 'Cash, G-cash & Maya';
            } else {
                paymentMethodString = methods.join(' & ');
            }

            document.getElementById('modalPaymentMethod').value = paymentMethodString;

            // Update payment amount display
            updatePaymentAmountDisplay();
        }

        function updatePaymentAmountDisplay() {
            const paymentBreakdown = [];
            let totalPayment = 0;

            // Check each payment method
            if (document.getElementById('checkCash').checked) {
                const cashAmt = parseFloat(document.getElementById('modalCashAmount').value) || 0;
                if (cashAmt > 0) {
                    paymentBreakdown.push('Cash: ₱' + cashAmt.toFixed(2));
                    totalPayment += cashAmt;
                }
            }

            if (document.getElementById('checkGcash').checked) {
                const gcashAmt = parseFloat(document.getElementById('modalGcashAmount').value) || 0;
                if (gcashAmt > 0) {
                    paymentBreakdown.push('G-cash: ₱' + gcashAmt.toFixed(2));
                    totalPayment += gcashAmt;
                }
            }

            if (document.getElementById('checkMaya').checked) {
                const mayaAmt = parseFloat(document.getElementById('modalMayaAmount').value) || 0;
                if (mayaAmt > 0) {
                    paymentBreakdown.push('Maya: ₱' + mayaAmt.toFixed(2));
                    totalPayment += mayaAmt;
                }
            }

            if (document.getElementById('checkInstapay').checked) {
                const instapayAmt = parseFloat(document.getElementById('modalInstapayAmount').value) || 0;
                if (instapayAmt > 0) {
                    paymentBreakdown.push('Instapay: ₱' + instapayAmt.toFixed(2));
                    totalPayment += instapayAmt;
                }
            }

            if (document.getElementById('checkOnlineBanking').checked) {
                const onlineBankingAmt = parseFloat(document.getElementById('modalOnlineBankingAmount').value) || 0;
                if (onlineBankingAmt > 0) {
                    paymentBreakdown.push('Online Banking: ₱' + onlineBankingAmt.toFixed(2));
                    totalPayment += onlineBankingAmt;
                }
            }

            if (document.getElementById('checkAirbnb').checked) {
                const airbnbAmt = parseFloat(document.getElementById('modalAirbnbAmount').value) || 0;
                if (airbnbAmt > 0) {
                    paymentBreakdown.push('Airbnb: ₱' + airbnbAmt.toFixed(2));
                    totalPayment += airbnbAmt;
                }
            }

            const paymentDisplay = totalPayment > 0 ? paymentBreakdown.join(' + ') : '₱0.00';
            const modalPayAmtEl = document.getElementById('modalPaymentAmount');
            if (modalPayAmtEl) modalPayAmtEl.textContent = paymentDisplay;
        }

        function handleReservationCheckboxChange() {
            // If reservation payment method UI is not present, safely exit
            const cashChk = document.getElementById('checkReservationCash');
            const gcashChk = document.getElementById('checkReservationGcash');
            const mayaChk = document.getElementById('checkReservationMaya');
            const instapayChk = document.getElementById('checkReservationInstapay');
            const obChk = document.getElementById('checkReservationOnlineBanking');
            const airbnbChk = document.getElementById('checkReservationAirbnb');

            if (!cashChk && !gcashChk && !mayaChk && !instapayChk && !obChk && !airbnbChk) {
                return;
            }

            // Get checkbox states
            const checkCash = !!(cashChk && cashChk.checked);
            const checkGcash = !!(gcashChk && gcashChk.checked);
            const checkMaya = !!(mayaChk && mayaChk.checked);
            const checkInstapay = !!(instapayChk && instapayChk.checked);
            const checkOnlineBanking = !!(obChk && obChk.checked);
            const checkAirbnb = !!(airbnbChk && airbnbChk.checked);

            // Show/hide corresponding reservation payment rows
            const cashRow = document.getElementById('reservationCashPaymentRow');
            const gcashRow = document.getElementById('reservationGcashPaymentRow');
            const mayaRow = document.getElementById('reservationMayaPaymentRow');
            const instapayRow = document.getElementById('reservationInstapayPaymentRow');
            const obRow = document.getElementById('reservationOnlineBankingPaymentRow');
            const airbnbRow = document.getElementById('reservationAirbnbPaymentRow');

            if (cashRow) cashRow.style.display = checkCash ? 'grid' : 'none';
            if (gcashRow) gcashRow.style.display = checkGcash ? 'grid' : 'none';
            if (mayaRow) mayaRow.style.display = checkMaya ? 'grid' : 'none';
            if (instapayRow) instapayRow.style.display = checkInstapay ? 'grid' : 'none';
            if (obRow) obRow.style.display = checkOnlineBanking ? 'grid' : 'none';
            if (airbnbRow) airbnbRow.style.display = checkAirbnb ? 'grid' : 'none';

            // Build reservation payment method string for backward compatibility
            const methods = [];
            if (checkCash) methods.push('Cash');
            if (checkGcash) methods.push('G-cash');
            if (checkMaya) methods.push('Maya');
            if (checkInstapay) methods.push('Instapay');
            if (checkOnlineBanking) methods.push('Online Banking');
            if (checkAirbnb) methods.push('Airbnb');

            let reservationPaymentMethodString = '';
            if (methods.length === 0) {
                reservationPaymentMethodString = 'Cash'; // Default
            } else if (methods.length === 1) {
                reservationPaymentMethodString = methods[0];
            } else if (methods.length === 2) {
                reservationPaymentMethodString = methods.join(' & ');
            } else if (methods.length === 3 && checkCash && checkGcash && checkMaya) {
                reservationPaymentMethodString = 'Cash, G-cash & Maya';
            } else {
                reservationPaymentMethodString = methods.join(' & ');
            }

            const methodInput = document.getElementById('modalReservationPaymentMethod');
            if (methodInput) methodInput.value = reservationPaymentMethodString;

            // Update reservation amount display
            updateReservationAmountDisplay();
        }

        function updateReservationAmountDisplay() {
            const amountEl = document.getElementById('modalReservationAmount');
            if (!amountEl) return;

            const hasUi =
                document.getElementById('checkReservationCash') ||
                document.getElementById('checkReservationGcash') ||
                document.getElementById('checkReservationMaya') ||
                document.getElementById('checkReservationInstapay') ||
                document.getElementById('checkReservationOnlineBanking') ||
                document.getElementById('checkReservationAirbnb');

            // If the Reservation Payment Method UI has been removed, just show the stored downpayment total.
            if (!hasUi && window.currentBooking) {
                const dpTotal = getTotalDownpayment(window.currentBooking);
                amountEl.textContent = `₱${dpTotal.toFixed(2)}`;
                return;
            }

            const reservationBreakdown = [];
            let totalReservation = 0;

            if (document.getElementById('checkReservationCash')?.checked) {
                const cashAmt = parseFloat(document.getElementById('modalReservationCash').value) || 0;
                if (cashAmt > 0) {
                    reservationBreakdown.push('Cash: ₱' + cashAmt.toFixed(2));
                    totalReservation += cashAmt;
                }
            }

            if (document.getElementById('checkReservationGcash')?.checked) {
                const gcashAmt = parseFloat(document.getElementById('modalReservationGcash').value) || 0;
                if (gcashAmt > 0) {
                    reservationBreakdown.push('G-cash: ₱' + gcashAmt.toFixed(2));
                    totalReservation += gcashAmt;
                }
            }

            if (document.getElementById('checkReservationMaya')?.checked) {
                const mayaAmt = parseFloat(document.getElementById('modalReservationMaya').value) || 0;
                if (mayaAmt > 0) {
                    reservationBreakdown.push('Maya: ₱' + mayaAmt.toFixed(2));
                    totalReservation += mayaAmt;
                }
            }

            if (document.getElementById('checkReservationInstapay')?.checked) {
                const instapayAmt = parseFloat(document.getElementById('modalReservationInstapay').value) || 0;
                if (instapayAmt > 0) {
                    reservationBreakdown.push('Instapay: ₱' + instapayAmt.toFixed(2));
                    totalReservation += instapayAmt;
                }
            }

            if (document.getElementById('checkReservationOnlineBanking')?.checked) {
                const onlineBankingAmt = parseFloat(document.getElementById('modalReservationOnlineBanking').value) || 0;
                if (onlineBankingAmt > 0) {
                    reservationBreakdown.push('Online Banking: ₱' + onlineBankingAmt.toFixed(2));
                    totalReservation += onlineBankingAmt;
                }
            }

            if (document.getElementById('checkReservationAirbnb')?.checked) {
                const airbnbAmt = parseFloat(document.getElementById('modalReservationAirbnb').value) || 0;
                if (airbnbAmt > 0) {
                    reservationBreakdown.push('Airbnb: ₱' + airbnbAmt.toFixed(2));
                    totalReservation += airbnbAmt;
                }
            }

            const reservationDisplay = totalReservation > 0 ? reservationBreakdown.join(' + ') : '₱0.00';
            amountEl.textContent = reservationDisplay;
        }

        function handlePaymentMethodChange() {
            const paymentMethod = document.getElementById('modalPaymentMethod').value;

            // All field rows
            const cashOnlyAmountRow = document.getElementById('cashOnlyAmountRow');
            const gcashOnlyAmountRow = document.getElementById('gcashOnlyAmountRow');
            const mayaOnlyAmountRow = document.getElementById('mayaOnlyAmountRow');
            const instapayOnlyAmountRow = document.getElementById('instapayOnlyAmountRow');
            const onlineBankingOnlyAmountRow = document.getElementById('onlineBankingOnlyAmountRow');
            const airbnbOnlyAmountRow = document.getElementById('airbnbOnlyAmountRow');
            const cashGcashAmountRow = document.getElementById('cashGcashAmountRow');
            const cashMayaAmountRow = document.getElementById('cashMayaAmountRow');
            const gcashMayaAmountRow = document.getElementById('gcashMayaAmountRow');
            const gcashRefField = document.getElementById('gcashReferenceField');
            const mayaRefField = document.getElementById('mayaReferenceField');
            const instapayRefField = document.getElementById('instapayReferenceField');
            const onlineBankingRefField = document.getElementById('onlineBankingReferenceField');
            const airbnbRefField = document.getElementById('airbnbReferenceField');
            const mayaReferenceInline = document.getElementById('mayaReferenceInline');

            // Hide all fields first
            cashOnlyAmountRow.style.display = 'none';
            gcashOnlyAmountRow.style.display = 'none';
            mayaOnlyAmountRow.style.display = 'none';
            instapayOnlyAmountRow.style.display = 'none';
            onlineBankingOnlyAmountRow.style.display = 'none';
            airbnbOnlyAmountRow.style.display = 'none';
            cashGcashAmountRow.style.display = 'none';
            cashMayaAmountRow.style.display = 'none';
            gcashMayaAmountRow.style.display = 'none';
            gcashRefField.style.display = 'none';
            mayaRefField.style.display = 'none';
            instapayRefField.style.display = 'none';
            onlineBankingRefField.style.display = 'none';
            airbnbRefField.style.display = 'none';
            mayaReferenceInline.style.display = 'none';

            // Show fields based on payment method
            if (paymentMethod === 'Cash') {
                cashOnlyAmountRow.style.display = 'grid';
            } else if (paymentMethod === 'Cash & G-cash') {
                cashGcashAmountRow.style.display = 'grid';
                gcashRefField.style.display = 'grid';
                mayaReferenceInline.style.display = 'none';
            } else if (paymentMethod === 'Cash & Maya') {
                cashMayaAmountRow.style.display = 'grid';
                mayaRefField.style.display = 'grid';
            } else if (paymentMethod === 'G-cash & Maya') {
                gcashMayaAmountRow.style.display = 'grid';
                gcashRefField.style.display = 'grid';
                mayaReferenceInline.style.display = 'flex';
            } else if (paymentMethod === 'Cash, G-cash & Maya') {
                cashGcashAmountRow.style.display = 'grid';
                cashMayaAmountRow.style.display = 'grid';
                gcashRefField.style.display = 'grid';
                mayaReferenceInline.style.display = 'flex';
            } else if (paymentMethod === 'G-cash') {
                gcashOnlyAmountRow.style.display = 'grid';
                gcashRefField.style.display = 'grid';
                mayaReferenceInline.style.display = 'none';
            } else if (paymentMethod === 'Maya') {
                mayaOnlyAmountRow.style.display = 'grid';
                mayaRefField.style.display = 'grid';
            } else if (paymentMethod === 'Instapay') {
                instapayOnlyAmountRow.style.display = 'grid';
                instapayRefField.style.display = 'grid';
            } else if (paymentMethod === 'Online Banking') {
                onlineBankingOnlyAmountRow.style.display = 'grid';
                onlineBankingRefField.style.display = 'grid';
            } else if (paymentMethod === 'Airbnb') {
                airbnbOnlyAmountRow.style.display = 'grid';
                airbnbRefField.style.display = 'grid';
            }
        }

        function handleReservationPaymentMethodChange() {
            const methodInputEl = document.getElementById('modalReservationPaymentMethod');
            if (!methodInputEl) return;
            const reservationPaymentMethod = methodInputEl.value;

            // Reservation amount field rows
            const reservationCashOnlyRow = document.getElementById('reservationCashOnlyRow');
            const reservationGcashOnlyRow = document.getElementById('reservationGcashOnlyRow');
            const reservationMayaOnlyRow = document.getElementById('reservationMayaOnlyRow');
            const reservationInstapayOnlyRow = document.getElementById('reservationInstapayOnlyRow');
            const reservationOnlineBankingOnlyRow = document.getElementById('reservationOnlineBankingOnlyRow');
            const reservationAirbnbOnlyRow = document.getElementById('reservationAirbnbOnlyRow');
            const reservationCashGcashRow = document.getElementById('reservationCashGcashRow');
            const reservationCashMayaRow = document.getElementById('reservationCashMayaRow');
            const reservationGcashMayaRow = document.getElementById('reservationGcashMayaRow');
            const reservationAllThreeRow = document.getElementById('reservationAllThreeRow');
            const reservationAllThreeRow2 = document.getElementById('reservationAllThreeRow2');
            const reservationGcashRefField = document.getElementById('reservationGcashRefField');
            const reservationMayaRefField = document.getElementById('reservationMayaRefField');
            const reservationInstapayRefField = document.getElementById('reservationInstapayRefField');
            const reservationOnlineBankingRefField = document.getElementById('reservationOnlineBankingRefField');
            const reservationAirbnbRefField = document.getElementById('reservationAirbnbRefField');

            // Hide all reservation fields first
            reservationCashOnlyRow.style.display = 'none';
            reservationGcashOnlyRow.style.display = 'none';
            reservationMayaOnlyRow.style.display = 'none';
            reservationInstapayOnlyRow.style.display = 'none';
            reservationOnlineBankingOnlyRow.style.display = 'none';
            reservationAirbnbOnlyRow.style.display = 'none';
            reservationCashGcashRow.style.display = 'none';
            reservationCashMayaRow.style.display = 'none';
            reservationGcashMayaRow.style.display = 'none';
            reservationAllThreeRow.style.display = 'none';
            reservationAllThreeRow2.style.display = 'none';
            reservationGcashRefField.style.display = 'none';
            reservationMayaRefField.style.display = 'none';
            reservationInstapayRefField.style.display = 'none';
            reservationOnlineBankingRefField.style.display = 'none';
            reservationAirbnbRefField.style.display = 'none';

            // Show fields based on reservation payment method
            if (reservationPaymentMethod === 'Cash') {
                reservationCashOnlyRow.style.display = 'grid';
            } else if (reservationPaymentMethod === 'Cash & G-cash') {
                reservationCashGcashRow.style.display = 'grid';
                reservationGcashRefField.style.display = 'grid';
            } else if (reservationPaymentMethod === 'Cash & Maya') {
                reservationCashMayaRow.style.display = 'grid';
                reservationMayaRefField.style.display = 'grid';
            } else if (reservationPaymentMethod === 'G-cash & Maya') {
                reservationGcashMayaRow.style.display = 'grid';
                reservationGcashRefField.style.display = 'grid';
                reservationMayaRefField.style.display = 'grid';
            } else if (reservationPaymentMethod === 'Cash, G-cash & Maya') {
                reservationAllThreeRow.style.display = 'grid';
                reservationAllThreeRow2.style.display = 'grid';
                reservationGcashRefField.style.display = 'grid';
                reservationMayaRefField.style.display = 'grid';
            } else if (reservationPaymentMethod === 'G-cash') {
                reservationGcashOnlyRow.style.display = 'grid';
                reservationGcashRefField.style.display = 'grid';
            } else if (reservationPaymentMethod === 'Maya') {
                reservationMayaOnlyRow.style.display = 'grid';
                reservationMayaRefField.style.display = 'grid';
            } else if (reservationPaymentMethod === 'Instapay') {
                reservationInstapayOnlyRow.style.display = 'grid';
                reservationInstapayRefField.style.display = 'grid';
            } else if (reservationPaymentMethod === 'Online Banking') {
                reservationOnlineBankingOnlyRow.style.display = 'grid';
                reservationOnlineBankingRefField.style.display = 'grid';
            } else if (reservationPaymentMethod === 'Airbnb') {
                reservationAirbnbOnlyRow.style.display = 'grid';
                reservationAirbnbRefField.style.display = 'grid';
            }
        }

        function updateBooking() {
            const bookingId = document.getElementById('editBookingId').value;

            // Validate required field: Modification Reason
            // Only require if there's no existing modification reason stored
            const modificationReasonField = document.getElementById('modalModificationReason');
            const modificationReason = modificationReasonField.value.trim();
            const originalModificationReason = modificationReasonField.getAttribute('data-original-value') || '';

            // If there was no previous modification reason, require one now
            if (!originalModificationReason && !modificationReason) {
                alert('Modification Reason is required. Please provide a reason for this modification.');
                modificationReasonField.focus();
                return;
            }

            // Get booking type
            const bookingType = document.getElementById('modalBookingTypeWalkin').checked ? 'Walk-in' : 'Reservation';

            const updatedData = {
                id: bookingId,
                modification_reason: modificationReason,
                room: document.getElementById('modalRoom').value,
                booking_type: bookingType,
                guest_type: document.getElementById('modalGuestType').value,
                guest_names: document.getElementById('modalGuestNames').value,
                reason_for_stay: document.getElementById('modalReasonForStay').value,
                contact_person_name: document.getElementById('modalContactPersonName').value,
                contact_no: document.getElementById('modalContactNo').value,
                address: document.getElementById('modalAddress').value,
                tin_number: document.getElementById('modalTinNo').value,
                request: document.getElementById('modalRequest').value,
                vehicle_type: document.getElementById('modalVehicleType').value,
                plate_number: document.getElementById('modalPlateNumber').value,
                vehicle_description: document.getElementById('modalVehicleDescription').value,
                transfer_room_from: document.getElementById('modalTransferRoomFrom').value,
                transfer_refund_amount: parseFloat(document.getElementById('modalTransferRefundAmount').value) || 0,
                check_in: document.getElementById('modalCheckIn').value,
                check_out: document.getElementById('modalCheckOut').value,
                referral_code: document.getElementById('modalReferralCode').value,
                payment_method: '',
            };

            syncModalMetaFromPayments();
            const metaData = collectPaymentBookingMeta();
            updatedData.duration = metaData.duration;
            updatedData.promo = metaData.promo;
            updatedData.breakfast = metaData.breakfast;
            updatedData.extend_hours = metaData.extend_hours || 0;
            updatedData.extend_minutes = metaData.extend_minutes || 0;
            updatedData.extend_price = metaData.extend_price || 0;
            updatedData.extend_regular_rate = metaData.extend_regular_rate || 0;
            updatedData.extend_bundle_rate = metaData.extend_bundle_rate || 0;
            updatedData.extend_bundle_breakfast = metaData.extend_bundle_breakfast || '';

            // Calculate cumulative payment method totals from the history list to send as main amounts
            let totalCash = 0;
            let totalGcash = 0;
            let totalMaya = 0;
            let totalInstapay = 0;
            let totalOnlineBanking = 0;
            let totalAirbnb = 0;

            const methodsList = ['cash', 'gcash', 'maya', 'instapay', 'ob', 'airbnb'];
            let idx = 0;
            while (document.getElementById(`payHist_${methodsList[0]}_chk_${idx}`) !== null) {
                const cashChk = document.getElementById(`payHist_cash_chk_${idx}`);
                const cashAmt = document.getElementById(`payHist_cash_amt_${idx}`);
                if (cashChk && cashChk.checked && cashAmt) totalCash += parseFloat(cashAmt.value) || 0;

                const gcashChk = document.getElementById(`payHist_gcash_chk_${idx}`);
                const gcashAmt = document.getElementById(`payHist_gcash_amt_${idx}`);
                if (gcashChk && gcashChk.checked && gcashAmt) totalGcash += parseFloat(gcashAmt.value) || 0;

                const mayaChk = document.getElementById(`payHist_maya_chk_${idx}`);
                const mayaAmt = document.getElementById(`payHist_maya_amt_${idx}`);
                if (mayaChk && mayaChk.checked && mayaAmt) totalMaya += parseFloat(mayaAmt.value) || 0;

                const instChk = document.getElementById(`payHist_instapay_chk_${idx}`);
                const instAmt = document.getElementById(`payHist_instapay_amt_${idx}`);
                if (instChk && instChk.checked && instAmt) totalInstapay += parseFloat(instAmt.value) || 0;

                const obChk = document.getElementById(`payHist_ob_chk_${idx}`);
                const obAmt = document.getElementById(`payHist_ob_amt_${idx}`);
                if (obChk && obChk.checked && obAmt) totalOnlineBanking += parseFloat(obAmt.value) || 0;

                const airChk = document.getElementById(`payHist_airbnb_chk_${idx}`);
                const airAmt = document.getElementById(`payHist_airbnb_amt_${idx}`);
                if (airChk && airChk.checked && airAmt) totalAirbnb += parseFloat(airAmt.value) || 0;

                idx++;
            }

            // If there was no history rendered/loaded, fall back to the existing values of the booking
            if (idx === 0) {
                const currentBooking = window.currentBooking || {};
                const parseSum = (str) => str ? str.split('|').reduce((sum, v) => sum + (parseFloat(v) || 0), 0) : 0;
                totalCash = parseSum(currentBooking.payment_amount_cash_history);
                totalGcash = parseSum(currentBooking.payment_amount_g_cash_history);
                totalMaya = parseSum(currentBooking.payment_amount_maya_history);
                totalInstapay = parseSum(currentBooking.payment_amount_instapay_history);
                totalOnlineBanking = parseSum(currentBooking.payment_amount_online_banking_history);
                totalAirbnb = parseSum(currentBooking.payment_amount_airbnb_history);
            }

            updatedData.cash_amount = totalCash.toFixed(2);
            updatedData.gcash_amount = totalGcash.toFixed(2);
            updatedData.maya_amount = totalMaya.toFixed(2);
            updatedData.instapay_amount = totalInstapay.toFixed(2);
            updatedData.online_banking_amount = totalOnlineBanking.toFixed(2);
            updatedData.airbnb_amount = totalAirbnb.toFixed(2);

            // Determine payment method string from cumulative totals
            const activeMethods = [];
            if (totalCash > 0) activeMethods.push('Cash');
            if (totalGcash > 0) activeMethods.push('G-cash');
            if (totalMaya > 0) activeMethods.push('Maya');
            if (totalInstapay > 0) activeMethods.push('Instapay');
            if (totalOnlineBanking > 0) activeMethods.push('Online Banking');
            if (totalAirbnb > 0) activeMethods.push('Airbnb');

            let paymentMethodString = '';
            if (activeMethods.length === 0) {
                paymentMethodString = 'Cash'; // Default
            } else if (activeMethods.length === 1) {
                paymentMethodString = activeMethods[0];
            } else if (activeMethods.length === 2) {
                paymentMethodString = activeMethods.join(' & ');
            } else if (activeMethods.length === 3 && totalCash > 0 && totalGcash > 0 && totalMaya > 0) {
                paymentMethodString = 'Cash, G-cash & Maya';
            } else {
                paymentMethodString = activeMethods.join(' & ');
            }

            updatedData.payment_method = paymentMethodString;
            document.getElementById('modalPaymentMethod').value = paymentMethodString;

            // Extract reservation amounts (reservation payment method checkboxes were removed).
            // Use the reservation input fields directly; this keeps the payload valid and prevents null-check crashes.
            const rCash = parseFloat(document.getElementById('modalReservationCash')?.value) || 0;
            const rGcash = parseFloat(document.getElementById('modalReservationGcash')?.value) || 0;
            const rMaya = parseFloat(document.getElementById('modalReservationMaya')?.value) || 0;
            const rInstapay = parseFloat(document.getElementById('modalReservationInstapay')?.value) || 0;
            const rOnlineBanking = parseFloat(document.getElementById('modalReservationOnlineBanking')?.value) || 0;
            const rAirbnb = parseFloat(document.getElementById('modalReservationAirbnb')?.value) || 0;

            updatedData.reservation_cash = rCash;
            updatedData.reservation_gcash = rGcash;
            updatedData.reservation_maya = rMaya;
            updatedData.reservation_instapay = rInstapay;
            updatedData.reservation_online_banking = rOnlineBanking;
            updatedData.reservation_airbnb = rAirbnb;

            // Extract reservation reference numbers (only when the related amount is present)
            const gcashRef = document.getElementById('modalReservationGcashReference')?.value || '';
            const mayaRef = document.getElementById('modalReservationMayaReference')?.value || '';
            const instapayRef = document.getElementById('modalReservationInstapayReference')?.value || '';
            const onlineBankingRef = document.getElementById('modalReservationOnlineBankingReference')?.value || '';
            const airbnbRef = document.getElementById('modalReservationAirbnbReference')?.value || '';

            updatedData.reservation_gcash_ref = rGcash > 0 ? gcashRef : '';
            updatedData.reservation_maya_ref = rMaya > 0 ? mayaRef : '';
            updatedData.reservation_instapay_ref = rInstapay > 0 ? instapayRef : '';
            updatedData.reservation_online_banking_ref = rOnlineBanking > 0 ? onlineBankingRef : '';
            updatedData.reservation_airbnb_ref = rAirbnb > 0 ? airbnbRef : '';

            // Per-payment additionals (guest, pet, food/item)
            const addData = collectPaymentAdditionals();
            Object.assign(updatedData, {
                additional_guest: addData.additional_guest,
                additional_pet: addData.additional_pet,
                additional_data: addData.additional_data,
                additional: document.getElementById('modalAdditional').value,
                cancellation_reason: document.getElementById('modalCancellationReason').value,
                cancellation_refund: document.getElementById('modalCancellationRefund').value
            });
            if (document.getElementById('modalAdditionalGuest')) {
                document.getElementById('modalAdditionalGuest').value = addData.additional_guest;
            }
            if (document.getElementById('modalAdditionalPet')) {
                document.getElementById('modalAdditionalPet').value = addData.additional_pet;
            }
            if (document.getElementById('modalAdditionalCharges')) {
                document.getElementById('modalAdditionalCharges').value = addData.additional_data;
            }

            // Collect edited payment method history entries (per-payment cards)
            const payHistData = collectPaymentHistory();
            if (payHistData && Object.keys(payHistData).length > 0) {
                Object.assign(updatedData, payHistData);
            }

            // Per-payment discount details (overrides modal discount fields)
            const discData = collectPaymentDiscounts();
            Object.assign(updatedData, discData);
            if (document.getElementById('modalDiscountAmount')) {
                document.getElementById('modalDiscountAmount').value = discData.discount_amount;
            }
            if (document.getElementById('modalDiscountCount')) {
                document.getElementById('modalDiscountCount').value = discData.discount_count || '';
            }
            if (document.getElementById('modalDiscountId')) {
                document.getElementById('modalDiscountId').value = discData.discount_id || '';
            }
            updatedData.discount_amount = discData.discount_amount;
            updatedData.discount_count = discData.discount_count;
            updatedData.discount_id = discData.discount_id;

            // Sync reference fields after payment history is collected
            Object.assign(updatedData, collectPaymentReferences());

            // Log the complete data being sent
            console.log('Sending update data:', JSON.stringify(updatedData, null, 2));

            // Send update request
            fetch('update_modification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(updatedData)
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Update response:', data);
                    if (data.success) {
                        alert('Booking updated successfully!');
                        closeEditModal();
                        // Reload with current search/filter preserved
                        const searchInput = document.getElementById('searchInput');
                        const dateInput = document.getElementById('dateFilterInput');
                        if (searchInput && searchInput.value) {
                            searchByBookingId();
                        } else if (dateInput && dateInput.value) {
                            searchByDate(dateInput.value);
                        } else {
                            location.reload();
                        }
                    } else {
                        alert('Error updating booking: ' + (data.error || 'Unknown error'));
                        console.error('Update error:', data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating booking: ' + error.message);
                });
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        function deleteModification(id, source) {
            if (confirm('Are you sure you want to delete this modification from ' + source + '?')) {
                fetch('delete_modification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, source: source })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert('Deleted successfully');
                            const searchInput = document.getElementById('searchInput');
                            const dateFromInput = document.getElementById('dateFromInput');
                            const dateToInput = document.getElementById('dateToInput');
                            if (searchInput && searchInput.value) {
                                searchByBookingId();
                            } else if (dateFromInput && (dateFromInput.value || dateToInput.value)) {
                                searchByDateRange(dateFromInput.value, dateToInput.value);
                            } else {
                                location.reload();
                            }
                        } else {
                            alert('Error deleting: ' + data.error);
                        }
                    })
                    .catch(err => {
                        alert('Network error: ' + err.message);
                    });
            }
        }

        // Don't load all data on page load - wait for search

        // Extension Duration Modal Functions
        let currentExtendBookingId = null;
        let currentBookingData = null;

        function openExtendDurationModal(bookingId) {
            currentExtendBookingId = bookingId;

            // Fetch booking details to get check-out time and extension data
            fetch(`get_modifications.php?booking_id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.modifications && data.modifications.length > 0) {
                        currentBookingData = data.modifications[0];

                        // Set current check-out
                        const currentCheckOutEl = document.getElementById('currentCheckOut');
                        if (currentCheckOutEl && currentBookingData.check_out) {
                            currentCheckOutEl.value = formatDateTime(currentBookingData.check_out);
                        }

                        // Initialize new check-out to current
                        const newCheckOutEl = document.getElementById('newCheckOut');
                        if (newCheckOutEl && currentBookingData.check_out) {
                            newCheckOutEl.value = formatDateTime(currentBookingData.check_out);
                        }

                        // Load existing extension data if available
                        const extendHours = parseInt(currentBookingData.extend_hours) || 0;
                        const extendMinutes = parseInt(currentBookingData.extend_minutes) || 0;
                        const extendPrice = parseFloat(currentBookingData.extend_price) || 0;
                        const extendRegularRate = parseFloat(currentBookingData.extend_regular_rate) || 0;
                        const extendBundleRate = parseFloat(currentBookingData.extend_bundle_rate) || 0;

                        // Store extension data for later use
                        currentBookingData.existing_extend_hours = extendHours;
                        currentBookingData.existing_extend_price = extendPrice;

                        // Initialize / Reset Guest Extension Checkbox Section
                        const guestSection = document.getElementById('extendAdditionalGuestSection');
                        if (guestSection) {
                            guestSection.innerHTML = '';
                            const addGuests = parseInt(currentBookingData.additional_guest || 0);
                            if (addGuests > 0) {
                                guestSection.style.display = 'flex';
                                const existingExtendGuest = parseInt(currentBookingData.extend_additional_guest || 0);
                                
                                for (let i = 1; i <= addGuests; i++) {
                                    const suffix = ["th", "st", "nd", "rd"];
                                    const v = i % 100;
                                    const ordinal = i + (suffix[(v - 20) % 10] || suffix[v] || suffix[0]);
                                    
                                    const checkboxWrapper = document.createElement('div');
                                    checkboxWrapper.style.cssText = 'display: flex; align-items: center; gap: 12px; cursor: pointer;';
                                    
                                    const checkbox = document.createElement('input');
                                    checkbox.type = 'checkbox';
                                    checkbox.className = 'extend-guest-checkbox';
                                    checkbox.id = `extendGuestCheckbox_${i}`;
                                    checkbox.style.cssText = 'width: 20px; height: 20px; cursor: pointer;';
                                    checkbox.checked = (i <= existingExtendGuest);
                                    
                                    const label = document.createElement('label');
                                    label.htmlFor = `extendGuestCheckbox_${i}`;
                                    label.style.cssText = 'font-size: 14px; font-weight: 500; cursor: pointer; margin: 0; color: #374151;';
                                    label.textContent = `Extend ${ordinal} Additional Guest (₱300.00)`;
                                    
                                    checkbox.onchange = function() {
                                        updateExtendDurationPrices();
                                    };
                                    
                                    checkboxWrapper.appendChild(checkbox);
                                    checkboxWrapper.appendChild(label);
                                    guestSection.appendChild(checkboxWrapper);
                                }
                            } else {
                                guestSection.style.display = 'none';
                            }
                        }

                        // Determine which method was used based on the data
                        // If extend_regular_rate or extend_bundle_rate has a value, it was Room rate method
                        // Otherwise, if extend_hours/minutes has value, it was Duration method

                        if (extendRegularRate > 0) {
                            // Room rate - Regular was used
                            document.getElementById('extendByRoomRate').checked = true;
                            toggleExtendFields(); // Show room rate buttons
                            selectRoomRateType('regular'); // Automatically open Regular section
                        } else if (extendBundleRate > 0) {
                            // Room rate - Bundle was used
                            document.getElementById('extendByRoomRate').checked = true;
                            toggleExtendFields(); // Show room rate buttons
                            selectRoomRateType('bundle'); // Automatically open Bundle section
                        } else if (extendHours > 0 || extendMinutes > 0) {
                            // Duration method was used
                            document.getElementById('extendByDuration').checked = true;
                            document.getElementById('extendHours').value = extendHours;
                            document.getElementById('extendMinutes').value = extendMinutes;
                            toggleExtendFields(); // Show duration fields
                            calculateExtendCost();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching booking details:', error);
                });

            const modal = document.getElementById('extendDurationModal');
            modal.style.display = 'flex';
        }

        function updateExtendDurationPrices() {
            calculateRegularCost();
            calculateBundleCost();
            calculateExtendCost();
        }

        function closeExtendDurationModal() {
            const modal = document.getElementById('extendDurationModal');
            modal.style.display = 'none';
            currentExtendBookingId = null;
            currentBookingData = null;

            // Reset form
            document.getElementById('extendByRoomRate').checked = false;
            document.getElementById('extendByDuration').checked = false;
            document.getElementById('extendDurationFields').style.display = 'none';
            document.getElementById('extendRoomRateButtons').style.display = 'none';
            document.getElementById('extendRegularSection').style.display = 'none';
            document.getElementById('extendBundleSection').style.display = 'none';
            document.getElementById('extendHours').value = '0';
            document.getElementById('extendMinutes').value = '0';
            document.getElementById('extendCostDisplay').textContent = '₱0.00';
            document.getElementById('regularCostDisplay').textContent = '₱0.00';
            document.getElementById('bundleCostDisplay').textContent = '₱0.00';

            // Reset guest extension section
            const extendGuestSection = document.getElementById('extendAdditionalGuestSection');
            if (extendGuestSection) {
                extendGuestSection.style.display = 'none';
                extendGuestSection.innerHTML = '';
            }

            // Remove active states
            const regularBtn = document.getElementById('regularBtn');
            const bundleBtn = document.getElementById('bundleBtn');
            if (regularBtn) regularBtn.classList.remove('active');
            if (bundleBtn) bundleBtn.classList.remove('active');
        }

        function toggleExtendFields() {
            const roomRateRadio = document.getElementById('extendByRoomRate');
            const durationRadio = document.getElementById('extendByDuration');
            const durationFieldsContainer = document.getElementById('extendDurationFields');
            const roomRateButtonsContainer = document.getElementById('extendRoomRateButtons');
            const regularSection = document.getElementById('extendRegularSection');
            const bundleSection = document.getElementById('extendBundleSection');
            const regularBtn = document.getElementById('regularBtn');
            const bundleBtn = document.getElementById('bundleBtn');

            if (durationRadio.checked) {
                // Show duration fields (hours/minutes)
                durationFieldsContainer.style.display = 'block';
                roomRateButtonsContainer.style.display = 'none';
                regularSection.style.display = 'none';
                bundleSection.style.display = 'none';

                // Remove active states from buttons
                if (regularBtn) regularBtn.classList.remove('active');
                if (bundleBtn) bundleBtn.classList.remove('active');

                calculateExtendCost();
            } else if (roomRateRadio.checked) {
                // Show room rate buttons (Regular/Bundle)
                durationFieldsContainer.style.display = 'none';
                roomRateButtonsContainer.style.display = 'flex';
                regularSection.style.display = 'none';
                bundleSection.style.display = 'none';

                // Remove active states from buttons when switching to room rate
                if (regularBtn) regularBtn.classList.remove('active');
                if (bundleBtn) bundleBtn.classList.remove('active');
            } else {
                // Hide everything
                durationFieldsContainer.style.display = 'none';
                roomRateButtonsContainer.style.display = 'none';
                regularSection.style.display = 'none';
                bundleSection.style.display = 'none';

                // Remove active states from buttons
                if (regularBtn) regularBtn.classList.remove('active');
                if (bundleBtn) bundleBtn.classList.remove('active');
            }
        }

        function calculateExtendCost() {
            const hours = parseInt(document.getElementById('extendHours').value) || 0;
            const minutes = parseInt(document.getElementById('extendMinutes').value) || 0;

            // Cost calculation: 200 per hour, 100 for 30 minutes
            const hourCost = hours * 200;
            const minuteCost = minutes === 30 ? 100 : 0;
            let totalCost = hourCost + minuteCost;

            const checkedGuestsCount = document.querySelectorAll('#extendAdditionalGuestSection .extend-guest-checkbox:checked').length;
            const guestFee = checkedGuestsCount * 300;

            totalCost += guestFee;

            // Update cost display
            document.getElementById('extendCostDisplay').textContent = '₱' + totalCost.toFixed(2);

            // Calculate new check-out time
            if (currentBookingData && currentBookingData.check_out) {
                const currentCheckOut = new Date(currentBookingData.check_out);
                const newCheckOut = new Date(currentCheckOut);
                newCheckOut.setHours(newCheckOut.getHours() + hours);
                newCheckOut.setMinutes(newCheckOut.getMinutes() + minutes);

                document.getElementById('newCheckOut').value = formatDateTime(newCheckOut);
            }
        }

        function formatDateTime(dateStr) {
            const date = new Date(dateStr);
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const year = date.getFullYear();
            let hours = date.getHours();
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            const hoursStr = String(hours).padStart(2, '0');

            return `${month}/${day}/${year}, ${hoursStr}:${minutes} ${ampm}`;
        }

        function submitExtendDuration() {
            if (!currentExtendBookingId) {
                alert('No booking selected');
                return;
            }

            const roomRateRadio = document.getElementById('extendByRoomRate');
            const durationRadio = document.getElementById('extendByDuration');
            const checkedGuestsCount = document.querySelectorAll('#extendAdditionalGuestSection .extend-guest-checkbox:checked').length;
            const hasGuestExt = checkedGuestsCount > 0;

            let extendType = '';
            let hours = 0;
            let minutes = 0;
            let extendRegularRate = 0;
            let extendBundleRate = 0;
            let extendBundleBreakfast = '';

            if (roomRateRadio && roomRateRadio.checked) {
                extendType = 'room_rate';
            } else if (durationRadio && durationRadio.checked) {
                extendType = 'duration';
            } else if (hasGuestExt) {
                extendType = 'duration'; // default to duration type
            } else {
                alert('Please select an extension type');
                return;
            }

            const formData = new FormData();
            formData.append('booking_id', currentExtendBookingId);
            formData.append('extend_type', extendType);
            formData.append('extend_additional_guest', checkedGuestsCount);

            if (extendType === 'duration') {
                const hoursInput = document.getElementById('extendHours');
                const minutesInput = document.getElementById('extendMinutes');
                hours = hoursInput ? (parseInt(hoursInput.value) || 0) : 0;
                minutes = minutesInput ? (parseInt(minutesInput.value) || 0) : 0;

                if (!hasGuestExt && hours === 0 && minutes === 0) {
                    alert('Please enter hours or minutes to extend');
                    return;
                }

                formData.append('extend_hours', hours);
                formData.append('extend_minutes', minutes);

                // Submit to server using UPDATE endpoint (replaces extension)
                fetch('update_extend_duration.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Extension updated successfully!');
                            closeExtendDurationModal();
                            // Reload with current search/filter preserved
                            const searchInput = document.getElementById('searchInput');
                            const dateInput = document.getElementById('dateFilterInput');
                            if (searchInput && searchInput.value) {
                                // If there's a search term, reload with that search
                                searchByBookingId();
                            } else if (dateInput && dateInput.value) {
                                // If there's a date filter, reload with that date
                                searchByDate(dateInput.value);
                            } else {
                                // Otherwise just reload the page
                                location.reload();
                            }
                        } else {
                            alert('Error: ' + (data.error || 'Failed to update extension'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Network error: Failed to update extension');
                    });

            } else {
                // Check if regular section is visible
                const regularSection = document.getElementById('extendRegularSection');
                const bundleSection = document.getElementById('extendBundleSection');

                if (regularSection && regularSection.style.display === 'block') {
                    const durationSelect = document.getElementById('regularDuration');
                    const selectedOption = durationSelect ? durationSelect.options[durationSelect.selectedIndex] : null;

                    if (!hasGuestExt && (!selectedOption || !selectedOption.value)) {
                        alert('Please select a duration');
                        return;
                    }

                    hours = selectedOption ? parseInt(selectedOption.value) : 0;
                    const price = selectedOption ? parseFloat(selectedOption.dataset.price || 0) : 0;

                    formData.append('extend_hours', hours);
                    formData.append('extend_minutes', 0);
                    formData.append('extend_regular_rate', price);

                    // Submit to server using UPDATE endpoint (replaces extension)
                    fetch('update_extend_duration.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Extension updated successfully!');
                                closeExtendDurationModal();
                                // Reload with current search/filter preserved
                                const searchInput = document.getElementById('searchInput');
                                const dateInput = document.getElementById('dateFilterInput');
                                if (searchInput && searchInput.value) {
                                    searchByBookingId();
                                } else if (dateInput && dateInput.value) {
                                    searchByDate(dateInput.value);
                                } else {
                                    location.reload();
                                }
                            } else {
                                alert('Error: ' + (data.error || 'Failed to update extension'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Network error: Failed to update extension');
                        });

                } else if (bundleSection && bundleSection.style.display === 'block') {
                    const bundleSelect = document.getElementById('bundleSelect');
                    const selectedOption = bundleSelect ? bundleSelect.options[bundleSelect.selectedIndex] : null;

                    if (!hasGuestExt && (!selectedOption || !selectedOption.value)) {
                        alert('Please select a bundle');
                        return;
                    }

                    hours = selectedOption ? parseInt(selectedOption.dataset.hours) : 0;
                    const price = selectedOption ? parseFloat(selectedOption.dataset.price || 0) : 0;

                    formData.append('extend_hours', hours);
                    formData.append('extend_minutes', 0);
                    formData.append('extend_bundle_rate', price);

                    // Get breakfast selections
                    const breakfastSelects = document.querySelectorAll('#bundleBreakfastContainer select');
                    const breakfastItems = [];
                    breakfastSelects.forEach(select => {
                        if (select.value && select.value !== '') {
                            breakfastItems.push(select.value);
                        }
                    });
                    formData.append('extend_bundle_breakfast', breakfastItems.join(' | '));

                    // Submit to server using UPDATE endpoint (replaces extension)
                    fetch('update_extend_duration.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Extension updated successfully!');
                                closeExtendDurationModal();
                                // Reload with current search/filter preserved
                                const searchInput = document.getElementById('searchInput');
                                const dateInput = document.getElementById('dateFilterInput');
                                if (searchInput && searchInput.value) {
                                    searchByBookingId();
                                } else if (dateInput && dateInput.value) {
                                    searchByDate(dateInput.value);
                                } else {
                                    location.reload();
                                }
                            } else {
                                alert('Error: ' + (data.error || 'Failed to update extension'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Network error: Failed to update extension');
                        });

                } else {
                    if (!hasGuestExt) {
                        alert('Please select Regular or Bundle.');
                        return;
                    }
                    // If guest extension only, submit with 0 duration
                    formData.append('extend_hours', 0);
                    formData.append('extend_minutes', 0);
                    fetch('update_extend_duration.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Extension updated successfully!');
                                closeExtendDurationModal();
                                location.reload();
                            } else {
                                alert('Error: ' + (data.error || 'Failed to update extension'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Network error: Failed to update extension');
                        });
                }
            }
        }

        function selectRoomRateType(type) {
            const regularSection = document.getElementById('extendRegularSection');
            const bundleSection = document.getElementById('extendBundleSection');
            const regularBtn = document.getElementById('regularBtn');
            const bundleBtn = document.getElementById('bundleBtn');

            console.log('selectRoomRateType called with type:', type);
            console.log('currentBookingData:', currentBookingData);

            if (type === 'regular') {
                regularSection.style.display = 'block';
                bundleSection.style.display = 'none';
                regularBtn.classList.add('active');
                bundleBtn.classList.remove('active');

                // Load duration options
                loadRegularDurationOptions();

                // Set current check-out
                if (currentBookingData && currentBookingData.check_out) {
                    document.getElementById('regularCurrentCheckOut').value = formatDateTime(currentBookingData.check_out);
                    document.getElementById('regularNewCheckOut').value = formatDateTime(currentBookingData.check_out);
                }
            } else if (type === 'bundle') {
                regularSection.style.display = 'none';
                bundleSection.style.display = 'block';
                regularBtn.classList.remove('active');
                bundleBtn.classList.add('active');

                // Load bundle options
                loadBundleOptions();

                // Set current check-out
                if (currentBookingData && currentBookingData.check_out) {
                    document.getElementById('bundleCurrentCheckOut').value = formatDateTime(currentBookingData.check_out);
                    document.getElementById('bundleNewCheckOut').value = formatDateTime(currentBookingData.check_out);
                }

                // Generate breakfast fields based on room capacity
                const guestCapacity = parseInt(currentBookingData.guest_capacity) || parseInt(currentBookingData.room_guest_capacity) || 2;
                console.log('Guest capacity for breakfast:', guestCapacity);
                generateBundleBreakfastFields(guestCapacity);
            }
        }

        function loadRegularDurationOptions() {
            const durationSelect = document.getElementById('regularDuration');
            durationSelect.innerHTML = '<option value="">Select Duration</option>';

            // Use the same approach as editModal - pass both room type and room_id
            if (currentBookingData && currentBookingData.room_id) {
                const roomId = currentBookingData.room_id;
                const roomType = currentBookingData.room || '';

                // Use the room type directly
                const cleanRoomType = roomType || 'Unknown';

                console.log('Loading pricing for:', { cleanRoomType, roomId });

                fetch(`get_room_pricing.php?room_type=${encodeURIComponent(cleanRoomType)}&room_id=${encodeURIComponent(roomId)}`)
                    .then(response => response.json())
                    .then(data => {
                        durationSelect.innerHTML = '<option value="">Select Duration</option>';

                        if (data.success && data.pricing && data.pricing.length > 0) {
                            data.pricing.forEach(price => {
                                const option = document.createElement('option');
                                option.value = price.hours;
                                option.dataset.price = price.price;
                                // Format price without .toFixed() to preserve original formatting
                                option.textContent = `${price.hours} hours - ₱${price.price}`;
                                durationSelect.appendChild(option);
                            });

                            // Pre-select by RATE first (handles stacked room-rate + duration extensions)
                            const existingRate = parseFloat(currentBookingData.extend_regular_rate) || 0;
                            const existingTotalHours = currentBookingData.existing_extend_hours || 0;
                            let rateMatchedHours = 0;

                            if (existingRate > 0) {
                                // Match by rate: correctly shows "12 hrs" when total stored hours is 15 (12+3)
                                // NOTE: opt.dataset.price may be formatted like "1,690.00" - strip commas before parseFloat
                                for (let i = 0; i < durationSelect.options.length; i++) {
                                    const opt = durationSelect.options[i];
                                    const optPrice = parseFloat((opt.dataset.price || '').replace(/,/g, '')) || 0;
                                    if (Math.abs(optPrice - existingRate) < 0.01) {
                                        durationSelect.selectedIndex = i;
                                        rateMatchedHours = parseInt(opt.value) || 0;
                                        calculateRegularCost();
                                        break;
                                    }
                                }

                                // No rate match found - add a custom option using the stored rate
                                if (rateMatchedHours === 0 && existingTotalHours > 0) {
                                    const customOption = document.createElement('option');
                                    customOption.value = existingTotalHours;
                                    customOption.dataset.price = existingRate;
                                    customOption.textContent = `${existingTotalHours} hours - \u20b1${existingRate.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} (Extended)`;
                                    customOption.selected = true;
                                    durationSelect.appendChild(customOption);
                                    rateMatchedHours = existingTotalHours;
                                    calculateRegularCost();
                                }
                            } else if (existingTotalHours > 0) {
                                // No room rate stored - match directly by total hours
                                durationSelect.value = existingTotalHours;
                                rateMatchedHours = existingTotalHours;
                                calculateRegularCost();
                            }

                            // Pre-fill Duration tab with any leftover hours beyond the room-rate portion
                            // e.g. total=15, room rate=12 hrs → duration-only = 3 hrs
                            const durationOnlyHours = Math.max(0, existingTotalHours - rateMatchedHours);
                            const existingMinutes = parseInt(currentBookingData.extend_minutes) || 0;
                            if (durationOnlyHours > 0 || existingMinutes > 0) {
                                document.getElementById('extendHours').value = durationOnlyHours;
                                document.getElementById('extendMinutes').value = existingMinutes;
                            }
                        } else {
                            console.log('No pricing data found');
                            durationSelect.innerHTML = '<option value="">No pricing available</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading duration options:', error);
                        durationSelect.innerHTML = '<option value="">Error loading pricing</option>';
                    });
            } else {
                console.log('No booking data available');
                durationSelect.innerHTML = '<option value="">No room selected</option>';
            }
        }

        function calculateRegularCost() {
            const durationSelect = document.getElementById('regularDuration');
            const selectedOption = durationSelect.options[durationSelect.selectedIndex];

            if (selectedOption && selectedOption.value) {
                const hours = parseInt(selectedOption.value);
                const priceStr = selectedOption.dataset.price || '0';
                const price = parseFloat(priceStr.replace(/,/g, '')) || 0;

                const checkedGuestsCount = document.querySelectorAll('#extendAdditionalGuestSection .extend-guest-checkbox:checked').length;
                const guestFee = checkedGuestsCount * 300;

                // Update cost display
                document.getElementById('regularCostDisplay').textContent = '₱' + (price + guestFee).toFixed(2);

                // Calculate new check-out time
                if (currentBookingData && currentBookingData.check_out) {
                    const currentCheckOut = new Date(currentBookingData.check_out);
                    const newCheckOut = new Date(currentCheckOut);
                    newCheckOut.setHours(newCheckOut.getHours() + hours);

                    document.getElementById('regularNewCheckOut').value = formatDateTime(newCheckOut);
                }
            } else {
                document.getElementById('regularCostDisplay').textContent = '₱0.00';
                if (currentBookingData && currentBookingData.check_out) {
                    document.getElementById('regularNewCheckOut').value = formatDateTime(currentBookingData.check_out);
                }
            }
        }

        function loadBundleOptions() {
            const bundleSelect = document.getElementById('bundleSelect');
            bundleSelect.innerHTML = '<option value="">Select Bundle</option>';

            if (currentBookingData && currentBookingData.room) {
                const roomType = currentBookingData.room || '';
                const normalizedRoomType = roomType.toLowerCase().trim();

                console.log('Loading bundle options for:', normalizedRoomType);

                // Fetch promo data
                fetch('get_promos.php')
                    .then(response => response.json())
                    .then(data => {
                        bundleSelect.innerHTML = '<option value="">Select Bundle</option>';

                        if (data.success && data.promos && data.promos.length > 0) {
                            data.promos.forEach(promo => {
                                const promoTitle = (promo.title || '').toLowerCase().trim();
                                let shouldInclude = false;

                                // Check if promo matches room type
                                if (normalizedRoomType.includes('deluxe')) {
                                    shouldInclude = promoTitle.includes('deluxe') || promoTitle.includes('deluxe room');
                                } else if (normalizedRoomType.includes('premium 1') || normalizedRoomType.includes('premium1')) {
                                    shouldInclude = promoTitle.includes('premium 1') || promoTitle.includes('premium1');
                                } else if (normalizedRoomType.includes('premium 2') || normalizedRoomType.includes('premium2')) {
                                    shouldInclude = promoTitle.includes('premium 2') || promoTitle.includes('premium2');
                                }

                                if (shouldInclude) {
                                    const title = promo.title || 'Untitled Promo';
                                    let displayTitle12 = title;
                                    let displayTitle24 = title;
                                    const lowerTitle = title.toLowerCase();

                                    // Map to Package 1 (12hrs) and Package 2 (24hrs)
                                    if (lowerTitle.includes('premium 1') || lowerTitle.includes('premium1') ||
                                        lowerTitle.includes('premium 2') || lowerTitle.includes('premium2') ||
                                        lowerTitle.includes('deluxe')) {
                                        displayTitle12 = 'Package 1';
                                        displayTitle24 = 'Package 2';
                                    }

                                    // Add 12 hours option
                                    if (promo.price_12hrs && parseFloat(promo.price_12hrs) > 0) {
                                        const option12 = document.createElement('option');
                                        option12.value = `${promo.title}_12`;
                                        const timeLabel = displayTitle12.startsWith('Package') ? '12hrs' : '12 hrs';
                                        option12.textContent = `${displayTitle12} ${timeLabel} - ₱${promo.price_12hrs}`;
                                        option12.dataset.price = promo.price_12hrs;
                                        option12.dataset.hours = '12';
                                        option12.dataset.promoTitle = promo.title;
                                        bundleSelect.appendChild(option12);
                                    }

                                    // Add 24 hours option
                                    if (promo.price_24hrs && parseFloat(promo.price_24hrs) > 0) {
                                        const option24 = document.createElement('option');
                                        option24.value = `${promo.title}_24`;
                                        const timeLabel = displayTitle24.startsWith('Package') ? '24hrs' : '24 hrs';
                                        option24.textContent = `${displayTitle24} ${timeLabel} - ₱${promo.price_24hrs}`;
                                        option24.dataset.price = promo.price_24hrs;
                                        option24.dataset.hours = '24';
                                        option24.dataset.promoTitle = promo.title;
                                        bundleSelect.appendChild(option24);
                                    }
                                }
                            });
                        } else {
                            console.log('No promo data found');
                            bundleSelect.innerHTML = '<option value="">No bundles available</option>';
                        }

                        // Pre-select the existing bundle if available
                        const existingBundleRate = parseFloat(currentBookingData.extend_bundle_rate) || 0;
                        const existingTotalHours = currentBookingData.existing_extend_hours || 0;
                        const existingTotalMinutes = parseInt(currentBookingData.extend_minutes) || 0;
                        let bundleMatchedHours = 0;

                        if (existingBundleRate > 0) {
                            // Match by RATE (strip commas from formatted strings like "2,089.00")
                            let bundleFound = false;
                            for (let i = 0; i < bundleSelect.options.length; i++) {
                                const option = bundleSelect.options[i];
                                const optPrice = parseFloat((option.dataset.price || '').replace(/,/g, '')) || 0;
                                if (Math.abs(optPrice - existingBundleRate) < 0.01) {
                                    bundleSelect.selectedIndex = i;
                                    bundleMatchedHours = parseInt(option.dataset.hours) || 0;
                                    bundleFound = true;
                                    calculateBundleCost();
                                    break;
                                }
                            }

                            // If no rate match, add custom option using stored rate
                            if (!bundleFound && existingTotalHours > 0) {
                                const customOpt = document.createElement('option');
                                customOpt.value = `custom_${existingTotalHours}`;
                                customOpt.dataset.hours = existingTotalHours;
                                customOpt.dataset.price = existingBundleRate;
                                customOpt.textContent = `${existingTotalHours} hrs Bundle - \u20b1${existingBundleRate.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} (Extended)`;
                                customOpt.selected = true;
                                bundleSelect.appendChild(customOpt);
                                bundleMatchedHours = existingTotalHours;
                                calculateBundleCost();
                            }
                        } else if (existingTotalHours > 0) {
                            // No bundle rate - match by hours directly
                            for (let i = 0; i < bundleSelect.options.length; i++) {
                                const option = bundleSelect.options[i];
                                if (option.dataset.hours == existingTotalHours) {
                                    bundleSelect.selectedIndex = i;
                                    bundleMatchedHours = existingTotalHours;
                                    calculateBundleCost();
                                    break;
                                }
                            }
                        }

                        // Pre-fill Duration tab with leftover hours/minutes stacked on top of bundle
                        // e.g. total=13h30m, bundle=12hrs → duration-only = 1h30m
                        const durationOnlyHours = Math.max(0, existingTotalHours - bundleMatchedHours);
                        if (durationOnlyHours > 0 || existingTotalMinutes > 0) {
                            document.getElementById('extendHours').value = durationOnlyHours;
                            document.getElementById('extendMinutes').value = existingTotalMinutes;
                        }
                    })
                    .catch(error => {
                        console.error('Error loading bundle options:', error);
                        bundleSelect.innerHTML = '<option value="">Error loading bundles</option>';
                    });
            } else {
                console.log('No booking data available');
                bundleSelect.innerHTML = '<option value="">No room selected</option>';
            }
        }

        function calculateBundleCost() {
            const bundleSelect = document.getElementById('bundleSelect');
            const selectedOption = bundleSelect.options[bundleSelect.selectedIndex];

            if (selectedOption && selectedOption.value) {
                const hours = parseInt(selectedOption.dataset.hours);
                const priceStr = selectedOption.dataset.price || '0';
                const price = parseFloat(priceStr.replace(/,/g, '')) || 0;

                const checkedGuestsCount = document.querySelectorAll('#extendAdditionalGuestSection .extend-guest-checkbox:checked').length;
                const guestFee = checkedGuestsCount * 300;

                // Update cost display
                document.getElementById('bundleCostDisplay').textContent = '₱' + (price + guestFee).toFixed(2);

                // Calculate new check-out time
                if (currentBookingData && currentBookingData.check_out) {
                    const currentCheckOut = new Date(currentBookingData.check_out);
                    const newCheckOut = new Date(currentCheckOut);
                    newCheckOut.setHours(newCheckOut.getHours() + hours);

                    document.getElementById('bundleNewCheckOut').value = formatDateTime(newCheckOut);
                }
            } else {
                document.getElementById('bundleCostDisplay').textContent = '₱0.00';
                if (currentBookingData && currentBookingData.check_out) {
                    document.getElementById('bundleNewCheckOut').value = formatDateTime(currentBookingData.check_out);
                }
            }
        }

        function generateBundleBreakfastFields(guestCount) {
            const container = document.getElementById('bundleBreakfastContainer');
            const guestLabel = document.getElementById('bundleBreakfastGuestLabel');

            console.log('generateBundleBreakfastFields called with guestCount:', guestCount);
            console.log('Container element:', container);
            console.log('Guest label element:', guestLabel);

            if (!container) {
                console.error('bundleBreakfastContainer not found!');
                return;
            }

            container.innerHTML = ''; // Clear existing fields

            // Determine how many saved breakfasts exist from prior extensions
            let savedBreakfastsCount = 0;
            if (typeof currentBookingData !== 'undefined' && currentBookingData && currentBookingData.extend_bundle_breakfast) {
                savedBreakfastsCount = currentBookingData.extend_bundle_breakfast.split('|').filter(s => s.trim() !== '').length;
            }

            // Use whichever is larger: room capacity or number of saved breakfasts
            const capacity = Math.max(parseInt(guestCount) || 0, savedBreakfastsCount);

            console.log('Parsed capacity:', capacity, '(guestCount:', guestCount, ', savedBreakfastsCount:', savedBreakfastsCount, ')');

            // Update the "Guest - X" label
            if (guestLabel) {
                guestLabel.textContent = `Guest - ${capacity}`;
            }

            if (capacity === 0) {
                console.log('Capacity is 0, not generating fields');
                return;
            }

            console.log('Fetching breakfast items from get_breakfast.php...');

            // Fetch breakfast options from get_breakfast.php
            fetch('get_breakfast.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Breakfast items response:', data);

                    const breakfastOptions = ['Select Breakfast', 'None'];
                    if (data.success && data.items) {
                        data.items.forEach(item => {
                            const optionText = `${item.food_name.toUpperCase()} - ₱${parseFloat(item.price).toFixed(2)}`;
                            breakfastOptions.push(optionText);
                        });
                    }

                    console.log('Breakfast options:', breakfastOptions);
                    console.log('Generating', capacity, 'breakfast fields');

                    let savedBreakfasts = [];
                    if (typeof currentBookingData !== 'undefined' && currentBookingData && currentBookingData.extend_bundle_breakfast) {
                        savedBreakfasts = currentBookingData.extend_bundle_breakfast.split('|').map(s => s.trim());
                    }

                    // Generate fields for each guest
                    for (let i = 0; i < capacity; i++) {
                        const fieldWrapper = document.createElement('div');
                        fieldWrapper.style.cssText = 'display: flex; gap: 8px; align-items: center; margin-bottom: 8px;';

                        const select = document.createElement('select');
                        select.id = `bundleBreakfast_${i}`;
                        select.name = `breakfast_${i}`;
                        select.className = 'extend-field-input';
                        select.style.flex = '1';
                        select.style.cursor = 'pointer';
                        select.style.background = '#ffffff';
                        select.style.backgroundColor = '#ffffff';

                        // Populate with breakfast options
                        breakfastOptions.forEach(opt => {
                            const option = document.createElement('option');
                            if (opt === 'Select Breakfast' || opt === 'None') {
                                option.value = opt;
                                option.textContent = opt;
                            } else {
                                // Extract food name without price for value
                                const foodName = opt.split(' - ')[0].trim();
                                option.value = `1 ${foodName} (Promo)`;
                                option.textContent = `${foodName} (Promo)`;
                            }
                            select.appendChild(option);
                        });

                        // Pre-select saved breakfast if it exists
                        if (savedBreakfasts.length > i && savedBreakfasts[i] !== '') {
                            const targetVal = savedBreakfasts[i].toLowerCase();
                            for (let j = 0; j < select.options.length; j++) {
                                if (select.options[j].value.toLowerCase() === targetVal) {
                                    select.selectedIndex = j;
                                    break;
                                }
                            }
                        }

                        const quantityInput = document.createElement('input');
                        quantityInput.type = 'number';
                        quantityInput.id = `bundleBreakfastQty_${i}`;
                        quantityInput.name = `breakfast_qty_${i}`;
                        quantityInput.className = 'extend-field-input';
                        quantityInput.min = '1';
                        quantityInput.value = '1';
                        quantityInput.placeholder = 'Qty';
                        quantityInput.style.width = '80px';

                        fieldWrapper.appendChild(select);
                        fieldWrapper.appendChild(quantityInput);
                        container.appendChild(fieldWrapper);

                        console.log('Added breakfast field', i);
                    }

                    console.log('Finished generating breakfast fields');
                })
                .catch(error => {
                    console.error('Error loading breakfast options:', error);
                });
        }
    </script>

    <!-- Extend Duration Modal -->
    <div id="extendDurationModal" class="modal-overlay" style="display:none;">
        <div class="extend-modal-container">
            <div class="extend-modal-header">
                <h2 style="margin: 0; font-size: 18px; font-weight: 600; color: #1a1a1a;">Extend Duration</h2>
                <span class="close" onclick="closeExtendDurationModal()"
                    style="font-size: 28px; color: #666; cursor: pointer; line-height: 1;">&times;</span>
            </div>
            <div class="extend-modal-body">
                <div class="extend-options-box">
                    <label class="extend-radio-option">
                        <input type="radio" name="extendType" id="extendByRoomRate" value="room_rate"
                            onchange="toggleExtendFields()">
                        <span class="extend-option-text">Room rate</span>
                    </label>
                    <label class="extend-radio-option">
                        <input type="radio" name="extendType" id="extendByDuration" value="duration"
                            onchange="toggleExtendFields()">
                        <span class="extend-option-text">Duration</span>
                    </label>
                </div>

                <!-- Room Rate Buttons (hidden by default) -->
                <div id="extendRoomRateButtons"
                    style="display: none; margin-top: 0; width: 100%; gap: 16px; justify-content: center;">
                    <button type="button" class="extend-rate-btn" id="regularBtn"
                        onclick="selectRoomRateType('regular')">
                        Regular
                    </button>
                    <button type="button" class="extend-rate-btn" id="bundleBtn" onclick="selectRoomRateType('bundle')">
                        Bundle
                    </button>
                </div>

                <!-- Regular Section (hidden by default) -->
                <div id="extendRegularSection" style="display: none; margin-top: 24px; width: 100%;">
                    <div class="extend-field-group" style="margin-bottom: 16px;">
                        <label class="extend-field-label">Duration</label>
                        <select id="regularDuration" class="extend-field-input" onchange="calculateRegularCost()">
                            <option value="">Select Duration</option>
                        </select>
                    </div>

                    <div class="extend-field-row">
                        <div class="extend-field-group">
                            <label class="extend-field-label">Current Check-Out</label>
                            <input type="text" id="regularCurrentCheckOut" class="extend-field-input" readonly>
                        </div>
                        <div class="extend-field-group">
                            <label class="extend-field-label">New Check-Out</label>
                            <input type="text" id="regularNewCheckOut" class="extend-field-input" readonly>
                        </div>
                    </div>

                    <div class="extend-cost-display" id="regularCostDisplay">
                        ₱0.00
                    </div>
                </div>

                <!-- Bundle Section (hidden by default) -->
                <div id="extendBundleSection" style="display: none; margin-top: 24px; width: 100%;">
                    <div class="extend-field-group" style="margin-bottom: 16px;">
                        <label class="extend-field-label">Bundle</label>
                        <select id="bundleSelect" class="extend-field-input" onchange="calculateBundleCost()">
                            <option value="">Select Bundle</option>
                        </select>
                    </div>

                    <div class="extend-field-group" style="margin-bottom: 16px;">
                        <label class="extend-field-label">Breakfast</label>
                        <label id="bundleBreakfastGuestLabel"
                            style="font-size: 13px; color: #666; margin-bottom: 8px; display: block; margin-top: 0;">Guest
                            - 2</label>
                        <div id="bundleBreakfastContainer"></div>
                    </div>

                    <div class="extend-field-row">
                        <div class="extend-field-group">
                            <label class="extend-field-label">Current Check-Out</label>
                            <input type="text" id="bundleCurrentCheckOut" class="extend-field-input" readonly>
                        </div>
                        <div class="extend-field-group">
                            <label class="extend-field-label">New Check-Out</label>
                            <input type="text" id="bundleNewCheckOut" class="extend-field-input" readonly>
                        </div>
                    </div>

                    <div class="extend-cost-display" id="bundleCostDisplay">
                        ₱0.00
                    </div>
                </div>

                <!-- Duration Fields (hidden by default) -->
                <div id="extendDurationFields" style="display: none; margin-top: 0; width: 100%;">
                    <div class="extend-field-row">
                        <div class="extend-field-group">
                            <label class="extend-field-label">Additional Hours</label>
                            <input type="number" id="extendHours" class="extend-field-input" min="0" value="0"
                                oninput="calculateExtendCost()">
                        </div>
                        <div class="extend-field-group">
                            <label class="extend-field-label">Unit</label>
                            <input type="text" class="extend-field-input" value="Hours" readonly>
                        </div>
                    </div>

                    <div class="extend-field-row">
                        <div class="extend-field-group">
                            <label class="extend-field-label">Additional Minutes</label>
                            <select id="extendMinutes" class="extend-field-input" onchange="calculateExtendCost()">
                                <option value="0">0</option>
                                <option value="30">30</option>
                            </select>
                        </div>
                        <div class="extend-field-group">
                            <label class="extend-field-label">Unit</label>
                            <input type="text" class="extend-field-input" value="Minutes" readonly>
                        </div>
                    </div>

                    <div class="extend-field-row">
                        <div class="extend-field-group">
                            <label class="extend-field-label">Current Check-Out</label>
                            <input type="text" id="currentCheckOut" class="extend-field-input" readonly>
                        </div>
                        <div class="extend-field-group">
                            <label class="extend-field-label">New Check-Out</label>
                            <input type="text" id="newCheckOut" class="extend-field-input" readonly>
                        </div>
                    </div>

                    <div class="extend-cost-display" id="extendCostDisplay">
                        ₱0.00
                    </div>
                </div>

                <!-- Guest Extension Checkbox Container -->
                <div id="extendAdditionalGuestSection" style="display: none; margin-top: 15px; border-top: 1px solid #e5e7eb; padding-top: 15px; margin-bottom: 10px; flex-direction: column; gap: 10px;">
                </div>
            </div>
            <div class="extend-modal-footer">
                <button type="button" class="extend-btn extend-btn-cancel"
                    onclick="closeExtendDurationModal()">Cancel</button>
                <button type="button" class="extend-btn extend-btn-update"
                    onclick="submitExtendDuration()">Update</button>
            </div>
        </div>
    </div>

    <style>
        /* Extension Modal Styles - Matching Booking.html */
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .extend-modal-container {
            background-color: #ffffff;
            border-radius: 8px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .extend-modal-header {
            padding: 20px 24px;
            background: #ffffff;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .extend-modal-body {
            padding: 32px 48px 28px 48px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }

        .extend-options-box {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 80px;
            border: 2px solid #03583c;
            border-radius: 8px;
            padding: 18px 40px;
            background: #ffffff;
            margin-bottom: 24px;
            margin-left: 0;
            margin-right: 0;
        }

        .extend-radio-option {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .extend-radio-option input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            margin: 0;
            accent-color: #383838ff;
        }

        .extend-option-text {
            font-size: 15px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }

        .extend-field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 16px;
            width: 100%;
        }

        .extend-field-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .extend-field-label {
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
        }

        .extend-field-input {
            padding: 11px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            color: #1f2937;
            font-family: 'Poppins', sans-serif;
            background: #ffffff !important;
            background-color: #ffffff !important;
        }

        select.extend-field-input {
            background: #ffffff !important;
            background-color: #ffffff !important;
            background-image: none !important;
            cursor: pointer !important;
        }

        #regularDuration {
            background: #ffffff !important;
            background-color: #ffffff !important;
            cursor: pointer !important;
        }

        #extendMinutes {
            background: #ffffff !important;
            background-color: #ffffff !important;
            cursor: pointer !important;
        }

        #bundleSelect {
            background: #ffffff !important;
            background-color: #ffffff !important;
            cursor: pointer !important;
        }

        /* Breakfast dropdowns in Bundle section */
        #bundleBreakfastContainer select {
            background: #ffffff !important;
            background-color: #ffffff !important;
            cursor: pointer !important;
        }

        .extend-field-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            background: #ffffff !important;
        }

        .extend-field-input:read-only {
            background: #f3f4f6 !important;
            color: #6b7280;
            cursor: not-allowed;
        }

        .extend-cost-display {
            padding: 14px 20px;
            background: #408D69;
            color: white;
            font-size: 20px;
            font-weight: 600;
            text-align: center;
            border-radius: 8px;
            margin-top: 8px;
            margin-bottom: 0;
            margin-left: 0;
            margin-right: 0;
            letter-spacing: 0.5px;
        }

        .extend-rate-btn {
            flex: 1;
            padding: 12px 24px;
            background: #ffffff;
            color: #374151;
            border: 1px solid #000000ff;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
        }

        .extend-rate-btn:hover {
            background: #dfdfdfff;
            border-color: #000000ff;
            color: #374151;
        }

        .extend-rate-btn:active {
            background: #f3f4f6;
        }

        .extend-rate-btn.active {
            background: #408D69;
            color: white;
            border-color: #10b981;
        }

        .extend-rate-btn.active:hover {
            background: #059669;
            color: white;
        }

        .extend-modal-footer {
            padding: 20px 24px;
            background: #ffffff;
            border-top: 1px solid #e5e5e5;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .extend-btn {
            padding: 10px 28px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
        }

        .extend-btn-update {
            background: #19800fff;
            color: white;
        }

        .extend-btn-update:hover {
            background: #059669;
        }

        .extend-btn-cancel {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .extend-btn-cancel:hover {
            background: #e5e7eb;
        }

        @media (max-width: 600px) {
            .extend-modal-body {
                padding: 30px 20px;
            }

            .extend-options-box {
                flex-direction: column;
                gap: 20px;
                padding: 20px;
            }
        }
    </style>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Booking Details</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editBookingId">

                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Booking ID</span>
                        <span class="detail-value" id="modalBookingId">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Room</span>
                        <input type="text" class="detail-input" id="modalRoom">
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-item full-width">
                        <span class="detail-label">Modification Reason <span style="color: red;"
                                id="modificationReasonRequired">*</span></span>
                        <textarea class="detail-input" id="modalModificationReason" rows="2"
                            placeholder="Enter reason for modification (required for first-time modifications)"
                            style="resize: vertical; min-height: 50px;"></textarea>
                        <small id="modificationReasonHint"
                            style="color: #666; font-size: 11px; display: none; margin-top: 4px;">
                            Previous modification reason is stored. You can update it or leave it as is.
                        </small>
                    </div>
                </div>

                <div class="detail-divider"></div>

                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Booking Type</span>
                        <div style="display: flex; gap: 20px; align-items: center; padding: 8px 0;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="radio" name="bookingType" id="modalBookingTypeWalkin" value="Walk-in"
                                    style="cursor: pointer;">
                                <span style="font-size: 14px; color: #212121;">Walk-in</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="radio" name="bookingType" id="modalBookingTypeReservation"
                                    value="Reservation" style="cursor: pointer;">
                                <span style="font-size: 14px; color: #212121;">Reservation</span>
                            </label>
                        </div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Type of Guest</span>
                        <select class="detail-input" id="modalGuestType" onchange="handleGuestTypeChange()">
                            <option value="Solo">Solo</option>
                            <option value="Duo">Duo</option>
                            <option value="Family">Family</option>
                            <option value="Group">Group</option>
                            <option value="Company">Company</option>
                        </select>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label" id="guestNamesLabel">Guest Names</span>
                        <input type="text" class="detail-input" id="modalGuestNames">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Reason for Stay</span>
                        <input type="text" class="detail-input" id="modalReasonForStay" placeholder="Enter reason">
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Contact No.</span>
                        <input type="text" class="detail-input" id="modalContactNo" placeholder="Enter contact number">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Address</span>
                        <input type="text" class="detail-input" id="modalAddress" placeholder="Enter address">
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Request</span>
                        <input type="text" class="detail-input" id="modalRequest">
                    </div>
                    <div class="detail-item">
                        <!-- Empty space for alignment -->
                    </div>
                </div>

                <!-- Vehicle Details Section -->
                <div class="detail-divider"></div>
                <div class="detail-row">
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <span class="detail-label" style="font-weight: 600; color: #1f2937;">Vehicle Details</span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Type of Vehicle</span>
                        <select class="detail-input" id="modalVehicleType">
                            <option value="">Vehicle Type</option>
                            <option value="Motorcycle">Motorcycle</option>
                            <option value="Tricycle">Tricycle</option>
                            <option value="Pickup">Pick-up</option>
                            <option value="Car">Car</option>

                        </select>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Plate Number</span>
                        <input type="text" class="detail-input" id="modalPlateNumber" placeholder="Plate number">
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <span class="detail-label">Description</span>
                        <textarea class="detail-input" id="modalVehicleDescription"
                            placeholder="Additional vehicle details" rows="2" style="resize: vertical;"></textarea>
                    </div>
                </div>

                <!-- Transfer Details Section -->
                <div class="detail-divider"></div>
                <div class="detail-row">
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <span class="detail-label" style="font-weight: 600; color: #1f2937;">Transfer Details</span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Transfer Room From</span>
                        <input type="text" class="detail-input" id="modalTransferRoomFrom"
                            placeholder="Original room (e.g., Transient 994)">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Transfer Refund Amount</span>
                        <input type="number" class="detail-input" id="modalTransferRefundAmount" placeholder="0.00"
                            step="0.01" min="0">
                    </div>
                </div>

                <!-- Company-specific fields (hidden by default) -->
                <div id="companyFields" style="display: none;">
                    <div class="detail-row">
                        <div class="detail-item">
                            <span class="detail-label">Contact Person Name</span>
                            <input type="text" class="detail-input" id="modalContactPersonName"
                                placeholder="Enter contact person">
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">TIN No.</span>
                            <input type="text" class="detail-input" id="modalTinNo"
                                placeholder="Enter TIN number (Optional)">
                        </div>
                    </div>
                </div>

                <div class="detail-divider"></div>

                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Check-In</span>
                        <input type="datetime-local" class="detail-input" id="modalCheckIn">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Check-Out</span>
                        <input type="datetime-local" class="detail-input" id="modalCheckOut">
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-item" style="display:none;">
                        <span class="detail-label">Duration</span>
                        <select class="detail-input" id="modalDuration">
                            <option value="">Loading...</option>
                        </select>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Referral Code</span>
                        <input type="text" class="detail-input" id="modalReferralCode">
                    </div>
                </div>

                <div class="detail-divider"></div>

                <div class="detail-row" style="display:none;">
                    <div class="detail-item">
                        <span class="detail-label">Promo</span>
                        <select class="detail-input" id="modalPromo" onchange="handleModalPromoChange()">
                            <option value="">No Promo</option>
                        </select>
                    </div>
                    <div class="detail-item">
                        <!-- Empty span -->
                    </div>
                </div>

                <div class="detail-row" style="display:none;">
                    <div class="detail-item full-width">
                        <div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; margin-top: 8px;">
                            <span class="detail-label" style="margin-bottom: 12px; display: block;">Breakfast</span>
                            <div id="modalBreakfastContainer"
                                style="margin-bottom: 12px; display: flex; flex-direction: column; gap: 8px;">
                                <!-- dynamically populated -->
                            </div>
                            <button type="button" onclick="addModalBreakfastDropdown()"
                                style="width: 100%; padding: 10px 16px; background: #4CAF50; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                + Add Breakfast
                            </button>
                            <input type="hidden" id="modalBreakfast" value="">
                        </div>
                    </div>
                </div>

                <input type="hidden" id="modalAdditionalCharges" name="additional_charges" value="[]">
                <input type="hidden" id="modalAdditional" value="">

                <input type="hidden" id="modalAdditionalGuest" value="">
                <input type="hidden" id="modalAdditionalPet" value="">

                <div class="detail-divider"></div>

                <input type="hidden" id="modalDiscountCount" value="">
                <input type="hidden" id="modalDiscountAmount" value="">
                <input type="hidden" id="modalDiscountId" value="">
                <input type="hidden" id="modalPaymentMethod">

                <!-- Payment Method Section -->
                <div class="detail-row" id="paymentHistorySection">
                    <div class="detail-item full-width">
                        <span class="detail-label">Payment History</span>
                        <div id="modalPaymentHistoryContent" style="margin-top:8px;">
                            <span style="color:#9ca3af; font-size:13px;">No payment method available.</span>
                        </div>
                    </div>
                </div>

                <!-- Reservation Amount hidden (no longer displayed) -->
                <span id="modalReservationAmount" style="display:none;"></span>

                <!-- Reservation Cash Payment Fields -->
                <div class="detail-row" id="reservationCashPaymentRow" style="display: none;">
                    <div class="detail-item full-width">
                        <span class="detail-label">Reservation Cash Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalReservationCash"
                            placeholder="Enter reservation cash amount" oninput="updateReservationAmountDisplay()">
                    </div>
                </div>

                <!-- Reservation G-cash Payment Fields -->
                <div class="detail-row" id="reservationGcashPaymentRow" style="display: none;">
                    <div class="detail-item">
                        <span class="detail-label">Reservation G-cash Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalReservationGcash"
                            placeholder="Enter reservation G-cash amount" oninput="updateReservationAmountDisplay()">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Reservation G-cash Reference No.</span>
                        <input type="text" class="detail-input" id="modalReservationGcashReference"
                            placeholder="Enter G-cash reference number">
                    </div>
                </div>

                <!-- Reservation Maya Payment Fields -->
                <div class="detail-row" id="reservationMayaPaymentRow" style="display: none;">
                    <div class="detail-item">
                        <span class="detail-label">Reservation Maya Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalReservationMaya"
                            placeholder="Enter reservation Maya amount" oninput="updateReservationAmountDisplay()">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Reservation Maya Reference No.</span>
                        <input type="text" class="detail-input" id="modalReservationMayaReference"
                            placeholder="Enter Maya reference number">
                    </div>
                </div>

                <!-- Reservation Instapay Payment Fields -->
                <div class="detail-row" id="reservationInstapayPaymentRow" style="display: none;">
                    <div class="detail-item">
                        <span class="detail-label">Reservation Instapay Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalReservationInstapay"
                            placeholder="Enter reservation Instapay amount" oninput="updateReservationAmountDisplay()">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Reservation Instapay Reference No.</span>
                        <input type="text" class="detail-input" id="modalReservationInstapayReference"
                            placeholder="Enter Instapay reference number">
                    </div>
                </div>

                <!-- Reservation Online Banking Payment Fields -->
                <div class="detail-row" id="reservationOnlineBankingPaymentRow" style="display: none;">
                    <div class="detail-item">
                        <span class="detail-label">Reservation Online Banking Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalReservationOnlineBanking"
                            placeholder="Enter reservation Online Banking amount"
                            oninput="updateReservationAmountDisplay()">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Reservation Online Banking Reference No.</span>
                        <input type="text" class="detail-input" id="modalReservationOnlineBankingReference"
                            placeholder="Enter Online Banking reference number">
                    </div>
                </div>

                <!-- Reservation Airbnb Payment Fields -->
                <div class="detail-row" id="reservationAirbnbPaymentRow" style="display: none;">
                    <div class="detail-item">
                        <span class="detail-label">Reservation Airbnb Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalReservationAirbnb"
                            placeholder="Enter reservation Airbnb amount" oninput="updateReservationAmountDisplay()">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Reservation Airbnb Reference No.</span>
                        <input type="text" class="detail-input" id="modalReservationAirbnbReference"
                            placeholder="Enter Airbnb reference number">
                    </div>
                </div>

                <!-- Single Payment Amount Fields -->
                <div class="detail-row" id="cashOnlyAmountRow" style="display: none;">
                    <div class="detail-item full-width">
                        <span class="detail-label">Cash Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalCashAmount3"
                            placeholder="Enter cash amount">
                    </div>
                </div>
                <div class="detail-row" id="gcashOnlyAmountRow" style="display: none;">
                    <div class="detail-item full-width">
                        <span class="detail-label">G-cash Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalGcashAmount3"
                            placeholder="Enter G-cash amount">
                    </div>
                </div>
                <div class="detail-row" id="mayaOnlyAmountRow" style="display: none;">
                    <div class="detail-item full-width">
                        <span class="detail-label">Maya Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalMayaAmount3"
                            placeholder="Enter Maya amount">
                    </div>
                </div>

                <!-- Split Payment Amount Fields - Row 1 -->
                <div class="detail-row" id="cashGcashAmountRow" style="display: none;">
                    <div class="detail-item">
                        <span class="detail-label">Cash Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalCashAmount"
                            placeholder="Enter cash amount">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">G-cash Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalGcashAmount"
                            placeholder="Enter G-cash amount">
                    </div>
                </div>

                <!-- Split Payment Amount Fields - Row 2 -->
                <div class="detail-row" id="cashMayaAmountRow" style="display: none;">
                    <div class="detail-item">
                        <span class="detail-label">Cash Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalCashAmount2"
                            placeholder="Enter cash amount">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Maya Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalMayaAmount"
                            placeholder="Enter Maya amount">
                    </div>
                </div>

                <!-- Split Payment Amount Fields - Row 3 -->
                <div class="detail-row" id="gcashMayaAmountRow" style="display: none;">
                    <div class="detail-item">
                        <span class="detail-label">G-cash Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalGcashAmount2"
                            placeholder="Enter G-cash amount">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Maya Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalMayaAmount2"
                            placeholder="Enter Maya amount">
                    </div>
                </div>

                <!-- Reference Fields - Row 1 -->
                <div class="detail-row" id="gcashReferenceField" style="display: none;">
                    <div class="detail-item">
                        <span class="detail-label">G-cash Reference Number</span>
                        <input type="text" class="detail-input" id="modalGcashReference"
                            placeholder="Enter G-cash reference number">
                    </div>
                    <div class="detail-item" id="mayaReferenceInline">
                        <span class="detail-label">Maya Reference Number</span>
                        <input type="text" class="detail-input" id="modalMayaReference"
                            placeholder="Enter Maya reference number">
                    </div>
                </div>

                <!-- Reference Fields - Row 2 (if Maya only) -->
                <div class="detail-row" id="mayaReferenceField" style="display: none;">
                    <div class="detail-item full-width">
                        <span class="detail-label">Maya Reference Number</span>
                        <input type="text" class="detail-input" id="modalMayaReference2"
                            placeholder="Enter Maya reference number">
                    </div>
                </div>

                <div class="detail-row" style="display: none;">
                    <div class="detail-item">
                        <span class="detail-label">Payment Status</span>
                        <span id="modalPaymentStatus" class="status-badge">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Booking Status</span>
                        <span id="modalStatus" class="status-badge">-</span>
                    </div>
                </div>

                <!-- Encoder Information -->
                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Encoder (Check-in)</span>
                        <input type="text" class="detail-input" id="modalEncoder" placeholder="Encoder name" readonly
                            style="background-color: #f5f5f5;">
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Encoder (Check-out)</span>
                        <input type="text" class="detail-input" id="modalEncoderCheckout"
                            placeholder="Encoder checkout name" readonly style="background-color: #f5f5f5;">
                    </div>
                </div>

                <div class="detail-divider"></div>

                <!-- Cancellation Section -->
                <div class="detail-row">
                    <div class="detail-item full-width">
                        <span class="detail-label">Reason for Cancellation</span>
                        <textarea class="detail-input" id="modalCancellationReason" rows="3"
                            placeholder="Cancellation Reason" style="resize: vertical; min-height: 60px;"></textarea>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-item">
                        <span class="detail-label">Cancellation Refund Amount</span>
                        <input type="number" step="0.01" class="detail-input" id="modalCancellationRefund"
                            placeholder="0.00" min="0">
                    </div>
                    <div class="detail-item">
                        <!-- Empty space for alignment -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-secondary" onclick="closeEditModal()">Close</button>
                <button class="modal-btn modal-btn-primary" onclick="updateBooking()">Update</button>
            </div>
        </div>
    </div>

    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #ffffff;
            margin: 3% auto;
            padding: 0;
            border-radius: 4px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            padding: 20px 24px;
            background: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #212121;
        }

        .close {
            color: #757575;
            font-size: 24px;
            font-weight: 300;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #212121;
        }

        .modal-body {
            padding: 24px;
            margin: 0;
            max-height: 60vh;
            overflow-y: auto;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 16px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .detail-item.full-width {
            grid-column: 1 / -1;
        }

        .detail-label {
            font-size: 11px;
            font-weight: 600;
            color: #757575;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 14px;
            color: #212121;
            font-weight: 400;
        }

        .detail-input {
            font-size: 14px;
            color: #212121;
            font-weight: 400;
            padding: 8px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .detail-input:focus {
            outline: none;
            border-color: #5e5ce6;
        }

        .detail-input[type="datetime-local"] {
            font-family: inherit;
        }

        .detail-divider {
            height: 1px;
            background: #e0e0e0;
            margin: 16px 0;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            width: fit-content;
        }

        .status-badge.paid {
            background: #4caf50;
            color: white;
        }

        .status-badge.unpaid {
            background: #f44336;
            color: white;
        }

        .status-badge.confirmed,
        .status-badge.occupied {
            background: #2196f3;
            color: white;
        }

        .status-badge.available {
            background: #4caf50;
            color: white;
        }

        .status-badge.reserved {
            background: #ff9800;
            color: white;
        }

        .status-badge.pending {
            background: #ffc107;
            color: white;
        }

        .status-badge.out-of-order {
            background: #f44336;
            color: white;
        }

        .modal-footer {
            padding: 16px 24px;
            background: #fafafa;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 3px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .modal-btn-primary {
            background: #292929ff;
            color: white;
        }

        .modal-btn-primary:hover {
            background: #4b4b4bff;
        }

        .modal-btn-secondary {
            background: white;
            color: #424242;
            border: 1px solid #bdbdbd;
        }

        .modal-btn-secondary:hover {
            background: #f5f5f5;
        }

        .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f5f5f5;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #bdbdbd;
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #9e9e9e;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .detail-row {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .modal-header h2 {
                font-size: 16px;
            }
        }
    </style>
</body>

</html>