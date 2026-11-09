<?php
/**
 * POSPharma - Profit & Loss and Sales Analytics Dashboard
 * ລາຍງານກຳໄລ-ຂາດທຶນ & ວິເຄາະຍອດຂາຍເຊີງເລິກ 100% Offline
 */

require_once 'includes/functions.php';
requirePermission('can_view_profit');

$pageTitle = 'ລາຍງານກຳໄລ-ຂາດທຶນ & ວິເຄາະ';

// Initial date range (default to current month)
$startDate = isset($_GET['start']) ? trim($_GET['start']) : date('Y-m-01');
$endDate = isset($_GET['end']) ? trim($_GET['end']) : date('Y-m-d');

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header" style="flex-wrap:wrap; gap:16px; align-items:center; justify-content:space-between;">
        <div>
            <h1 class="page-title" style="display:flex; align-items:center; gap:10px;">
                <i class="fas fa-chart-pie" style="color:var(--primary);"></i> ລາຍງານກຳໄລ-ຂາດທຶນ & ວິເຄາະຍອດຂາຍ
            </h1>
            <p style="font-size:0.88rem; color:var(--text-medium); margin-top:3px; margin-bottom:0;">
                ວິເຄາະຕົ້ນທຶນສິນຄ້າ (COGS), ກຳໄລຂັ້ນຕົ້ນ, ລາຍຈ່າຍບໍລິຫານ ແລະ ກຳໄລສຸດທິ ຕາມມາດຕະຖານບັນຊີ
            </p>
        </div>

        <!-- Date Filters Toolbar -->
        <div class="date-filters" style="display:flex; flex-wrap:wrap; align-items:center; gap:8px;">
            <div class="btn-group" style="display:flex; gap:4px;">
                <button type="button" class="btn btn-sm btn-filter" onclick="applyDatePreset('today')" id="btnFilterToday">ມື້ນີ້</button>
                <button type="button" class="btn btn-sm btn-filter" onclick="applyDatePreset('yesterday')" id="btnFilterYesterday">ມື້ວານ</button>
                <button type="button" class="btn btn-sm btn-filter" onclick="applyDatePreset('7days')" id="btnFilter7Days">7 ມື້ຜ່ານມາ</button>
                <button type="button" class="btn btn-sm btn-filter active" onclick="applyDatePreset('this_month')" id="btnFilterThisMonth">ເດືອນນີ້</button>
                <button type="button" class="btn btn-sm btn-filter" onclick="applyDatePreset('last_month')" id="btnFilterLastMonth">ເດືອນກ່ອນ</button>
                <button type="button" class="btn btn-sm btn-filter" onclick="applyDatePreset('this_year')" id="btnFilterThisYear">ປີນີ້</button>
            </div>
            <div style="display:inline-flex; align-items:center; gap:4px; background:#fff; padding:3px 8px; border:1px solid var(--border-light); border-radius:8px;">
                <input type="date" class="date-input" id="reportStartDate" value="<?= $startDate ?>" onchange="reloadReportData()" style="border:none; outline:none; font-size:0.85rem;">
                <span style="color:var(--text-light);">-</span>
                <input type="date" class="date-input" id="reportEndDate" value="<?= $endDate ?>" onchange="reloadReportData()" style="border:none; outline:none; font-size:0.85rem;">
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="exportProfitLossExcel()" style="border-color:#2563eb; color:#1d4ed8; background:#eff6ff; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
                <i class="fas fa-file-excel"></i> ສົ່ງອອກ Excel
            </button>
        </div>
    </div>

    <!-- Executive Financial KPIs -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom:24px;">
        <!-- 1. Total Revenue -->
        <div class="stat-card" style="border-left: 4px solid #0052cc;">
            <div class="stat-icon blue"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <h4>ຍອດຂາຍລວມ (Revenue)</h4>
                <span class="stat-value" id="kpiRevenue" style="font-size:1.45rem;">0 ₭</span>
                <small style="color:var(--text-light); font-size:0.78rem;">
                    ຈຳນວນ: <b id="kpiBillCount">0</b> ບິນ (ສະເລ່ຍ: <span id="kpiAvgBill">0</span> ₭)
                </small>
            </div>
        </div>

        <!-- 2. Cost of Goods Sold -->
        <div class="stat-card" style="border-left: 4px solid #ffab00;">
            <div class="stat-icon orange"><i class="fas fa-boxes"></i></div>
            <div class="stat-info">
                <h4>ຕົ້ນທຶນສິນຄ້າຂາຍ (COGS)</h4>
                <span class="stat-value" id="kpiCogs" style="font-size:1.45rem; color:#b76e00;">0 ₭</span>
                <small style="color:var(--text-light); font-size:0.78rem;">
                    ຈຳນວນຢາທີ່ຂາຍ: <b id="kpiItemsSold">0</b> ແຜງ/ກ່ອງ
                </small>
            </div>
        </div>

        <!-- 3. Gross Profit -->
        <div class="stat-card" style="border-left: 4px solid #059669;">
            <div class="stat-icon green"><i class="fas fa-chart-line"></i></div>
            <div class="stat-info">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h4>ກຳໄລຂັ້ນຕົ້ນ (Gross)</h4>
                    <span class="badge badge-success" id="badgeGrossMargin" style="font-weight:700; font-size:0.75rem;">0%</span>
                </div>
                <span class="stat-value" id="kpiGrossProfit" style="font-size:1.45rem; color:#059669;">0 ₭</span>
                <small style="color:var(--text-light); font-size:0.78rem;">
                    ຍອດຂາຍລົບຕົ້ນທຶນສິນຄ້າ
                </small>
            </div>
        </div>

        <!-- 4. Operating Expenses -->
        <div class="stat-card" style="border-left: 4px solid #de350b;">
            <div class="stat-icon coral"><i class="fas fa-receipt"></i></div>
            <div class="stat-info">
                <h4>ຄ່າໃຊ້ຈ່າຍຮ້ານ (OpEx)</h4>
                <span class="stat-value" id="kpiExpenses" style="font-size:1.45rem; color:var(--danger);">0 ₭</span>
                <small style="color:var(--text-light); font-size:0.78rem;">
                    ຄ່າເຊົ່າ, ຄ່າໄຟ, ເງິນເດືອນ, ອື່ນໆ
                </small>
            </div>
        </div>

        <!-- 5. Net Profit -->
        <div class="stat-card" style="border-left: 4px solid #7c3aed; background:linear-gradient(180deg, #faf5ff 0%, #fff 100%);">
            <div class="stat-icon purple"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h4 style="color:#581c87; font-weight:800;">ກຳໄລສຸດທິ (Net Profit)</h4>
                    <span class="badge" id="badgeNetMargin" style="font-weight:700; font-size:0.75rem; background:#ede9fe; color:#6b21a8;">0%</span>
                </div>
                <span class="stat-value" id="kpiNetProfit" style="font-size:1.55rem; color:#6b21a8; font-weight:900;">0 ₭</span>
                <small style="color:var(--text-light); font-size:0.78rem;">
                    ກຳໄລຕົວຈິງຫຼັງຫັກທຸກລາຍຈ່າຍ
                </small>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap:20px; margin-bottom:24px;">
        <!-- Chart 1: Profit & Revenue Trend -->
        <div class="card" style="padding:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-dark); margin:0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-chart-area" style="color:var(--primary);"></i> ແນວໂນ້ມຍອດຂາຍ, ຕົ້ນທຶນ & ກຳໄລສຸດທິ
                </h3>
            </div>
            <div style="position:relative; height:280px;">
                <canvas id="profitTrendChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: Category Profit Share -->
        <div class="card" style="padding:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-dark); margin:0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-chart-pie" style="color:#10b981;"></i> ສັດສ່ວນກຳໄລຕາມໝວດໝູ່ຢາ
                </h3>
            </div>
            <div style="position:relative; height:280px; display:flex; align-items:center; justify-content:center;">
                <canvas id="categoryShareChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 3: Peak Sales Hours Heatmap -->
    <div class="card" style="padding:20px; margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
            <h3 style="font-size:1.05rem; font-weight:700; color:var(--text-dark); margin:0; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-clock" style="color:#f59e0b;"></i> ຊ່ວງເວລາຂາຍດີທີ່ສຸດ (Peak Sales Hours: 08:00 - 21:00)
            </h3>
            <span style="font-size:0.82rem; color:var(--text-light);">
                ຊ່ວຍວິເຄາະໄລຍະເວລາທີ່ລູກຄ້າເຂົ້າຮ້ານຫຼາຍສຸດ ເພື່ອວາງແຜນຈັດກະເພສັດ
            </span>
        </div>
        <div style="position:relative; height:220px;">
            <canvas id="peakHoursChart"></canvas>
        </div>
    </div>

    <!-- Drilldown Tabs Section -->
    <div class="card" style="padding:0; overflow:hidden;">
        <!-- Tabs Header -->
        <div class="tabs" style="display:flex; border-bottom:1px solid var(--border-light); background:#f8fafc; padding:12px 20px 0; margin-bottom:0; gap:8px;">
            <button type="button" class="tab active" onclick="switchPlTab('products')" id="plTabProducts">
                <i class="fas fa-pills"></i> ວິເຄາະກຳໄລຕາມລາຍການຢາ
            </button>
            <button type="button" class="tab" onclick="switchPlTab('categories')" id="plTabCategories">
                <i class="fas fa-folder"></i> ກຳໄລຕາມໝວດໝູ່
            </button>
            <button type="button" class="tab" onclick="switchPlTab('payments')" id="plTabPayments">
                <i class="fas fa-credit-card"></i> ຊ່ອງທາງຊຳລະ & ສະກຸນເງິນ
            </button>
        </div>

        <!-- Tab 1: Profit by Product -->
        <div id="plContainerProducts" style="padding:20px;">
            <div class="toolbar" style="flex-wrap:wrap; gap:12px; margin-bottom:14px;">
                <div style="font-weight:700; font-size:1rem; color:var(--text-dark);">
                    ລາຍການຢາທີ່ສ້າງກຳໄລສູງສຸດ
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="ຄົ້ນຫາຊື່ຢາ ຫຼື ບາໂຄດ..." oninput="filterTable('plProductSearch','plProductTable')" id="plProductSearch">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="plProductTable" style="font-size:0.88rem;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="width:35px;">#</th>
                            <th>ຊື່ສິນຄ້າ / ຢາ</th>
                            <th>ໝວດໝູ່</th>
                            <th style="text-align:center; width:90px;">ຈຳນວນຂາຍ</th>
                            <th style="text-align:right; width:110px;">ລາຄາຂາຍ</th>
                            <th style="text-align:right; width:110px;">ຕົ້ນທຶນ</th>
                            <th style="text-align:right; width:130px;">ຍອດຂາຍລວມ</th>
                            <th style="text-align:right; width:130px;">ຕົ້ນທຶນລວມ</th>
                            <th style="text-align:right; width:130px; color:#059669;">ກຳໄລລວມ (Profit)</th>
                            <th style="text-align:center; width:100px;">% Margin</th>
                        </tr>
                    </thead>
                    <tbody id="plProductTableBody">
                        <tr><td colspan="10" style="text-align:center; padding:30px;"><i class="fas fa-spinner fa-spin"></i> ກຳລັງໂຫຼດຂໍ້ມູນ...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 2: Profit by Category -->
        <div id="plContainerCategories" style="display:none; padding:20px;">
            <div class="table-responsive">
                <table class="table" id="plCategoryTable" style="font-size:0.88rem;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="width:35px;">#</th>
                            <th>ໝວດໝູ່ຢາ</th>
                            <th style="text-align:center; width:110px;">ຈຳນວນທີ່ຂາຍ</th>
                            <th style="text-align:right; width:150px;">ຍອດຂາຍລວມ</th>
                            <th style="text-align:right; width:150px;">ຕົ້ນທຶນລວມ (COGS)</th>
                            <th style="text-align:right; width:150px; color:#059669;">ກຳໄລລວມ</th>
                            <th style="text-align:center; width:120px;">% Margin</th>
                            <th style="text-align:center; width:140px;">ສັດສ່ວນຍອດຂາຍ</th>
                        </tr>
                    </thead>
                    <tbody id="plCategoryTableBody">
                        <tr><td colspan="8" style="text-align:center; padding:30px;"><i class="fas fa-spinner fa-spin"></i> ກຳລັງໂຫຼດ...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 3: Payment Methods & Currencies -->
        <div id="plContainerPayments" style="display:none; padding:20px;">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px;">
                <!-- Payment Methods Card -->
                <div class="card" style="box-shadow:none; border:1px solid var(--border-light);">
                    <h4 style="font-size:0.95rem; font-weight:700; margin-bottom:14px; color:var(--text-dark);">
                        <i class="fas fa-wallet" style="color:var(--primary); margin-right:6px;"></i> ຮູບແບບການຊຳລະເງິນ
                    </h4>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:#f8fafc; border-radius:8px;">
                            <span><i class="fas fa-money-bill-wave" style="color:#059669; margin-right:6px;"></i> ເງິນສົດ (Cash)</span>
                            <strong id="payCashTotal">0 ₭</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:#f8fafc; border-radius:8px;">
                            <span><i class="fas fa-qrcode" style="color:#0284c7; margin-right:6px;"></i> ໂອນ (QR Transfer)</span>
                            <strong id="payTransferTotal">0 ₭</strong>
                        </div>
                    </div>
                </div>

                <!-- Multi-Currency Card -->
                <div class="card" style="box-shadow:none; border:1px solid var(--border-light);">
                    <h4 style="font-size:0.95rem; font-weight:700; margin-bottom:14px; color:var(--text-dark);">
                        <i class="fas fa-coins" style="color:#f59e0b; margin-right:6px;"></i> ສະກຸນເງິນທີ່ຮັບຊຳລະ (ທຽບເທົ່າກີບ)
                    </h4>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:#f8fafc; border-radius:8px;">
                            <span>₭ ເງິນກີບ (LAK)</span>
                            <strong id="currLakTotal">0 ₭</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:#f8fafc; border-radius:8px;">
                            <span>฿ ເງິນບາດ (THB)</span>
                            <strong id="currThbTotal">0 ₭</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:#f8fafc; border-radius:8px;">
                            <span>$ ໂດລາ (USD)</span>
                            <strong id="currUsdTotal">0 ₭</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Chart.js and SheetJS Local Bundles -->
