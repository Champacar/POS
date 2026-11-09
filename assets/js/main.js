/**
 * Main JavaScript
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

// ===================================================
// Sidebar Toggle
// ===================================================
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');

    if (toggle) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });

        // Restore state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
    }

    // Auto-scroll active nav link on mobile bottom bar
    if (window.innerWidth <= 991) {
        const activeNav = document.querySelector('.sidebar-nav .nav-link.active');
        if (activeNav) {
            setTimeout(() => {
                activeNav.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }, 100);
        }
    }
});

// ===================================================
// Date Filter Buttons
// ===================================================
function setDateFilter(type) {
    const today = new Date();
    let startDate, endDate;

    if (type === 'today') {
        startDate = endDate = formatDateInput(today);
    } else if (type === 'month') {
        startDate = formatDateInput(new Date(today.getFullYear(), today.getMonth(), 1));
        endDate = formatDateInput(today);
    }

    const startInput = document.getElementById('startDate');
    const endInput = document.getElementById('endDate');

    if (startInput) startInput.value = startDate;
    if (endInput) endInput.value = endDate;

    // Update active button
    document.querySelectorAll('.btn-filter').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.querySelector(`[data-filter="${type}"]`);
    if (activeBtn) activeBtn.classList.add('active');

    // Trigger reload
    if (typeof loadPageData === 'function') {
        loadPageData(startDate, endDate);
    }
}

function formatDateInput(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function onDateChange() {
    const startDate = document.getElementById('startDate')?.value;
    const endDate = document.getElementById('endDate')?.value;

    // Remove active from filter buttons
    document.querySelectorAll('.btn-filter').forEach(btn => btn.classList.remove('active'));

    if (startDate && endDate && typeof loadPageData === 'function') {
        loadPageData(startDate, endDate);
    }
}

// ===================================================
// Toast Notifications
// ===================================================
function showToast(message, type = 'success', duration = 3000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    // Icon mapping
    const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
    toast.innerHTML = `
        <i class="fas ${icon}"></i>
        <div style="flex: 1;">${message}</div>
        <button class="toast-close" style="background:none;border:none;color:var(--text-light);cursor:pointer;font-size:1rem;padding:0 4px;" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
        <div class="toast-progress" style="position:absolute;bottom:0;left:0;height:3px;background:var(--${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'});width:100%;transform-origin:left;animation:toastProgress ${duration}ms linear forwards;"></div>
    `;

    container.appendChild(toast);

    // Add progress bar animation keyframes if not already present
    if (!document.getElementById('toastKeyframes')) {
        const style = document.createElement('style');
        style.id = 'toastKeyframes';
        style.innerHTML = `
            @keyframes toastProgress {
                from { transform: scaleX(1); }
                to { transform: scaleX(0); }
            }
        `;
        document.head.appendChild(style);
    }

    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, duration);
}

// ===================================================
// Modal Functions
// ===================================================
let lastFocusedElement = null;

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        lastFocusedElement = document.activeElement;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        setTimeout(() => {
            const firstFocusable = modal.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]):not(.modal-close)');
            if (firstFocusable) {
                firstFocusable.focus();
            } else {
                const fallbackBtn = modal.querySelector('button:not([disabled])');
                if (fallbackBtn) fallbackBtn.focus();
            }
        }, 50);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        modal.classList.remove('active');
        const remainingModals = document.querySelectorAll('.modal-overlay.show, .modal-overlay.active');
        if (remainingModals.length === 0) {
            document.body.style.overflow = '';
        }
        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
            lastFocusedElement.focus();
            lastFocusedElement = null;
        }
    }
}

// Close modal on Escape key & Focus Trap on Tab
document.addEventListener('keydown', function (e) {
    if (typeof Swal !== 'undefined' && Swal.isVisible()) return;

    const activeModals = document.querySelectorAll('.modal-overlay.show, .modal-overlay.active');
    if (activeModals.length === 0) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && sidebar.classList.contains('open') && typeof closeMobileSidebar === 'function') {
                closeMobileSidebar();
            }
        }
        return;
    }

    const topModal = activeModals[activeModals.length - 1];

    if (e.key === 'Escape' || e.key === 'Esc') {
        if (topModal.id === 'checkoutModal' && typeof closeCheckoutModal === 'function') {
            closeCheckoutModal();
        } else {
            closeModal(topModal.id);
        }
        return;
    }

    if (e.key === 'Tab') {
        const focusableSelectors = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
        const focusableElements = Array.from(topModal.querySelectorAll(focusableSelectors)).filter(el => el.offsetParent !== null);
        if (focusableElements.length === 0) return;

        const firstEl = focusableElements[0];
        const lastEl = focusableElements[focusableElements.length - 1];

        if (e.shiftKey) {
            if (document.activeElement === firstEl || !topModal.contains(document.activeElement)) {
                e.preventDefault();
                lastEl.focus();
            }
        } else {
            if (document.activeElement === lastEl || !topModal.contains(document.activeElement)) {
                e.preventDefault();
                firstEl.focus();
            }
        }
    }
});

// Close modal on overlay click (except critical forms to prevent accidental data loss)
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        const staticModals = ['checkoutModal', 'addProductModal', 'stockInModal', 'stockOutModal'];
        if (staticModals.includes(e.target.id)) {
            // Do not dismiss critical transaction/data modals abruptly on backdrop click
            return;
        }
        if (e.target.id === 'receiptPreviewModal' && typeof closeReceiptPreviewModal === 'function') {
            closeReceiptPreviewModal();
            return;
        }
        closeModal(e.target.id);
        const remainingModals = document.querySelectorAll('.modal-overlay.show, .modal-overlay.active');
        if (remainingModals.length === 0) {
            document.body.style.overflow = '';
        }
    }
});

// ===================================================
// Format Currency
// ===================================================
function formatCurrency(amount) {
    return Number(amount).toLocaleString('en-US');
}

// ===================================================
// Button Loading Helper
// ===================================================
window.setButtonLoading = function (btn, isLoading, loadingText = 'ກຳລັງດຳເນີນການ...') {
    if (!btn) return;
    if (isLoading) {
        if (!btn.dataset.originalHtml) {
            btn.dataset.originalHtml = btn.innerHTML;
        }
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${loadingText}`;
    } else {
        btn.disabled = false;
        if (btn.dataset.originalHtml) {
            btn.innerHTML = btn.dataset.originalHtml;
            delete btn.dataset.originalHtml;
        }
    }
};

// ===================================================
// AJAX Helper
// ===================================================
async function fetchAPI(url, options = {}) {
    try {
        const headers = { ...options.headers };
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta && !headers['X-CSRF-TOKEN']) {
            headers['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content');
        }
        if (!(options.body instanceof FormData) && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }
        const response = await fetch(url, {
            ...options,
            headers: headers,
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        if (options.showErrorToast) {
            showToast('ເກີດຂໍ້ຜິດພາດ: ' + error.message, 'error');
            error._toastShown = true;
        }
        throw error;
    }
}

function confirmDelete(message = 'ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບ?') {
    if (typeof Swal === 'undefined') {
        return Promise.resolve(window.confirm(message));
    }
    return Swal.fire({
        title: 'ຢືນຢັນການດຳເນີນການ',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2e7d32',
        cancelButtonColor: '#d32f2f',
        confirmButtonText: 'ຕົກລົງ',
        cancelButtonText: 'ຍົກເລີກ',
        customClass: {
            popup: 'swal2-lao-popup'
        }
    }).then((result) => {
        return result.isConfirmed;
    });
}

// ===================================================
// Search Filter
// ===================================================
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);

    if (!input || !table) return;

    const filter = input.value.toLowerCase();
    const rows = table.querySelectorAll('tbody tr:not(.no-filter-results)');
    let visibleCount = 0;

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const match = text.includes(filter);
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });

    const tbody = table.querySelector('tbody');
    if (tbody) {
        let emptyRow = tbody.querySelector('.no-filter-results');
        if (visibleCount === 0 && rows.length > 0) {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.className = 'no-filter-results';
                emptyRow.innerHTML = `<td colspan="100" style="text-align:center; padding:30px; color:var(--text-light);">ບໍ່ພົບຂໍ້ມູນທີ່ກົງກັບຄຳຄົ້ນຫາ</td>`;
                tbody.appendChild(emptyRow);
            }
            emptyRow.style.display = '';
        } else if (emptyRow) {
            emptyRow.style.display = 'none';
        }
    }
}

// ===================================================
// Receipt Preview Modal & Silent Desktop Printing
// ===================================================
let activeReceiptUrl = null;
let activeReceiptSaleId = null;

function ensureReceiptModalExists() {
    let modal = document.getElementById('receiptPreviewModal');
    if (modal) return modal;

    const modalHtml = `
    <div class="modal-overlay" id="receiptPreviewModal" style="z-index: 2500; backdrop-filter: blur(4px);">
        <div class="modal" style="max-width: 440px; width: 95%; max-height: 92vh; display: flex; flex-direction: column; box-shadow: 0 25px 60px rgba(0,0,0,0.3); border-radius: 16px; overflow: hidden; background: #fff;">
            <!-- Modal Header -->
            <div class="modal-header" style="padding: 14px 18px; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; background: #fff;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(30, 142, 62, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--text-dark);" id="receiptModalTitle">ຕົວຢ່າງໃບບິນ (Receipt Preview)</h3>
                        <div style="font-size: 11px; color: var(--text-light); margin-top: 1px;" id="receiptModalSub">ກວດສອບຮູບແບບໃບບິນກ່ອນສັ່ງພິມ</div>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="closeReceiptPreviewModal()" aria-label="Close" style="width: 32px; height: 32px; border: none; background: transparent; cursor: pointer; color: var(--text-light); font-size: 1.1rem; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body (Paper preview area) -->
            <div class="modal-body" style="padding: 16px 12px; background: #f3f4f6; overflow-y: auto; flex: 1; display: flex; justify-content: center; align-items: flex-start; min-height: 380px;">
                <div id="receiptPaperWrapper" style="background: #fff; width: 100%; max-width: 330px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); border-radius: 6px; overflow: hidden; position: relative; border-top: 4px solid var(--primary);">
                    <div id="receiptIframeLoading" style="position: absolute; inset: 0; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; z-index: 5;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 1.8rem; color: var(--primary);"></i>
                        <span style="font-size: 12px; color: var(--text-light);">ກຳລັງໂຫຼດຕົວຢ່າງໃບບິນ...</span>
                    </div>
                    <iframe id="receiptPreviewFrame" name="receiptPreviewFrame" style="width: 100%; border: none; height: 420px; display: block; background: #fff;" onload="onReceiptFrameLoaded()"></iframe>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer" style="padding: 12px 16px; border-top: 1px solid var(--border-light); background: #fafafa; display: flex; flex-direction: column; gap: 10px;">
                <!-- Row 1: Auto Print Toggle & Printer Selection -->
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; flex-wrap: wrap; gap: 8px;">
                    <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12.5px; font-weight: 600; color: var(--text-dark); user-select: none; margin: 0;">
                        <input type="checkbox" id="receiptAutoPrintToggle" style="width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer;" onchange="onAutoPrintToggleChange(this.checked)">
                        <span>ປິ້ນອັດຕະໂນມັດ (Auto-Print)</span>
                    </label>

                    <div id="printerSelectionArea" style="display: flex; align-items: center; gap: 6px;">
                        <span id="printerDeviceBadge" style="font-size: 11px; padding: 3px 8px; border-radius: 20px; background: #e5e7eb; color: #374151; display: none; align-items: center; gap: 4px; font-weight: 500; cursor: pointer;">
                            <i class="fas fa-print" style="color: var(--primary); font-size: 10px;"></i>
                            <span id="printerDeviceName">Default</span>
                        </span>
                        <select id="printerSelectDropdown" style="display: none; font-size: 11px; padding: 3px 6px; border-radius: 6px; border: 1px solid var(--border-light); background: #fff; max-width: 160px;" onchange="onPrinterSelected(this.value)"></select>
                    </div>
                </div>

                <!-- Row 2: Action Buttons -->
                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 8px; width: 100%;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeReceiptPreviewModal()" style="min-width: 75px;">
                        <i class="fas fa-times"></i> ປິດ
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnExecuteReceiptPrint" onclick="executeReceiptPrint()" style="min-width: 100px;">
                        <i class="fas fa-print"></i> ສັ່ງພິມ
                    </button>
                </div>
            </div>
        </div>
    </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Setup global message listener for dynamic iframe resizing
    window.addEventListener('message', function(e) {
        if (e.data && e.data.type === 'RECEIPT_PREVIEW_READY') {
            const iframe = document.getElementById('receiptPreviewFrame');
            if (iframe && e.data.height) {
                iframe.style.height = Math.max(380, e.data.height + 20) + 'px';
            }
            const loader = document.getElementById('receiptIframeLoading');
            if (loader) loader.style.display = 'none';
        }
    });

    // Setup Escape key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const m = document.getElementById('receiptPreviewModal');
            if (m && m.classList.contains('show')) {
                closeReceiptPreviewModal();
            }
        }
    });

    return document.getElementById('receiptPreviewModal');
}

async function printReceipt(saleId, options = {}) {
    ensureReceiptModalExists();
    const modal = document.getElementById('receiptPreviewModal');
    const iframe = document.getElementById('receiptPreviewFrame');
    const loader = document.getElementById('receiptIframeLoading');
    const autoPrintCheckbox = document.getElementById('receiptAutoPrintToggle');
    const titleEl = document.getElementById('receiptModalTitle');

    activeReceiptSaleId = saleId;

    let targetUrl = options.url;
    if (!targetUrl) {
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
        targetUrl = `${baseUrl}api/receipt.php?id=${saleId}&preview=1`;
    }
    activeReceiptUrl = targetUrl;

    if (titleEl) {
        titleEl.textContent = saleId ? `ຕົວຢ່າງໃບບິນ (#${saleId})` : 'ຕົວຢ່າງໃບບິນ (Receipt Preview)';
    }

    if (loader) loader.style.display = 'flex';

    // Auto-print preference:
    // Specifically for Desktop App: Default is ON (autoPrintEnabled = true), unless user explicitly turned it OFF ('false')
    // Specifically for Web App: Default is OFF (autoPrintEnabled = false)
    const isDesktop = Boolean(window.api && (window.api.isDesktop || typeof window.api.printReceiptSilent === 'function'));
    const savedAutoPrint = localStorage.getItem('pos_auto_print');
    let autoPrintEnabled = false;
    if (isDesktop) {
        if (saleId) {
            autoPrintEnabled = (savedAutoPrint !== 'false');
        } else {
            autoPrintEnabled = options.autoPrint === true;
        }
    } else {
        autoPrintEnabled = (savedAutoPrint === 'true') || options.autoPrint === true;
    }

    if (autoPrintCheckbox) {
        autoPrintCheckbox.checked = autoPrintEnabled;
    }

    // Initialize printer list if running in Desktop App
    initPrinterInfo();

    // Show preview modal
    modal.classList.add('show');

    // Reset printed status flag
    if (iframe) {
        iframe.dataset.hasPrinted = 'false';
    }

    // Load receipt into preview frame
    iframe.src = targetUrl;

    // If auto-print is enabled, set fallback auto-print timer (2.5s) in case onload does not trigger
    if (autoPrintEnabled) {
        if (iframe.dataset.printTimer) {
            clearTimeout(Number(iframe.dataset.printTimer));
        }
        const printTimer = setTimeout(async () => {
            if (iframe && iframe.dataset.hasPrinted !== 'true') {
                const ok = await executeReceiptPrint({ isAuto: true });
                if (ok && isDesktop) {
                    setTimeout(() => {
                        closeReceiptPreviewModal();
                    }, 1200);
                }
            }
        }, 2500);
        iframe.dataset.printTimer = String(printTimer);
    }
}

async function executeReceiptPrint(opts = {}) {
    const btn = document.getElementById('btnExecuteReceiptPrint');
    const iframe = document.getElementById('receiptPreviewFrame');

    if (opts.isAuto && iframe && iframe.dataset.hasPrinted === 'true') {
        return false;
    }
    if (iframe) {
        iframe.dataset.hasPrinted = 'true';
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງພິມ...';
    }

    const isDesktop = Boolean(window.api && (window.api.isDesktop || typeof window.api.printReceiptSilent === 'function'));
    let printSuccess = false;

    try {
        const fullUrl = new URL(activeReceiptUrl, window.location.href).href;
        const selectedPrinter = localStorage.getItem('pos_receipt_printer') || localStorage.getItem('pos_selected_printer') || '';

        // In Desktop App, extract pre-rendered HTML from the iframe and silent print via IPC
        if (isDesktop && typeof window.api.printReceiptSilent === 'function') {
            let htmlContent = '';
            try {
                if (iframe && iframe.contentDocument && iframe.contentDocument.documentElement) {
                    htmlContent = iframe.contentDocument.documentElement.outerHTML;
                    if (!htmlContent.includes('<base')) {
                        htmlContent = htmlContent.replace('<head>', `<head><base href="${window.location.origin}/">`);
                    }
                }
            } catch (e) {
                console.warn('Could not extract iframe outerHTML:', e);
            }

            const res = await window.api.printReceiptSilent({
                url: fullUrl,
                html: htmlContent,
                deviceName: selectedPrinter
            });

            if (res && res.success) {
                printSuccess = true;
                showToast(opts.isAuto ? 'ພິມໃບບິນອັດຕະໂນມັດສຳເລັດ' : 'ສັ່ງພິມໃບບິນແລ້ວ', 'success');
            } else {
                console.warn('Silent print error, falling back to browser print:', res);
                showToast('ພວມເປີດໜ້າຕ່າງພິມໃບບິນ...', 'info');
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                }
                printSuccess = false; // Keep preview modal open so cashier can complete print
            }
        } else if (window.QZBridge && window.QZBridge.connected) {
            // 2. Web App with QZ Tray silent rasterized print
            let htmlContent = '';
            try {
                if (iframe && iframe.contentDocument && iframe.contentDocument.documentElement) {
                    htmlContent = iframe.contentDocument.documentElement.outerHTML;
                    if (!htmlContent.includes('<base')) {
                        htmlContent = htmlContent.replace('<head>', `<head><base href="${window.location.origin}/">`);
                    }
                }
            } catch (e) {
                console.warn('[QZBridge] Could not extract outerHTML:', e);
            }

            const targetPayload = htmlContent || fullUrl;
            const ok = await window.QZBridge.printReceipt(targetPayload, {
                printer: selectedPrinter
            });

            if (ok) {
                printSuccess = true;
                showToast(opts.isAuto ? 'ພິມໃບບິນອັດຕະໂນມັດສຳເລັດ (QZ Tray)' : 'ສັ່ງພິມໃບບິນແລ້ວ (QZ Tray)', 'success');
            } else {
                console.warn('[QZBridge] Silent print failed, falling back to browser print');
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                    printSuccess = true;
                }
            }
        } else {
            // Web App (Browser): standard browser print dialog
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                printSuccess = true;
                showToast('ສັ່ງພິມໃບບິນແລ້ວ', 'success');
            } else {
                const printWin = window.open(fullUrl + '&autoprint=1', '_blank', 'width=420,height=600');
                if (printWin) {
                    printWin.focus();
                    printSuccess = true;
                    showToast('ສັ່ງພິມໃບບິນແລ້ວ', 'success');
                }
            }
        }
    } catch (err) {
        console.error('Error during receipt printing:', err);
        if (iframe && iframe.contentWindow) {
            iframe.contentWindow.print();
            printSuccess = true;
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-print"></i> ສັ່ງພິມ';
        }
    }

    return printSuccess;
}

/**
 * Kick Cash Drawer (ລິ້ນຊັກເງິນ)
 * ຮອງຮັບທັງ Desktop App (Silent Pulse) ແລະ Web App (Driver Kick / Hidden Frame)
 */
