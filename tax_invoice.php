<?php
/**
 * POSPharma - Official Tax Invoice / VAT Invoice (ໃບກຳກັບພາສີ / ໃບຮັບເງິນອາກອນມູນຄ່າເພີ່ມ)
 * ມາດຕະຖານສ່ວຍສາອາກອນ ສປປ ລາວ 100% Offline
 */

require_once __DIR__ . '/includes/functions.php';

// Allow local preview without forcing login redirect if local or preview
if (!isLoggedIn()) {
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocal = in_array($remoteIp, ['127.0.0.1', '::1', 'localhost']) || !empty($_GET['preview']) || !empty($_GET['sample']);
    if (!$isLocal) {
        requireLogin();
    }
}
setSecurityHeaders();

$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$paperSize = isset($_GET['size']) && $_GET['size'] === 'a5' ? 'a5' : 'a4';

$db = getDB();
$settings = getShopSettings();

if ($saleId > 0) {
    $stmt = $db->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();

    if (!$sale) {
        die('ບໍ່ພົບຂໍ້ມູນການຂາຍ');
    }

    $stmtItems = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $stmtItems->execute([$saleId]);
    $items = $stmtItems->fetchAll();
} else {
    // Sample / Demo Tax Invoice
    $sale = [
        'id' => 0,
        'invoice_number' => 'INV202611090001',
        'tax_invoice_number' => 'TAX-20261109-0001',
        'created_at' => date('Y-m-d H:i:s'),
        'customer_name' => 'ທ່ານ ສົມພອນ ວິໄລສັກ',
        'customer_company' => 'ບໍລິສັດ ພັດທະນາການຢາ ຈຳກັດ',
        'customer_tax_id' => '01-10293847',
        'customer_address' => 'ຖະໜົນສຸພານຸວົງ, ບ້ານສີຫອມ, ເມືອງຈັນທະບູລີ, ນະຄອນຫຼວງວຽງຈັນ',
        'subtotal' => 200000,
        'discount_amount' => 10000,
        'tax_amount' => 13300,
        'total_amount' => 203300,
        'payment_method' => 'transfer',
        'note' => 'ຊຳລະຄົບຖ້ວນຜ່ານລະບົບ BCEL One',
        'cashier' => 'ເພສັດຊະກອນ ສົມສັກ'
    ];
    $items = [
        ['product_name' => 'Amoxicillin 500mg', 'unit' => 'ກັບ', 'quantity' => 2, 'unit_price' => 45000, 'total_price' => 90000],
        ['product_name' => 'Paracetamol 500mg (ແຜງ 10 ເມັດ)', 'unit' => 'ແຜງ', 'quantity' => 5, 'unit_price' => 10000, 'total_price' => 50000],
        ['product_name' => 'Vitamin C 1000mg Effervescent', 'unit' => 'ຫຼອດ', 'quantity' => 1, 'unit_price' => 60000, 'total_price' => 60000],
    ];
}

// Compute VAT Rate percentage
$netBeforeTax = ($sale['subtotal'] ?? 0) - ($sale['discount_amount'] ?? 0);
$taxAmount = (float)($sale['tax_amount'] ?? 0);
$totalAmount = (float)($sale['total_amount'] ?? 0);
$vatRate = 0;
if ($netBeforeTax > 0 && $taxAmount > 0) {
    $vatRate = round(($taxAmount / $netBeforeTax) * 100);
} else {
    $vatRate = (float)($settings['tax_rate'] ?? 7);
}

