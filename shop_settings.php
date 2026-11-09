<?php
/**
 * ຕັ້ງຄ່າຮ້ານ - Shop Settings
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireAdmin();

$pageTitle = 'ຕັ້ງຄ່າຮ້ານ';

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    try {
        $db = getDB();
        
        $shopName = $_POST['shop_name'] ?? 'ຮ້ານຂາຍຢາດີ';
        $shopNameEn = $_POST['shop_name_en'] ?? '';
        $address = $_POST['address'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $openTime = $_POST['open_time'] ?? '08:00';
        $closeTime = $_POST['close_time'] ?? '20:00';
        $showInReceipt = isset($_POST['show_in_receipt']) ? 1 : 0;
        $receiptSize = $_POST['receipt_size'] ?? '80mm';
        $taxRate = $_POST['tax_rate'] ?? 10.00;
        $taxType = $_POST['tax_type'] ?? 'excluded';
        $enableMultiCurrency = isset($_POST['enable_multi_currency']) ? 1 : 0;
        $exchangeRateThb = !empty($_POST['exchange_rate_thb']) ? (float)$_POST['exchange_rate_thb'] : 680.00;
        $exchangeRateUsd = !empty($_POST['exchange_rate_usd']) ? (float)$_POST['exchange_rate_usd'] : 22000.00;
        $enableCashDrawer = isset($_POST['enable_cash_drawer']) ? 1 : 0;
        $cashDrawerMode = $_POST['cash_drawer_mode'] ?? 'driver';
        $enableDynamicQr = isset($_POST['enable_dynamic_qr']) ? 1 : 0;
        $bankName = trim($_POST['bank_name'] ?? 'BCEL One');
        $bankAccountNumber = trim($_POST['bank_account_number'] ?? '');
        $bankAccountName = trim($_POST['bank_account_name'] ?? '');
        $qrPayload = trim($_POST['qr_payload'] ?? '');
        $qrMode = trim($_POST['qr_mode'] ?? 'auto');
        $qrUriScheme = isset($_POST['qr_uri_scheme']) ? 1 : 0;
        
        // Helper to sync asset files across desktop app directories
        $syncAssetFile = function($relativeFilePath) {
            $src = __DIR__ . '/' . $relativeFilePath;
            if (!file_exists($src)) return;
            $targets = [
                'D:/Project/pospharma/' . $relativeFilePath,
                'D:/Project/pospharma/desktop-app/src/' . $relativeFilePath,
                'D:/Project/pospharma/dist/desktop/win-unpacked/resources/app/src/' . $relativeFilePath,
            ];
            foreach ($targets as $dest) {
                if (realpath($dest) !== realpath($src)) {
                    $dir = dirname($dest);
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    @copy($src, $dest);
                }
            }
        };

        // Handle logo upload
        $logoPath = !empty($_POST['existing_logo']) ? $_POST['existing_logo'] : NULL;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir('assets/images')) {
                mkdir('assets/images', 0777, true);
            }
            $fileExtension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $logoName = 'logo_' . time() . '.' . $fileExtension;
            $targetFile = 'assets/images/' . $logoName;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
                $logoPath = $logoName;
                $syncAssetFile('assets/images/' . $logoName);
            }
        }
        
        // Handle footer image upload
        $footerImagePath = !empty($_POST['existing_footer_image']) ? $_POST['existing_footer_image'] : NULL;
        if (isset($_FILES['footer_image']) && $_FILES['footer_image']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir('assets/images')) {
                mkdir('assets/images', 0777, true);
            }
            $fileExtension = pathinfo($_FILES['footer_image']['name'], PATHINFO_EXTENSION);
            $footerImageName = 'footer_' . time() . '.' . $fileExtension;
            $targetFile = 'assets/images/' . $footerImageName;
            if (move_uploaded_file($_FILES['footer_image']['tmp_name'], $targetFile)) {
                $footerImagePath = $footerImageName;
                $syncAssetFile('assets/images/' . $footerImageName);
            }
        }
        
        // Handle favicon upload
        $faviconPath = !empty($_POST['existing_favicon']) ? $_POST['existing_favicon'] : NULL;
        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir('assets/images')) {
                mkdir('assets/images', 0777, true);
            }
            $fileExtension = pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION);
            $faviconName = 'favicon_' . time() . '.' . $fileExtension;
            $targetFile = 'assets/images/' . $faviconName;
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $targetFile)) {
                $faviconPath = $faviconName;
                $syncAssetFile('assets/images/' . $faviconName);
            }
        }
        
        // Handle qr_code upload
        $qrCodePath = !empty($_POST['existing_qr_code']) ? $_POST['existing_qr_code'] : NULL;
        if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir('assets/images')) {
                mkdir('assets/images', 0777, true);
            }
            $fileExtension = pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION);
            $qrCodeName = 'qrcode_' . time() . '.' . $fileExtension;
            $targetFile = 'assets/images/' . $qrCodeName;
            if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $targetFile)) {
                $qrCodePath = $qrCodeName;
                $syncAssetFile('assets/images/' . $qrCodeName);
            }
        }
        
        // Handle cfd_media upload
        $cfdMediaPath = !empty($_POST['existing_cfd_media']) ? $_POST['existing_cfd_media'] : NULL;
        if (isset($_FILES['cfd_media']) && $_FILES['cfd_media']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir('assets/media')) {
                mkdir('assets/media', 0777, true);
            }
            $fileExtension = pathinfo($_FILES['cfd_media']['name'], PATHINFO_EXTENSION);
            $cfdMediaName = 'cfd_' . time() . '.' . $fileExtension;
            $targetFile = 'assets/media/' . $cfdMediaName;
            if (move_uploaded_file($_FILES['cfd_media']['tmp_name'], $targetFile)) {
                $cfdMediaPath = $cfdMediaName;
                $syncAssetFile('assets/media/' . $cfdMediaName);
            }
        }
        
        $stmt = $db->prepare("UPDATE shop_settings SET shop_name = ?, shop_name_en = ?, address = ?, phone = ?, email = ?, open_time = ?, close_time = ?, show_in_receipt = ?, receipt_size = ?, tax_rate = ?, tax_type = ?, logo = ?, favicon = ?, footer_image = ?, qr_code = ?, cfd_media = ?, enable_multi_currency = ?, exchange_rate_thb = ?, exchange_rate_usd = ?, enable_cash_drawer = ?, cash_drawer_mode = ?, enable_dynamic_qr = ?, bank_name = ?, bank_account_number = ?, bank_account_name = ?, qr_payload = ?, qr_mode = ?, qr_uri_scheme = ? WHERE id = 1");
        $stmt->execute([
            $shopName,
            $shopNameEn,
            $address,
            $phone,
            $email,
            $openTime,
            $closeTime,
            $showInReceipt,
            $receiptSize,
            $taxRate,
            $taxType,
            $logoPath,
            $faviconPath,
            $footerImagePath,
            $qrCodePath,
            $cfdMediaPath,
            $enableMultiCurrency,
            $exchangeRateThb,
            $exchangeRateUsd,
            $enableCashDrawer,
            $cashDrawerMode,
            $enableDynamicQr,
            $bankName,
            $bankAccountNumber,
            $bankAccountName,
            $qrPayload,
            $qrMode,
            $qrUriScheme
        ]);
        
        logActivity('settings_update', "ອັບເດດຂໍ້ມູນ ແລະ ການຕັ້ງຄ່າຮ້ານຄ້າ: {$shopName}", 'settings');

        $message = 'ບັນທຶກການຕັ້ງຄ່າສຳເລັດ!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'ເກີດຂໍ້ຜິດພາດ: ' . $e->getMessage();
        $messageType = 'error';
    }
}

try {
    $settings = getShopSettings();
} catch (Exception $e) {
    $settings = [
        'shop_name' => 'ຮ້ານຂາຍຢາດີ',
        'address' => '',
        'phone' => '',
        'email' => '',
        'open_time' => '08:00',
        'close_time' => '20:00',
        'show_in_receipt' => 1,
        'receipt_size' => '80mm',
        'tax_rate' => 10,
        'tax_type' => 'excluded',
        'logo' => NULL,
        'favicon' => NULL,
        'footer_image' => NULL,
        'enable_multi_currency' => 1,
        'exchange_rate_thb' => 680.00,
        'exchange_rate_usd' => 22000.00,
        'enable_cash_drawer' => 1,
        'cash_drawer_mode' => 'driver',
        'enable_dynamic_qr' => 1,
        'bank_name' => 'BCEL One',
        'bank_account_number' => '',
        'bank_account_name' => '',
        'qr_payload' => '',
        'qr_mode' => 'auto',
        'qr_uri_scheme' => 0
    ];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">ຂໍ້ມູນຮ້ານ</h1>
    </div>

    <div class="content-area">
        <form method="POST" action="" enctype="multipart/form-data" onsubmit="const btn = this.querySelector('button[type=submit]'); if(btn){ btn.disabled=true; btn.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> ກຳລັງບັນທຶກ...'; }">
            <?= csrfField() ?>
            <div class="settings-layout">
                <!-- Left: Form Card -->
                <div class="card settings-form-card">
                    <div class="card-header-flex">
                        <h3 class="card-section-title">ລາຍລະອຽດຂໍ້ມູນຮ້ານ</h3>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> ບັນທຶກຂໍ້ມູນ
                        </button>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">ຊື່ຮ້ານ (ພາສາລາວ) *</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-store input-left-icon"></i>
                                <input type="text" class="form-control" name="shop_name" id="inputShopName" value="<?= sanitize($settings['shop_name'] ?? '') ?>" required oninput="updateReceiptPreview()">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ຊື່ຮ້ານ (ພາສາອັງກິດ)</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-store input-left-icon"></i>
                                <input type="text" class="form-control" name="shop_name_en" id="inputShopNameEn" value="<?= sanitize($settings['shop_name_en'] ?? '') ?>" oninput="updateReceiptPreview()">
                            </div>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">ທີ່ຢູ່ / ສະຖານທີ່</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-map-marker-alt input-left-icon"></i>
                                <input type="text" class="form-control" name="address" id="inputAddress" value="<?= sanitize($settings['address'] ?? '') ?>" oninput="updateReceiptPreview()">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ເບີໂທລະສັບ</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-phone input-left-icon"></i>
                                <input type="text" class="form-control" name="phone" id="inputPhone" value="<?= sanitize($settings['phone'] ?? '') ?>" oninput="updateReceiptPreview()">
                            </div>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">ອີເມວ</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-envelope input-left-icon"></i>
                                <input type="email" class="form-control" name="email" id="inputEmail" value="<?= sanitize($settings['email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">ເວລາເປີດຮ້ານ</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-clock input-left-icon"></i>
                                <select class="form-control" name="open_time" id="inputOpenTime" onchange="updateReceiptPreview()">
                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                        <?php $t = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'; ?>
                                        <option value="<?= $t ?>" <?= ($settings['open_time'] ?? '08:00') === $t ? 'selected' : '' ?>><?= $t ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ເວລາປິດຮ້ານ</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-clock input-left-icon"></i>
                                <select class="form-control" name="close_time" id="inputCloseTime" onchange="updateReceiptPreview()">
                                    <?php for ($h = 0; $h < 24; $h++): ?>
                                        <?php $t = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'; ?>
                                        <option value="<?= $t ?>" <?= ($settings['close_time'] ?? '20:00') === $t ? 'selected' : '' ?>><?= $t ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ສະແດງໃນໃບບິນ</label>
                            <div style="display:flex;align-items:center;height:40px;">
                                <label class="switch-toggle">
                                    <input type="checkbox" name="show_in_receipt" id="inputShowInReceipt" value="1" <?= ($settings['show_in_receipt'] ?? 1) ? 'checked' : '' ?> onchange="updateReceiptPreview()">
                                    <span class="slider-round"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">ແພັກເກັດການໃຊ້ງານ</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-shield-alt input-left-icon" style="color:var(--primary);"></i>
                                <input type="text" class="form-control" value="ພຣີມຽມ" readonly style="background:#f4f6f8;font-weight:600;color:var(--primary);cursor:default;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ຂະໜາດໃບບິນ</label>
                            <div class="segmented-control">
                                <input type="radio" id="size56" name="receipt_size" value="56mm" <?= ($settings['receipt_size'] ?? '80mm') === '56mm' ? 'checked' : '' ?> onchange="changeReceiptSize('56mm')">
                                <label for="size56">56mm</label>
                                
                                <input type="radio" id="size80" name="receipt_size" value="80mm" <?= ($settings['receipt_size'] ?? '80mm') === '80mm' ? 'checked' : '' ?> onchange="changeReceiptSize('80mm')">
                                <label for="size80">80mm</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">ປະເພດອາກອນ (VAT)</label>
                            <div class="segmented-control">
                                <input type="radio" id="tax_included" name="tax_type" value="included" <?= ($settings['tax_type'] ?? 'excluded') === 'included' ? 'checked' : '' ?> onchange="updateReceiptPreview()">
                                <label for="tax_included">ລວມໃນ</label>
                                
                                <input type="radio" id="tax_excluded" name="tax_type" value="excluded" <?= ($settings['tax_type'] ?? 'excluded') === 'excluded' ? 'checked' : '' ?> onchange="updateReceiptPreview()">
                                <label for="tax_excluded">ນອກ</label>
                                
                                <input type="radio" id="tax_none" name="tax_type" value="none" <?= ($settings['tax_type'] ?? 'excluded') === 'none' ? 'checked' : '' ?> onchange="updateReceiptPreview()">
                                <label for="tax_none">ບໍ່ມີ</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ອັດຕາອາກອນ (%)</label>
                            <div class="input-suffix-wrapper">
                                <input type="number" class="form-control" name="tax_rate" id="inputTaxRate" value="<?= $settings['tax_rate'] ?? 10 ?>" min="0" max="100" step="0.01" oninput="updateReceiptPreview()">
                                <span class="input-suffix">%</span>
                            </div>
                        </div>
                    </div>

                    <hr class="form-divider">

                    <div class="form-row-3">
                        <div class="form-group">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <label class="form-label" style="margin-bottom:0;">ໂລໂກ້ຮ້ານ</label>
                                <button type="button" id="btnDelete_logo" class="btn-clear-upload" onclick="clearUpload('logo', event)" title="ລຶບໂລໂກ້" style="<?= empty($settings['logo']) ? 'display:none;' : '' ?>">
                                    <i class="fas fa-trash-alt"></i> ລຶບ
                                </button>
                            </div>
                            <input type="hidden" name="existing_logo" value="<?= $settings['logo'] ?? '' ?>">
                            <div class="upload-zone position-relative" tabindex="0" role="button" onclick="document.getElementById('logoFileInput').click()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}">
                                <button type="button" id="floatDelete_logo" class="btn-remove-upload" onclick="clearUpload('logo', event)" title="ລຶບຮູບ" style="<?= empty($settings['logo']) ? 'display:none;' : '' ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <input type="file" id="logoFileInput" name="logo" style="display:none;" accept="image/*" onchange="previewUpload(this, 'logoPreview')">
                                <div class="upload-preview-container" id="logoPreview">
                                    <?php if (!empty($settings['logo'])): ?>
                                        <img src="assets/images/<?= $settings['logo'] ?>" alt="Logo">
                                    <?php else: ?>
                                        <div class="upload-placeholder">
                                            <i class="far fa-image"></i>
                                            <span>ເລືອກໂລໂກ້<br><small>ໄຟລ໌ໃຫຍ່ບໍ່ເກີນ 2MB</small></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <label class="form-label" style="margin-bottom:0;">ໄອຄອນເວັບ (Favicon)</label>
                                <button type="button" id="btnDelete_favicon" class="btn-clear-upload" onclick="clearUpload('favicon', event)" title="ລຶບ Favicon" style="<?= empty($settings['favicon']) ? 'display:none;' : '' ?>">
                                    <i class="fas fa-trash-alt"></i> ລຶບ
                                </button>
                            </div>
                            <input type="hidden" name="existing_favicon" value="<?= $settings['favicon'] ?? '' ?>">
                            <div class="upload-zone position-relative" tabindex="0" role="button" onclick="document.getElementById('faviconFileInput').click()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}">
                                <button type="button" id="floatDelete_favicon" class="btn-remove-upload" onclick="clearUpload('favicon', event)" title="ລຶບຮູບ" style="<?= empty($settings['favicon']) ? 'display:none;' : '' ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <input type="file" id="faviconFileInput" name="favicon" style="display:none;" accept="image/*" onchange="previewUpload(this, 'faviconPreview')">
                                <div class="upload-preview-container" id="faviconPreview">
                                    <?php if (!empty($settings['favicon'])): ?>
                                        <img src="assets/images/<?= $settings['favicon'] ?>" alt="Favicon">
                                    <?php else: ?>
                                        <div class="upload-placeholder">
                                            <i class="far fa-image"></i>
                                            <span>ເລືອກ Favicon<br><small>ໄຟລ໌ໃຫຍ່ບໍ່ເກີນ 1MB</small></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <label class="form-label" style="margin-bottom:0;">ຮູບທ້າຍໃບບິນ</label>
                                <button type="button" id="btnDelete_footer" class="btn-clear-upload" onclick="clearUpload('footer', event)" title="ລຶບຮູບທ້າຍໃບບິນ" style="<?= empty($settings['footer_image']) ? 'display:none;' : '' ?>">
                                    <i class="fas fa-trash-alt"></i> ລຶບ
                                </button>
                            </div>
                            <input type="hidden" name="existing_footer_image" value="<?= $settings['footer_image'] ?? '' ?>">
                            <div class="upload-zone position-relative" tabindex="0" role="button" onclick="document.getElementById('footerFileInput').click()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}">
                                <button type="button" id="floatDelete_footer" class="btn-remove-upload" onclick="clearUpload('footer', event)" title="ລຶບຮູບ" style="<?= empty($settings['footer_image']) ? 'display:none;' : '' ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <input type="file" id="footerFileInput" name="footer_image" style="display:none;" accept="image/*" onchange="previewUpload(this, 'footerPreview')">
                                <div class="upload-preview-container" id="footerPreview">
                                    <?php if (!empty($settings['footer_image'])): ?>
                                        <img src="assets/images/<?= $settings['footer_image'] ?>" alt="Footer Image">
                                    <?php else: ?>
                                        <div class="upload-placeholder">
                                            <i class="far fa-image"></i>
                                            <span>ເລືອກຮູບ<br><small>ໄຟລ໌ໃຫຍ່ບໍ່ເກີນ 2MB</small></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Multi-Currency & Cash Drawer Settings Card -->
                <div class="card settings-form-card" style="margin-top: 24px;">
                    <div class="card-header-flex">
                        <h3 class="card-section-title"><i class="fas fa-coins" style="color:var(--primary); margin-right:6px;"></i> ລະບົບຫຼາຍສະກຸນເງິນ (Multi-Currency)</h3>
                        <label class="switch-toggle" title="ເປີດ/ປິດ ຮັບເງິນຫຼາຍສະກຸນ">
                            <input type="checkbox" name="enable_multi_currency" id="inputEnableMultiCurrency" value="1" <?= ($settings['enable_multi_currency'] ?? 1) ? 'checked' : '' ?>>
                            <span class="slider-round"></span>
                        </label>
                    </div>

                    <p style="font-size: 13.5px; color: #5e6c84; margin-bottom: 16px;">
                        ຮອງຮັບການຮັບເງິນສົດເປັນ <strong>ເງິນບາດ (THB)</strong> ແລະ <strong>ເງິນໂດລາ (USD)</strong> ທີ່ຈຸດຂາຍ (POS). ຍອດບັນຊີຫຼັກ, ລາຍງານຍອດຂາຍ ແລະ ລາຄາຢາຈະຍັງຄົງຄິດໄລ່ເປັນ <strong>ເງິນກີບ (LAK)</strong> ເພື່ອຄວາມຖືກຕ້ອງທາງບັນຊີ.
                    </p>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">ອັດຕາແລກປ່ຽນ 1 ບາດ (THB) ເທົ່າກັບກີບ *</label>
                            <div class="input-suffix-wrapper">
                                <input type="number" class="form-control" name="exchange_rate_thb" id="inputExchangeRateThb" value="<?= htmlspecialchars($settings['exchange_rate_thb'] ?? '680.00') ?>" step="0.01" min="1" required>
                                <span class="input-suffix">ກີບ (LAK)</span>
                            </div>
                            <small class="text-muted">ຕົວຢ່າງ: 1 ฿ = 680 ₭ (ຍອດ 100,000 ກີບ ≈ 147.06 ฿)</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ອັດຕາແລກປ່ຽນ 1 ໂດລາ (USD) ເທົ່າກັບກີບ *</label>
                            <div class="input-suffix-wrapper">
                                <input type="number" class="form-control" name="exchange_rate_usd" id="inputExchangeRateUsd" value="<?= htmlspecialchars($settings['exchange_rate_usd'] ?? '22000.00') ?>" step="0.01" min="1" required>
                                <span class="input-suffix">ກີບ (LAK)</span>
                            </div>
                            <small class="text-muted">ຕົວຢ່າງ: 1 $ = 22,000 ₭ (ຍອດ 110,000 ກີບ = 5.00 $)</small>
                        </div>
                    </div>

                    <hr class="form-divider" style="margin: 20px 0;">

                    <div class="card-header-flex" style="margin-bottom: 12px;">
                        <h3 class="card-section-title"><i class="fas fa-cash-register" style="color:var(--primary); margin-right:6px;"></i> ລະບົບລິ້ນຊັກເງິນ (Cash Drawer)</h3>
                        <label class="switch-toggle" title="ເປີດ/ປິດ ເປີດລິ້ນຊັກເງິນອັດຕະໂນມັດ">
                            <input type="checkbox" name="enable_cash_drawer" id="inputEnableCashDrawer" value="1" <?= ($settings['enable_cash_drawer'] ?? 1) ? 'checked' : '' ?>>
                            <span class="slider-round"></span>
                        </label>
                    </div>

                    <div class="form-row-2" style="align-items: center; gap: 20px;">
                        <div>
                            <p style="font-size: 13.5px; color: var(--text-dark); margin-bottom: 6px; font-weight: 600;">
                                ເປີດລິ້ນຊັກເງິນອັດຕະໂນມັດ ເມື່ອຂາຍເງິນສົດ (Auto Kick)
                            </p>
                            <p style="font-size: 12.5px; color: #5e6c84; margin-bottom: 0;">
                                ລິ້ນຊັກເງິນທີ່ຕໍ່ຜ່ານສາຍ RJ11 ເຂົ້າຊ່ອງ DK (Drawer Kick) ຫຼັງເຄື່ອງພິມໃບບິນ ຈະເປີດອອກເອງອັດຕະໂນມັດເມື່ອພິມບິນເງິນສົດ ຫຼື ກົດປຸ່ມລັດ <strong>F7</strong> ທີ່ໜ້າ POS.
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <button type="button" class="btn btn-outline" onclick="testCashDrawer()" style="min-height: 42px;">
                                <i class="fas fa-external-link-alt"></i> ທົດສອບເປີດລິ້ນຊັກ (Test Kick)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Payment & CFD Settings Card -->
                <div class="card settings-form-card" style="margin-top: 24px;">
                    <div class="card-header-flex">
                        <h3 class="card-section-title">ການຊຳລະເງິນ & ໜ້າຈໍລູກຄ້າ (CFD)</h3>
                        <a href="<?= BASE_URL ?>customer_display.php" target="_blank" class="btn btn-outline">
                            <i class="fas fa-desktop"></i> ເປີດໜ້າຈໍລູກຄ້າ
                        </a>
                    </div>

                    <!-- Dynamic LAO QR Box -->
                    <div style="background:#f0f7ff; border:1px solid #cce0ff; border-radius:10px; padding:18px 20px; margin-bottom:20px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <div>
                                <h4 style="margin:0; font-size:1.05rem; font-weight:700; color:#0052cc; display:flex; align-items:center; gap:8px;">
                                    <i class="fas fa-bolt" style="color:#ffab00;"></i> ລະບົບ Dynamic LAO QR ພ້ອມຝັງຍອດເງິນອັດຕະໂນມັດ (EMVCo)
                                </h4>
                                <p style="margin:4px 0 0 0; font-size:0.85rem; color:#5e6c84;">
                                    ສ້າງ QR Code ທີ່ລະບຸຍອດເງິນພໍດີແຕ່ລະບິນ ລູກຄ້າສະແກນດ້ວຍແອັບ BCEL One ຫຼື ທະນາຄານອື່ນ ແລ້ວຍອດເງິນຂຶ້ນທັນທີ ໂດຍບໍ່ຕ້ອງພິມເອງ (100% Offline)
                                </p>
                            </div>
                            <label class="switch" style="margin-left:16px;">
                                <input type="checkbox" name="enable_dynamic_qr" id="inputEnableDynamicQr" value="1" <?= !empty($settings['enable_dynamic_qr']) ? 'checked' : '' ?> onchange="toggleDynamicQrFields()">
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div id="dynamicQrFieldsContainer" style="display:<?= !empty($settings['enable_dynamic_qr']) ? 'block' : 'none' ?>; margin-top:16px; border-top:1px dashed #b3d4ff; padding-top:14px;">
                            <div class="form-row-3" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label" style="font-size:0.85rem; font-weight:600;">ທະນາຄານ / ແອັບ *</label>
                                    <select class="form-control" name="bank_name" id="inputBankName">
                                        <?php 
                                        $currentBank = $settings['bank_name'] ?? 'BCEL One';
                                        $banks = ['BCEL One', 'LDB Trust', 'JDB Yes', 'LaoVietBank', 'Hal-KB', 'Maruhan', 'Indochina Bank', 'ອື່ນໆ (LAPNet / Lao QR)'];
                                        foreach ($banks as $b):
                                        ?>
                                        <option value="<?= $b ?>" <?= $currentBank === $b ? 'selected' : '' ?>><?= $b ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label" style="font-size:0.85rem; font-weight:600;">ເລກບັນຊີ / ເລກໂທລະສັບຜູກບັນຊີ *</label>
                                    <input type="text" class="form-control" name="bank_account_number" id="inputBankAccountNumber" value="<?= htmlspecialchars($settings['bank_account_number'] ?? '') ?>" placeholder="010120000998877 ຫຼື 020XXXXXXXX">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label" style="font-size:0.85rem; font-weight:600;">ຊື່ບັນຊີ / ຊື່ຮ້ານຄ້າ *</label>
                                    <input type="text" class="form-control" name="bank_account_name" id="inputBankAccountName" value="<?= htmlspecialchars($settings['bank_account_name'] ?? '') ?>" placeholder="ຊື່ເຈົ້າຂອງບັນຊີ...">
                                </div>
                            </div>

                            <!-- Row 2: QR Mode & URI Scheme -->
                            <div class="form-row-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-top:14px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label" style="font-size:0.85rem; font-weight:600;">ມາດຕະຖານ / ຮູບແບບ QR Code</label>
                                    <select class="form-control" name="qr_mode" id="inputQrMode">
                                        <option value="auto" <?= ($settings['qr_mode'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>ອັດຕະໂນມັດ (BCEL One + LAPNet ປະສົມ - ແນະນຳ)</option>
                                        <option value="bcel" <?= ($settings['qr_mode'] ?? '') === 'bcel' ? 'selected' : '' ?>>BCEL One / OnePay ເທົ່ານັ້ນ (Tag 33 & 26)</option>
                                        <option value="lapnet" <?= ($settings['qr_mode'] ?? '') === 'lapnet' ? 'selected' : '' ?>>LAPNet / LAO QR ແຫ່ງຊາດ (ທຸກທະນາຄານ)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0; display:flex; align-items:flex-end;">
                                    <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; cursor:pointer; margin-bottom:10px;">
                                        <input type="checkbox" name="qr_uri_scheme" id="inputQrUriScheme" value="1" <?= !empty($settings['qr_uri_scheme']) ? 'checked' : '' ?>>
                                        <span>ຝັງຮູບແບບ <code>onepay://qr/</code> ສຳລັບແອັບ BCEL One</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Row 3: Raw Static QR Payload (Optional - Recommended for 100% genuine BCEL One) -->
                            <div style="margin-top:14px; padding:12px; background:#fff; border:1px solid #cce0ff; border-radius:8px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                    <label class="form-label" style="font-size:0.85rem; font-weight:700; color:#0052cc; margin-bottom:0;">
                                        <i class="fas fa-qrcode"></i> ລະຫັດ QR Code ຕົ້ນສະບັບຂອງຮ້ານ (Raw Static QR Payload - ທາງເລືອກແນະນຳ)
                                    </label>
                                    <button type="button" class="btn btn-sm btn-outline" onclick="validateStaticQrPayload()" style="font-size:0.78rem; padding:3px 8px;">
                                        <i class="fas fa-search"></i> ກວດສອບລະຫັດ QR
                                    </button>
                                </div>
                                <p style="font-size:0.78rem; color:#5e6c84; margin-bottom:6px;">
                                    ຖ້າທ່ານມີ QR Code ຈາກແອັບ BCEL One (My QR ຫຼື OnePay) ສາມາດກັອບປີ້ຂໍ້ຄວາມ ຫຼື ສະແກນເອົາລະຫັດມາໃສ່ບ່ອນນີ້. ລະບົບຈະນຳໃຊ້ລະຫັດຮ້ານຄ້າຕົວຈິງຂອງທ່ານໄປຝັງຍອດເງິນອັດຕະໂນມັດ 100%:
                                </p>
                                <textarea class="form-control" name="qr_payload" id="inputQrPayload" rows="2" style="font-family:monospace; font-size:0.8rem;" placeholder="ຕົວຢ່າງ: 000201010211... ຫຼື onepay://qr/000201..."><?= htmlspecialchars($settings['qr_payload'] ?? '') ?></textarea>
                                <div id="qrPayloadFeedback" style="display:none; margin-top:6px; font-size:0.82rem; padding:6px 10px; border-radius:6px;"></div>
                            </div>

                            <!-- Row 4: Interactive Live Test & Scan Area -->
                            <div style="margin-top:14px; padding:14px; background:linear-gradient(180deg, #f0f7ff 0%, #fff 100%); border:1px solid #b3d4ff; border-radius:10px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                                    <strong style="color:#0052cc; font-size:0.9rem; display:flex; align-items:center; gap:6px;">
                                        <i class="fas fa-vial"></i> ທົດສອບສ້າງ Dynamic QR & ສະແກນດ້ວຍມືຖືຕົວຈິງ
                                    </strong>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <span style="font-size:0.8rem; color:#5e6c84;">ຍອດທົດສອບ:</span>
                                        <input type="number" id="testQrAmountInput" value="10000" step="1000" style="width:110px; padding:4px 8px; font-size:0.85rem; border:1px solid #b3d4ff; border-radius:6px; font-weight:700; text-align:right;">
                                        <span style="font-size:0.8rem; font-weight:700;">₭</span>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="triggerTestQrGeneration()" style="font-size:0.8rem; padding:4px 10px;">
                                            <i class="fas fa-sync-alt"></i> ສ້າງ QR
                                        </button>
                                    </div>
                                </div>

                                <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                                    <div id="testQrContainer" style="width:140px; height:140px; background:#fff; border:1px solid #d0e2ff; border-radius:8px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                                        <span style="color:#8993a4; font-size:0.75rem;">ກົດປຸ່ມສ້າງ QR</span>
                                    </div>
                                    <div style="flex:1; min-width:240px;">
                                        <div style="font-size:0.85rem; font-weight:700; color:#172b4d;" id="testQrDetails">
                                            ທະນາຄານ: <span id="testQrBank">-</span> | ຍອດເງິນ: <span id="testQrAmount" style="color:#0052cc;">0 ₭</span>
                                        </div>
                                        <div style="font-size:0.8rem; color:#5e6c84; margin-top:2px;">
                                            ບັນຊີ: <span id="testQrAccount">-</span> (<span id="testQrName">-</span>)
                                        </div>
                                        <div style="margin-top:6px; font-size:0.78rem; background:#fff; padding:6px 10px; border-radius:6px; border:1px solid #e2e8f0; word-break:break-all; font-family:monospace; max-height:55px; overflow-y:auto;" id="testQrPayloadText">
                                            ກົດປຸ່ມສ້າງ QR ເພື່ອເບິ່ງ Payload String
                                        </div>
                                        <div style="margin-top:6px; font-size:0.8rem; color:#059669; font-weight:600;" id="testQrStatus">
                                            👉 ທ່ານສາມາດເປີດແອັບ <b>BCEL One</b> ໃນມືຖື ແລ້ວສະແກນ QR ນີ້ໄດ້ທັນທີ!
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <label class="form-label" style="margin-bottom:0;">QR Code ຮັບເງິນ (ສຳລັບການໂອນ)</label>
                                <button type="button" id="btnDelete_qr_code" class="btn-clear-upload" onclick="clearUpload('qr_code', event)" title="ລຶບ QR Code" style="<?= empty($settings['qr_code']) ? 'display:none;' : '' ?>">
                                    <i class="fas fa-trash-alt"></i> ລຶບ
                                </button>
                            </div>
                            <input type="hidden" name="existing_qr_code" value="<?= $settings['qr_code'] ?? '' ?>">
                            <div class="upload-zone position-relative" tabindex="0" role="button" onclick="document.getElementById('qrCodeFileInput').click()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}">
                                <button type="button" id="floatDelete_qr_code" class="btn-remove-upload" onclick="clearUpload('qr_code', event)" title="ລຶບຮູບ" style="<?= empty($settings['qr_code']) ? 'display:none;' : '' ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <input type="file" id="qrCodeFileInput" name="qr_code" style="display:none;" accept="image/*" onchange="previewUpload(this, 'qrCodePreview')">
                                <div class="upload-preview-container" id="qrCodePreview">
                                    <?php if (!empty($settings['qr_code'])): ?>
                                        <img src="assets/images/<?= $settings['qr_code'] ?>" alt="QR Code">
                                    <?php else: ?>
                                        <div class="upload-placeholder">
                                            <i class="fas fa-qrcode"></i>
                                            <span>ເລືອກຮູບ QR Code<br><small>ສຳລັບໃຫ້ລູກຄ້າສະແກນຈ່າຍ</small></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <label class="form-label" style="margin-bottom:0;">ສື່ໂຄສະນາ (CFD Media)</label>
                                <button type="button" id="btnDelete_cfd_media" class="btn-clear-upload" onclick="clearUpload('cfd_media', event)" title="ລຶບສື່ໂຄສະນາ" style="<?= empty($settings['cfd_media']) ? 'display:none;' : '' ?>">
                                    <i class="fas fa-trash-alt"></i> ລຶບ
                                </button>
                            </div>
                            <input type="hidden" name="existing_cfd_media" value="<?= $settings['cfd_media'] ?? '' ?>">
                            <div class="upload-zone position-relative" tabindex="0" role="button" onclick="document.getElementById('cfdMediaFileInput').click()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}">
                                <button type="button" id="floatDelete_cfd_media" class="btn-remove-upload" onclick="clearUpload('cfd_media', event)" title="ລຶບຮູບ" style="<?= empty($settings['cfd_media']) ? 'display:none;' : '' ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <input type="file" id="cfdMediaFileInput" name="cfd_media" style="display:none;" accept="video/mp4,image/*" onchange="previewCFDMedia(this)">
                                <div class="upload-preview-container" id="cfdMediaPreview">
                                    <?php if (!empty($settings['cfd_media'])): ?>
                                        <?php if(str_ends_with(strtolower($settings['cfd_media']), '.mp4')): ?>
                                            <video src="assets/media/<?= $settings['cfd_media'] ?>" style="max-width:100%;max-height:100px;" muted></video>
                                        <?php else: ?>
                                            <img src="assets/media/<?= $settings['cfd_media'] ?>" alt="CFD Media">
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="upload-placeholder">
                                            <i class="fas fa-film"></i>
                                            <span>ເລືອກຮູບ ຫຼື ວິດີໂອ MP4<br><small>ສຳລັບສະແດງໃນຈໍລູກຄ້າ</small></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Save Action Bar -->
                        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" style="min-width: 160px; min-height: 44px;">
                                <i class="fas fa-save"></i> ບັນທຶກຂໍ້ມູນ
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right: Preview Card -->
                <div class="card settings-preview-card">
                    <div class="card-header-flex">
                        <h3 class="card-section-title" id="previewTitle">ຕົວຢ່າງໃບບິນ (<?= $settings['receipt_size'] ?? '80mm' ?>)</h3>
                        <button type="button" class="btn btn-outline btn-sm" onclick="printSampleReceipt()">
                            <i class="fas fa-print"></i> ພິມຕົວຢ່າງ
                        </button>
                    </div>

                    <div class="receipt-container-wrapper">
                        <!-- Thermal Receipt -->
                        <div class="receipt-preview-box size-<?= ($settings['receipt_size'] ?? '80mm') === '56mm' ? '56mm' : '80mm' ?>" id="receiptPreviewBox">
                            <div class="receipt-preview-header" id="receiptHeaderBlock">
                                <h2 id="prevShopName">ຮ້ານຂາຍຢາດີ</h2>
                                <div id="prevShopNameEn" style="font-size: 14px; font-weight: 700; margin-top: 2px;"></div>
                                <p id="prevAddress"><?= sanitize($settings['address'] ?? '') ?></p>
                                <p id="prevHours">ເວລາເປີດຮ້ານ 08:00 - 20:00</p>
                                <h3 class="receipt-type-title">ໃບຮັບເງິນ</h3>
                            </div>
                            
                            <div class="receipt-preview-meta">
                                <div class="meta-row">
                                    <span>ວັນທີ: <?= date('d/m/Y') ?></span>
                                    <span>14:54</span>
                                </div>
                                <div class="meta-row">
                                    <span>ເລກບິນ:</span>
                                    <span>INV-897535</span>
                                </div>
                            </div>

                            <div class="receipt-preview-divider"></div>

                            <table class="receipt-preview-table">
                                <thead>
                                    <tr>
                                        <th>ລາຍການ</th>
                                        <th style="text-align:center;">ຈຳນວນ</th>
                                        <th style="text-align:right;">ລາຄາ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Clarithromycin</td>
                                        <td style="text-align:center;">1 ກ່ອງ</td>
                                        <td style="text-align:right;">123,000</td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="receipt-preview-divider"></div>

                            <div class="receipt-preview-totals">
                                <div class="total-row">
                                    <span>ລວມຍອດ:</span>
                                    <span>123,000</span>
                                </div>
                                <div class="total-row" id="prevTaxRow">
                                    <span id="prevTaxLabel">ອາກອນ (10%):</span>
                                    <span id="prevTaxValue">12,300</span>
                                </div>
                                <div class="total-row grand-total">
                                    <span>ຍອດລວມ:</span>
                                    <span id="prevGrandTotal">135,300</span>
                                </div>
                            </div>

                            <div class="receipt-preview-payment">
                                <span>ວິທີຊຳລະ:</span>
                                <span>ເງິນສົດ</span>
                            </div>

                            <div class="receipt-preview-divider"></div>

                            <div class="receipt-preview-qr" style="text-align: center; margin: 8px 0; font-family: 'Noto Sans Lao', sans-serif;">
                                <div id="prevInvoiceQr" style="display: inline-block; background: #fff; line-height: 0;">
                                    <?= generateQrCodeSvg('INV-897535', 75) ?>
                                </div>
                                <div style="font-size: 10.5px; font-weight: 700; color: #000; margin-top: 3px; letter-spacing: 0.5px;">INV-897535</div>
                                <div style="font-size: 8px; color: #555; margin-top: 1px;">(ສະແກນກວດສອບເລກທີບິນ)</div>
                            </div>

                            <div class="receipt-preview-footer">
                                <p class="footer-msg">ຂໍຂອບໃຈ</p>
                                <div class="footer-stars">★ ★ ★ ★ ★ ★ ★ ★ ★ ★</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Initial call to load live values from inputs into receipt preview
    updateReceiptPreview();
});

function updateReceiptPreview() {
    const shopName = document.getElementById('inputShopName').value || 'ຮ້ານຂາຍຢາດີ';
    const shopNameEn = document.getElementById('inputShopNameEn').value || '';
    const address = document.getElementById('inputAddress').value || '';
    const phone = document.getElementById('inputPhone').value || '';
    const openTime = document.getElementById('inputOpenTime').value;
    const closeTime = document.getElementById('inputCloseTime').value;
    const showInReceipt = document.getElementById('inputShowInReceipt').checked;
    const taxRate = parseFloat(document.getElementById('inputTaxRate').value) || 0;
    const taxType = document.querySelector('input[name="tax_type"]:checked').value;

    // Header updates
    document.getElementById('prevShopName').textContent = shopName;
    
    const prevShopNameEnEl = document.getElementById('prevShopNameEn');
    prevShopNameEnEl.textContent = shopNameEn;
    if (shopNameEn) {
        prevShopNameEnEl.style.display = '';
    } else {
        prevShopNameEnEl.style.display = 'none';
    }

    document.getElementById('prevAddress').textContent = address + (phone ? ' (ໂທ: ' + phone + ')' : '');
    document.getElementById('prevHours').textContent = `ເວລາເປີດຮ້ານ ${openTime} - ${closeTime}`;

    // Visibility toggle
    const headerBlock = document.getElementById('receiptHeaderBlock');
    if (showInReceipt) {
        headerBlock.style.display = '';
    } else {
        headerBlock.style.display = 'none';
    }

    // Totals calculations
    const subtotal = 123000;
    let tax = 0;
    let grandTotal = subtotal;
    let taxLabel = '';
    
    if (taxType === 'excluded') {
        tax = Math.round(subtotal * (taxRate / 100));
        grandTotal = subtotal + tax;
        taxLabel = `ອາກອນນອກ (${taxRate}%):`;
    } else if (taxType === 'included') {
        tax = Math.round(subtotal - (subtotal / (1 + (taxRate / 100))));
        grandTotal = subtotal;
        taxLabel = `ອາກອນລວມໃນ (${taxRate}%):`;
    } else {
        tax = 0;
        grandTotal = subtotal;
        taxLabel = `ອາກອນ:`;
    }

    document.getElementById('prevTaxLabel').textContent = taxLabel;
    
    if (taxType === 'none') {
        document.getElementById('prevTaxRow').style.display = 'none';
    } else {
        document.getElementById('prevTaxRow').style.display = '';
        document.getElementById('prevTaxValue').textContent = formatCurrency(tax);
    }
    
    document.getElementById('prevGrandTotal').textContent = formatCurrency(grandTotal);
}

function changeReceiptSize(size) {
    const previewBox = document.getElementById('receiptPreviewBox');
    const titleEl = document.getElementById('previewTitle');

    if (size === '56mm') {
        previewBox.classList.remove('size-80mm');
        previewBox.classList.add('size-56mm');
        titleEl.textContent = 'ຕົວຢ່າງໃບບິນ (56mm)';
    } else {
        previewBox.classList.remove('size-56mm');
        previewBox.classList.add('size-80mm');
        titleEl.textContent = 'ຕົວຢ່າງໃບບິນ (80mm)';
    }
}

function previewUpload(input, previewId) {
    const previewContainer = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            previewContainer.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            
            let type = '';
            if (previewId === 'logoPreview') type = 'logo';
            else if (previewId === 'faviconPreview') type = 'favicon';
            else if (previewId === 'footerPreview') type = 'footer';
            else if (previewId === 'qrCodePreview') type = 'qr_code';
            
            if (type) {
                const btnDelete = document.getElementById('btnDelete_' + type);
                const floatDelete = document.getElementById('floatDelete_' + type);
                if (btnDelete) btnDelete.style.display = 'inline-flex';
                if (floatDelete) floatDelete.style.display = 'inline-flex';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewCFDMedia(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        const previewId = 'cfdMediaPreview';
        reader.onload = function(e) {
            const container = document.getElementById(previewId);
            if (file.type.startsWith('video/')) {
                container.innerHTML = `<video src="${e.target.result}" style="max-width:100%;max-height:100px;" muted autoplay loop></video>`;
            } else {
                container.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            }
            const btnDelete = document.getElementById('btnDelete_cfd_media');
            const floatDelete = document.getElementById('floatDelete_cfd_media');
            if (btnDelete) btnDelete.style.display = 'inline-flex';
            if (floatDelete) floatDelete.style.display = 'inline-flex';
        };
        reader.readAsDataURL(file);
    }
}

function clearUpload(type, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const config = {
        logo: {
            fileInput: 'logoFileInput',
            hiddenName: 'existing_logo',
            container: 'logoPreview',
            btnId: 'btnDelete_logo',
            floatId: 'floatDelete_logo',
            placeholder: `
                <div class="upload-placeholder">
                    <i class="far fa-image"></i>
                    <span>ເລືອກໂລໂກ້<br><small>ໄຟລ໌ໃຫຍ່ບໍ່ເກີນ 2MB</small></span>
                </div>
            `
        },
        favicon: {
            fileInput: 'faviconFileInput',
            hiddenName: 'existing_favicon',
            container: 'faviconPreview',
            btnId: 'btnDelete_favicon',
            floatId: 'floatDelete_favicon',
            placeholder: `
                <div class="upload-placeholder">
                    <i class="far fa-image"></i>
                    <span>ເລືອກ Favicon<br><small>ໄຟລ໌ໃຫຍ່ບໍ່ເກີນ 1MB</small></span>
                </div>
            `
        },
        footer: {
            fileInput: 'footerFileInput',
            hiddenName: 'existing_footer_image',
            container: 'footerPreview',
            btnId: 'btnDelete_footer',
            floatId: 'floatDelete_footer',
            placeholder: `
                <div class="upload-placeholder">
                    <i class="far fa-image"></i>
                    <span>ເລືອກຮູບ<br><small>ໄຟລ໌ໃຫຍ່ບໍ່ເກີນ 2MB</small></span>
                </div>
            `
        },
        qr_code: {
            fileInput: 'qrCodeFileInput',
            hiddenName: 'existing_qr_code',
            container: 'qrCodePreview',
            btnId: 'btnDelete_qr_code',
            floatId: 'floatDelete_qr_code',
            placeholder: `
                <div class="upload-placeholder">
                    <i class="fas fa-qrcode"></i>
                    <span>ເລືອກຮູບ QR Code<br><small>ສຳລັບໃຫ້ລູກຄ້າສະແກນຈ່າຍ</small></span>
                </div>
            `
        },
        cfd_media: {
            fileInput: 'cfdMediaFileInput',
            hiddenName: 'existing_cfd_media',
            container: 'cfdMediaPreview',
            btnId: 'btnDelete_cfd_media',
            floatId: 'floatDelete_cfd_media',
            placeholder: `
                <div class="upload-placeholder">
                    <i class="fas fa-film"></i>
                    <span>ເລືອກຮູບ ຫຼື ວິດີໂອ MP4<br><small>ສຳລັບສະແດງໃນຈໍລູກຄ້າ</small></span>
                </div>
            `
        }
    };
    
    const item = config[type];
    if (!item) return;
    
    const fileEl = document.getElementById(item.fileInput);
    const hiddenEl = document.querySelector(`input[name="${item.hiddenName}"]`);
    const containerEl = document.getElementById(item.container);
    const btnEl = document.getElementById(item.btnId);
    const floatEl = document.getElementById(item.floatId);
    
    if (fileEl) fileEl.value = '';
    if (hiddenEl) hiddenEl.value = '';
    if (containerEl) containerEl.innerHTML = item.placeholder;
    if (btnEl) btnEl.style.display = 'none';
    if (floatEl) floatEl.style.display = 'none';
}

function printSampleReceipt() {
    // Basic print preview trigger using current values
    const size = document.querySelector('input[name="receipt_size"]:checked').value;
    const shopName = encodeURIComponent(document.getElementById('inputShopName').value);
    const shopNameEn = encodeURIComponent(document.getElementById('inputShopNameEn').value);
    const address = encodeURIComponent(document.getElementById('inputAddress').value);
    const phone = encodeURIComponent(document.getElementById('inputPhone').value);
    const openTime = document.getElementById('inputOpenTime').value;
    const closeTime = document.getElementById('inputCloseTime').value;
    const showInReceipt = document.getElementById('inputShowInReceipt').checked ? 1 : 0;
    const taxRate = document.getElementById('inputTaxRate').value;
    const taxType = document.querySelector('input[name="tax_type"]:checked').value;

    const url = `api/receipt.php?sample=1&size=${size}&shop_name=${shopName}&shop_name_en=${shopNameEn}&address=${address}&phone=${phone}&open_time=${openTime}&close_time=${closeTime}&show_in_receipt=${showInReceipt}&tax_rate=${taxRate}&tax_type=${taxType}&preview=1`;
    
    if (typeof printReceipt === 'function') {
        printReceipt(null, { url: url });
    } else {
        const printWindow = window.open(url, 'receipt_sample', 'width=400,height=600');
        if (printWindow) {
            printWindow.focus();
        }
    }
}

function previewCFDMedia(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        const previewId = 'cfdMediaPreview';
        reader.onload = function(e) {
            const container = document.getElementById(previewId);
            if (file.type.startsWith('video/')) {
                container.innerHTML = `<video src="${e.target.result}" style="max-width:100%;max-height:100px;" muted autoplay loop></video>`;
            } else {
                container.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            }
        };
        reader.readAsDataURL(file);
    }
}

function toggleDynamicQrFields() {
    const chk = document.getElementById('inputEnableDynamicQr');
    const container = document.getElementById('dynamicQrFieldsContainer');
    if (chk && container) {
        container.style.display = chk.checked ? 'block' : 'none';
    }
}

async function validateStaticQrPayload() {
    const payload = document.getElementById('inputQrPayload').value.trim();
    const fb = document.getElementById('qrPayloadFeedback');
    if (!payload) {
        if (fb) {
            fb.style.display = 'block';
            fb.style.background = '#fff0b3';
            fb.style.color = '#7a4e00';
            fb.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ກະລຸນາປ້ອນ ຫຼື ວາງຂໍ້ຄວາມ QR Payload ກ່ອນ';
        }
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'validate');
        formData.append('payload', payload);
        const res = await fetch('api/dynamic_qr.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (fb) {
            fb.style.display = 'block';
            if (data && data.success) {
                const crcBadge = data.valid_crc 
                    ? '<span style="color:#059669; font-weight:700;"><i class="fas fa-check-circle"></i> CRC-16 ຖືກຕ້ອງ 100%</span>' 
                    : '<span style="color:#de350b; font-weight:700;"><i class="fas fa-times-circle"></i> CRC-16 ບໍ່ກົງ</span>';
                
                fb.style.background = data.valid_crc ? '#ecfdf5' : '#fef2f2';
                fb.style.color = data.valid_crc ? '#065f46' : '#991b1b';
                fb.style.border = data.valid_crc ? '1px solid #a7f3d0' : '1px solid #fecaca';
                fb.innerHTML = `
                    <div style="font-weight:700; margin-bottom:2px;">
                        ${crcBadge} | ທະນາຄານ: <b>${data.detected_bank}</b>
                    </div>
                    <div>ຊື່ບັນຊີ/ຮ້ານ: <b>${data.merchant_name}</b> | ສະກຸນເງິນ: <b>${data.currency}</b> | ຮູບແບບ: <b>${data.qr_type}</b></div>
                `;
            } else {
                fb.style.background = '#fef2f2';
                fb.style.color = '#991b1b';
                fb.innerHTML = `<i class="fas fa-times-circle"></i> ${data?.message || 'ບໍ່ສາມາດອ່ານໂຄງສ້າງ QR ໄດ້'}`;
            }
        }
    } catch (e) {
        console.error('Validation error:', e);
    }
}

async function triggerTestQrGeneration() {
    const amt = parseFloat(document.getElementById('testQrAmountInput')?.value || 10000);
    const container = document.getElementById('testQrContainer');
    const detailsBank = document.getElementById('testQrBank');
    const detailsAmt = document.getElementById('testQrAmount');
    const detailsAcc = document.getElementById('testQrAccount');
    const detailsName = document.getElementById('testQrName');
    const payloadText = document.getElementById('testQrPayloadText');

    if (container) container.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:24px; color:#0052cc;"></i>';

    try {
        const customPayload = document.getElementById('inputQrPayload')?.value.trim();
        let url = `api/dynamic_qr.php?amount=${amt}&currency=LAK&size=140&bill_no=TEST-001`;
        let options = {};
        if (customPayload) {
            const fd = new FormData();
            fd.append('amount', amt);
            fd.append('currency', 'LAK');
            fd.append('size', 140);
            fd.append('bill_no', 'TEST-001');
            fd.append('custom_payload', customPayload);
            options = { method: 'POST', body: fd };
            url = 'api/dynamic_qr.php';
        }

        const res = await fetch(url, options);
        const data = await res.json();

        if (data && data.success && data.svg) {
            if (container) container.innerHTML = data.svg;
            if (detailsBank) detailsBank.textContent = data.bank_name || 'BCEL One';
            if (detailsAmt) detailsAmt.textContent = Number(amt).toLocaleString() + ' ₭';
            if (detailsAcc) detailsAcc.textContent = data.account_number || '-';
            if (detailsName) detailsName.textContent = data.account_name || '-';
            if (payloadText) payloadText.textContent = data.payload;
        } else {
            if (container) container.innerHTML = '<span style="color:var(--danger);font-size:12px;">ເກີດຂໍ້ຜິດພາດ</span>';
        }
    } catch (e) {
        console.error('Test QR error:', e);
        if (container) container.innerHTML = '<span style="color:var(--danger);font-size:12px;">ໂຫຼດບໍ່ສຳເລັດ</span>';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('inputEnableDynamicQr')?.checked) {
        triggerTestQrGeneration();
    }
});

async function testCashDrawer() {
    if (typeof triggerCashDrawerKick === 'function') {
        const ok = await triggerCashDrawerKick({ silent: false });
        if (!ok) {
            showToast('ບໍ່ສາມາດສົ່ງຄຳສັ່ງເປີດລິ້ນຊັກເງິນໄດ້', 'warning');
        }
    } else {
        showToast('ສັ່ງເປີດລິ້ນຊັກເງິນ...', 'info');
        window.open('api/drawer.php?test=1', '_blank', 'width=200,height=200');
    }
}
</script>

<?php if (!empty($message)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showToast(<?= json_encode($message) ?>, <?= json_encode($messageType) ?>);
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
