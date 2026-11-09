<?php
/**
 * Shift Summary Receipt (ໃບສະຫຼຸບຍອດກະຂາຍ)
 * ຮອງຮັບເຄື່ອງພິມໃບບິນ Thermal 80mm & 56mm
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$shiftId = (int)($_GET['id'] ?? 0);
if ($shiftId <= 0) {
    echo 'ບໍ່ພົບລະຫັດກະ';
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT s.*, u.full_name as cashier_name, u.username as cashier_username
    FROM shifts s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$shiftId]);
$shift = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$shift) {
    echo 'ບໍ່ພົບຂໍ້ມູນກະ';
    exit;
}

$settings = getShopSettings();
$receiptSize = $_GET['size'] ?? ($settings['receipt_size'] ?? '80mm');
$is56mm = ($receiptSize === '56mm');

// Get payment breakdown from sales table for this shift
$stmtSales = $db->prepare("
    SELECT 
        COUNT(*) as total_bills,
        COALESCE(SUM(total_amount), 0) as total_sales,
        COALESCE(SUM(cash_amount), 0) as total_cash,
        COALESCE(SUM(transfer_amount), 0) as total_transfer,
        COALESCE(SUM(discount_amount), 0) as total_discount,
        COALESCE(SUM(CASE WHEN payment_method = 'credit' THEN total_amount ELSE 0 END), 0) as total_credit,
        COALESCE(SUM(CASE WHEN currency = 'THB' THEN paid_amount_foreign ELSE 0 END), 0) as total_thb_collected,
        COALESCE(SUM(CASE WHEN currency = 'USD' THEN paid_amount_foreign ELSE 0 END), 0) as total_usd_collected
    FROM sales 
    WHERE shift_id = ? AND payment_status != 'cancelled'
");
$stmtSales->execute([$shiftId]);
$salesStats = $stmtSales->fetch(PDO::FETCH_ASSOC);

$openTime = !empty($shift['opened_at']) ? date('d/m/Y H:i', strtotime($shift['opened_at'])) : '-';
$closeTime = !empty($shift['closed_at']) ? date('d/m/Y H:i', strtotime($shift['closed_at'])) : 'ກຳລັງເປີດຢູ່';

$diff = (float)$shift['difference_lak'];
$diffLabel = '0 ກີບ (ພໍດີ)';
$diffClass = 'color: #000;';
if ($diff > 0) {
    $diffLabel = '+' . formatCurrency($diff) + ' ກີບ (ເກີນ)';
    $diffClass = 'color: #2e7d32;';
} elseif ($diff < 0) {
    $diffLabel = '-' . formatCurrency(abs($diff)) + ' ກີບ (ຂາດ)';
    $diffClass = 'color: #c62828;';
}
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <title>ໃບສະຫຼຸບຍອດກະ <?= htmlspecialchars($shift['shift_number']) ?></title>
    <style>
        @page {
            margin: 0;
            size: <?= $is56mm ? '56mm auto' : '80mm auto' ?>;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Noto Sans Lao', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: <?= $is56mm ? '10px' : '11.5px' ?>;
            line-height: 1.35;
            color: #000;
            background: #fff;
            padding: <?= $is56mm ? '6px 4px' : '10px 8px' ?>;
            width: <?= $is56mm ? '56mm' : '80mm' ?>;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
        }
        .shop-name {
            font-size: <?= $is56mm ? '13px' : '15px' ?>;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .shop-sub {
            font-size: <?= $is56mm ? '9px' : '10px' ?>;
            color: #333;
            margin-bottom: 4px;
        }
        .report-title {
            font-size: <?= $is56mm ? '12px' : '13px' ?>;
            font-weight: 700;
            text-transform: uppercase;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 4px 0;
            margin: 6px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 3px;
        }
        .row.bold {
            font-weight: 700;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .divider-double {
            border-top: 2px solid #000;
            margin: 6px 0;
        }
        .section-header {
            font-weight: 700;
            font-size: <?= $is56mm ? '10.5px' : '12px' ?>;
            margin: 5px 0 3px 0;
            background: #f4f4f4;
            padding: 2px 4px;
        }
        .sign-area {
            margin-top: 18px;
            display: flex;
            justify-content: space-between;
            text-align: center;
            font-size: <?= $is56mm ? '9px' : '10px' ?>;
        }
        .sign-line {
            width: 45%;
            border-top: 1px dotted #000;
            margin-top: 30px;
            padding-top: 3px;
        }
        @media print {
            body {
                padding: 4px 2px;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="shop-name"><?= htmlspecialchars($settings['shop_name'] ?? 'ຮ້ານຂາຍຢາດີ') ?></div>
        <?php if (!empty($settings['shop_name_en'])): ?>
            <div class="shop-sub"><?= htmlspecialchars($settings['shop_name_en']) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['phone'])): ?>
            <div class="shop-sub">ໂທ: <?= htmlspecialchars($settings['phone']) ?></div>
        <?php endif; ?>
        <div class="report-title">ໃບສະຫຼຸບຍອດກະ (SHIFT REPORT)</div>
    </div>

    <div class="row">
        <span>ເລກທີກະ:</span>
        <span class="bold"><?= htmlspecialchars($shift['shift_number']) ?></span>
    </div>
    <div class="row">
        <span>ພະນັກງານ:</span>
        <span class="bold"><?= htmlspecialchars($shift['cashier_name'] ?: $shift['cashier_username']) ?></span>
    </div>
    <div class="row">
        <span>ເວລາເປີດ:</span>
        <span><?= $openTime ?></span>
    </div>
    <div class="row">
        <span>ເວລາປິດ:</span>
        <span><?= $closeTime ?></span>
    </div>
    <div class="row">
        <span>ສະຖານະ:</span>
        <span class="bold"><?= $shift['status'] === 'closed' ? 'ປິດກະແລ້ວ' : 'ກຳລັງເປີດຢູ່' ?></span>
    </div>

    <div class="divider"></div>

    <div class="section-header">ສະຫຼຸບຍອດຂາຍ (SALES SUMMARY)</div>
    <div class="row">
        <span>ຈຳນວນບິນຂາຍ:</span>
        <span class="bold"><?= (int)($shift['total_bills'] ?: $salesStats['total_bills']) ?> ບິນ</span>
    </div>
    <div class="row bold" style="font-size: <?= $is56mm ? '11px' : '12.5px' ?>;">
        <span>ຍອດຂາຍລວມ:</span>
        <span><?= formatCurrency($shift['total_sales_amount'] ?: $salesStats['total_sales']) ?> ກີບ</span>
    </div>
    <?php if (($shift['total_discount'] ?: $salesStats['total_discount']) > 0): ?>
    <div class="row">
        <span>ສ່ວນຫຼຸດລວມ:</span>
        <span>-<?= formatCurrency($shift['total_discount'] ?: $salesStats['total_discount']) ?> ກີບ</span>
    </div>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="section-header">ແຍກຕາມຮູບແບບຊຳລະ (PAYMENT METHODS)</div>
    <div class="row">
        <span>ເງິນສົດ (LAK):</span>
        <span><?= formatCurrency($shift['total_cash_amount'] ?: $salesStats['total_cash']) ?> ກີບ</span>
    </div>
    <div class="row">
        <span>ເງິນໂອນ (Transfer):</span>
        <span><?= formatCurrency($shift['total_transfer_amount'] ?: $salesStats['total_transfer']) ?> ກີບ</span>
    </div>
    <?php if (($shift['total_credit_amount'] ?: $salesStats['total_credit']) > 0): ?>
    <div class="row">
        <span>ຕິດໜີ້ (Credit):</span>
        <span><?= formatCurrency($shift['total_credit_amount'] ?: $salesStats['total_credit']) ?> ກີບ</span>
    </div>
    <?php endif; ?>
    <?php if ($salesStats['total_thb_collected'] > 0): ?>
    <div class="row">
        <span>ເງິນບາດ (THB):</span>
        <span><?= number_format($salesStats['total_thb_collected'], 2) ?> ฿</span>
    </div>
    <?php endif; ?>
    <?php if ($salesStats['total_usd_collected'] > 0): ?>
    <div class="row">
        <span>ເງິນໂດລາ (USD):</span>
        <span><?= number_format($salesStats['total_usd_collected'], 2) ?> $</span>
    </div>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="section-header">ສະຫຼຸບເງິນສົດໃນລິ້ນຊັກ (DRAWER CASH)</div>
    <div class="row">
        <span>ເງິນຕັ້ງຕົ້ນ (Float):</span>
        <span><?= formatCurrency($shift['starting_cash']) ?> ກີບ</span>
    </div>
    <div class="row">
        <span>+ ຍອດຂາຍເງິນສົດ:</span>
        <span><?= formatCurrency($shift['total_cash_amount'] ?: $salesStats['total_cash']) ?> ກີບ</span>
    </div>
    <div class="divider"></div>
    <div class="row bold">
        <span>= ຍອດທີ່ຄວນມີ:</span>
        <span><?= formatCurrency($shift['expected_cash_lak'] ?: ((float)$shift['starting_cash'] + (float)$salesStats['total_cash'])) ?> ກີບ</span>
    </div>

    <?php if ($shift['status'] === 'closed'): ?>
    <div class="row">
        <span>ເງິນກີບນັບໄດ້:</span>
        <span><?= formatCurrency($shift['cash_counted_lak']) ?> ກີບ</span>
    </div>
    <?php if ($shift['cash_counted_thb'] > 0): ?>
    <div class="row">
        <span>ເງິນບາດນັບໄດ້:</span>
        <span><?= number_format($shift['cash_counted_thb'], 2) ?> ฿</span>
    </div>
    <?php endif; ?>
    <?php if ($shift['cash_counted_usd'] > 0): ?>
    <div class="row">
        <span>ເງິນໂດລານັບໄດ້:</span>
        <span><?= number_format($shift['cash_counted_usd'], 2) ?> $</span>
    </div>
    <?php endif; ?>
    <div class="divider"></div>
    <div class="row bold" style="font-size: <?= $is56mm ? '11px' : '12.5px' ?>; <?= $diffClass ?>">
        <span>ຜົນຕ່າງ (ຂາດ/ເກີນ):</span>
        <span><?= $diffLabel ?></span>
    </div>
    <?php endif; ?>

    <?php if (!empty($shift['note'])): ?>
    <div class="divider"></div>
    <div style="font-size: <?= $is56mm ? '9px' : '10px' ?>; color: #444; margin-top: 3px;">
        <strong>ໝາຍເຫດ:</strong> <?= nl2br(htmlspecialchars($shift['note'])) ?>
    </div>
    <?php endif; ?>

    <div class="divider-double"></div>

    <div class="sign-area">
        <div class="sign-line">ລາຍເຊັນພະນັກງານ</div>
        <div class="sign-line">ລາຍເຊັນຜູ້ຈັດການ</div>
    </div>

    <div style="text-align: center; margin-top: 15px; font-size: 8px; color: #666;">
        ພິມເມື່ອ: <?= date('d/m/Y H:i:s') ?>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('autoprint') === '1') {
                try {
                    window.print();
                } catch (e) {}
            }
        });
    </script>
</body>
</html>
