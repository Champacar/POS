<?php
/**
 * Receipt Print View
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once __DIR__ . '/../includes/functions.php';
// Allow local print requests (Electron desktop app) without forcing session login redirect
if (!isLoggedIn()) {
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocal = in_array($remoteIp, ['127.0.0.1', '::1', 'localhost']) || !empty($_GET['preview']) || !empty($_GET['sample']);
    if (!$isLocal) {
        requireLogin();
    }
}
setSecurityHeaders();

$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isSample = isset($_GET['sample']) && $_GET['sample'] == 1;

if ($isSample) {
    // Generate sample receipt data, merging live uploaded assets (logo, qr_code, footer_image)
    $dbSettings = getShopSettings() ?: [];
    $settings = array_merge($dbSettings, [
        'shop_name' => $_GET['shop_name'] ?? ($dbSettings['shop_name'] ?? 'ຮ້ານຂາຍຢາດີ'),
        'shop_name_en' => $_GET['shop_name_en'] ?? ($dbSettings['shop_name_en'] ?? 'Good Pharmacy'),
        'address' => $_GET['address'] ?? ($dbSettings['address'] ?? ''),
        'phone' => $_GET['phone'] ?? ($dbSettings['phone'] ?? '1234-5678'),
        'open_time' => $_GET['open_time'] ?? ($dbSettings['open_time'] ?? '08:00'),
        'close_time' => $_GET['close_time'] ?? ($dbSettings['close_time'] ?? '20:00'),
        'show_in_receipt' => isset($_GET['show_in_receipt']) ? (int)$_GET['show_in_receipt'] : ($dbSettings['show_in_receipt'] ?? 1),
        'tax_rate' => isset($_GET['tax_rate']) ? (float)$_GET['tax_rate'] : ($dbSettings['tax_rate'] ?? 10),
        'tax_type' => $_GET['tax_type'] ?? ($dbSettings['tax_type'] ?? 'excluded'),
        'receipt_size' => $_GET['size'] ?? ($dbSettings['receipt_size'] ?? '80mm'),
    ]);

    $subtotal = 123000;
    $taxRate = $settings['tax_rate'];
    $taxType = $settings['tax_type'];
    
    $taxAmount = 0;
    $totalAmount = $subtotal;
    
    if ($taxType === 'excluded') {
        $taxAmount = round($subtotal * ($taxRate / 100));
        $totalAmount = $subtotal + $taxAmount;
    } else if ($taxType === 'included') {
        $taxAmount = round($subtotal - ($subtotal / (1 + ($taxRate / 100))));
        $totalAmount = $subtotal;
    }

    $sale = [
        'invoice_number' => 'INV-897535',
        'created_at' => date('Y-m-d H:i:s'),
        'payment_method' => 'cash',
        'subtotal' => $subtotal,
        'discount_amount' => 0,
        'tax_amount' => $taxAmount,
        'total_amount' => $totalAmount,
    ];

    $items = [
        [
            'product_name' => 'Clarithromycin',
            'quantity' => 1,
            'unit_price' => 123000,
            'total_price' => 123000,
        ]
    ];
} else {
    if ($saleId <= 0) {
        echo 'ບໍ່ພົບຂໍ້ມູນ';
        exit;
    }

    try {
        $db = getDB();
        $settings = getShopSettings();
        
        $stmt = $db->prepare("SELECT * FROM sales WHERE id = ?");
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch();
        
        if (!$sale) {
            echo 'ບໍ່ພົບຂໍ້ມູນ';
            exit;
        }
        
        $stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
        $stmt->execute([$saleId]);
        $items = $stmt->fetchAll();
    } catch (Exception $e) {
        echo 'ເກີດຂໍ້ຜິດພາດ';
        exit;
    }
}

$receiptSize = $settings['receipt_size'] ?? '80mm';
$is56mm = ($receiptSize === '56mm' || $receiptSize === '58mm');
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <title>ບິນ #<?= $sale['invoice_number'] ?></title>
    <style>
        @import url('<?= BASE_URL ?>assets/vendor/fonts/noto-sans-lao.css');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Noto Sans Lao', monospace;
            width: <?= $is56mm ? '48mm' : '72mm' ?>;
            max-width: <?= $is56mm ? '48mm' : '72mm' ?>;
            margin: 0 auto;
            padding: <?= $is56mm ? '6px 2px' : '8px 4px' ?>;
            font-size: <?= $is56mm ? '9.5px' : '11px' ?>;
            line-height: 1.35;
            color: #000;
            font-weight: 600; /* Bolder print */
            word-break: break-word;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .line {
            border-top: 1.5px dashed #000;
            margin: 6px 0;
            width: 100%;
        }
        .shop-name {
            font-size: <?= $is56mm ? '13px' : '15px' ?>;
            font-weight: 700;
            margin-bottom: 2px;
            line-height: 1.3;
        }
        .row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 2px 0;
            gap: 4px;
        }
        .row > span:first-child {
            flex-shrink: 0;
        }
        .row > span:last-child {
            text-align: right;
            white-space: nowrap;
            padding-right: 2px;
        }
        .items { margin: 6px 0; }
        .item-name {
            font-weight: 600;
            margin-bottom: 2px;
            word-break: break-word;
            line-height: 1.3;
        }
        .item-detail {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding-left: 8px;
            gap: 4px;
            color: #000; /* Darker print */
        }
        .item-detail > span:last-child {
            text-align: right;
            white-space: nowrap;
            padding-right: 2px;
        }
        .total-row {
            font-size: <?= $is56mm ? '12px' : '13px' ?>;
            font-weight: 700;
        }
        .invoice-qr-block {
            margin: 8px 0;
            text-align: center;
        }
        .invoice-qr-block svg {
            display: inline-block;
            margin: 0 auto;
        }
        @media print {
            @page {
                size: auto;
                margin: 0mm;
            }
            html, body {
                width: 100% !important;
                max-width: <?= $is56mm ? '48mm' : '72mm' ?> !important;
                margin: 0 auto !important;
                padding: <?= $is56mm ? '1.5mm 1mm' : '2mm 2mm' ?> !important;
                box-sizing: border-box !important;
                font-size: <?= $is56mm ? '9.5px' : '11px' ?> !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
            .no-print { display: none !important; }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center;margin:6px auto 10px;max-width:280px;">
        <button onclick="window.print()" style="width:100%;padding:8px 16px;background:#1e8e3e;color:#fff;border:none;border-radius:6px;font-family:'Noto Sans Lao',sans-serif;font-size:13px;font-weight:700;cursor:pointer;">🖨️ ສັ່ງພິມໃບບິນ (Print Receipt)</button>
    </div>
    <?php if (empty($settings['show_in_receipt']) || $settings['show_in_receipt'] == 1): ?>
    <div class="center">
        <?php if (!empty($settings['logo'])): ?>
        <div style="margin-bottom: 8px;">
            <img src="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($settings['logo']) ?>" style="max-height: 50px; max-width: 100px; object-fit: contain;" alt="Logo">
        </div>
        <?php endif; ?>
        <div class="shop-name"><?= sanitize($settings['shop_name'] ?? 'ຮ້ານຂາຍຢາດີ') ?></div>
        <div><?= sanitize($settings['shop_name_en'] ?? '') ?></div>
        <div style="font-size:10px;color:#000; font-weight:600;margin-top:4px;"><?= sanitize($settings['address'] ?? '') ?></div>
        <div style="font-size:10px;color:#000; font-weight:600;">ໂທ: <?= sanitize($settings['phone'] ?? '') ?></div>
        <?php if (!empty($settings['show_in_receipt']) && !empty($settings['open_time']) && !empty($settings['close_time'])): ?>
        <div style="font-size:10px;color:#000; font-weight:600;">ເວລາເປີດຮ້ານ <?= sanitize($settings['open_time']) ?> - <?= sanitize($settings['close_time']) ?></div>
        <?php endif; ?>
    </div>
    <div class="line"></div>
    <?php endif; ?>
    
    <div class="row">
        <span>ເລກບິນ:</span>
        <span class="bold"><?= $sale['invoice_number'] ?></span>
    </div>
    <div class="row">
        <span>ວັນທີ:</span>
        <span><?= formatDateTime($sale['created_at']) ?></span>
    </div>
    <div class="row">
        <span>ການຊຳລະ:</span>
        <span><?= getPaymentMethodLabel($sale['payment_method']) ?></span>
    </div>
    
    <div class="line"></div>
    
    <div class="items">
        <?php foreach ($items as $item): ?>
        <div style="margin-bottom:6px;">
            <div class="item-name"><?= sanitize($item['product_name']) ?></div>
            <div class="item-detail">
                <span><?= $item['quantity'] ?> x <?= formatCurrency($item['unit_price']) ?></span>
                <span><?= formatCurrency($item['total_price']) ?> ກີບ</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="line"></div>
    
    <div class="row">
        <span>ລວມ:</span>
        <span><?= formatCurrency($sale['subtotal']) ?> ກີບ</span>
    </div>
    <?php if ($sale['discount_amount'] > 0): ?>
    <div class="row">
        <span>ສ່ວນຫຼຸດ:</span>
        <span>-<?= formatCurrency($sale['discount_amount']) ?> ກີບ</span>
    </div>
    <?php endif; ?>
    <?php if (($settings['tax_type'] ?? 'excluded') !== 'none'): ?>
    <div class="row">
        <span><?= ($settings['tax_type'] ?? 'excluded') === 'included' ? 'ອາກອນລວມໃນ' : 'ອາກອນນອກ' ?>:</span>
        <span><?= formatCurrency($sale['tax_amount']) ?> ກີບ</span>
    </div>
    <?php endif; ?>
    
    <div class="line"></div>
    
    <div class="row total-row">
        <span>ຍອດລວມ:</span>
        <span><?= formatCurrency($sale['total_amount']) ?> ກີບ</span>
    </div>

    <?php if ($sale['payment_method'] === 'cash'): ?>
        <?php if (!empty($sale['currency']) && $sale['currency'] !== 'LAK'): ?>
            <?php 
                $currSign = $sale['currency'] === 'THB' ? '฿' : '$';
                $currName = $sale['currency'] === 'THB' ? 'ບາດ (THB)' : 'ໂດລາ (USD)';
                $paidForeign = (float)($sale['paid_amount_foreign'] ?? 0);
                $changeForeign = (float)($sale['change_amount_foreign'] ?? 0);
                $rate = (float)($sale['exchange_rate'] ?? 1);
            ?>
            <div class="row" style="font-size: 11px;">
                <span>ສະກຸນເງິນຊຳລະ:</span>
                <span><?= $currName ?></span>
            </div>
            <div class="row" style="font-size: 11px;">
                <span>ອັດຕາແລກປ່ຽນ:</span>
                <span>1 <?= $currSign ?> = <?= formatCurrency($rate) ?> ₭</span>
            </div>
            <div class="row" style="font-size: 11px;">
                <span>ຮັບເງິນມາ:</span>
                <span><?= number_format($paidForeign, 2) ?> <?= $currSign ?> (<?= formatCurrency($sale['cash_amount']) ?> ₭)</span>
            </div>
            <div class="row" style="font-size: 11px; font-weight: bold;">
                <span>ເງິນທອນ:</span>
                <span><?= formatCurrency($sale['change_amount'] ?? max(0, $sale['cash_amount'] - $sale['total_amount'])) ?> ກີບ</span>
            </div>
            <?php if ($changeForeign > 0): ?>
            <div class="row" style="font-size: 10px; color: #555;">
                <span>(ທຽບເທົ່າເງິນທອນ:</span>
                <span><?= number_format($changeForeign, 2) ?> <?= $currSign ?>)</span>
            </div>
            <?php endif; ?>
        <?php elseif (isset($sale['cash_amount']) && $sale['cash_amount'] > 0): ?>
            <div class="row" style="font-size: 11px;">
                <span>ຮັບເງິນສົດ:</span>
                <span><?= formatCurrency($sale['cash_amount']) ?> ກີບ</span>
            </div>
            <div class="row" style="font-size: 11px;">
                <span>ເງິນທອນ:</span>
                <span><?= formatCurrency($sale['change_amount'] ?? max(0, $sale['cash_amount'] - $sale['total_amount'])) ?> ກີບ</span>
            </div>
        <?php endif; ?>
    <?php elseif ($sale['payment_method'] === 'split'): ?>
        <div class="row" style="font-size: 11px;">
            <span>ຈ່າຍສົດ:</span>
            <span><?= formatCurrency($sale['cash_amount'] ?? 0) ?> ກີບ</span>
        </div>
        <div class="row" style="font-size: 11px;">
            <span>ຈ່າຍໂອນ:</span>
            <span><?= formatCurrency($sale['transfer_amount'] ?? 0) ?> ກີບ</span>
        </div>
    <?php endif; ?>
    
    <div class="line"></div>
    
    <div class="center" style="margin: 10px 0;">
        <?php if (!empty($settings['qr_code'])): ?><div style="font-size: 9px; font-weight: 700; margin-bottom: 2px;">QR Code ຮັບເງິນ</div><img src="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($settings['qr_code']) ?>" style="width: 75px; height: 75px; object-fit: contain;" alt="Payment QR Code"><div class="line" style="margin: 6px 0;"></div><?php endif; ?><div class="invoice-qr-block"><div style="display: inline-block; background: #fff; line-height: 0;"><?= generateQrCodeSvg($sale['invoice_number'], $is56mm ? 70 : 85) ?></div><div style="font-size: <?= $is56mm ? '9.5px' : '10.5px' ?>; color: #000; font-weight: 700; margin-top: 3px; letter-spacing: 0.5px;"><?= htmlspecialchars($sale['invoice_number']) ?></div><div style="font-size: 8px; color: #333; margin-top: 1px;">(ສະແກນກວດສອບເລກທີບິນ)</div></div>

    </div>
    
    <div class="line"></div>
    
    <div class="center" style="margin-top:10px;font-size:11px;">
        <p>ຂອບໃຈທີ່ໃຊ້ບໍລິການ</p>
        <p style="color:#000; font-weight:600;font-size:10px;">Thank you for your purchase</p>
        <?php if (!empty($settings['footer_image'])): ?><div style="margin-top: 6px;"><img src="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($settings['footer_image']) ?>" style="max-width: 120px; max-height: 60px; object-fit: contain;" alt="Footer"></div><?php endif; ?>
    </div>
    
    <?php if (!empty($_GET['autoprint'])): ?>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
    <?php endif; ?>
    <script>
        window.addEventListener('load', function() {
            try {
                var h = document.documentElement.scrollHeight || document.body.scrollHeight;
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'RECEIPT_PREVIEW_READY', height: h }, '*');
                }
            } catch(e) {}
        });
    </script>
</body>
</html>
