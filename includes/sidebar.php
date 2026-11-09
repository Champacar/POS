<?php
/**
 * Sidebar Navigation
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

$notificationCount = 0;
$settings = null;
try {
    $notificationCount = getUnreadNotificationsCount();
    $settings = getShopSettings();
} catch (Exception $e) {
    $notificationCount = 0;
}
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <!-- Toggle Button -->
    <div class="sidebar-toggle" id="sidebarToggle" title="ຫຍໍ້/ຂະຫຍາຍ">
        <i class="fas fa-chevron-left"></i>
    </div>

    <!-- Logo & Brand -->
    <div class="sidebar-header">
        <div style="display:flex; align-items:center; gap:12px;">
            <div class="sidebar-logo">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($settings['logo']) ?>" style="width: 100%; height: 100%; object-fit: contain; border-radius: 12px;" alt="Logo">
                <?php else: ?>
                    <i class="fas fa-plus-circle"></i>
                <?php endif; ?>
            </div>
            <div class="sidebar-brand">
                <h2><?= htmlspecialchars($settings['shop_name'] ?? 'ລະບົບຂາຍຢາດີ') ?></h2>
                <span><?= htmlspecialchars($settings['shop_name_en'] ?? 'POINT OF SALE') ?></span>
            </div>
        </div>
        <button type="button" class="sidebar-mobile-close" onclick="closeMobileSidebar()" aria-label="ປິດເມນູ">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <?php if (hasPermission('can_view_reports')): ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>index.php" class="nav-link <?= isActivePage('index') ?>" title="ລາຍງານການຂາຍ">
                <i class="fas fa-th-large"></i>
                <span>ລາຍງານການຂາຍ</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasRole(['admin', 'manager']) || canViewProfitLoss()): ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>income_expense_report.php" class="nav-link <?= isActivePage('income_expense_report') ?>" title="ລາຍງານລາຍຮັບລາຍຈ່າຍ">
                <i class="fas fa-chart-line"></i>
                <span>ລາຍງານລາຍຮັບລາຍຈ່າຍ</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (canViewProfitLoss()): ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>profit_loss_report.php" class="nav-link <?= isActivePage('profit_loss_report') ?>" title="ກຳໄລ-ຂາດທຶນ & ວິເຄາະ">
                <i class="fas fa-chart-pie"></i>
                <span>ກຳໄລ-ຂາດທຶນ & ວິເຄາະ</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasRole(['admin', 'manager']) || canViewProfitLoss()): ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>tax_report.php" class="nav-link <?= isActivePage('tax_report') ?>" title="ລາຍງານອາກອນ & ການເງິນ">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>ລາຍງານອາກອນ & ການເງິນ</span>
            </a>
        </div>
        <?php endif; ?>

        <div class="nav-item">
            <a href="<?= BASE_URL ?>pos.php" class="nav-link <?= isActivePage('pos') ?>" title="ໜ້າຂາຍ">
                <i class="fas fa-capsules"></i>
                <span>ໜ້າຂາຍ</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>sales_history.php" class="nav-link <?= isActivePage('sales_history') ?>" title="ປະຫວັດການຂາຍ">
                <i class="fas fa-money-bill-wave"></i>
                <span>ປະຫວັດການຂາຍ</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>shifts.php" class="nav-link <?= isActivePage('shifts') ?>" title="ປິດກະ / ປະຫວັດກະ">
                <i class="fas fa-clock"></i>
                <span>ປິດກະ / ປະຫວັດກະ</span>
            </a>
        </div>

        <?php if (hasRole(['admin', 'manager'])): ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>expenses.php" class="nav-link <?= isActivePage('expenses') ?>" title="ບັນທຶກລາຍຈ່າຍ">
                <i class="fas fa-wallet"></i>
                <span>ບັນທຶກລາຍຈ່າຍ</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasRole(['admin', 'manager', 'pharmacist']) || hasPermission('can_manage_products')): ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>products.php" class="nav-link <?= isActivePage('products') ?>" title="ຈັດການສິນຄ້າ">
                <i class="fas fa-box"></i>
                <span>ຈັດການສິນຄ້າ</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>barcode_manager.php" class="nav-link <?= isActivePage('barcode_manager') ?>" title="ບາໂຄດ & ສະຕິກເກີ">
                <i class="fas fa-barcode"></i>
                <span>ບາໂຄດ & ສະຕິກເກີ</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasRole(['admin', 'manager', 'pharmacist']) || hasPermission('can_manage_stock')): ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>inventory.php" class="nav-link <?= isActivePage('inventory') ?>" title="ສາງສິນຄ້າ">
                <i class="fas fa-warehouse"></i>
                <span>ສາງສິນຄ້າ</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (hasRole(['admin', 'manager', 'pharmacist']) || hasPermission('can_manage_po')): ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>suppliers.php" class="nav-link <?= isActivePage('suppliers') ?>" title="ຜູ້ສະໜອງຢາ">
                <i class="fas fa-truck"></i>
                <span>ຜູ້ສະໜອງຢາ</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>purchase_orders.php" class="nav-link <?= isActivePage('purchase_orders') ?>" title="ໃບສັ່ງຊື້ຢາ">
                <i class="fas fa-file-invoice"></i>
                <span>ໃບສັ່ງຊື້ຢາ</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'developer'], true)): ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>staff.php" class="nav-link <?= isActivePage('staff') ?>" title="ພະນັກງານ">
                <i class="fas fa-users"></i>
                <span>ພະນັກງານ</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>shop_settings.php" class="nav-link <?= isActivePage('shop_settings') ?>" title="ຂໍ້ມູນຮ້ານ">
                <i class="fas fa-store"></i>
                <span>ຂໍ້ມູນຮ້ານ</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>backup.php" class="nav-link <?= isActivePage('backup') ?>" title="ສຳຮອງຂໍ້ມູນ (Backup)">
                <i class="fas fa-database"></i>
                <span>ສຳຮອງຂໍ້ມູນ (Backup)</span>
            </a>
        </div>
        <?php endif; ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>notifications.php" class="nav-link <?= isActivePage('notifications') ?>" title="ແຈ້ງເຕືອນ">
                <i class="fas fa-bell"></i>
                <span>ແຈ້ງເຕືອນ</span>
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-count"><?= $notificationCount ?></span>
                <?php endif; ?>
            </a>
        </div>
        <?php if (($_SESSION['role'] ?? '') === 'developer'): ?>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>server_settings.php" class="nav-link <?= isActivePage('server_settings') ?>" title="ຕັ້ງຄ່າເຊີເວີ (Server Settings)">
                <i class="fas fa-server"></i>
                <span>ຕັ້ງຄ່າເຊີເວີ (Server Settings)</span>
            </a>
        </div>
        <?php endif; ?>
        <div class="nav-item" id="pwaInstallItem">
            <button type="button" class="nav-link pwa-install-btn" id="pwaInstallBtn" onclick="installPWA()" title="ຕິດຕັ້ງແອັບ (Install PWA)" style="display:none; width:100%; border:none; background:transparent; cursor:pointer; text-align:left; color:var(--primary); font-weight:600;">
                <i class="fas fa-download" style="color:var(--primary);"></i>
                <span>ຕິດຕັ້ງແອັບ (Install)</span>
            </button>
        </div>
        <div class="nav-item">
            <a href="<?= BASE_URL ?>logout.php" class="nav-link" title="ອອກລະບົບ">
                <i class="fas fa-sign-out-alt"></i>
                <span>ອອກລະບົບ</span>
            </a>
        </div>
    </nav>
</aside>
