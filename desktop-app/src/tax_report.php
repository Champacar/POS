<?php
/**
 * POSPharma - Advanced Financial Statements & Tax Report
 * ລາຍງານອາກອນມູນຄ່າເພີ່ມ, ໃບກຳກັບພາສີ ແລະ ງົບກະແສເງິນສົດ 100% Offline
 */

require_once 'includes/functions.php';
requireLogin();
requireRoles(['admin', 'manager']);

$pageTitle = 'ລາຍງານອາກອນ & ການເງິນ';

$startDate = isset($_GET['start']) ? trim($_GET['start']) : date('Y-m-01');
$endDate = isset($_GET['end']) ? trim($_GET['end']) : date('Y-m-d');

$db = getDB();
$settings = getShopSettings();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header" style="flex-wrap:wrap; gap:16px; align-items:center; justify-content:space-between;">
        <div>
            <h1 class="page-title" style="display:flex; align-items:center; gap:10px;">
                <i class="fas fa-file-invoice-dollar" style="color:var(--primary);"></i> ລາຍງານອາກອນ & ງົບການເງິນຂັ້ນສູງ
            </h1>
            <p style="font-size:0.88rem; color:var(--text-medium); margin-top:3px; margin-bottom:0;">
                ຄຸ້ມຄອງໃບກຳກັບພາສີ, ສະຫຼຸບອາກອນມູນຄ່າເພີ່ມ (VAT) ສຳລັບນຳສົ່ງສ່ວຍສາອາກອນ ແລະ ງົບກະແສເງິນສົດ (Cash Flow)
            </p>
        </div>

        <!-- Date Filters Toolbar -->
        <div class="date-filters" style="display:flex; flex-wrap:wrap; align-items:center; gap:8px;">
            <div class="btn-group" style="display:flex; gap:4px;">
                <button type="button" class="btn btn-sm btn-filter" onclick="applyPreset('today')" id="btnFilterToday">ມື້ນີ້</button>
                <button type="button" class="btn btn-sm btn-filter" onclick="applyPreset('7days')" id="btnFilter7Days">7 ມື້ຜ່ານມາ</button>
                <button type="button" class="btn btn-sm btn-filter active" onclick="applyPreset('this_month')" id="btnFilterThisMonth">ເດືອນນີ້</button>
                <button type="button" class="btn btn-sm btn-filter" onclick="applyPreset('last_month')" id="btnFilterLastMonth">ເດືອນກ່ອນ</button>
                <button type="button" class="btn btn-sm btn-filter" onclick="applyPreset('this_year')" id="btnFilterThisYear">ປີນີ້</button>
            </div>

            <div style="display:flex; align-items:center; gap:6px;">
                <input type="date" id="dateStart" class="form-control form-control-sm" value="<?= $startDate ?>" style="width:135px;" onchange="loadReportData()">
                <span style="color:var(--text-light);">-</span>
                <input type="date" id="dateEnd" class="form-control form-control-sm" value="<?= $endDate ?>" style="width:135px;" onchange="loadReportData()">
            </div>

            <div style="display:flex; gap:6px;">
                <button type="button" class="btn btn-sm btn-white" onclick="printTaxFilingReport()" title="ພິມລາຍງານນຳສົ່ງສ່ວຍສາອາກອນ">
                    <i class="fas fa-print"></i> ພິມລາຍງານ
                </button>
                <button type="button" class="btn btn-sm btn-outline" onclick="exportTaxInvoicesCSV()" title="ສົ່ງອອກ CSV">
                    <i class="fas fa-file-csv" style="color:var(--success);"></i> CSV
                </button>
            </div>
        </div>
    </div>

    <div class="content-area">
        <!-- Navigation Tabs -->
        <div class="tabs" style="display:flex; gap:8px; border-bottom:2px solid var(--border-color); margin-bottom:20px;">
            <button type="button" class="tab-btn active" id="tabBtnVat" onclick="switchReportTab('vat')" style="padding:10px 18px; font-weight:600; border:none; background:none; border-bottom:3px solid var(--primary); cursor:pointer; color:var(--primary); display:flex; align-items:center; gap:8px;">
                <i class="fas fa-file-invoice"></i> ໃບກຳກັບພາສີ & ອາກອນ (VAT)
            </button>
            <button type="button" class="tab-btn" id="tabBtnCashFlow" onclick="switchReportTab('cash_flow')" style="padding:10px 18px; font-weight:600; border:none; background:none; cursor:pointer; color:var(--text-medium); display:flex; align-items:center; gap:8px;">
                <i class="fas fa-money-check-alt"></i> ງົບກະແສເງິນສົດ (Cash Flow)
            </button>
            <button type="button" class="tab-btn" id="tabBtnSettings" onclick="switchReportTab('settings')" style="padding:10px 18px; font-weight:600; border:none; background:none; cursor:pointer; color:var(--text-medium); display:flex; align-items:center; gap:8px;">
                <i class="fas fa-cog"></i> ຕັ້ງຄ່າຂໍ້ມູນອາກອນ (Tax Settings)
            </button>
        </div>

        <!-- ============================================ -->
        <!-- TAB 1: VAT & TAX INVOICES                    -->
        <!-- ============================================ -->
        <div id="tabContentVat">
            <!-- 4 Metric Cards -->
            <div class="metrics-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:20px;">
                <div class="card" style="padding:16px; border-left:4px solid var(--primary);">
                    <div style="font-size:0.8rem; color:var(--text-light); text-transform:uppercase; font-weight:600; margin-bottom:4px;">ຍອດຂາຍລວມທັງໝົດ</div>
                    <div id="kpiTotalSales" style="font-size:1.5rem; font-weight:800; color:var(--text-dark);">0 ₭</div>
                    <div style="font-size:0.75rem; color:var(--text-medium); margin-top:4px;">ລວມທັງບິນທຳມະດາ ແລະ ໃບກຳກັບ</div>
                </div>

                <div class="card" style="padding:16px; border-left:4px solid var(--info);">
                    <div style="font-size:0.8rem; color:var(--text-light); text-transform:uppercase; font-weight:600; margin-bottom:4px;">ມູນຄ່າກ່ອນອາກອນ (Taxable Base)</div>
                    <div id="kpiTaxableSubtotal" style="font-size:1.5rem; font-weight:800; color:var(--info);">0 ₭</div>
                    <div style="font-size:0.75rem; color:var(--text-medium); margin-top:4px;">ມູນຄ່າສິນຄ້າຕົວຈິງກ່ອນບວກ VAT</div>
                </div>

                <div class="card" style="padding:16px; border-left:4px solid var(--danger);">
                    <div style="font-size:0.8rem; color:var(--text-light); text-transform:uppercase; font-weight:600; margin-bottom:4px;">ອາກອນມູນຄ່າເພີ່ມ (Output VAT)</div>
                    <div id="kpiTotalVat" style="font-size:1.5rem; font-weight:800; color:var(--danger);">0 ₭</div>
                    <div style="font-size:0.75rem; color:var(--text-medium); margin-top:4px;">ອາກອນຂາຍທີ່ຕ້ອງນຳສົ່ງສ່ວຍສາອາກອນ</div>
                </div>

                <div class="card" style="padding:16px; border-left:4px solid var(--success);">
                    <div style="font-size:0.8rem; color:var(--text-light); text-transform:uppercase; font-weight:600; margin-bottom:4px;">ຈຳນວນໃບກຳກັບທີ່ອອກ</div>
                    <div id="kpiInvoiceCount" style="font-size:1.5rem; font-weight:800; color:var(--success);">0 ສະບັບ</div>
                    <div style="font-size:0.75rem; color:var(--text-medium); margin-top:4px;">ລວມມູນຄ່າ: <span id="kpiInvoiceTotal">0 ₭</span></div>
                </div>
            </div>

            <!-- Tax Invoices Table Card -->
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px;">
                    <h3 style="margin:0; font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-list text-primary"></i> ບັນຊີໃບກຳກັບພາສີອາກອນມູນຄ່າເພີ່ມ (Tax Invoices Journal)
                    </h3>
                    <div style="font-size:0.85rem; color:var(--text-light);">
                        ສະແດງສະເພາະບິນທີ່ອອກໃບກຳກັບພາສີເປັນທາງການ
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table" id="taxInvoicesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ວັນທີ</th>
                                <th>ເລກທີໃບກຳກັບ</th>
                                <th>ເລກບິນອ້າງອີງ</th>
                                <th>ຊື່ບໍລິສັດ / ລູກຄ້າ</th>
                                <th>ເລກ TIN ລູກຄ້າ</th>
                                <th style="text-align:right;">ມູນຄ່າກ່ອນ VAT</th>
                                <th style="text-align:right;">ອາກອນ (VAT)</th>
                                <th style="text-align:right;">ຍອດລວມທັງໝົດ</th>
                                <th style="text-align:center;">ຈັດການ</th>
                            </tr>
                        </thead>
                        <tbody id="taxInvoicesTbody">
                            <tr>
                                <td colspan="10" style="text-align:center; padding:40px; color:var(--text-light);">
                                    <i class="fas fa-spinner fa-spin" style="font-size:1.8rem; margin-bottom:10px; display:block;"></i>
                                    ກຳລັງໂຫຼດຂໍ້ມູນ...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB 2: CASH FLOW STATEMENT                   -->
        <!-- ============================================ -->
        <div id="tabContentCashFlow" style="display:none;">
            <div class="card" style="max-width:900px; margin:0 auto;">
                <div class="card-header" style="background:#f8fafc; border-bottom:1px solid var(--border-color); padding:16px 20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h3 style="margin:0; font-size:1.15rem; color:var(--text-dark);">
                                <i class="fas fa-balance-scale text-primary"></i> ງົບກະແສເງິນສົດ (Cash Flow Statement)
                            </h3>
                            <div style="font-size:0.8rem; color:var(--text-medium); margin-top:3px;">
                                ຊ່ວງເວລາ: <span id="cfPeriodDisplay"></span>
                            </div>
                        </div>
                        <div id="cfNetBadge"></div>
                    </div>
                </div>

                <div class="card-body" style="padding:24px;">
                    <!-- 1. Operating Inflows -->
                    <div style="margin-bottom:24px;">
                        <h4 style="font-size:0.95rem; text-transform:uppercase; color:#0284c7; font-weight:700; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-arrow-circle-down text-success"></i> 1. ກະແສເງິນສົດຮັບຈາກການດຳເນີນງານ (Operating Inflows)
                        </h4>
                        <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:8px 12px; color:var(--text-dark);">ເງິນສົດຮັບຈາກການຂາຍ (Cash Sales)</td>
                                <td style="padding:8px 12px; text-align:right; font-weight:600; color:#16a34a;" id="cfCashSales">0 ₭</td>
                            </tr>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:8px 12px; color:var(--text-dark);">ເງິນໂອນ / BCEL One ຮັບຈາກການຂາຍ (Bank / QR Transfer)</td>
                                <td style="padding:8px 12px; text-align:right; font-weight:600; color:#16a34a;" id="cfTransferSales">0 ₭</td>
                            </tr>
                            <tr style="background:#f0fdf4; font-weight:700;">
                                <td style="padding:10px 12px; color:#166534;">ລວມກະແສເງິນສົດຮັບທັງໝົດ (Total Cash Inflow)</td>
                                <td style="padding:10px 12px; text-align:right; font-size:1.05rem; color:#166534;" id="cfTotalInflow">0 ₭</td>
                            </tr>
                        </table>
                    </div>

                    <!-- 2. Operating Outflows -->
                    <div style="margin-bottom:24px;">
                        <h4 style="font-size:0.95rem; text-transform:uppercase; color:#dc2626; font-weight:700; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-arrow-circle-up text-danger"></i> 2. ກະແສເງິນສົດຈ່າຍອອກ (Operating Outflows)
                        </h4>
                        <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:8px 12px; color:var(--text-dark);">ຊື້ຢາ ແລະ ສິນຄ້າເຂົ້າສາງ (Stock Purchases / PO Received)</td>
                                <td style="padding:8px 12px; text-align:right; font-weight:600; color:#dc2626;" id="cfStockPurchase">0 ₭</td>
                            </tr>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:8px 12px; color:var(--text-dark);">ລາຍຈ່າຍບໍລິຫານ ແລະ ປະຈຳຮ້ານ (General & Admin Expenses)</td>
                                <td style="padding:8px 12px; text-align:right; font-weight:600; color:#dc2626;" id="cfGeneralExpense">0 ₭</td>
                            </tr>
                            <tr style="background:#fef2f2; font-weight:700;">
                                <td style="padding:10px 12px; color:#991b1b;">ລວມກະແສເງິນສົດຈ່າຍທັງໝົດ (Total Cash Outflow)</td>
                                <td style="padding:10px 12px; text-align:right; font-size:1.05rem; color:#991b1b;" id="cfTotalOutflow">0 ₭</td>
                            </tr>
                        </table>
                    </div>

                    <!-- 3. Net Cash Flow -->
                    <div style="background:#f8fafc; border:2px solid var(--border-color); border-radius:12px; padding:18px 24px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:1.1rem; font-weight:800; color:var(--text-dark);">
                                3. ກະແສເງິນສົດສຸດທິ (Net Cash Flow)
                            </div>
                            <div style="font-size:0.8rem; color:var(--text-light); margin-top:2px;">
                                ຄຳນວນຈາກ: ເງິນສົດຮັບລວມ - ເງິນສົດຈ່າຍລວມ
                            </div>
                        </div>
                        <div id="cfNetCashFlow" style="font-size:1.6rem; font-weight:900;">
                            0 ₭
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB 3: TAX SETTINGS                          -->
        <!-- ============================================ -->
        <div id="tabContentSettings" style="display:none;">
            <div class="card" style="max-width:700px; margin:0 auto;">
                <div class="card-header" style="padding:16px 20px;">
                    <h3 style="margin:0; font-size:1.15rem;">
                        <i class="fas fa-cog text-primary"></i> ຕັ້ງຄ່າຂໍ້ມູນອາກອນຮ້ານຄ້າ (Shop Tax Profile)
                    </h3>
                </div>
                <div class="card-body" style="padding:24px;">
                    <form id="taxSettingsForm" onsubmit="saveTaxSettings(event)">
                        <div class="form-group">
                            <label class="form-label">ເລກປະຈຳຕົວຜູ້ເສຍອາກອນຂອງຮ້ານ (TIN) *</label>
                            <input type="text" class="form-control" id="settTaxId" value="<?= htmlspecialchars($settings['tax_id'] ?? '') ?>" placeholder="01-xxxxxxxx" required>
                            <small class="text-muted">ເລກ TIN ທີ່ໄດ້ຮັບຈາກກົມສ່ວຍສາອາກອນ (ຈະພິມລົງໃນຫົວໃບກຳກັບພາສີ)</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ຊື່ວິສາຫະກິດ / ບໍລິສັດຕາມທະບຽນອາກອນ *</label>
                            <input type="text" class="form-control" id="settCompanyName" value="<?= htmlspecialchars($settings['company_name'] ?? ($settings['shop_name'] ?? '')) ?>" placeholder="ບໍລິສັດ ... ຈຳກັດ" required>
                            <small class="text-muted">ຊື່ທາງການສຳລັບພິມຫົວໃບກຳກັບພາສີ</small>
                        </div>

                        <div class="form-row-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label class="form-label">ຕົວຫຍໍ້ເລກທີໃບກຳກັບ (Prefix)</label>
                                <input type="text" class="form-control" id="settTaxPrefix" value="<?= htmlspecialchars($settings['tax_invoice_prefix'] ?? 'TAX-') ?>" placeholder="TAX-">
                                <small class="text-muted">ຕົວຢ່າງ: TAX-20261109-0001</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">ອັດຕາອາກອນມູນຄ່າເພີ່ມເລີ່ມຕົ້ນ (%)</label>
                                <select class="form-control" id="settTaxRate">
                                    <option value="7" <?= (float)($settings['tax_rate'] ?? 7) == 7 ? 'selected' : '' ?>>7% (ມາດຕະຖານ ສປປ ລາວ)</option>
                                    <option value="10" <?= (float)($settings['tax_rate'] ?? 7) == 10 ? 'selected' : '' ?>>10%</option>
                                    <option value="0" <?= (float)($settings['tax_rate'] ?? 7) == 0 ? 'selected' : '' ?>>0% (ຍົກເວັ້ນອາກອນ)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ວິທີການຄິດໄລ່ອາກອນມູນຄ່າເພີ່ມ</label>
                            <select class="form-control" id="settTaxType">
                                <option value="excluded" <?= ($settings['tax_type'] ?? 'excluded') === 'excluded' ? 'selected' : '' ?>>ລາຄາສິນຄ້າຍັງບໍ່ລວມອາກອນ (Add-on / Excluded)</option>
                                <option value="included" <?= ($settings['tax_type'] ?? '') === 'included' ? 'selected' : '' ?>>ລາຄາສິນຄ້າລວມອາກອນແລ້ວ (VAT Included)</option>
                                <option value="none" <?= ($settings['tax_type'] ?? '') === 'none' ? 'selected' : '' ?>>ບໍ່ຄິດໄລ່ອາກອນ (Disabled)</option>
                            </select>
                        </div>

                        <div style="margin-top:24px; text-align:right;">
                            <button type="submit" class="btn btn-primary" id="btnSaveTaxSettings">
                                <i class="fas fa-save"></i> ບັນທຶກການຕັ້ງຄ່າ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
