<?php
/**
 * 404 Not Found Page
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once __DIR__ . '/includes/functions.php';
setSecurityHeaders();

$settings = [];
try {
    $settings = getShopSettings();
} catch (Exception $e) {}

$shopName = $settings['shop_name'] ?? 'ລະບົບຂາຍຢາດີ';
$shopLogo = $settings['logo'] ?? '';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="404 ບໍ່ພົບໜ້າທີ່ທ່ານຕ້ອງການ - <?= htmlspecialchars($shopName) ?>">
    <title>404 ບໍ່ພົບໜ້າທີ່ຕ້ອງການ | <?= htmlspecialchars($shopName) ?></title>
    <link rel="icon" href="<?= BASE_URL ?>assets/images/favicon.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fonts/noto-sans-lao.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        body {
            font-family: 'Noto Sans Lao', sans-serif;
            background: linear-gradient(135deg, var(--bg-gradient-start, #b2dfdb) 0%, var(--bg-gradient-mid, #c8e6c9) 40%, var(--bg-gradient-end, #dcedc8) 100%);
            color: #263238;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .error-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            max-width: 520px;
            width: 100%;
            padding: 45px 35px;
            text-align: center;
        }
        .error-code {
            font-size: 5rem;
            font-weight: 800;
            color: var(--primary, #2e7d32);
            line-height: 1;
            margin-bottom: 10px;
            letter-spacing: -2px;
        }
        .error-icon {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: #e8f5e9;
            color: var(--primary, #2e7d32);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 20px;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1b5e20;
            margin-bottom: 12px;
        }
        p {
            font-size: 0.95rem;
            color: #546e7a;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .error-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .btn-primary-action {
            background: var(--primary, #2e7d32);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
        }
        .btn-primary-action:hover {
            background: #1b5e20;
            transform: translateY(-1px);
        }
        .btn-secondary-action {
            background: #ffffff;
            color: #263238;
            border: 1px solid #cfd8dc;
        }
        .btn-secondary-action:hover {
            border-color: var(--primary, #2e7d32);
            color: var(--primary, #2e7d32);
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">
            <i class="fas fa-prescription-bottle-alt"></i>
        </div>
        <div class="error-code">404</div>
        <h1>ບໍ່ພົບໜ້າທີ່ທ່ານຕ້ອງການ</h1>
        <p>ໜ້າເວັບທີ່ທ່ານກຳລັງຊອກຫາອາດຖືກຍ້າຍ, ລຶບອອກ ຫຼື ບໍ່ມີຢູ່ໃນລະບົບຂາຍຢາ.</p>
        <div class="error-actions">
            <a href="<?= BASE_URL ?>index.php" class="btn-action btn-primary-action">
                <i class="fas fa-home"></i> ກັບຄືນໜ້າຫຼັກ
            </a>
            <a href="<?= BASE_URL ?>pos.php" class="btn-action btn-secondary-action">
                <i class="fas fa-cash-register"></i> ໄປໜ້າຂາຍ (POS)
            </a>
        </div>
    </div>
</body>
</html>