async function triggerCashDrawerKick(options = {}) {
    const isDesktop = Boolean(window.api && (window.api.isDesktop || typeof window.api.printReceiptSilent === 'function'));
    const selectedPrinter = localStorage.getItem('pos_receipt_printer') || localStorage.getItem('pos_selected_printer') || '';
    const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '';

    if (isDesktop && typeof window.api.printReceiptSilent === 'function') {
        try {
            const res = await window.api.printReceiptSilent({
                html: '<!DOCTYPE html><html><body style="margin:0;padding:0;"><div style="font-size:1px;color:#fff;">.</div></body></html>',
                deviceName: selectedPrinter
            });
            if (res && res.success) {
                if (!options.silent) showToast('ສັ່ງເປີດລິ້ນຊັກເງິນສຳເລັດ', 'success');
                return true;
            }
        } catch (e) {
            console.warn('Desktop cash drawer kick error:', e);
        }
    }

    // 2. QZ Tray Direct ESC/POS Pulse (Raw bytes 1B 70 00 19 FA & 1B 70 01 19 FA)
    if (window.QZBridge && window.QZBridge.connected) {
        try {
            const ok = await window.QZBridge.kickCashDrawer(selectedPrinter);
            if (ok) {
                if (!options.silent) showToast('ສັ່ງເປີດລິ້ນຊັກເງິນສຳເລັດ (QZ Tray / ESC/POS)', 'success');
                return true;
            }
        } catch (e) {
            console.warn('[QZBridge] Drawer kick error:', e);
        }
    }

    // Web browser fallback: load api/drawer.php into hidden iframe
    try {
        let kickFrame = document.getElementById('cashDrawerKickFrame');
        if (!kickFrame) {
            kickFrame = document.createElement('iframe');
            kickFrame.id = 'cashDrawerKickFrame';
            kickFrame.style.display = 'none';
            kickFrame.style.position = 'fixed';
            kickFrame.style.width = '0';
            kickFrame.style.height = '0';
            kickFrame.style.border = 'none';
            document.body.appendChild(kickFrame);
        }
        kickFrame.src = `${baseUrl}api/drawer.php?t=${Date.now()}`;
        if (!options.silent) showToast('ສັ່ງເປີດລິ້ນຊັກເງິນແລ້ວ', 'info');
        return true;
    } catch (err) {
        console.error('Web cash drawer kick error:', err);
        return false;
    }
}

