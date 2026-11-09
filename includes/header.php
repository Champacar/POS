<?php
/**
 * HTML Header
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
setSecurityHeaders();
$settings = null;
try {
    $settings = getShopSettings();
} catch (Exception $e) {
    // Ignore
}

$shopName = $settings['shop_name'] ?? 'ລະບົບຂາຍຢາດີ';

if (!isset($pageTitle)) {
    $pageTitle = $shopName;
}
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($shopName) ?> - ລະບົບຂາຍໜ້າຮ້ານ ແລະ ບໍລິຫານສາງຢາ (Point of Sale System)">
    <title><?php 
        if (!empty($pageTitle) && $pageTitle !== $shopName) {
            echo htmlspecialchars($pageTitle) . ' | ' . htmlspecialchars($shopName);
        } else {
            echo htmlspecialchars($shopName);
        }
    ?></title>

    <?php if (!empty($settings['favicon']) && file_exists(__DIR__ . '/../assets/images/' . $settings['favicon'])): ?>
        <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($settings['favicon']) ?>">
    <?php elseif (!empty($settings['logo']) && file_exists(__DIR__ . '/../assets/images/' . $settings['logo'])): ?>
        <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($settings['logo']) ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/favicon.png">
        <link rel="shortcut icon" href="<?= BASE_URL ?>assets/images/favicon.ico">
    <?php endif; ?>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css">

    <!-- SweetAlert2 -->
    <script src="<?= BASE_URL ?>assets/vendor/sweetalert2.all.min.js"></script>

    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    
    <!-- CSRF Token for AJAX -->
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCSRFToken()) ?>">
    
    <!-- Base URL for JavaScript -->
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const SYNC_MODE = '<?= SYNC_MODE ?>';
    </script>
</head>
<body>
    <!-- Mobile Toggle Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleMobileSidebar()" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>
