<?php
/**
 * ລາຍງານການຂາຍ - Sales Report Dashboard
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();
if (!hasPermission('can_view_reports')) {
    header('Location: ' . BASE_URL . 'pos.php');
    exit;
}

$pageTitle = 'ລາຍງານການຂາຍ';

// Default date range
$startDate = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$endDate = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

// Get stats
try {
    $stats = getDashboardStats($startDate, $endDate);
} catch (Exception $e) {
    $stats = [
        'total_sales' => 0,
        'cash_sales' => 0,
        'transfer_sales' => 0,
        'bill_count' => 0,
        'items_sold' => 0,
        'notifications' => 0,
    ];
}

// Get recent sales
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT s.*, u.full_name as cashier FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.payment_status != 'cancelled' AND DATE(s.created_at) BETWEEN ? AND ? ORDER BY s.created_at DESC LIMIT 20");
    $stmt->execute([$startDate, $endDate]);
    $recentSales = $stmt->fetchAll();
} catch (Exception $e) {
    $recentSales = [];
}

// Get chart data
try {
    $chartData = getSalesChartData($startDate, $endDate);
} catch (Exception $e) {
    $chartData = ['labels' => [], 'data' => []];
}

// Get top-selling products
try {
    $topProducts = getTopSellingProducts($startDate, $endDate, 5);
} catch (Exception $e) {
    $topProducts = [];
}

// Get expiring products
$expiryDays = isset($_GET['expiry_days']) ? (int)$_GET['expiry_days'] : 90;
try {
    $expiringProducts = getExpiringProducts($expiryDays);
} catch (Exception $e) {
    $expiringProducts = [];
}

try {
    $expiredProducts = getExpiredProducts();
} catch (Exception $e) {
    $expiredProducts = [];
}

// Get low stock products
try {
    $lowStockProducts = getLowStockProducts(10);
} catch (Exception $e) {
    $lowStockProducts = [];
}

$extraScripts = ['charts.js'];

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main Content -->
<main class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">ລາຍງານການຂາຍ</h1>
            <p style="font-size:0.85rem;color:var(--text-medium);margin-top:2px;">ພາບລວມຍອດຂາຍ, ສະຕ໋ອກຢາ ແລະ ການແຈ້ງເຕືອນວັນໝົດອາຍຸປະຈຳວັນ</p>
        </div>
        <div class="date-filters" style="flex-wrap:wrap;gap:8px;">
            <a href="pos.php" class="btn btn-primary btn-sm"><i class="fas fa-cash-register"></i> ເປີດໜ້າຂາຍ (POS)</a>
            <button class="btn-filter <?= ($startDate === date('Y-m-d') && $endDate === date('Y-m-d')) ? 'active' : '' ?>" data-filter="today" onclick="setDateFilter('today')">ມື້ນີ້</button>
            <button class="btn-filter <?= ($startDate === date('Y-m-01')) ? 'active' : '' ?>" data-filter="month" onclick="setDateFilter('month')">ເດືອນນີ້</button>
            <input type="date" class="date-input" id="startDate" value="<?= $startDate ?>" onchange="onDateChange()">
            <span class="date-separator">-</span>
            <input type="date" class="date-input" id="endDate" value="<?= $endDate ?>" onchange="onDateChange()">
        </div>
    </div>

    <!-- Content Area -->
    <div class="content-area">
        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'developer'], true) && (empty($settings['phone']) || $settings['phone'] === '020 99999999')): ?>
        <div class="alert alert-info" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
            <div>
                <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                <strong>ຍິນດີຕ້ອນຮັບ!</strong> ກະລຸນາຕັ້ງຄ່າຊື່ຮ້ານ, ເບີໂທລະສັບ ແລະ ໂລໂກ້ຮ້ານກ່ອນເລີ່ມພິມໃບບິນໃຫ້ລູກຄ້າ.
            </div>
            <a href="shop_settings.php" class="btn btn-primary btn-sm"><i class="fas fa-store"></i> ໄປຕັ້ງຄ່າຮ້ານ</a>
        </div>
        <?php endif; ?>

        <?php 
        $isAdminDefaultPwd = false;
        if (($_SESSION['username'] ?? '') === 'admin') {
            try {
                $checkStmt = $db->prepare("SELECT password FROM users WHERE username = 'admin'");
                $checkStmt->execute();
                $adminUser = $checkStmt->fetch();
                if ($adminUser && password_verify('password', $adminUser['password'])) {
                    $isAdminDefaultPwd = true;
                }
            } catch (Exception $e) {}
        }
        ?>
        <?php if ($isAdminDefaultPwd): ?>
        <div class="alert alert-warning" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; background:#fff3e0; border:1px solid #ffe0b2; color:#935100; font-weight:500; border-radius:12px; padding:12px 18px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <i class="fas fa-exclamation-triangle" style="font-size:1.4rem; color:#e65100;"></i>
                <div>
                    <strong style="color:#bf360c;">ແຈ້ງເຕືອນຄວາມປອດໄພ:</strong> ບັນຊີ Admin ຍັງຄົງໃຊ້ລະຫັດຜ່ານເລີ່ມຕົ້ນ (password). ກະລຸນາປ່ຽນລະຫັດຜ່ານທັນທີເພື່ອຄວາມປອດໄພຂອງລະບົບ.
                </div>
            </div>
            <a href="staff.php" class="btn btn-sm" style="background:#e65100; color:#fff; border-radius:8px; font-weight:600;"><i class="fas fa-key"></i> ປ່ຽນລະຫັດຜ່ານດຽວນີ້</a>
        </div>
        <?php endif; ?>
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card animate-in" style="animation-delay: 0.05s;">
                <div class="stat-icon green">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h4>ຍອດຂາຍ</h4>
                    <div>
                        <span class="stat-value"><?= formatCurrency($stats['total_sales']) ?></span>
                        <span class="stat-unit">ກີບ</span>
                    </div>
                </div>
            </div>

            <div class="stat-card animate-in" style="animation-delay: 0.1s;">
                <div class="stat-icon green">
                    <i class="fas fa-money-check-alt"></i>
                </div>
                <div class="stat-info">
                    <h4>ຊຳລະດ້ວຍເງິນສົດ</h4>
                    <div>
                        <span class="stat-value"><?= formatCurrency($stats['cash_sales']) ?></span>
                        <span class="stat-unit">ກີບ</span>
                    </div>
                </div>
            </div>

            <div class="stat-card animate-in" style="animation-delay: 0.15s;">
                <div class="stat-icon coral">
                    <i class="fas fa-university"></i>
                </div>
                <div class="stat-info">
                    <h4>ຊຳລະດ້ວຍເງິນໂອນ</h4>
                    <div>
                        <span class="stat-value"><?= formatCurrency($stats['transfer_sales']) ?></span>
                        <span class="stat-unit">ກີບ</span>
                    </div>
                </div>
            </div>

            <div class="stat-card animate-in" style="animation-delay: 0.2s;">
                <div class="stat-icon blue">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                    <h4>ຈຳນວນບິນ</h4>
                    <div>
                        <span class="stat-value"><?= $stats['bill_count'] ?></span>
                    </div>
                </div>
            </div>

            <div class="stat-card animate-in" style="animation-delay: 0.25s;">
                <div class="stat-icon teal">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-info">
                    <h4>ຈຳນວນລາຍການສິນຄ້າ</h4>
                    <div>
                        <span class="stat-value"><?= $stats['items_sold'] ?></span>
                    </div>
                </div>
            </div>

            <div class="stat-card animate-in" style="animation-delay: 0.3s;">
                <div class="stat-icon <?= $stats['notifications'] > 0 ? 'coral pulse' : 'coral' ?>">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-info">
                    <h4>ການແຈ້ງເຕືອນ</h4>
                    <div>
                        <span class="stat-value"><?= $stats['notifications'] ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Widgets: Expiring / Expired / Low Stock -->
        <?php $hasAlerts = !empty($expiredProducts) || !empty($expiringProducts) || !empty($lowStockProducts); ?>
        <?php if ($hasAlerts): ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:16px; margin-bottom:20px;">

            <?php if (!empty($expiredProducts)): ?>
            <div class="card animate-in" style="border: 1px solid #ffcdd2; padding:16px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(229,115,115,0.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-exclamation-triangle" style="color:var(--danger);font-size:1rem;"></i>
                    </div>
                    <div>
                        <h4 style="font-size:0.9rem;font-weight:700;color:var(--danger);margin:0;">ສິນຄ້າໝົດອາຍຸແລ້ວ</h4>
                        <span style="font-size:0.75rem;color:var(--text-light);"><?= count($expiredProducts) ?> ລາຍການ</span>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:200px;overflow-y:auto;">
                    <table class="table" style="font-size:0.8rem;">
                        <thead><tr><th>ຊື່ສິນຄ້າ</th><th>ໝົດອາຍຸ</th><th>ຄົງເຫຼືອ</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($expiredProducts, 0, 8) as $ep): ?>
                            <tr>
                                <td><strong><?= sanitize($ep['name']) ?></strong></td>
                                <td><span class="badge badge-danger"><?= date('d/m/Y', strtotime($ep['expiry_date'])) ?></span></td>
                                <td><?= number_format($ep['stock_quantity']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($expiredProducts) > 8): ?>
                <a href="inventory.php?tab=batch-list" style="font-size:0.78rem;color:var(--danger);font-weight:600;text-decoration:none;">ເບິ່ງທັງໝົດ →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($expiringProducts)): ?>
            <div class="card animate-in" style="border: 1px solid #ffe082; padding:16px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,183,77,0.15);display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-clock" style="color:var(--warning);font-size:1rem;"></i>
                        </div>
                        <div>
                            <h4 style="font-size:0.9rem;font-weight:700;color:#e65100;margin:0;">ສິນຄ້າໃກ້ໝົດອາຍຸ</h4>
                            <span style="font-size:0.75rem;color:var(--text-light);"><?= count($expiringProducts) ?> ລາຍການ (ພາຍໃນ <?= $expiryDays ?> ວັນ)</span>
                        </div>
                    </div>
                    <div>
                        <select class="form-control" id="expiryThresholdSelect" onchange="changeExpiryThreshold(this.value)" style="padding:4px 8px; font-size:0.75rem; height:auto; width:auto; border-radius:6px; background:#fff; border:1px solid #ccc; font-family:'Noto Sans Lao',sans-serif;">
                            <option value="30" <?= $expiryDays == 30 ? 'selected' : '' ?>>30 ວັນ (1 ເດືອນ)</option>
                            <option value="90" <?= $expiryDays == 90 ? 'selected' : '' ?>>90 ວັນ (3 ເດືອນ)</option>
                            <option value="180" <?= $expiryDays == 180 ? 'selected' : '' ?>>180 ວັນ (6 ເດືອນ)</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:200px;overflow-y:auto;">
                    <table class="table" style="font-size:0.8rem;">
                        <thead><tr><th>ຊື່ສິນຄ້າ</th><th>ໝົດອາຍຸ</th><th>ຄົງເຫຼືອ</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($expiringProducts, 0, 8) as $ep): ?>
                            <?php $daysLeft = (int)((strtotime($ep['expiry_date']) - time()) / 86400); ?>
                            <tr>
                                <td><strong><?= sanitize($ep['name']) ?></strong></td>
                                <td><span class="badge badge-warning"><?= date('d/m/Y', strtotime($ep['expiry_date'])) ?> (<?= $daysLeft ?> ວັນ)</span></td>
                                <td><?= number_format($ep['stock_quantity']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($expiringProducts) > 8): ?>
                <a href="inventory.php?tab=batch-list" style="font-size:0.78rem;color:#e65100;font-weight:600;text-decoration:none;">ເບິ່ງທັງໝົດ →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($lowStockProducts)): ?>
            <div class="card animate-in" style="border: 1px solid #bbdefb; padding:16px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(2,119,189,0.12);display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-cubes" style="color:var(--info);font-size:1rem;"></i>
                    </div>
                    <div>
                        <h4 style="font-size:0.9rem;font-weight:700;color:#1565c0;margin:0;">ສິນຄ້າໃກ້ໝົດສະຕ໋ອກ</h4>
                        <span style="font-size:0.75rem;color:var(--text-light);"><?= count($lowStockProducts) ?> ລາຍການ</span>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:200px;overflow-y:auto;">
                    <table class="table" style="font-size:0.8rem;">
                        <thead><tr><th>ຊື່ສິນຄ້າ</th><th>ຄົງເຫຼືອ</th><th>ຂັ້ນຕ່ຳ</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($lowStockProducts, 0, 8) as $lp): ?>
                            <tr>
                                <td><strong><?= sanitize($lp['name']) ?></strong></td>
                                <td>
                                    <span class="badge <?= $lp['stock_quantity'] == 0 ? 'badge-danger' : 'badge-warning' ?>">
                                        <?= number_format($lp['stock_quantity']) ?>
                                    </span>
                                </td>
                                <td><?= number_format($lp['min_stock']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($lowStockProducts) > 8): ?>
                <a href="inventory.php" style="font-size:0.78rem;color:#1565c0;font-weight:600;text-decoration:none;">ເບິ່ງທັງໝົດ →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

        <!-- Charts and Recent Sales -->
        <div class="charts-grid">
            <!-- Sales Chart -->
            <div class="card animate-in" style="min-height: 380px; display: flex; flex-direction: column;">
                <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 16px;">ຍອດຂາຍ <?= $startDate === $endDate ? '(ຕາມຊົ່ວໂມງ)' : '(ຕາມວັນ)' ?></h3>
                <?php if (empty($recentSales)): ?>
                <div class="empty-state" style="flex: 1; padding: 40px 20px;">
                    <i class="fas fa-chart-bar"></i>
                    <p>ບໍ່ມີຂໍ້ມູນຍອດຂາຍ</p>
                    <span class="empty-state-hint">ເລືອກຊ່ວງວັນທີອື່ນ ຫຼື ເລີ່ມຂາຍ</span>
                    <div style="margin-top: 16px;">
                        <a href="pos.php" class="btn btn-primary"><i class="fas fa-cash-register"></i> ເປີດໜ້າຂາຍ (POS)</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="chart-container" id="salesChartContainer">
                    <canvas id="salesChart"></canvas>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div style="display:flex;flex-direction:column;gap:20px;">
                <!-- Top Selling Products -->
                <div class="card animate-in">
                    <div class="top-products-header">
                        <h3 class="top-products-title">
                            <span class="top-products-icon">
                                <i class="fas fa-trophy"></i>
                            </span>
                            <span>ສິນຄ້າຂາຍດີ</span>
                        </h3>
                        <?php if (!empty($topProducts)): ?>
                            <span class="top-products-count-badge">Top <?= count($topProducts) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($topProducts)): ?>
                    <div class="empty-state" style="padding:30px;">
                        <i class="fas fa-medal"></i>
                        <p>ບໍ່ມີຂໍ້ມູນສິນຄ້າຂາຍດີ</p>
                    </div>
                    <?php else: ?>
                    <?php 
                        $maxQty = !empty($topProducts) ? max(array_column($topProducts, 'total_qty')) : 1; 
                        if ($maxQty <= 0) $maxQty = 1; 
                    ?>
                    <div class="top-products-list">
                        <?php foreach ($topProducts as $i => $tp): 
                            $rank = $i + 1;
                            $pct = min(100, max(12, round(($tp['total_qty'] / $maxQty) * 100)));
                        ?>
                        <div class="top-product-item rank-<?= $rank ?>">
                            <div class="top-product-rank rank-badge-<?= $rank ?>" title="ອັນດັບ <?= $rank ?>">
                                <?php if ($rank === 1): ?>
                                    <i class="fas fa-crown"></i>
                                <?php else: ?>
                                    <?= $rank ?>
                                <?php endif; ?>
                            </div>
                            <div class="top-product-info">
                                <div class="top-product-main">
                                    <span class="top-product-name" title="<?= sanitize($tp['product_name']) ?>"><?= sanitize($tp['product_name']) ?></span>
                                    <span class="top-product-revenue"><?= formatCurrency($tp['total_revenue']) ?> <small>₭</small></span>
                                </div>
                                <div class="top-product-meta">
                                    <span class="top-product-qty"><i class="fas fa-shopping-bag"></i> <?= $tp['total_qty'] ?> ລາຍການ</span>
                                    <div class="top-product-progress">
                                        <div class="top-product-progress-bar rank-bar-<?= $rank ?>" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Sales -->
                <div class="card animate-in">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid var(--border-light);">
                        <h3 style="font-size:1rem; font-weight:700; margin:0; display:flex; align-items:center; gap:8px; color:var(--text-dark);">
                            <span style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:8px; background:linear-gradient(135deg, #e8f5e9, #c8e6c9); color:var(--primary); box-shadow:0 1px 3px rgba(46,125,50,0.15);">
                                <i class="fas fa-receipt" style="font-size:0.85rem;"></i>
                            </span>
                            <span>ລາຍການຂາຍລ່າສຸດ</span>
                        </h3>
                        <a href="sales_history.php" style="font-size:0.78rem; color:var(--primary); font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">ເບິ່ງທັງໝົດ <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i></a>
                    </div>
                    <?php if (empty($recentSales)): ?>
                    <div class="empty-state" style="padding:30px; text-align:center;">
                        <i class="fas fa-receipt" style="font-size: 2.2rem; color: var(--text-light); margin-bottom: 8px; display: block;"></i>
                        <p style="color: var(--text-medium); margin-bottom: 12px;">ບໍ່ມີລາຍການຂາຍລ່າສຸດ</p>
                        <a href="pos.php" class="btn btn-primary btn-sm"><i class="fas fa-cash-register"></i> ເປີດໜ້າຂາຍ (POS)</a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table" id="recentSalesTable">
                            <thead>
                                <tr>
                                    <th>ເລກບິນ</th>
                                    <th>ຍອດ</th>
                                    <th>ການຊຳລະ</th>
                                    <th>ເວລາ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recentSales, 0, 10) as $sale): ?>
                                <tr>
                                    <td><?= $sale['invoice_number'] ?></td>
                                    <td><?= formatCurrency($sale['total_amount']) ?> ກີບ</td>
                                    <td>
                                        <span class="badge <?= getPaymentMethodBadge($sale['payment_method']) ?>"><?= getPaymentMethodLabel($sale['payment_method']) ?></span>
                                    </td>
                                    <td><?= formatDateTime($sale['created_at']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
window.salesChartLabels = <?= json_encode($chartData['labels']) ?>;
window.salesChartData = <?= json_encode($chartData['data']) ?>;

// Page data loader for date filter changes
function loadPageData(startDate, endDate) {
    const url = new URL(window.location.href);
    url.searchParams.set('start', startDate);
    url.searchParams.set('end', endDate);
    window.location.href = url.toString();
}

function changeExpiryThreshold(days) {
    const url = new URL(window.location.href);
    url.searchParams.set('expiry_days', days);
    window.location.href = url.toString();
}
</script>

<?php include 'includes/footer.php'; ?>
