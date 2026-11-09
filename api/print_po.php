<?php
/**
 * Print Purchase Order
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID ບໍ່ຖືກຕ້ອງ");
}

try {
    $db = getDB();
    
    // Fetch PO
    $stmt = $db->prepare("
        SELECT po.*, s.name as supplier_name, s.phone as supplier_phone, s.address as supplier_address, s.email as supplier_email
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.id = ?
    ");
    $stmt->execute([$id]);
    $po = $stmt->fetch();
    
    if (!$po) {
        die("ບໍ່ພົບໃບສັ່ງຊື້ຢາ");
    }
    
    // Fetch Items
    $stmtItems = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll();
    
    $settings = getShopSettings();
} catch (Exception $e) {
    die("ເກີດຂໍ້ຜິດພາດ: " . safeErrorMessage($e));
}
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <title>ໃບສັ່ງຊື້ຢາ - <?= htmlspecialchars($po['po_number']) ?></title>
    <link rel="icon" href="<?= BASE_URL ?>assets/images/favicon.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fonts/noto-sans-lao.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Noto Sans Lao', sans-serif;
            padding: 40px;
            background: #fff;
            color: #333;
            font-size: 14px;
            line-height: 1.6;
        }
        .invoice-box {
            max-width: 800px;
            margin: 0 auto;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .header-table td {
            vertical-align: top;
        }
        .shop-title {
            font-size: 24px;
            font-weight: 700;
            color: #00875a;
        }
        .doc-title {
            font-size: 26px;
            font-weight: 700;
            text-align: right;
            color: #333;
            text-transform: uppercase;
        }
        .meta-details {
            text-align: right;
            margin-top: 10px;
        }
        .info-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 35px;
        }
        .info-section td {
            width: 50%;
            padding: 10px;
            border: 1px solid #e0e0e0;
            vertical-align: top;
            border-radius: 6px;
        }
        .section-heading {
            font-weight: 700;
            color: #333;
            margin-bottom: 6px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 4px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 35px;
        }
        .items-table th {
            background: #f4f5f7;
            border: 1px solid #e0e0e0;
            padding: 10px;
            font-weight: 700;
            text-align: left;
        }
        .items-table td {
            border: 1px solid #e0e0e0;
            padding: 10px;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .summary-table {
            width: 40%;
            margin-left: 60%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        .summary-table td {
            padding: 8px 10px;
            font-size: 15px;
        }
        .summary-table .total-row td {
            font-weight: 700;
            font-size: 18px;
            border-top: 2px double #333;
            border-bottom: 2px double #333;
        }
        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 50px;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            padding: 20px;
        }
        .signature-line {
            width: 200px;
            border-bottom: 1px solid #333;
            margin: 0 auto 10px auto;
            height: 40px;
        }
        @media print {
            body {
                padding: 0;
            }
            @page {
                size: A4;
                margin: 1.5cm;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <!-- Document Header -->
        <table class="header-table">
            <tr>
                <td>
                    <div class="shop-title"><?= htmlspecialchars($settings['shop_name'] ?? 'ຮ້ານຂາຍຢາດີ') ?></div>
                    <div><?= htmlspecialchars($settings['address'] ?? 'ນະຄອນຫຼວງວຽງຈັນ') ?></div>
                    <div>ໂທ: <?= htmlspecialchars($settings['phone'] ?? '-') ?></div>
                    <?php if (!empty($settings['email'])): ?>
                        <div>ອີເມວ: <?= htmlspecialchars($settings['email']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="doc-title">ໃບສັ່ງຊື້ຢາ</div>
                    <div class="meta-details">
                        <div><strong>ເລກທີໃບສັ່ງຊື້:</strong> <?= htmlspecialchars($po['po_number']) ?></div>
                        <div><strong>ວັນທີ:</strong> <?= date('d/m/Y', strtotime($po['created_at'])) ?></div>
                        <div><strong>ສະຖານະ:</strong> ສະບັບຮ່າງ (Draft)</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Supplier & Shipping Info -->
        <table class="info-section">
            <tr>
                <td style="padding-right: 15px;">
                    <div class="section-heading">ສັ່ງຊື້ເຖິງ (Supplier Details)</div>
                    <div><strong>ບໍລິສັດ / ຜູ້ສະໜອງ:</strong> <?= htmlspecialchars($po['supplier_name'] ?? 'ບໍ່ລະບຸ') ?></div>
                    <?php if (!empty($po['supplier_phone'])): ?>
                        <div><strong>ເບີໂທ:</strong> <?= htmlspecialchars($po['supplier_phone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($po['supplier_email'])): ?>
                        <div><strong>ອີເມວ:</strong> <?= htmlspecialchars($po['supplier_email']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($po['supplier_address'])): ?>
                        <div><strong>ທີ່ຢູ່:</strong> <?= htmlspecialchars($po['supplier_address']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding-left: 15px;">
                    <div class="section-heading">ສະຖານທີ່ຈັດສົ່ງ (Deliver To)</div>
                    <div><strong>ຊື່ຮ້ານ:</strong> <?= htmlspecialchars($settings['shop_name'] ?? 'ຮ້ານຂາຍຢາດີ') ?></div>
                    <div><strong>ທີ່ຢູ່ຈັດສົ່ງ:</strong> <?= htmlspecialchars($settings['address'] ?? 'ນະຄອນຫຼວງວຽງຈັນ') ?></div>
                    <div><strong>ເບີໂທຕິດຕໍ່:</strong> <?= htmlspecialchars($settings['phone'] ?? '-') ?></div>
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 50px;">#</th>
                    <th>ລາຍການຢາ / ສິນຄ້າ</th>
                    <th class="text-center" style="width: 100px;">ຈຳນວນສັ່ງຊື້</th>
                    <th class="text-right" style="width: 150px;">ລາຄາຕົ້ນທຶນ</th>
                    <th class="text-right" style="width: 180px;">ຍອດລວມ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item): ?>
                <?php $subtotal = $item['quantity'] * $item['unit_price']; ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td><strong><?= htmlspecialchars($item['product_name']) ?></strong></td>
                    <td class="text-center"><?= number_format($item['quantity']) ?></td>
                    <td class="text-right"><?= formatCurrency($item['unit_price']) ?> ກີບ</td>
                    <td class="text-right"><?= formatCurrency($subtotal) ?> ກີບ</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Summary -->
        <table class="summary-table">
            <tr class="total-row">
                <td>ຍອດລວມທັງໝົດ:</td>
                <td class="text-right"><?= formatCurrency($po['total_amount']) ?> ກີບ</td>
            </tr>
        </table>

        <?php if (!empty($po['note'])): ?>
        <div style="margin-bottom: 40px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; background: #fafafa;">
            <strong>ໝາຍເຫດ:</strong> <?= htmlspecialchars($po['note']) ?>
        </div>
        <?php endif; ?>

        <!-- Signatures Area -->
        <table class="signatures">
            <tr>
                <td>
                    <p>ຜູ້ສ້າງໃບສັ່ງຊື້ (Authorized Buyer)</p>
                    <div class="signature-line"></div>
                    <p>ວັນທີ: ____/____/________</p>
                </td>
                <td>
                    <p>ຜູ້ສະໜອງຮັບຮອງ (Supplier Confirmed)</p>
                    <div class="signature-line"></div>
                    <p>ວັນທີ: ____/____/________</p>
                </td>
            </tr>
        </table>
    </div>

    <script>
        window.onload = function() {
            if (window.api && typeof window.api.printCurrentPageSilent === 'function') {
                window.api.printCurrentPageSilent();
            } else {
                window.print();
            }
        }
    </script>
</body>
</html>
