<?php
/**
 * POSPharma - ສູນຈັດການ ແລະ ອອກແບບພິມບາໂຄດ (Barcode Center)
 * ຮອງຮັບມາດຕະຖານ: CODE 128, EAN-13, EAN-8, UPC-A, QR Code, Multi-Barcode, Label Designer & Thermal Printing
 */

require_once 'includes/functions.php';
requireLogin();
if (!hasRole(['admin', 'manager', 'pharmacist']) && !hasPermission('can_manage_products')) {
    header('Location: ' . BASE_URL . 'pos.php');
    exit;
}

$pageTitle = 'ບາໂຄດ & ສະຕິກເກີ';
$db = getDB();

// Fetch categories
$categories = [];
try {
    $categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch purchase orders for "Print by PO"
$purchaseOrders = [];
try {
    $purchaseOrders = $db->query("SELECT id, po_number, order_date, status FROM purchase_orders ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch initial templates
$templates = [];
try {
    $templates = $db->query("SELECT * FROM barcode_templates ORDER BY is_default DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch units
$units = [];
try {
    $units = $db->query("SELECT name FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Fetch shop settings
$shopSettings = getShopSettings();
$shopName = $shopSettings['shop_name'] ?? 'ຮ້ານຂາຍຢາ POSPharma';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="content-area">
        <div class="page-header" style="flex-wrap:wrap;gap:12px;margin-bottom:20px;">
        <div>
            <h1 class="page-title" style="display:flex;align-items:center;gap:10px;font-size:1.45rem;margin:0;color:var(--text-dark);">
                <i class="fas fa-barcode" style="color:var(--primary);"></i>
                ສູນຈັດການ ແລະ ອອກແບບພິມບາໂຄດ
            </h1>
            <p style="margin:4px 0 0 0;font-size:0.88rem;color:var(--text-medium);">
                ມາດຕະຖານ CODE 128, EAN-13, EAN-8, UPC-A, QR Code • ຫຼາຍບາໂຄດຕໍ່ສິນຄ້າ • ອອກແບບສະຕິກເກີຕາມໃຈ
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="btn btn-outline btn-sm" onclick="batchAssignMissingBarcodes()">
                <i class="fas fa-magic"></i> ສ້າງບາໂຄດໃຫ້ສິນຄ້າທີ່ຍັງບໍ່ມີ
            </button>
            <button type="button" class="btn btn-primary btn-sm" onclick="switchTab('designer')">
                <i class="fas fa-palette"></i> ອອກແບບ Template ໃໝ່
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="barcode-nav-tabs">
        <button type="button" class="tab-btn active" id="tabBtnPrint" onclick="switchTab('print')">
            <i class="fas fa-print"></i> 1. ສູນພິມສະຕິກເກີ (Print Hub)
        </button>
        <button type="button" class="tab-btn" id="tabBtnDesigner" onclick="switchTab('designer')">
            <i class="fas fa-sliders-h"></i> 2. ອອກແບບຮູບແບບສະຕິກເກີ (Label Designer)
        </button>
        <button type="button" class="tab-btn" id="tabBtnPrinter" onclick="switchTab('printer')">
            <i class="fas fa-cog"></i> 3. ຕັ້ງຄ່າເຄື່ອງພິມ (Printer Integration)
        </button>
    </div>

    <!-- =================================================== -->
    <!-- TAB 1: PRINT HUB -->
    <!-- =================================================== -->
    <div id="tabContentPrint" class="tab-content-panel">
        <div class="barcode-hub-grid">
            
            <!-- Left: Product Selection -->
            <div class="card" style="padding:16px;">
                <!-- Filter Bar -->
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px;">
                    <div style="flex:1;min-width:180px;">
                        <input type="text" id="filterSearch" class="form-control" placeholder="ຄົ້ນຫາຊື່, SKU ຫຼື ບາໂຄດ..." oninput="filterProductList()">
                    </div>
                    <div style="width:160px;">
                        <select id="filterCategory" class="form-control" onchange="filterProductList()">
                            <option value="">ທຸກໝວດໝູ່</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="width:200px;">
                        <select id="filterSource" class="form-control" onchange="handleSourceChange(this.value)">
                            <option value="all">ສິນຄ້າທັງໝົດ</option>
                            <option value="in_stock">ສະເພາະທີ່ມີສະຕັອກ</option>
                            <option value="no_barcode">ສະເພາະທີ່ຍັງບໍ່ມີບາໂຄດ</option>
                            <option value="po">ດຶງຈາກໃບຮັບສິນຄ້າ (PO)</option>
                        </select>
                    </div>
                </div>

                <!-- PO Picker (Hidden by default) -->
                <div id="poPickerRow" style="display:none;background:#f1f8e9;padding:10px 14px;border-radius:8px;margin-bottom:14px;align-items:center;gap:10px;">
                    <label style="font-weight:600;font-size:0.85rem;white-space:nowrap;margin:0;">ເລືອກໃບສັ່ງຊື້:</label>
                    <select id="selectedPoId" class="form-control" style="max-width:300px;" onchange="loadPoItems(this.value)">
                        <option value="">-- ເລືອກໃບສັ່ງຊື້ / ໃບຮັບສິນຄ້າ --</option>
                        <?php foreach ($purchaseOrders as $po): ?>
                            <option value="<?= $po['id'] ?>"><?= sanitize($po['po_number']) ?> (<?= $po['order_date'] ?> - <?= $po['status'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Action helpers -->
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;font-size:0.85rem;">
                    <div>
                        <label style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-weight:600;">
                            <input type="checkbox" id="selectAllChk" onchange="toggleSelectAll(this.checked)"> ເລືອກທັງໝົດ
                        </label>
                        <span id="selectedCountBadge" style="margin-left:10px;color:var(--primary);font-weight:700;">ເລືອກແລ້ວ: 0 ລາຍການ</span>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="btn btn-outline btn-xs" onclick="setAllQuantities(1)">ຕັ້ງຈຳນວນ = 1</button>
                        <button type="button" class="btn btn-outline btn-xs" onclick="setAllQuantities('stock')">ຕັ້ງຈຳນວນ = ສະຕັອກ</button>
                        <button type="button" class="btn btn-outline btn-xs" onclick="clearAllSelections()">ລ້າງການເລືອກ</button>
                    </div>
                </div>

                <!-- Product Table -->
                <div style="overflow-x:auto;max-height:550px;">
                    <table class="table" id="productPrintTable" style="width:100%;font-size:0.88rem;">
                        <thead style="position:sticky;top:0;background:#fff;z-index:2;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                            <tr>
                                <th style="width:36px;text-align:center;"></th>
                                <th>ສິນຄ້າ</th>
                                <th>ບາໂຄດຫຼັກ</th>
                                <th>ປະເພດ</th>
                                <th>ລາຄາຂາຍ</th>
                                <th>ສະຕັອກ</th>
                                <th style="width:120px;text-align:center;">ຈຳນວນດວງ</th>
                                <th style="width:90px;text-align:center;">ຫຼາຍບາໂຄດ</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody">
                            <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light);"><i class="fas fa-spinner fa-spin"></i> ກຳລັງໂຫຼດຂໍ້ມູນ...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right: Print Options & Live Preview -->
            <div style="display:flex;flex-direction:column;gap:16px;">
                <!-- Template Selection Card -->
                <div class="card" style="padding:16px;">
                    <h3 style="font-size:1rem;margin:0 0 12px 0;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-sliders-h" style="color:var(--primary);"></i> ຮູບແບບສະຕິກເກີ (Template)
                    </h3>
                    
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label">ເລືອກ Template</label>
                        <select id="printTemplateSelect" class="form-control" onchange="onPrintTemplateChanged()">
                            <?php foreach ($templates as $tpl): ?>
                                <option value="<?= $tpl['id'] ?>" <?= $tpl['is_default'] ? 'selected' : '' ?> data-width="<?= $tpl['width_mm'] ?>" data-height="<?= $tpl['height_mm'] ?>" data-cols="<?= $tpl['columns'] ?>" data-ori="<?= $tpl['orientation'] ?>">
                                    <?= sanitize($tpl['name']) ?> (<?= $tpl['width_mm'] ?>x<?= $tpl['height_mm'] ?>mm)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display:flex;gap:10px;margin-bottom:12px;">
                        <div style="flex:1;min-width:0;">
                            <label class="form-label" style="font-size:0.82rem;margin-bottom:4px;white-space:nowrap;">ທິດທາງ</label>
                            <select id="printOrientationSelect" class="form-control" style="font-size:0.82rem;padding:6px 8px;" onchange="updateLivePrintPreview()">
                                <option value="portrait" selected>ແນວຕັ້ງ (Portrait)</option>
                                <option value="landscape">ແນວນອນ (Landscape)</option>
                            </select>
                        </div>
                        <div style="width:85px;min-width:0;flex-shrink:0;">
                            <label class="form-label" style="font-size:0.82rem;margin-bottom:4px;white-space:nowrap;">ຈຳນວນຊຸດ</label>
                            <input type="number" id="printCopiesInput" class="form-control" style="text-align:center;font-size:0.85rem;padding:6px 8px;" value="1" min="1" max="100">
                        </div>
                    </div>

                    <!-- Live Sticker Preview Container -->
                    <div style="margin-top:14px;border:1px dashed #bdbdbd;background:#fafafa;padding:10px;border-radius:8px;text-align:center;max-width:100%;box-sizing:border-box;">
                        <div style="font-size:0.75rem;color:var(--text-light);font-weight:600;margin-bottom:8px;text-transform:uppercase;">
                            <i class="fas fa-eye"></i> ຕົວຢ່າງສະຕິກເກີ 1 ດວງ (Live Preview)
                        </div>
                        <div id="liveStickerPreviewBox" style="display:inline-flex;justify-content:center;align-items:center;background:#fff;border:1px solid #ccc;border-radius:4px;box-shadow:0 3px 10px rgba(0,0,0,0.06);margin:0 auto;overflow:hidden;padding:4px;max-width:100%;box-sizing:border-box;">
                            <div id="liveStickerContent" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;text-align:center;max-width:100%;box-sizing:border-box;">
                                <div style="font-weight:700;font-size:8pt;color:#000;">ຕົວຢ່າງຊື່ຢາ</div>
                                <svg id="previewBarcodeSvg" style="max-width:95%;height:32px;"></svg>
                                <div style="font-weight:800;font-size:8.5pt;color:#000;">₭ 20,000</div>
                            </div>
                        </div>
                    </div>

                    <!-- Print Action Buttons -->
                    <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
                        <button type="button" class="btn btn-primary" style="padding:10px 12px;font-size:0.9rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;width:100%;white-space:normal;text-align:center;" onclick="executeStickerPrint()">
                            <i class="fas fa-print"></i> <span>ພິມສະຕິກເກີ (<span id="totalPrintStickersCount">0</span> ດວງ)</span>
                        </button>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <button type="button" class="btn btn-outline" style="padding:8px 4px;font-size:0.8rem;display:flex;align-items:center;justify-content:center;gap:6px;min-width:0;overflow:hidden;white-space:nowrap;" onclick="exportToPdf()" title="ສົ່ງອອກ PDF">
                                <i class="fas fa-file-pdf" style="color:#d32f2f;"></i> <span>ສົ່ງອອກ PDF</span>
                            </button>
                            <button type="button" class="btn btn-outline" style="padding:8px 4px;font-size:0.8rem;display:flex;align-items:center;justify-content:center;gap:6px;min-width:0;overflow:hidden;white-space:nowrap;" onclick="downloadTsplCommands()" title="ຄຳສັ່ງ TSPL">
                                <i class="fas fa-terminal" style="color:#1976d2;"></i> <span>ຄຳສັ່ງ TSPL</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- =================================================== -->
    <!-- TAB 2: LABEL DESIGNER -->
    <!-- =================================================== -->
    <div id="tabContentDesigner" class="tab-content-panel" style="display:none;">
        <div class="barcode-designer-grid">
            
            <!-- Controls Form -->
            <div class="card" style="padding:20px;">
                <h3 style="font-size:1.1rem;margin:0 0 16px 0;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-magic" style="color:var(--primary);"></i> ຕັ້ງຄ່າຮູບແບບສະຕິກເກີ
                </h3>

                <input type="hidden" id="designerTemplateId" value="">

                <div class="form-group">
                    <label class="form-label">ຊື່ Template</label>
                    <input type="text" id="tplName" class="form-control" placeholder="ເຊັ່ນ: ສະຕິກເກີຂະໜາດ 40x30mm ມາດຕະຖານ">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">ຄວາມກວ້າງ (mm)</label>
                        <input type="number" id="tplWidth" class="form-control" value="40" min="15" max="210" step="1" oninput="onDesignerDimensionChanged()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ລວງສູງ (mm)</label>
                        <input type="number" id="tplHeight" class="form-control" value="30" min="10" max="297" step="1" oninput="onDesignerDimensionChanged()">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">ຈຳນວນຖັນຕໍ່ແຖວ</label>
                        <select id="tplColumns" class="form-control" onchange="renderDesignerPreview()">
                            <option value="1">1 ຖັນ (1 ແຖວ - ມ້ວນສະຕິກເກີ)</option>
                            <option value="2">2 ຖັນ (2 ແຖວ)</option>
                            <option value="3">3 ຖັນ (3 ແຖວ)</option>
                            <option value="4">4 ຖັນ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ທິດທາງ</label>
                        <select id="tplOrientation" class="form-control" onchange="renderDesignerPreview()">
                            <option value="portrait" selected>ແນວຕັ້ງ (Portrait)</option>
                            <option value="landscape">ແນວນອນ (Landscape)</option>
                        </select>
                    </div>
                </div>

                <!-- Toggles for Fields -->
                <div style="margin:16px 0;background:#f8f9fa;padding:12px;border-radius:8px;">
                    <div style="font-weight:700;font-size:0.85rem;margin-bottom:8px;color:var(--text-dark);">
                        ຂໍ້ມູນທີ່ຕ້ອງການສະແດງໃນສະຕິກເກີ:
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:0.85rem;">
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" id="showNameChk" checked onchange="renderDesignerPreview()"> ຊື່ສິນຄ້າ
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" id="showPriceChk" checked onchange="renderDesignerPreview()"> ລາຄາຂາຍ
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" id="showBarcodeChk" checked onchange="renderDesignerPreview()"> ຮູບ Barcode
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" id="showBarcodeTextChk" checked onchange="renderDesignerPreview()"> ຕົວເລກໃຕ້ບາໂຄດ
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" id="showShopNameChk" onchange="renderDesignerPreview()"> ຊື່ຮ້ານຄ້າ
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" id="showUnitChk" checked onchange="renderDesignerPreview()"> ໜ່ວຍສິນຄ້າ
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" id="showCodeChk" onchange="renderDesignerPreview()"> ລະຫັດສິນຄ້າ (SKU)
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" id="showLotExpiryChk" onchange="renderDesignerPreview()"> Lot & ວັນໝົດອາຍຸ
                        </label>
                    </div>
                </div>

                <!-- Typography & Sizing Sliders -->
                <div class="form-group">
                    <label class="form-label" style="display:flex;justify-content:space-between;">
                        <span>ຂະໜາດຕົວອັກສອນຊື່ສິນຄ້າ</span>
                        <span id="labelFontSizeName" style="font-weight:700;">8.5pt</span>
                    </label>
                    <input type="range" id="sliderFontSizeName" min="6" max="14" step="0.5" value="8.5" oninput="document.getElementById('labelFontSizeName').innerText = this.value + 'pt'; renderDesignerPreview();">
                </div>

                <div class="form-group">
                    <label class="form-label" style="display:flex;justify-content:space-between;">
                        <span>ຂະໜາດຕົວອັກສອນລາຄາ</span>
                        <span id="labelFontSizePrice" style="font-weight:700;">9pt</span>
                    </label>
                    <input type="range" id="sliderFontSizePrice" min="6" max="16" step="0.5" value="9" oninput="document.getElementById('labelFontSizePrice').innerText = this.value + 'pt'; renderDesignerPreview();">
                </div>

                <div class="form-group">
                    <label class="form-label" style="display:flex;justify-content:space-between;">
                        <span>ຄວາມສູງຂອງ Barcode</span>
                        <span id="labelBarcodeHeight" style="font-weight:700;">32px</span>
                    </label>
                    <input type="range" id="sliderBarcodeHeight" min="18" max="70" step="2" value="32" oninput="document.getElementById('labelBarcodeHeight').innerText = this.value + 'px'; renderDesignerPreview();">
                </div>

                <div class="form-group" style="margin-top:12px;">
                    <label style="cursor:pointer;display:flex;align-items:center;gap:6px;font-weight:600;">
                        <input type="checkbox" id="tplIsDefault"> ຕັ້ງເປັນ Template ຫຼັກ (Default)
                    </label>
                </div>

                <!-- Save Buttons -->
                <div style="display:flex;gap:8px;margin-top:18px;">
                    <button type="button" class="btn btn-primary" style="flex:1;" onclick="saveTemplateForm()">
                        <i class="fas fa-save"></i> ບັນທຶກ Template
                    </button>
                    <button type="button" class="btn btn-outline" onclick="resetDesignerForm()">
                        <i class="fas fa-undo"></i> ລ້າງຄ່າ
                    </button>
                </div>
            </div>

            <!-- Right: Interactive Live Visual Preview & Template List -->
            <div style="display:flex;flex-direction:column;gap:20px;">
                
                <!-- Live Canvas Preview Card -->
                <div class="card" style="padding:24px;text-align:center;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                        <h4 style="margin:0;font-size:1rem;color:var(--text-dark);"><i class="fas fa-desktop" style="color:var(--primary);"></i> ພາບສະແດງຜົນຕົວຈິງ (Scaled 100%)</h4>
                        <span id="previewDimensionsBadge" style="background:#e8f5e9;color:var(--primary);font-weight:700;padding:4px 10px;border-radius:12px;font-size:0.8rem;">40 x 30 mm</span>
                    </div>

                    <div style="background:#f0f2f5;padding:30px;border-radius:12px;display:flex;justify-content:center;align-items:center;min-height:260px;">
                        <div id="designerCanvas" style="background:#fff;border:1px solid #c8d1d8;border-radius:4px;box-shadow:0 8px 24px rgba(0,0,0,0.08);box-sizing:border-box;display:flex;flex-direction:column;justify-content:center;align-items:center;transition:all 0.2s ease;">
                            <!-- Dynamic Content injected via JS -->
                        </div>
                    </div>
                </div>

                <!-- Saved Templates Table -->
                <div class="card" style="padding:16px;">
                    <h4 style="margin:0 0 12px 0;font-size:0.95rem;">ລາຍການ Template ທັງໝົດໃນລະບົບ</h4>
                    <div style="overflow-x:auto;">
                        <table class="table" style="width:100%;font-size:0.85rem;">
                            <thead>
                                <tr>
                                    <th>ຊື່ Template</th>
                                    <th>ຂະໜາດ</th>
                                    <th>ຖັນ</th>
                                    <th>Default</th>
                                    <th style="width:120px;text-align:right;">ຈັດການ</th>
                                </tr>
                            </thead>
                            <tbody id="savedTemplatesTbody">
                                <!-- Injected via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- =================================================== -->
    <!-- TAB 3: PRINTER INTEGRATION -->
    <!-- =================================================== -->
    <div id="tabContentPrinter" class="tab-content-panel" style="display:none;">
        <div class="barcode-printer-grid">
            
            <div class="card" style="padding:20px;">
                <h3 style="font-size:1.1rem;margin:0 0 16px 0;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-print" style="color:var(--primary);"></i> ການຕັ້ງຄ່າເຄື່ອງພິມ (Printer Settings)
                </h3>

                <div class="form-group">
                    <label class="form-label">ເຄື່ອງພິມສະຕິກເກີ (Printer Device)</label>
                    <select id="cfgPrinterName" class="form-control" onchange="localStorage.setItem('pos_barcode_printer', this.value)">
                        <option value="">-- ເລືອກເຄື່ອງພິມ --</option>
                        <option value="Grozziie TP878 (USB)">Grozziie TP878 (USB) - ມາດຕະຖານ</option>
                        <option value="Grozziie TP878 (Bluetooth)">Grozziie TP878 (Bluetooth)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">ປະເພດເຄື່ອງພິມ (Printer Category)</label>
                    <select id="cfgPrinterType" class="form-control">
                        <option value="thermal_label" selected>Thermal Label Printer (Grozziie / TSC / Xprinter / Gprinter)</option>
                        <option value="desktop_label">Desktop Label Printer</option>
                        <option value="thermal_receipt">Thermal Receipt Printer (58mm / 80mm)</option>
                        <option value="a4_laser">A4 Laser / Inkjet Printer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">ພາສາຄວບຄຸມ (Command Language)</label>
                    <select id="cfgCommandLang" class="form-control">
                        <option value="driver_gdi" selected>Windows Driver / Web Print (ມາດຕະຖານ Browser & Desktop App)</option>
                        <option value="tspl">TSPL (TSC / Grozziie / Xprinter / Gprinter Raw)</option>
                        <option value="zpl">ZPL (Zebra Page Language)</option>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">ຄວາມເຂັ້ມການພິມ (Density: 1-15)</label>
                        <input type="number" id="cfgDensity" class="form-control" value="8" min="1" max="15">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ຄວາມໄວການພິມ (Speed: 2-6 inch/s)</label>
                        <input type="number" id="cfgSpeed" class="form-control" value="4" min="2" max="6">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Offset ແກນ X (mm)</label>
                        <input type="number" id="cfgOffsetX" class="form-control" value="0" step="0.5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Offset ແກນ Y (mm)</label>
                        <input type="number" id="cfgOffsetY" class="form-control" value="0" step="0.5">
                    </div>
                </div>

                <div style="margin-top:18px;display:flex;gap:10px;">
                    <button type="button" class="btn btn-primary" onclick="savePrinterSettings()">
                        <i class="fas fa-save"></i> ບັນທຶກການຕັ້ງຄ່າ
                    </button>
                    <button type="button" class="btn btn-outline" onclick="executeTestPrint()">
                        <i class="fas fa-vial"></i> ທົດສອບພິມ (Test Print)
                    </button>
                </div>
            </div>

            <!-- Calibration Guide -->
            <div class="card" style="padding:20px;background:#fcfcfc;">
                <h3 style="font-size:1.05rem;margin:0 0 14px 0;color:var(--text-dark);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-book-open" style="color:var(--primary);"></i> ຄູ່ມືການ Calibrate ເຊັນເຊີດວງສະຕິກເກີ
                </h3>

                <div style="font-size:0.88rem;color:var(--text-medium);line-height:1.7;">
                    <p style="margin-bottom:10px;">
                        <strong>ເປັນຫຍັງຕ້ອງ Calibrate?</strong> ເມື່ອປ່ຽນມ້ວນສະຕິກເກີຂະໜາດໃໝ່ (ເຊັ່ນ ປ່ຽນຈາກ 40x30mm ມາເປັນ 50x30mm), ເຄື່ອງພິມອາດຈະບໍ່ຮູ້ຈັກຕຳແໜ່ງຮອຍຕໍ່ລະຫວ່າງດວງສະຕິກເກີ (Label Gap) ເຮັດໃຫ້ພິມຂ້າມດວງ ຫຼື ເຈ້ຍໄຫຼບໍ່ຢຸດ.
                    </p>

                    <div style="background:#e8f5e9;border-left:4px solid var(--primary);padding:10px 14px;border-radius:4px;margin-bottom:14px;">
                        <strong style="color:var(--primary);">ຂັ້ນຕອນການ Calibrate ເຄື່ອງພິມ Grozziie TP878:</strong>
                        <ol style="margin:6px 0 0 18px;padding:0;">
                            <li>ປິດສະວິດເຄື່ອງພິມ (Power OFF).</li>
                            <li>ກົດປຸ່ມ <strong>FEED</strong> (ປຸ່ມກົດເລື່ອນເຈ້ຍ) ຄ້າງໄວ້ <strong>ຢ່າຟ້າວປ່ອຍ</strong>.</li>
                            <li>ເປີດສະວິດເຄື່ອງພິມ (Power ON) ໃນຂະນະທີ່ຍັງກົດປຸ່ມ FEED ຄ້າງໄວ້ຢູ່.</li>
                            <li>ລໍຖ້າຈົນກວ່າເຄື່ອງພິມຈະດັງ <strong>"ບິບ... ບິບ..."</strong> (ປະມານ 3-5 ວິນາທີ) ແລ້ວຈຶ່ງປ່ອຍມື.</li>
                            <li>ເຄື່ອງພິມຈະດຶງເຈ້ຍເລື່ອນອອກມາ 2-3 ດວງ ເພື່ອວັດແທກຂະໜາດດວງສະຕິກເກີໂດຍອັດຕະໂນມັດ.</li>
                        </ol>
                    </div>

                    <div style="background:#fff3e0;border-left:4px solid #ff9800;padding:10px 14px;border-radius:4px;">
                        <strong style="color:#e65100;">ຂໍ້ຄວນລະວັງສຳຄັນ:</strong>
                        <p style="margin:4px 0 0 0;font-size:0.83rem;">
                            ຫ້າມສົມມຸດວ່າ Thermal Receipt Printer (ເຄື່ອງພິມໃບບິນ) ແລະ Thermal Label Printer (ເຄື່ອງພິມສະຕິກເກີ) ສາມາດໃຊ້ແທນກັນໄດ້. ເຄື່ອງພິມສະຕິກເກີຕ້ອງມີ <strong>Gap / Mark Sensor</strong> ເພື່ອກວດຈັບໄລຍະຫ່າງຂອງແຕ່ລະດວງ.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
    </div>
</main>

<!-- =================================================== -->
<!-- MULTI-BARCODE MODAL (Standard Shared Component) -->
<!-- =================================================== -->
<?php include 'includes/multi_barcode_modal.php'; ?>

<!-- Extra Scripts -->
<script src="assets/vendor/JsBarcode.all.min.js"></script>
<script src="assets/js/barcode_manager.js?v=<?= time() ?>"></script>

<?php include 'includes/footer.php'; ?>