$companyName = !empty($settings['company_name']) ? $settings['company_name'] : ($settings['shop_name'] ?? 'ຮ້ານຂາຍຢາດີ');
$shopTaxId = !empty($settings['tax_id']) ? $settings['tax_id'] : '-';
$taxInvNo = !empty($sale['tax_invoice_number']) ? $sale['tax_invoice_number'] : ('TAX-' . date('Ymd', strtotime($sale['created_at'])) . '-' . str_pad($sale['id'], 4, '0', STR_PAD_LEFT));
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ໃບກຳກັບພາສີ / ໃບຮັບເງິນອາກອນມູນຄ່າເພີ່ມ (Official Tax Invoice) - <?= htmlspecialchars($companyName) ?> ເລກທີ <?= htmlspecialchars($taxInvNo) ?>">
    <title>ໃບກຳກັບພາສີ - <?= htmlspecialchars($taxInvNo) ?> | <?= htmlspecialchars($companyName) ?></title>
    
    <?php if (!empty($settings['favicon']) && file_exists(__DIR__ . '/assets/images/' . $settings['favicon'])): ?>
        <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($settings['favicon']) ?>">
    <?php elseif (!empty($settings['logo']) && file_exists(__DIR__ . '/assets/images/' . $settings['logo'])): ?>
        <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($settings['logo']) ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/favicon.png">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fonts/noto-sans-lao.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        @page {
            size: <?= $paperSize === 'a5' ? 'A5 landscape' : 'A4 portrait' ?>;
            margin: 12mm 15mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            font-family: 'Noto Sans Lao', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #e2e8f0;
            color: #1e293b;
            margin: 0;
            padding: 20px 0;
        }
        .screen-toolbar {
            max-width: <?= $paperSize === 'a5' ? '820px' : '820px' ?>;
            margin: 0 auto 16px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .invoice-paper {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            padding: 30px 35px;
            border-radius: 6px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #cbd5e1;
        }
        .inv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 16px;
            margin-bottom: 16px;
        }
        .inv-seller {
            max-width: 55%;
        }
        .inv-seller h2 {
            margin: 0 0 4px 0;
            font-size: 1.25rem;
            color: #0369a1;
            font-weight: 700;
        }
        .inv-seller .en-name {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 6px;
        }
        .inv-seller .seller-info {
            font-size: 0.82rem;
            line-height: 1.45;
            color: #334155;
        }
        .inv-title-box {
            text-align: right;
        }
        .inv-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 2px 0;
        }
        .inv-subtitle {
            font-size: 0.85rem;
            font-weight: 600;
            color: #0284c7;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .inv-meta-table {
            margin-left: auto;
            font-size: 0.82rem;
            border-collapse: collapse;
        }
        .inv-meta-table td {
            padding: 2px 6px;
        }
        .inv-meta-table td.lbl {
            font-weight: 600;
            color: #475569;
            text-align: right;
        }
        .inv-meta-table td.val {
            font-weight: 700;
            color: #0f172a;
            text-align: left;
        }
        .inv-party-grid {
            display: grid;
            grid-template-columns: 1fr;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 18px;
            font-size: 0.84rem;
            line-height: 1.5;
        }
        .party-title {
            font-weight: 700;
            color: #0284c7;
            margin-bottom: 4px;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .inv-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            margin-bottom: 16px;
        }
        .inv-table th {
            background: #0284c7;
            color: #ffffff;
            font-weight: 600;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #0284c7;
        }
        .inv-table th.text-center { text-align: center; }
        .inv-table th.text-right { text-align: right; }
        .inv-table td {
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        .inv-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .inv-summary-grid {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 24px;
        }
        .inv-words-box {
            flex: 1;
            background: #f1f5f9;
            border-left: 4px solid #0284c7;
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 0.82rem;
        }
        .inv-totals-table {
            width: 320px;
            border-collapse: collapse;
            font-size: 0.84rem;
        }
        .inv-totals-table td {
            padding: 4px 8px;
        }
        .inv-totals-table td.lbl {
            text-align: right;
            color: #475569;
        }
        .inv-totals-table td.val {
            text-align: right;
            font-weight: 700;
            color: #0f172a;
        }
        .inv-totals-table tr.grand-total td {
            border-top: 2px solid #0284c7;
            border-bottom: 2px double #0284c7;
            font-size: 0.95rem;
            color: #0284c7;
            font-weight: 800;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .signatures-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 36px;
            text-align: center;
            font-size: 0.82rem;
        }
        .sig-box {
            padding-top: 50px;
            border-top: 1px dashed #94a3b8;
        }
        .sig-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .sig-sub {
            font-size: 0.75rem;
            color: #64748b;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .screen-toolbar {
                display: none !important;
            }
            .invoice-paper {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

    <!-- Screen Toolbar -->
    <div class="screen-toolbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> ພິມໃບກຳກັບພາສີ (Print)
            </button>
            <div class="btn-group">
                <a href="?id=<?= $saleId ?>&size=a4" class="btn btn-sm <?= $paperSize === 'a4' ? 'btn-primary' : 'btn-white' ?>">A4 ມາດຕະຖານ</a>
                <a href="?id=<?= $saleId ?>&size=a5" class="btn btn-sm <?= $paperSize === 'a5' ? 'btn-primary' : 'btn-white' ?>">A5 ແນວນອນ</a>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <span class="badge badge-success"><i class="fas fa-check-circle"></i> ຖືກຕ້ອງຕາມມາດຕະຖານສ່ວຍສາອາກອນ</span>
            <button class="btn btn-white btn-sm" onclick="window.close() || window.history.back()">
                <i class="fas fa-times"></i> ປິດ
            </button>
        </div>
    </div>

    <!-- Printable Invoice Page -->
    <div class="invoice-paper">
        <!-- Header -->
        <div class="inv-header">
            <div class="inv-seller">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="assets/images/<?= htmlspecialchars($settings['logo']) ?>" style="height:48px; object-fit:contain; margin-bottom:8px;" alt="Logo">
                <?php endif; ?>
                <h2><?= htmlspecialchars($companyName) ?></h2>
                <div class="en-name"><?= htmlspecialchars($settings['shop_name_en'] ?? 'PHARMACY POS') ?></div>
                <div class="seller-info">
                    <strong>ເລກປະຈຳຕົວຜູ້ເສຍອາກອນ (TIN):</strong> <span style="font-family:monospace; font-weight:700; color:#0369a1; font-size:0.95rem;"><?= htmlspecialchars($shopTaxId) ?></span><br>
                    <strong>ທີ່ຢູ່:</strong> <?= htmlspecialchars($settings['address'] ?: 'ນະຄອນຫຼວງວຽງຈັນ, ສປປ ລາວ') ?><br>
                    <strong>ເບີໂທ:</strong> <?= htmlspecialchars($settings['phone'] ?: '-') ?> | <strong>ອີເມວ:</strong> <?= htmlspecialchars($settings['email'] ?: '-') ?>
                </div>
            </div>

            <div class="inv-title-box">
                <h1 class="inv-title">ໃບກຳກັບພາສີ</h1>
                <div class="inv-subtitle">TAX INVOICE / ໃບຮັບເງິນ</div>
                <span class="badge badge-danger" style="font-size:0.75rem; letter-spacing:0.5px; padding:2px 8px; margin-bottom:6px; display:inline-block;">ຕົ້ນສະບັບ / ORIGINAL</span>
                
                <table class="inv-meta-table">
                    <tr>
                        <td class="lbl">ເລກທີໃບກຳກັບ:</td>
                        <td class="val" style="color:#0284c7; font-family:monospace; font-size:0.92rem;"><?= htmlspecialchars($taxInvNo) ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">ວັນທີອອກ:</td>
                        <td class="val"><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">ເລກທີໃບບິນອ້າງອີງ:</td>
                        <td class="val" style="font-family:monospace;"><?= htmlspecialchars($sale['invoice_number']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Buyer Info Box -->
        <div class="inv-party-grid">
            <div class="party-title"><i class="fas fa-building"></i> ຂໍ້ມູນຜູ້ຊື້ / CUSTOMER (BUYER)</div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                <div>
                    <strong>ຊື່ລູກຄ້າ / ບໍລິສັດ:</strong> <?= htmlspecialchars($sale['customer_company'] ?: ($sale['customer_name'] ?: 'ລູກຄ້າທົ່ວໄປ (General Customer)')) ?><br>
                    <strong>ເລກປະຈຳຕົວຜູ້ເສຍອາກອນ (TIN):</strong> <span style="font-family:monospace; font-weight:700;"><?= htmlspecialchars($sale['customer_tax_id'] ?: '-') ?></span>
                </div>
                <div>
                    <strong>ທີ່ຢູ່:</strong> <?= htmlspecialchars($sale['customer_address'] ?: '-') ?><br>
                    <strong>ວິທີຊຳລະເງິນ:</strong> <?= getPaymentMethodLabel($sale['payment_method']) ?>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="inv-table">
            <thead>
                <tr>
                    <th class="text-center" style="width:40px;">#</th>
                    <th>ລາຍການສິນຄ້າ & ຢາ (Description)</th>
                    <th class="text-center" style="width:70px;">ຫົວໜ່ວຍ</th>
                    <th class="text-center" style="width:70px;">ຈຳນວນ</th>
                    <th class="text-right" style="width:110px;">ລາຄາ/ໜ່ວຍ (₭)</th>
                    <th class="text-right" style="width:130px;">ລາຄາລວມ (₭)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $it): ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($it['product_name']) ?></strong>
                    </td>
                    <td class="text-center"><?= htmlspecialchars($it['unit'] ?? 'ອັນ') ?></td>
                    <td class="text-center"><strong><?= (int)$it['quantity'] ?></strong></td>
                    <td class="text-right"><?= formatCurrency($it['unit_price']) ?></td>
                    <td class="text-right"><strong><?= formatCurrency($it['total_price']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Summary & Totals -->
        <div class="inv-summary-grid">
            <div class="inv-words-box">
                <div style="font-weight:700; color:#334155; margin-bottom:4px;">ຈຳນວນເງິນລວມເປັນຕົວໜັງສື (Amount in words):</div>
                <div style="font-weight:700; color:#0284c7; font-size:0.92rem;"><?= numberToLaoWords($totalAmount) ?></div>
                <?php if (!empty($sale['note'])): ?>
                <div style="margin-top:6px; font-size:0.78rem; color:#64748b;">
                    <strong>ໝາຍເຫດ:</strong> <?= htmlspecialchars($sale['note']) ?>
                </div>
                <?php endif; ?>
            </div>

            <table class="inv-totals-table">
                <tr>
                    <td class="lbl">ມູນຄ່າສິນຄ້າລວມ (Subtotal):</td>
                    <td class="val"><?= formatCurrency($sale['subtotal']) ?> ₭</td>
                </tr>
                <?php if (($sale['discount_amount'] ?? 0) > 0): ?>
                <tr>
                    <td class="lbl">ສ່ວນຫຼຸດ (Discount):</td>
                    <td class="val" style="color:#ef4444;">-<?= formatCurrency($sale['discount_amount']) ?> ₭</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="lbl">ມູນຄ່າກ່ອນອາກອນ (Net Amount):</td>
                    <td class="val"><?= formatCurrency($netBeforeTax) ?> ₭</td>
                </tr>
                <tr>
                    <td class="lbl">ອາກອນມູນຄ່າເພີ່ມ (VAT <?= $vatRate ?>%):</td>
                    <td class="val"><?= formatCurrency($taxAmount) ?> ₭</td>
                </tr>
                <tr class="grand-total">
                    <td class="lbl">ຍອດລວມທັງໝົດ (Grand Total):</td>
                    <td class="val"><?= formatCurrency($totalAmount) ?> ₭</td>
                </tr>
            </table>
        </div>

        <!-- Signatures Block -->
        <div class="signatures-grid">
            <div class="sig-box">
                <div class="sig-title">ຜູ້ຊື້ສິນຄ້າ / Buyer</div>
                <div class="sig-sub">(ລົງລາຍເຊັນ & ວັນທີ)</div>
            </div>
            <div class="sig-box">
                <div class="sig-title">ຜູ້ຮັບເງິນ / Cashier</div>
                <div class="sig-sub"><?= htmlspecialchars($sale['cashier'] ?? 'ພະນັກງານ') ?></div>
            </div>
            <div class="sig-box">
                <div class="sig-title">ຜູ້ມີອຳນາດລົງລາຍເຊັນ / Authorized</div>
                <div class="sig-sub">(ລົງລາຍເຊັນ & ປະທັບຕາ)</div>
            </div>
        </div>
    </div>

</body>
</html>
