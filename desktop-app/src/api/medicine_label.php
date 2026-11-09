<?php
/**
 * Medicine Label Print View
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once __DIR__ . '/../includes/functions.php';
// Allow local print requests (Electron desktop app) without forcing session login redirect
if (!isLoggedIn()) {
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocal = in_array($remoteIp, ['127.0.0.1', '::1', 'localhost']) || !empty($_GET['sample']);
    if (!$isLocal) {
        requireLogin();
    }
}
setSecurityHeaders();

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$productName = isset($_GET['product_name']) ? trim($_GET['product_name']) : '';
$qty = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
$patientName = isset($_GET['patient_name']) ? trim($_GET['patient_name']) : '';
$usage = isset($_GET['usage']) ? trim($_GET['usage']) : 'ກິນຕາມແພດສັ່ງ';
$size = isset($_GET['size']) ? trim($_GET['size']) : '8x5';
$isSample = isset($_GET['sample']) && $_GET['sample'] == 1;

$unit = 'ແຜງ';
$expiryDate = '';

if ($isSample) {
    $settings = [
        'shop_name' => $_GET['shop_name'] ?? 'ຮ້ານຂາຍຢາດີ',
        'phone' => $_GET['phone'] ?? '020 5993 5897',
    ];
    $expiryDate = date('Y-m-d', strtotime('+1 year'));
} else {
    // Load product details for unit and expiry date safely
    if ($productId > 0) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT unit, expiry_date FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if ($product) {
                $unit = !empty($product['unit']) ? $product['unit'] : 'ແຜງ';
                $expiryDate = $product['expiry_date'] ?? '';
            }
        } catch (Throwable $e) {
            // Fallback gracefully
        }
    }

    try {
        $settings = getShopSettings();
    } catch (Throwable $e) {
        $settings = [];
    }
}

$shopName = !empty($settings['shop_name']) ? $settings['shop_name'] : 'ຮ້ານຂາຍຢາດີ';
$shopPhone = !empty($settings['phone']) ? $settings['phone'] : '';

// Dimension and layout parameters based on sticker size
if ($size === '7x5') {
    $pageWidth = '70mm';
    $pageHeight = '50mm';
    $containerWidth = '66mm';
    $containerHeight = '46mm';
    $containerMargin = '2mm';
    $shopNameSize = '11.5px';
    $shopPhoneSize = '9px';
    $metaSize = '9.5px';
    $medicineNameSize = '11.5px';
    $usageFontSize = '10.5px';
    $usageMaxHeight = '14mm';
    $footerFontSize = '8.5px';
    $showWarning = true;
} else if ($size === '5x3') {
    $pageWidth = '50mm';
    $pageHeight = '30mm';
    $containerWidth = '46mm';
    $containerHeight = '27mm';
    $containerMargin = '1.5mm';
    $shopNameSize = '10px';
    $shopPhoneSize = '8px';
    $metaSize = '8.5px';
    $medicineNameSize = '10px';
    $usageFontSize = '9px';
    $usageMaxHeight = '9mm';
    $footerFontSize = '7.5px';
    $showWarning = false;
    $isRoll = false;
} else if ($size === '80mm_roll') {
    $pageWidth = '64mm';
    $pageHeight = 'auto';
    $containerWidth = '64mm';
    $containerHeight = 'auto';
    $containerMargin = '1mm';
    $shopNameSize = '11.5px';
    $shopPhoneSize = '9px';
    $metaSize = '9.5px';
    $medicineNameSize = '11.5px';
    $usageFontSize = '10.5px';
    $usageMaxHeight = 'none';
    $footerFontSize = '8.5px';
    $showWarning = true;
    $isRoll = true;
} else if ($size === '4x3') {
    $pageWidth = '40mm';
    $pageHeight = '30mm';
    $containerWidth = '38mm';
    $containerHeight = '28mm';
    $containerMargin = '1mm';
    $shopNameSize = '9.5px';
    $shopPhoneSize = '7.5px';
    $metaSize = '8px';
    $medicineNameSize = '9.5px';
    $usageFontSize = '8.5px';
    $usageMaxHeight = '8mm';
    $footerFontSize = '7.5px';
    $showWarning = false;
} else {
    // Default: 8x5 (8.0 x 5.0 cm)
    $size = '8x5';
    $pageWidth = '80mm';
    $pageHeight = '50mm';
    $containerWidth = '76mm';
    $containerHeight = '46mm';
    $containerMargin = '2mm';
    $shopNameSize = '12px';
    $shopPhoneSize = '9.5px';
    $metaSize = '10px';
    $medicineNameSize = '12.5px';
    $usageFontSize = '11px';
    $usageMaxHeight = '15mm';
    $footerFontSize = '9px';
    $showWarning = true;
}
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <title>ສະຫຼາກຢາ - <?= htmlspecialchars($productName) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fonts/noto-sans-lao.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page {
            size: <?= !empty($isRoll) ? 'auto' : ($pageWidth . ' ' . $pageHeight) ?>;
            margin: 0;
        }
        html, body {
            width: <?= $pageWidth ?>;
            height: <?= $pageHeight ?>;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
            font-family: 'Noto Sans Lao', sans-serif;
            overflow: hidden;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .label-container {
            width: <?= $containerWidth ?>;
            height: <?= $containerHeight ?>;
            max-height: <?= $containerHeight ?>;
            margin: <?= $containerMargin ?> auto;
            border: 1.5px solid #000;
            border-radius: 6px;
            padding: 4px 6px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            box-sizing: border-box;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            margin-bottom: 2px;
        }
        .shop-info {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .shop-name {
            font-size: <?= $shopNameSize ?>;
            font-weight: 700;
            line-height: 1.2;
        }
        .shop-phone {
            font-size: <?= $shopPhoneSize ?>;
            color: #333;
            margin-left: 4px;
        }
        .date-badge {
            font-size: <?= $metaSize ?>;
            font-weight: 600;
            white-space: nowrap;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: <?= $metaSize ?>;
            line-height: 1.2;
        }
        .patient-name {
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 65%;
        }
        .medicine-box {
            background: #f4f6f8;
            border: 1px solid #000;
            border-radius: 4px;
            padding: 2px 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 4px;
        }
        .medicine-name {
            font-size: <?= $medicineNameSize ?>;
            font-weight: 700;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .medicine-qty {
            font-size: <?= $metaSize ?>;
            font-weight: 700;
            white-space: nowrap;
        }
        .usage-section {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        .usage-label {
            font-size: <?= $metaSize ?>;
            font-weight: 700;
            line-height: 1.1;
        }
        .usage-box {
            border: 1px solid #000;
            border-radius: 4px;
            padding: 3px 5px;
            background: #fff;
            max-height: <?= $usageMaxHeight ?>;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: <?= $usageFontSize ?>;
            font-weight: 700;
            line-height: 1.25;
            word-break: break-word;
        }
        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px dashed #000;
            padding-top: 2px;
            margin-top: 1px;
            font-size: <?= $footerFontSize ?>;
            line-height: 1.1;
        }
        .exp-date {
            font-weight: 700;
            color: #000;
        }
        .warning-text {
            font-weight: 700;
            color: #d32f2f;
        }
        @media print {
            @page {
                size: <?= $pageWidth ?> <?= $pageHeight ?>;
                margin: 0;
            }
            html, body {
                width: <?= $pageWidth ?> !important;
                height: <?= $pageHeight ?> !important;
                max-width: <?= $pageWidth ?> !important;
                max-height: <?= $pageHeight ?> !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                page-break-after: avoid !important;
                page-break-before: avoid !important;
            }
            .label-container {
                width: <?= $containerWidth ?> !important;
                height: <?= $containerHeight ?> !important;
                max-height: <?= $containerHeight ?> !important;
                margin: <?= $containerMargin ?> auto !important;
                box-sizing: border-box !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin: 4px auto 6px; padding: 2px;">
        <button onclick="window.print()" style="padding: 5px 14px; background: #1e8e3e; color: #fff; border: none; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'Noto Sans Lao', sans-serif;">
            🖨️ ສັ່ງພິມສະຫຼາກ (Print)
        </button>
    </div>
    <div class="label-container">
        <!-- Header: Shop & Date -->
        <div class="header">
            <div class="shop-info">
                <?php if (!empty($settings['logo']) && file_exists(__DIR__ . '/../assets/images/' . $settings['logo'])): ?>
                    <img src="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($settings['logo']) ?>" alt="Logo" style="max-height:16px;max-width:24px;object-fit:contain;">
                <?php endif; ?>
                <span class="shop-name"><?= htmlspecialchars($shopName) ?></span>
                <?php if (!empty($shopPhone)): ?>
                    <span class="shop-phone">ໂທ: <?= htmlspecialchars($shopPhone) ?></span>
                <?php endif; ?>
            </div>
            <div class="date-badge"><?= date('d/m/Y') ?></div>
        </div>
        
        <!-- Patient Info -->
        <div class="meta-row">
            <div class="patient-name">ຄົນເຈັບ: <?= htmlspecialchars($patientName ?: 'ທົ່ວໄປ') ?></div>
            <div>Rx: #<?= str_pad($productId, 4, '0', STR_PAD_LEFT) ?></div>
        </div>
        
        <!-- Medicine Name & Quantity -->
        <div class="medicine-box">
            <div class="medicine-name"><?= htmlspecialchars($productName ?: 'ຢາ') ?></div>
            <div class="medicine-qty"><?= $qty ?> <?= htmlspecialchars($unit) ?></div>
        </div>
        
        <!-- Usage / Instructions -->
        <div class="usage-section">
            <div class="usage-label">ວິທີໃຊ້ / ວິທີກິນ:</div>
            <div class="usage-box">
                <?= htmlspecialchars($usage) ?>
            </div>
        </div>
        
        <!-- Footer: Expiry & Children Warning -->
        <div class="footer-row">
            <div class="exp-date">
                <?php if (!empty($expiryDate)): ?>
                    Exp: <?= date('d/m/Y', strtotime($expiryDate)) ?>
                <?php else: ?>
                    Exp: -
                <?php endif; ?>
            </div>
            <?php if ($showWarning): ?>
                <div class="warning-text">⚠ ເກັບໃຫ້ພົ້ນມືເດັກ</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($_GET['autoprint'])): ?>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
                if (window.self === window.top) {
                    window.onafterprint = function() {
                        setTimeout(function() { window.close(); }, 300);
                    };
                }
            }, 350);
        });
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
