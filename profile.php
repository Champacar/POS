<?php
/**
 * ໂປຣໄຟລຂອງຂ້ອຍ - User Profile
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'ໂປຣໄຟລຂອງຂ້ອຍ';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch();
    if (!$user) {
        header('Location: ' . BASE_URL . 'logout.php');
        exit;
    }
} catch (Exception $e) {
    die("Error loading user profile: " . htmlspecialchars($e->getMessage()));
}

// Get user sales stats
$userSalesCount = 0;
$userSalesTotal = 0;
try {
    $stmtSales = $db->prepare("SELECT COUNT(*) as bill_count, COALESCE(SUM(total_amount), 0) as total_sold FROM sales WHERE user_id = ? AND payment_status != 'cancelled'");
    $stmtSales->execute([$currentUserId]);
    $saleStats = $stmtSales->fetch(PDO::FETCH_ASSOC);
    if ($saleStats) {
        $userSalesCount = (int)$saleStats['bill_count'];
        $userSalesTotal = (float)$saleStats['total_sold'];
    }
} catch (Exception $e) {}

// Get user recent activity logs
$userLogs = [];
try {
    $stmtLogs = $db->prepare("SELECT * FROM user_activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 15");
    $stmtLogs->execute([$currentUserId]);
    $userLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$userAvatarUrl = getUserAvatarUrl($user['avatar'] ?? null);
$userPermissions = !empty($user['permissions']) ? json_decode($user['permissions'], true) : getDefaultPermissionsByRole($user['role']);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* Profile Page Custom Styles */
.profile-container {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 24px;
    align-items: start;
    margin-top: 10px;
}
@media (max-width: 992px) {
    .profile-container {
        grid-template-columns: 1fr;
    }
}