async function initPrinterInfo() {
    const badge = document.getElementById('printerDeviceBadge');
    const nameSpan = document.getElementById('printerDeviceName');
    const dropdown = document.getElementById('printerSelectDropdown');

    const hasDesktop = Boolean(window.api && typeof window.api.getPrinters === 'function');
    const hasQZ = Boolean(window.QZBridge && window.QZBridge.connected);

    if (!hasDesktop && !hasQZ) {
        if (window.QZBridge && typeof window.QZBridge.connect === 'function' && !window.QZBridge.connected) {
            window.QZBridge.connect().then(ok => {
                if (ok) initPrinterInfo();
            }).catch(() => {});
        }
        if (badge) badge.style.display = 'none';
        if (dropdown) dropdown.style.display = 'none';
        return;
    }

    try {
        let rawPrinters = [];
        if (hasDesktop) {
            rawPrinters = await window.api.getPrinters();
        } else if (hasQZ) {
            rawPrinters = await window.QZBridge.refreshPrinters();
        }

        const printers = (rawPrinters || []).map(p => {
            if (typeof p === 'string') return { name: p, isDefault: false };
            return p;
        });

        if (Array.isArray(printers) && printers.length > 0) {
            const savedPrinter = localStorage.getItem('pos_receipt_printer') || localStorage.getItem('pos_selected_printer') || '';
            const defaultPrinter = printers.find(p => p.isDefault) || printers[0];
            const receiptPrinter = printers.find(p => /gp-|c80|pos|receipt|thermal|80|58/i.test(p.name));
            const bestDefault = (savedPrinter && printers.some(p => p.name === savedPrinter))
                ? savedPrinter
                : (receiptPrinter ? receiptPrinter.name : defaultPrinter.name);

            // Persist the selected receipt printer name
            localStorage.setItem('pos_receipt_printer', bestDefault);

            dropdown.innerHTML = '';
            printers.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.name;
                let tag = '';
                const n = p.name.toLowerCase();
                if (n.includes('grozziie') || n.includes('tp878') || n.includes('label') || n.includes('sticker') || n.includes('barcode')) {
                    tag = ' 🏷️ [Label]';
                } else if (n.includes('c80') || n.includes('pos') || n.includes('receipt') || n.includes('80')) {
                    tag = ' 🧾 [Receipt 80mm]';
                }
                opt.textContent = p.name + tag + (p.isDefault ? ' (Default)' : '');
                if (p.name === bestDefault) {
                    opt.selected = true;
                }
                dropdown.appendChild(opt);
            });

            const activeName = bestDefault;
            if (nameSpan) nameSpan.textContent = activeName;
            if (badge) {
                badge.style.display = 'inline-flex';
                badge.title = 'ກົດເພື່ອປ່ຽນເຄື່ອງພິມ / Click to change printer';
                badge.onclick = () => {
                    badge.style.display = 'none';
                    dropdown.style.display = 'inline-block';
                    dropdown.focus();
                };
            }
            if (dropdown) {
                dropdown.onchange = () => {
                    if (dropdown.value) {
                        localStorage.setItem('pos_receipt_printer', dropdown.value);
                        if (nameSpan) nameSpan.textContent = dropdown.value;
                    }
                };
                dropdown.onblur = () => {
                    dropdown.style.display = 'none';
                    badge.style.display = 'inline-flex';
                    if (nameSpan) nameSpan.textContent = dropdown.value || 'Default';
                };
            }
        } else {
            if (badge) badge.style.display = 'none';
        }
    } catch (e) {
        console.warn('Could not query printers:', e);
    }
}

