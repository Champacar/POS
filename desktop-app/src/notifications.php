<?php
/**
 * ແຈ້ງເຕືອນ - Notifications
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'ແຈ້ງເຕືອນ';

// Mark all as read if requested (POST with CSRF protection)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    requireCSRFToken();
    try {
        $db = getDB();
        $db->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    } catch (Exception $e) {}
    header('Location: notifications.php');
    exit;
}

try {
    $db = getDB();
    $notifications = $db->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 50")->fetchAll();
    
    // Auto-generate low stock notifications
    $lowStockProducts = $db->query("SELECT * FROM products WHERE stock_quantity <= min_stock AND is_active = 1")->fetchAll();
    foreach ($lowStockProducts as $p) {
        $exists = $db->prepare("SELECT COUNT(*) as c FROM notifications WHERE title LIKE ? AND DATE(created_at) = CURDATE()");
        $exists->execute(["%{$p['name']}%"]);
        if ($exists->fetch()['c'] == 0) {
            $stmt = $db->prepare("INSERT INTO notifications (title, message, type, link) VALUES (?, ?, 'warning', 'inventory.php')");
            $stmt->execute([
                "ສິນຄ້າໃກ້ໝົດ: {$p['name']}",
                "ສິນຄ້າ {$p['name']} ເຫຼືອພຽງ {$p['stock_quantity']} {$p['unit']}"
            ]);
        }
    }
    
    // Reload notifications
    $notifications = $db->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 50")->fetchAll();
} catch (Exception $e) {
    $notifications = [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">ແຈ້ງເຕືອນ</h1>
        <form method="POST" action="" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-outline btn-sm">
                <i class="fas fa-check-double"></i> ອ່ານທັງໝົດ
            </button>
        </form>
    </div>

    <div class="content-area">
        <?php if (empty($notifications)): ?>
        <div class="empty-state" style="padding:80px;">
            <i class="fas fa-bell-slash"></i>
            <p>ບໍ່ມີການແຈ້ງເຕືອນ</p>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($notifications as $n): ?>
            <div class="card" style="padding:16px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:14px;<?= !$n['is_read'] ? 'border-left:4px solid var(--primary);' : 'opacity:0.7;' ?>">
                <div class="stat-icon <?= $n['type'] === 'warning' ? 'orange' : ($n['type'] === 'danger' ? 'coral' : ($n['type'] === 'success' ? 'green' : 'blue')) ?>" style="width:40px;height:40px;flex-shrink:0;">
                    <i class="fas <?= $n['type'] === 'warning' ? 'fa-exclamation-triangle' : ($n['type'] === 'danger' ? 'fa-times-circle' : ($n['type'] === 'success' ? 'fa-check-circle' : 'fa-info-circle')) ?>"></i>
                </div>
                <div style="flex:1;min-width:180px;">
                    <div style="font-weight:600;font-size:0.9rem;"><?= sanitize($n['title']) ?></div>
                    <div style="font-size:0.8rem;color:var(--text-light);margin-top:2px;"><?= sanitize($n['message'] ?? '') ?></div>
                </div>
                <div style="font-size:0.75rem;color:var(--text-light);white-space:nowrap;">
                    <?= formatDateTime($n['created_at']) ?>
                </div>
                <?php if ($n['link']): ?>
                <a href="<?= $n['link'] ?>" class="btn btn-outline btn-sm">ເບິ່ງ</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
