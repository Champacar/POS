<?php
/**
 * ໃບສັ່ງຊື້ຢາ - Purchase Orders Management
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();
if (!hasRole(['admin', 'manager', 'pharmacist']) && !hasPermission('can_manage_po')) {
    header('Location: ' . BASE_URL . 'pos.php');
    exit;
}

$pageTitle = 'ໃບສັ່ງຊື້ຢາ';

try {
    $db = getDB();
    $purchaseOrders = $db->query("
        SELECT po.*, s.name as supplier_name
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        ORDER BY po.created_at DESC
    ")->fetchAll();
    
    $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    
    // Fetch all active products, prioritizing low stock items first
    $lowStock = $db->query("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        ORDER BY (p.stock_quantity <= p.min_stock) DESC, p.name ASC
    ")->fetchAll();
} catch (Exception $e) {
    $purchaseOrders = [];
    $suppliers = [];
    $lowStock = [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">ຈັດການໃບສັ່ງຊື້ຢາ</h1>
    </div>

    <div class="content-area">
        <div class="toolbar">
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> ສ້າງໃບສັ່ງຊື້ຢາ
            </button>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="ຄົ້ນຫາໃບສັ່ງຊື້..." oninput="filterTable('poSearch','poTable')" id="poSearch">
            </div>
        </div>

        <?php if (empty($purchaseOrders)): ?>
        <div class="empty-state" style="padding:60px 20px;">
            <i class="fas fa-file-invoice" style="font-size: 48px; opacity: 0.3; margin-bottom: 15px; display: block;"></i>
            <p style="font-size: 1.1rem; font-weight: 600; margin-bottom: 15px;">ບໍ່ມີຂໍ້ມູນໃບສັ່ງຊື້ຢາ</p>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> ສ້າງໃບສັ່ງຊື້ຢາໃໝ່
            </button>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table" id="poTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ເລກທີໃບສັ່ງຊື້</th>
                            <th>ຜູ້ສະໜອງ / ບໍລິສັດ</th>
                            <th>ຍອດລວມ</th>
                            <th>ວັນທີສ້າງ</th>
                            <th>ສະຖານະ</th>
                            <th>ໝາຍເຫດ</th>
                            <th style="text-align:right;">ຈັດການ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchaseOrders as $i => $po): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= sanitize($po['po_number']) ?></strong></td>
                            <td><?= sanitize($po['supplier_name'] ?? 'ບໍ່ລະບຸ') ?></td>
                            <td><strong><?= formatCurrency($po['total_amount']) ?></strong> ກີບ</td>
                            <td><?= formatDateTime($po['created_at']) ?></td>
                            <td>
                                <span class="badge <?= $po['status'] === 'received' ? 'badge-success' : ($po['status'] === 'sent' ? 'badge-info' : ($po['status'] === 'cancelled' ? 'badge-danger' : 'badge-warning')) ?>">
                                    <?php
                                    if ($po['status'] === 'draft') echo 'ສະບັບຮ່າງ';
                                    else if ($po['status'] === 'sent') echo 'ສົ່ງແລ້ວ';
                                    else if ($po['status'] === 'received') echo 'ຮັບເຄື່ອງແລ້ວ';
                                    else if ($po['status'] === 'cancelled') echo 'ຍົກເລີກ';
                                    ?>
                                </span>
                            </td>
                            <td><span style="font-size:0.85rem; color:var(--text-medium);"><?= sanitize($po['note'] ?? '-') ?></span></td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex; gap:6px; align-items:center;">
                                    <?php if ($po['status'] === 'draft' || $po['status'] === 'sent'): ?>
                                    <button class="btn btn-primary btn-sm" onclick="receivePO(<?= $po['id'] ?>)" title="ຮັບສິນຄ້າເຂົ້າສາງ" style="min-height:38px; padding:6px 12px; font-size:0.85rem; font-weight:600;">
                                        <i class="fas fa-box-open"></i> ຮັບສິນຄ້າເຂົ້າສາງ
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn-icon btn-white btn-sm" onclick="printPO(<?= $po['id'] ?>)" title="ພິມໃບສັ່ງຊື້" style="color:var(--primary);">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <a href="<?= BASE_URL ?>barcode_manager.php?source=po&po_id=<?= $po['id'] ?>" class="btn-icon btn-white btn-sm" title="ພິມສະຕິກເກີບາໂຄດຕາມໃບຮັບສິນຄ້າ" style="color:var(--text-dark);display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                                        <i class="fas fa-barcode"></i>
                                    </a>
                                    <button class="btn-icon btn-white btn-sm" style="color:var(--danger);" onclick="deletePO(<?= $po['id'] ?>)" title="ລຶບ">
                                        <i class="fas fa-trash"></i>
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
</main>

<!-- Create Purchase Order Modal -->
<div class="modal-overlay" id="poModal">
    <div class="modal" style="max-width:850px;">
        <div class="modal-header">
            <h3><i class="fas fa-file-invoice" style="margin-right:8px; color:var(--primary);"></i>ສ້າງໃບສັ່ງຊື້ຢາໃໝ່</h3>
            <button class="modal-close" onclick="closeModal('poModal')"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="savePO(event)">
            <div class="modal-body" style="max-height:550px; overflow-y:auto;">
                <div class="form-row-2" style="gap:16px; margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">ເລືອກຜູ້ສະໜອງ *</label>
                        <select class="form-control" id="poSupplier" required onchange="this.style.borderColor=''">
                            <option value="">-- ເລືອກຜູ້ສະໜອງ --</option>
                            <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">ໝາຍເຫດ</label>
                        <input type="text" class="form-control" id="poNote" placeholder="ໝາຍເຫດເພີ່ມເຕີມ...">
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-top:20px; margin-bottom:10px;">
                    <h4 style="font-size:0.95rem; font-weight:700; margin-bottom:0; color:var(--text-dark);">
                        <i class="fas fa-pills" style="color:var(--primary); margin-right:6px;"></i>ລາຍການຢາສຳລັບສັ່ງຊື້ (ຢາໃກ້ໝົດສະແດງກ່ອນ)
                    </h4>
                    <button type="button" class="btn btn-sm btn-white" onclick="filterPoItemsBySupplier()" style="color:var(--primary); font-weight:600; border-color:rgba(0,82,204,0.3);">
                        <i class="fas fa-bolt" style="color:#ffab00;"></i> ດຶງສະເພາະຢາໃກ້ໝົດຂອງຜູ້ສະໜອງນີ້
                    </button>
                </div>

                <?php if (empty($lowStock)): ?>
                <div style="text-align:center; padding:30px; border: 1px dashed var(--border-light); border-radius:10px; color:var(--text-light);">
                    <i class="fas fa-box-open" style="font-size:32px; color:var(--text-light); margin-bottom:10px; display:block;"></i>
                    <span>ບໍ່ມີລາຍການຢາໃນລະບົບ</span>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table" style="font-size:0.88rem;">
                        <thead>
                            <tr>
                                <th style="width:40px;">ເລືອກ</th>
                                <th>ຊື່ຢາ</th>
                                <th style="text-align:center;">ຄົງເຫຼືອ</th>
                                <th style="text-align:center;">ຂັ້ນຕ່ຳ</th>
                                <th style="width:120px;">ລາຄາຕົ້ນທຶນ (ກີບ)</th>
                                <th style="width:100px;">ຈຳນວນສັ່ງຊື້</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStock as $idx => $item): ?>
                            <?php 
                            $isLow = $item['stock_quantity'] <= $item['min_stock'];
                            // Suggest order quantity to reach double min stock
                            $suggestedQty = max(5, ($item['min_stock'] * 2) - $item['stock_quantity']); 
                            ?>
                            <tr style="vertical-align: middle;" data-supplier-id="<?= (int)($item['supplier_id'] ?? 0) ?>" data-is-low="<?= $isLow ? '1' : '0' ?>">
                                <td style="text-align:center;">
                                    <input type="checkbox" class="po-item-checkbox" data-id="<?= $item['id'] ?>" data-name="<?= sanitize($item['name']) ?>" id="chk_<?= $idx ?>" style="width:22px; height:22px; cursor:pointer;">
                                </td>
                                <td>
                                    <label for="chk_<?= $idx ?>" style="cursor:pointer; display:block;">
                                        <strong><?= sanitize($item['name']) ?></strong>
                                        <?php if ($isLow): ?>
                                            <span class="badge badge-warning" style="font-size:0.7rem; margin-left:4px;">(ໃກ້ໝົດ)</span>
                                        <?php endif; ?>
                                        <br>
                                        <span style="font-size:0.75rem; color:var(--text-light);"><?= sanitize($item['unit']) ?></span>
                                    </label>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge <?= $isLow ? 'badge-warning' : 'badge-success' ?>"><?= $item['stock_quantity'] ?></span>
                                </td>
                                <td style="text-align:center;"><?= $item['min_stock'] ?></td>
                                <td>
                                    <input type="number" step="0.01" class="form-control po-item-cost" data-id="<?= $item['id'] ?>" value="<?= $item['cost_price'] ?>" style="padding:4px; font-size:0.85rem; height:auto; text-align:right;">
                                </td>
                                <td>
                                    <input type="number" class="form-control po-item-qty" data-id="<?= $item['id'] ?>" value="<?= $suggestedQty ?>" min="1" style="padding:4px; font-size:0.85rem; height:auto; text-align:center;">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-white" onclick="closeModal('poModal')">ຍົກເລີກ</button>
                <button type="submit" class="btn btn-primary" id="savePoBtn">ສ້າງໃບສັ່ງຊື້ຢາ</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    // Uncheck all items & show all rows
    document.querySelectorAll('.po-item-checkbox').forEach(chk => chk.checked = false);
    document.querySelectorAll('#poModal table tbody tr').forEach(r => r.style.display = '');
    const supplierEl = document.getElementById('poSupplier');
    if (supplierEl) {
        supplierEl.value = '';
        supplierEl.style.borderColor = '';
    }
    document.getElementById('poNote').value = '';
    openModal('poModal');
}

function filterPoItemsBySupplier() {
    const supplierEl = document.getElementById('poSupplier');
    const supplierId = parseInt(supplierEl ? supplierEl.value : 0, 10);
    if (!supplierId) {
        if (supplierEl) {
            supplierEl.style.borderColor = 'var(--danger)';
            supplierEl.focus();
        }
        showToast('ກະລຸນາເລືອກຜູ້ສະໜອງກ່ອນ', 'warning');
        return;
    }
    if (supplierEl) supplierEl.style.borderColor = '';

    const rows = document.querySelectorAll('#poModal table tbody tr');
    let matchedCount = 0;
    rows.forEach(r => {
        const itemSupId = parseInt(r.getAttribute('data-supplier-id') || 0, 10);
        const isLow = r.getAttribute('data-is-low') === '1';
        const chk = r.querySelector('.po-item-checkbox');
        if (itemSupId === supplierId) {
            r.style.display = '';
            if (isLow && chk) {
                chk.checked = true;
                matchedCount++;
            }
        } else {
            r.style.display = 'none';
            if (chk) chk.checked = false;
        }
    });

    if (matchedCount > 0) {
        showToast(`ດຶງ ແລະ ເລືອກຢາໃກ້ໝົດຂອງຜູ້ສະໜອງນີ້ແລ້ວ ${matchedCount} ລາຍການ`, 'info');
    } else {
        showToast(`ບໍ່ພົບຢາໃກ້ໝົດຂອງຜູ້ສະໜອງນີ້ (ຫຼື ສະແດງສະເພາະຢາທີ່ເຊື່ອມໂຍງ)`, 'info');
    }
}

async function savePO(event) {
    event.preventDefault();
    
    const supplierEl = document.getElementById('poSupplier');
    const supplierId = supplierEl ? supplierEl.value : '';
    const note = document.getElementById('poNote').value.trim();
    
    if (!supplierId) {
        if (supplierEl) {
            supplierEl.style.borderColor = 'var(--danger)';
            supplierEl.focus();
        }
        showToast('ກະລຸນາເລືອກຜູ້ສະໜອງຢາ', 'warning');
        return;
    } else if (supplierEl) {
        supplierEl.style.borderColor = '';
    }
    
    // Collect checked items
    const checkedCheckboxes = document.querySelectorAll('.po-item-checkbox:checked');
    if (checkedCheckboxes.length === 0) {
        showToast('ກະລຸນາເລືອກຢ່າງໜ້ອຍ 1 ລາຍການເພື່ອສັ່ງຊື້', 'warning');
        const firstChk = document.querySelector('.po-item-checkbox');
        if (firstChk) firstChk.focus();
        return;
    }
    
    const items = [];
    let totalAmount = 0;
    
    checkedCheckboxes.forEach(chk => {
        const id = chk.dataset.id;
        const name = chk.dataset.name;
        
        // Find quantity and cost price input matching this product ID
        const costInput = document.querySelector(`.po-item-cost[data-id="${id}"]`);
        const qtyInput = document.querySelector(`.po-item-qty[data-id="${id}"]`);
        
        const cost = parseFloat(costInput.value) || 0;
        const qty = parseInt(qtyInput.value) || 1;
        const total = cost * qty;
        totalAmount += total;
        
        items.push({
            product_id: id,
            product_name: name,
            quantity: qty,
            unit_price: cost
        });
    });
    
    const data = {
        supplier_id: supplierId || null,
        note: note,
        total_amount: totalAmount,
        items: items
    };
    
    const btn = document.getElementById('savePoBtn');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...'; }

    try {
        const result = await fetchAPI('api/purchase_orders.php', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        
        if (result.success) {
            showToast(result.message || 'ສ້າງໃບສັ່ງຊື້ຢາສຳເລັດ', 'success');
            closeModal('poModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
    }
}

async function receivePO(poId) {
    const confirmed = await Swal.fire({
        title: 'ຮັບສິນຄ້າເຂົ້າສາງ',
        text: 'ທ່ານຕ້ອງການຢືນຢັນການຮັບສິນຄ້າຈາກໃບສັ່ງຊື້ນີ້ເຂົ້າສາງບໍ່?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ຢືນຢັນຮັບສິນຄ້າ',
        cancelButtonText: 'ຍົກເລີກ',
        customClass: { popup: 'swal2-lao-popup' }
    });
    if (!confirmed.isConfirmed) return;

    try {
        const result = await fetchAPI('api/purchase_orders.php', {
            method: 'PUT',
            body: JSON.stringify({ id: poId, action: 'receive' })
        });
        if (result.success) {
            showToast(result.message || 'ຮັບສິນຄ້າເຂົ້າສາງສຳເລັດ', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່', 'error');
    }
}

async function deletePO(poId) {
    if (!await confirmDelete('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບໃບສັ່ງຊື້ຢານີ້?')) return;
    
    try {
        const result = await fetchAPI(`api/purchase_orders.php?id=${poId}`, {
            method: 'DELETE'
        });
        
        if (result.success) {
            showToast(result.message || 'ລຶບໃບສັ່ງຊື້ຢາສຳເລັດ', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
    }
}

function printPO(poId) {
    const printWindow = window.open(`api/print_po.php?id=${poId}`, '_blank', 'width=800,height=600');
    if (printWindow) {
        printWindow.focus();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