function closeReceiptPreviewModal() {
    const modal = document.getElementById('receiptPreviewModal');
    if (modal) {
        modal.classList.remove('show');
    }
    const iframe = document.getElementById('receiptPreviewFrame');
    if (iframe) {
        if (iframe.dataset.printTimer) {
            clearTimeout(Number(iframe.dataset.printTimer));
            delete iframe.dataset.printTimer;
        }
        iframe.dataset.hasPrinted = 'false';
        iframe.src = 'about:blank';
    }
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.focus();
    }
}

function onReceiptFrameLoaded() {
    const loader = document.getElementById('receiptIframeLoading');
    if (loader) loader.style.display = 'none';
    const iframe = document.getElementById('receiptPreviewFrame');
    try {
        if (iframe && iframe.contentWindow && iframe.contentDocument) {
            const doc = iframe.contentDocument;
            const h = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight);
            if (h > 0) {
                iframe.style.height = Math.max(380, h + 20) + 'px';
            }
        }
    } catch (e) {}

    // Auto-print immediately when iframe finishes loading
    const isDesktop = Boolean(window.api && (window.api.isDesktop || typeof window.api.printReceiptSilent === 'function'));
    const autoPrintCheckbox = document.getElementById('receiptAutoPrintToggle');
    const autoPrintEnabled = autoPrintCheckbox ? autoPrintCheckbox.checked : false;

    if (autoPrintEnabled && iframe && iframe.src && !iframe.src.endsWith('about:blank')) {
        if (iframe.dataset.hasPrinted !== 'true') {
            if (iframe.dataset.printTimer) {
                clearTimeout(Number(iframe.dataset.printTimer));
            }
            const printTimer = setTimeout(async () => {
                if (iframe && iframe.dataset.hasPrinted !== 'true') {
                    const ok = await executeReceiptPrint({ isAuto: true });
                    // ONLY close preview modal if auto-print was actually completed
                    if (ok && isDesktop) {
                        setTimeout(() => {
                            closeReceiptPreviewModal();
                        }, 1200);
                    }
                }
            }, 300);
            iframe.dataset.printTimer = String(printTimer);
        }
    }
}

