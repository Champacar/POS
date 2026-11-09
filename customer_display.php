<?php
require_once 'includes/functions.php';
try {
    $settings = getShopSettings();
} catch (Exception $e) {
    $settings = [];
}
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ໜ້າຈໍສະແດງຜົນລູກຄ້າ - <?= sanitize($settings['shop_name'] ?? SITE_NAME) ?>">
    <title>ໜ້າຈໍສຳລັບລູກຄ້າ - <?= sanitize($settings['shop_name'] ?? 'POS') ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/<?= htmlspecialchars(!empty($settings['favicon']) ? $settings['favicon'] : 'favicon.png') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css">
    <link href="<?= BASE_URL ?>assets/vendor/fonts/noto-sans-lao.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2e7d32;
            --primary-light: #e8f5e9;
            --secondary: #eef2f5;
            --text-dark: #263238;
            --text-light: #546e7a;
            --danger: #d32f2f;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Noto Sans Lao', sans-serif; }
        body { display: flex; height: 100vh; background-color: var(--secondary); overflow: hidden; }
        @media (max-width: 768px) {
            body {
                flex-direction: column;
                overflow-y: auto;
                height: auto;
                min-height: 100vh;
            }
            .cfd-left {
                min-height: 260px;
            }
            .cfd-right {
                border-left: none;
                border-top: 2px solid #ddd;
            }
        }
        
        /* Left: Media Area */
        .cfd-left {
            flex: 7;
            position: relative;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .cfd-media {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .cfd-marquee {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 15px;
            font-size: 24px;
            white-space: nowrap;
            overflow: hidden;
        }
        .cfd-marquee span {
            display: inline-block;
            animation: marquee 15s linear infinite;
        }
        @keyframes marquee {
            0% { transform: translateX(100vw); }
            100% { transform: translateX(-100%); }
        }

        /* Right: Cart Area */
        .cfd-right {
            flex: 3;
            background: #fff;
            display: flex;
            flex-direction: column;
            border-left: 2px solid #ddd;
            box-shadow: -4px 0 15px rgba(0,0,0,0.05);
        }
        .cfd-header {
            padding: 20px;
            background: var(--primary);
            color: #fff;
            text-align: center;
        }
        .cfd-header h1 { font-size: 28px; margin-bottom: 5px; }
        .cfd-header p { font-size: 16px; opacity: 0.9; }

        .cfd-cart {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            font-size: 18px;
        }
        .cart-item-name { font-weight: 600; color: var(--text-dark); margin-bottom: 4px; }
        .cart-item-meta { color: var(--text-light); font-size: 14px; }
        .cart-item-price { font-weight: 700; color: var(--text-dark); text-align: right; }

        .cfd-totals {
            padding: 20px;
            background: #f9fafb;
            border-top: 2px solid #eee;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text-light);
        }
        .total-row.grand-total {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary);
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px dashed #ddd;
        }

        /* Overlay for Payment / QR Code */
        .cfd-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .cfd-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .qr-box {
            background: #fff;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        .qr-box img {
            width: 300px;
            height: 300px;
            object-fit: contain;
            margin-bottom: 15px;
        }
        .qr-amount {
            font-size: 48px;
            font-weight: 700;
            color: var(--danger);
        }
        .qr-title {
            font-size: 24px;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        .payment-success {
            text-align: center;
        }
        .payment-success i {
            font-size: 80px;
            color: var(--primary);
            margin-bottom: 20px;
        }
        .payment-success h2 {
            font-size: 36px;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        .payment-success p {
            font-size: 24px;
            color: var(--text-light);
        }

        .empty-cart {
            text-align: center;
            color: var(--text-light);
            margin-top: 50px;
        }
        .empty-cart i { font-size: 64px; margin-bottom: 20px; opacity: 0.5; }
        .empty-cart p { font-size: 24px; }
    </style>
</head>
<body>

    <div class="cfd-left">
        <?php if(!empty($settings['cfd_media'])): ?>
            <?php if(str_ends_with(strtolower($settings['cfd_media']), '.mp4')): ?>
                <video class="cfd-media" src="assets/media/<?= $settings['cfd_media'] ?>" autoplay loop muted></video>
            <?php else: ?>
                <img class="cfd-media" src="assets/media/<?= $settings['cfd_media'] ?>" alt="Media">
            <?php endif; ?>
        <?php else: ?>
            <div style="color:#fff;font-size:48px;opacity:0.5;">ຍິນດີຕ້ອນຮັບ</div>
        <?php endif; ?>

        <div class="cfd-marquee">
            <span>ຍິນດີຕ້ອນຮັບສູ່ <?= sanitize($settings['shop_name'] ?? 'ຮ້ານຂອງພວກເຮົາ') ?> - ຂໍຂອບໃຈທີ່ໃຊ້ບໍລິການ</span>
        </div>

        <div class="cfd-overlay" id="paymentOverlay">
            <!-- Content injected via JS -->
        </div>
    </div>

    <div class="cfd-right">
        <div class="cfd-header">
            <h1><?= sanitize($settings['shop_name'] ?? 'ຮ້ານຂາຍຢາດີ') ?></h1>
            <p>ລາຍການສິນຄ້າຂອງທ່ານ</p>
        </div>
        <div class="cfd-cart" id="cfdCartList">
            <!-- Cart items injected via JS -->
        </div>
        <div class="cfd-totals">
            <div class="total-row">
                <span>ລວມມູນຄ່າ:</span>
                <span id="cfdSubtotal">0</span>
            </div>
            <div class="total-row" id="cfdTaxRow" style="display:none;">
                <span>ອາກອນ:</span>
                <span id="cfdTax">0</span>
            </div>
            <div class="total-row grand-total">
                <span>ຍອດຊຳລະ:</span>
                <span id="cfdTotal">0</span>
            </div>
        </div>
    </div>

    <script>
        const qrCodeUrl = "<?= !empty($settings['qr_code']) ? 'assets/images/' . $settings['qr_code'] : '' ?>";
        const enableDynamicQr = <?= !empty($settings['enable_dynamic_qr']) ? 'true' : 'false' ?>;
        const bankName = "<?= sanitize($settings['bank_name'] ?? 'BCEL One') ?>";
        const bankAccountName = "<?= sanitize($settings['bank_account_name'] ?? '') ?>";
        let lastCfdQrAmount = null;
        let lastCfdQrSvg = '';
        let isFetchingCfdQr = false;

        function formatCurrency(num) {
            return new Intl.NumberFormat('lo-LA').format(Math.round(num));
        }

        async function fetchDynamicQrSvg(amount) {
            if (lastCfdQrAmount === amount && lastCfdQrSvg) {
                return lastCfdQrSvg;
            }
            if (isFetchingCfdQr) return lastCfdQrSvg;
            isFetchingCfdQr = true;
            try {
                const res = await fetch(`api/dynamic_qr.php?amount=${amount}&currency=LAK&size=200`);
                const data = await res.json();
                if (data && data.success && data.svg) {
                    lastCfdQrAmount = amount;
                    lastCfdQrSvg = data.svg;
                }
            } catch (err) {
                console.error('CFD QR error:', err);
            } finally {
                isFetchingCfdQr = false;
            }
            return lastCfdQrSvg;
        }

        function updateUI() {
            // Get data from localStorage (set by pos.js)
            const cartStr = localStorage.getItem('pos_cart');
            const totalsStr = localStorage.getItem('pos_totals');
            const stateStr = localStorage.getItem('pos_state');

            const cart = cartStr ? JSON.parse(cartStr) : [];
            const totals = totalsStr ? JSON.parse(totalsStr) : { subtotal: 0, tax: 0, total: 0 };
            const state = stateStr ? JSON.parse(stateStr) : { status: 'idle' }; // idle, checkout, success

            // Render Cart
            const cartList = document.getElementById('cfdCartList');
            if (cart.length === 0) {
                cartList.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-basket"></i>
                        <p>ຍິນດີຕ້ອນຮັບ!</p>
                        <p style="font-size:18px;margin-top:10px;">ກະລຸນາລໍຖ້າພະນັກງານສະແກນສິນຄ້າ...</p>
                    </div>
                `;
            } else {
                cartList.innerHTML = cart.map(item => `
                    <div class="cart-item">
                        <div>
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-item-meta">${item.quantity} ${item.unit || 'ແຜງ'} × ${formatCurrency(item.price)}</div>
                        </div>
                        <div class="cart-item-price">${formatCurrency(item.quantity * item.price)}</div>
                    </div>
                `).join('');
                // Auto scroll to bottom
                cartList.scrollTop = cartList.scrollHeight;
            }

            // Render Totals
            document.getElementById('cfdSubtotal').textContent = formatCurrency(totals.subtotal);
            document.getElementById('cfdTotal').textContent = formatCurrency(totals.total);
            if (totals.tax > 0) {
                document.getElementById('cfdTaxRow').style.display = 'flex';
                document.getElementById('cfdTax').textContent = formatCurrency(totals.tax);
            } else {
                document.getElementById('cfdTaxRow').style.display = 'none';
            }

            // Render Overlay
            const overlay = document.getElementById('paymentOverlay');
            if (state.status === 'checkout') {
                if (state.payment_method === 'transfer' || state.payment_method === 'split') {
                    const amountToShow = state.payment_method === 'split' ? state.transfer_amount : totals.total;
                    
                    if (enableDynamicQr) {
                        fetchDynamicQrSvg(amountToShow || 0).then(svgHtml => {
                            overlay.innerHTML = `
                                <div class="qr-box" style="padding: 24px; max-width: 420px; border-radius: 16px;">
                                    <div style="display:inline-flex; align-items:center; gap:8px; background:#e0f2fe; color:#0284c7; padding:4px 12px; border-radius:20px; font-size:14px; font-weight:700; margin-bottom:10px;">
                                        <i class="fas fa-bolt" style="color:#f59e0b;"></i> Dynamic LAO QR • ${bankName}
                                    </div>
                                    <div class="qr-title" style="font-size:19px; margin-bottom:6px;">ສະແກນເພື່ອຊຳລະເງິນ</div>
                                    <div style="margin: 8px auto; display: flex; justify-content: center; background: #fff; padding: 10px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); width:200px; height:200px;">
                                        ${svgHtml || '<div style="display:flex;align-items:center;justify-content:center;height:100%;"><i class="fas fa-spinner fa-spin" style="font-size:32px;color:var(--primary);"></i></div>'}
                                    </div>
                                    <div style="color:var(--text-light);font-size:15px; margin-top:4px;">ຍອດເງິນທີ່ຕ້ອງໂອນ:</div>
                                    <div class="qr-amount" style="font-size:36px; color:var(--primary); font-weight:800;">${formatCurrency(amountToShow || 0)} ₭</div>
                                    ${bankAccountName ? `<div style="font-size:13.5px; color:#64748b; margin-top:4px;">ບັນຊີ: <b>${bankAccountName}</b></div>` : ''}
                                </div>
                            `;
                            overlay.classList.add('active');
                        });
                    } else if (qrCodeUrl) {
                        overlay.innerHTML = `
                            <div class="qr-box">
                                <div class="qr-title">ສະແກນເພື່ອຊຳລະເງິນ</div>
                                <img src="${qrCodeUrl}" alt="QR Code">
                                <div style="color:var(--text-light);font-size:18px;">ຈຳນວນເງິນ:</div>
                                <div class="qr-amount">${formatCurrency(amountToShow || 0)} ₭</div>
                            </div>
                        `;
                        overlay.classList.add('active');
                    } else {
                        overlay.innerHTML = `
                            <div class="qr-box">
                                <div class="qr-title">ກຳລັງລໍຖ້າການໂອນເງິນ...</div>
                                <div class="qr-amount">${formatCurrency(totals.total)} ₭</div>
                            </div>
                        `;
                        overlay.classList.add('active');
                    }
                } else if (state.payment_method === 'cash') {
                    const currInfo = JSON.parse(localStorage.getItem('pos_currency') || '{"currency":"LAK","rate":1}');
                    let foreignSub = '';
                    if (currInfo && currInfo.currency && currInfo.currency !== 'LAK') {
                        const sign = currInfo.currency === 'THB' ? '฿' : '$';
                        const rate = Number(currInfo.rate) || 1;
                        const foreignAmt = (totals.total / rate).toFixed(2);
                        foreignSub = `
                            <div style="font-size: 32px; font-weight: 700; color: var(--primary); margin-top: 10px;">
                                ~ ${foreignAmt} ${sign}
                            </div>
                            <div style="font-size: 15px; color: var(--text-light); margin-top: 4px;">
                                (ອັດຕາແລກປ່ຽນ: 1 ${sign} = ${formatCurrency(rate)} ₭)
                            </div>
                        `;
                    }

                    overlay.innerHTML = `
                        <div class="qr-box" style="width: 90%; max-width: 440px; padding: 36px 24px;">
                            <div class="qr-title">ຍອດຊຳລະທັງໝົດ</div>
                            <div class="qr-amount" style="font-size: 54px;">${formatCurrency(totals.total)} ₭</div>
                            ${foreignSub}
                        </div>
                    `;
                    overlay.classList.add('active');
                } else {
                    overlay.classList.remove('active');
                }
            } else if (state.status === 'success') {
                const currInfo = JSON.parse(localStorage.getItem('pos_currency') || '{"currency":"LAK","rate":1}');
                let foreignChangeSub = '';
                if (currInfo && currInfo.currency && currInfo.currency !== 'LAK' && state.change > 0) {
                    const sign = currInfo.currency === 'THB' ? '฿' : '$';
                    const rate = Number(currInfo.rate) || 1;
                    const changeForeign = (state.change / rate).toFixed(2);
                    foreignChangeSub = `<div style="font-size:18px; color:var(--text-medium); margin-top:4px;">(ຫຼື ທຽບເທົ່າ: <strong>${changeForeign} ${sign}</strong>)</div>`;
                }

                overlay.innerHTML = `
                    <div class="payment-success">
                        <i class="fas fa-check-circle"></i>
                        <h2>ຊຳລະເງິນສຳເລັດ</h2>
                        <p>ເງິນທອນ: <strong style="color:var(--danger); font-size:28px;">${formatCurrency(state.change || 0)} ₭</strong></p>
                        ${foreignChangeSub}
                        <p style="margin-top:20px;font-size:18px;">ຂໍຂອບໃຈທີ່ໃຊ້ບໍລິການ!</p>
                    </div>
                `;
                overlay.classList.add('active');
            } else {
                overlay.classList.remove('active');
            }
        }

        // Listen for changes in localStorage
        window.addEventListener('storage', function(e) {
            if (['pos_cart', 'pos_totals', 'pos_state', 'pos_currency'].includes(e.key)) {
                updateUI();
            }
        });

        // Initialize UI
        updateUI();
    </script>
</body>
</html>
