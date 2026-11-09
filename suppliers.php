<?php
/**
 * ຜູ້ສະໜອງ - Supplier Management
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();
if (!hasRole(['admin', 'manager', 'pharmacist']) && !hasPermission('can_manage_po')) {
    header('Location: ' . BASE_URL . 'pos.php');
    exit;
}

$pageTitle = 'ຜູ້ສະໜອງ';

try {
    $db = getDB();
    $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    $suppliers = [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">ຜູ້ສະໜອງຢາ</h1>
    </div>

    <div class="content-area">
        <div class="toolbar">
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> ເພີ່ມຜູ້ສະໜອງ
            </button>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="ຄົ້ນຫາຜູ້ສະໜອງ..." oninput="filterTable('supplierSearch','supplierTable')" id="supplierSearch">
            </div>
        </div>

        <?php if (empty($suppliers)): ?>
        <div class="empty-state" style="padding:60px 20px; text-align:center;">
            <i class="fas fa-truck" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 12px; display: block;"></i>
            <h4 style="margin-bottom:8px; color:var(--text-main);">ບໍ່ມີຂໍ້ມູນຜູ້ສະໜອງ</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">ຍັງບໍ່ມີລາຍການຜູ້ສະໜອງຢາໃນລະບົບ</p>
            <button class="btn btn-primary btn-sm" onclick="openAddModal()">
                <i class="fas fa-plus"></i> ເພີ່ມຜູ້ສະໜອງໃໝ່
            </button>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table" id="supplierTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ຊື່ຜູ້ສະໜອງ / ບໍລິສັດ</th>
                            <th>ເບີໂທຕິດຕໍ່</th>
                            <th>ອີເມວ</th>
                            <th>ທີ່ຢູ່</th>
                            <th>ໝາຍເຫດ</th>
                            <th>ຈັດການ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $i => $s): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= sanitize($s['name']) ?></strong></td>
                            <td><?= sanitize($s['phone'] ?? '-') ?></td>
                            <td><?= sanitize($s['email'] ?? '-') ?></td>
                            <td><?= sanitize($s['address'] ?? '-') ?></td>
                            <td><span style="font-size:0.85rem; color:var(--text-medium);"><?= sanitize($s['note'] ?? '-') ?></span></td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <button class="btn-icon btn-white btn-sm" onclick="editSupplier(<?= $s['id'] ?>)" title="ແກ້ໄຂ"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon btn-white btn-sm" style="color:var(--danger);" onclick="deleteSupplier(<?= $s['id'] ?>)" title="ລຶບ"><i class="fas fa-trash"></i></button>
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

<!-- Supplier Modal -->
<div class="modal-overlay" id="supplierModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">ເພີ່ມຜູ້ສະໜອງໃໝ່</h3>
            <button class="modal-close" onclick="closeModal('supplierModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="supplierForm" onsubmit="saveSupplier(event)">
                <input type="hidden" id="supplierId" value="">
                <div class="form-group">
                    <label class="form-label">ຊື່ຜູ້ສະໜອງ / ບໍລິສັດ *</label>
                    <input type="text" class="form-control" id="supplierName" required placeholder="ປ້ອນຊື່ບໍລິສັດ ຫຼື ຜູ້ສະໜອງ...">
                </div>
                <div class="form-group">
                    <label class="form-label">ເບີໂທຕິດຕໍ່</label>
                    <input type="text" class="form-control" id="supplierPhone" placeholder="ປ້ອນເບີໂທ...">
                </div>
                <div class="form-group">
                    <label class="form-label">ອີເມວ</label>
                    <input type="email" class="form-control" id="supplierEmail" placeholder="ປ້ອນອີເມວ...">
                </div>
                <div class="form-group">
                    <label class="form-label">ທີ່ຢູ່</label>
                    <textarea class="form-control" id="supplierAddress" rows="2" placeholder="ປ້ອນທີ່ຢູ່..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">ໝາຍເຫດ</label>
                    <textarea class="form-control" id="supplierNote" rows="2" placeholder="ໝາຍເຫດເພີ່ມເຕີມ..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-white" onclick="closeModal('supplierModal')">ຍົກເລີກ</button>
            <button type="button" class="btn btn-primary" id="saveSupplierBtn" onclick="saveSupplier(event)"><i class="fas fa-save"></i> ບັນທຶກ</button>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('supplierForm').reset();
    document.getElementById('supplierId').value = '';
    document.getElementById('modalTitle').textContent = 'ເພີ່ມຜູ້ສະໜອງໃໝ່';
    openModal('supplierModal');
}

async function editSupplier(id) {
    try {
        const result = await fetchAPI(`${BASE_URL}api/suppliers.php?id=${id}`);
        if (result.success && result.supplier) {
            const s = result.supplier;
            document.getElementById('supplierId').value = s.id;
            document.getElementById('supplierName').value = s.name;
            document.getElementById('supplierPhone').value = s.phone || '';
            document.getElementById('supplierEmail').value = s.email || '';
            document.getElementById('supplierAddress').value = s.address || '';
            document.getElementById('supplierNote').value = s.note || '';
            
            document.getElementById('modalTitle').textContent = 'ແກ້ໄຂຂໍ້ມູນຜູ້ສະໜອງ';
            openModal('supplierModal');
        } else {
            showToast('ບໍ່ພົບຂໍ້ມູນຜູ້ສະໜອງ', 'error');
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການດຶງຂໍ້ມູນ', 'error');
    }
}

async function saveSupplier(e) {
    if (e) e.preventDefault();
    const id = document.getElementById('supplierId').value;
    const name = document.getElementById('supplierName').value.trim();
    const phone = document.getElementById('supplierPhone').value;
    const email = document.getElementById('supplierEmail').value;
    const address = document.getElementById('supplierAddress').value;
    const note = document.getElementById('supplierNote').value;
    
    if (!name) {
        const nameInput = document.getElementById('supplierName');
        if (nameInput) {
            nameInput.style.borderColor = 'var(--danger)';
            nameInput.focus();
        }
        showToast('ກະລຸນາປ້ອນຊື່ຜູ້ສະໜອງ', 'warning');
        return;
    }
    const nameInput = document.getElementById('supplierName');
    if (nameInput) nameInput.style.borderColor = '';

    const data = { name, phone, email, address, note };
    if (id) data.id = parseInt(id);
    
    const btn = document.getElementById('saveSupplierBtn');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...'; }

    try {
        const result = await fetchAPI(`${BASE_URL}api/suppliers.php`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
        
        if (result.success) {
            showToast(id ? 'ແກ້ໄຂຂໍ້ມູນສຳເລັດ' : 'ເພີ່ມຜູ້ສະໜອງສຳເລັດ', 'success');
            closeModal('supplierModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການບັນທຶກ', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
    }
}

async function deleteSupplier(id) {
    if (!await confirmDelete('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບຜູ້ສະໜອງນີ້?')) return;
    
    try {
        const result = await fetchAPI(`${BASE_URL}api/suppliers.php?id=${id}`, {
            method: 'DELETE'
        });
        
        if (result.success) {
            showToast('ລຶບຜູ້ສະໜອງສຳເລັດ', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການລຶບ', 'error');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
