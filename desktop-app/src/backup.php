<?php
/**
 * Backup & Restore
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'ສຳຮອງ ແລະ ກູ້ຄືນຂໍ້ມູນ';
$message = '';
$messageType = '';

if (isset($_SESSION['backup_message'])) {
    $message = $_SESSION['backup_message'];
    $messageType = $_SESSION['backup_message_type'];
    unset($_SESSION['backup_message']);
    unset($_SESSION['backup_message_type']);
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">ສຳຮອງ & ກູ້ຄືນຂໍ້ມູນ (Backup & Restore)</h1>
    </div>

    <div class="content-area">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 20px;">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="settings-layout">
            <!-- Backup Section -->
            <div class="card settings-form-card" style="flex:1;">
                <div class="card-header-flex">
                    <h3 class="card-section-title"><i class="fas fa-download"></i> ສຳຮອງຂໍ້ມູນ (Backup)</h3>
                </div>
                <p style="color:var(--text-medium);margin-bottom:20px;">
                    ການສຳຮອງຂໍ້ມູນຈະດາວໂຫຼດຖານຂໍ້ມູນທັງໝົດເປັນໄຟລ໌ <code><?= (defined('DB_CONNECTION') && DB_CONNECTION === 'sqlite') ? '.sqlite / .db' : '.sql' ?></code> ມາຍັງເຄື່ອງຂອງທ່ານ. ຄວນສຳຮອງຂໍ້ມູນເປັນປະຈຳເພື່ອປ້ອງກັນຂໍ້ມູນສູນຫາຍ.
                </p>
                <a href="api/backup_export.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px;">
                    <i class="fas fa-database"></i> ດາວໂຫຼດຖານຂໍ້ມູນ
                </a>
            </div>

            <!-- Restore Section -->
            <div class="card settings-preview-card" style="flex:1; border-color:#ffcc80;">
                <div class="card-header-flex">
                    <h3 class="card-section-title"><i class="fas fa-upload"></i> ກູ້ຄືນຂໍ້ມູນ (Restore)</h3>
                </div>
                <p style="color:var(--text-medium);margin-bottom:20px;">
                    ເລືອກໄຟລ໌ <code><?= (defined('DB_CONNECTION') && DB_CONNECTION === 'sqlite') ? '.db ຫຼື .sqlite' : '.sql' ?></code> ທີ່ເຄີຍສຳຮອງໄວ້ເພື່ອກູ້ຄືນຂໍ້ມູນ. 
                    <strong style="color:var(--danger);display:block;margin-top:8px;">
                        <i class="fas fa-exclamation-triangle"></i> ຄຳເຕືອນ: ຂໍ້ມູນປັດຈຸບັນຈະຖືກແທນທີ່ດ້ວຍຂໍ້ມູນຈາກໄຟລ໌ທັງໝົດ!
                    </strong>
                </p>
                
                <form action="api/backup_import.php" method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <input type="file" name="backup_file" class="form-control" accept="<?= (defined('DB_CONNECTION') && DB_CONNECTION === 'sqlite') ? '.db,.sqlite' : '.sql' ?>" required style="padding:10px;">
                    </div>
                    <button type="submit" class="btn btn-danger" onclick="confirmRestore(event)" style="display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-sync-alt"></i> ເລີ່ມກູ້ຄືນຂໍ້ມູນ
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
async function confirmRestore(event) {
    event.preventDefault();
    const form = event.target.closest('form');
    const confirmed = await confirmDelete('ທ່ານແນ່ໃຈຫຼືບໍ່ວ່າຕ້ອງການກູ້ຄືນຂໍ້ມູນ? ຂໍ້ມູນປັດຈຸບັນຈະຖືກລຶບຖິ້ມທັງໝົດ!');
    if (confirmed) {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງກູ້ຄືນຂໍ້ມູນ...';
        }
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
