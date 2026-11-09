<?php
/**
 * ປິດກະ / ປະຫວັດກະຂາຍ - Shift Management & Audit
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'ປິດກະ / ປະຫວັດກະຂາຍ';

$db = getDB();

// Filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$userIdFilter = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$statusFilter = $_GET['status'] ?? 'all';

// Query shifts
$shifts = [];
try {
    $query = "
        SELECT s.*, u.full_name as cashier_name, u.username as cashier_username
        FROM shifts s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE DATE(s.opened_at) BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];

    if ($userIdFilter > 0) {
        $query .= " AND s.user_id = ?";
        $params[] = $userIdFilter;
    }

    if ($statusFilter === 'open' || $statusFilter === 'closed') {
        $query .= " AND s.status = ?";
        $params[] = $statusFilter;
    }

    $query .= " ORDER BY s.id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Shifts query error: " . $e->getMessage());
    $shifts = [];
}

// Get all staff for filter dropdown
$users = [];
try {
    $users = $db->query("SELECT id, username, full_name FROM users ORDER BY full_name, username")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $users = [];
}

// Calculate totals for summary cards
$totalShifts = count($shifts);
$totalSalesAll = 0;
$totalOver = 0;
$totalShort = 0;

foreach ($shifts as $s) {
    $totalSalesAll += (float)$s['total_sales_amount'];
    $diff = (float)$s['difference_lak'];
    if ($diff > 0) {
        $totalOver += $diff;
    } elseif ($diff < 0) {
        $totalShort += abs($diff);
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">ປິດກະ / ປະຫວັດກະຂາຍ</h1>
            <p style="font-size:0.85rem; color:var(--text-medium); margin-top:2px;">
                ກວດສອບຍອດຂາຍ, ການນັບເງິນສົດໃນລິ້ນຊັກ ແລະ ຜົນຕ່າງເງິນຂາດ/ເກີນປະຈຳກະ (Shift Balancing)
            </p>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>pos.php" class="btn btn-warning btn-sm" style="font-weight:700;">
                <i class="fas fa-clock"></i> ເປີດ / ປິດກະຂາຍ (ໜ້າ POS)
            </a>
            <a href="<?= BASE_URL ?>pos.php" class="btn btn-primary btn-sm">
                <i class="fas fa-cash-register"></i> ໄປໜ້າຂາຍ POS
            </a>
        </div>
    </div>

    <div class="content-area">
        <!-- Stats Row -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(46, 125, 50, 0.12); color: var(--primary);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">ຈຳນວນກະທັງໝົດ</span>
                    <strong class="stat-value"><?= $totalShifts ?> ກະ</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(0, 82, 204, 0.12); color: #0052cc;">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">ຍອດຂາຍລວມ</span>
                    <strong class="stat-value"><?= formatCurrency($totalSalesAll) ?> ₭</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(0, 135, 90, 0.12); color: #00875a;">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">ເງິນເກີນລວມ (Over)</span>
                    <strong class="stat-value" style="color: #00875a;">+<?= formatCurrency($totalOver) ?> ₭</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(222, 53, 11, 0.12); color: var(--danger);">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">ເງິນຂາດລວມ (Short)</span>
                    <strong class="stat-value" style="color: var(--danger);">-<?= formatCurrency($totalShort) ?> ₭</strong>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card" style="margin-bottom: 20px;">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                <div class="form-group" style="margin-bottom: 0; min-width: 140px; flex: 1;">
                    <label class="form-label" style="font-size: 12px;">ວັນທີເລີ່ມຕົ້ນ</label>
                    <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0; min-width: 140px; flex: 1;">
                    <label class="form-label" style="font-size: 12px;">ວັນທີສິ້ນສຸດ</label>
                    <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0; min-width: 150px; flex: 1;">
                    <label class="form-label" style="font-size: 12px;">ພະນັກງານ</label>
                    <select class="form-control" name="user_id">
                        <option value="0">-- ພະນັກງານທັງໝົດ --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $userIdFilter === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['full_name'] ?: $u['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0; min-width: 130px; flex: 1;">
                    <label class="form-label" style="font-size: 12px;">ສະຖານະ</label>
                    <select class="form-control" name="status">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>ທັງໝົດ</option>
                        <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>ກຳລັງເປີດ (Open)</option>
                        <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>ປິດແລ້ວ (Closed)</option>
                    </select>
                </div>
                <div style="display:flex; gap:6px;">
                    <button type="submit" class="btn btn-primary" style="height: 42px;">
                        <i class="fas fa-filter"></i> ກັ່ນຕອງ
                    </button>
                    <a href="<?= BASE_URL ?>shifts.php" class="btn btn-outline" style="height: 42px;" title="ລ້າງຄ່າ">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Shift Table Card -->
        <div class="card">
            <div class="card-header-flex">
                <h3 class="card-section-title">ລາຍການປະຫວັດກະທັງໝົດ</h3>
                <span style="font-size: 0.85rem; color: var(--text-medium);"><?= count($shifts) ?> ລາຍການ</span>
            </div>

            <?php if (empty($shifts)): ?>
                <div class="empty-state" style="padding: 50px 10px; text-align: center;">
                    <i class="fas fa-clock" style="font-size: 42px; color: var(--text-light); margin-bottom: 12px; display: block;"></i>
                    <p style="color: var(--text-medium); margin-bottom: 12px;">ບໍ່ພົບປະຫວັດກະຂາຍໃນຊ່ວງເວລາທີ່ເລືອກ</p>
                    <a href="<?= BASE_URL ?>pos.php" class="btn btn-primary btn-sm"><i class="fas fa-cash-register"></i> ໄປເປີດກະຂາຍທີ່ POS</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table" id="shiftTable">
                        <thead>
                            <tr>
                                <th>ເລກທີກະ</th>
                                <th>ພະນັກງານ</th>
                                <th>ເວລາເປີດ</th>
                                <th>ເວລາປິດ</th>
                                <th style="text-align:center;">ບິນ</th>
                                <th style="text-align:right;">ຍອດຂາຍລວມ</th>
                                <th style="text-align:right;">ເງິນທີ່ຄວນມີ</th>
                                <th style="text-align:right;">ເງິນນັບໄດ້</th>
                                <th style="text-align:center;">ຜົນຕ່າງ</th>
                                <th style="text-align:center;">ສະຖານະ</th>
                                <th style="text-align:center; min-width: 90px;">ຈັດການ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shifts as $s): ?>
                                <?php 
                                    $diff = (float)$s['difference_lak'];
                                    $diffBadge = '<span class="badge badge-success">0 ₭</span>';
                                    if ($diff > 0) {
                                        $diffBadge = '<span class="badge" style="background:#e3fcef; color:#006644; font-weight:700;">+' . formatCurrency($diff) . '</span>';
                                    } elseif ($diff < 0) {
                                        $diffBadge = '<span class="badge" style="background:#ffebe6; color:#bf2600; font-weight:700;">-' . formatCurrency(abs($diff)) . '</span>';
                                    }
                                    if ($s['status'] === 'open') {
                                        $diffBadge = '<span class="text-muted">-</span>';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--primary); font-family: monospace; font-size: 13px;">
                                            <?= htmlspecialchars($s['shift_number']) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($s['cashier_name'] ?: $s['cashier_username']) ?></strong>
                                    </td>
                                    <td style="font-size: 12px; color: var(--text-dark);">
                                        <?= date('d/m/Y H:i', strtotime($s['opened_at'])) ?>
                                    </td>
                                    <td style="font-size: 12px; color: var(--text-medium);">
                                        <?= !empty($s['closed_at']) ? date('d/m/Y H:i', strtotime($s['closed_at'])) : '<span style="color:#ffab00; font-weight:600;"><i class="fas fa-spinner fa-spin"></i> ກຳລັງເປີດ</span>' ?>
                                    </td>
                                    <td style="text-align:center; font-weight: 600;">
                                        <?= (int)$s['total_bills'] ?>
                                    </td>
                                    <td style="text-align:right; font-weight: 700; color: var(--primary);">
                                        <?= formatCurrency($s['total_sales_amount']) ?> ₭
                                    </td>
                                    <td style="text-align:right; font-size: 12.5px;">
                                        <?= formatCurrency($s['expected_cash_lak']) ?> ₭
                                    </td>
                                    <td style="text-align:right; font-size: 12.5px; font-weight: 600;">
                                        <?= $s['status'] === 'closed' ? formatCurrency($s['cash_counted_lak']) . ' ₭' : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?= $diffBadge ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($s['status'] === 'closed'): ?>
                                            <span class="badge badge-success"><i class="fas fa-check-circle"></i> ປິດແລ້ວ</span>
                                        <?php else: ?>
                                            <span class="badge" style="background:#fff8e6; color:#b76e00; border:1px solid #ffe399;"><i class="fas fa-circle-notch fa-spin"></i> ເປີດຢູ່</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center; white-space:nowrap;">
                                        <?php if ($s['status'] === 'open'): ?>
                                            <a href="<?= BASE_URL ?>pos.php" class="btn btn-warning btn-sm" style="padding:4px 8px; font-weight:600; margin-right:4px;" title="ປິດກະນີ້ທີ່ໜ້າ POS">
                                                <i class="fas fa-lock"></i> ປິດກະ
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline btn-sm btn-icon" onclick="printShiftReport(<?= (int)$s['id'] ?>)" title="ພິມໃບສະຫຼຸບຍອດກະ" style="padding: 4px 8px; min-width:34px;">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function printShiftReport(shiftId) {
    const url = `api/shift_receipt.php?id=${shiftId}&autoprint=1`;
    if (typeof printReceipt === 'function') {
        printReceipt(null, { url: url, title: `ໃບສະຫຼຸບຍອດກະ (#${shiftId})` });
    } else {
        window.open(url, '_blank', 'width=420,height=600');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
