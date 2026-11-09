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
                    <span class="footer-user"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['full_name']) ?> (<?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'user')) ?>)</span>
                    <span class="footer-divider">|</span>
                <?php endif; ?>
                <span class="footer-version"><i class="fas fa-code-branch"></i> v1.0.0</span>
            </div>
        </div>
    </footer>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

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
