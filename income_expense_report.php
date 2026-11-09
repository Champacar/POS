<?php
/**
 * ລາຍງານລາຍຮັບລາຍຈ່າຍ - Income/Expense Report
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();
if (!hasRole(['admin', 'manager']) && !canViewProfitLoss()) {
    header('Location: ' . BASE_URL . 'pos.php');
    exit;
}

$pageTitle = 'ລາຍງານລາຍຮັບລາຍຈ່າຍ';

$startDate = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$endDate = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

try {
    $stats = getIncomeExpenseStats($startDate, $endDate);
} catch (Exception $e) {
    $stats = [
        'profit' => 0,
        'total_income' => 0,
        'total_expense' => 0,
        'cash_sales' => 0,
        'transfer_sales' => 0,
        'general_expense' => 0,
        'import_expense' => 0,
    ];
}

// Get recent expenses
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT e.*, ec.name as category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC LIMIT 20");
    $stmt->execute([$startDate, $endDate]);
    $recentExpenses = $stmt->fetchAll();
} catch (Exception $e) {
    $recentExpenses = [];
}

// Get medicine imports (stock_history where type = 'in')
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT sh.*, p.name as product_name, p.unit, p.cost_price FROM stock_history sh LEFT JOIN products p ON sh.product_id = p.id WHERE sh.type = 'in' AND DATE(sh.created_at) BETWEEN ? AND ? ORDER BY sh.created_at DESC LIMIT 20");
    $stmt->execute([$startDate, $endDate]);
    $recentImports = $stmt->fetchAll();
} catch (Exception $e) {
    $recentImports = [];
}

// Get chart data
try {
    $chartData = getIncomeExpenseChartData($startDate, $endDate);
} catch (Exception $e) {
    $chartData = ['labels' => [], 'income' => [], 'expense' => [], 'profit' => []];
}

$extraScripts = ['charts.js'];

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">ລາຍງານລາຍຮັບລາຍຈ່າຍ</h1>
        <div class="date-filters">
            <a href="expenses.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> ບັນທຶກລາຍຈ່າຍ</a>
            <button class="btn-filter <?= ($startDate === date('Y-m-d') && $endDate === date('Y-m-d')) ? 'active' : '' ?>" data-filter="today" onclick="setDateFilter('today')">ມື້ນີ້</button>
            <button class="btn-filter <?= ($startDate === date('Y-m-01')) ? 'active' : '' ?>" data-filter="month" onclick="setDateFilter('month')">ເດືອນນີ້</button>
            <input type="date" class="date-input" id="startDate" value="<?= $startDate ?>" onchange="onDateChange()">
            <span class="date-separator">-</span>
            <input type="date" class="date-input" id="endDate" value="<?= $endDate ?>" onchange="onDateChange()">
        </div>
    </div>

    <div class="content-area">
        <!-- Stats Grid -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 20px;">
            <div class="stat-card animate-in">
                <div class="stat-icon green"><i class="fas fa-chart-line"></i></div>
                <div class="stat-info">
                    <h4>ກຳໄລ (ລາຍຮັບ - ລາຍຈ່າຍ)</h4>
                    <div>
                        <span class="stat-value"><?= formatCurrency($stats['profit']) ?></span>
                        <span class="stat-unit">ກີບ</span>
                    </div>
                </div>
            </div>

            <div class="stat-card animate-in">
                <div class="stat-icon green"><i class="fas fa-arrow-down"></i></div>
                <div class="stat-info">
                    <h4>ລວມລາຍຮັບ</h4>
                    <div>
                        <span class="stat-value"><?= formatCurrency($stats['total_income']) ?></span>
                        <span class="stat-unit">ກີບ</span>
                    </div>
                </div>
            </div>

            <div class="stat-card animate-in">
                <div class="stat-icon green"><i class="fas fa-money-check-alt"></i></div>
                <div class="stat-info">
                    <h4>ຊຳລະດ້ວຍເງິນສົດ</h4>
                    <div>
                        <span class="stat-value"><?= formatCurrency($stats['cash_sales']) ?></span>
                        <span class="stat-unit">ກີບ</span>
                    </div>
                </div>
            </div>

            <div class="stat-card animate-in">
                <div class="stat-icon coral"><i class="fas fa-university"></i></div>
                <div class="stat-info">
                    <h4>ຊຳລະດ້ວຍເງິນໂອນ</h4>
                    <div>
                        <span class="stat-value"><?= formatCurrency($stats['transfer_sales']) ?></span>
                        <span class="stat-unit">ກີບ</span>
                    </div>
                </div>
            </div>

            <div class="stat-card animate-in">
                <div class="stat-icon orange"><i class="fas fa-arrow-up"></i></div>
                <div class="stat-info">
                    <h4>ລວມລາຍຈ່າຍ</h4>
                    <div>
                        <span class="stat-value"><?= formatCurrency($stats['total_expense']) ?></span>
                        <span class="stat-unit">ກີບ</span>
                    </div>
                </div>
            </div>

            <div class="stat-card animate-in">
                <div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
                <div class="stat-info">
                    <h4>ລວມລາຍຈ່າຍທົ່ວໄປ</h4>
                    <div>
                        <span class="stat-value"><?= formatCurrency($stats['general_expense']) ?></span>
                        <span class="stat-unit">ກີບ</span>
                    </div>
                </div>
            </div>

            <div class="stat-card animate-in">
                <div class="stat-icon coral"><i class="fas fa-warehouse"></i></div>
                <div class="stat-info">
                    <h4>ລວມລາຍການນຳເຂົ້າສິນຄ້າ</h4>
                    <div>
                        <span class="stat-value"><?= formatCurrency($stats['import_expense']) ?></span>
                        <span class="stat-unit">ກີບ</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="card animate-in" style="min-height: 380px; display: flex; flex-direction: column;">
                <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 6px;">ລາຍຮັບ - ລາຍຈ່າຍ <?= $startDate === $endDate ? '(ຕາມຊົ່ວໂມງ)' : '(ຕາມວັນ)' ?></h3>
                <?php if ($stats['total_income'] == 0 && $stats['total_expense'] == 0): ?>
                <div class="empty-state" style="flex: 1; padding: 40px 20px;">
                    <i class="fas fa-chart-line"></i>
                    <p>ບໍ່ມີຂໍ້ມູນລາຍຮັບລາຍຈ່າຍ</p>
                    <span class="empty-state-hint">ເລືອກຊ່ວງວັນທີອື່ນ ຫຼື ເລີ່ມຂາຍ/ບັນທຶກລາຍຈ່າຍ</span>
                </div>
                <?php else: ?>
                <div class="chart-legend">
                    <div class="legend-item"><div class="legend-dot" style="background:#4caf50;"></div> ລາຍຮັບ</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#e57373;"></div> ລາຍຈ່າຍ</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#42a5f5;"></div> ກຳໄລ</div>
                </div>
                <div class="chart-container">
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
                <?php endif; ?>
            </div>

            <div style="display:flex;flex-direction:column;gap:20px;">
                <!-- General Expenses -->
                <div class="card animate-in">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <h3 style="font-size: 1rem; font-weight: 700;">ລາຍຈ່າຍທົ່ວໄປ</h3>
                        <a href="expenses.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> ບັນທຶກລາຍຈ່າຍ</a>
                    </div>
                    <?php if (empty($recentExpenses)): ?>
                    <div class="empty-state" style="padding:30px; text-align:center;">
                        <i class="fas fa-wallet" style="font-size: 2rem; color: var(--text-light); margin-bottom: 8px; display: block;"></i>
                        <p style="color: var(--text-medium); margin-bottom: 10px;">ບໍ່ມີຂໍ້ມູນລາຍຈ່າຍໃນຊ່ວງເວລານີ້</p>
                        <a href="expenses.php" class="btn btn-outline btn-sm"><i class="fas fa-plus"></i> ບັນທຶກລາຍຈ່າຍ</a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>ລາຍການ</th><th>ຈຳນວນ</th><th>ວັນທີ</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentExpenses as $exp): ?>
                                <tr>
                                    <td><?= sanitize($exp['title']) ?></td>
                                    <td><?= formatCurrency($exp['amount']) ?> ກີບ</td>
                                    <td><?= formatDate($exp['expense_date']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Import Section -->
                <div class="card animate-in">
                    <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 12px;">ການນຳເຂົ້າຢາ</h3>
                    <?php if (empty($recentImports)): ?>
                    <div class="empty-state" style="padding:25px; text-align:center;">
                        <i class="fas fa-boxes" style="font-size: 2rem; color: var(--text-light); margin-bottom: 8px; display: block;"></i>
                        <p style="color: var(--text-medium); margin-bottom: 10px;">ບໍ່ມີຂໍ້ມູນການນຳເຂົ້າຢາໃນຊ່ວງເວລານີ້</p>
                        <a href="inventory.php" class="btn btn-outline btn-sm"><i class="fas fa-plus"></i> ໄປໜ້າຮັບຢາເຂົ້າສາງ</a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ຊື່ຢາ</th>
                                    <th>ຈຳນວນ</th>
                                    <th>ວັນທີ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentImports as $imp): ?>
                                <tr>
                                    <td><strong><?= sanitize($imp['product_name'] ?? '-') ?></strong></td>
                                    <td><?= number_format($imp['quantity']) ?> <?= sanitize($imp['unit'] ?? '') ?></td>
                                    <td><?= formatDateTime($imp['created_at']) ?></td>
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
window.incExpChartLabels = <?= json_encode($chartData['labels']) ?>;
window.incExpChartIncome = <?= json_encode($chartData['income']) ?>;
window.incExpChartExpense = <?= json_encode($chartData['expense']) ?>;
window.incExpChartProfit = <?= json_encode($chartData['profit']) ?>;

function loadPageData(startDate, endDate) {
    window.location.href = `income_expense_report.php?start=${startDate}&end=${endDate}`;
}
</script>

<?php include 'includes/footer.php'; ?>