let currentTaxData = null;

function applyPreset(preset) {
    const today = new Date();
    let start = new Date();
    let end = new Date();

    document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));

    if (preset === 'today') {
        document.getElementById('btnFilterToday')?.classList.add('active');
    } else if (preset === '7days') {
        start.setDate(today.getDate() - 6);
        document.getElementById('btnFilter7Days')?.classList.add('active');
    } else if (preset === 'this_month') {
        start = new Date(today.getFullYear(), today.getMonth(), 1);
        document.getElementById('btnFilterThisMonth')?.classList.add('active');
    } else if (preset === 'last_month') {
        start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        end = new Date(today.getFullYear(), today.getMonth(), 0);
        document.getElementById('btnFilterLastMonth')?.classList.add('active');
    } else if (preset === 'this_year') {
        start = new Date(today.getFullYear(), 0, 1);
        document.getElementById('btnFilterThisYear')?.classList.add('active');
    }

    const fmt = d => d.toISOString().split('T')[0];
    document.getElementById('dateStart').value = fmt(start);
    document.getElementById('dateEnd').value = fmt(end);
    loadReportData();
}

function switchReportTab(tab) {
    ['vat', 'cash_flow', 'settings'].forEach(t => {
        const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1).replace('_', ''));
        const content = document.getElementById('tabContent' + t.charAt(0).toUpperCase() + t.slice(1).replace('_', ''));
        if (btn) {
            btn.classList.remove('active');
            btn.style.borderBottom = 'none';
            btn.style.color = 'var(--text-medium)';
        }
        if (content) content.style.display = 'none';
    });

    if (tab === 'vat') {
        document.getElementById('tabBtnVat').classList.add('active');
        document.getElementById('tabBtnVat').style.borderBottom = '3px solid var(--primary)';
        document.getElementById('tabBtnVat').style.color = 'var(--primary)';
        document.getElementById('tabContentVat').style.display = 'block';
    } else if (tab === 'cash_flow') {
        document.getElementById('tabBtnCashFlow').classList.add('active');
        document.getElementById('tabBtnCashFlow').style.borderBottom = '3px solid var(--primary)';
        document.getElementById('tabBtnCashFlow').style.color = 'var(--primary)';
        document.getElementById('tabContentCashFlow').style.display = 'block';
    } else if (tab === 'settings') {
        document.getElementById('tabBtnSettings').classList.add('active');
        document.getElementById('tabBtnSettings').style.borderBottom = '3px solid var(--primary)';
        document.getElementById('tabBtnSettings').style.color = 'var(--primary)';
        document.getElementById('tabContentSettings').style.display = 'block';
    }
}

