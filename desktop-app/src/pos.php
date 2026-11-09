<?php
/**
 * ໜ້າຂາຍ - POS Sales Page
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'ໜ້າຂາຍ';

// Get categories
try {
    $db = getDB();
    $categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Get products
try {
    $products = $db->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY p.name")->fetchAll();
} catch (Exception $e) {
    $products = [];
}

// Get multi-barcodes for all active products
$barcodesByProduct = [];
try {
    $bcRows = $db->query("SELECT product_id, barcode, barcode_type, unit, multiplier, price FROM product_barcodes WHERE is_active = 1")->fetchAll();
    foreach ($bcRows as $bcr) {
        $barcodesByProduct[$bcr['product_id']][] = [
            'barcode' => (string)$bcr['barcode'],
            'type' => (string)$bcr['barcode_type'],
            'unit' => $bcr['unit'] ?: '',
            'multiplier' => (int)$bcr['multiplier'],
            'price' => $bcr['price'] !== null ? (float)$bcr['price'] : null
        ];
    }
} catch (Exception $e) {
    $barcodesByProduct = [];
}

// Get shop settings for tax and features
try {
    $shopSettings = getShopSettings();
    $taxRate = $shopSettings['tax_rate'] ?? 10;
    $taxType = $shopSettings['tax_type'] ?? 'excluded';
    $enableMultiCurrency = (int)($shopSettings['enable_multi_currency'] ?? 1);
    $exchangeRateThb = (float)($shopSettings['exchange_rate_thb'] ?? 680.00);
    $exchangeRateUsd = (float)($shopSettings['exchange_rate_usd'] ?? 22000.00);
    $enableCashDrawer = (int)($shopSettings['enable_cash_drawer'] ?? 1);
    $enableDynamicQr = (int)($shopSettings['enable_dynamic_qr'] ?? 1);
    $bankName = $shopSettings['bank_name'] ?? 'BCEL One';
    $bankAccountNumber = $shopSettings['bank_account_number'] ?? '';
    $bankAccountName = $shopSettings['bank_account_name'] ?? '';
} catch (Exception $e) {
    $taxRate = 10;
    $taxType = 'excluded';
    $enableMultiCurrency = 1;
    $exchangeRateThb = 680.00;
    $exchangeRateUsd = 22000.00;
    $enableCashDrawer = 1;
    $enableDynamicQr = 1;
    $bankName = 'BCEL One';
    $bankAccountNumber = '';
    $bankAccountName = '';
}

$extraScripts = ['pos.js', 'medicine_label.js'];

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<script>
window.SHOP_DYNAMIC_QR = <?= json_encode([
    'enabled' => $enableDynamicQr,
    'bankName' => $bankName,
    'accountNumber' => $bankAccountNumber,
    'accountName' => $bankAccountName
], JSON_UNESCAPED_UNICODE) ?>;

window.onerror = function(msg, url, line) {
    console.error("JS Error: ", msg, url, line);
    return false;
};
</script>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">ໜ້າຂາຍ</h1>
            <p style="font-size:0.85rem;color:var(--text-medium);margin-top:2px;">ລະບົບຂາຍຢາໜ້າຮ້ານ, ຕັດສະຕ໋ອກດ່ວນ ແລະ ຄິດໄລ່ເງິນ (Point of Sale)</p>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <!-- Shift Status Button -->
            <button type="button" class="btn btn-outline btn-sm" id="btnShiftStatus" onclick="handleShiftButtonClick()" style="border-color:#ffab00; color:#b76e00; font-weight:600; min-height:36px;" title="ຈັດການກະຂາຍ">
                <i class="fas fa-clock"></i> <span id="shiftStatusText">ກວດສອບກະ...</span>
            </button>

            <?php if (!empty($enableCashDrawer)): ?>
            <button type="button" class="btn btn-outline btn-sm" id="btnManualOpenDrawer" onclick="manualOpenDrawer()" style="color:var(--text-dark); font-weight:600;" title="ສັ່ງເປີດລິ້ນຊັກເງິນ (F7)">
                <i class="fas fa-cash-register"></i> ເປີດລິ້ນຊັກ (F7)
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline btn-sm" id="btnLastSaleLabel" onclick="printLastSaleLabel()" style="display:none; color:var(--primary); font-weight:600;" title="ພິມສະຫຼາກຢາຂອງບິນລ່າສຸດ">
                <i class="fas fa-prescription-bottle-alt"></i> ພິມສະຫຼາກບິນລ່າສຸດ
            </button>
            <a href="<?= BASE_URL ?>customer_display.php" target="_blank" class="btn btn-outline btn-sm" title="ເປີດໜ້າຈໍລູກຄ້າ (CFD)">
                <i class="fas fa-desktop"></i> ເປີດໜ້າຈໍລູກຄ້າ
            </a>
        </div>
    </div>

    <div class="pos-layout">
        <!-- Left: Products -->
        <div class="pos-products">
            <!-- Category Tabs -->
            <div class="tabs" id="categoryTabs">
                <div class="tab active" tabindex="0" role="button" data-category="all" onclick="filterCategory('all', this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();filterCategory('all', this);}">ທັງໝົດ</div>
                <?php foreach ($categories as $cat): ?>
                <div class="tab" tabindex="0" role="button" data-category="<?= $cat['id'] ?>" onclick="filterCategory(<?= $cat['id'] ?>, this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();filterCategory(<?= $cat['id'] ?>, this);}"><?= sanitize($cat['name']) ?></div>
                <?php endforeach; ?>
            </div>

            <!-- Search & Product Count -->
            <div class="card" style="flex:1;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
                    <div>
                        <h3 style="font-size:1rem;font-weight:700;">ລາຍການຢາ</h3>
                        <span style="font-size:0.75rem;color:var(--text-light);">ຄົ້ນຫາ ຫຼື ສະແກນບາໂຄດ (F2)</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:240px;justify-content:flex-end;">
                        <div class="search-box" style="flex:1;max-width:320px;min-width:140px;">
                            <i class="fas fa-barcode"></i>
                            <input type="text" id="productSearch" autofocus placeholder="ຄົ້ນຫາ ຫຼື ສະແກນບາໂຄດ (F2)..." oninput="searchProducts(this.value)">
                        </div>
                        <span style="font-size:0.85rem;color:var(--text-medium);white-space:nowrap;" id="productCount"><?= count($products) ?> ລາຍການ</span>
                    </div>
                </div>

                <!-- Product Grid -->
                <div class="product-grid" id="productGrid">
                    <?php if (empty($products)): ?>
                    <div class="empty-state" style="grid-column:1/-1; padding:40px 10px; text-align:center;">
                        <i class="fas fa-boxes" style="font-size:40px; color:var(--text-light); margin-bottom:10px;"></i>
                        <p style="margin-bottom:12px;">ຍັງບໍ່ມີລາຍການຢາໃນລະບົບ</p>
                        <a href="<?= BASE_URL ?>products.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> ໄປໜ້າເພີ່ມສິນຄ້າ</a>
                    </div>
                    <?php else: ?>
                        <?php 
                        $todayDate = date('Y-m-d');
                        $soonDate = date('Y-m-d', strtotime('+30 days'));
                        foreach ($products as $i => $product): 
                            $isExpired = (!empty($product['expiry_date']) && $product['expiry_date'] < $todayDate);
                            $isExpiringSoon = (!$isExpired && !empty($product['expiry_date']) && $product['expiry_date'] <= $soonDate);
                            $daysLeft = !empty($product['expiry_date']) ? (int)((strtotime($product['expiry_date']) - strtotime($todayDate)) / 86400) : null;
                        ?>
                        <div class="product-card <?= $isExpired ? 'product-expired' : '' ?>" 
                             data-id="<?= $product['id'] ?>"
                             data-name="<?= sanitize($product['name']) ?>"
                             data-price="<?= $product['selling_price'] ?>"
                             data-stock="<?= $product['stock_quantity'] ?>"
                             data-unit="<?= sanitize($product['unit'] ?? 'ແຜງ') ?>"
                             data-category="<?= $product['category_id'] ?>"
                             data-barcode="<?= sanitize($product['barcode'] ?? '') ?>"
                             data-multi-barcodes='<?= htmlspecialchars(json_encode($barcodesByProduct[$product['id']] ?? []), ENT_QUOTES, 'UTF-8') ?>'
                             data-image="<?= htmlspecialchars($product['image'] ?? '') ?>"
                             data-expiry="<?= htmlspecialchars($product['expiry_date'] ?? '') ?>"
                             data-expired="<?= $isExpired ? '1' : '0' ?>"
                             tabindex="<?= $i === 0 ? '0' : '-1' ?>" role="button" aria-label="<?= sanitize($product['name']) ?> ລາຄາ <?= formatCurrency($product['selling_price']) ?> ກີບ" onclick="addToCart(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();addToCart(this);}">
                            <?php if ($isExpired): ?>
                                <div class="product-card-stock-danger" style="position:absolute;top:8px;right:8px;background:var(--danger);color:#fff;padding:2px 8px;border-radius:12px;font-size:0.75rem;font-weight:700;z-index:2;box-shadow:0 2px 6px rgba(211,47,47,0.35);">ໝົດອາຍຸ</div>
                            <?php elseif ($isExpiringSoon): ?>
                                <div style="position:absolute;top:8px;right:8px;background:#ff8b00;color:#fff;padding:2px 8px;border-radius:12px;font-size:0.72rem;font-weight:700;z-index:2;box-shadow:0 2px 6px rgba(255,139,0,0.35);" title="ໝົດອາຍຸໃນ <?= $daysLeft ?> ວັນ">ໝົດອາຍຸ <?= $daysLeft ?>ວ</div>
                            <?php elseif ($product['stock_quantity'] > 0 && $product['stock_quantity'] <= $product['min_stock']): ?>
                                <div class="product-card-stock-warning">ໃກ້ໝົດ</div>
                            <?php endif; ?>
                            <?php if (!empty($product['image']) && file_exists('assets/images/products/' . $product['image'])): ?>
                                <div class="product-card-thumb">
                                    <img src="assets/images/products/<?= htmlspecialchars($product['image']) ?>" alt="<?= sanitize($product['name']) ?>" class="product-thumb-img" loading="lazy">
                                </div>
                            <?php else: ?>
                                <div class="product-card-icon">
                                    <i class="fas fa-pills"></i>
                                </div>
                            <?php endif; ?>
                            <div class="product-card-name"><?= sanitize($product['name']) ?></div>
                            <div class="product-card-price"><?= formatCurrency($product['selling_price']) ?> ກີບ <small style="font-weight:600;color:var(--text-medium);font-size:0.8rem;">/ <?= sanitize($product['unit'] ?? 'ແຜງ') ?></small></div>
                            <div class="product-card-stock">ຄົງເຫຼືອ: <?= $product['stock_quantity'] ?> <?= sanitize($product['unit'] ?? 'ແຜງ') ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Cart -->
        <div class="pos-cart">
            <div class="pos-cart-header" style="flex-direction: column; align-items: stretch; gap: 10px;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <h3 style="font-size: 0.95rem; font-weight: 700; margin:0;">ລາຍການສັ່ງຊື້ປັດຈຸບັນ</h3>
                    <span style="font-size:0.75rem;color:var(--text-light);font-weight:600;" id="cartItemCount">0 ລາຍການ</span>
                </div>
                <div class="pos-cart-quick-actions" style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                    <button class="btn btn-outline btn-sm" id="btnPriceCheck" style="color: var(--info); border-color: rgba(2,119,189,0.35); padding: 8px 10px; min-height: 42px; font-size: 0.85rem; font-weight: 600; justify-content:center; border-radius: 8px;" onclick="openPriceCheckModal()" title="ເຊັກລາຄາຢາ (F4)">
                        <i class="fas fa-search-dollar"></i> ເຊັກລາຄາ (F4)
                    </button>
                    <button class="btn btn-outline btn-sm" id="btnHoldCart" style="color: var(--warning); border-color: rgba(178,106,0,0.35); padding: 8px 10px; min-height: 42px; font-size: 0.85rem; font-weight: 600; justify-content:center; border-radius: 8px;" onclick="holdCart()" title="ພັກບິນປັດຈຸບັນ (F8)">
                        <i class="fas fa-pause"></i> ພັກບິນ (F8)
                    </button>
                    <button class="btn btn-outline btn-sm position-relative" id="btnHeldCarts" style="padding: 8px 10px; min-height: 42px; font-size: 0.85rem; font-weight: 600; justify-content:center; border-radius: 8px;" onclick="openHeldCartsModal()" title="ລາຍການບິນທີ່ພັກໄວ້">
                        <i class="fas fa-folder-open"></i> ບິນພັກ
                        <span class="badge badge-warning" id="heldCartsCount" style="position:absolute; top:-6px; right:-6px; padding:2px 6px; border-radius:50%; font-size:10px; font-weight:700; display:none; background:var(--danger); color:#fff;">0</span>
                    </button>
                    <button class="btn btn-outline btn-sm" id="btnClearCart" style="color: var(--danger); border-color: rgba(211,47,47,0.35); padding: 8px 10px; min-height: 42px; font-size: 0.85rem; font-weight: 600; justify-content:center; border-radius: 8px;" onclick="clearCart()" title="ລ້າງລາຍການທັງໝົດ">
                        <i class="fas fa-trash-alt"></i> ລ້າງກະຕ່າ
                    </button>
                </div>
            </div>

            <div class="pos-cart-items" id="cartItems">
                <div class="empty-state" id="cartEmpty">
                    <i class="fas fa-file-invoice"></i>
                    <p>ຍັງບໍ່ມີລາຍການໃດໆ</p>
                    <span style="font-size:0.75rem;color:var(--text-light);">ເລືອກລາຍການໃສ່ກະຕ່າ</span>
                </div>
            </div>

            <div class="pos-cart-summary">
                <!-- Discount -->
                <div class="summary-row" style="margin-bottom:8px;">
                    <span>ສ່ວນຫຼຸດ (ກີບ)</span>
                    <input type="number" id="discountInput" class="form-control" style="width:120px;text-align:right;padding:6px 10px;" value="0" min="0" oninput="updateCartSummary()" <?= hasPermission('can_give_discount') ? '' : 'disabled title="ທ່ານບໍ່ມີສິດໃຫ້ສ່ວນຫຼຸດ"' ?>>
                </div>

                <!-- Totals -->
                <div class="summary-row">
                    <span>ລວມຍອດ</span>
                    <span id="subtotalDisplay">0 ກີບ</span>
                </div>
                <div class="summary-row" id="posTaxRow" style="<?= $taxType === 'none' ? 'display:none;' : '' ?>">
                    <span id="posTaxLabel">
                        <?= $taxType === 'included' ? 'ອາກອນລວມໃນ' : 'ອາກອນນອກ' ?> (<?= $taxRate ?>%)
                    </span>
                    <span id="taxDisplay">0 ກີບ</span>
                </div>
                <div class="summary-row total">
                    <span>ຍອດລວມທີ່ຕ້ອງຊຳລະ</span>
                    <span id="totalDisplay">0 ກີບ</span>
                </div>
            </div>

            <div class="pos-cart-actions">
                <button class="btn w-100 text-white" onclick="processCheckout()" id="checkoutBtn" title="ຊຳລະການຂາຍ (F9)" disabled>
                    <i class="fas fa-check-circle me-2"></i>
                    ຊຳລະການຂາຍ & ພິມບິນ (F9)
                </button>
            </div>
        </div>
    </div>
</main>

<!-- Mobile Floating Cart Pill / Quick Jump -->
<div class="pos-mobile-cart-bar" id="posMobileCartBar" style="display:none;" onclick="const cartEl = document.querySelector('.pos-cart'); if (cartEl) cartEl.scrollIntoView({ behavior: 'smooth' });">
    <div style="display:flex; align-items:center; gap:8px;">
        <i class="fas fa-shopping-cart" style="font-size:1.1rem;"></i>
        <span id="posMobileCartCount" style="font-weight:600;">0 ລາຍການ</span>
        <span style="opacity:0.6;">|</span>
        <span id="posMobileCartTotal" style="font-weight:700;">0 ກີບ</span>
    </div>
    <div style="display:flex; align-items:center; gap:6px; font-size:0.85rem; font-weight:600; background:rgba(255,255,255,0.2); padding:4px 10px; border-radius:8px;">
        <span>ເບິ່ງກະຕ່າ</span>
        <i class="fas fa-arrow-down"></i>
    </div>
</div>

<!-- Checkout Modal -->
<div class="modal-overlay" id="checkoutModal" style="z-index: 2000;">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <h3>ຊຳລະເງິນ</h3>
            <button class="modal-close" aria-label="ປິດໜ້າຕ່າງຊຳລະເງິນ" onclick="closeCheckoutModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="background:#f4f6f8; padding:15px; border-radius:10px; text-align:center; margin-bottom:20px;">
                <div style="font-size:16px; color:#5e6c84;">ຍອດຊຳລະທັງໝົດ</div>
                <div id="checkoutTotalDisplay" style="font-size:36px; font-weight:700; color:var(--primary);">0 ກີບ</div>
            </div>

            <div class="form-group">
                <label class="form-label">ຮູບແບບການຊຳລະເງິນ</label>
                <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                    <button class="btn btn-outline flex-1" id="btnPayCash" onclick="selectPaymentMethod('cash')"><i class="fas fa-money-bill-wave"></i> ສົດ</button>
                    <button class="btn btn-outline flex-1" id="btnPayTransfer" onclick="selectPaymentMethod('transfer')"><i class="fas fa-qrcode"></i> ໂອນ</button>
                    <button class="btn btn-outline flex-1" id="btnPaySplit" onclick="selectPaymentMethod('split')"><i class="fas fa-columns"></i> ແບ່ງຈ່າຍ</button>
                    <button class="btn btn-outline flex-1" id="btnPayCredit" onclick="selectPaymentMethod('credit')"><i class="fas fa-hand-holding-usd"></i> ຕິດໜີ້</button>
                </div>
            </div>

            <!-- Customer Name -->
            <div class="form-group">
                <label class="form-label">ຊື່ລູກຄ້າ (ບໍ່ບັງຄັບ)</label>
                <input type="text" class="form-control" id="customerNameInput" placeholder="ປ້ອນຊື່ລູກຄ້າ..." onkeydown="if(event.key==='Enter'){event.preventDefault();const cIn=document.getElementById('cashAmountInput');if(cIn&&cIn.offsetParent!==null){cIn.focus();}else{confirmCheckout();}}">
            </div>

            <!-- Cash Input -->
            <div id="cashInputSection" style="display:none;">
                <?php if (!empty($enableMultiCurrency)): ?>
                <!-- Currency Selector Tabs -->
                <div id="currencySelectorRow" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; padding:6px 10px; background:#eef2f5; border-radius:8px;">
                    <div style="font-size:13px; font-weight:600; color:#42526e;">
                        <i class="fas fa-coins" style="color:var(--primary); margin-right:4px;"></i> ສະກຸນເງິນ:
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="btn btn-sm btn-currency active" id="btnCurrLAK" onclick="setCheckoutCurrency('LAK')">₭ ກີບ</button>
                        <button type="button" class="btn btn-sm btn-currency" id="btnCurrTHB" onclick="setCheckoutCurrency('THB')">฿ ບາດ</button>
                        <button type="button" class="btn btn-sm btn-currency" id="btnCurrUSD" onclick="setCheckoutCurrency('USD')">$ ໂດລາ</button>
                    </div>
                </div>

                <!-- Currency Rate Notice (shown when THB or USD) -->
                <div id="currencyRateNotice" style="display:none; font-size:12.5px; color:#5e6c84; background:#fff8e6; border:1px solid #ffe399; padding:8px 12px; border-radius:8px; margin-bottom:12px; justify-content:space-between; align-items:center;">
                    <div>
                        <i class="fas fa-info-circle" style="color:#ffab00; margin-right:4px;"></i>
                        <span id="currencyRateText">ອັດຕາແລກປ່ຽນ: 1 ฿ = 680 ກີບ</span>
                    </div>
                    <span id="currencyForeignTotalText" style="font-weight:700; color:var(--primary); font-size:14px;">~ 0.00 ฿</span>
                </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 8px;">
                    <label class="form-label" id="cashInputLabel">ຮັບເງິນມາ (ກີບ)</label>
                    <input type="number" class="form-control" id="cashAmountInput" step="any" style="font-size:24px; font-weight:bold; height:50px;" oninput="calculateChange()" onkeydown="if(event.key==='Enter'){event.preventDefault();confirmCheckout();}">
                    <div id="cashForeignEquivalent" style="font-size:13px; color:#5e6c84; margin-top:4px; display:none;"></div>
                </div>

                <!-- Dynamic Quick Cash Buttons Container -->
                <div id="quickCashButtonsContainer" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:6px; margin-bottom:15px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(10000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+10k</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(20000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+20k</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(50000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+50k</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(100000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+100k</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="addQuickCash(500000)" style="padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;">+500k</button>
                    <button type="button" class="btn btn-outline btn-sm" style="grid-column: span 2; color:var(--primary); border-color:var(--primary-light); padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:700; border-radius:8px;" onclick="setExactCash()">ພໍດີຍອດຊຳລະ</button>
                    <button type="button" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:rgba(222,53,11,0.25); padding:8px 4px; min-height:44px; font-size:0.92rem; font-weight:600; border-radius:8px;" onclick="clearQuickCash()">ລ້າງ (0)</button>
                </div>

                <div class="form-group" style="text-align:right;">
                    <div style="font-size:16px; color:#5e6c84;">
                        ເງິນທອນ: <strong id="changeDisplay" style="font-size:24px; color:var(--primary);">0 ກີບ</strong>
                    </div>
                    <div id="changeForeignDisplay" style="font-size:13.5px; color:#5e6c84; margin-top:2px; display:none;"></div>
                </div>
            </div>

            <!-- Split Payment Input -->
            <div id="splitInputSection" style="display:none;">
                <div class="form-row-2" style="gap:15px;">
                    <div class="form-group">
                        <label class="form-label">ເງິນສົດ (ກີບ)</label>
                        <input type="number" class="form-control" id="splitCashInput" style="font-size:20px; height:45px;" oninput="calculateSplit()" onkeydown="if(event.key==='Enter'){event.preventDefault();confirmCheckout();}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ເງິນໂອນ (ກີບ)</label>
                        <input type="number" class="form-control" id="splitTransferInput" style="font-size:20px; height:45px;" oninput="calculateSplit()" onkeydown="if(event.key==='Enter'){event.preventDefault();confirmCheckout();}">
                    </div>
                </div>
                <div id="splitError" style="color:var(--danger); font-size:14px; display:none; margin-top:-5px; margin-bottom:15px;">ຍອດຊຳລະຍັງບໍ່ຄົບຖ້ວນ</div>
            </div>

            <!-- Transfer (Dynamic QR) Input Section -->
            <div id="transferInputSection" style="display:none; text-align:center; padding:16px 20px; background:#f0f7ff; border:1px solid #cce0ff; border-radius:12px; margin-bottom:15px;">
                <div style="display:inline-flex; align-items:center; gap:6px; background:#e0f2fe; color:#0284c7; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:700; margin-bottom:8px;">
                    <i class="fas fa-bolt" style="color:#f59e0b;"></i> Dynamic LAO QR • <span id="transferBankNameDisplay"><?= htmlspecialchars($bankName) ?></span>
                </div>
                <div id="transferQrContainer" style="display:flex; justify-content:center; align-items:center; min-height:160px; margin:6px auto; background:#fff; padding:8px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06); width:180px; height:180px;">
                    <i class="fas fa-spinner fa-spin" style="font-size:28px; color:var(--primary);"></i>
                </div>
                <div style="font-size:13px; color:#5e6c84; margin-top:6px;">
                    ບັນຊີ: <strong id="transferAccountDisplay"><?= htmlspecialchars($bankAccountName ?: $bankAccountNumber) ?></strong>
                </div>
                <div style="font-size:20px; font-weight:800; color:var(--primary); margin-top:2px;">
                    <span id="transferAmountDisplay">0</span> ₭
                </div>
            </div>

            <!-- Tax Invoice Section -->
            <div class="tax-invoice-section" style="margin-top:14px; border:1px solid #bae6fd; background:#f0f9ff; border-radius:10px; padding:12px 14px;">
                <label style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0; cursor:pointer; font-weight:700; color:#0369a1; font-size:0.92rem;">
                    <span style="display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-file-invoice-dollar" style="font-size:1.15rem; color:#0284c7;"></i> ອອກໃບກຳກັບພາສີ (Tax Invoice)
                    </span>
                    <input type="checkbox" id="chkIssueTaxInvoice" onchange="toggleTaxInvoiceSection()" style="width:18px; height:18px; accent-color:#0284c7; cursor:pointer;">
                </label>
                
                <div id="taxInvoiceFields" style="display:none; margin-top:12px; border-top:1px dashed #7dd3fc; padding-top:10px;">
                    <div class="form-group" style="margin-bottom:8px;">
                        <label class="form-label" style="font-size:0.8rem; margin-bottom:2px;">ຊື່ບໍລິສັດ / ອົງກອນ (Company / Buyer Name)</label>
                        <input type="text" class="form-control form-control-sm" id="taxCustomerCompany" placeholder="ຊື່ບໍລິສັດ ຫຼື ຊື່ລູກຄ້າເຕັມ...">
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px; margin-bottom:8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-size:0.8rem; margin-bottom:2px;">ເລກປະຈຳຕົວຜູ້ເສຍອາກອນ (TIN)</label>
                            <input type="text" class="form-control form-control-sm" id="taxCustomerTin" placeholder="01-xxxxxxxx">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-size:0.8rem; margin-bottom:2px;">ອັດຕາ VAT (%)</label>
                            <select class="form-control form-control-sm" id="taxVatRate" onchange="onTaxRateChangedInCheckout()">
                                <option value="7" selected>7% (ມາດຕະຖານ)</option>
                                <option value="10">10%</option>
                                <option value="0">0% (ຍົກເວັ້ນອາກອນ)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:0.8rem; margin-bottom:2px;">ທີ່ຢູ່ (Address)</label>
                        <input type="text" class="form-control form-control-sm" id="taxCustomerAddress" placeholder="ບ້ານ, ເມືອງ, ແຂວງ...">
                    </div>
                </div>
            </div>

        </div>
        <div class="modal-footer">
            <button class="btn btn-white" onclick="closeCheckoutModal()">ຍົກເລີກ</button>
            <button class="btn btn-primary" onclick="confirmCheckout()" id="confirmCheckoutBtn"><i class="fas fa-check-circle"></i> ຢືນຢັນການຊຳລະ</button>
        </div>
    </div>
</div>

<!-- Price Check Modal -->
<div class="modal-overlay" id="priceCheckModal" style="z-index: 2100;">
    <div class="modal" style="max-width:550px;">
        <div class="modal-header">
            <h3><i class="fas fa-search-dollar"></i> ເຊັກລາຄາຢາ ແລະ ສະຕ໋ອກ</h3>
            <button class="modal-close" aria-label="ປິດໜ້າຕ່າງເຊັກລາຄາ" onclick="closePriceCheckModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">ຄົ້ນຫາຢາ (ປ້ອນຊື່ ຫຼື ສະແກນບາໂຄດ)</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-barcode input-left-icon"></i>
                    <input type="text" class="form-control" id="priceCheckSearchInput" placeholder="ສະແກນບາໂຄດ ຫຼື ພິມຊື່ຢາ..." oninput="doPriceCheckSearch(this.value)">
                </div>
            </div>
            
            <div id="priceCheckResult" style="margin-top:20px; display:none; max-height: 350px; overflow-y: auto;">
                <!-- Result cards injected via JS -->
            </div>
            <div id="priceCheckPlaceholder" style="text-align:center; color:var(--text-light); padding:30px 10px;">
                <i class="fas fa-pills" style="font-size:48px; opacity:0.3; margin-bottom:15px; display:block;"></i>
                <span>ກະລຸນາສະແກນບາໂຄດ ຫຼື ປ້ອນຊື່ຢາເພື່ອເບິ່ງລາຍລະອຽດ</span>
            </div>
        </div>
    </div>
</div>

<!-- Held Carts Modal -->
<div class="modal-overlay" id="heldCartsModal" style="z-index: 2100;">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="fas fa-folder-open"></i> ລາຍການບິນທີ່ພັກໄວ້</h3>
            <button class="modal-close" aria-label="ປິດໜ້າຕ່າງບິນພັກ" onclick="closeHeldCartsModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="heldCartsList" style="max-height:400px; overflow-y:auto; padding: 5px;">
                <!-- Held carts list injected via JS -->
            </div>
        </div>
    </div>
</div>

<!-- Open Shift Modal -->
<div class="modal-overlay" id="openShiftModal" style="z-index: 2200;">
    <div class="modal" style="max-width:440px;">
        <div class="modal-header">
            <h3><i class="fas fa-play-circle" style="color:var(--primary); margin-right:6px;"></i> ເປີດກະຂາຍ (Start Shift)</h3>
            <button class="modal-close" aria-label="ປິດ" onclick="closeModal('openShiftModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:13.5px; color:#5e6c84; margin-bottom:16px;">
                ກະລຸນາກວດນັບ ແລະ ປ້ອນ <strong>ເງິນທອນຕັ້ງຕົ້ນ (Cash Float)</strong> ທີ່ມີຢູ່ໃນລິ້ນຊັກເງິນກ່ອນເລີ່ມການຂາຍ.
            </p>
            <div class="form-group">
                <label class="form-label">ເງິນທອນຕັ້ງຕົ້ນໃນລິ້ນຊັກ (ກີບ) *</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-money-bill-wave input-left-icon"></i>
                    <input type="number" class="form-control" id="inputStartingCash" value="0" style="font-size:22px; font-weight:700; height:48px;" min="0" step="1000" autofocus>
                </div>
            </div>
            <!-- Quick Float Presets -->
            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:15px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('inputStartingCash').value=0">0 ກີບ</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('inputStartingCash').value=200000">+200k</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('inputStartingCash').value=500000">+500k</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('inputStartingCash').value=1000000">+1M</button>
            </div>
            <div class="form-group">
                <label class="form-label">ໝາຍເຫດ (ບໍ່ບັງຄັບ)</label>
                <input type="text" class="form-control" id="inputOpenShiftNote" placeholder="ເຊັ່ນ: ກະເຊົ້າ, ປ່ຽນກະ...">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-white" onclick="closeModal('openShiftModal')">ຍົກເລີກ</button>
            <button class="btn btn-primary" id="btnConfirmOpenShift" onclick="confirmOpenShift()">
                <i class="fas fa-check"></i> ຢືນຢັນເປີດກະ
            </button>
        </div>
    </div>
</div>

<!-- Close Shift Modal -->
<div class="modal-overlay" id="closeShiftModal" style="z-index: 2200;">
    <div class="modal" style="max-width:540px;">
        <div class="modal-header">
            <h3><i class="fas fa-clock" style="color:#ffab00; margin-right:6px;"></i> ປິດກະຂາຍປະຈຳວັນ (End Shift)</h3>
            <button class="modal-close" aria-label="ປິດ" onclick="closeModal('closeShiftModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <!-- Shift Live Summary Banner -->
            <div style="background:#f4f6f8; border-radius:10px; padding:14px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:13px; color:#5e6c84;">ລະຫັດກະ: <strong id="closeShiftNumber" style="color:var(--text-dark);">-</strong></span>
                    <span style="font-size:12px; color:#5e6c84;">ເປີດເມື່ອ: <strong id="closeShiftOpenedAt">-</strong></span>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8px; text-align:center;">
                    <div style="background:#fff; padding:8px 4px; border-radius:6px; border:1px solid #e2e8f0;">
                        <div style="font-size:11px; color:#5e6c84;">ຈຳນວນບິນ</div>
                        <div id="closeShiftBills" style="font-size:16px; font-weight:700; color:var(--text-dark);">0</div>
                    </div>
                    <div style="background:#fff; padding:8px 4px; border-radius:6px; border:1px solid #e2e8f0;">
                        <div style="font-size:11px; color:#5e6c84;">ຍອດຂາຍລວມ</div>
                        <div id="closeShiftSalesTotal" style="font-size:15px; font-weight:700; color:var(--primary);">0 ₭</div>
                    </div>
                    <div style="background:#fff; padding:8px 4px; border-radius:6px; border:1px solid #e2e8f0;">
                        <div style="font-size:11px; color:#5e6c84;">ຍອດໂອນ (QR)</div>
                        <div id="closeShiftTransferTotal" style="font-size:15px; font-weight:700; color:#0052cc;">0 ₭</div>
                    </div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; padding-top:8px; border-top:1px dashed #cbd5e1; font-size:13.5px;">
                    <span>ເງິນຕັ້ງຕົ້ນ + ຍອດຂາຍສົດ = <strong>ເງິນສົດທີ່ຄວນມີ:</strong></span>
                    <strong id="closeShiftExpectedCash" style="font-size:16px; color:var(--primary);">0 ₭</strong>
                </div>
            </div>

            <!-- Cash Count Section -->
            <h4 style="font-size:14px; margin-bottom:10px;"><i class="fas fa-coins" style="color:var(--primary); margin-right:4px;"></i> ນັບເງິນສົດຕົວຈິງໃນລິ້ນຊັກ (Cash Count)</h4>
            
            <div class="form-row-3" style="gap:10px;">
                <div class="form-group" style="margin-bottom:8px;">
                    <label class="form-label" style="font-size:12px;">ເງິນກີບ (LAK) *</label>
                    <input type="number" class="form-control" id="inputCountedLak" style="font-size:18px; font-weight:700; height:44px;" placeholder="0" oninput="calculateShiftDifference()">
                </div>
                <div class="form-group" style="margin-bottom:8px;">
                    <label class="form-label" style="font-size:12px;">ເງິນບາດ (THB)</label>
                    <input type="number" class="form-control" id="inputCountedThb" style="font-size:18px; font-weight:700; height:44px;" placeholder="0" oninput="calculateShiftDifference()">
                </div>
                <div class="form-group" style="margin-bottom:8px;">
                    <label class="form-label" style="font-size:12px;">ເງິນໂດລາ (USD)</label>
                    <input type="number" class="form-control" id="inputCountedUsd" style="font-size:18px; font-weight:700; height:44px;" placeholder="0" oninput="calculateShiftDifference()">
                </div>
            </div>

            <!-- Live Difference Box -->
            <div id="shiftDiffBanner" style="padding:10px 14px; border-radius:8px; margin:10px 0; display:flex; justify-content:space-between; align-items:center; background:#e3fcef; border:1px solid #abf5d1;">
                <div>
                    <div style="font-size:11px; color:#5e6c84;">ຜົນຕ່າງເງິນສົດ (ຂາດ / ເກີນ)</div>
                    <div id="shiftDiffStatusText" style="font-size:16px; font-weight:700; color:#006644;">0 ກີບ (ພໍດີ)</div>
                </div>
                <div id="shiftDiffTotalCounted" style="font-size:13px; font-weight:600; color:#42526e;">
                    ລວມນັບໄດ້: 0 ₭
                </div>
            </div>

            <div class="form-group" style="margin-top:10px; margin-bottom:0;">
                <label class="form-label" style="font-size:12px;">ໝາຍເຫດຕອນປິດກະ</label>
                <input type="text" class="form-control" id="inputCloseShiftNote" placeholder="ເຊັ່ນ: ເງິນສົດຖືກຕ້ອງຄົບຖ້ວນ...">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-white" onclick="closeModal('closeShiftModal')">ຍົກເລີກ</button>
            <button class="btn btn-primary" id="btnConfirmCloseShift" onclick="confirmCloseShift()">
                <i class="fas fa-lock"></i> ຢືນຢັນປິດກະ & ພິມໃບສະຫຼຸບ
            </button>
        </div>
    </div>
</div>

<!-- Hidden data for JS -->
<script>
    const TAX_RATE = <?= $taxRate ?>;
    const TAX_TYPE = '<?= $taxType ?>';
    const ENABLE_MULTI_CURRENCY = <?= $enableMultiCurrency ?>;
    const EXCHANGE_RATE_THB = <?= $exchangeRateThb ?>;
    const EXCHANGE_RATE_USD = <?= $exchangeRateUsd ?>;
    const ENABLE_CASH_DRAWER = <?= $enableCashDrawer ?>;
</script>

<?php 
include 'includes/medicine_label_modal.php';
include 'includes/footer.php'; 
?>