<script src="<?= BASE_URL ?>assets/vendor/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>assets/vendor/xlsx.full.min.js"></script>

<script>
let currentPlData = null;
let trendChartInstance = null;
let categoryChartInstance = null;
let peakHoursChartInstance = null;

function formatCurrency(val) {
    return Number(val || 0).toLocaleString('en-US');
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function applyDatePreset(preset) {
    document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
    const btn = document.getElementById('btnFilter' + preset.charAt(0).toUpperCase() + preset.slice(1).replace(/_([a-z])/g, (g) => g[1].toUpperCase()));
    if (btn) btn.classList.add('active');

    const today = new Date();
    const pad = n => String(n).padStart(2, '0');
    const toYmd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    let start = toYmd(today);
    let end = toYmd(today);

    if (preset === 'today') {
        start = toYmd(today);
        end = toYmd(today);
    } else if (preset === 'yesterday') {
        const y = new Date(today);
        y.setDate(today.getDate() - 1);
        start = toYmd(y);
        end = toYmd(y);
    } else if (preset === '7days') {
        const d7 = new Date(today);
        d7.setDate(today.getDate() - 6);
        start = toYmd(d7);
        end = toYmd(today);
    } else if (preset === 'this_month') {
        start = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-01`;
        end = toYmd(today);
    } else if (preset === 'last_month') {
        const firstPrev = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const lastPrev = new Date(today.getFullYear(), today.getMonth(), 0);
        start = toYmd(firstPrev);
        end = toYmd(lastPrev);
    } else if (preset === 'this_year') {
        start = `${today.getFullYear()}-01-01`;
        end = toYmd(today);
    }

    document.getElementById('reportStartDate').value = start;
    document.getElementById('reportEndDate').value = end;
    reloadReportData();
}

async function reloadReportData() {
    const start = document.getElementById('reportStartDate').value;
    const end = document.getElementById('reportEndDate').value;

    try {
        const res = await fetchAPI(`${BASE_URL}api/profit_loss.php?start=${start}&end=${end}`);
        if (!res || !res.success) {
            showToast(res?.message || 'ເກີດຂໍ້ຜິດພາດໃນການໂຫຼດຂໍ້ມູນ', 'error');
            return;
        }

        currentPlData = res;
        renderKpis(res.kpis);
        renderCharts(res);
        renderProductTable(res.products);
        renderCategoryTable(res.categories);
        renderPaymentSummary(res.kpis);
    } catch (e) {
        console.error(e);
        showToast('ເກີດຂໍ້ຜິດພາດ: ' + e.message, 'error');
    }
}

function renderKpis(k) {
    if (!k) return;
    document.getElementById('kpiRevenue').textContent = formatCurrency(k.total_revenue) + ' ₭';
    document.getElementById('kpiBillCount').textContent = Number(k.bill_count).toLocaleString();
    document.getElementById('kpiAvgBill').textContent = formatCurrency(k.average_bill_value);
    
    document.getElementById('kpiCogs').textContent = formatCurrency(k.total_cogs) + ' ₭';
    document.getElementById('kpiItemsSold').textContent = Number(k.items_sold).toLocaleString();

    document.getElementById('kpiGrossProfit').textContent = formatCurrency(k.gross_profit) + ' ₭';
    document.getElementById('badgeGrossMargin').textContent = `${k.gross_margin}% Margin`;

    document.getElementById('kpiExpenses').textContent = formatCurrency(k.operating_expenses) + ' ₭';

    const netEl = document.getElementById('kpiNetProfit');
    netEl.textContent = formatCurrency(k.net_profit) + ' ₭';
    if (k.net_profit < 0) {
        netEl.style.color = 'var(--danger)';
    } else {
        netEl.style.color = '#6b21a8';
    }
    document.getElementById('badgeNetMargin').textContent = `${k.net_margin}% Margin`;
}

function renderCharts(data) {
    const isDark = document.body.classList.contains('dark-theme');
    const textColor = isDark ? '#e2e8f0' : '#475569';
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';

    // Chart 1: Trend Chart
    const trendCtx = document.getElementById('profitTrendChart');
    if (trendCtx) {
        if (trendChartInstance) trendChartInstance.destroy();
        const t = data.trend || { labels: [], revenue: [], cogs: [], net_profit: [] };
        trendChartInstance = new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: t.labels,
                datasets: [
                    {
                        label: 'ຍອດຂາຍ (Revenue)',
                        data: t.revenue,
                        backgroundColor: 'rgba(37, 99, 235, 0.75)',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 4,
                        order: 2
                    },
                    {
                        label: 'ຕົ້ນທຶນ (COGS)',
                        data: t.cogs,
                        backgroundColor: 'rgba(245, 158, 11, 0.75)',
                        borderColor: '#f59e0b',
                        borderWidth: 1,
                        borderRadius: 4,
                        order: 3
                    },
                    {
                        type: 'line',
                        label: 'ກຳໄລສຸດທິ (Net Profit)',
                        data: t.net_profit,
                        borderColor: '#10b981',
                        backgroundColor: '#10b981',
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.3,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: textColor, font: { size: 12 } } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.dataset.label + ': ' + formatCurrency(ctx.parsed.y) + ' ₭';
                            }
                        }
                    }
                },
                scales: {
                    x: { ticks: { color: textColor }, grid: { display: false } },
                    y: { 
                        ticks: { 
                            color: textColor,
                            callback: v => (v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v))
                        }, 
                        grid: { color: gridColor } 
                    }
                }
            }
        });
    }

    // Chart 2: Category Profit Share
    const catCtx = document.getElementById('categoryShareChart');
    if (catCtx) {
        if (categoryChartInstance) categoryChartInstance.destroy();
        const cats = data.categories || [];
        const labels = cats.map(c => c.category_name);
        const values = cats.map(c => Math.max(0, c.profit));
        const colors = [
            '#2563eb', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', 
            '#06b6d4', '#f97316', '#64748b'
        ];

        categoryChartInstance = new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: labels.length > 0 ? labels : ['ບໍ່ມີຂໍ້ມູນ'],
                datasets: [{
                    data: values.length > 0 ? values : [1],
                    backgroundColor: colors.slice(0, Math.max(1, labels.length)),
                    borderWidth: 2,
                    borderColor: isDark ? '#1e293b' : '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'right',
                        labels: { color: textColor, font: { size: 11 } } 
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const val = ctx.parsed;
                                return ctx.label + ': ' + formatCurrency(val) + ' ₭';
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }

    // Chart 3: Peak Hours Heatmap
    const peakCtx = document.getElementById('peakHoursChart');
    if (peakCtx) {
        if (peakHoursChartInstance) peakHoursChartInstance.destroy();
        const peaks = data.peak_hours || [];
        const labels = peaks.map(p => p.hour);
        const revenues = peaks.map(p => p.revenue);

        peakHoursChartInstance = new Chart(peakCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ຍອດຂາຍຕາມຊົ່ວໂມງ',
                    data: revenues,
                    backgroundColor: revenues.map(v => {
                        const max = Math.max(...revenues, 1);
                        const ratio = v / max;
                        return ratio > 0.75 ? '#059669' : (ratio > 0.4 ? '#3b82f6' : '#94a3b8');
                    }),
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => 'ຍອດຂາຍ: ' + formatCurrency(ctx.parsed.y) + ' ₭'
                        }
                    }
                },
                scales: {
                    x: { ticks: { color: textColor }, grid: { display: false } },
                    y: { 
                        ticks: { 
                            color: textColor,
                            callback: v => (v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v))
                        }, 
                        grid: { color: gridColor } 
                    }
                }
            }
        });
    }
}

function renderProductTable(products) {
    const tbody = document.getElementById('plProductTableBody');
    if (!tbody) return;

    if (!products || products.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; padding:30px; color:var(--text-light);">ບໍ່ມີລາຍການຂາຍໃນຊ່ວງເວລານີ້</td></tr>`;
        return;
    }

    tbody.innerHTML = products.map((p, idx) => {
        const isProfitable = p.total_profit > 0;
        const marginColor = p.margin_percent >= 30 ? 'badge-success' : (p.margin_percent > 15 ? 'badge-info' : 'badge-warning');

        return `
            <tr style="vertical-align: middle;">
                <td>${idx + 1}</td>
                <td>
                    <div style="font-weight:700; color:var(--text-dark);">${escapeHtml(p.product_name)}</div>
                    ${p.barcode ? `<div style="font-size:0.75rem; color:var(--text-light);"><i class="fas fa-barcode"></i> ${escapeHtml(p.barcode)}</div>` : ''}
                </td>
                <td><span class="badge badge-secondary" style="font-size:0.75rem;">${escapeHtml(p.category_name)}</span></td>
                <td style="text-align:center; font-weight:700;">${Number(p.quantity_sold).toLocaleString()} ${escapeHtml(p.unit)}</td>
                <td style="text-align:right;">${formatCurrency(p.avg_selling_price)}</td>
                <td style="text-align:right;">${formatCurrency(p.avg_cost_price)}</td>
                <td style="text-align:right; font-weight:600;">${formatCurrency(p.total_revenue)} ₭</td>
                <td style="text-align:right; color:#b76e00;">${formatCurrency(p.total_cogs)} ₭</td>
                <td style="text-align:right; font-weight:800; color:${isProfitable ? '#059669' : 'var(--danger)'};">
                    ${formatCurrency(p.total_profit)} ₭
                </td>
                <td style="text-align:center;">
                    <span class="badge ${marginColor}" style="font-size:0.75rem; font-weight:700;">
                        ${p.margin_percent}%
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}

function renderCategoryTable(categories) {
    const tbody = document.getElementById('plCategoryTableBody');
    if (!tbody) return;

    if (!categories || categories.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-light);">ບໍ່ມີຂໍ້ມູນ</td></tr>`;
        return;
    }

    tbody.innerHTML = categories.map((c, idx) => {
        return `
            <tr style="vertical-align: middle;">
                <td>${idx + 1}</td>
                <td><strong>${escapeHtml(c.category_name)}</strong></td>
                <td style="text-align:center; font-weight:700;">${Number(c.items_sold).toLocaleString()}</td>
                <td style="text-align:right; font-weight:600;">${formatCurrency(c.revenue)} ₭</td>
                <td style="text-align:right; color:#b76e00;">${formatCurrency(c.cogs)} ₭</td>
                <td style="text-align:right; font-weight:800; color:#059669;">${formatCurrency(c.profit)} ₭</td>
                <td style="text-align:center;"><span class="badge badge-success">${c.margin_percent}%</span></td>
                <td style="text-align:center;">
                    <div style="display:flex; align-items:center; gap:6px; justify-content:center;">
                        <div style="flex:1; max-width:80px; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                            <div style="width:${c.revenue_share_percent}%; height:100%; background:var(--primary);"></div>
                        </div>
                        <span style="font-size:0.8rem; font-weight:600;">${c.revenue_share_percent}%</span>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderPaymentSummary(k) {
    if (!k) return;
    document.getElementById('payCashTotal').textContent = formatCurrency(k.cash_sales) + ' ₭';
    document.getElementById('payTransferTotal').textContent = formatCurrency(k.transfer_sales) + ' ₭';

    const curr = k.currencies || {};
    document.getElementById('currLakTotal').textContent = formatCurrency(curr.lak) + ' ₭';
    document.getElementById('currThbTotal').textContent = formatCurrency(curr.thb) + ' ₭';
    document.getElementById('currUsdTotal').textContent = formatCurrency(curr.usd) + ' ₭';
}

function switchPlTab(tabId) {
    const tabs = ['products', 'categories', 'payments'];
    tabs.forEach(t => {
        const btn = document.getElementById('plTab' + t.charAt(0).toUpperCase() + t.slice(1));
        const c = document.getElementById('plContainer' + t.charAt(0).toUpperCase() + t.slice(1));
        if (btn) btn.classList.remove('active');
        if (c) c.style.display = 'none';
    });

    const activeBtn = document.getElementById('plTab' + tabId.charAt(0).toUpperCase() + tabId.slice(1));
    const activeC = document.getElementById('plContainer' + tabId.charAt(0).toUpperCase() + tabId.slice(1));
    if (activeBtn) activeBtn.classList.add('active');
    if (activeC) activeC.style.display = 'block';
}

async function exportProfitLossExcel() {
    if (typeof XLSX === 'undefined') {
        showToast('ບໍ່ພົບໂປຣແກຣມສົ່ງອອກ Excel', 'warning');
        return;
    }
    if (!currentPlData) {
        showToast('ບໍ່ມີຂໍ້ມູນລາຍງານ', 'warning');
        return;
    }

    try {
        const k = currentPlData.kpis || {};
        const wb = XLSX.utils.book_new();

        // Sheet 1: Executive Summary Statement
        const summaryData = [
            ['ລາຍງານກຳໄລ-ຂາດທຶນ (Profit & Loss Statement)'],
            ['ຊ່ວງເວລາ:', `${currentPlData.start_date} ເຖິງ ${currentPlData.end_date}`],
            [],
            ['ລາຍການ', 'ມູນຄ່າ (ກີບ)', '% ຕໍ່ຍອດຂາຍ'],
            ['1. ຍອດຂາຍລວມ (Gross Revenue)', k.total_revenue, '100%'],
            ['2. ຕົ້ນທຶນສິນຄ້າຂາຍ (COGS)', k.total_cogs, `${(100 - k.gross_margin).toFixed(1)}%`],
            ['3. ກຳໄລຂັ້ນຕົ້ນ (Gross Profit)', k.gross_profit, `${k.gross_margin}%`],
            ['4. ຄ່າໃຊ້ຈ່າຍບໍລິຫານຮ້ານ (OpEx)', k.operating_expenses, `${((k.operating_expenses / (k.total_revenue || 1)) * 100).toFixed(1)}%`],
            ['5. ກຳໄລສຸດທິ (Net Profit)', k.net_profit, `${k.net_margin}%`],
            [],
            ['ສະຖິຕິເພີ່ມເຕີມ:'],
            ['ຈຳນວນໃບບິນທັງໝົດ:', k.bill_count],
            ['ຈຳນວນຢາທີ່ຂາຍທັງໝົດ:', k.items_sold],
            ['ຍອດຂາຍສະເລ່ຍຕໍ່ບິນ:', k.average_bill_value],
            ['ຍອດຂາຍເງິນສົດ:', k.cash_sales],
            ['ຍອດຂາຍເງິນໂອນ:', k.transfer_sales]
        ];
        const ws1 = XLSX.utils.aoa_to_sheet(summaryData);
        ws1['!cols'] = [{ wch: 35 }, { wch: 22 }, { wch: 18 }];
        XLSX.utils.book_append_sheet(wb, ws1, 'P&L Statement');

        // Sheet 2: Profit by Medicine
        const prodData = [
            ['ບາໂຄດ', 'ຊື່ສິນຄ້າ / ຢາ', 'ໝວດໝູ່', 'ຈຳນວນຂາຍ', 'ຫົວໜ່ວຍ', 'ລາຄາຂາຍ', 'ລາຄາທຶນ', 'ຍອດຂາຍລວມ', 'ຕົ້ນທຶນລວມ', 'ກຳໄລລວມ', '% Margin']
        ];
        (currentPlData.products || []).forEach(p => {
            prodData.push([
                p.barcode || '',
                p.product_name,
                p.category_name,
                p.quantity_sold,
                p.unit,
                p.avg_selling_price,
                p.avg_cost_price,
                p.total_revenue,
                p.total_cogs,
                p.total_profit,
                `${p.margin_percent}%`
            ]);
        });
        const ws2 = XLSX.utils.aoa_to_sheet(prodData);
        ws2['!cols'] = [
            { wch: 18 }, { wch: 34 }, { wch: 18 }, { wch: 12 }, { wch: 10 },
            { wch: 14 }, { wch: 14 }, { wch: 16 }, { wch: 16 }, { wch: 16 }, { wch: 12 }
        ];
        XLSX.utils.book_append_sheet(wb, ws2, 'Profit by Medicine');

        const filename = `profit_loss_${currentPlData.start_date}_to_${currentPlData.end_date}.xlsx`;
        XLSX.writeFile(wb, filename);
        showToast(`ສົ່ງອອກ ${filename} ສຳເລັດແລ້ວ!`, 'success');
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດ: ' + e.message, 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    reloadReportData();
});
</script>

<?php include 'includes/footer.php'; ?>
