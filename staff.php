<?php
/**
 * ພະນັກງານ - Staff Management
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'ພະນັກງານ';

try {
    $db = getDB();
    $currentUserRole = $_SESSION['role'] ?? '';
    if ($currentUserRole === 'developer') {
        $staff = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
    } else {
        $staff = $db->query("SELECT * FROM users WHERE role != 'developer' ORDER BY created_at DESC")->fetchAll();
    }
} catch (Exception $e) {
    $staff = [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">ພະນັກງານ</h1>
    </div>

    <div class="content-area">
        <div class="toolbar">
            <button class="btn btn-primary" onclick="openAddStaffModal()">
                <i class="fas fa-plus"></i> ເພີ່ມພະນັກງານ
            </button>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="ຄົ້ນຫາ..." oninput="filterTable('staffSearch','staffTable')" id="staffSearch">
            </div>
        </div>

        <?php if (empty($staff)): ?>
        <div class="empty-state" style="padding:60px 20px; text-align:center;">
            <i class="fas fa-users" style="font-size:3rem; color:var(--text-light); margin-bottom:12px; display:block;"></i>
            <h4 style="margin-bottom:8px; color:var(--text-dark);">ບໍ່ມີຂໍ້ມູນພະນັກງານ</h4>
            <p style="color:var(--text-medium); margin-bottom:20px;">ຍັງບໍ່ມີລາຍຊື່ພະນັກງານໃນລະບົບ</p>
            <button class="btn btn-primary btn-sm" onclick="openAddStaffModal()">
                <i class="fas fa-user-plus"></i> ເພີ່ມພະນັກງານໃໝ່
            </button>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table" id="staffTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ຊື່-ນາມສະກຸນ</th>
                            <th>ຊື່ຜູ້ໃຊ້</th>
                            <th>ບົດບາດ</th>
                            <th>ເບີໂທ</th>
                            <th>ສະຖານະ</th>
                            <th>ເຂົ້າລະບົບລ່າສຸດ</th>
                            <th>ຈັດການ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff as $i => $s): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <?php 
                                    $stAvatar = getUserAvatarUrl($s['avatar'] ?? null);
                                    if ($stAvatar): 
                                    ?>
                                        <img src="<?= $stAvatar ?>?v=<?= time() ?>" alt="Avatar" style="width:34px; height:34px; border-radius:50%; object-fit:cover; border:1px solid #e2e8f0; flex-shrink:0;">
                                    <?php else: ?>
                                        <div style="width:34px; height:34px; border-radius:50%; background:#e8f5e9; color:#2e7d32; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; border:1px solid #c8e6c9; flex-shrink:0;">
                                            <?= mb_substr($s['full_name'], 0, 1) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= sanitize($s['full_name']) ?></strong>
                                        <?php if (!empty($s['email'])): ?>
                                            <div style="font-size:0.75rem; color:var(--text-medium);"><?= sanitize($s['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= sanitize($s['username']) ?></td>
                            <td>
                                <?php
                                $displayRole = getRoleLabel($s['role']);
                                $badgeClass = getRoleBadgeClass($s['role']);
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($displayRole) ?></span>
                                <?php
                                $perms = !empty($s['permissions']) ? json_decode($s['permissions'], true) : [];
                                if (!empty($perms['can_view_profit']) && !in_array($s['role'], ['admin', 'manager', 'developer'])):
                                ?>
                                    <span class="badge badge-light" title="ສິດທິພິເສດ: ເບິ່ງກຳໄລໄດ້" style="font-size:0.75rem;"><i class="fas fa-chart-pie text-success"></i> ກຳໄລ</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $s['phone'] ?: '-' ?></td>
                            <td>
                                <?php if ($s['is_active']): ?>
                                    <span class="badge badge-success">ເປີດໃຊ້ງານ</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">ປິດ</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $s['last_login'] ? formatDateTime($s['last_login']) : 'ຍັງບໍ່ທັນ' ?></td>
                            <td>
                                <div style="display:flex;gap:4px;">
                                    <button class="btn-icon btn-white btn-sm" onclick="editStaff(<?= $s['id'] ?>)" title="ແກ້ໄຂ"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon btn-white btn-sm" style="color:var(--danger);" onclick="deleteStaff(<?= $s['id'] ?>)" title="ລຶບ"><i class="fas fa-trash"></i></button>
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

<!-- Add Staff Modal -->
<div class="modal-overlay" id="addStaffModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="staffModalTitle">ເພີ່ມພະນັກງານໃໝ່</h3>
            <button class="modal-close" onclick="closeModal('addStaffModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="staffForm" onsubmit="saveStaff(event)">
                <input type="hidden" id="staffId" value="">
                <input type="hidden" id="removeStaffAvatarFlag" value="0">
                
                <!-- Avatar Upload Section in Modal -->
                <div style="text-align:center; margin-bottom:18px;">
                    <div style="position:relative; width:80px; height:80px; margin:0 auto 8px; border-radius:50%; border:3px solid #e2e8f0; overflow:hidden; background:#f1f5f9; cursor:pointer;" onclick="document.getElementById('staffAvatarInput').click()" title="ກົດເພື່ອເລືອກຮູບ">
                        <img id="staffAvatarPreview" src="" style="width:100%; height:100%; object-fit:cover; display:none;">
                        <div id="staffAvatarPlaceholder" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:26px;">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <input type="file" id="staffAvatarInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="previewStaffAvatar(this)">
                    <div style="display:flex; justify-content:center; gap:6px;">
                        <button type="button" class="btn btn-sm btn-white" style="font-size:0.75rem; padding:3px 10px;" onclick="document.getElementById('staffAvatarInput').click()">
                            <i class="fas fa-upload text-primary"></i> ເລືອກຮູບ
                        </button>
                        <button type="button" class="btn btn-sm btn-white text-danger" id="clearStaffAvatarBtn" style="font-size:0.75rem; padding:3px 10px; display:none;" onclick="clearStaffAvatar()">
                            <i class="fas fa-times"></i> ລຶບຮູບ
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">ຊື່-ນາມສະກຸນ *</label>
                    <input type="text" class="form-control" id="staffName" required placeholder="ຊື່ ແລະ ນາມສະກຸນ" oninput="this.classList.remove('is-invalid')">
                    <div class="invalid-feedback">ກະລຸນາປ້ອນຊື່ ແລະ ນາມສະກຸນ</div>
                </div>
                <div class="form-group">
                    <label class="form-label">ຊື່ຜູ້ໃຊ້ *</label>
                    <input type="text" class="form-control" id="staffUsername" required placeholder="ປ້ອນຊື່ຜູ້ໃຊ້" oninput="this.classList.remove('is-invalid')">
                    <div class="invalid-feedback">ກະລຸນາປ້ອນຊື່ຜູ້ໃຊ້ (Username)</div>
                </div>
                <div class="form-group">
                    <label class="form-label">ລະຫັດຜ່ານ * <span id="pwdHelp" style="font-size:0.75rem;color:var(--text-light);font-weight:normal;">(ປ້ອນເມື່ອຕ້ອງການປ່ຽນ)</span></label>
                    <input type="password" class="form-control" id="staffPassword" required placeholder="ປ້ອນລະຫັດຜ່ານ" oninput="this.classList.remove('is-invalid')">
                    <div class="invalid-feedback">ກະລຸນາປ້ອນລະຫັດຜ່ານ</div>
                </div>
                <div class="form-group">
                    <label class="form-label">ບົດບາດ</label>
                    <select class="form-control" id="staffRole" onchange="onRoleChanged()">
                        <option value="cashier">ພະນັກງານຂາຍ (Cashier)</option>
                        <option value="pharmacist">ເພສັດຊະກອນ (Pharmacist)</option>
                        <option value="manager">ຜູ້ຈັດການ (Manager)</option>
                        <option value="admin">ຜູ້ດູແລລະບົບ (Admin)</option>
                        <?php if (($_SESSION['role'] ?? '') === 'developer'): ?>
                            <option value="developer">ນັກພັດທະນາ (Developer)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">ເບີໂທ</label>
                    <input type="text" class="form-control" id="staffPhone" placeholder="020 xxxxxxxx">
                </div>
                <div class="form-group">
                    <label class="form-label">ອີເມວ</label>
                    <input type="email" class="form-control" id="staffEmail" placeholder="example@mail.com">
                </div>
                <div class="form-group" id="statusGroup" style="display:none;">
                    <label class="form-label">ສະຖານະ</label>
                    <select class="form-control" id="staffStatus">
                        <option value="1">ເປີດໃຊ້ງານ</option>
                        <option value="0">ປິດໃຊ້ງານ</option>
                    </select>
                </div>

                <!-- Granular Permissions Settings -->
                <div class="form-group" id="permissionsGroup" style="margin-top:16px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                        <label class="form-label" style="font-weight:600; margin-bottom:0;">
                            <i class="fas fa-shield-alt text-primary"></i> ສິດທິການນຳໃຊ້ (Permissions)
                        </label>
                        <button type="button" class="btn btn-sm btn-white" style="font-size:0.75rem; padding:2px 8px;" onclick="resetRolePermissions()">
                            <i class="fas fa-undo"></i> ຄືນຄ່າເລີ່ມຕົ້ນຕາມບົດບາດ
                        </button>
                    </div>
                    <div style="background:var(--bg-light, #f8fafc); border:1px solid var(--border-color, #e2e8f0); border-radius:10px; padding:12px; display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; cursor:pointer;">
                            <input type="checkbox" id="perm_can_view_profit" class="perm-cb">
                            <span><i class="fas fa-chart-pie text-success"></i> ເບິ່ງກຳໄລ-ຂາດທຶນ</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; cursor:pointer;">
                            <input type="checkbox" id="perm_can_manage_stock" class="perm-cb">
                            <span><i class="fas fa-warehouse text-primary"></i> ຈັດການສາງ & ກວດນັບ</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; cursor:pointer;">
                            <input type="checkbox" id="perm_can_manage_products" class="perm-cb">
                            <span><i class="fas fa-box text-info"></i> ຈັດການຢາ & ລາຄາ</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; cursor:pointer;">
                            <input type="checkbox" id="perm_can_manage_po" class="perm-cb">
                            <span><i class="fas fa-file-invoice text-warning"></i> ໃບສັ່ງຊື້ຢາ (PO)</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; cursor:pointer;">
                            <input type="checkbox" id="perm_can_give_discount" class="perm-cb">
                            <span><i class="fas fa-percent text-secondary"></i> ໃຫ້ສ່ວນຫຼຸດໜ້າຂາຍ</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; cursor:pointer;">
                            <input type="checkbox" id="perm_can_view_reports" class="perm-cb">
                            <span><i class="fas fa-chart-line text-info"></i> ເບິ່ງ Dashboard ຍອດຂາຍ</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; cursor:pointer; grid-column: span 2;">
                            <input type="checkbox" id="perm_can_delete_sales" class="perm-cb">
                            <span style="color:var(--danger, #ef4444);"><i class="fas fa-trash-alt"></i> ຍົກເລີກ / ລຶບບິນຂາຍ (Admin/Manager)</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-white" onclick="closeModal('addStaffModal')">ຍົກເລີກ</button>
            <button class="btn btn-primary" id="saveStaffBtn" onclick="saveStaff()"><i class="fas fa-save"></i> ບັນທຶກ</button>
        </div>
    </div>
</div>

<script>
const ROLE_DEFAULTS = {
    admin: {
        can_view_profit: true,
        can_manage_stock: true,
        can_manage_products: true,
        can_manage_po: true,
        can_give_discount: true,
        can_delete_sales: true,
        can_view_reports: true
    },
    developer: {
        can_view_profit: true,
        can_manage_stock: true,
        can_manage_products: true,
        can_manage_po: true,
        can_give_discount: true,
        can_delete_sales: true,
        can_view_reports: true
    },
    manager: {
        can_view_profit: true,
        can_manage_stock: true,
        can_manage_products: true,
        can_manage_po: true,
        can_give_discount: true,
        can_delete_sales: true,
        can_view_reports: true
    },
    pharmacist: {
        can_view_profit: false,
        can_manage_stock: true,
        can_manage_products: true,
        can_manage_po: true,
        can_give_discount: true,
        can_delete_sales: false,
        can_view_reports: true
    },
    cashier: {
        can_view_profit: false,
        can_manage_stock: false,
        can_manage_products: false,
        can_manage_po: false,
        can_give_discount: false,
        can_delete_sales: false,
        can_view_reports: false
    }
};

function setPermissionsInUI(perms) {
    const keys = ['can_view_profit', 'can_manage_stock', 'can_manage_products', 'can_manage_po', 'can_give_discount', 'can_delete_sales', 'can_view_reports'];
    keys.forEach(k => {
        const el = document.getElementById('perm_' + k);
        if (el) el.checked = !!perms[k];
    });
}

function getPermissionsFromUI() {
    return {
        can_view_profit: !!document.getElementById('perm_can_view_profit')?.checked,
        can_manage_stock: !!document.getElementById('perm_can_manage_stock')?.checked,
        can_manage_products: !!document.getElementById('perm_can_manage_products')?.checked,
        can_manage_po: !!document.getElementById('perm_can_manage_po')?.checked,
        can_give_discount: !!document.getElementById('perm_can_give_discount')?.checked,
        can_delete_sales: !!document.getElementById('perm_can_delete_sales')?.checked,
        can_view_reports: !!document.getElementById('perm_can_view_reports')?.checked
    };
}

function onRoleChanged() {
    const role = document.getElementById('staffRole').value;
    const defaults = ROLE_DEFAULTS[role] || ROLE_DEFAULTS.cashier;
    setPermissionsInUI(defaults);
}

function resetRolePermissions() {
    onRoleChanged();
    showToast('ຄືນຄ່າສິດທິຕາມບົດບາດແລ້ວ', 'info');
}

function previewStaffAvatar(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('staffAvatarPreview').src = e.target.result;
        document.getElementById('staffAvatarPreview').style.display = 'block';
        document.getElementById('staffAvatarPlaceholder').style.display = 'none';
        document.getElementById('clearStaffAvatarBtn').style.display = 'inline-block';
        document.getElementById('removeStaffAvatarFlag').value = '0';
    };
    reader.readAsDataURL(file);
}

function clearStaffAvatar() {
    document.getElementById('staffAvatarInput').value = '';
    document.getElementById('staffAvatarPreview').src = '';
    document.getElementById('staffAvatarPreview').style.display = 'none';
    document.getElementById('staffAvatarPlaceholder').style.display = 'flex';
    document.getElementById('clearStaffAvatarBtn').style.display = 'none';
    document.getElementById('removeStaffAvatarFlag').value = '1';
}

function openAddStaffModal() {
    document.getElementById('staffId').value = '';
    document.getElementById('staffForm').reset();
    clearStaffAvatar();
    document.getElementById('removeStaffAvatarFlag').value = '0';
    document.getElementById('staffEmail').value = '';
    document.getElementById('staffPassword').required = true;
    document.getElementById('pwdHelp').style.display = 'none';
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('staffRole').value = 'pharmacist';
    onRoleChanged();
    document.getElementById('staffModalTitle').textContent = 'ເພີ່ມພະນັກງານໃໝ່';
    openModal('addStaffModal');
}

async function saveStaff(e) {
    if (e) e.preventDefault();
    const id = document.getElementById('staffId').value;
    const nameEl = document.getElementById('staffName');
    const userEl = document.getElementById('staffUsername');
    const passEl = document.getElementById('staffPassword');
    const fullName = nameEl ? nameEl.value.trim() : '';
    const username = userEl ? userEl.value.trim() : '';
    const password = passEl ? passEl.value : '';
    
    [nameEl, userEl, passEl].forEach(el => el?.classList.remove('is-invalid'));
    
    if (!fullName) {
        nameEl?.classList.add('is-invalid');
        nameEl?.focus();
        showToast('ກະລຸນາປ້ອນຊື່ ແລະ ນາມສະກຸນ', 'warning');
        return;
    }
    
    if (!username) {
        userEl?.classList.add('is-invalid');
        userEl?.focus();
        showToast('ກະລຸນາປ້ອນຊື່ຜູ້ໃຊ້ (Username)', 'warning');
        return;
    }
    
    if (!id && !password) {
        passEl?.classList.add('is-invalid');
        passEl?.focus();
        showToast('ກະລຸນາປ້ອນລະຫັດຜ່ານ', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('full_name', fullName);
    formData.append('username', username);
    formData.append('role', document.getElementById('staffRole').value);
    formData.append('phone', document.getElementById('staffPhone').value);
    formData.append('email', document.getElementById('staffEmail').value);
    formData.append('permissions', JSON.stringify(getPermissionsFromUI()));

    const avatarFile = document.getElementById('staffAvatarInput').files[0];
    if (avatarFile) {
        formData.append('avatar', avatarFile);
    }
    if (document.getElementById('removeStaffAvatarFlag').value === '1') {
        formData.append('remove_avatar', '1');
    }
    
    if (password) {
        formData.append('password', password);
    }
    
    if (id) {
        formData.append('id', id);
        formData.append('is_active', document.getElementById('staffStatus').value);
    }
    
    const btn = document.getElementById('saveStaffBtn');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...'; }

    try {
        const result = await fetchAPI(`${BASE_URL}api/users.php`, {
            method: 'POST',
            body: formData,
        });
        
        if (result.success) {
            showToast(id ? 'ແກ້ໄຂຂໍ້ມູນພະນັກງານສຳເລັດ' : 'ເພີ່ມພະນັກງານສຳເລັດ', 'success');
            closeModal('addStaffModal');
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

async function deleteStaff(id) {
    if (id === 1) {
        showToast('ບໍ່ສາມາດລຶບຜູ້ດູແລລະບົບຫຼັກໄດ້', 'error');
        return;
    }
    if (!await confirmDelete('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບພະນັກງານນີ້?')) return;
    if (window.showActionLoading) window.showActionLoading('ກຳລັງລຶບພະນັກງານ...');
    try {
        const result = await fetchAPI(`${BASE_URL}api/users.php?id=${id}`, {
            method: 'DELETE',
        });
        if (window.hideActionLoading) window.hideActionLoading();
        if (result.success) {
            showToast('ລຶບພະນັກງານສຳເລັດ', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (error) {
        if (window.hideActionLoading) window.hideActionLoading();
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
    }
}

async function editStaff(id) {
    try {
        const result = await fetchAPI(`${BASE_URL}api/users.php?id=${id}`);
        if (result.success && result.user) {
            const u = result.user;
            document.getElementById('staffId').value = u.id;
            document.getElementById('staffName').value = u.full_name;
            document.getElementById('staffUsername').value = u.username;
            document.getElementById('staffPassword').value = '';
            document.getElementById('staffPassword').required = false;
            document.getElementById('pwdHelp').style.display = 'inline';
            document.getElementById('staffRole').value = u.role;
            document.getElementById('staffPhone').value = u.phone || '';
            document.getElementById('staffEmail').value = u.email || '';
            document.getElementById('staffStatus').value = u.is_active;
            document.getElementById('statusGroup').style.display = '';

            // Handle Avatar in modal
            document.getElementById('staffAvatarInput').value = '';
            document.getElementById('removeStaffAvatarFlag').value = '0';
            if (u.avatar_url) {
                document.getElementById('staffAvatarPreview').src = u.avatar_url;
                document.getElementById('staffAvatarPreview').style.display = 'block';
                document.getElementById('staffAvatarPlaceholder').style.display = 'none';
                document.getElementById('clearStaffAvatarBtn').style.display = 'inline-block';
            } else {
                document.getElementById('staffAvatarPreview').src = '';
                document.getElementById('staffAvatarPreview').style.display = 'none';
                document.getElementById('staffAvatarPlaceholder').style.display = 'flex';
                document.getElementById('clearStaffAvatarBtn').style.display = 'none';
            }

            // Populate permissions
            const userPerms = u.permissions || ROLE_DEFAULTS[u.role] || ROLE_DEFAULTS.cashier;
            setPermissionsInUI(userPerms);
            
            document.getElementById('staffModalTitle').textContent = 'ແກ້ໄຂຂໍ້ມູນພະນັກງານ';
            openModal('addStaffModal');
        } else {
            showToast('ບໍ່ສາມາດດຶງຂໍ້ມູນພະນັກງານໄດ້', 'error');
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການດຶງຂໍ້ມູນ', 'error');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
