<?php
/**
 * ປະຫວັດການຂາຍ - Sales History
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();

$settings = null;
try {
    $settings = getShopSettings();
} catch (Exception $e) {
    // Ignore
}

$pageTitle = 'ປະຫວັດການຂາຍ';

$startDate = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$endDate = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'history';

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $db = getDB();
        $whereStatus = ($tab === 'deleted') ? "s.payment_status = 'cancelled'" : "s.payment_status != 'cancelled'";
        if (!empty($search)) {
            $sql = "SELECT s.invoice_number, s.customer_name, s.subtotal, s.discount_amount, s.tax_amount, s.total_amount, s.payment_method, s.payment_status, s.created_at, u.full_name as cashier FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE {$whereStatus} AND (s.invoice_number LIKE ? OR s.customer_name LIKE ?) ORDER BY s.created_at DESC";
            $params = ["%$search%", "%$search%"];
        } else {
            $sql = "SELECT s.invoice_number, s.customer_name, s.subtotal, s.discount_amount, s.tax_amount, s.total_amount, s.payment_method, s.payment_status, s.created_at, u.full_name as cashier FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE {$whereStatus} AND DATE(s.created_at) BETWEEN ? AND ? ORDER BY s.created_at DESC";
            $params = [$startDate, $endDate];
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        $csvHeaders = ['ເລກບິນ', 'ລູກຄ້າ', 'ຍອດລວມ', 'ສ່ວນຫຼຸດ', 'ອາກອນ', 'ຍອດສຸດທິ', 'ການຊຳລະ', 'ສະຖານະ', 'ພະນັກງານ', 'ວັນທີ'];
        $csvRows = array_map(function($r) {
            return [$r['invoice_number'], $r['customer_name'] ?: '-', $r['subtotal'], $r['discount_amount'], $r['tax_amount'], $r['total_amount'], getPaymentMethodLabel($r['payment_method']), getPaymentStatusLabel($r['payment_status']), $r['cashier'] ?: '-', $r['created_at']];
        }, $rows);
        
        exportCSV('sales_' . $startDate . '_' . $endDate . '.csv', $csvHeaders, $csvRows);
    } catch (Exception $e) {
        // Fall through to normal page render
    }
}

try {
    $db = getDB();
    
    $whereStatus = ($tab === 'deleted') ? "s.payment_status = 'cancelled'" : "s.payment_status != 'cancelled'";
    
    if (!empty($search)) {
        // When searching (e.g. by QR scanner or keyword), search across all dates
        $sql = "SELECT s.*, u.full_name as cashier 
                FROM sales s 
                LEFT JOIN users u ON s.user_id = u.id 
                WHERE {$whereStatus} AND (s.invoice_number LIKE ? OR s.customer_name LIKE ?)";
        $params = ["%$search%", "%$search%"];
    } else {
        $sql = "SELECT s.*, u.full_name as cashier 
                FROM sales s 
                LEFT JOIN users u ON s.user_id = u.id 
                WHERE {$whereStatus} AND DATE(s.created_at) BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
    }
    
    $sql .= " ORDER BY s.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();
} catch (Exception $e) {
    $sales = [];
}

$extraScripts = ['medicine_label.js'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">ປະຫວັດການຂາຍ</h1>
        <div class="date-filters">
            <button class="btn-filter <?= ($startDate === date('Y-m-d') && $endDate === date('Y-m-d')) ? 'active' : '' ?>" data-filter="today" onclick="setDateFilter('today')">ມື້ນີ້</button>
            <button class="btn-filter <?= ($startDate === date('Y-m-01')) ? 'active' : '' ?>" data-filter="month" onclick="setDateFilter('month')">ເດືອນນີ້</button>
            <input type="date" class="date-input" id="startDate" value="<?= $startDate ?>" onchange="onDateChange()">
            <span class="date-separator">-</span>
            <input type="date" class="date-input" id="endDate" value="<?= $endDate ?>" onchange="onDateChange()">
        </div>
    </div>

    <div class="content-area">
        <!-- Toolbar -->
        <div class="toolbar">
            <div class="toolbar-left" style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="?tab=history&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn <?= $tab !== 'deleted' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
                    <i class="fas fa-list"></i> ປະຫວັດການຂາຍ
                </a>
                <a href="?tab=deleted&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn <?= $tab === 'deleted' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
                    <i class="fas fa-history"></i> ປະຫວັດການລຶບ
                </a>
            </div>
            <div class="search-box" style="display:flex;align-items:center;">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="ຄົ້ນຫາເລກບິນ ຫຼື ສະແກນ QR..." value="<?= htmlspecialchars($search) ?>" onkeyup="if(event.key==='Enter')searchSales()">
                <?php if (!empty($search)): ?>
                <button type="button" class="btn btn-outline btn-sm" onclick="clearSearch()" style="margin-left:4px;min-height:40px;padding:4px 10px;border-radius:8px;" title="ລ້າງຄົ້ນຫາ">
                    <i class="fas fa-times"></i>
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="searchSales()" style="margin-left:6px;min-height:40px;min-width:44px;padding:4px 12px;border-radius:8px;" title="ຄົ້ນຫາ">
                    ຄົ້ນຫາ
                </button>
            </div>
            <a href="?export=csv&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-outline btn-sm" title="ສົ່ງອອກ CSV">
                <i class="fas fa-file-csv" style="color:var(--info);"></i> ສົ່ງອອກ CSV
            </a>
        </div>

        <!-- Search Match Banner -->
        <?php if (!empty($search)): ?>
            <?php 
            $isExactMatch = count($sales) === 1 && (strcasecmp($sales[0]['invoice_number'], $search) === 0);
            ?>
            <?php if ($isExactMatch): ?>
            <div class="alert alert-success" style="display:flex;justify-content:space-between;align-items:center;background:#e6f4ea;border:1px solid #34a853;border-radius:8px;padding:12px 16px;margin-bottom:14px;">
                <div>
                    <i class="fas fa-check-circle" style="color:#1e8e3e;font-size:1.2rem;margin-right:8px;"></i>
                    <strong>ພົບໃບບິນ: <?= htmlspecialchars($sales[0]['invoice_number']) ?></strong> 
                    <span style="font-size:12px;color:#333;margin-left:6px;">(ວັນທີ: <?= formatDateTime($sales[0]['created_at']) ?> | ລວມ: <?= formatCurrency($sales[0]['total_amount']) ?> ກີບ)</span>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="printReceipt(<?= (int)$sales[0]['id'] ?>)">
                        <i class="fas fa-print"></i> ພິມໃບບິນ
                    </button>
                    <?php if ($sales[0]['payment_status'] !== 'cancelled'): ?>
                    <button type="button" class="btn btn-outline btn-sm" data-sale-id="<?= (int)$sales[0]['id'] ?>" data-customer="<?= sanitize($sales[0]['customer_name'] ?? '') ?>" onclick="openLabelModal(this.dataset.saleId, this.dataset.customer)" style="color:var(--primary);border-color:var(--primary);">
                        <i class="fas fa-prescription-bottle-alt"></i> ພິມສະຫຼາກຢາ
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div style="display:flex;align-items:center;justify-content:space-between;background:var(--bg-card,#fff);border:1px solid var(--border-color,#e2e8f0);border-radius:8px;padding:8px 14px;margin-bottom:12px;font-size:13px;">
                <div>
                    <i class="fas fa-qrcode" style="color:var(--primary);margin-right:6px;"></i>
                    <span>ຜົນການຄົ້ນຫາ: <strong><?= htmlspecialchars($search) ?></strong> (ພົບ <?= count($sales) ?> ລາຍການ)</span>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="clearSearch()" style="padding:2px 8px;font-size:12px;">
                    <i class="fas fa-times"></i> ລ້າງຄົ້ນຫາ
                </button>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Sales Table -->
        <?php if (empty($sales)): ?>
        <div class="empty-state" style="padding:60px 20px; text-align:center;">
            <i class="fas fa-receipt" style="font-size:3rem; color:var(--text-light); margin-bottom:12px;"></i>
            <h4 style="margin-bottom:8px; color:var(--text-dark);">ບໍ່ພົບປະຫວັດການຂາຍ</h4>
            <p style="color:var(--text-light); margin-bottom:20px;">ບໍ່ພົບຂໍ້ມູນການຂາຍໃນຊ່ວງວັນທີ ຫຼື ຄຳຄົ້ນຫາທີ່ເລືອກ</p>
            <div style="display:flex; justify-content:center; gap:10px; flex-wrap:wrap;">
                <a href="sales_history.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-calendar-day"></i> ລ້າງຕົວກອງ (ມື້ນີ້)
                </a>
                <a href="pos.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-cash-register"></i> ໄປໜ້າຂາຍ (POS)
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table" id="salesTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ເລກບິນ</th>
                            <th>ລູກຄ້າ</th>
                            <th>ຍອດລວມ</th>
                            <th>ສ່ວນຫຼຸດ</th>
                            <th>ຍອດສຸດທິ</th>
                            <th>ການຊຳລະ</th>
                            <th>ສະຖານະ</th>
                            <th>ພະນັກງານ</th>
                            <th>ວັນທີ</th>
                            <th>ຈັດການ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $i => $sale): ?>
                        <tr class="<?= (!empty($search) && strcasecmp($sale['invoice_number'], $search) === 0) ? 'table-highlight-row' : '' ?>">
                            <td><?= $i + 1 ?></td>
                            <td>
                                <strong><?= $sale['invoice_number'] ?></strong>
                                <?php if (!empty($sale['is_tax_invoice'])): ?>
                                    <span class="badge badge-primary" style="font-size:0.7rem; padding:2px 6px; margin-left:4px;" title="ໃບກຳກັບພາສີ: <?= htmlspecialchars($sale['tax_invoice_number']) ?>">
                                        <i class="fas fa-file-invoice"></i> VAT
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($sale['customer_company'] ?: ($sale['customer_name'] ?: '-')) ?>
                                <?php if (!empty($sale['customer_tax_id'])): ?>
                                    <div style="font-size:0.75rem; color:var(--text-light); font-family:monospace;">TIN: <?= htmlspecialchars($sale['customer_tax_id']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= formatCurrency($sale['subtotal']) ?> ກີບ</td>
                            <td><?= formatCurrency($sale['discount_amount']) ?> ກີບ</td>
                            <td><strong><?= formatCurrency($sale['total_amount']) ?> ກີບ</strong></td>
                            <td><span class="badge <?= getPaymentMethodBadge($sale['payment_method']) ?>"><?= getPaymentMethodLabel($sale['payment_method']) ?></span></td>
                            <td><span class="badge <?= getPaymentStatusBadge($sale['payment_status']) ?>"><?= getPaymentStatusLabel($sale['payment_status']) ?></span></td>
                            <td><?= $sale['cashier'] ?: '-' ?></td>
                            <td><?= formatDateTime($sale['created_at']) ?></td>
                            <td>
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <button class="btn-icon btn-white btn-sm" onclick="printReceipt(<?= (int)$sale['id'] ?>)" title="ພິມບິນປົກກະຕິ (80mm)">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <?php if ($sale['payment_status'] !== 'cancelled'): ?>
                                    <button class="btn-icon btn-white btn-sm" data-sale-id="<?= (int)$sale['id'] ?>" data-customer="<?= sanitize($sale['customer_name'] ?? '') ?>" onclick="openLabelModal(this.dataset.saleId, this.dataset.customer)" title="ພິມສະຫຼາກຢາ" style="color:var(--primary);">
                                        <i class="fas fa-prescription-bottle-alt"></i>
                                    </button>
                                    <?php if (!empty($sale['is_tax_invoice'])): ?>
                                    <a href="tax_invoice.php?id=<?= (int)$sale['id'] ?>" target="_blank" class="btn-icon btn-white btn-sm" title="ເບິ່ງ/ພິມໃບກຳກັບພາສີ (A4/A5)" style="color:#0284c7;">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </a>
                                    <?php else: ?>
                                    <button type="button" class="btn-icon btn-white btn-sm" onclick="openRetroTaxInvoiceModal(<?= (int)$sale['id'] ?>, '<?= addslashes(htmlspecialchars($sale['customer_name'] ?? '')) ?>', '<?= addslashes(htmlspecialchars($sale['invoice_number'])) ?>')" title="ອອກໃບກຳກັບພາສີຍ້ອນຫຼັງ" style="color:#64748b;">
                                        <i class="fas fa-file-invoice"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (hasPermission('can_delete_sales')): ?>
                                    <button class="btn-icon btn-white btn-sm" style="color:var(--danger);" onclick="cancelSale(<?= (int)$sale['id'] ?>, this)" title="ຍົກເລີກ">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/medicine_label_modal.php'; ?>

<script>
function loadPageData(startDate, endDate) {
    window.location.href = `sales_history.php?tab=<?= $tab ?>&start=${startDate}&end=${endDate}`;
}

function searchSales(query) {
    const searchVal = (typeof query === 'string' ? query : document.getElementById('searchInput').value).trim();
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    window.location.href = `sales_history.php?tab=<?= $tab ?>&start=${startDate}&end=${endDate}&search=${encodeURIComponent(searchVal)}`;
}

function clearSearch() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    window.location.href = `sales_history.php?tab=<?= $tab ?>&start=${startDate}&end=${endDate}`;
}

// Sound feedback for successful barcode / QR scanner read
function playScanBeep() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1760, ctx.currentTime);
        gain.gain.setValueAtTime(0.12, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.08);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.08);
    } catch(e) {}
}

// Hardware Barcode & 2D QR Scanner Listener
let scannerBuffer = '';
let scannerTimeout = null;

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.focus();
        if (searchInput.value) {
            searchInput.select();
        }
    }

    // Capture global scanner keystrokes
    document.addEventListener('keydown', (e) => {
        const active = document.activeElement;
        const isSearchBox = active && active.id === 'searchInput';

        // Ignore if user is manually editing date filters or other inputs
        if (active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName) && !isSearchBox) {
            return;
        }

        // Hardware scanners finish reading by sending 'Enter'
        if (e.key === 'Enter') {
            if (scannerBuffer.length >= 3) {
                e.preventDefault();
                const code = scannerBuffer.trim();
                scannerBuffer = '';
                playScanBeep();
                if (searchInput) {
                    searchInput.value = code;
                    searchInput.style.borderColor = 'var(--primary, #1e8e3e)';
                    searchInput.style.boxShadow = '0 0 0 3px rgba(30, 142, 62, 0.2)';
                }
                searchSales(code);
                return;
            }
        }

        // Buffer rapid characters sent by the barcode reader (< 80ms per char)
        if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
            scannerBuffer += e.key;
            clearTimeout(scannerTimeout);
            scannerTimeout = setTimeout(() => {
                scannerBuffer = '';
            }, 80);
        }
    });
});

async function cancelSale(saleId, btn) {
    if (!await confirmDelete('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການຍົກເລີກການຂາຍນີ້?')) return;
    
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
    try {
        const result = await fetchAPI(`${BASE_URL}api/sales.php?id=${saleId}`, {
            method: 'DELETE',
        });
        if (result.success) {
            showToast('ຍົກເລີກການຂາຍສຳເລັດ', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-times"></i>';
            }
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່', 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times"></i>';
        }
    }
}

function openRetroTaxInvoiceModal(saleId, customerName, invoiceNumber) {
    document.getElementById('retroSaleId').value = saleId;
    document.getElementById('retroInvoiceRef').textContent = invoiceNumber;
    document.getElementById('retroCustomerCompany').value = customerName || '';
    document.getElementById('retroCustomerTin').value = '';
    document.getElementById('retroCustomerAddress').value = '';
    document.getElementById('retroTaxRate').value = '7';
    openModal('retroTaxInvoiceModal');
}

async function submitRetroTaxInvoice() {
    const saleId = document.getElementById('retroSaleId').value;
    const company = document.getElementById('retroCustomerCompany').value.trim();
    const tin = document.getElementById('retroCustomerTin').value.trim();
    const address = document.getElementById('retroCustomerAddress').value.trim();
    const rate = parseFloat(document.getElementById('retroTaxRate').value) || 0;

    const btn = document.getElementById('btnSubmitRetroTax');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງອອກໃບກຳກັບ...';
    }

    try {
        const result = await fetchAPI(`${BASE_URL}api/sales.php`, {
            method: 'POST',
            body: JSON.stringify({
                action: 'issue_tax_invoice',
                sale_id: parseInt(saleId),
                customer_company: company,
                customer_tax_id: tin,
                customer_address: address,
                tax_rate: rate
            })
        });

        if (result.success) {
            showToast('ອອກໃບກຳກັບພາສີສຳເລັດ!', 'success');
            closeModal('retroTaxInvoiceModal');
            window.open(`${BASE_URL}tax_invoice.php?id=${saleId}`, '_blank');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> ຢືນຢັນອອກໃບກຳກັບ';
            }
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່', 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> ຢືນຢັນອອກໃບກຳກັບ';
        }
    }
}
</script>

<!-- Retroactive Tax Invoice Modal -->
<div class="modal-overlay" id="retroTaxInvoiceModal">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <h3><i class="fas fa-file-invoice-dollar" style="color:var(--primary);"></i> ອອກໃບກຳກັບພາສີຍ້ອນຫຼັງ</h3>
            <button class="modal-close" onclick="closeModal('retroTaxInvoiceModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="retroSaleId" value="">
            <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:0.85rem;">
                ເລກທີໃບບິນອ້າງອີງ: <strong id="retroInvoiceRef" style="color:#0284c7; font-family:monospace;"></strong>
            </div>
            <div class="form-group">
                <label class="form-label">ຊື່ບໍລິສັດ / ອົງກອນ ຫຼື ລູກຄ້າ *</label>
                <input type="text" class="form-control" id="retroCustomerCompany" placeholder="ຊື່ບໍລິສັດ ຫຼື ຊື່ຜູ້ຊື້...">
            </div>
            <div class="form-row-2" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="form-group">
                    <label class="form-label">ເລກປະຈຳຕົວຜູ້ເສຍອາກອນ (TIN)</label>
                    <input type="text" class="form-control" id="retroCustomerTin" placeholder="01-xxxxxxxx">
                </div>
                <div class="form-group">
                    <label class="form-label">ອັດຕາ VAT (%)</label>
                    <select class="form-control" id="retroTaxRate">
                        <option value="7" selected>7% (ມາດຕະຖານ)</option>
                        <option value="10">10%</option>
                        <option value="0">0% (ຍົກເວັ້ນອາກອນ)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">ທີ່ຢູ່ສຳນັກງານ / ບໍລິສັດ</label>
                <textarea class="form-control" id="retroCustomerAddress" rows="2" placeholder="ບ້ານ, ເມືອງ, ແຂວງ..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-white" onclick="closeModal('retroTaxInvoiceModal')">ຍົກເລີກ</button>
            <button class="btn btn-primary" id="btnSubmitRetroTax" onclick="submitRetroTaxInvoice()">
                <i class="fas fa-check-circle"></i> ຢືນຢັນອອກໃບກຳກັບ
            </button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