async function loadReportData() {
    const start = document.getElementById('dateStart').value;
    const end = document.getElementById('dateEnd').value;

    try {
        const res = await fetchAPI(`${BASE_URL}api/tax_report.php?start=${start}&end=${end}`);
        if (res.success) {
            currentTaxData = res;
            renderVatTab(res);
            renderCashFlowTab(res);
        } else {
            showToast(res.message || 'ບໍ່ສາມາດໂຫຼດຂໍ້ມູນໄດ້', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່', 'error');
    }
}

function renderVatTab(data) {
    const s = data.vat_stats;
    document.getElementById('kpiTotalSales').textContent = formatCurrency(s.total_sales) + ' ₭';
    document.getElementById('kpiTaxableSubtotal').textContent = formatCurrency(s.net_sales) + ' ₭';
    document.getElementById('kpiTotalVat').textContent = formatCurrency(s.total_vat) + ' ₭';
    document.getElementById('kpiInvoiceCount').textContent = parseInt(s.tax_invoice_count) + ' ສະບັບ';
    document.getElementById('kpiInvoiceTotal').textContent = formatCurrency(s.tax_invoice_total) + ' ₭';

    const tbody = document.getElementById('taxInvoicesTbody');
    const invoices = data.invoices || [];

    if (invoices.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" style="text-align:center; padding:50px; color:var(--text-light);">
                    <i class="fas fa-file-invoice" style="font-size:2.5rem; margin-bottom:10px; display:block;"></i>
                    ບໍ່ພົບລາຍການໃບກຳກັບພາສີໃນຊ່ວງເວລານີ້
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = invoices.map((inv, idx) => {
        const subtotal = (parseFloat(inv.subtotal) || 0) - (parseFloat(inv.discount_amount) || 0);
        return `
            <tr>
                <td>${idx + 1}</td>
                <td>${inv.created_at ? inv.created_at.substring(0, 16) : '-'}</td>
                <td>
                    <strong style="color:var(--primary); font-family:monospace;">${inv.tax_invoice_number || '-'}</strong>
                </td>
                <td style="font-family:monospace;">${inv.invoice_number}</td>
                <td>
                    <strong>${inv.customer_company || inv.customer_name || 'ລູກຄ້າທົ່ວໄປ'}</strong>
                </td>
                <td style="font-family:monospace; font-weight:600; color:#0369a1;">
                    ${inv.customer_tax_id || '-'}
                </td>
                <td style="text-align:right;">${formatCurrency(subtotal)} ₭</td>
                <td style="text-align:right; font-weight:700; color:var(--danger);">${formatCurrency(inv.tax_amount)} ₭</td>
                <td style="text-align:right; font-weight:700;">${formatCurrency(inv.total_amount)} ₭</td>
                <td style="text-align:center;">
                    <a href="tax_invoice.php?id=${inv.id}" target="_blank" class="btn btn-sm btn-white" title="ເບິ່ງ/ພິມໃບກຳກັບ A4/A5">
                        <i class="fas fa-print" style="color:var(--primary);"></i> ພິມ
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}

function renderCashFlowTab(data) {
    const cf = data.cash_flow;
    const start = data.period.start;
    const end = data.period.end;

    document.getElementById('cfPeriodDisplay').textContent = `${start} ຫາ ${end}`;
    document.getElementById('cfCashSales').textContent = formatCurrency(cf.cash_sales) + ' ₭';
    document.getElementById('cfTransferSales').textContent = formatCurrency(cf.transfer_sales) + ' ₭';
    document.getElementById('cfTotalInflow').textContent = formatCurrency(cf.total_inflow) + ' ₭';

    document.getElementById('cfStockPurchase').textContent = formatCurrency(cf.stock_purchase) + ' ₭';
    document.getElementById('cfGeneralExpense').textContent = formatCurrency(cf.general_expense) + ' ₭';
    document.getElementById('cfTotalOutflow').textContent = formatCurrency(cf.total_outflow) + ' ₭';

    const net = cf.net_cash_flow;
    const netEl = document.getElementById('cfNetCashFlow');
    const badgeEl = document.getElementById('cfNetBadge');

    if (net >= 0) {
        netEl.style.color = '#16a34a';
        netEl.textContent = '+' + formatCurrency(net) + ' ₭';
        badgeEl.innerHTML = '<span class="badge badge-success" style="font-size:0.85rem;"><i class="fas fa-arrow-up"></i> ກະແສເງິນສົດເປັນບວກ</span>';
    } else {
        netEl.style.color = '#dc2626';
        netEl.textContent = formatCurrency(net) + ' ₭';
        badgeEl.innerHTML = '<span class="badge badge-danger" style="font-size:0.85rem;"><i class="fas fa-arrow-down"></i> ກະແສເງິນສົດຕິດລົບ</span>';
    }
}

async function saveTaxSettings(e) {
    e.preventDefault();
    const taxId = document.getElementById('settTaxId').value.trim();
    const company = document.getElementById('settCompanyName').value.trim();
    const prefix = document.getElementById('settTaxPrefix').value.trim();
    const rate = parseFloat(document.getElementById('settTaxRate').value) || 7;
    const type = document.getElementById('settTaxType').value;

    const btn = document.getElementById('btnSaveTaxSettings');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...';

    try {
        const res = await fetchAPI(`${BASE_URL}api/tax_report.php`, {
            method: 'POST',
            body: JSON.stringify({
                action: 'save_tax_settings',
                tax_id: taxId,
                company_name: company,
                tax_invoice_prefix: prefix,
                tax_rate: rate,
                tax_type: type
            })
        });

        if (res.success) {
            showToast('ບັນທຶກຂໍ້ມູນອາກອນສຳເລັດ!', 'success');
        } else {
            showToast(res.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (err) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການບັນທຶກ', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
}

function exportTaxInvoicesCSV() {
    if (!currentTaxData || !currentTaxData.invoices || currentTaxData.invoices.length === 0) {
        showToast('ບໍ່ມີຂໍ້ມູນໃບກຳກັບພາສີສຳລັບສົ່ງອອກ', 'warning');
        return;
    }

    const headers = ['ລຳດັບ', 'ວັນທີ', 'ເລກທີໃບກຳກັບ', 'ເລກບິນອ້າງອີງ', 'ຊື່ບໍລິສັດ/ລູກຄ້າ', 'TIN', 'ມູນຄ່າກ່ອນVAT', 'VAT', 'ຍອດລວມ'];
    const rows = currentTaxData.invoices.map((inv, idx) => {
        const subtotal = (parseFloat(inv.subtotal) || 0) - (parseFloat(inv.discount_amount) || 0);
        return [
            idx + 1,
            `"${inv.created_at || ''}"`,
            `"${inv.tax_invoice_number || ''}"`,
            `"${inv.invoice_number || ''}"`,
            `"${(inv.customer_company || inv.customer_name || '').replace(/"/g, '""')}"`,
            `"${inv.customer_tax_id || ''}"`,
            subtotal,
            inv.tax_amount,
            inv.total_amount
        ];
    });

    const csvContent = '\uFEFF' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `tax_invoices_${document.getElementById('dateStart').value}_to_${document.getElementById('dateEnd').value}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    showToast('ສົ່ງອອກ CSV ສຳເລັດ!', 'success');
}

function printTaxFilingReport() {
    window.print();
}

// Initial load
document.addEventListener('DOMContentLoaded', () => {
    loadReportData();
});
</script>

<?php include 'includes/footer.php'; ?>
