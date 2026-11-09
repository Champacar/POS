/**
 * POS Pharma - PWA Client Helper
 * Handles Service Worker Registration & App Install Prompt
 */

let deferredPrompt = null;

// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('./sw.js')
            .then((reg) => {
                // Check for SW updates
                reg.onupdatefound = () => {
                    const installingWorker = reg.installing;
                    if (installingWorker) {
                        installingWorker.onstatechange = () => {
                            if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                console.log('[PWA] ມີເວີຊັນໃໝ່ພ້ອມອັບເດດ');
                            }
                        };
                    }
                };
            })
            .catch((err) => {
                console.warn('[PWA] SW registration failed:', err);
            });
    });
}

// Check if running as standalone PWA
function isRunningStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches ||
           window.navigator.standalone === true ||
           document.referrer.includes('android-app://');
}

// Listen for beforeinstallprompt event
window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent mini-infobar on mobile Chrome
    e.preventDefault();
    deferredPrompt = e;

    // Show install buttons if available
    const installBtns = document.querySelectorAll('.pwa-install-btn, #pwaInstallBtn');
    installBtns.forEach(btn => {
        btn.style.display = 'flex';
    });
});

// Install Button Click Handler
window.installPWA = async function () {
    if (!deferredPrompt) {
        if (typeof showToast === 'function') {
            showToast('ແອັບນີ້ຖືກຕິດຕັ້ງແລ້ວ ຫຼື ອຸປະກອນຂອງທ່ານຍັງບໍ່ຮອງຮັບ', 'info');
        }
        return;
    }

    // Show the install prompt
    deferredPrompt.prompt();

    // Wait for the user to respond to the prompt
    const { outcome } = await deferredPrompt.userChoice;
    console.log(`[PWA] Install prompt outcome: ${outcome}`);

    // Clear deferredPrompt
    deferredPrompt = null;

    // Hide install buttons
    const installBtns = document.querySelectorAll('.pwa-install-btn, #pwaInstallBtn');
    installBtns.forEach(btn => {
        btn.style.display = 'none';
    });
};

// Listen for app installed event
window.addEventListener('appinstalled', () => {
    console.log('[PWA] POS Pharma ຖືກຕິດຕັ້ງສຳເລັດແລ້ວ');
    if (typeof showToast === 'function') {
        showToast('ຕິດຕັ້ງແອັບ POS Pharma ລົງໃນອຸປະກອນສຳເລັດແລ້ວ!', 'success', 4000);
    }
    const installBtns = document.querySelectorAll('.pwa-install-btn, #pwaInstallBtn');
    installBtns.forEach(btn => {
        btn.style.display = 'none';
    });
});

// On DOM ready, hide install button if already in standalone mode
document.addEventListener('DOMContentLoaded', () => {
    if (isRunningStandalone()) {
        const installBtns = document.querySelectorAll('.pwa-install-btn, #pwaInstallBtn');
        installBtns.forEach(btn => {
            btn.style.display = 'none';
        });
    }
});
