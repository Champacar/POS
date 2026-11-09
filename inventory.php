<?php
/**
 * ສາງສິນຄ້າ - Inventory/Warehouse
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();
if (!hasRole(['admin', 'manager', 'pharmacist']) && !hasPermission('can_manage_stock')) {
    header('Location: ' . BASE_URL . 'pos.php');
    exit;
}

$pageTitle = 'ສາງສິນຄ້າ';

try {
    $db = getDB();
    
    // Get products with stock info
    $products = $db->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY p.stock_quantity ASC")->fetchAll();
    
    // Stats
    $totalProducts = count($products);
    $lowStock = count(array_filter($products, fn($p) => $p['stock_quantity'] <= $p['min_stock']));
    $outOfStock = count(array_filter($products, fn($p) => $p['stock_quantity'] == 0));
    
    $today = date('Y-m-d');
    $warningDate = date('Y-m-d', strtotime('+30 days'));
    $expired = count(array_filter($products, fn($p) => !empty($p['expiry_date']) && $p['expiry_date'] < $today));
    $expiringSoon = count(array_filter($products, fn($p) => !empty($p['expiry_date']) && $p['expiry_date'] >= $today && $p['expiry_date'] <= $warningDate));
    
    // Recent stock history (joined with suppliers)
    $stockHistory = $db->query("SELECT sh.*, p.name as product_name, u.full_name as user_name, s.name as supplier_name FROM stock_history sh LEFT JOIN products p ON sh.product_id = p.id LEFT JOIN users u ON sh.user_id = u.id LEFT JOIN suppliers s ON sh.supplier_id = s.id ORDER BY sh.created_at DESC LIMIT 50")->fetchAll();
    
    // Get suppliers for Stock In selection
    $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    
    // Get all batches
    $allBatches = $db->query("
        SELECT pb.*, p.name as product_name, p.unit
        FROM product_batches pb
        JOIN products p ON pb.product_id = p.id
        ORDER BY pb.expiry_date ASC, pb.id DESC
    ")->fetchAll();
} catch (Exception $e) {
    $products = [];
    $totalProducts = 0;
    $lowStock = 0;
    $outOfStock = 0;
    $expired = 0;
    $expiringSoon = 0;
    $stockHistory = [];
    $suppliers = [];
    $allBatches = [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">ສາງສິນຄ້າ</h1>
    </div>

    <div class="content-area">
        <!-- Stats -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom:20px;">
            <div class="stat-card animate-in">
                <div class="stat-icon green"><i class="fas fa-boxes"></i></div>
                <div class="stat-info">
                    <h4>ສິນຄ້າທັງໝົດ</h4>
                    <span class="stat-value"><?= $totalProducts ?></span>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-info">
                    <h4>ໃກ້ໝົດສະຕ໋ອກ</h4>
                    <span class="stat-value"><?= $lowStock ?></span>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon coral"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <h4>ໝົດສະຕ໋ອກ</h4>
                    <span class="stat-value"><?= $outOfStock ?></span>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon orange"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-info">
                    <h4>ໃກ້ໝົດອາຍຸ</h4>
                    <span class="stat-value"><?= $expiringSoon ?></span>
                </div>
            </div>
            <div class="stat-card animate-in">
                <div class="stat-icon red"><i class="fas fa-skull-crossbones"></i></div>
                <div class="stat-info">
                    <h4>ໝົດອາຍຸແລ້ວ</h4>
                    <span class="stat-value"><?= $expired ?></span>
                </div>
            </div>
        </div>

        <!-- Tabs Header -->
        <div class="tabs" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px;">
            <button type="button" class="tab active" onclick="switchTab('stock-list')" id="tab-stock-list">
                <i class="fas fa-boxes"></i> ສະຕ໋ອກປັດຈຸບັນ
            </button>
            <button type="button" class="tab" onclick="switchTab('stock-history')" id="tab-stock-history">
                <i class="fas fa-history"></i> ປະຫວັດການເຄື່ອນໄຫວສະຕ໋ອກ
            </button>
            <button type="button" class="tab" onclick="switchTab('batch-list')" id="tab-batch-list">
                <i class="fas fa-layer-group"></i> ລາຍການ Lot/Batch ຢາ
            </button>
            <button type="button" class="tab" onclick="switchTab('auto-reorder')" id="tab-auto-reorder">
                <i class="fas fa-bolt" style="color:#ffab00; margin-right:4px;"></i> ແນະນຳສັ່ງຊື້ຢາ (Auto Reorder)
            </button>
            <button type="button" class="tab" onclick="switchTab('stock-audit')" id="tab-stock-audit">
                <i class="fas fa-clipboard-check" style="margin-right:4px;"></i> ກວດນັບສະຕ໋ອກ (Stock Audit)
            </button>
        </div>

        <!-- Tab 1: Current Stock List -->
        <div id="stock-list-container">
            <div class="toolbar">
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <button class="btn btn-primary" onclick="openModal('stockInModal')">
                        <i class="fas fa-plus"></i> ນຳເຂົ້າສິນຄ້າ
                    </button>
                    <button class="btn btn-white" style="color:var(--danger); border-color:rgba(222,53,11,0.2);" onclick="openModal('stockOutModal')">
                        <i class="fas fa-minus-circle"></i> ຕັດສະຕ໋ອກ / ປັບປຸງ
                    </button>
                    <button type="button" class="btn btn-outline" onclick="exportStockToExcel()" style="border-color:#2563eb;color:#1d4ed8;background:#eff6ff;font-weight:600;display:inline-flex;align-items:center;gap:6px;" title="ສົ່ງອອກລາຍການສະຕ໋ອກເປັນ Excel">
                        <i class="fas fa-file-excel"></i> ສົ່ງອອກ Excel
                    </button>
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="ຄົ້ນຫາຢາ..." oninput="filterTable('invSearch','inventoryTable')" id="invSearch">
                </div>
            </div>

            <?php if (empty($products)): ?>
            <div class="empty-state" style="padding:60px; text-align:center;">
                <i class="fas fa-boxes" style="font-size: 48px; opacity: 0.3; margin-bottom: 15px; display: block;"></i>
                <p style="margin-bottom:12px;">ບໍ່ມີຂໍ້ມູນສິນຄ້າໃນສາງ</p>
                <button class="btn btn-primary btn-sm" onclick="openModal('stockInModal')"><i class="fas fa-plus"></i> ນຳເຂົ້າສິນຄ້າ (Stock In)</button>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ຊື່ສິນຄ້າ</th>
                                <th>ໝວດໝູ່</th>
                                <th>ສະຕ໋ອກປັດຈຸບັນ</th>
                                <th>ຂັ້ນຕ່ຳ</th>
                                <th>ໜ່ວຍ</th>
                                <th>ສະຖານະ</th>
                                <th>ວັນໝົດອາຍຸ</th>
                                <th>ຈັດການ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= sanitize($p['name']) ?></strong></td>
                                <td><?= sanitize($p['category_name'] ?? '-') ?></td>
                                <td class="fw-bold"><?= $p['stock_quantity'] ?></td>
                                <td><?= $p['min_stock'] ?></td>
                                <td><?= sanitize($p['unit']) ?></td>
                                <td>
                                    <?php 
                                    $isExpired = !empty($p['expiry_date']) && $p['expiry_date'] < $today;
                                    $isExpiringSoon = !empty($p['expiry_date']) && !$isExpired && $p['expiry_date'] <= $warningDate;
                                    
                                    if ($p['stock_quantity'] == 0): ?>
                                        <span class="badge badge-danger">ໝົດສະຕ໋ອກ</span>
                                    <?php elseif ($p['stock_quantity'] <= $p['min_stock']): ?>
                                        <span class="badge badge-warning">ໃກ້ໝົດ</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">ປົກກະຕິ</span>
                                    <?php endif;
                                    
                                    if ($isExpired): ?>
                                        <span class="badge badge-danger">ໝົດອາຍຸ</span>
                                    <?php elseif ($isExpiringSoon): ?>
                                        <span class="badge badge-warning">ໃກ້ໝົດອາຍຸ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isExpired): ?>
                                        <span class="text-danger fw-bold"><i class="fas fa-calendar-times"></i> <?= formatDate($p['expiry_date']) ?></span>
                                    <?php elseif ($isExpiringSoon): ?>
                                        <span class="text-warning fw-bold"><i class="fas fa-hourglass-half"></i> <?= formatDate($p['expiry_date']) ?></span>
                                    <?php else: ?>
                                        <?= $p['expiry_date'] ? formatDate($p['expiry_date']) : '-' ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px; flex-wrap:nowrap;">
                                        <button class="btn btn-sm btn-primary" onclick="quickStockIn(<?= $p['id'] ?>)">
                                            <i class="fas fa-plus"></i> ນຳເຂົ້າ
                                        </button>
                                        <button class="btn btn-sm btn-white" style="color:var(--danger); border-color:rgba(222,53,11,0.2);" onclick="quickStockOut(<?= $p['id'] ?>)">
                                            <i class="fas fa-minus"></i> ຕັດອອກ
                                        </button>
                                        <button class="btn btn-sm btn-white" style="color:var(--primary); border-color:rgba(46,125,50,0.25);" data-id="<?= (int)$p['id'] ?>" data-name="<?= sanitize($p['name']) ?>" onclick="viewProductBatches(this.dataset.id, this.dataset.name)">
                                            <i class="fas fa-layer-group"></i> ເບິ່ງ Lot
                                        </button>
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

        <!-- Tab 2: Stock History Logs -->
        <div id="stock-history-container" style="display:none;">
            <div class="toolbar" style="justify-content: flex-end;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="ຄົ້ນຫາປະຫວັດ..." oninput="filterTable('histSearch','historyTable')" id="histSearch">
                </div>
            </div>
            <?php if (empty($stockHistory)): ?>
            <div class="empty-state" style="padding:60px; text-align:center;">
                <i class="fas fa-history" style="font-size: 48px; opacity: 0.3; margin-bottom: 15px; display: block;"></i>
                <p style="margin-bottom:12px;">ບໍ່ມີຂໍ້ມູນປະຫວັດການເຄື່ອນໄຫວສະຕ໋ອກ</p>
                <button class="btn btn-primary btn-sm" onclick="openModal('stockInModal')"><i class="fas fa-plus"></i> ນຳເຂົ້າສິນຄ້າ</button>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table" id="historyTable">
                        <thead>
                            <tr>
                                <th>ວັນທີ-ເວລາ</th>
                                <th>ຊື່ສິນຄ້າ</th>
                                <th>ປະເພດ</th>
                                <th>ຈຳນວນ</th>
                                <th>ຜູ້ຮັບຜິດຊອບ</th>
                                <th>ຜູ້ສະໜອງ</th>
                                <th>ໝາຍເຫດ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stockHistory as $h): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                                <td><strong><?= sanitize($h['product_name']) ?></strong></td>
                                <td>
                                    <?php if ($h['type'] === 'in'): ?>
                                        <span class="badge badge-success" style="display:inline-flex; align-items:center; gap:4px;">
                                            <i class="fas fa-plus-circle"></i> ນຳເຂົ້າ
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" style="display:inline-flex; align-items:center; gap:4px;">
                                            <i class="fas fa-minus-circle"></i> ຕັດອອກ
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold"><?= number_format($h['quantity']) ?></td>
                                <td><?= sanitize($h['user_name'] ?? 'ລະບົບ') ?></td>
                                <td><?= $h['type'] === 'in' ? sanitize($h['supplier_name'] ?? '-') : '-' ?></td>
                                <td><span style="font-size:0.85rem; color:var(--text-medium);"><?= sanitize($h['note'] ?? '-') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tab 3: Lot/Batch List -->
        <div id="batch-list-container" style="display:none;">
            <div class="toolbar" style="flex-wrap:wrap; gap:12px;">
                <div style="font-weight:700; font-size:1.1rem; color:var(--text-dark);">
                    <i class="fas fa-layer-group" style="color:var(--primary); margin-right:8px;"></i>ລາຍການ Lot/Batch ຢາທັງໝົດໃນລະບົບ
                </div>
                <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                    <span style="font-size:0.85rem; color:var(--text-light); font-weight:600;">ກັ່ນຕອງ:</span>
                    <button type="button" class="btn btn-sm btn-outline batch-filter-btn active" onclick="filterBatches('all', this)" id="btnFilterBatchAll">ທັງໝົດ</button>
                    <button type="button" class="btn btn-sm btn-outline batch-filter-btn" onclick="filterBatches('expired', this)" style="color:var(--danger); border-color:var(--danger);">ໝົດອາຍຸແລ້ວ</button>
                    <button type="button" class="btn btn-sm btn-outline batch-filter-btn" onclick="filterBatches('expiring', this)" style="color:#d97706; border-color:#d97706;">ໃກ້ໝົດອາຍຸ (≤ 90ວັນ)</button>
                    <button type="button" class="btn btn-sm btn-outline batch-filter-btn" onclick="filterBatches('normal', this)" style="color:var(--success); border-color:var(--success);">ປົກກະຕິ</button>
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="ຄົ້ນຫາ Lot/Batch..." oninput="filterTable('batchSearch','batchTable')" id="batchSearch">
                </div>
            </div>

            <?php if (empty($allBatches)): ?>
            <div class="empty-state" style="padding:60px; text-align:center;">
                <i class="fas fa-layer-group" style="font-size: 48px; opacity: 0.3; margin-bottom: 15px; display: block;"></i>
                <p style="margin-bottom:12px;">ບໍ່ມີຂໍ້ມູນ Lot/Batch ຢາໃນລະບົບ</p>
                <button class="btn btn-primary btn-sm" onclick="openModal('stockInModal')"><i class="fas fa-plus"></i> ນຳເຂົ້າ Lot ທຳອິດ</button>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table" id="batchTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ຊື່ຢາ</th>
                                <th>ເລກ Lot/Batch</th>
                                <th style="text-align:right;">ລາຄາຕົ້ນທຶນ</th>
                                <th style="text-align:right;">ລາຄາຂາຍ</th>
                                <th style="text-align:center;">ຈຳນວນນຳເຂົ້າ</th>
                                <th style="text-align:center;">ຄົງເຫຼືອປັດຈຸບັນ</th>
                                <th style="text-align:center;">ວັນໝົດອາຍຸ</th>
                                <th>ສະຖານະ Lot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allBatches as $idx => $b): ?>
                            <?php 
                            $isExpired = strtotime($b['expiry_date']) < time(); 
                            $isExpiring = !$isExpired && (strtotime($b['expiry_date']) <= strtotime('+90 days'));
                            ?>
                            <tr style="vertical-align: middle;" data-batch-status="<?= $isExpired ? 'expired' : ($isExpiring ? 'expiring' : 'normal') ?>">
                                <td><?= $idx + 1 ?></td>
                                <td><strong><?= sanitize($b['product_name']) ?></strong></td>
                                <td><span class="badge badge-info"><?= sanitize($b['batch_number']) ?></span></td>
                                <td style="text-align:right;"><?= formatCurrency($b['cost_price']) ?> ກີບ</td>
                                <td style="text-align:right;"><?= formatCurrency($b['selling_price']) ?> ກີບ</td>
                                <td style="text-align:center;"><?= number_format($b['initial_quantity']) ?> <?= sanitize($b['unit']) ?></td>
                                <td style="text-align:center; font-weight:700;"><?= number_format($b['quantity']) ?> <?= sanitize($b['unit']) ?></td>
                                <td style="text-align:center;">
                                    <span style="<?= $isExpired || $isExpiring ? 'font-weight:700; color:var(--danger);' : '' ?>">
                                        <?= date('d/m/Y', strtotime($b['expiry_date'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isExpired): ?>
                                        <span class="badge badge-danger">ໝົດອາຍຸແລ້ວ</span>
                                    <?php elseif ($isExpiring): ?>
                                        <span class="badge badge-warning">ໃກ້ໝົດອາຍຸ</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">ປົກກະຕິ</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tab 4: Auto Reorder Suggestions -->
        <div id="auto-reorder-container" style="display:none;">
            <!-- Header Banner -->
            <div class="card" style="margin-bottom:20px; background:linear-gradient(135deg, #0747a6 0%, #0052cc 100%); color:#fff; border:none; padding:20px 24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                    <div>
                        <h3 style="font-size:1.3rem; font-weight:800; color:#fff; margin-bottom:4px; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-bolt" style="color:#ffab00;"></i> ສູນແນະນຳສັ່ງຊື້ຢາເຕີມສະຕ໋ອກ (Auto Reorder Hub)
                        </h3>
                        <p style="font-size:0.9rem; color:#deebff; margin-bottom:0;">
                            ລະບົບຄຳນວນລາຍການຢາທີ່ສະຕ໋ອກຫຼຸດຮອດຈຸດສັ່ງຊື້ (Reorder Point) ແລະ ແຍກຕາມຜູ້ສະໜອງຢາ (Suppliers) ເພື່ອສ້າງໃບສັ່ງຊື້ PO ໄດ້ໃນ 1 ຄລິກ
                        </p>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="btn btn-white" onclick="loadAutoReorderSuggestions()" style="font-weight:700; color:#0052cc;">
                            <i class="fas fa-sync-alt"></i> ໂຫຼດຂໍ້ມູນໃໝ່
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom:20px;">
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-info">
                        <h4>ລາຍການທີ່ຕ້ອງສັ່ງຊື້</h4>
                        <span class="stat-value" id="reorderTotalItems">0</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon coral"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-info">
                        <h4>ສິນຄ້າໝົດສະຕ໋ອກ</h4>
                        <span class="stat-value" id="reorderOutOfStock">0</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-calculator"></i></div>
                    <div class="stat-info">
                        <h4>ປະມານການງົບປະມານ</h4>
                        <span class="stat-value" id="reorderTotalBudget" style="font-size:1.4rem;">0 ₭</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-truck"></i></div>
                    <div class="stat-info">
                        <h4>ຜູ້ສະໜອງທີ່ກ່ຽວຂ້ອງ</h4>
                        <span class="stat-value" id="reorderTotalSuppliers">0</span>
                    </div>
                </div>
            </div>

            <!-- Supplier Groups Container -->
            <div id="reorderSupplierGroups">
                <div style="text-align:center; padding:40px; color:#5e6c84;">
                    <i class="fas fa-spinner fa-spin" style="font-size:32px; color:var(--primary); margin-bottom:12px;"></i>
                    <p>ກຳລັງວິເຄາະສະຕ໋ອກ ແລະ ຜູ້ສະໜອງຢາ...</p>
                </div>
            </div>
        </div>

        <!-- Tab 5: Barcode Stock Audit -->
        <div id="stock-audit-container" style="display:none;">
            <div id="stockAuditContent">
                <div style="text-align:center; padding:50px 20px; color:var(--text-light);">
                    <i class="fas fa-spinner fa-spin" style="font-size:36px; color:var(--primary); margin-bottom:14px; display:block;"></i>
                    <p style="font-size:0.95rem; margin-bottom:0;">ກຳລັງໂຫຼດຂໍ້ມູນຮອບກວດນັບສະຕ໋ອກ...</p>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Start Stock Audit Modal -->
<div class="modal-overlay" id="startAuditModal">
    <div class="modal" style="max-width:550px;">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check" style="margin-right:8px; color:var(--primary);"></i>ເປີດຮອບກວດນັບສະຕ໋ອກໃໝ່</h3>
            <button class="modal-close" onclick="closeModal('startAuditModal')"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitStartAudit(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">ຊື່ຮອບກວດນັບ *</label>
                    <input type="text" class="form-control" id="auditTitleInput" required value="ກວດນັບສະຕ໋ອກປະຈຳວັນ <?= date('d/m/Y') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">ໝວດໝູ່ທີ່ຕ້ອງການກວດນັບ</label>
                    <select class="form-control" id="auditCategorySelect">
                        <option value="0">-- ກວດນັບສິນຄ້າທຸກໝວດໝູ່ (ທັງໝົດ) --</option>
                        <?php 
                        try {
                            $dbCat = getDB();
                            $cats = $dbCat->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
                            foreach ($cats as $c):
                        ?>
                        <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                        <?php 
                            endforeach;
                        } catch (Exception $e) {}
                        ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">ໝາຍເຫດ</label>
                    <textarea class="form-control" id="auditNoteInput" rows="2" placeholder="ໝາຍເຫດເພີ່ມເຕີມ ເຊັ່ນ: ກວດນັບກ່ອນປິດຮອບເດືອນ..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-white" onclick="closeModal('startAuditModal')">ຍົກເລີກ</button>
                <button type="submit" class="btn btn-primary" id="startAuditBtn"><i class="fas fa-play"></i> ເລີ່ມຕົ້ນກວດນັບ</button>
            </div>
        </form>
    </div>
</div>

<!-- Stock In Modal -->
<div class="modal-overlay" id="stockInModal">
    <div class="modal">
        <div class="modal-header">
            <h3>ນຳເຂົ້າສິນຄ້າ (Stock In)</h3>
            <button class="modal-close" onclick="closeModal('stockInModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">ສະແກນບາໂຄດ</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-barcode input-left-icon"></i>
                    <input type="text" class="form-control" id="stockBarcode" placeholder="ສະແກນບາໂຄດ ຫຼື ປ້ອນເລກບາໂຄດເພື່ອຄົ້ນຫາ...">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">ເລືອກສິນຄ້າ *</label>
                <select class="form-control" id="stockProduct">
                    <option value="">ເລືອກສິນຄ້າ</option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>" data-barcode="<?= sanitize($p['barcode'] ?? '') ?>"><?= sanitize($p['name']) ?> (ປັດຈຸບັນ: <?= $p['stock_quantity'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">ຈຳນວນນຳເຂົ້າ *</label>
                <input type="number" class="form-control" id="stockQty" min="1" placeholder="0">
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">ເລກ Lot / Batch</label>
                    <input type="text" class="form-control" id="stockBatchNumber" placeholder="ຕົວຢ່າງ: LOT12345">
                </div>
                <div class="form-group">
                    <label class="form-label">ວັນໝົດອາຍຸ Lot</label>
                    <input type="date" class="form-control" id="stockExpiryDate">
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">ລາຄາຕົ້ນທຶນ Lot</label>
                    <input type="number" step="0.01" class="form-control" id="stockCostPrice" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">ລາຄາຂາຍ Lot</label>
                    <input type="number" step="0.01" class="form-control" id="stockSellingPrice" placeholder="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">ຜູ້ສະໜອງຢາ</label>
                <select class="form-control" id="stockSupplier">
                    <option value="">ເລືອກຜູ້ສະໜອງ (ບໍ່ບັງຄັບ)</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">ໝາຍເຫດ</label>
                <textarea class="form-control" id="stockNote" rows="2" placeholder="ໝາຍເຫດ..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-white" onclick="closeModal('stockInModal')">ຍົກເລີກ</button>
            <button class="btn btn-primary" id="stockInBtn" onclick="stockIn()"><i class="fas fa-save"></i> ບັນທຶກ</button>
        </div>
    </div>
</div>

<!-- Stock Out Modal -->
<div class="modal-overlay" id="stockOutModal">
    <div class="modal">
        <div class="modal-header">
            <h3>ຕັດສະຕ໋ອກ / ປັບປຸງສະຕ໋ອກ (Stock Out)</h3>
            <button class="modal-close" onclick="closeModal('stockOutModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">ສະແກນບາໂຄດ</label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-barcode input-left-icon"></i>
                    <input type="text" class="form-control" id="stockOutBarcode" placeholder="ສະແກນບາໂຄດ ຫຼື ປ້ອນເລກບາໂຄດເພື່ອຄົ້ນຫາ...">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">ເລືອກສິນຄ້າ *</label>
                <select class="form-control" id="stockOutProduct">
                    <option value="">ເລືອກສິນຄ້າ</option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>" data-barcode="<?= sanitize($p['barcode'] ?? '') ?>"><?= sanitize($p['name']) ?> (ປັດຈຸບັນ: <?= $p['stock_quantity'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">ຈຳນວນທີ່ຕ້ອງການຕັດອອກ *</label>
                <input type="number" class="form-control" id="stockOutQty" min="1" placeholder="0">
            </div>
            <div class="form-group">
                <label class="form-label">ໝາຍເຫດ / ເຫດຜົນໃນການຕັດອອກ</label>
                <textarea class="form-control" id="stockOutNote" rows="2" placeholder="ຕົວຢ່າງ: ຢາໝົດອາຍຸ, ຊຳລຸດ, ປັບປຸງສະຕ໋ອກຄືນ..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-white" onclick="closeModal('stockOutModal')">ຍົກເລີກ</button>
            <button class="btn btn-danger" id="stockOutBtn" onclick="stockOut()"><i class="fas fa-minus-circle"></i> ຢືນຢັນການຕັດສະຕ໋ອກ</button>
        </div>
    </div>
</div>

<!-- Product Batches Modal -->
<div class="modal-overlay" id="productBatchesModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group" style="margin-right:8px; color:var(--primary);"></i>ລາຍການ Lot/Batch ຢາ: <span id="batchModalProductName" style="color:var(--primary);"></span></h3>
            <button class="modal-close" onclick="closeModal('productBatchesModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="table-responsive">
                <table class="table" style="font-size:0.9rem;">
                    <thead>
                        <tr>
                            <th>ເລກ Lot/Batch</th>
                            <th style="text-align:right;">ລາຄາຕົ້ນທຶນ</th>
                            <th style="text-align:right;">ລາຄາຂາຍ</th>
                            <th style="text-align:center;">ຄົງເຫຼືອ</th>
                            <th style="text-align:center;">ວັນໝົດອາຍຸ</th>
                            <th>ສະຖານະ</th>
                        </tr>
                    </thead>
                    <tbody id="productBatchesTableBody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-white" onclick="closeModal('productBatchesModal')">ປິດ</button>
        </div>
    </div>
</div>

<script>
// Tab Switching
function switchTab(tabId) {
    const listTab = document.getElementById('tab-stock-list');
    const histTab = document.getElementById('tab-stock-history');
    const batchTab = document.getElementById('tab-batch-list');
    const autoTab = document.getElementById('tab-auto-reorder');
    const auditTab = document.getElementById('tab-stock-audit');
    
    const listContainer = document.getElementById('stock-list-container');
    const histContainer = document.getElementById('stock-history-container');
    const batchContainer = document.getElementById('batch-list-container');
    const autoContainer = document.getElementById('auto-reorder-container');
    const auditContainer = document.getElementById('stock-audit-container');
    
    // Reset all tabs
    [listTab, histTab, batchTab, autoTab, auditTab].forEach(tab => {
        if (tab) {
            tab.classList.remove('active');
        }
    });
    
    // Hide all containers
    [listContainer, histContainer, batchContainer, autoContainer, auditContainer].forEach(c => {
        if (c) c.style.display = 'none';
    });
    
    // Activate clicked tab
    if (tabId === 'stock-list') {
        listTab.classList.add('active');
        listContainer.style.display = 'block';
    } else if (tabId === 'stock-history') {
        histTab.classList.add('active');
        histContainer.style.display = 'block';
    } else if (tabId === 'batch-list') {
        batchTab.classList.add('active');
        batchContainer.style.display = 'block';
    } else if (tabId === 'auto-reorder') {
        if (autoTab) autoTab.classList.add('active');
        if (autoContainer) autoContainer.style.display = 'block';
        loadAutoReorderSuggestions();
    } else if (tabId === 'stock-audit') {
        if (auditTab) auditTab.classList.add('active');
        if (auditContainer) auditContainer.style.display = 'block';
        loadStockAuditSession();
    }

    if (window.history && window.history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        window.history.replaceState(null, '', url);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab && ['stock-list', 'stock-history', 'batch-list', 'auto-reorder', 'stock-audit'].includes(activeTab)) {
        switchTab(activeTab);
    }
});

// Tab 3: Filter Batches by Expiry Status
function filterBatches(status, btn) {
    document.querySelectorAll('.batch-filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    
    const rows = document.querySelectorAll('#batchTable tbody tr');
    rows.forEach(r => {
        if (status === 'all') {
            r.style.display = '';
        } else {
            r.style.display = (r.getAttribute('data-batch-status') === status) ? '' : 'none';
        }
    });
}

function escapeReorderHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Tab 4: Auto Reorder Suggestions
let reorderSuppliersData = [];

async function loadAutoReorderSuggestions() {
    const container = document.getElementById('reorderSupplierGroups');
    if (!container) return;
    
    container.innerHTML = `
        <div style="text-align:center; padding:50px 20px; color:var(--text-light);">
            <i class="fas fa-spinner fa-spin" style="font-size:36px; color:var(--primary); margin-bottom:14px; display:block;"></i>
            <p style="font-size:0.95rem; margin-bottom:0;">ກຳລັງວິເຄາະສະຕ໋ອກ ແລະ ຜູ້ສະໜອງຢາ...</p>
        </div>
    `;

    try {
        const res = await fetchAPI(`${BASE_URL}api/reorder_suggestions.php?action=suggestions`);
        if (!res || !res.success) {
            container.innerHTML = `<div class="card" style="padding:30px; text-align:center; color:var(--danger);"><i class="fas fa-exclamation-circle" style="font-size:32px; margin-bottom:10px;"></i><p>${res?.message || 'ເກີດຂໍ້ຜິດພາດໃນການໂຫຼດຂໍ້ມູນ'}</p></div>`;
            return;
        }

        const summary = res.summary || {};
        const totalItemsEl = document.getElementById('reorderTotalItems');
        const outOfStockEl = document.getElementById('reorderOutOfStock');
        const totalBudgetEl = document.getElementById('reorderTotalBudget');
        const totalSuppliersEl = document.getElementById('reorderTotalSuppliers');

        if (totalItemsEl) totalItemsEl.textContent = Number(summary.total_low_stock_products || 0).toLocaleString();
        if (outOfStockEl) outOfStockEl.textContent = Number(summary.total_out_of_stock || 0).toLocaleString();
        if (totalBudgetEl) totalBudgetEl.textContent = formatCurrency(summary.total_estimated_budget || 0) + ' ₭';
        if (totalSuppliersEl) totalSuppliersEl.textContent = Number(summary.total_suppliers || 0).toLocaleString();

        const suppliers = res.suppliers || [];
        reorderSuppliersData = suppliers;

        if (suppliers.length === 0) {
            container.innerHTML = `
                <div class="card" style="text-align:center; padding:60px 20px; border-radius:12px;">
                    <i class="fas fa-check-circle" style="font-size:52px; color:var(--success); margin-bottom:16px; display:block;"></i>
                    <h3 style="font-weight:700; color:var(--text-dark); margin-bottom:8px;">ສະຕ໋ອກຢາຢູ່ໃນເກນປອດໄພທັງໝົດ! 🎉</h3>
                    <p style="color:var(--text-light); margin-bottom:0; max-width:500px; margin-left:auto; margin-right:auto;">
                        ບໍ່ມີລາຍການຢາໃດທີ່ສະຕ໋ອກຫຼຸດຮອດຈຸດສັ່ງຊື້ (Reorder Point) ໃນຂະນະນີ້. ທ່ານສາມາດກັບມາເບິ່ງໃໝ່ໄດ້ເມື່ອມີການຂາຍເພີ່ມເຕີມ.
                    </p>
                </div>
            `;
            return;
        }

        let html = '';
        suppliers.forEach((s) => {
            const hasSupplier = s.supplier_id > 0;
            html += `
                <div class="card supplier-reorder-card" id="supplier_card_${s.supplier_id}" style="margin-bottom:24px; border-top:4px solid var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; padding-bottom:16px; border-bottom:1px solid var(--border-light); margin-bottom:16px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                                <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-dark); margin:0;">
                                    ${escapeReorderHtml(s.supplier_name)}
                                </h3>
                                ${s.supplier_phone ? `<span style="font-size:0.82rem; color:var(--text-light);"><i class="fas fa-phone-alt"></i> ${escapeReorderHtml(s.supplier_phone)}</span>` : ''}
                            </div>
                            <div style="display:flex; gap:12px; font-size:0.85rem; color:var(--text-light);">
                                <span><i class="fas fa-box"></i> ຢາໃກ້ໝົດ: <b style="color:var(--text-dark);">${s.items.length}</b> ລາຍການ</span>
                                <span><i class="fas fa-coins"></i> ປະມານການ: <b style="color:var(--primary);" id="card_total_cost_${s.supplier_id}">${formatCurrency(s.total_cost)} ₭</b></span>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.85rem; font-weight:600; color:var(--text-dark); margin:0; user-select:none;">
                                <input type="checkbox" id="chk_all_${s.supplier_id}" checked onchange="toggleSupplierAll(${s.supplier_id}, this.checked)" style="width:18px; height:18px; cursor:pointer;">
                                ເລືອກທັງໝົດ
                            </label>
                            <button type="button" class="btn btn-primary btn-sm" id="btn_create_po_${s.supplier_id}" onclick="createAutoPO(${s.supplier_id}, this)" style="display:inline-flex; align-items:center; gap:6px; font-weight:700; padding:8px 14px;">
                                <i class="fas fa-file-invoice-dollar"></i> ສ້າງໃບສັ່ງຊື້ PO (${s.items.length} ລາຍການ)
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table" style="font-size:0.88rem; margin-bottom:0;">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th style="width:40px; text-align:center;">ເລືອກ</th>
                                    <th>ຊື່ສິນຄ້າ / ຢາ</th>
                                    <th style="text-align:center;">ສະຕ໋ອກ / ຂັ້ນຕ່ຳ</th>
                                    <th style="text-align:right;">ລາຄາຕົ້ນທຶນ</th>
                                    <th style="text-align:center; width:130px;">ຈຳນວນແນະນຳສັ່ງ</th>
                                    <th style="text-align:right;">ມູນຄ່າລວມ (ກີບ)</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            s.items.forEach((it) => {
                const isOut = it.stock_quantity <= 0;
                const statusBadge = isOut 
                    ? `<span class="badge badge-danger" style="font-size:0.7rem; margin-left:6px;"><i class="fas fa-times-circle"></i> ໝົດສະຕ໋ອກ</span>`
                    : `<span class="badge badge-warning" style="font-size:0.7rem; margin-left:6px;"><i class="fas fa-exclamation-triangle"></i> ໃກ້ໝົດ</span>`;

                html += `
                    <tr style="vertical-align: middle;" id="row_${s.supplier_id}_${it.id}">
                        <td style="text-align:center;">
                            <input type="checkbox" class="reorder-item-chk reorder-chk-${s.supplier_id}" 
                                data-supplier-id="${s.supplier_id}" 
                                data-id="${it.id}" 
                                data-name="${escapeReorderHtml(it.name)}" 
                                data-cost="${it.cost_price}" 
                                checked 
                                onchange="updateSupplierCardTotal(${s.supplier_id})">
                        </td>
                        <td>
                            <div style="font-weight:700; color:var(--text-dark); display:flex; align-items:center;">
                                ${escapeReorderHtml(it.name)}
                                ${statusBadge}
                            </div>
                            <div style="font-size:0.78rem; color:var(--text-light); margin-top:2px;">
                                <span>${it.barcode ? `<i class="fas fa-barcode"></i> ${escapeReorderHtml(it.barcode)} | ` : ''}</span>
                                <span>ໝວດ: ${escapeReorderHtml(it.category_name)}</span>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge ${isOut ? 'badge-danger' : 'badge-warning'}" style="font-weight:700; font-size:0.85rem;">
                                ${Number(it.stock_quantity).toLocaleString()}
                            </span>
                            <span style="color:var(--text-light); font-size:0.8rem;"> / ${Number(it.min_stock).toLocaleString()} ${escapeReorderHtml(it.unit)}</span>
                        </td>
                        <td style="text-align:right; font-weight:600;">
                            ${formatCurrency(it.cost_price)} ₭
                        </td>
                        <td style="text-align:center;">
                            <div style="display:inline-flex; align-items:center; gap:4px;">
                                <input type="number" min="1" class="form-control reorder-qty-input reorder-qty-${s.supplier_id}" 
                                    id="qty_${s.supplier_id}_${it.id}" 
                                    data-supplier-id="${s.supplier_id}" 
                                    data-id="${it.id}" 
                                    data-cost="${it.cost_price}" 
                                    value="${it.suggested_quantity}" 
                                    onchange="updateSupplierCardTotal(${s.supplier_id})" 
                                    onkeyup="updateSupplierCardTotal(${s.supplier_id})" 
                                    style="width:85px; text-align:center; padding:5px 8px; font-weight:700;">
                                <span style="font-size:0.8rem; color:var(--text-light);">${escapeReorderHtml(it.unit)}</span>
                            </div>
                        </td>
                        <td style="text-align:right; font-weight:700; color:var(--primary);" id="subtotal_${s.supplier_id}_${it.id}">
                            ${formatCurrency(it.estimated_cost)} ₭
                        </td>
                    </tr>
                `;
            });

            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;

    } catch (e) {
        console.error(e);
        container.innerHTML = `<div class="card" style="padding:30px; text-align:center; color:var(--danger);"><i class="fas fa-exclamation-circle" style="font-size:32px; margin-bottom:10px;"></i><p>ເກີດຂໍ້ຜິດພາດ: ${escapeReorderHtml(e.message)}</p></div>`;
    }
}

function updateSupplierCardTotal(supplierId) {
    const checkboxes = document.querySelectorAll(`.reorder-chk-${supplierId}`);
    let selectedCount = 0;
    let totalCost = 0;

    checkboxes.forEach(chk => {
        const prodId = chk.getAttribute('data-id');
        const qtyInput = document.getElementById(`qty_${supplierId}_${prodId}`);
        const subtotalEl = document.getElementById(`subtotal_${supplierId}_${prodId}`);
        const cost = parseFloat(chk.getAttribute('data-cost')) || 0;
        const qty = parseInt(qtyInput ? qtyInput.value : 0, 10) || 0;
        const rowCost = qty * cost;

        if (subtotalEl) {
            subtotalEl.textContent = formatCurrency(rowCost) + ' ₭';
        }

        if (chk.checked) {
            selectedCount++;
            totalCost += rowCost;
        }
    });

    const cardTotalCost = document.getElementById(`card_total_cost_${supplierId}`);
    if (cardTotalCost) {
        cardTotalCost.textContent = formatCurrency(totalCost) + ' ₭';
    }

    const btn = document.getElementById(`btn_create_po_${supplierId}`);
    if (btn) {
        btn.innerHTML = `<i class="fas fa-file-invoice-dollar"></i> ສ້າງໃບສັ່ງຊື້ PO (${selectedCount} ລາຍການ)`;
        btn.disabled = (selectedCount === 0);
    }
}

function toggleSupplierAll(supplierId, checked) {
    const checkboxes = document.querySelectorAll(`.reorder-chk-${supplierId}`);
    checkboxes.forEach(chk => {
        chk.checked = checked;
    });
    updateSupplierCardTotal(supplierId);
}

async function createAutoPO(supplierId, btnEl) {
    const checkboxes = document.querySelectorAll(`.reorder-chk-${supplierId}:checked`);
    if (checkboxes.length === 0) {
        showToast('ກະລຸນາເລືອກຢາຢ່າງໜ້ອຍ 1 ລາຍການ', 'warning');
        return;
    }

    const items = [];
    checkboxes.forEach(chk => {
        const prodId = parseInt(chk.getAttribute('data-id'), 10);
        const name = chk.getAttribute('data-name');
        const cost = parseFloat(chk.getAttribute('data-cost')) || 0;
        const qtyInput = document.getElementById(`qty_${supplierId}_${prodId}`);
        const qty = parseInt(qtyInput ? qtyInput.value : 1, 10) || 1;

        items.push({
            product_id: prodId,
            product_name: name,
            quantity: qty,
            unit_price: cost
        });
    });

    const origHtml = btnEl ? btnEl.innerHTML : '';
    if (btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງສ້າງໃບ PO...';
    }

    try {
        const result = await fetchAPI(`${BASE_URL}api/reorder_suggestions.php?action=create_auto_po`, {
            method: 'POST',
            body: JSON.stringify({
                supplier_id: supplierId,
                items: items,
                note: 'ສ້າງອັດຕະໂນມັດຈາກລະບົບແນະນຳສັ່ງຊື້ຢາ (Auto Reorder Hub)'
            })
        });

        if (result && result.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'ສ້າງໃບສັ່ງຊື້ PO ສຳເລັດ!',
                    html: `
                        <div style="text-align:left; padding:12px 16px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; margin-top:10px;">
                            <p style="margin-bottom:6px; font-weight:700; color:#166534; font-size:1.05rem;">
                                <i class="fas fa-check-circle"></i> ເລກທີໃບສັ່ງຊື້: ${result.po_number}
                            </p>
                            <p style="margin-bottom:4px; font-size:0.9rem; color:#1e293b;">
                                ຈຳນວນຢາທີ່ສັ່ງ: <b>${result.items_count}</b> ລາຍການ
                            </p>
                            <p style="margin-bottom:0; font-size:0.9rem; color:#1e293b;">
                                ມູນຄ່າລວມປະມານ: <b style="color:var(--primary); font-size:1.05rem;">${formatCurrency(result.total_amount)} ກີບ</b>
                            </p>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-external-link-alt"></i> ໄປໜ້າໃບສັ່ງຊື້ PO',
                    cancelButtonText: 'ສັ່ງຊື້ຜູ້ສະໜອງອື່ນຕໍ່',
                    confirmButtonColor: 'var(--primary)',
                    cancelButtonColor: '#64748b'
                }).then((dialogRes) => {
                    if (dialogRes.isConfirmed) {
                        window.location.href = `${BASE_URL}purchase_orders.php`;
                    } else {
                        loadAutoReorderSuggestions();
                    }
                });
            } else {
                showToast(result.message || 'ສ້າງໃບສັ່ງຊື້ສຳເລັດ', 'success');
                setTimeout(() => loadAutoReorderSuggestions(), 1200);
            }
        } else {
            showToast(result?.message || 'ເກີດຂໍ້ຜິດພາດໃນການສ້າງໃບສັ່ງຊື້', 'error');
            if (btnEl) {
                btnEl.disabled = false;
                btnEl.innerHTML = origHtml;
            }
        }
    } catch (err) {
        console.error(err);
        showToast('ເກີດຂໍ້ຜິດພາດ: ' + err.message, 'error');
        if (btnEl) {
            btnEl.disabled = false;
            btnEl.innerHTML = origHtml;
        }
    }
}

// Quick Actions
function quickStockIn(productId) {
    openModal('stockInModal');
    document.getElementById('stockProduct').value = productId;
}

function quickStockOut(productId) {
    openModal('stockOutModal');
    document.getElementById('stockOutProduct').value = productId;
}

function selectProductByBarcode(barcode) {
    if (!barcode) return false;
    const select = document.getElementById('stockProduct');
    const options = select.options;
    let found = false;
    for (let i = 0; i < options.length; i++) {
        if (options[i].getAttribute('data-barcode') === barcode.trim()) {
            select.selectedIndex = i;
            found = true;
            break;
        }
    }
    return found;
}

function selectProductByBarcodeOut(barcode) {
    if (!barcode) return false;
    const select = document.getElementById('stockOutProduct');
    const options = select.options;
    let found = false;
    for (let i = 0; i < options.length; i++) {
        if (options[i].getAttribute('data-barcode') === barcode.trim()) {
            select.selectedIndex = i;
            found = true;
            break;
        }
    }
    return found;
}

document.addEventListener('DOMContentLoaded', () => {
    const stockBarcode = document.getElementById('stockBarcode');
    if (stockBarcode) {
        stockBarcode.addEventListener('input', function() {
            selectProductByBarcode(this.value);
        });
        stockBarcode.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const matched = selectProductByBarcode(this.value);
                if (matched) {
                    const qtyInput = document.getElementById('stockQty');
                    qtyInput.focus();
                    qtyInput.select();
                } else {
                    showToast('ບໍ່ພົບສິນຄ້າທີ່ມີບາໂຄດນີ້', 'warning');
                }
            }
        });
    }

    const stockOutBarcode = document.getElementById('stockOutBarcode');
    if (stockOutBarcode) {
        stockOutBarcode.addEventListener('input', function() {
            selectProductByBarcodeOut(this.value);
        });
        stockOutBarcode.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const matched = selectProductByBarcodeOut(this.value);
                if (matched) {
                    const qtyInput = document.getElementById('stockOutQty');
                    qtyInput.focus();
                    qtyInput.select();
                } else {
                    showToast('ບໍ່ພົບສິນຄ້າທີ່ມີບາໂຄດນີ້', 'warning');
                }
            }
        });
    }
    
    // Reset fields & autofocus barcode when modal is opened
    window.openModal = (function(originalOpenModal) {
        return function(modalId) {
            if (modalId === 'stockInModal') {
                ['stockBarcode', 'stockProduct', 'stockQty', 'stockBatchNumber', 'stockExpiryDate', 'stockCostPrice', 'stockSellingPrice', 'stockSupplier', 'stockNote'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
            } else if (modalId === 'stockOutModal') {
                ['stockOutBarcode', 'stockOutProduct', 'stockOutQty', 'stockOutNote'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
            }
            originalOpenModal(modalId);
            if (modalId === 'stockInModal') {
                setTimeout(() => {
                    const barcodeInput = document.getElementById('stockBarcode');
                    if (barcodeInput) {
                        barcodeInput.focus();
                    }
                }, 300);
            } else if (modalId === 'stockOutModal') {
                setTimeout(() => {
                    const barcodeInput = document.getElementById('stockOutBarcode');
                    if (barcodeInput) {
                        barcodeInput.focus();
                    }
                }, 300);
            }
        };
    })(window.openModal || function() {});
});

async function stockIn() {
    const productId = document.getElementById('stockProduct').value;
    const qty = document.getElementById('stockQty').value;
    const supplierId = document.getElementById('stockSupplier').value;
    const batchNumber = document.getElementById('stockBatchNumber').value.trim();
    const expiryDate = document.getElementById('stockExpiryDate').value;
    const costPrice = document.getElementById('stockCostPrice').value;
    const sellingPrice = document.getElementById('stockSellingPrice').value;

    const prodEl = document.getElementById('stockProduct');
    const qtyEl = document.getElementById('stockQty');
    if (prodEl) prodEl.style.borderColor = '';
    if (qtyEl) qtyEl.style.borderColor = '';

    let hasError = false;
    if (!productId) {
        if (prodEl) {
            prodEl.style.borderColor = 'var(--danger)';
            prodEl.focus();
        }
        hasError = true;
    }
    if (!qty || parseInt(qty) <= 0) {
        if (qtyEl) {
            qtyEl.style.borderColor = 'var(--danger)';
            if (!hasError) qtyEl.focus();
        }
        hasError = true;
    }
    if (hasError) {
        showToast('ກະລຸນາເລືອກສິນຄ້າ ແລະ ປ້ອນຈຳນວນທີ່ຖືກຕ້ອງ', 'warning');
        return;
    }
    const btn = document.getElementById('stockInBtn');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...'; }

    try {
        const result = await fetchAPI(`${BASE_URL}api/products.php`, {
            method: 'PUT',
            body: JSON.stringify({ 
                action: 'stock_in', 
                product_id: parseInt(productId), 
                quantity: parseInt(qty), 
                supplier_id: supplierId ? parseInt(supplierId) : null,
                note: document.getElementById('stockNote').value,
                batch_number: batchNumber,
                expiry_date: expiryDate,
                cost_price: costPrice ? parseFloat(costPrice) : 0,
                selling_price: sellingPrice ? parseFloat(sellingPrice) : 0
            }),
        });
        if (result.success) {
            showToast('ນຳເຂົ້າສິນຄ້າສຳເລັດ', 'success');
            closeModal('stockInModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
    }
}

async function viewProductBatches(productId, productName) {
    document.getElementById('batchModalProductName').textContent = productName;
    const body = document.getElementById('productBatchesTableBody');
    body.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> ກຳລັງໂຫຼດຂໍ້ມູນ Lot...</td></tr>`;
    openModal('productBatchesModal');

    try {
        const result = await fetchAPI(`${BASE_URL}api/products.php?action=get_batches&product_id=${productId}`);
        if (result.success && result.batches) {
            body.innerHTML = '';
            if (result.batches.length === 0) {
                body.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--text-light); padding:20px;">ບໍ່ມີລາຍການ Lot/Batch ສໍາລັບຢານີ້</td></tr>`;
                return;
            }
            
            result.batches.forEach(b => {
                const isExpired = new Date(b.expiry_date) < new Date();
                const isExpiring = !isExpired && (new Date(b.expiry_date) <= new Date(Date.now() + 90 * 24 * 60 * 60 * 1000));
                
                let badgeClass = 'badge-success';
                let labelText = 'ປົກກະຕິ';
                if (isExpired) {
                    badgeClass = 'badge-danger';
                    labelText = 'ໝົດອາຍຸ';
                } else if (isExpiring) {
                    badgeClass = 'badge-warning';
                    labelText = 'ໃກ້ໝົດອາຍຸ';
                }
                
                body.innerHTML += `
                    <tr style="vertical-align: middle;">
                        <td><span class="badge badge-info">${escapeHTML(b.batch_number)}</span></td>
                        <td style="text-align:right;">${formatCurrency(b.cost_price)} ກີບ</td>
                        <td style="text-align:right;">${formatCurrency(b.selling_price)} ກີບ</td>
                        <td style="text-align:center; font-weight:700;">${b.quantity}</td>
                        <td style="text-align:center;">${formatDateString(b.expiry_date)}</td>
                        <td><span class="badge ${badgeClass}">${labelText}</span></td>
                    </tr>
                `;
            });
        } else {
            showToast('ບໍ່ສາມາດດຶງຂໍ້ມູນ Lot ໄດ້', 'error');
            closeModal('productBatchesModal');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່', 'error');
        closeModal('productBatchesModal');
    }
}

function escapeHTML(str) {
    return str.replace(/[&<>'"]/g, 
        tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
    );
}

function formatCurrency(val) {
    return new Intl.NumberFormat().format(val);
}

function formatDateString(dateStr) {
    if (!dateStr) return '-';
    const parts = dateStr.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dateStr;
}

async function stockOut() {
    const productId = document.getElementById('stockOutProduct').value;
    const qty = document.getElementById('stockOutQty').value;
    const prodEl = document.getElementById('stockOutProduct');
    const qtyEl = document.getElementById('stockOutQty');
    if (prodEl) prodEl.style.borderColor = '';
    if (qtyEl) qtyEl.style.borderColor = '';

    let hasError = false;
    if (!productId) {
        if (prodEl) {
            prodEl.style.borderColor = 'var(--danger)';
            prodEl.focus();
        }
        hasError = true;
    }
    if (!qty || parseInt(qty) <= 0) {
        if (qtyEl) {
            qtyEl.style.borderColor = 'var(--danger)';
            if (!hasError) qtyEl.focus();
        }
        hasError = true;
    }
    if (hasError) {
        showToast('ກະລຸນາເລືອກສິນຄ້າ ແລະ ປ້ອນຈຳນວນທີ່ຖືກຕ້ອງ', 'warning');
        return;
    }
    const btn = document.getElementById('stockOutBtn');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...'; }

    try {
        const result = await fetchAPI(`${BASE_URL}api/products.php`, {
            method: 'PUT',
            body: JSON.stringify({ action: 'stock_out', product_id: parseInt(productId), quantity: parseInt(qty), note: document.getElementById('stockOutNote').value }),
        });
        if (result.success) {
            showToast('ຕັດສະຕ໋ອກສຳເລັດ', 'success');
            closeModal('stockOutModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
    }
}

// ===================================================
// Tab 5: Barcode Stock Audit & Physical Stock Take
// ===================================================
let activeAuditSession = null;
let activeAuditItems = [];
let auditScanMultiplier = 1;
let currentAuditFilter = 'all';

function playAuditBeep(success = true) {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = success ? 'sine' : 'sawtooth';
        osc.frequency.setValueAtTime(success ? 900 : 250, ctx.currentTime);
        gain.gain.setValueAtTime(0.18, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + (success ? 0.12 : 0.25));
        osc.start();
        osc.stop(ctx.currentTime + (success ? 0.12 : 0.25));
    } catch (e) {}
}

async function loadStockAuditSession() {
    const container = document.getElementById('stockAuditContent');
    if (!container) return;

    container.innerHTML = `
        <div style="text-align:center; padding:50px 20px; color:var(--text-light);">
            <i class="fas fa-spinner fa-spin" style="font-size:36px; color:var(--primary); margin-bottom:14px; display:block;"></i>
            <p style="font-size:0.95rem; margin-bottom:0;">ກຳລັງໂຫຼດຂໍ້ມູນຮອບກວດນັບສະຕ໋ອກ...</p>
        </div>
    `;

    try {
        const res = await fetchAPI(`${BASE_URL}api/stock_audit.php?action=current_session`);
        if (!res || !res.success) {
            container.innerHTML = `<div class="card" style="padding:30px; text-align:center; color:var(--danger);"><i class="fas fa-exclamation-circle" style="font-size:32px; margin-bottom:10px;"></i><p>${res?.message || 'ເກີດຂໍ້ຜິດພາດ'}</p></div>`;
            return;
        }

        if (!res.has_active) {
            activeAuditSession = null;
            activeAuditItems = [];
            await renderNoActiveAuditView(container);
            return;
        }

        activeAuditSession = res.session;
        activeAuditItems = res.items || [];
        renderActiveAuditView(container, res.session, res.summary, activeAuditItems);
    } catch (e) {
        console.error(e);
        container.innerHTML = `<div class="card" style="padding:30px; text-align:center; color:var(--danger);"><p>ເກີດຂໍ້ຜິດພາດ: ${escapeReorderHtml(e.message)}</p></div>`;
    }
}

async function renderNoActiveAuditView(container) {
    let histHtml = '';
    try {
        const histRes = await fetchAPI(`${BASE_URL}api/stock_audit.php?action=history`);
        const history = histRes?.history || [];
        if (history.length > 0) {
            histHtml = `
                <div class="card" style="margin-top:24px;">
                    <div style="font-weight:700; font-size:1.05rem; margin-bottom:14px; color:var(--text-dark); display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-history" style="color:var(--primary);"></i> ປະຫວັດຮອບກວດນັບສະຕ໋ອກທີ່ຜ່ານມາ
                    </div>
                    <div class="table-responsive">
                        <table class="table" style="font-size:0.88rem;">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th>ເລກທີຮອບ</th>
                                    <th>ຊື່ຮອບກວດນັບ</th>
                                    <th>ຜູ້ດຳເນີນການ</th>
                                    <th style="text-align:center;">ລາຍການຕົງກັນ</th>
                                    <th style="text-align:center;">ລາຍການຜົນຕ່າງ</th>
                                    <th style="text-align:right;">ມູນຄ່າຜົນຕ່າງ</th>
                                    <th style="text-align:center;">ສະຖານະ</th>
                                    <th>ວັນທີສຳເລັດ</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${history.map(h => {
                                    const isComp = h.status === 'completed';
                                    const statusBadge = isComp 
                                        ? `<span class="badge badge-success">ສຳເລັດແລ້ວ</span>`
                                        : (h.status === 'in_progress' ? `<span class="badge badge-warning">ກຳລັງນັບ</span>` : `<span class="badge badge-secondary">ຍົກເລີກ</span>`);
                                    return `
                                        <tr>
                                            <td><strong>${escapeReorderHtml(h.audit_code)}</strong></td>
                                            <td>${escapeReorderHtml(h.title)}</td>
                                            <td>${escapeReorderHtml(h.creator_name || 'Admin')}</td>
                                            <td style="text-align:center;"><span class="badge badge-success">${h.matched_items}</span></td>
                                            <td style="text-align:center;"><span class="badge ${h.discrepancy_items > 0 ? 'badge-danger' : 'badge-success'}">${h.discrepancy_items}</span></td>
                                            <td style="text-align:right; font-weight:700; color:${h.total_cost_variance < 0 ? 'var(--danger)' : (h.total_cost_variance > 0 ? 'var(--primary)' : 'var(--success)')};">
                                                ${formatCurrency(h.total_cost_variance)} ₭
                                            </td>
                                            <td style="text-align:center;">${statusBadge}</td>
                                            <td>${h.reconciled_at ? new Date(h.reconciled_at).toLocaleString('lo-LA') : '-'}</td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
    } catch (e) {}

    container.innerHTML = `
        <div class="card" style="text-align:center; padding:50px 24px; border:2px dashed #86efac; border-radius:14px; background:#f0fdf4;">
            <div style="width:70px; height:70px; background:#dcfce7; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                <i class="fas fa-clipboard-check" style="font-size:32px; color:#16a34a;"></i>
            </div>
            <h3 style="font-size:1.35rem; font-weight:800; color:#166534; margin-bottom:8px;">
                ໂໝດກວດນັບສະຕ໋ອກຕົວຈິງດ້ວຍເຄື່ອງສະແກນບາໂຄດ (Physical Stock Take)
            </h3>
            <p style="color:#4b5563; max-width:600px; margin:0 auto 20px; font-size:0.95rem; line-height:1.5;">
                ເລີ່ມຕົ້ນຮອບກວດນັບສະຕ໋ອກ ເພື່ອຍິງບາໂຄດນັບຈຳນວນຢາຕົວຈິງເທິງຊັ້ນວາງ ລະບົບຈະຄິດໄລ່ຜົນຕ່າງ (ຂາດ / ເກີນ) ໂດຍອັດຕະໂນມັດ ແລະ ສາມາດກົດປັບປຸງຍອດສະຕ໋ອກໄດ້ໃນ 1 ຄລິກ
            </p>
            <div>
                <button type="button" class="btn btn-primary" onclick="openModal('startAuditModal')" style="padding:10px 24px; font-size:1rem; font-weight:700;">
                    <i class="fas fa-play" style="margin-right:6px;"></i> ເປີດຮອບກວດນັບສະຕ໋ອກໃໝ່
                </button>
            </div>
        </div>
        ${histHtml}
    `;
}

function renderActiveAuditView(container, session, summary, items) {
    const totalProd = summary.total_products || items.length;
    const countedProd = summary.counted_products || 0;
    const matchedProd = summary.matched_products || 0;
    const diffProd = summary.discrepancy_products || 0;
    const netCost = summary.net_cost_variance || 0;

    container.innerHTML = `
        <!-- Audit Header -->
        <div class="card" style="margin-bottom:20px; background:linear-gradient(135deg, #059669 0%, #10b981 100%); color:#fff; border:none; padding:18px 24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                <div>
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                        <span class="badge" style="background:rgba(255,255,255,0.25); color:#fff; font-weight:800; font-size:0.85rem;">
                            ${escapeReorderHtml(session.audit_code)}
                        </span>
                        <span class="badge" style="background:#fef08a; color:#854d0e; font-weight:700; font-size:0.75rem;">
                            <i class="fas fa-spinner fa-spin"></i> ກຳລັງກວດນັບ (In Progress)
                        </span>
                    </div>
                    <h3 style="font-size:1.3rem; font-weight:800; color:#fff; margin:0;">
                        ${escapeReorderHtml(session.title)}
                    </h3>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn btn-white btn-sm" onclick="exportAuditExcel()" style="font-weight:700; color:#059669;">
                        <i class="fas fa-file-excel"></i> ສົ່ງອອກ Excel
                    </button>
                    <button type="button" class="btn btn-white btn-sm" onclick="cancelStockAudit(${session.id})" style="color:var(--danger); font-weight:700;">
                        <i class="fas fa-times"></i> ຍົກເລີກຮອບ
                    </button>
                    <button type="button" class="btn btn-sm" onclick="reconcileStockAudit(${session.id})" style="background:#1e293b; color:#fff; font-weight:800; padding:8px 16px; border:none; box-shadow:0 4px 10px rgba(0,0,0,0.2);">
                        <i class="fas fa-check-double" style="color:#4ade80;"></i> ປັບປຸງຍອດສະຕ໋ອກຕົວຈິງ (1-Click Reconcile)
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary KPI Grid -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom:20px;">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-boxes"></i></div>
                <div class="stat-info">
                    <h4>ລາຍການທັງໝົດ</h4>
                    <span class="stat-value" id="kpiAuditTotal">${totalProd}</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-barcode"></i></div>
                <div class="stat-info">
                    <h4>ນັບແລ້ວ</h4>
                    <span class="stat-value" id="kpiAuditCounted">${countedProd}</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h4>ຕົງກັນ (Matched)</h4>
                    <span class="stat-value" id="kpiAuditMatched">${matchedProd}</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon coral"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-info">
                    <h4>ມີຜົນຕ່າງ (ຂາດ/ເກີນ)</h4>
                    <span class="stat-value" id="kpiAuditDiff">${diffProd}</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-coins"></i></div>
                <div class="stat-info">
                    <h4>ມູນຄ່າຜົນຕ່າງລວມ</h4>
                    <span class="stat-value" id="kpiAuditCost" style="font-size:1.3rem; color:${netCost < 0 ? 'var(--danger)' : (netCost > 0 ? 'var(--primary)' : 'var(--success)')};">
                        ${formatCurrency(netCost)} ₭
                    </span>
                </div>
            </div>
        </div>

        <!-- Barcode Scanner Box -->
        <div class="card" style="padding:18px 20px; margin-bottom:20px; background:#f0fdf4; border:2px solid #86efac; border-radius:12px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div style="flex:1; min-width:280px;">
                    <label class="form-label" style="font-weight:700; color:#166534; display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                        <i class="fas fa-barcode"></i> ສະແກນບາໂຄດນັບສິນຄ້າ (ຍິງບາໂຄດເທິງຊັ້ນວາງແລ້ວລະບົບຈະນັບອັດຕະໂນມັດ)
                    </label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-barcode input-left-icon" style="color:#16a34a; font-size:18px;"></i>
                        <input type="text" id="auditBarcodeInput" class="form-control" placeholder="ສະແກນບາໂຄດ ຫຼື ປ້ອນຊື່ສິນຄ້າແລ້ວກົດ Enter..." style="font-size:18px; font-weight:700; height:48px; border-color:#86efac; padding-left:42px;" onkeydown="handleAuditKeydown(event)">
                    </div>
                </div>
                <div style="display:flex; align-items:flex-end; gap:6px;">
                    <div style="font-size:0.85rem; font-weight:700; color:#166534; margin-right:4px; margin-bottom:10px;">ນັບເທື່ອລະ:</div>
                    <button type="button" class="btn btn-sm btn-multiplier ${auditScanMultiplier === 1 ? 'btn-primary' : 'btn-white'}" onclick="setAuditMultiplier(1, this)">+1</button>
                    <button type="button" class="btn btn-sm btn-multiplier ${auditScanMultiplier === 5 ? 'btn-primary' : 'btn-white'}" onclick="setAuditMultiplier(5, this)">+5</button>
                    <button type="button" class="btn btn-sm btn-multiplier ${auditScanMultiplier === 10 ? 'btn-primary' : 'btn-white'}" onclick="setAuditMultiplier(10, this)">+10</button>
                    <button type="button" class="btn btn-sm btn-multiplier ${auditScanMultiplier === 20 ? 'btn-primary' : 'btn-white'}" onclick="setAuditMultiplier(20, this)">+20</button>
                </div>
            </div>
            <div id="auditLastScanNotice" style="margin-top:10px; font-size:0.9rem; color:#166534; font-weight:700; display:none;">
                <i class="fas fa-check-circle"></i> <span id="auditLastScanText"></span>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="toolbar" style="flex-wrap:wrap; gap:12px; margin-bottom:14px;">
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                <span style="font-size:0.85rem; color:var(--text-light); font-weight:600;">ກັ່ນຕອງ:</span>
                <button type="button" class="btn btn-sm btn-outline audit-filter-btn ${currentAuditFilter === 'all' ? 'active' : ''}" onclick="filterAuditItems('all', this)">ທັງໝົດ (${totalProd})</button>
                <button type="button" class="btn btn-sm btn-outline audit-filter-btn ${currentAuditFilter === 'counted' ? 'active' : ''}" onclick="filterAuditItems('counted', this)">ນັບແລ້ວ (${countedProd})</button>
                <button type="button" class="btn btn-sm btn-outline audit-filter-btn ${currentAuditFilter === 'matched' ? 'active' : ''}" onclick="filterAuditItems('matched', this)" style="color:var(--success); border-color:var(--success);">ຕົງກັນ (${matchedProd})</button>
                <button type="button" class="btn btn-sm btn-outline audit-filter-btn ${currentAuditFilter === 'diff' ? 'active' : ''}" onclick="filterAuditItems('diff', this)" style="color:var(--danger); border-color:var(--danger);">ມີຜົນຕ່າງ (${diffProd})</button>
            </div>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="ຄົ້ນຫາໃນຕາຕະລາງ..." oninput="filterTable('auditTableSearch','auditItemsTable')" id="auditTableSearch">
            </div>
        </div>

        <!-- Audit Items Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="table" id="auditItemsTable" style="font-size:0.88rem;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="width:35px;">#</th>
                            <th>ຊື່ສິນຄ້າ / ບາໂຄດ</th>
                            <th style="text-align:center; width:110px;">ຍອດໃນລະບົບ</th>
                            <th style="text-align:center; width:170px;">ຍອດນັບຕົວຈິງ</th>
                            <th style="text-align:center; width:120px;">ຜົນຕ່າງ</th>
                            <th style="text-align:right; width:110px;">ລາຄາທຶນ</th>
                            <th style="text-align:right; width:130px;">ມູນຄ່າຜົນຕ່າງ</th>
                            <th style="text-align:center; width:100px;">ເວລາສະແກນ</th>
                        </tr>
                    </thead>
                    <tbody id="auditTableBody">
                        ${renderAuditTableRows(items)}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    setTimeout(() => {
        const inp = document.getElementById('auditBarcodeInput');
        if (inp) inp.focus();
    }, 100);
}

function renderAuditTableRows(items) {
    if (!items || items.length === 0) {
        return `<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-light);">ບໍ່ມີລາຍການສິນຄ້າ</td></tr>`;
    }

    return items.map((it, idx) => {
        const sys = parseInt(it.system_quantity, 10);
        const counted = parseInt(it.counted_quantity, 10);
        const diff = counted - sys;
        const cost = parseFloat(it.cost_price) || 0;
        const costDiff = diff * cost;

        let diffBadge = '';
        if (counted === 0 && sys > 0) {
            diffBadge = `<span class="badge badge-secondary" style="font-size:0.75rem;">ຍັງບໍ່ທັນນັບ</span>`;
        } else if (diff === 0) {
            diffBadge = `<span class="badge badge-success" style="font-size:0.75rem;"><i class="fas fa-check"></i> ຕົງກັນ</span>`;
        } else if (diff < 0) {
            diffBadge = `<span class="badge badge-danger" style="font-size:0.75rem;"><i class="fas fa-arrow-down"></i> ຂາດ ${Math.abs(diff)}</span>`;
        } else {
            diffBadge = `<span class="badge badge-info" style="font-size:0.75rem;"><i class="fas fa-arrow-up"></i> ເກີນ +${diff}</span>`;
        }

        const isMatched = (diff === 0 && counted > 0);
        const hasDiff = (diff !== 0);

        return `
            <tr style="vertical-align: middle;" id="audit_row_${it.id}" 
                data-counted="${counted > 0 ? '1' : '0'}" 
                data-matched="${isMatched ? '1' : '0'}" 
                data-diff="${hasDiff ? '1' : '0'}">
                <td>${idx + 1}</td>
                <td>
                    <div style="font-weight:700; color:var(--text-dark);">${escapeReorderHtml(it.product_name)}</div>
                    <div style="font-size:0.78rem; color:var(--text-light);">
                        ${it.barcode ? `<i class="fas fa-barcode"></i> ${escapeReorderHtml(it.barcode)} | ` : ''}
                        <span>${escapeReorderHtml(it.category_name || 'ທົ່ວໄປ')}</span>
                    </div>
                </td>
                <td style="text-align:center; font-weight:700;">
                    ${sys.toLocaleString()} <span style="font-size:0.78rem; color:var(--text-light);">${escapeReorderHtml(it.unit)}</span>
                </td>
                <td style="text-align:center;">
                    <div style="display:inline-flex; align-items:center; gap:4px;">
                        <button type="button" class="btn btn-sm btn-white" onclick="adjustItemCount(${it.id}, -1)" style="padding:2px 8px; font-weight:700;">-</button>
                        <input type="number" min="0" class="form-control" id="audit_qty_${it.id}" value="${counted}" 
                            style="width:70px; text-align:center; font-weight:700; padding:4px;" 
                            onchange="saveItemCountDirectly(${it.id}, this.value)">
                        <button type="button" class="btn btn-sm btn-white" onclick="adjustItemCount(${it.id}, 1)" style="padding:2px 8px; font-weight:700;">+</button>
                        <span style="font-size:0.78rem; color:var(--text-light); margin-left:2px;">${escapeReorderHtml(it.unit)}</span>
                    </div>
                </td>
                <td style="text-align:center;" id="audit_diff_${it.id}">
                    ${diffBadge}
                </td>
                <td style="text-align:right;">
                    ${formatCurrency(cost)} ₭
                </td>
                <td style="text-align:right; font-weight:700; color:${costDiff < 0 ? 'var(--danger)' : (costDiff > 0 ? 'var(--primary)' : 'var(--success)')};" id="audit_costdiff_${it.id}">
                    ${formatCurrency(costDiff)} ₭
                </td>
                <td style="text-align:center; font-size:0.78rem; color:var(--text-light);" id="audit_time_${it.id}">
                    ${it.scanned_at ? it.scanned_at.split(' ')[1] : '-'}
                </td>
            </tr>
        `;
    }).join('');
}

function setAuditMultiplier(val, btn) {
    auditScanMultiplier = val;
    document.querySelectorAll('.btn-multiplier').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-white');
    });
    if (btn) {
        btn.classList.remove('btn-white');
        btn.classList.add('btn-primary');
    }
    const inp = document.getElementById('auditBarcodeInput');
    if (inp) inp.focus();
}

function handleAuditKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = e.target.value.trim();
        if (code) {
            submitAuditScan(code, auditScanMultiplier);
            e.target.value = '';
        }
    }
}

async function submitAuditScan(barcode, qty = 1) {
    try {
        const res = await fetchAPI(`${BASE_URL}api/stock_audit.php?action=scan`, {
            method: 'POST',
            body: JSON.stringify({
                audit_id: activeAuditSession?.id || 0,
                barcode: barcode,
                quantity: qty
            })
        });

        if (res && res.success) {
            playAuditBeep(true);
            const notice = document.getElementById('auditLastScanNotice');
            const noticeTxt = document.getElementById('auditLastScanText');
            if (notice && noticeTxt) {
                noticeTxt.textContent = res.message;
                notice.style.display = 'block';
                setTimeout(() => { if (notice) notice.style.display = 'none'; }, 3000);
            }
            showToast(res.message, 'success', 1500);

            // Reload session data to update KPI and table
            loadStockAuditSession();
        } else {
            playAuditBeep(false);
            showToast(res?.message || 'ບໍ່ພົບສິນຄ້າ', 'warning');
        }
    } catch (e) {
        playAuditBeep(false);
        showToast('ເກີດຂໍ້ຜິດພາດ: ' + e.message, 'error');
    }
}

async function adjustItemCount(itemId, delta) {
    const inp = document.getElementById(`audit_qty_${itemId}`);
    if (!inp) return;
    const current = parseInt(inp.value, 10) || 0;
    const nextVal = Math.max(0, current + delta);
    inp.value = nextVal;
    await saveItemCountDirectly(itemId, nextVal);
}

async function saveItemCountDirectly(itemId, newQty) {
    const qty = Math.max(0, parseInt(newQty, 10) || 0);
    try {
        const res = await fetchAPI(`${BASE_URL}api/stock_audit.php?action=set_quantity`, {
            method: 'POST',
            body: JSON.stringify({
                item_id: itemId,
                counted_quantity: qty
            })
        });
        if (res && res.success) {
            playAuditBeep(true);
            loadStockAuditSession();
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
    }
}

function filterAuditItems(filter, btn) {
    currentAuditFilter = filter;
    document.querySelectorAll('.audit-filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const rows = document.querySelectorAll('#auditItemsTable tbody tr');
    rows.forEach(r => {
        if (filter === 'all') {
            r.style.display = '';
        } else if (filter === 'counted') {
            r.style.display = (r.getAttribute('data-counted') === '1') ? '' : 'none';
        } else if (filter === 'matched') {
            r.style.display = (r.getAttribute('data-matched') === '1') ? '' : 'none';
        } else if (filter === 'diff') {
            r.style.display = (r.getAttribute('data-diff') === '1') ? '' : 'none';
        }
    });
}

async function submitStartAudit(e) {
    e.preventDefault();
    const title = document.getElementById('auditTitleInput').value.trim();
    const catId = parseInt(document.getElementById('auditCategorySelect').value, 10) || 0;
    const note = document.getElementById('auditNoteInput').value.trim();
    const btn = document.getElementById('startAuditBtn');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງເປີດຮອບ...';

    try {
        const res = await fetchAPI(`${BASE_URL}api/stock_audit.php?action=start_session`, {
            method: 'POST',
            body: JSON.stringify({
                title: title,
                category_id: catId,
                note: note
            })
        });

        if (res && res.success) {
            closeModal('startAuditModal');
            showToast(res.message, 'success');
            loadStockAuditSession();
        } else {
            showToast(res?.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (err) {
        showToast('ເກີດຂໍ້ຜິດພາດ: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
}

async function cancelStockAudit(auditId) {
    if (typeof Swal !== 'undefined') {
        const confirm = await Swal.fire({
            title: 'ຍົກເລີກຮອບກວດນັບ?',
            text: 'ຂໍ້ມູນການນັບໃນຮອບນີ້ຈະບໍ່ຖືກນຳໄປປັບປຸງຍອດສະຕ໋ອກ ທ່ານແນ່ໃຈບໍ່?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ຍົກເລີກຮອບກວດນັບ',
            cancelButtonText: 'ກັບຄືນ',
            confirmButtonColor: 'var(--danger)',
            cancelButtonColor: '#64748b'
        });
        if (!confirm.isConfirmed) return;
    }

    try {
        const res = await fetchAPI(`${BASE_URL}api/stock_audit.php?action=cancel_session`, {
            method: 'POST',
            body: JSON.stringify({ audit_id: auditId })
        });
        if (res && res.success) {
            showToast(res.message, 'success');
            loadStockAuditSession();
        } else {
            showToast(res?.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດ: ' + e.message, 'error');
    }
}

async function reconcileStockAudit(auditId) {
    if (typeof Swal !== 'undefined') {
        const confirm = await Swal.fire({
            title: 'ຢືນຢັນປັບປຸງຍອດສະຕ໋ອກຕົວຈິງ?',
            html: `
                <div style="text-align:left; font-size:0.95rem; line-height:1.6; color:#334155;">
                    <p style="margin-bottom:8px;">ລະບົບຈະດຳເນີນການດັ່ງນີ້:</p>
                    <ul style="padding-left:20px; margin-bottom:12px;">
                        <li>ປັບປຸງຈຳນວນສະຕ໋ອກສິນຄ້າໃນລະບົບ ໃຫ້ກົງກັບ <b>ຍອດທີ່ນັບໄດ້ຕົວຈິງ</b></li>
                        <li>ບັນທຶກປະຫວັດການປັບປຸງ (Adjust Logs) ເຂົ້າສູ່ລະບົບປະຫວັດສະຕ໋ອກ</li>
                        <li>ປິດຮອບກວດນັບນີ້ເປັນສະຖານະ <b>ສຳເລັດ (Completed)</b></li>
                    </ul>
                    <div style="background:#fee2e2; color:#991b1b; padding:8px 12px; border-radius:6px; font-size:0.88rem;">
                        <i class="fas fa-exclamation-triangle"></i> ກະລຸນາກວດສອບຄວາມຖືກຕ້ອງກ່ອນຢືນຢັນ
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check-double"></i> ຢືນຢັນປັບປຸງສະຕ໋ອກ',
            cancelButtonText: 'ກວດສອບຕໍ່',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        });
        if (!confirm.isConfirmed) return;
    }

    try {
        showToast('ກຳລັງປັບປຸງຍອດສະຕ໋ອກ...', 'info');
        const res = await fetchAPI(`${BASE_URL}api/stock_audit.php?action=reconcile`, {
            method: 'POST',
            body: JSON.stringify({ audit_id: auditId })
        });

        if (res && res.success) {
            if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    icon: 'success',
                    title: 'ປັບປຸງຍອດສະຕ໋ອກສຳເລັດ!',
                    html: `
                        <div style="text-align:left; padding:12px 16px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;">
                            <p style="margin-bottom:6px; font-weight:700; color:#166534; font-size:1.05rem;">
                                <i class="fas fa-check-circle"></i> ເລກທີຮອບ: ${res.audit_code}
                            </p>
                            <p style="margin-bottom:4px; font-size:0.9rem;">
                                ລາຍການທີ່ຕົງກັນ: <b>${res.matched_count}</b> ລາຍການ
                            </p>
                            <p style="margin-bottom:4px; font-size:0.9rem;">
                                ລາຍການທີ່ປັບປຸງຍອດ (ຜົນຕ່າງ): <b style="color:var(--danger);">${res.discrepancy_count}</b> ລາຍການ
                            </p>
                            <p style="margin-bottom:0; font-size:0.9rem;">
                                ມູນຄ່າຜົນຕ່າງລວມ: <b style="color:var(--primary); font-size:1.05rem;">${formatCurrency(res.total_cost_variance)} ກີບ</b>
                            </p>
                        </div>
                    `,
                    confirmButtonText: 'ຕົກລົງ',
                    confirmButtonColor: 'var(--primary)'
                });
            } else {
                showToast(res.message, 'success');
            }
            loadStockAuditSession();
        } else {
            showToast(res?.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດ: ' + e.message, 'error');
    }
}

async function exportAuditExcel() {
    if (typeof XLSX === 'undefined') {
        showToast('ບໍ່ພົບໂປຣແກຣມສົ່ງອອກ Excel', 'warning');
        return;
    }
    if (!activeAuditItems || activeAuditItems.length === 0) {
        showToast('ບໍ່ມີຂໍ້ມູນໃນຮອບກວດນັບ', 'warning');
        return;
    }

    try {
        const headers = ['ບາໂຄດ', 'ຊື່ສິນຄ້າ / ຢາ', 'ໝວດໝູ່', 'ຍອດໃນລະບົບ', 'ຍອດນັບຕົວຈິງ', 'ຜົນຕ່າງ', 'ຫົວໜ່ວຍ', 'ລາຄາຕົ້ນທຶນ', 'ມູນຄ່າຜົນຕ່າງ', 'ສະຖານະ'];
        const rows = [headers];

        activeAuditItems.forEach(it => {
            const sys = parseInt(it.system_quantity, 10);
            const counted = parseInt(it.counted_quantity, 10);
            const diff = counted - sys;
            const cost = parseFloat(it.cost_price) || 0;
            const costDiff = diff * cost;
            let status = 'ຕົງກັນ';
            if (counted === 0 && sys > 0) status = 'ຍັງບໍ່ທັນນັບ';
            else if (diff < 0) status = `ຂາດ ${Math.abs(diff)}`;
            else if (diff > 0) status = `ເກີນ +${diff}`;

            rows.push([
                it.barcode || '',
                it.product_name,
                it.category_name || 'ທົ່ວໄປ',
                sys,
                counted,
                diff,
                it.unit || 'ແຜງ',
                cost,
                costDiff,
                status
            ]);
        });

        const ws = XLSX.utils.aoa_to_sheet(rows);
        ws['!cols'] = [
            { wch: 18 }, { wch: 34 }, { wch: 18 }, { wch: 14 }, { wch: 14 },
            { wch: 12 }, { wch: 10 }, { wch: 14 }, { wch: 16 }, { wch: 16 }
        ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Stock Audit');
        const filename = `stock_audit_${activeAuditSession?.audit_code || 'export'}.xlsx`;
        XLSX.writeFile(wb, filename);
        showToast(`ສົ່ງອອກ ${filename} ສຳເລັດແລ້ວ`, 'success');
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການສົ່ງອອກ: ' + e.message, 'error');
    }
}

// Export Stock to Excel
async function exportStockToExcel() {
    if (typeof XLSX === 'undefined') {
        window.location.href = `${BASE_URL}api/products_export.php?format=csv`;
        return;
    }
    try {
        showToast('ກຳລັງກຽມໄຟລ໌ Excel ສະຕ໋ອກສິນຄ້າ...', 'info');
        const res = await fetchAPI(`${BASE_URL}api/products_export.php?format=json`);
        if (!res || !res.success || !res.products || res.products.length === 0) {
            showToast('ບໍ່ມີຂໍ້ມູນສິນຄ້າໃຫ້ສົ່ງອອກ', 'warning');
            return;
        }

        const headers = ['ບາໂຄດ', 'ຊື່ສິນຄ້າ / ຢາ', 'ໝວດໝູ່', 'ລາຄາທຶນ', 'ລາຄາຂາຍ', 'ຈຳນວນສະຕ໋ອກ', 'ຂັ້ນຕ່ຳສະຕ໋ອກ', 'ຫົວໜ່ວຍ', 'ວັນໝົດອາຍຸ', 'ສະຖານະສະຕ໋ອກ'];
        const rows = [headers];

        const today = new Date().toISOString().split('T')[0];
        res.products.forEach(p => {
            const stock = parseInt(p.stock_quantity || 0, 10);
            const min = parseInt(p.min_stock || 5, 10);
            let status = 'ປົກກະຕິ';
            if (stock === 0) status = 'ໝົດສະຕ໋ອກ';
            else if (stock <= min) status = 'ໃກ້ໝົດສະຕ໋ອກ';

            if (p.expiry_date && p.expiry_date < today) {
                status = 'ໝົດອາຍຸແລ້ວ';
            }

            rows.push([
                p.barcode ? String(p.barcode).trim() : '',
                p.name || '',
                p.category_name || 'ອື່ນໆ',
                parseFloat(p.cost_price || 0),
                parseFloat(p.selling_price || 0),
                stock,
                min,
                p.unit || 'ແຜງ',
                p.expiry_date || '',
                status
            ]);
        });

        const ws = XLSX.utils.aoa_to_sheet(rows);
        ws['!cols'] = [
            { wch: 18 }, { wch: 34 }, { wch: 18 }, { wch: 14 }, { wch: 14 },
            { wch: 15 }, { wch: 14 }, { wch: 12 }, { wch: 14 }, { wch: 16 }
        ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'ສະຕ໋ອກສິນຄ້າ');
        const filename = `inventory_stock_${today}.xlsx`;
        XLSX.writeFile(wb, filename);

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'ສົ່ງອອກສະຕ໋ອກສຳເລັດ!',
                text: `ສົ່ງອອກລາຍການສະຕ໋ອກທັງໝົດ ${res.products.length} ລາຍການ ເປັນໄຟລ໌ ${filename} ຮຽບຮ້ອຍແລ້ວ.`,
                confirmButtonColor: 'var(--primary)',
                confirmButtonText: 'ຕົກລົງ'
            });
        } else {
            showToast('ສົ່ງອອກສະຕ໋ອກສຳເລັດແລ້ວ', 'success');
        }
    } catch (e) {
        console.error(e);
        showToast('ເກີດຂໍ້ຜິດພາດໃນການສົ່ງອອກ: ' + e.message, 'error');
    }
}
</script>

<!-- SheetJS Local Bundle -->
<script src="<?= BASE_URL ?>assets/vendor/xlsx.full.min.js"></script>

<?php include 'includes/footer.php'; ?>
