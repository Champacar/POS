/**
 * POSPharma - QZ Tray & Hardware Integration Bridge
 * Connects Web POS with Local Thermal Printers, Cash Drawers (ESC/POS) & Barcode Scanners
 * 
 * Supports:
 * - Silent Auto Printing via WebSocket wss://localhost:8182
 * - Raw ESC/POS commands (Paper Cut, Cash Drawer Kick via RJ11 Pin 2 & Pin 5)
 * - Pixel/Raster Lao typography rendering (No broken consonants or tone marks)
 * - Multi-printer routing (Receipt 80mm/58mm vs. Label/Sticker 80x50mm, 70x50mm, 50x30mm)
 */
(function (window) {
    'use strict';

    const QZBridge = {
        connected: false,
        connecting: false,
        printers: [],
        listeners: [],
        reconnectTimer: null,

        // Check if QZ Tray library is loaded
        isAvailable() {
            return typeof qz !== 'undefined' && typeof qz.websocket !== 'undefined';
        },

        // Setup security certificates (Demo/Self-Signed/Unrestricted mode)
        initSecurity() {
            if (!this.isAvailable()) return;

            try {
                // If certificates not configured, provide silent resolve to prevent errors
                if (typeof qz.security.setCertificatePromise === 'function') {
                    qz.security.setCertificatePromise(function (resolve) {
                        resolve(); // Runs in standard/demo mode
                    });
                }
                if (typeof qz.security.setSignaturePromise === 'function') {
                    qz.security.setSignaturePromise(function () {
                        return function (resolve) {
                            resolve();
                        };
                    });
                }
            } catch (e) {
                console.warn('[QZBridge] Security setup notice:', e);
            }
        },

        // Connect to QZ Tray WebSocket
        async connect(options = {}) {
            if (!this.isAvailable()) {
                console.warn('[QZBridge] qz-tray.js library not loaded');
                return false;
            }

            if (qz.websocket.isActive()) {
                this.connected = true;
                return true;
            }

            if (this.connecting) return false;
            this.connecting = true;

            this.initSecurity();

            // Try Secure WebSocket first (wss://localhost:8182), then Fallback to ws://localhost:8181
            try {
                await qz.websocket.connect({
                    host: options.host || 'localhost',
                    usingSecure: options.secure !== false,
                    keepAlive: 60,
                    retries: 1,
                    delay: 1
                });

                this.connected = true;
                this.connecting = false;
                console.log('[QZBridge] Connected to QZ Tray v' + (qz.version || '2.2'));

                await this.refreshPrinters();
                this.notifyStatus(true);
                return true;
            } catch (err) {
                // Insecure fallback attempt
                try {
                    await qz.websocket.connect({
                        host: options.host || 'localhost',
                        usingSecure: false,
                        keepAlive: 60,
                        retries: 0,
                        delay: 0
                    });

                    this.connected = true;
                    this.connecting = false;
                    console.log('[QZBridge] Connected to QZ Tray (standard ws)');

                    await this.refreshPrinters();
                    this.notifyStatus(true);
                    return true;
                } catch (fallbackErr) {
                    this.connected = false;
                    this.connecting = false;
                    this.notifyStatus(false);
                    return false;
                }
            }
        },

        // Disconnect
        async disconnect() {
            if (this.isAvailable() && qz.websocket.isActive()) {
                try {
                    await qz.websocket.disconnect();
                } catch (e) {}
            }
            this.connected = false;
            this.notifyStatus(false);
        },

        // Register status change callback
        onStatusChange(fn) {
            if (typeof fn === 'function') {
                this.listeners.push(fn);
                fn(this.connected, this.printers);
            }
        },

        notifyStatus(status) {
            this.listeners.forEach(fn => {
                try { fn(status, this.printers); } catch (e) {}
            });
        },

        // Fetch OS printers list
        async refreshPrinters() {
            if (!this.connected) return [];
            try {
                const list = await qz.printers.find();
                this.printers = Array.isArray(list) ? list : [list];
                return this.printers;
            } catch (e) {
                console.warn('[QZBridge] Error fetching printers:', e);
                return [];
            }
        },

        // Get configured or best matching printer
        getPrinter(type = 'receipt') {
            const keyMap = {
                receipt: 'pos_receipt_printer',
                label: 'pos_label_printer',
                barcode: 'pos_barcode_printer'
            };
            const saved = localStorage.getItem(keyMap[type] || 'pos_receipt_printer') || localStorage.getItem('pos_selected_printer');
            if (saved) return saved;

            if (this.printers.length > 0) {
                if (type === 'label' || type === 'barcode') {
                    const match = this.printers.find(p => /label|sticker|grozziie|tp878|xprinter|zebra|tsc|gp-/i.test(p));
                    if (match) return match;
                } else {
                    const match = this.printers.find(p => /pos|receipt|thermal|80|58|c80|tm-t|xp-|rp/i.test(p));
                    if (match) return match;
                }
                return this.printers[0];
            }
            return null;
        },

        /**
         * Kick Cash Drawer via ESC/POS Commands
         * Pin 2: 1B 70 00 19 FA
         * Pin 5: 1B 70 01 19 FA
         */
        async kickCashDrawer(printerName = null) {
            const printer = printerName || this.getPrinter('receipt');
            if (!printer) {
                console.warn('[QZBridge] No receipt printer found for drawer kick');
                return false;
            }

            if (!this.connected) {
                const ok = await this.connect();
                if (!ok) return false;
            }

            try {
                const config = qz.configs.create(printer);
                // Send raw ESC/POS pulses for both Pin 2 (standard) and Pin 5 (alternate)
                const data = [
                    '\x1B\x70\x00\x19\xFA',
                    '\x1B\x70\x01\x19\xFA'
                ];
                await qz.print(config, data);
                console.log('[QZBridge] Cash drawer kick pulse sent to:', printer);
                return true;
            } catch (e) {
                console.error('[QZBridge] Cash drawer kick error:', e);
                return false;
            }
        },

        /**
         * Silent Print Receipt (Pixel / Raster HTML)
         * Ensures 100% crisp Lao typography and automatic paper cutting
         */
        async printReceipt(htmlOrUrl, options = {}) {
            const printer = options.printer || this.getPrinter('receipt');
            if (!printer) {
                console.warn('[QZBridge] No receipt printer found');
                return false;
            }

            if (!this.connected) {
                const ok = await this.connect();
                if (!ok) return false;
            }

            try {
                const is58mm = options.size === '58mm' || (options.size && options.size.includes('56'));
                const printWidth = is58mm ? 48 : 72; // mm printable area

                const config = qz.configs.create(printer, {
                    rasterize: true,
                    density: 203,
                    colorType: 'grayscale',
                    scaleContent: true,
                    units: 'mm',
                    size: { width: printWidth },
                    margins: { top: 0, right: 0, bottom: 0, left: 0 }
                });

                let printData = [];
                const isUrl = typeof htmlOrUrl === 'string' && (htmlOrUrl.startsWith('http://') || htmlOrUrl.startsWith('https://') || htmlOrUrl.startsWith('/'));

                if (isUrl) {
                    printData.push({
                        type: 'pixel',
                        format: 'html',
                        flavor: 'file',
                        data: htmlOrUrl
                    });
                } else {
                    printData.push({
                        type: 'pixel',
                        format: 'html',
                        flavor: 'plain',
                        data: htmlOrUrl
                    });
                }

                // Automatic Paper Cut command: ESC i / GS V 65 3
                if (options.cut !== false) {
                    printData.push('\x1D\x56\x41\x03');
                }

                await qz.print(config, printData);
                console.log('[QZBridge] Receipt printed silently via QZ Tray:', printer);
                return true;
            } catch (e) {
                console.error('[QZBridge] Receipt print error:', e);
                return false;
            }
        },

        /**
         * Silent Print Medicine Label / Sticker
         * Supports standard sticker dimensions (80x50mm, 70x50mm, 50x30mm)
         */
        async printLabel(htmlOrUrl, options = {}) {
            const printer = options.printer || this.getPrinter('label');
            if (!printer) {
                console.warn('[QZBridge] No label printer found');
                return false;
            }

            if (!this.connected) {
                const ok = await this.connect();
                if (!ok) return false;
            }

            try {
                let widthMm = 80;
                let heightMm = 50;
                if (options.size === '7x5') { widthMm = 70; heightMm = 50; }
                else if (options.size === '5x3') { widthMm = 50; heightMm = 30; }
                else if (options.size === '4x3') { widthMm = 40; heightMm = 30; }
                else if (options.widthMm && options.heightMm) {
                    widthMm = options.widthMm;
                    heightMm = options.heightMm;
                }

                const config = qz.configs.create(printer, {
                    rasterize: true,
                    density: 203,
                    colorType: 'color',
                    units: 'mm',
                    size: { width: widthMm, height: heightMm },
                    margins: 0,
                    orientation: options.orientation || 'default'
                });

                let printData = [];
                const isUrl = typeof htmlOrUrl === 'string' && (htmlOrUrl.startsWith('http://') || htmlOrUrl.startsWith('https://') || htmlOrUrl.startsWith('/'));

                if (isUrl) {
                    printData.push({
                        type: 'pixel',
                        format: 'html',
                        flavor: 'file',
                        data: htmlOrUrl
                    });
                } else {
                    printData.push({
                        type: 'pixel',
                        format: 'html',
                        flavor: 'plain',
                        data: htmlOrUrl
                    });
                }

                await qz.print(config, printData);
                console.log('[QZBridge] Medicine label printed silently via QZ Tray:', printer);
                return true;
            } catch (e) {
                console.error('[QZBridge] Medicine label print error:', e);
                return false;
            }
        }
    };

    // Auto-connect attempt on page load
    document.addEventListener('DOMContentLoaded', function () {
        if (QZBridge.isAvailable()) {
            QZBridge.connect().catch(() => {});
        }
    });

    window.QZBridge = QZBridge;
})(window);