.profile-card {
    background: var(--card-bg, #ffffff);
    border-radius: var(--card-radius, 14px);
    box-shadow: var(--card-shadow, 0 2px 12px rgba(0,0,0,0.06));
    padding: 28px 24px;
    text-align: center;
    position: relative;
    border: 1px solid rgba(0,0,0,0.04);
}

.profile-avatar-wrapper {
    position: relative;
    width: 140px;
    height: 140px;
    margin: 0 auto 18px;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 8px 25px rgba(46, 125, 50, 0.2);
    border: 4px solid #ffffff;
    background: #e8f5e9;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.profile-avatar-wrapper:hover {
    transform: scale(1.03);
    box-shadow: 0 12px 30px rgba(46, 125, 50, 0.3);
}

.profile-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.profile-avatar-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 52px;
    color: var(--primary, #2e7d32);
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    font-weight: 700;
}

.profile-avatar-overlay {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary, #2e7d32);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.25);
    border: 3px solid #ffffff;
    transition: background-color 0.2s;
}
.profile-avatar-wrapper:hover .profile-avatar-overlay {
    background: var(--primary-dark, #1b5e20);
}

.profile-meta-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-dark, #263238);
    margin-bottom: 4px;
}

.profile-meta-username {
    font-size: 0.9rem;
    color: var(--text-medium, #546e7a);
    margin-bottom: 12px;
    display: inline-block;
    background: #f1f5f9;
    padding: 3px 12px;
    border-radius: 20px;
    font-family: monospace;
}

.profile-quick-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px dashed var(--border-color, #e2e8f0);
    text-align: left;
}
.stat-box {
    background: #f8fafc;
    border: 1px solid #eef2f6;
    border-radius: 12px;
    padding: 12px 14px;
}
.stat-box-label {
    font-size: 0.75rem;
    color: var(--text-light, #64748b);
    margin-bottom: 4px;
    display: block;
}
.stat-box-val {
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary, #2e7d32);
}

/* Tabs */
.profile-tabs {
    display: flex;
    gap: 8px;
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 24px;
}
.profile-tab-btn {
    padding: 12px 20px;
    border: none;
    background: transparent;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-medium, #64748b);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}
.profile-tab-btn:hover {
    color: var(--primary, #2e7d32);
}
.profile-tab-btn.active {
    color: var(--primary, #2e7d32);
    border-bottom-color: var(--primary, #2e7d32);
    background: rgba(46, 125, 50, 0.04);
    border-radius: 8px 8px 0 0;
}

.profile-tab-content {
    display: none;
}
.profile-tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

.field-group-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-dark, #1e293b);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 8px;
}

.input-icon-wrapper {
    position: relative;
}
.input-icon-wrapper i.leading-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 15px;
}
.input-icon-wrapper input {
    padding-left: 42px;
}

.password-toggle-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 4px;
    font-size: 15px;
}
.password-toggle-btn:hover {
    color: var(--primary, #2e7d32);
}

.perm-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #334155;
}
.perm-pill.active {
    background: #e8f5e9;
    border-color: #c8e6c9;
    color: #1b5e20;
}
</style>

<main class="main-content">
    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 20px;">
        <div>
            <h1 class="page-title"><i class="fas fa-user-circle text-primary" style="margin-right: 8px;"></i> ໂປຣໄຟລຂອງຂ້ອຍ</h1>
            <p style="font-size:0.85rem;color:var(--text-medium);margin-top:2px;">
                ຈັດການຂໍ້ມູນສ່ວນຕົວ, ຮູບໂປຣໄຟລ ແລະ ຕັ້ງຄ່າຄວາມປອດໄພຂອງບັນຊີ
            </p>
        </div>
        <div>
            <a href="index.php" class="btn btn-white btn-sm"><i class="fas fa-arrow-left"></i> ກັບຄືນໜ້າຫຼັກ</a>
        </div>
    </div>

    <!-- Hidden Avatar File Input -->
    <input type="file" id="avatarFileInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="handleAvatarFileSelected(event)">

    <!-- Profile Grid Layout -->
    <div class="profile-container">
        
        <!-- Left: Profile Summary Card -->
        <div>
            <div class="profile-card">
                <!-- Avatar with Click-to-Upload -->
                <div class="profile-avatar-wrapper" onclick="triggerAvatarUpload()" title="ກົດເພື່ອປ່ຽນຮູບໂປຣໄຟລ">
                    <?php if ($userAvatarUrl): ?>
                        <img src="<?= $userAvatarUrl ?>?v=<?= time() ?>" alt="Avatar" class="profile-avatar-img" id="avatarDisplayImg">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder" id="avatarDisplayPlaceholder">
                            <?= mb_substr($user['full_name'], 0, 1) ?>
                        </div>
                        <img src="" alt="Avatar" class="profile-avatar-img" id="avatarDisplayImg" style="display:none;">
                    <?php endif; ?>
                    <div class="profile-avatar-overlay">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>

                <!-- Avatar Action Buttons -->
                <div style="display:flex; justify-content:center; gap:8px; margin-bottom:18px;">
                    <button type="button" class="btn btn-sm btn-white" onclick="triggerAvatarUpload()" style="font-size:0.8rem; padding:5px 12px;">
                        <i class="fas fa-upload text-primary"></i> ປ່ຽນຮູບ
                    </button>
                    <?php if (!empty($user['avatar'])): ?>
                        <button type="button" class="btn btn-sm btn-white text-danger" id="removeAvatarBtn" onclick="removeAvatarPrompt()" style="font-size:0.8rem; padding:5px 12px;" title="ລຶບຮູບໂປຣໄຟລ">
                            <i class="fas fa-trash-alt"></i> ລຶບ
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Name & Role -->
                <h2 class="profile-meta-title" id="profileDisplayName"><?= htmlspecialchars($user['full_name']) ?></h2>
                <div class="profile-meta-username">@<?= htmlspecialchars($user['username']) ?></div>
                
                <div>
                    <span class="badge <?= getRoleBadgeClass($user['role']) ?>" style="font-size:0.85rem; padding:6px 14px;">
                        <i class="fas fa-user-tag" style="margin-right:5px;"></i> <?= htmlspecialchars(getRoleLabel($user['role'])) ?>
                    </span>
                </div>

                <!-- Account Meta List -->
                <div style="text-align:left; margin-top:20px; font-size:0.85rem; color:var(--text-medium); line-height:2;">
                    <div>
                        <i class="fas fa-calendar-alt text-primary" style="width:20px;"></i> 
                        ວັນທີສ້າງບັນຊີ: <strong><?= formatDate($user['created_at']) ?></strong>
                    </div>
                    <div>
                        <i class="fas fa-clock text-info" style="width:20px;"></i> 
                        ເຂົ້າລະບົບລ່າສຸດ: <strong><?= $user['last_login'] ? formatDateTime($user['last_login']) : 'ຍັງບໍ່ມີປະຫວັດ' ?></strong>
                    </div>
                    <div>
                        <i class="fas fa-shield-alt text-success" style="width:20px;"></i> 
                        ສະຖານະ: <strong class="text-success">ເປີດໃຊ້ງານປົກກະຕິ</strong>
                    </div>
                </div>

                <!-- Quick Sales Stats (Cashiers/Staff) -->
                <div class="profile-quick-stats">
                    <div class="stat-box">
                        <span class="stat-box-label"><i class="fas fa-file-invoice-dollar"></i> ຈຳນວນບິນຂາຍ</span>
                        <div class="stat-box-val"><?= number_format($userSalesCount) ?> ບິນ</div>
                    </div>
                    <div class="stat-box">
                        <span class="stat-box-label"><i class="fas fa-coins"></i> ຍອດຂາຍລວມ</span>
                        <div class="stat-box-val" style="font-size:0.9rem;"><?= formatCurrency($userSalesTotal) ?> ₭</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Tabbed Configuration Card -->
        <div class="card" style="padding:28px;">
            <!-- Tab Navigation -->
            <div class="profile-tabs">
                <button type="button" class="profile-tab-btn active" onclick="switchProfileTab('personal')">
                    <i class="fas fa-user-edit"></i> ຂໍ້ມູນສ່ວນຕົວ
                </button>
                <button type="button" class="profile-tab-btn" onclick="switchProfileTab('security')">
                    <i class="fas fa-shield-alt"></i> ປ່ຽນລະຫັດຜ່ານ
                </button>
                <button type="button" class="profile-tab-btn" onclick="switchProfileTab('activity')">
                    <i class="fas fa-history"></i> ປະຫວັດການເຄື່ອນໄຫວ
                </button>
            </div>

            <!-- TAB 1: Personal Info -->
            <div class="profile-tab-content active" id="tab-personal">
                <form id="profileInfoForm" onsubmit="saveProfileInfo(event)">
                    <div class="field-group-title">
                        <i class="fas fa-id-card text-primary"></i> ຂໍ້ມູນພື້ນຖານ
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                        <div class="form-group">
                            <label class="form-label">ຊື່ ແລະ ນາມສະກຸນ <span class="text-danger">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-user leading-icon"></i>
                                <input type="text" class="form-control" id="inputFullName" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required placeholder="ປ້ອນຊື່ ແລະ ນາມສະກຸນ">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ຊື່ເຂົ້າໃຊ້ລະບົບ (Username)</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-at leading-icon"></i>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" readonly style="background:#f1f5f9; cursor:not-allowed;" title="ຊື່ຜູ້ໃຊ້ບໍ່ສາມາດປ່ຽນເອງໄດ້">
                            </div>
                            <small style="color:var(--text-light); font-size:0.75rem;">(ຫາກຕ້ອງການປ່ຽນຊື່ຜູ້ໃຊ້ ກະລຸນາຕິດຕໍ່ຜູ້ດູແລລະບົບ)</small>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-top:8px;">
                        <div class="form-group">
                            <label class="form-label">ເບີໂທລະສັບ</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-phone leading-icon"></i>
                                <input type="text" class="form-control" id="inputPhone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="020 xxxxxxxx">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ອີເມວ (Email)</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-envelope leading-icon"></i>
                                <input type="email" class="form-control" id="inputEmail" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="example@mail.com">
                            </div>
                        </div>
                    </div>

                    <!-- Role & Assigned Permissions Details -->
                    <div class="field-group-title" style="margin-top:24px;">
                        <i class="fas fa-user-lock text-primary"></i> ບົດບາດ & ສິດທິການນຳໃຊ້ (Permissions)
                    </div>

                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:24px;">
                        <div style="margin-bottom:12px; display:flex; align-items:center; justify-content:space-between;">
                            <div>
                                <strong>ບົດບາດໃນລະບົບ:</strong> 
                                <span class="badge <?= getRoleBadgeClass($user['role']) ?>"><?= htmlspecialchars(getRoleLabel($user['role'])) ?></span>
                            </div>
                        </div>
                        <div style="display:flex; flex-wrap:wrap; gap:8px;">
                            <span class="perm-pill <?= !empty($userPermissions['can_view_reports']) ? 'active' : '' ?>">
                                <i class="fas fa-<?= !empty($userPermissions['can_view_reports']) ? 'check-circle text-success' : 'times-circle text-muted' ?>"></i> ເບິ່ງ Dashboard ລາຍງານ
                            </span>
                            <span class="perm-pill <?= !empty($userPermissions['can_manage_products']) ? 'active' : '' ?>">
                                <i class="fas fa-<?= !empty($userPermissions['can_manage_products']) ? 'check-circle text-success' : 'times-circle text-muted' ?>"></i> ຈັດການສິນຄ້າ & ລາຄາ
                            </span>
                            <span class="perm-pill <?= !empty($userPermissions['can_manage_stock']) ? 'active' : '' ?>">
                                <i class="fas fa-<?= !empty($userPermissions['can_manage_stock']) ? 'check-circle text-success' : 'times-circle text-muted' ?>"></i> ຈັດການສາງ & Stock
                            </span>
                            <span class="perm-pill <?= !empty($userPermissions['can_manage_po']) ? 'active' : '' ?>">
                                <i class="fas fa-<?= !empty($userPermissions['can_manage_po']) ? 'check-circle text-success' : 'times-circle text-muted' ?>"></i> ໃບສັ່ງຊື້ຢາ (PO)
                            </span>
                            <span class="perm-pill <?= !empty($userPermissions['can_view_profit']) ? 'active' : '' ?>">
                                <i class="fas fa-<?= !empty($userPermissions['can_view_profit']) ? 'check-circle text-success' : 'times-circle text-muted' ?>"></i> ເບິ່ງກຳໄລ-ຂາດທຶນ
                            </span>
                            <span class="perm-pill <?= !empty($userPermissions['can_give_discount']) ? 'active' : '' ?>">
                                <i class="fas fa-<?= !empty($userPermissions['can_give_discount']) ? 'check-circle text-success' : 'times-circle text-muted' ?>"></i> ໃຫ້ສ່ວນຫຼຸດໜ້າຂາຍ
                            </span>
                            <span class="perm-pill <?= !empty($userPermissions['can_delete_sales']) ? 'active' : '' ?>">
                                <i class="fas fa-<?= !empty($userPermissions['can_delete_sales']) ? 'check-circle text-success' : 'times-circle text-muted' ?>"></i> ຍົກເລີກບິນຂາຍ
                            </span>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                            <i class="fas fa-save"></i> ບັນທຶກການປ່ຽນແປງ
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB 2: Change Password -->
            <div class="profile-tab-content" id="tab-security">
                <form id="changePasswordForm" onsubmit="saveNewPassword(event)">
                    <div class="field-group-title">
                        <i class="fas fa-key text-primary"></i> ປ່ຽນລະຫັດຜ່ານບັນຊີ
                    </div>

                    <!-- Security Alert Hint -->
                    <div class="alert alert-info" style="display:flex; gap:12px; margin-bottom:20px; align-items:flex-start;">
                        <i class="fas fa-shield-alt" style="font-size:1.4rem; color:var(--primary); margin-top:2px;"></i>
                        <div style="font-size:0.85rem; line-height:1.6;">
                            <strong>ຂໍ້ແນະນຳຄວາມປອດໄພ:</strong><br>
                            - ລະຫັດຜ່ານໃໝ່ຄວນມີຄວາມຍາວຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ<br>
                            - ແນະນຳໃຫ້ປະກອບດ້ວຍຕົວອັກສອນພາສາອັງກິດ ແລະ ຕົວເລກປະສົມກັນ<br>
                            - ຫຼີກລ່ຽງການໃຊ້ລະຫັດຜ່ານທີ່ຄາດເດົາໄດ້ງ່າຍ ເຊັ່ນ: 123456 ຫຼື ວັນເດືອນປີເກີດ
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ລະຫັດຜ່ານປະຈຸບັນ <span class="text-danger">*</span></label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-lock leading-icon"></i>
                            <input type="password" class="form-control" id="inputCurrentPassword" required placeholder="ປ້ອນລະຫັດຜ່ານປະຈຸບັນຂອງທ່ານ">
                            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('inputCurrentPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                        <div class="form-group">
                            <label class="form-label">ລະຫັດຜ່ານໃໝ່ <span class="text-danger">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-key leading-icon"></i>
                                <input type="password" class="form-control" id="inputNewPassword" required minlength="6" placeholder="ຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ" oninput="checkPasswordMatch()">
                                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('inputNewPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ຢືນຢັນລະຫັດຜ່ານໃໝ່ <span class="text-danger">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-check-double leading-icon"></i>
                                <input type="password" class="form-control" id="inputConfirmPassword" required minlength="6" placeholder="ປ້ອນລະຫັດຜ່ານໃໝ່ອີກຄັ້ງ" oninput="checkPasswordMatch()">
                                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('inputConfirmPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="pwdMatchMsg" style="font-size:0.75rem; margin-top:4px;"></div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; margin-top:20px;">
                        <button type="submit" class="btn btn-primary" id="savePasswordBtn">
                            <i class="fas fa-shield-alt"></i> ປ່ຽນລະຫັດຜ່ານດຽວນີ້
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB 3: Activity Logs -->
            <div class="profile-tab-content" id="tab-activity">
                <div class="field-group-title">
                    <i class="fas fa-history text-primary"></i> ປະຫວັດການເຄື່ອນໄຫວຫຼ້າສຸດຂອງທ່ານ
                </div>

                <?php if (empty($userLogs)): ?>
                    <div style="text-align:center; padding:40px 20px; color:var(--text-light);">
                        <i class="fas fa-clipboard-list" style="font-size:2.5rem; opacity:0.5; margin-bottom:12px;"></i>
                        <p>ຍັງບໍ່ມີປະຫວັດການເຄື່ອນໄຫວທີ່ບັນທຶກໄວ້</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table" style="font-size:0.85rem;">
                            <thead>
                                <tr>
                                    <th>ເວລາ</th>
                                    <th>ໝວດໝູ່</th>
                                    <th>ການກະທຳ</th>
                                    <th>ລາຍລະອຽດ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userLogs as $log): ?>
                                    <tr>
                                        <td style="white-space:nowrap; color:var(--text-medium);">
                                            <?= formatDateTime($log['created_at']) ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-light" style="font-size:0.75rem;">
                                                <?= htmlspecialchars($log['category'] ?? 'system') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($log['action']) ?></strong>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($log['description']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</main>

<script>
// State
let selectedAvatarFile = null;

// Tab Switching
function switchProfileTab(tabName) {
    document.querySelectorAll('.profile-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.profile-tab-content').forEach(content => content.classList.remove('active'));

    const targetBtn = Array.from(document.querySelectorAll('.profile-tab-btn')).find(b => b.getAttribute('onclick')?.includes(tabName));
    const targetContent = document.getElementById('tab-' + tabName);

    if (targetBtn) targetBtn.classList.add('active');
    if (targetContent) targetContent.classList.add('active');

    // Update URL hash without scroll
    history.replaceState(null, null, '#' + tabName);
}

// Check URL Hash on Load
window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#', '');
    if (['personal', 'security', 'activity'].includes(hash)) {
        switchProfileTab(hash);
    }
});

// Trigger Avatar File Input
function triggerAvatarUpload() {
    document.getElementById('avatarFileInput').click();
}

// Handle Avatar Selection & Live Preview
function handleAvatarFileSelected(e) {
    const file = e.target.files[0];
    if (!file) return;

    if (!file.type.match(/^image\/(jpeg|png|webp|gif)$/i)) {
        Swal.fire({
            icon: 'warning',
            title: 'ໄຟລ໌ບໍ່ຖືກຕ້ອງ',
            text: 'ກະລຸນາເລືອກໄຟລ໌ຮູບພາບປະເພດ JPG, PNG, WEBP ຫຼື GIF ເທົ່ານັ້ນ',
            confirmButtonText: 'ຕົກລົງ'
        });
        e.target.value = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            icon: 'warning',
            title: 'ຂະໜາດໄຟລ໌ໃຫຍ່ເກີນໄປ',
            text: 'ຮູບພາບຕ້ອງມີຂະໜາດບໍ່ເກີນ 5MB',
            confirmButtonText: 'ຕົກລົງ'
        });
        e.target.value = '';
        return;
    }

    selectedAvatarFile = file;

    // Preview
    const reader = new FileReader();
    reader.onload = function(evt) {
        const img = document.getElementById('avatarDisplayImg');
        const placeholder = document.getElementById('avatarDisplayPlaceholder');
        img.src = evt.target.result;
        img.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';

        // Auto prompt to upload immediately
        Swal.fire({
            title: 'ຕ້ອງການບັນທຶກຮູບໂປຣໄຟລໃໝ່ເລີຍບໍ່?',
            imageUrl: evt.target.result,
            imageWidth: 120,
            imageHeight: 120,
            imageAlt: 'Preview',
            showCancelButton: true,
            confirmButtonColor: '#2e7d32',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fas fa-check"></i> ອັບໂຫຼດເລີຍ',
            cancelButtonText: 'ຍົກເລີກ'
        }).then((result) => {
            if (result.isConfirmed) {
                uploadAvatarDirectly(file);
            }
        });
    };
    reader.readAsDataURL(file);
}

// Upload Avatar Directly via AJAX
async function uploadAvatarDirectly(file) {
    const formData = new FormData();
    formData.append('action', 'update_profile');
    formData.append('full_name', document.getElementById('inputFullName').value);
    formData.append('phone', document.getElementById('inputPhone').value);
    formData.append('email', document.getElementById('inputEmail').value);
    formData.append('avatar', file);

    try {
        Swal.fire({
            title: 'ກຳລັງອັບໂຫຼດຮູບ...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const res = await fetch(BASE_URL + 'api/users.php?action=update_profile', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.success) {
            Swal.fire({
                icon: 'success',
                title: 'ສຳເລັດ!',
                text: 'ອັບເດດຮູບໂປຣໄຟລສຳເລັດແລ້ວ',
                timer: 1600,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ຜິດພາດ',
                text: json.message || 'ບໍ່ສາມາດອັບໂຫຼດຮູບພາບໄດ້'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'ຜິດພາດ',
            text: 'ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່: ' + err.message
        });
    }
}

// Remove Avatar Prompt
function removeAvatarPrompt() {
    Swal.fire({
        title: 'ຕ້ອງການລຶບຮູບໂປຣໄຟລແທ້ບໍ່?',
        text: 'ຮູບໂປຣໄຟລຂອງທ່ານຈະຖືກລຶບອອກ ແລະ ປ່ຽນເປັນຮູບເລີ່ມຕົ້ນ',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d32f2f',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-trash-alt"></i> ລຶບຮູບ',
        cancelButtonText: 'ຍົກເລີກ'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const formData = new FormData();
                formData.append('action', 'update_profile');
                formData.append('full_name', document.getElementById('inputFullName').value);
                formData.append('phone', document.getElementById('inputPhone').value);
                formData.append('email', document.getElementById('inputEmail').value);
                formData.append('remove_avatar', '1');

                const res = await fetch(BASE_URL + 'api/users.php?action=update_profile', {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json();

                if (json.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'ສຳເລັດ',
                        text: 'ລຶບຮູບໂປຣໄຟລສຳເລັດແລ້ວ',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('ຜິດພາດ', json.message, 'error');
                }
            } catch (err) {
                Swal.fire('ຜິດພາດ', err.message, 'error');
            }
        }
    });
}

// Save Profile Personal Info
async function saveProfileInfo(e) {
    e.preventDefault();
    const btn = document.getElementById('saveProfileBtn');
    const originalText = btn.innerHTML;

    const formData = new FormData();
    formData.append('action', 'update_profile');
    formData.append('full_name', document.getElementById('inputFullName').value);
    formData.append('phone', document.getElementById('inputPhone').value);
    formData.append('email', document.getElementById('inputEmail').value);

    if (selectedAvatarFile) {
        formData.append('avatar', selectedAvatarFile);
    }

    try {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...';

        const res = await fetch(BASE_URL + 'api/users.php?action=update_profile', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.success) {
            document.getElementById('profileDisplayName').textContent = document.getElementById('inputFullName').value;
            Swal.fire({
                icon: 'success',
                title: 'ສຳເລັດ!',
                text: json.message || 'ບັນທຶກຂໍ້ມູນໂປຣໄຟລສຳເລັດແລ້ວ',
                timer: 1600,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ຜິດພາດ',
                text: json.message || 'ບໍ່ສາມາດບັນທຶກຂໍ້ມູນໄດ້'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'ຜິດພາດ',
            text: 'ເກີດຂໍ້ຜິດພາດ: ' + err.message
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Toggle Show/Hide Password
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Live Password Match Checker
function checkPasswordMatch() {
    const p1 = document.getElementById('inputNewPassword').value;
    const p2 = document.getElementById('inputConfirmPassword').value;
    const msg = document.getElementById('pwdMatchMsg');

    if (!p2) {
        msg.textContent = '';
        return;
    }

    if (p1 === p2) {
        msg.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> ລະຫັດຜ່ານກົງກັນ</span>';
    } else {
        msg.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> ລະຫັດຜ່ານບໍ່ກົງກັນ</span>';
    }
}

// Save New Password
async function saveNewPassword(e) {
    e.preventDefault();
    const btn = document.getElementById('savePasswordBtn');
    const originalText = btn.innerHTML;

    const currentPwd = document.getElementById('inputCurrentPassword').value;
    const newPwd = document.getElementById('inputNewPassword').value;
    const confirmPwd = document.getElementById('inputConfirmPassword').value;

    if (newPwd.length < 6) {
        Swal.fire({
            icon: 'warning',
            title: 'ລະຫັດຜ່ານສັ້ນເກີນໄປ',
            text: 'ລະຫັດຜ່ານໃໝ່ຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ'
        });
        return;
    }

    if (newPwd !== confirmPwd) {
        Swal.fire({
            icon: 'warning',
            title: 'ລະຫັດຜ່ານບໍ່ກົງກັນ',
            text: 'ກະລຸນາກວດສອບການຢືນຢັນລະຫັດຜ່ານໃໝ່ໃຫ້ຖືກຕ້ອງ'
        });
        return;
    }

    try {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງປ່ຽນລະຫັດ...';

        const res = await fetch(BASE_URL + 'api/users.php?action=change_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_password: currentPwd,
                new_password: newPwd,
                confirm_password: confirmPwd
            })
        });
        const json = await res.json();

        if (json.success) {
            Swal.fire({
                icon: 'success',
                title: 'ສຳເລັດ!',
                text: 'ປ່ຽນລະຫັດຜ່ານສຳເລັດແລ້ວ ກະລຸນາຈົດຈຳລະຫັດຜ່ານໃໝ່ຂອງທ່ານ',
                confirmButtonText: 'ຕົກລົງ'
            }).then(() => {
                document.getElementById('changePasswordForm').reset();
                document.getElementById('pwdMatchMsg').textContent = '';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ຜິດພາດ',
                text: json.message || 'ບໍ່ສາມາດປ່ຽນລະຫັດຜ່ານໄດ້'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'ຜິດພາດ',
            text: 'ເກີດຂໍ້ຜິດພາດ: ' + err.message
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>

<?php include 'includes/footer.php'; ?>