function onAutoPrintToggleChange(checked) {
    localStorage.setItem('pos_auto_print', checked ? 'true' : 'false');
    if (checked) {
        showToast('ເປີດໃຊ້ງານປິ້ນອັດຕະໂນມັດແລ້ວ (Auto-Print Enabled)', 'info');
    } else {
        showToast('ປິດໃຊ້ງານປິ້ນອັດຕະໂນມັດແລ້ວ (ກວດສອບຕົວຢ່າງກ່ອນສັ່ງພິມ)', 'info');
    }
}

function onPrinterSelected(printerName) {
    localStorage.setItem('pos_selected_printer', printerName);
    localStorage.setItem('pos_receipt_printer', printerName);
    const nameSpan = document.getElementById('printerDeviceName');
    if (nameSpan) nameSpan.textContent = printerName;
    const badge = document.getElementById('printerDeviceBadge');
    const dropdown = document.getElementById('printerSelectDropdown');
    if (dropdown) dropdown.style.display = 'none';
    if (badge) badge.style.display = 'inline-flex';
    showToast(`ເລືອກເຄື່ອງພິມ: ${printerName}`, 'info');
}

// ===================================================
// Mobile Sidebar Toggling
// ===================================================
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
        if (sidebar.classList.contains('open')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// ===================================================
// Cloud Sync (Standalone Mode - Disabled)
// Desktop App ແລະ Web App ແຍກກັນຢ່າງສົມບູນ ບໍ່ມີການ Sync
// ===================================================
function initSync() {
    const syncDiv = document.getElementById('sync-status-div');
    if (syncDiv) {
        syncDiv.style.display = 'none';
    }
}

function runBackgroundSync() {
    // ປິດການ Sync ລະຫວ່າງ Desktop ແລະ Web ແຍກກັນຢ່າງສົມບູນ
    return;
}

// Start sync service on load
document.addEventListener('DOMContentLoaded', () => {
    initSync();
});
