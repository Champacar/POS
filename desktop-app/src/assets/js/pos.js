/**
 * POS JavaScript
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

let cart = JSON.parse(localStorage.getItem('pos_cart')) || [];
let paymentMethod = 'cash';
let currentCategoryId = 'all';
let currentSearchQuery = '';
let lastCompletedSaleId = null;
let lastCompletedCustomer = '';

function printLastSaleLabel() {
    if (lastCompletedSaleId && typeof openLabelModal === 'function') {
        openLabelModal(lastCompletedSaleId, lastCompletedCustomer);
    } else {
        showToast('ຍັງບໍ່ມີຂໍ້ມູນການຂາຍລ່າສຸດ', 'warning');
    }
}

function saveCart() {
    localStorage.setItem('pos_cart', JSON.stringify(cart));
}

document.addEventListener('DOMContentLoaded', () => {
    if (cart.length > 0) {
        renderCart();
    } else {
        updateCartSummary();
    }
    initBarcodeScanner();
    updateHeldCartsCount();
    checkCurrentShift();

    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const visibleCards = Array.from(document.querySelectorAll('.product-card')).filter(c => c.style.display !== 'none');
                if (visibleCards.length > 0) {
                    visibleCards[0].focus();
                }
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (!query) return;

                // Check if scanned/typed query is an invoice QR code
                if (query.toUpperCase().startsWith('INV') || query.toUpperCase().startsWith('POS-')) {
                    checkScannedInvoiceInPos(query);
                    return;
                }

                const allCards = Array.from(document.querySelectorAll('.product-card'));
                const exactBarcodeCard = allCards.find(c => (c.dataset.barcode || '').toLowerCase() === query.toLowerCase());
                if (exactBarcodeCard) {
                    addToCart(exactBarcodeCard);
                    searchInput.value = '';
                    searchProducts('');
                    return;
                }

                const visibleCards = allCards.filter(c => c.style.display !== 'none');
                if (visibleCards.length === 1) {
                    addToCart(visibleCards[0]);
                    searchInput.value = '';
                    searchProducts('');
                } else if (visibleCards.length > 1) {
                    addToCart(visibleCards[0]);
                    searchInput.select();
                } else {
                    showToast(`ບໍ່ພົບສິນຄ້າ: ${query}`, 'warning');
                }
            }
        });
    }

    // Grid keyboard arrow navigation
    const productGrid = document.getElementById('productGrid');
    if (productGrid) {
        productGrid.addEventListener('keydown', (e) => {
            const activeCard = document.activeElement;
            if (!activeCard || !activeCard.classList.contains('product-card')) return;

            const visibleCards = Array.from(productGrid.querySelectorAll('.product-card')).filter(c => c.style.display !== 'none');
            const currentIndex = visibleCards.indexOf(activeCard);
            if (currentIndex === -1) return;

            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                const nextIndex = (currentIndex + 1) % visibleCards.length;
                visibleCards[nextIndex].focus();
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                const prevIndex = (currentIndex - 1 + visibleCards.length) % visibleCards.length;
                visibleCards[prevIndex].focus();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });
    }
});

// ===================================================
// Barcode Scanner (Keyboard Wedge) & POS Shortcuts
// ===================================================
let barcodeBuffer = '';
let barcodeTimeout = null;

function initBarcodeScanner() {
    document.addEventListener('keydown', (e) => {
        if (typeof Swal !== 'undefined' && Swal.isVisible()) return;

        const checkoutModal = document.getElementById('checkoutModal');
        const priceCheckModal = document.getElementById('priceCheckModal');
        const heldCartsModal = document.getElementById('heldCartsModal');
        const isCheckoutOpen = checkoutModal && checkoutModal.classList.contains('show');
        const isPriceCheckOpen = priceCheckModal && priceCheckModal.classList.contains('show');
        const isHeldCartsOpen = heldCartsModal && heldCartsModal.classList.contains('show');

        // Global POS Function Key Shortcuts (F1: Help, F2: Search, F4: Price Check, F7: Drawer, F8: Hold, F9: Checkout, Escape: Close Modals)
        if (e.key === 'F1') {
            e.preventDefault();
            showToast('ຄີລັດ POS: F2 (ຄົ້ນຫາ/ບາໂຄດ), F4 (ເຊັກລາຄາ), F7 (ເປີດລິ້ນຊັກ), F8 (ພັກບິນ), F9 (ຊຳລະເງິນ), Esc (ປິດ)', 'info', 4000);
            return;
        }
        if (e.key === 'F7') {
            e.preventDefault();
            manualOpenDrawer();
            return;
        }
        if (isCheckoutOpen) {
            if (e.altKey && e.key === '1') { e.preventDefault(); selectPaymentMethod('cash'); return; }
            if (e.altKey && e.key === '2') { e.preventDefault(); selectPaymentMethod('transfer'); return; }
            if (e.altKey && e.key === '3') { e.preventDefault(); selectPaymentMethod('split'); return; }
            if (e.altKey && e.key === '4') { e.preventDefault(); selectPaymentMethod('credit'); return; }
        }
        if (e.key === 'F2') {
            if (isCheckoutOpen || isPriceCheckOpen || isHeldCartsOpen) return;
            e.preventDefault();
            const searchInput = document.getElementById('productSearch');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
            return;
        }
        if (e.key === 'F4') {
            if (isCheckoutOpen) return;
            e.preventDefault();
            openPriceCheckModal();
            return;
        }
        if (e.key === 'F8') {
            if (isCheckoutOpen || isPriceCheckOpen || isHeldCartsOpen) return;
            e.preventDefault();
            holdCart();
            return;
        }
        if (e.key === 'F9') {
            if (isPriceCheckOpen || isHeldCartsOpen || isCheckingOut) return;
            e.preventDefault();
            if (isCheckoutOpen) {
                confirmCheckout();
            } else {
                processCheckout();
            }
            return;
        }
        if (e.key === 'Escape') {
            const labelModal = document.getElementById('medicineLabelModal');
            if (labelModal && labelModal.classList.contains('show')) {
                closeModal('medicineLabelModal');
                return;
            }
            if (isPriceCheckOpen) {
                closePriceCheckModal();
                return;
            }
            if (isHeldCartsOpen) {
                closeHeldCartsModal();
                return;
            }
            if (isCheckoutOpen) {
                closeCheckoutModal();
                return;
            }
        }

        // Ignore if focused on input fields (except the search box)
        const active = document.activeElement;
        const isSearchBox = active && active.id === 'productSearch';
        if (active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName) && !isSearchBox) {
            return;
        }

        // Barcode scanners typically fire rapidly and end with Enter
        if (e.key === 'Enter' && barcodeBuffer.length >= 4) {
            e.preventDefault();
            const scannedCode = barcodeBuffer;
            barcodeBuffer = '';
            handleBarcodeInput(scannedCode);
            return;
        }

        // Only accept printable characters
        if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
            barcodeBuffer += e.key;
            clearTimeout(barcodeTimeout);
            barcodeTimeout = setTimeout(() => {
                barcodeBuffer = '';
            }, 100); // Reset if typing is slow (not a scanner)
        }
    });
}

function handleBarcodeInput(barcode) {
    const cards = Array.from(document.querySelectorAll('.product-card'));
    let matchedCard = null;
    let matchedOpt = {};

    for (const card of cards) {
        if ((card.dataset.barcode || '').toLowerCase() === barcode.toLowerCase()) {
            matchedCard = card;
            break;
        }
        let multi = [];
        try { multi = JSON.parse(card.dataset.multiBarcodes || '[]'); } catch(e) {}
        const found = multi.find(m => (m.barcode || '').toLowerCase() === barcode.toLowerCase());
        if (found) {
            matchedCard = card;
            matchedOpt = { unit: found.unit, price: found.price, multiplier: found.multiplier };
            break;
        }
    }

    if (matchedCard) {
        addToCart(matchedCard, matchedOpt);
        const searchInput = document.getElementById('productSearch');
        if (searchInput && searchInput.value) {
            searchInput.value = '';
            searchProducts('');
        }
    } else {
        const cleanCode = barcode.trim();
        // Check if scanned barcode is an Invoice Number QR Code (starts with INV or POS-)
        if (cleanCode.toUpperCase().startsWith('INV') || cleanCode.toUpperCase().startsWith('POS-')) {
            checkScannedInvoiceInPos(cleanCode);
            return;
        }

        const searchInput = document.getElementById('productSearch');
        if (searchInput) {
            searchInput.value = barcode;
            searchProducts(barcode);
            searchInput.select();
        }
        showToast(`ບໍ່ພົບສິນຄ້າບາໂຄດ: ${barcode}`, 'warning');
    }
}

async function checkScannedInvoiceInPos(invoiceNumber) {
    try {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
        const res = await fetchAPI(`${baseUrl}api/sales.php?invoice=${encodeURIComponent(invoiceNumber)}`);
        if (res.success && res.sale) {
            const sale = res.sale;
            const payLabel = sale.payment_method === 'cash' ? 'ເງິນສົດ' : (sale.payment_method === 'transfer' ? 'ເງິນໂອນ' : 'ຈ່າຍແຍກ');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: 'ພົບເລກທີບິນ (Invoice QR)',
                    html: `
                        <div style="text-align:left; background:#f8fafc; padding:12px; border-radius:8px; font-size:13px; line-height:1.6; border:1px solid #e2e8f0;">
                            <div><strong>ເລກບິນ:</strong> <span style="font-family:monospace; font-weight:700; color:#1e8e3e;">${escapeHTML(sale.invoice_number)}</span></div>
                            <div><strong>ວັນທີ:</strong> ${escapeHTML(sale.created_at)}</div>
                            <div><strong>ລູກຄ້າ:</strong> ${escapeHTML(sale.customer_name || '-')}</div>
                            <div><strong>ຍອດລວມ:</strong> <strong style="color:#1e8e3e;">${formatCurrency(sale.total_amount)} ກີບ</strong> (${payLabel})</div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-print"></i> ພິມໃບບິນ',
                    cancelButtonText: '<i class="fas fa-history"></i> ໄປໜ້າປະຫວັດການຂາຍ',
                    confirmButtonColor: '#1e8e3e',
                    cancelButtonColor: '#1f6feb',
                    showCloseButton: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        printReceipt(sale.id);
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        window.location.href = `sales_history.php?search=${encodeURIComponent(sale.invoice_number)}`;
                    }
                });
            } else {
                window.location.href = `sales_history.php?search=${encodeURIComponent(invoiceNumber)}`;
            }
            return;
        }
    } catch (e) {
        console.error('Invoice scan lookup error:', e);
    }

    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.value = invoiceNumber;
        searchProducts(invoiceNumber);
        searchInput.select();
    }
    showToast(`ບໍ່ພົບສິນຄ້າ ຫຼື ໃບບິນ: ${invoiceNumber}`, 'warning');
}

// ===================================================
// Add to Cart
// ===================================================
function addToCart(el, options = {}) {
    const id = parseInt(el.dataset.id);
    const name = el.dataset.name;
    const price = options.price !== null && options.price !== undefined ? parseFloat(options.price) : parseFloat(el.dataset.price);
    const stock = parseInt(el.dataset.stock);
    const unit = options.unit || el.dataset.unit || 'ແຜງ';
    const isExpired = el.dataset.expired === '1';

    if (isExpired) {
        showToast(`ຢາ '${name}' ໝົດອາຍຸແລ້ວ! ບໍ່ສາມາດຂາຍໃຫ້ລູກຄ້າໄດ້`, 'error', 3000);
        return;
    }

    const expDate = el.dataset.expiry;
    if (expDate) {
        const daysLeft = Math.round((new Date(expDate) - new Date()) / (1000 * 60 * 60 * 24));
        if (daysLeft >= 0 && daysLeft <= 30) {
            showToast(`ແຈ້ງເຕືອນ: '${name}' ຈະໝົດອາຍຸໃນ ${daysLeft} ວັນ (${expDate})`, 'warning', 2500);
        }
    }

    if (stock <= 0) {
        showToast('ສິນຄ້າໝົດສະຕ໋ອກ!', 'error');
        return;
    }

    const existing = cart.find(item => item.id === id && item.unit === unit);
    if (existing) {
        if (existing.quantity >= stock) {
            showToast('ບໍ່ສາມາດເພີ່ມໄດ້ - ສະຕ໋ອກບໍ່ພຽງພໍ', 'warning');
            return;
        }
        existing.quantity++;
    } else {
        cart.push({ id, name, price, quantity: 1, stock, unit });
    }

    saveCart();
    renderCart();
    showToast(`ເພີ່ມ ${name} (${unit}) ແລ້ວ`, 'success', 1500);
}

// ===================================================
// Render Cart
// ===================================================
function renderCart() {
    const container = document.getElementById('cartItems');
    const emptyState = document.getElementById('cartEmpty');
    const countEl = document.getElementById('cartItemCount');

    if (cart.length === 0) {
        container.innerHTML = '';
        container.appendChild(createEmptyState());
        countEl.textContent = '0 ລາຍການໃນກະຕ່າ';
        updateCartSummary();
        return;
    }

    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    countEl.textContent = `${totalItems} ລາຍການໃນກະຕ່າ`;

    container.innerHTML = cart.map((item, index) => `
        <div class="cart-item" data-index="${index}">
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-price">${formatCurrency(item.price)} ກີບ</div>
            </div>
            <div class="cart-item-qty">
                <button class="qty-btn" aria-label="ຫຼຸດຈຳນວນ" onclick="changeQty(${index}, -1)">
                    <i class="fas fa-minus"></i>
                </button>
                <input type="number" class="qty-input" min="1" max="${item.stock}" value="${item.quantity}" aria-label="ຈຳນວນສິນຄ້າ" style="width:46px;height:36px;text-align:center;font-weight:600;border:1px solid var(--border);border-radius:var(--radius-sm);font-family:inherit;font-size:0.85rem;padding:2px;" onchange="setQty(${index}, this.value)" onblur="if(!this.value){ this.value = ${item.quantity}; }">
                <button class="qty-btn" aria-label="ເພີ່ມຈຳນວນ" onclick="changeQty(${index}, 1)">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div class="cart-item-total">${formatCurrency(item.price * item.quantity)} ກີບ</div>
            <button class="cart-item-remove" aria-label="ລຶບລາຍການອອກຈາກກະຕ່າ" onclick="removeFromCart(${index})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');

    updateCartSummary();
}

function createEmptyState() {
    const div = document.createElement('div');
    div.className = 'empty-state';
    div.id = 'cartEmpty';
    div.innerHTML = `
        <i class="fas fa-file-invoice"></i>
        <p>ຍັງບໍ່ມີລາຍການໃດໆ</p>
        <span style="font-size:0.75rem;color:var(--text-light);">ເລືອກລາຍການໃສ່ກະຕ່າ</span>
    `;
    return div;
}

// ===================================================
// Change Quantity
// ===================================================
function changeQty(index, delta) {
    const item = cart[index];
    if (!item) return;

    const newQty = item.quantity + delta;
    if (newQty <= 0) {
        removeFromCart(index);
        return;
    }
    if (newQty > item.stock) {
        showToast('ສະຕ໋ອກບໍ່ພຽງພໍ', 'warning');
        return;
    }

    item.quantity = newQty;
    saveCart();
    renderCart();
}

function setQty(index, val) {
    const item = cart[index];
    if (!item) return;

    if (val === '' || val === null || typeof val === 'undefined') {
        return;
    }

    const newQty = parseInt(val, 10);
    if (isNaN(newQty) || newQty <= 0) {
        removeFromCart(index);
        return;
    }
    if (newQty > item.stock) {
        showToast(`ສະຕ໋ອກຄົງເຫຼືອພຽງແຕ່ ${item.stock}`, 'warning');
        item.quantity = item.stock;
    } else {
        item.quantity = newQty;
    }

    saveCart();
    renderCart();

    const qtyInput = document.querySelector(`.cart-item[data-index="${index}"] .qty-input`);
    if (qtyInput) {
        qtyInput.focus();
        qtyInput.select();
    }
}

// ===================================================
// Remove from Cart
// ===================================================
function removeFromCart(index) {
    cart.splice(index, 1);
    saveCart();
    renderCart();
}

async function clearCart() {
    if (cart.length === 0) {
        if (typeof showToast === 'function') {
            showToast('ກະຕ່າວ່າງເປົ່າຢູ່ແລ້ວ', 'info');
        }
        return;
    }
    let confirmed = false;
    if (typeof confirmDelete === 'function') {
        confirmed = await confirmDelete('ລ້າງລາຍການທັງໝົດ?');
    } else if (typeof Swal !== 'undefined') {
        const res = await Swal.fire({
            title: 'ຢືນຢັນການດຳເນີນການ',
            text: 'ລ້າງລາຍການທັງໝົດ?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2e7d32',
            cancelButtonColor: '#d32f2f',
            confirmButtonText: 'ຕົກລົງ',
            cancelButtonText: 'ຍົກເລີກ',
            customClass: { popup: 'swal2-lao-popup' }
        });
        confirmed = res.isConfirmed;
    } else {
        confirmed = window.confirm('ລ້າງລາຍການທັງໝົດ?');
    }
    if (!confirmed) return;
    cart = [];
    document.getElementById('discountInput').value = 0;
    saveCart();
    renderCart();
}

// ===================================================
// Update Summary
// ===================================================
function updateCartSummary() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    let discount = parseFloat(document.getElementById('discountInput').value) || 0;
    if (discount < 0) {
        discount = 0;
        document.getElementById('discountInput').value = 0;
    }
    if (subtotal > 0 && discount > subtotal) {
        discount = subtotal;
        document.getElementById('discountInput').value = discount;
        showToast('ສ່ວນຫຼຸດບໍ່ສາມາດເກີນຍອດລວມໄດ້', 'warning', 2000);
    }
    const afterDiscount = Math.max(0, subtotal - discount);
    
    let tax = 0;
    let total = afterDiscount;
    
    if (typeof TAX_TYPE !== 'undefined') {
        if (TAX_TYPE === 'excluded') {
            tax = afterDiscount * (TAX_RATE / 100);
            total = afterDiscount + tax;
        } else if (TAX_TYPE === 'included') {
            tax = afterDiscount - (afterDiscount / (1 + (TAX_RATE / 100)));
            total = afterDiscount;
        } else {
            tax = 0;
            total = afterDiscount;
        }
    } else {
        // Fallback
        tax = afterDiscount * (TAX_RATE / 100);
        total = afterDiscount + tax;
    }

    document.getElementById('subtotalDisplay').textContent = formatCurrency(subtotal) + ' ກີບ';
    document.getElementById('taxDisplay').textContent = formatCurrency(tax) + ' ກີບ';
    document.getElementById('totalDisplay').textContent = formatCurrency(total) + ' ກີບ';

    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) {
        checkoutBtn.disabled = (cart.length === 0);
    }
    const btnHoldCart = document.getElementById('btnHoldCart');
    if (btnHoldCart) {
        btnHoldCart.disabled = (cart.length === 0);
    }
    const btnClearCart = document.getElementById('btnClearCart');
    if (btnClearCart) {
        btnClearCart.disabled = (cart.length === 0);
    }

    // Update mobile floating cart pill
    const mobileCartBar = document.getElementById('posMobileCartBar');
    if (mobileCartBar) {
        if (cart.length > 0 && window.innerWidth <= 991) {
            mobileCartBar.style.display = 'flex';
            const mobileCount = document.getElementById('posMobileCartCount');
            const mobileTotal = document.getElementById('posMobileCartTotal');
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            if (mobileCount) mobileCount.textContent = totalItems + ' ລາຍການ';
            if (mobileTotal) mobileTotal.textContent = formatCurrency(total) + ' ກີບ';
        } else {
            mobileCartBar.style.display = 'none';
        }
    }

    // Live sync totals to CFD
    localStorage.setItem('pos_totals', JSON.stringify({
        subtotal: subtotal,
        tax: tax,
        total: total
    }));
}

// ===================================================
// Filter Category & Search Products (Combined)
// ===================================================
function applyProductFilters() {
    const cards = document.querySelectorAll('.product-card');
    const lowerQuery = (currentSearchQuery || '').toLowerCase().trim();
    let visibleCount = 0;

    cards.forEach(card => {
        const matchesCategory = (currentCategoryId === 'all' || String(card.dataset.category) === String(currentCategoryId));
        const name = (card.dataset.name || '').toLowerCase();
        const barcode = (card.dataset.barcode || '').toLowerCase();
        let multi = [];
        try { multi = JSON.parse(card.dataset.multiBarcodes || '[]'); } catch(e) {}
        const multiMatch = multi.some(m => (m.barcode || '').toLowerCase().includes(lowerQuery));
        const matchesSearch = !lowerQuery || name.includes(lowerQuery) || barcode.includes(lowerQuery) || multiMatch;

        if (matchesCategory && matchesSearch) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const countEl = document.getElementById('productCount');
    if (countEl) {
        countEl.textContent = visibleCount + ' ລາຍການ';
    }

    let noResultEl = document.getElementById('posSearchNoResult');
    if (!noResultEl && cards.length > 0) {
        noResultEl = document.createElement('div');
        noResultEl.id = 'posSearchNoResult';
        noResultEl.className = 'empty-state';
        noResultEl.style.gridColumn = '1 / -1';
        noResultEl.style.padding = '40px 20px';
        noResultEl.style.textAlign = 'center';
        noResultEl.innerHTML = `<i class="fas fa-search" style="font-size: 2.5rem; color: var(--text-light); margin-bottom: 10px;"></i><p style="color: var(--text-dark); font-weight: 600;">ບໍ່ພົບລາຍການຢາທີ່ກົງກັບຄຳຄົ້ນຫາ</p><button class="btn btn-outline btn-sm" style="margin-top: 10px;" onclick="resetProductFilters()"><i class="fas fa-undo"></i> ລ້າງຄຳຄົ້ນຫາ</button>`;
        document.getElementById('productGrid')?.appendChild(noResultEl);
    }
    if (noResultEl) {
        noResultEl.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
    }
}

function resetProductFilters() {
    currentCategoryId = 'all';
    currentSearchQuery = '';
    const searchInput = document.getElementById('productSearch');
    if (searchInput) searchInput.value = '';
    const tabs = document.querySelectorAll('#categoryTabs .tab');
    tabs.forEach((t, idx) => {
        if (idx === 0) t.classList.add('active');
        else t.classList.remove('active');
    });
    applyProductFilters();
}

function filterCategory(categoryId, tab) {
    currentCategoryId = categoryId;
    document.querySelectorAll('#categoryTabs .tab').forEach(t => t.classList.remove('active'));
    if (tab) tab.classList.add('active');
    applyProductFilters();
}

function searchProducts(query) {
    currentSearchQuery = query || '';
    applyProductFilters();
}

// ===================================================
// Process Checkout (Opens Modal)
// ===================================================
let checkoutTotal = 0;
let checkoutSubtotal = 0;
let checkoutTax = 0;
let checkoutDiscount = 0;

async function processCheckout() {
    if (cart.length === 0) {
        showToast('ກະລຸນາເພີ່ມສິນຄ້າກ່ອນ', 'warning');
        return;
    }

    checkoutSubtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    checkoutDiscount = parseFloat(document.getElementById('discountInput').value) || 0;
    const afterDiscount = Math.max(0, checkoutSubtotal - checkoutDiscount);
    
    checkoutTax = 0;
    checkoutTotal = afterDiscount;
    
    if (typeof TAX_TYPE !== 'undefined') {
        if (TAX_TYPE === 'excluded') {
            checkoutTax = afterDiscount * (TAX_RATE / 100);
            checkoutTotal = afterDiscount + checkoutTax;
        } else if (TAX_TYPE === 'included') {
            checkoutTax = afterDiscount - (afterDiscount / (1 + (TAX_RATE / 100)));
            checkoutTotal = afterDiscount;
        } else {
            checkoutTax = 0;
            checkoutTotal = afterDiscount;
        }
    } else {
        checkoutTax = afterDiscount * (TAX_RATE / 100);
        checkoutTotal = afterDiscount + checkoutTax;
    }

    // Sync to CFD
    localStorage.setItem('pos_totals', JSON.stringify({
        subtotal: checkoutSubtotal,
        tax: checkoutTax,
        total: checkoutTotal
    }));

    // Open Modal
    document.getElementById('checkoutTotalDisplay').textContent = formatCurrency(checkoutTotal) + ' ກີບ';
    if (typeof openModal === 'function') {
        openModal('checkoutModal');
    } else {
        document.getElementById('checkoutModal').classList.add('show');
    }
    
    // Default method & currency
    if (typeof setCheckoutCurrency === 'function') {
        setCheckoutCurrency('LAK');
    }
    selectPaymentMethod('cash');
}

// ===================================================
// Multi-Currency & Cash Drawer State
// ===================================================
let checkoutCurrency = 'LAK';
const exchangeRates = {
    LAK: 1,
    THB: (typeof EXCHANGE_RATE_THB !== 'undefined' && Number(EXCHANGE_RATE_THB) > 0) ? Number(EXCHANGE_RATE_THB) : 680,
    USD: (typeof EXCHANGE_RATE_USD !== 'undefined' && Number(EXCHANGE_RATE_USD) > 0) ? Number(EXCHANGE_RATE_USD) : 22000
};

function setCheckoutCurrency(curr) {
    checkoutCurrency = curr;
    ['LAK', 'THB', 'USD'].forEach(c => {
        const btn = document.getElementById('btnCurr' + c);
        if (btn) {
            if (c === curr) btn.classList.add('active');
            else btn.classList.remove('active');
        }
    });

    const rateNotice = document.getElementById('currencyRateNotice');
    const rateText = document.getElementById('currencyRateText');
    const foreignTotalText = document.getElementById('currencyForeignTotalText');
    const cashInputLabel = document.getElementById('cashInputLabel');
    const cashForeignEq = document.getElementById('cashForeignEquivalent');
    const changeForeignEl = document.getElementById('changeForeignDisplay');

    if (curr === 'LAK') {
        if (rateNotice) rateNotice.style.display = 'none';
        if (cashInputLabel) cashInputLabel.textContent = 'ຮັບເງິນມາ (ກີບ)';
        if (cashForeignEq) cashForeignEq.style.display = 'none';
        if (changeForeignEl) changeForeignEl.style.display = 'none';
    } else if (curr === 'THB') {
        const rate = exchangeRates.THB;
        const totalInThb = checkoutTotal / rate;
        if (rateNotice) rateNotice.style.display = 'flex';
        if (rateText) rateText.textContent = `ອັດຕາແລກປ່ຽນ: 1 ฿ = ${formatCurrency(rate)} ກີບ`;
        if (foreignTotalText) foreignTotalText.textContent = `~ ${totalInThb.toFixed(2)} ฿`;
        if (cashInputLabel) cashInputLabel.textContent = 'ຮັບເງິນມາ (ບາດ / THB)';
    } else if (curr === 'USD') {
        const rate = exchangeRates.USD;
        const totalInUsd = checkoutTotal / rate;
        if (rateNotice) rateNotice.style.display = 'flex';
        if (rateText) rateText.textContent = `ອັດຕາແລກປ່ຽນ: 1 $ = ${formatCurrency(rate)} ກີບ`;
        if (foreignTotalText) foreignTotalText.textContent = `~ ${totalInUsd.toFixed(2)} $`;
        if (cashInputLabel) cashInputLabel.textContent = 'ຮັບເງິນມາ (ໂດລາ / USD)';
    }

    renderQuickCashButtons();

    const cashInput = document.getElementById('cashAmountInput');
    if (cashInput) {
        cashInput.value = '';
        cashInput.focus();
    }
    calculateChange();

    // Sync to CFD
    localStorage.setItem('pos_currency', JSON.stringify({
        currency: curr,
        rate: exchangeRates[curr] || 1
    }));
}

function renderQuickCashButtons() {
    const container = document.getElementById('quickCashButtonsContainer');
    if (!container) return;

    if (checkoutCurrency === 'LAK') {
        container.innerHTML = `
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(10000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+10k</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(20000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+20k</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(50000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+50k</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(100000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+100k</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(500000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+500k</button>
            <button type="button" class="btn btn-outline btn-sm" style="grid-column: span 2; color:var(--primary); border-color:var(--primary-light); padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:700; border-radius:8px;" onclick="setExactCash()">ພໍດີຍອດຊຳລະ</button>
            <button type="button" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:rgba(222,53,11,0.25); padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;" onclick="clearQuickCash()">ລ້າງ (0)</button>
        `;
    } else if (checkoutCurrency === 'THB') {
        const rate = exchangeRates.THB;
        const exactThbCeil = Math.ceil(checkoutTotal / rate);
        container.innerHTML = `
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(100)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+100 ฿</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(500)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+500 ฿</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(1000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+1,000 ฿</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(2000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+2,000 ฿</button>
            <button type="button" class="btn btn-outline btn-sm" style="grid-column: span 3; color:var(--primary); border-color:var(--primary-light); padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:700; border-radius:8px;" onclick="setExactCash()">ພໍດີ (~ ${exactThbCeil} ฿)</button>
            <button type="button" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:rgba(222,53,11,0.25); padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;" onclick="clearQuickCash()">ລ້າງ (0)</button>
        `;
    } else if (checkoutCurrency === 'USD') {
        const rate = exchangeRates.USD;
        const exactUsdCeil = Math.ceil(checkoutTotal / rate);
        container.innerHTML = `
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(10)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+$10</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(20)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+$20</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(50)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+$50</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(100)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+$100</button>
            <button type="button" class="btn btn-outline btn-sm" style="grid-column: span 3; color:var(--primary); border-color:var(--primary-light); padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:700; border-radius:8px;" onclick="setExactCash()">ພໍດີ (~ $${exactUsdCeil})</button>
            <button type="button" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:rgba(222,53,11,0.25); padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;" onclick="clearQuickCash()">ລ້າງ (0)</button>
        `;
    }
}

async function manualOpenDrawer() {
    if (typeof triggerCashDrawerKick === 'function') {
        const ok = await triggerCashDrawerKick({ silent: false });
        if (!ok) showToast('ບໍ່ສາມາດສັ່ງເປີດລິ້ນຊັກເງິນໄດ້', 'warning');
    } else {
        showToast('ສັ່ງເປີດລິ້ນຊັກເງິນ...', 'info');
        window.open('api/drawer.php?test=1', '_blank', 'width=200,height=200');
    }
}

function closeCheckoutModal() {
    if (typeof closeModal === 'function') {
        closeModal('checkoutModal');
    } else {
        document.getElementById('checkoutModal')?.classList.remove('show');
    }
    // Sync CFD back to idle
    localStorage.setItem('pos_state', JSON.stringify({ status: 'idle' }));
    const searchInput = document.getElementById('productSearch');
    if (searchInput) searchInput.focus();
}

let currentTransferQrAmount = null;

async function renderPosTransferQr(amount) {
    const qrSection = document.getElementById('transferInputSection');
    const container = document.getElementById('transferQrContainer');
    const amtEl = document.getElementById('transferAmountDisplay');
    const bankEl = document.getElementById('transferBankNameDisplay');
    const accEl = document.getElementById('transferAccountDisplay');
    
    if (amtEl) amtEl.textContent = formatCurrency(amount || 0);
    
    if (window.SHOP_DYNAMIC_QR) {
        if (bankEl && window.SHOP_DYNAMIC_QR.bankName) bankEl.textContent = window.SHOP_DYNAMIC_QR.bankName;
        if (accEl) accEl.textContent = window.SHOP_DYNAMIC_QR.accountName || window.SHOP_DYNAMIC_QR.accountNumber || '-';
    }

    if (!container) return;
    if (currentTransferQrAmount === amount && container.querySelector('svg')) {
        return;
    }

    container.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:28px; color:var(--primary);"></i>';
    try {
        const res = await fetch(`api/dynamic_qr.php?amount=${amount || 0}&currency=${checkoutCurrency || 'LAK'}&size=170`);
        const data = await res.json();
        if (data && data.success && data.svg) {
            currentTransferQrAmount = amount;
            container.innerHTML = data.svg;
        } else {
            container.innerHTML = '<div style="font-size:12px;color:var(--text-light);"><i class="fas fa-qrcode" style="font-size:32px;display:block;margin-bottom:4px;"></i>ບໍ່ສາມາດສ້າງ QR ໄດ້</div>';
        }
    } catch (e) {
        console.error('POS QR error:', e);
        container.innerHTML = '<div style="font-size:12px;color:var(--text-light);"><i class="fas fa-qrcode" style="font-size:32px;display:block;margin-bottom:4px;"></i>QR Code</div>';
    }
}

function selectPaymentMethod(method) {
    paymentMethod = method;
    // Update buttons UI
    ['cash', 'transfer', 'split', 'credit'].forEach(m => {
        const btn = document.getElementById('btnPay' + m.charAt(0).toUpperCase() + m.slice(1));
        if(btn) {
            if(m === method) btn.classList.add('active');
            else btn.classList.remove('active');
        }
    });

    // Update input sections
    const cashSection = document.getElementById('cashInputSection');
    const splitSection = document.getElementById('splitInputSection');
    const transferSection = document.getElementById('transferInputSection');

    if (cashSection) cashSection.style.display = (method === 'cash') ? 'block' : 'none';
    if (splitSection) splitSection.style.display = (method === 'split') ? 'block' : 'none';
    if (transferSection) transferSection.style.display = (method === 'transfer' || method === 'split') ? 'block' : 'none';

    // Reset inputs
    document.getElementById('cashAmountInput').value = '';
    const changeEl = document.getElementById('changeDisplay');
    if (changeEl) {
        changeEl.textContent = '0 ກີບ';
        changeEl.style.color = 'var(--primary)';
    }
    document.getElementById('splitCashInput').value = '';
    document.getElementById('splitTransferInput').value = '';
    document.getElementById('splitError').style.display = 'none';

    if (method === 'cash') {
        setTimeout(() => {
            document.getElementById('cashAmountInput')?.focus();
        }, 50);
    } else if (method === 'transfer') {
        renderPosTransferQr(checkoutTotal);
    } else if (method === 'split') {
        const splitTransfer = parseFloat(document.getElementById('splitTransferInput').value) || 0;
        renderPosTransferQr(splitTransfer);
    }

    // Sync CFD state
    localStorage.setItem('pos_state', JSON.stringify({
        status: 'checkout',
        payment_method: method,
        transfer_amount: method === 'transfer' ? checkoutTotal : 0
    }));
}

function calculateChange() {
    const rawInput = parseFloat(document.getElementById('cashAmountInput').value) || 0;
    const changeEl = document.getElementById('changeDisplay');
    const changeForeignEl = document.getElementById('changeForeignDisplay');
    const cashForeignEq = document.getElementById('cashForeignEquivalent');
    if (!changeEl) return;

    const rate = exchangeRates[checkoutCurrency] || 1;
    const isForeign = checkoutCurrency !== 'LAK';
    const sign = checkoutCurrency === 'THB' ? '฿' : '$';

    let cashLAK = rawInput;
    if (isForeign) {
        cashLAK = rawInput * rate;
        if (cashForeignEq) {
            if (rawInput > 0) {
                cashForeignEq.style.display = 'block';
                cashForeignEq.innerHTML = `ທຽບເທົ່າ: <strong>${formatCurrency(Math.round(cashLAK))} ກີບ</strong>`;
            } else {
                cashForeignEq.style.display = 'none';
            }
        }
    } else {
        if (cashForeignEq) cashForeignEq.style.display = 'none';
    }

    if (rawInput > 0 && cashLAK < checkoutTotal) {
        const deficitLAK = checkoutTotal - cashLAK;
        changeEl.textContent = '-' + formatCurrency(Math.round(deficitLAK)) + ' ກີບ (ຍັງບໍ່ຄົບ)';
        changeEl.style.color = 'var(--danger)';
        if (changeForeignEl) {
            if (isForeign) {
                const deficitForeign = deficitLAK / rate;
                changeForeignEl.style.display = 'block';
                changeForeignEl.style.color = 'var(--danger)';
                changeForeignEl.textContent = `ຍັງຂາດອີກ: ~ ${deficitForeign.toFixed(2)} ${sign}`;
            } else {
                changeForeignEl.style.display = 'none';
            }
        }
    } else {
        const changeLAK = Math.max(0, cashLAK - checkoutTotal);
        changeEl.textContent = formatCurrency(Math.round(changeLAK)) + ' ກີບ';
        changeEl.style.color = 'var(--primary)';
        if (changeForeignEl) {
            if (isForeign && rawInput > 0) {
                const changeForeign = changeLAK / rate;
                changeForeignEl.style.display = 'block';
                changeForeignEl.style.color = '#5e6c84';
                changeForeignEl.textContent = `(ຫຼື ທຽບເທົ່າ: ${changeForeign.toFixed(2)} ${sign})`;
            } else {
                changeForeignEl.style.display = 'none';
            }
        }
    }
}

function calculateSplit() {
    const cash = parseFloat(document.getElementById('splitCashInput').value) || 0;
    const transfer = parseFloat(document.getElementById('splitTransferInput').value) || 0;
    const errorEl = document.getElementById('splitError');
    if (cash + transfer >= checkoutTotal) {
        errorEl.style.display = 'none';
    } else {
        errorEl.style.display = 'block';
    }

    // Update POS modal transfer QR and CFD
    renderPosTransferQr(transfer);

    // Sync split transfer amount to CFD so QR code can show exact amount
    localStorage.setItem('pos_state', JSON.stringify({
        status: 'checkout',
        payment_method: 'split',
        transfer_amount: transfer
    }));
}

// ===================================================
// Tax Invoice Toggle & Rate calculation in Checkout
// ===================================================
function toggleTaxInvoiceSection() {
    const chk = document.getElementById('chkIssueTaxInvoice');
    const fields = document.getElementById('taxInvoiceFields');
    if (!chk || !fields) return;
    fields.style.display = chk.checked ? 'block' : 'none';
    if (chk.checked) {
        const custName = document.getElementById('customerNameInput')?.value?.trim();
        const compInput = document.getElementById('taxCustomerCompany');
        if (custName && compInput && !compInput.value) {
            compInput.value = custName;
        }
        onTaxRateChangedInCheckout();
    } else {
        // Reset to default tax calculation
        const net = Math.max(0, checkoutSubtotal - checkoutDiscount);
        if (typeof TAX_TYPE !== 'undefined' && TAX_TYPE === 'included') {
            checkoutTax = Math.round(net - (net / (1 + (TAX_RATE / 100))));
            checkoutTotal = net;
        } else if (typeof TAX_TYPE !== 'undefined' && TAX_TYPE === 'excluded') {
            checkoutTax = Math.round(net * (TAX_RATE / 100));
            checkoutTotal = net + checkoutTax;
        } else {
            checkoutTax = 0;
            checkoutTotal = net;
        }
        document.getElementById('checkoutTotalDisplay').textContent = formatCurrency(checkoutTotal) + ' ກີບ';
        const trAmt = document.getElementById('transferAmountDisplay');
        if (trAmt) trAmt.textContent = formatCurrency(checkoutTotal);
        if (paymentMethod === 'transfer') {
            renderPosTransferQr(checkoutTotal);
        }
        calculateChange();
    }
}

function onTaxRateChangedInCheckout() {
    const chk = document.getElementById('chkIssueTaxInvoice');
    if (!chk || !chk.checked) return;
    const rate = parseFloat(document.getElementById('taxVatRate')?.value) || 0;
    const net = Math.max(0, checkoutSubtotal - checkoutDiscount);
    checkoutTax = Math.round(net * (rate / 100));
    checkoutTotal = net + checkoutTax;
    document.getElementById('checkoutTotalDisplay').textContent = formatCurrency(checkoutTotal) + ' ກີບ';
    const trAmt = document.getElementById('transferAmountDisplay');
    if (trAmt) trAmt.textContent = formatCurrency(checkoutTotal);
    if (paymentMethod === 'transfer') {
        renderPosTransferQr(checkoutTotal);
    }
    calculateChange();
}

// ===================================================
// Confirm Checkout (API Call)
// ===================================================
let isCheckingOut = false;
async function confirmCheckout() {
    if (isCheckingOut) return;
    const btn = document.getElementById('confirmCheckoutBtn');
    if (btn && btn.disabled) return;

    let cashAmount = 0;
    let transferAmount = 0;
    let changeAmount = 0;
    let paidAmountForeign = 0;
    let changeAmountForeign = 0;
    const rate = exchangeRates[checkoutCurrency] || 1;

    if (paymentMethod === 'cash') {
        const rawCash = parseFloat(document.getElementById('cashAmountInput').value) || 0;
        if (checkoutCurrency !== 'LAK') {
            paidAmountForeign = rawCash;
            cashAmount = Math.round(rawCash * rate);
            if (rawCash > 0 && cashAmount < checkoutTotal) {
                showToast(`ເງິນສົດຮັບມາ (${rawCash} ${checkoutCurrency}) ໜ້ອຍກວ່າຍອດລວມ!`, 'warning');
                return;
            }
            if (rawCash === 0) {
                paidAmountForeign = Math.ceil(checkoutTotal / rate);
                cashAmount = Math.round(paidAmountForeign * rate);
            }
            changeAmount = Math.max(0, cashAmount - checkoutTotal);
            changeAmountForeign = changeAmount / rate;
        } else {
            cashAmount = rawCash;
            if (cashAmount > 0 && cashAmount < checkoutTotal) {
                showToast('ເງິນສົດຮັບມາໜ້ອຍກວ່າຍອດລວມ!', 'warning');
                return;
            }
            changeAmount = Math.max(0, cashAmount - checkoutTotal);
            if (cashAmount === 0) cashAmount = checkoutTotal; // auto fill if 0
        }
    } else if (paymentMethod === 'split') {
        cashAmount = parseFloat(document.getElementById('splitCashInput').value) || 0;
        transferAmount = parseFloat(document.getElementById('splitTransferInput').value) || 0;
        if (cashAmount + transferAmount < checkoutTotal) {
            document.getElementById('splitError').style.display = 'block';
            showToast('ຍອດແບ່ງຈ່າຍລວມກັນຍັງບໍ່ຄົບຕາມຍອດຊຳລະ', 'warning');
            return;
        }
    } else if (paymentMethod === 'transfer') {
        transferAmount = checkoutTotal;
    } else if (paymentMethod === 'credit') {
        const custName = document.getElementById('customerNameInput')?.value?.trim();
        if (!custName) {
            showToast('ກະລຸນາປ້ອນຊື່ລູກຄ້າສຳລັບການຕິດໜີ້!', 'warning');
            const custInput = document.getElementById('customerNameInput');
            if (custInput) {
                custInput.style.borderColor = 'var(--danger)';
                custInput.focus();
            }
            return;
        }
    }

    const isTaxInvoice = !!document.getElementById('chkIssueTaxInvoice')?.checked;
    let customerTaxId = null;
    let customerCompany = null;
    let customerAddress = null;
    let customTaxRate = null;
    if (isTaxInvoice) {
        customerCompany = document.getElementById('taxCustomerCompany')?.value?.trim() || null;
        customerTaxId = document.getElementById('taxCustomerTin')?.value?.trim() || null;
        customerAddress = document.getElementById('taxCustomerAddress')?.value?.trim() || null;
        customTaxRate = parseFloat(document.getElementById('taxVatRate')?.value) || 7;
    }

    const data = {
        items: cart.map(item => ({
            product_id: item.id,
            product_name: item.name,
            quantity: item.quantity,
            unit_price: item.price,
            total_price: item.price * item.quantity,
        })),
        subtotal: checkoutSubtotal,
        discount_amount: checkoutDiscount,
        tax_amount: checkoutTax,
        total_amount: checkoutTotal,
        payment_method: paymentMethod,
        cash_amount: cashAmount,
        transfer_amount: transferAmount,
        currency: checkoutCurrency,
        exchange_rate: rate,
        paid_amount_foreign: paidAmountForeign,
        change_amount_foreign: changeAmountForeign,
        change_amount: changeAmount,
        customer_name: document.getElementById('customerNameInput')?.value?.trim() || customerCompany || null,
        is_tax_invoice: isTaxInvoice ? 1 : 0,
        customer_tax_id: customerTaxId,
        customer_company: customerCompany,
        customer_address: customerAddress,
        tax_rate: customTaxRate,
    };

    try {
        isCheckingOut = true;
        const btn = document.getElementById('confirmCheckoutBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງດຳເນີນການ...';

        const result = await fetchAPI(BASE_URL + 'api/sales.php', {
            method: 'POST',
            body: JSON.stringify(data),
        });

        if (result.success) {
            // Show Success on CFD
            localStorage.setItem('pos_state', JSON.stringify({ status: 'success', change: changeAmount }));
            
            closeCheckoutModal();
            showToast('ບັນທຶກການຂາຍສຳເລັດ!', 'success');

            // Auto kick cash drawer on cash sale
            if (typeof ENABLE_CASH_DRAWER !== 'undefined' && ENABLE_CASH_DRAWER && typeof triggerCashDrawerKick === 'function') {
                if (paymentMethod === 'cash' || (paymentMethod === 'split' && cashAmount > 0)) {
                    triggerCashDrawerKick({ silent: true });
                }
            }

            cart = [];
            saveCart();
            document.getElementById('discountInput').value = 0;
            renderCart();

            let lastSaleId = result.sale_id;
            let lastCustomer = data.customer_name || '';
            lastCompletedSaleId = lastSaleId;
            lastCompletedCustomer = lastCustomer;
            const btnLast = document.getElementById('btnLastSaleLabel');
            if (btnLast) {
                btnLast.style.display = 'inline-flex';
            }

            if (result.sale_id) {
                printReceipt(result.sale_id);

                // If Tax Invoice was checked, open official Tax Invoice in new window
                if (result.is_tax_invoice) {
                    setTimeout(() => {
                        window.open(BASE_URL + 'tax_invoice.php?id=' + result.sale_id, '_blank');
                    }, 500);
                }

                // Prompt Pharmacist to print medicine labels immediately
                if (typeof Swal !== 'undefined' && typeof openLabelModal === 'function') {
                    Swal.fire({
                        title: 'ການຂາຍສຳເລັດ!',
                        html: `
                            <div style="text-align:center; padding:10px 0;">
                                <p style="font-size:1.05rem; color:var(--text-dark); margin-bottom:15px;">ທ່ານຕ້ອງການພິມ <strong>ສະຫຼາກຢາ / ຊອງຢາ</strong> ດ້ວຍຫຼືບໍ່?</p>
                                <div style="display:flex; justify-content:center; gap:10px; margin-top:15px;">
                                    <button type="button" class="btn btn-white" id="swalBtnSkip" onclick="Swal.close()">ຂ້າມ (ບໍ່ພິມ)</button>
                                    <button type="button" class="btn btn-primary" id="swalBtnPrintLabel" onclick="Swal.close(); if(typeof closeReceiptPreviewModal==='function')closeReceiptPreviewModal(); openLabelModal(${result.sale_id}, '${escapeHTML(lastCustomer)}')">
                                        <i class="fas fa-prescription-bottle-alt"></i> ພິມສະຫຼາກຢາ
                                    </button>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 10000
                    });
                }
            }

            setTimeout(() => {
                localStorage.setItem('pos_state', JSON.stringify({ status: 'idle' }));
                const searchInput = document.getElementById('productSearch');
                if (searchInput) {
                    searchInput.focus();
                }
            }, 3000); // Wait 3s so customer can see success screen
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການບັນທຶກ', 'error');
    } finally {
        isCheckingOut = false;
        const btn = document.getElementById('confirmCheckoutBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> ຢືນຢັນການຊຳລະ';
    }
}

// ===================================================
// Quick Cash Buttons
// ===================================================
function addQuickCash(amount) {
    const input = document.getElementById('cashAmountInput');
    const current = parseFloat(input.value) || 0;
    input.value = current + amount;
    calculateChange();
    input.focus();
}

function setExactCash() {
    const input = document.getElementById('cashAmountInput');
    if (!input) return;
    if (checkoutCurrency === 'LAK') {
        input.value = Math.ceil(checkoutTotal);
    } else {
        const rate = exchangeRates[checkoutCurrency] || 1;
        input.value = Math.ceil(checkoutTotal / rate);
    }
    calculateChange();
    input.focus();
}

function clearQuickCash() {
    const input = document.getElementById('cashAmountInput');
    input.value = '';
    calculateChange();
    input.focus();
}

// ===================================================
// Suspension (Hold/Resume Invoices)
// ===================================================
let heldCarts = JSON.parse(localStorage.getItem('pos_held_carts')) || [];

function saveHeldCarts() {
    localStorage.setItem('pos_held_carts', JSON.stringify(heldCarts));
    updateHeldCartsCount();
}

function updateHeldCartsCount() {
    const badge = document.getElementById('heldCartsCount');
    if (badge) {
        badge.textContent = heldCarts.length;
        badge.style.display = heldCarts.length > 0 ? 'block' : 'none';
    }
}

async function holdCart() {
    if (cart.length === 0) {
        showToast('ບໍ່ສາມາດພັກບິນເປົ່າໄດ້!', 'warning');
        return;
    }

    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const afterDiscount = Math.max(0, subtotal - discount);
    let total = afterDiscount;
    if (typeof TAX_TYPE !== 'undefined') {
        if (TAX_TYPE === 'excluded') {
            total = afterDiscount + (afterDiscount * (TAX_RATE / 100));
        }
    }

    let note = '';
    if (typeof Swal === 'undefined') {
        const promptVal = window.prompt('ປ້ອນຊື່ ຫຼື ໝາຍເຫດ ສຳລັບບິນພັກນີ້ (ບໍ່ບັງຄັບ):', '');
        if (promptVal === null) return;
        note = promptVal.trim() || `ບິນພັກ #${heldCarts.length + 1}`;
    } else {
        const nameResult = await Swal.fire({
            title: 'ພັກບິນ',
            text: 'ປ້ອນຊື່ ຫຼື ໝາຍເຫດ ສຳລັບບິນພັກນີ້ (ບໍ່ບັງຄັບ):',
            input: 'text',
            inputPlaceholder: 'ຊື່ລູກຄ້າ ຫຼື ໝາຍເຫດ...',
            showCancelButton: true,
            confirmButtonColor: '#2e7d32',
            cancelButtonColor: '#d32f2f',
            confirmButtonText: 'ຢືນຢັນ',
            cancelButtonText: 'ຍົກເລີກ',
            customClass: {
                popup: 'swal2-lao-popup'
            }
        });

        if (nameResult.isDismissed) return;
        note = (nameResult.value || '').trim() || `ບິນພັກ #${heldCarts.length + 1}`;
    }
    
    heldCarts.push({
        id: Date.now(),
        cart: [...cart],
        discount: discount,
        total: total,
        note: note,
        timestamp: new Date().toLocaleTimeString('lo-LA', { hour: '2-digit', minute: '2-digit' })
    });

    saveHeldCarts();
    
    // Reset active cart
    cart = [];
    document.getElementById('discountInput').value = 0;
    saveCart();
    renderCart();
    
    showToast('ພັກບິນຮຽບຮ້ອຍແລ້ວ', 'success');
}

function openHeldCartsModal() {
    const listContainer = document.getElementById('heldCartsList');
    if (heldCarts.length === 0) {
        listContainer.innerHTML = `
            <div style="text-align:center; color:var(--text-light); padding:40px 10px;">
                <i class="fas fa-folder-open" style="font-size:48px; opacity:0.3; margin-bottom:15px; display:block;"></i>
                <span>ບໍ່ມີລາຍການບິນທີ່ພັກໄວ້</span>
            </div>
        `;
    } else {
        listContainer.innerHTML = heldCarts.map((hc, idx) => `
            <div style="display:flex; justify-content:space-between; align-items:center; padding:15px; border-bottom:1px solid var(--border-light); background:#fff; margin-bottom:8px; border-radius:10px; box-shadow: 0 2px 5px rgba(0,0,0,0.02);">
                <div>
                    <strong style="color:var(--text-dark); font-size:1.05rem;">${hc.note}</strong>
                    <div style="font-size:0.8rem; color:var(--text-light); margin-top:4px;">
                        ເວລາ: ${hc.timestamp} | ລາຍການສິນຄ້າ: ${hc.cart.length} ລາຍການ
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="font-weight:700; color:var(--primary); font-size:1.1rem;">${formatCurrency(hc.total)} ₭</span>
                    <button class="btn btn-sm btn-primary" onclick="resumeCart(${hc.id})" style="padding:6px 12px;">
                        <i class="fas fa-play"></i> ດຶງຄືນ
                    </button>
                    <button class="btn btn-sm btn-white" onclick="deleteHeldCart(${hc.id})" style="color:var(--danger); border-color:rgba(222,53,11,0.2); padding:6px 10px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    if (typeof openModal === 'function') {
        openModal('heldCartsModal');
    } else {
        document.getElementById('heldCartsModal').classList.add('show');
    }
}

function closeHeldCartsModal() {
    if (typeof closeModal === 'function') {
        closeModal('heldCartsModal');
    } else {
        document.getElementById('heldCartsModal')?.classList.remove('show');
    }
    const searchInput = document.getElementById('productSearch');
    if (searchInput) searchInput.focus();
}

function resumeCart(id) {
    if (cart.length > 0) {
        if (typeof Swal === 'undefined') {
            if (window.confirm('ມີສິນຄ້າຢູ່ໃນກະຕ່າປັດຈຸບັນ, ຕ້ອງການພັກບິນປັດຈຸບັນກ່ອນ ຫຼື ບໍ່? (ກົດ OK ເພື່ອພັກບິນປັດຈຸບັນກ່ອນ, Cancel ເພື່ອຂຽນທັບ)')) {
                holdCurrentAndLoad(id);
            } else if (window.confirm('ຕ້ອງການຂຽນທັບກະຕ່າປັດຈຸບັນ ຫຼື ບໍ່?')) {
                loadHeldCart(id);
            }
            return;
        }
        Swal.fire({
            title: 'ຄຳເຕືອນ',
            text: 'ມີສິນຄ້າຢູ່ໃນກະຕ່າປັດຈຸບັນ, ຕ້ອງການພັກບິນປັດຈຸບັນກ່ອນ ຫຼື ບໍ່?',
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonColor: '#2e7d32',
            denyButtonColor: '#b26a00',
            cancelButtonColor: '#d32f2f',
            confirmButtonText: 'ພັກບິນປັດຈຸບັນກ່ອນ',
            denyButtonText: 'ຂຽນທັບກະຕ່າປັດຈຸບັນ',
            cancelButtonText: 'ຍົກເລີກ',
            customClass: {
                popup: 'swal2-lao-popup'
            }
        }).then(async (result) => {
            if (result.isConfirmed) {
                await holdCurrentAndLoad(id);
            } else if (result.isDenied) {
                loadHeldCart(id);
            }
        });
    } else {
        loadHeldCart(id);
    }
}

async function holdCurrentAndLoad(resumeId) {
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const afterDiscount = Math.max(0, subtotal - discount);
    let total = afterDiscount;
    if (typeof TAX_TYPE !== 'undefined' && TAX_TYPE === 'excluded') {
        total = afterDiscount + (afterDiscount * (TAX_RATE / 100));
    }
    
    heldCarts.push({
        id: Date.now(),
        cart: [...cart],
        discount: discount,
        total: total,
        note: `ບິນພັກ #${heldCarts.length + 1}`,
        timestamp: new Date().toLocaleTimeString('lo-LA', { hour: '2-digit', minute: '2-digit' })
    });
    
    loadHeldCart(resumeId);
}

function loadHeldCart(id) {
    const idx = heldCarts.findIndex(hc => hc.id === id);
    if (idx !== -1) {
        const hc = heldCarts[idx];
        cart = [...hc.cart];
        document.getElementById('discountInput').value = hc.discount;
        
        heldCarts.splice(idx, 1);
        saveHeldCarts();
        
        saveCart();
        renderCart();
        closeHeldCartsModal();
        showToast('ດຶງບິນພັກມາຮຽບຮ້ອຍແລ້ວ', 'success');
    }
}

async function deleteHeldCart(id) {
    const confirmed = await confirmDelete('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບບິນພັກນີ້?');
    if (confirmed) {
        heldCarts = heldCarts.filter(hc => hc.id !== id);
        saveHeldCarts();
        openHeldCartsModal();
        showToast('ລຶບບິນພັກຮຽບຮ້ອຍແລ້ວ', 'success');
    }
}

// ===================================================
// Price Check Modal Functions
// ===================================================
function openPriceCheckModal() {
    document.getElementById('priceCheckSearchInput').value = '';
    document.getElementById('priceCheckResult').style.display = 'none';
    document.getElementById('priceCheckPlaceholder').style.display = 'block';
    if (typeof openModal === 'function') {
        openModal('priceCheckModal');
    } else {
        document.getElementById('priceCheckModal').classList.add('show');
    }
    setTimeout(() => {
        document.getElementById('priceCheckSearchInput').focus();
    }, 200);
}

function closePriceCheckModal() {
    if (typeof closeModal === 'function') {
        closeModal('priceCheckModal');
    } else {
        document.getElementById('priceCheckModal')?.classList.remove('show');
    }
    const searchInput = document.getElementById('productSearch');
    if (searchInput) searchInput.focus();
}

function doPriceCheckSearch(query) {
    const resultContainer = document.getElementById('priceCheckResult');
    const placeholder = document.getElementById('priceCheckPlaceholder');
    
    if (query.trim().length === 0) {
        resultContainer.style.display = 'none';
        placeholder.style.display = 'block';
        return;
    }
    
    const cards = document.querySelectorAll('.product-card');
    const lowerQuery = query.toLowerCase();
    const matches = [];
    
    cards.forEach(card => {
        const name = card.dataset.name.toLowerCase();
        const barcode = (card.dataset.barcode || '').toLowerCase();
        if (name.includes(lowerQuery) || barcode.includes(lowerQuery)) {
            matches.push({
                id: card.dataset.id,
                name: card.dataset.name,
                price: parseFloat(card.dataset.price),
                stock: parseInt(card.dataset.stock),
                unit: card.dataset.unit || 'ແຜງ',
                barcode: card.dataset.barcode || '-',
            });
        }
    });
    
    if (matches.length === 0) {
        resultContainer.innerHTML = `
            <div style="text-align:center; color:var(--danger); padding:20px 10px; font-weight:600;">
                <i class="fas fa-exclamation-circle" style="font-size:24px; margin-bottom:10px; display:block;"></i>
                ບໍ່ພົບຂໍ້ມູນຢານີ້ໃນລະບົບ
            </div>
        `;
        resultContainer.style.display = 'block';
        placeholder.style.display = 'none';
    } else {
        resultContainer.innerHTML = matches.slice(0, 5).map(m => `
            <div style="background:#f8f9fa; border:1px solid var(--border-light); padding:15px; border-radius:12px; margin-bottom:10px; text-align: left;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <strong style="color:var(--text-dark); font-size:1.1rem; display:block;">${m.name}</strong>
                        <span style="font-size:0.8rem; color:var(--text-light);">
                            ບາໂຄດ: <strong style="font-family:monospace; color:var(--text-dark);">${m.barcode}</strong>
                        </span>
                    </div>
                    <span style="font-size:1.25rem; font-weight:800; color:var(--primary);">${formatCurrency(m.price)} ₭ <small style="font-weight:500;font-size:0.75rem;color:var(--text-light);">/ ${m.unit}</small></span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding-top:12px; border-top:1px dashed #ddd; font-size:0.9rem;">
                    <div>
                        <span style="color:var(--text-light);">ສະຕ໋ອກຄົງເຫຼືອ:</span>
                        <strong style="color:${m.stock <= 0 ? 'var(--danger)' : 'var(--primary)'};">${m.stock} ${m.stock <= 0 ? '(ໝົດ)' : m.unit}</strong>
                    </div>
                    <button class="btn btn-sm btn-primary" onclick="addFromPriceCheck(${m.id})" style="padding:4px 10px; font-size:0.8rem;" ${m.stock <= 0 ? 'disabled' : ''}>
                        <i class="fas fa-cart-plus"></i> ເພີ່ມເຂົ້າກະຕ່າ
                    </button>
                </div>
            </div>
        `).join('');
        resultContainer.style.display = 'block';
        placeholder.style.display = 'none';
    }
}

function addFromPriceCheck(productId) {
    const card = document.querySelector(`.product-card[data-id="${productId}"]`);
    if (card) {
        addToCart(card);
        closePriceCheckModal();
    }
}

// ===================================================
// Shift Management (ເປີດ-ປິດກະຂາຍ)
// ===================================================
let activeShiftData = null;

async function checkCurrentShift() {
    const btn = document.getElementById('btnShiftStatus');
    const textEl = document.getElementById('shiftStatusText');
    if (!btn || !textEl) return;

    try {
        const res = await fetchAPI(BASE_URL + 'api/shifts.php?action=current');
        if (res && res.success) {
            if (res.has_shift && res.shift) {
                activeShiftData = res.shift;
                btn.style.borderColor = 'var(--primary)';
                btn.style.color = 'var(--primary)';
                btn.style.background = 'var(--primary-bg)';
                textEl.innerHTML = `ກະ: <strong>${escapeHTML(res.shift.shift_number)}</strong> (ປິດກະ)`;
            } else {
                activeShiftData = null;
                btn.style.borderColor = '#ffab00';
                btn.style.color = '#b76e00';
                btn.style.background = '#fff8e6';
                textEl.innerHTML = `<i class="fas fa-play"></i> ເປີດກະຂາຍ (Start Shift)`;
            }
        }
    } catch (e) {
        console.warn('Failed to check shift status:', e);
    }
}

function handleShiftButtonClick() {
    if (activeShiftData) {
        openCloseShiftModal();
    } else {
        if (typeof openModal === 'function') {
            openModal('openShiftModal');
        } else {
            document.getElementById('openShiftModal')?.classList.add('show');
        }
        setTimeout(() => {
            document.getElementById('inputStartingCash')?.focus();
            document.getElementById('inputStartingCash')?.select();
        }, 100);
    }
}

async function confirmOpenShift() {
    const cashInput = document.getElementById('inputStartingCash');
    const noteInput = document.getElementById('inputOpenShiftNote');
    const startCash = parseFloat(cashInput?.value) || 0;
    const note = noteInput?.value?.trim() || '';

    const btn = document.getElementById('btnConfirmOpenShift');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງເປີດ...';
    }

    try {
        const res = await fetchAPI(BASE_URL + 'api/shifts.php?action=open', {
            method: 'POST',
            body: JSON.stringify({
                starting_cash: startCash,
                note: note
            })
        });

        if (res && res.success) {
            showToast(res.message || 'ເປີດກະຂາຍສຳເລັດ!', 'success');
            if (typeof closeModal === 'function') {
                closeModal('openShiftModal');
            } else {
                document.getElementById('openShiftModal')?.classList.remove('show');
            }
            await checkCurrentShift();
        } else {
            showToast(res.message || 'ບໍ່ສາມາດເປີດກະໄດ້', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການເປີດກະ', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> ຢືນຢັນເປີດກະ';
        }
    }
}

async function openCloseShiftModal() {
    try {
        showToast('ກຳລັງໂຫຼດຂໍ້ມູນຍອດກະ...', 'info', 1000);
        const res = await fetchAPI(BASE_URL + 'api/shifts.php?action=current');
        if (!res || !res.has_shift || !res.shift) {
            showToast('ບໍ່ພົບກະຂາຍທີ່ເປີດຢູ່', 'warning');
            await checkCurrentShift();
            return;
        }

        activeShiftData = res.shift;
        const shift = res.shift;

        const numEl = document.getElementById('closeShiftNumber');
        const openedEl = document.getElementById('closeShiftOpenedAt');
        const billsEl = document.getElementById('closeShiftBills');
        const salesEl = document.getElementById('closeShiftSalesTotal');
        const transferEl = document.getElementById('closeShiftTransferTotal');
        const expectedEl = document.getElementById('closeShiftExpectedCash');

        if (numEl) numEl.textContent = shift.shift_number;
        if (openedEl) openedEl.textContent = shift.opened_at ? shift.opened_at.substring(11, 16) : '-';
        if (billsEl) billsEl.textContent = shift.total_bills || 0;
        if (salesEl) salesEl.textContent = formatCurrency(shift.total_sales || 0) + ' ₭';
        if (transferEl) transferEl.textContent = formatCurrency(shift.total_transfer || 0) + ' ₭';
        if (expectedEl) expectedEl.textContent = formatCurrency(shift.expected_cash || 0) + ' ₭';

        // Reset counted inputs
        const lakIn = document.getElementById('inputCountedLak');
        const thbIn = document.getElementById('inputCountedThb');
        const usdIn = document.getElementById('inputCountedUsd');
        const noteIn = document.getElementById('inputCloseShiftNote');

        if (lakIn) lakIn.value = '';
        if (thbIn) thbIn.value = '';
        if (usdIn) usdIn.value = '';
        if (noteIn) noteIn.value = '';

        calculateShiftDifference();

        if (typeof openModal === 'function') {
            openModal('closeShiftModal');
        } else {
            document.getElementById('closeShiftModal')?.classList.add('show');
        }

        setTimeout(() => {
            if (lakIn) lakIn.focus();
        }, 150);
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການໂຫຼດຂໍ້ມູນກະ', 'error');
    }
}

function calculateShiftDifference() {
    if (!activeShiftData) return;

    const rateThb = exchangeRates.THB || 680;
    const rateUsd = exchangeRates.USD || 22000;

    const countedLak = parseFloat(document.getElementById('inputCountedLak')?.value) || 0;
    const countedThb = parseFloat(document.getElementById('inputCountedThb')?.value) || 0;
    const countedUsd = parseFloat(document.getElementById('inputCountedUsd')?.value) || 0;

    const totalCountedLak = Math.round(countedLak + (countedThb * rateThb) + (countedUsd * rateUsd));
    const expectedCashLak = Math.round(activeShiftData.expected_cash || 0);
    const diff = totalCountedLak - expectedCashLak;

    const banner = document.getElementById('shiftDiffBanner');
    const textEl = document.getElementById('shiftDiffStatusText');
    const totalEl = document.getElementById('shiftDiffTotalCounted');

    if (totalEl) {
        totalEl.textContent = `ລວມນັບໄດ້: ${formatCurrency(totalCountedLak)} ₭`;
    }

    if (!banner || !textEl) return;

    if (countedLak === 0 && countedThb === 0 && countedUsd === 0) {
        banner.style.background = '#f4f6f8';
        banner.style.borderColor = '#dfe1e6';
        textEl.style.color = '#5e6c84';
        textEl.textContent = 'ລໍຖ້າປ້ອນເງິນສົດທີ່ນັບໄດ້';
    } else if (diff === 0) {
        banner.style.background = '#e3fcef';
        banner.style.borderColor = '#abf5d1';
        textEl.style.color = '#006644';
        textEl.textContent = '✅ ເງິນສົດພໍດີ (Balanced)';
    } else if (diff > 0) {
        banner.style.background = '#e3fcef';
        banner.style.borderColor = '#abf5d1';
        textEl.style.color = '#006644';
        textEl.textContent = `🟢 ເງິນເກີນ: +${formatCurrency(diff)} ກີບ (Over)`;
    } else {
        banner.style.background = '#ffebe6';
        banner.style.borderColor = '#ffbdad';
        textEl.style.color = '#bf2600';
        textEl.textContent = `🔴 ເງິນຂາດ: -${formatCurrency(Math.abs(diff))} ກີບ (Short)`;
    }
}

async function confirmCloseShift() {
    if (!activeShiftData) return;

    const countedLak = parseFloat(document.getElementById('inputCountedLak')?.value) || 0;
    const countedThb = parseFloat(document.getElementById('inputCountedThb')?.value) || 0;
    const countedUsd = parseFloat(document.getElementById('inputCountedUsd')?.value) || 0;
    const note = document.getElementById('inputCloseShiftNote')?.value?.trim() || '';

    const btn = document.getElementById('btnConfirmCloseShift');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງປິດກະ...';
    }

    try {
        const shiftId = activeShiftData.id;
        const res = await fetchAPI(BASE_URL + 'api/shifts.php?action=close', {
            method: 'POST',
            body: JSON.stringify({
                shift_id: shiftId,
                cash_counted_lak: countedLak,
                cash_counted_thb: countedThb,
                cash_counted_usd: countedUsd,
                note: note
            })
        });

        if (res && res.success) {
            showToast('ປິດກະຂາຍສຳເລັດ!', 'success');
            if (typeof closeModal === 'function') {
                closeModal('closeShiftModal');
            } else {
                document.getElementById('closeShiftModal')?.classList.remove('show');
            }

            activeShiftData = null;
            await checkCurrentShift();

            // Prompt print shift summary receipt
            if (typeof printReceipt === 'function') {
                printReceipt(null, {
                    url: `${BASE_URL}api/shift_receipt.php?id=${shiftId}&autoprint=1`,
                    title: 'ໃບສະຫຼຸບຍອດກະ'
                });
            } else {
                window.open(`${BASE_URL}api/shift_receipt.php?id=${shiftId}&autoprint=1`, '_blank', 'width=420,height=600');
            }
        } else {
            showToast(res.message || 'ບໍ່ສາມາດປິດກະໄດ້', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການປິດກະ', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock"></i> ຢືນຢັນປິດກະ & ພິມໃບສະຫຼຸບ';
        }
    }
}

