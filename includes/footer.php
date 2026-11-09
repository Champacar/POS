<?php
$footerShopName = $settings['shop_name'] ?? 'ລະບົບຂາຍຢາດີ';
$footerShopNameEn = $settings['shop_name_en'] ?? 'POINT OF SALE';
?>
    <!-- App Footer -->
    <footer class="app-footer">
        <div class="app-footer-inner">
            <div class="app-footer-left">
                <i class="fas fa-capsules" style="color: var(--primary);"></i>
                <span>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars($footerShopName) ?></strong> (<?= htmlspecialchars($footerShopNameEn) ?>) &mdash; ລະບົບບໍລິຫານຮ້ານຂາຍຢາ</span>
            </div>
            <div class="app-footer-right">
                <?php if (!empty($_SESSION['full_name'])): ?>
                    <a href="<?= BASE_URL ?>profile.php" class="footer-user" title="ແກ້ໄຂໂປຣໄຟລ" style="color:inherit; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                        <?php 
                        $footerAvatar = getUserAvatarUrl($_SESSION['avatar'] ?? null);
                        if ($footerAvatar): 
                        ?>
                            <img src="<?= $footerAvatar ?>?v=<?= time() ?>" alt="Avatar" style="width:18px; height:18px; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($_SESSION['full_name']) ?> (<?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'user')) ?>)</span>
                    </a>
                    <span class="footer-divider">|</span>
                <?php endif; ?>
                <span class="footer-version"><i class="fas fa-code-branch"></i> v1.0.0</span>
            </div>
        </div>
    </footer>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Hardware Integration & QZ Tray Bridge -->
    <script src="<?= BASE_URL ?>assets/vendor/qz-tray.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/qz_bridge.js?v=<?= time() ?>"></script>

    <!-- Main JS -->
    <script src="<?= BASE_URL ?>assets/js/main.js?v=<?= time() ?>"></script>
    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <?php if ($script === 'charts.js'): ?>
                <script src="<?= BASE_URL ?>assets/vendor/chart.umd.min.js"></script>
            <?php endif; ?>
            <script src="<?= BASE_URL ?>assets/js/<?= $script ?>?v=<?= time() ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
