<?php
/**
 * ຈັດການສິນຄ້າ - Product Management
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();
if (!hasRole(['admin', 'manager', 'pharmacist']) && !hasPermission('can_manage_products')) {
    header('Location: ' . BASE_URL . 'pos.php');
    exit;
}

$pageTitle = 'ຈັດການສິນຄ້າ';

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

try {
    $db = getDB();
    $categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
    
    // Check if units table exists and fetch
    $units = [];
    try {
        $units = $db->query("SELECT * FROM units ORDER BY name")->fetchAll();
    } catch (Exception $e) {
        // units table might not exist yet if there's an issue
    }
    
    $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($categoryFilter > 0) {
        $sql .= " AND p.category_id = ?";
        $params[] = $categoryFilter;
    }
    
    $sql .= " ORDER BY p.name";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
    $products = [];
    $units = [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">ຈັດການສິນຄ້າ</h1>
    </div>

    <div class="content-area">
        <div class="toolbar">
            <div class="toolbar-left" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <select class="form-control" style="width:auto;min-width:160px;" onchange="window.location.href='products.php?category='+this.value">
                    <option value="0">ທຸກໝວດໝູ່</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-outline" id="btnBatchPrintBarcode" onclick="openBatchPrintBarcode()" style="display:none;background:#e8f5e9;color:var(--primary);border-color:var(--primary);font-weight:600;">
                    <i class="fas fa-barcode"></i> ພິມບາໂຄດທີ່ເລືອກ (<span id="batchCountBadge">0</span>)
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>barcode_manager.php" class="btn btn-outline" style="border-color:var(--border-color);color:var(--text-dark);background:#ffffff;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;" title="ສູນອອກແບບ ແລະ ພິມບາໂຄດ">
                    <i class="fas fa-barcode" style="color:var(--primary);"></i> ສູນບາໂຄດ
                </a>
                <button type="button" class="btn btn-outline" onclick="openImportExcelModal()" style="border-color:var(--border-color);color:var(--text-dark);background:#ffffff;font-weight:600;display:inline-flex;align-items:center;gap:6px;" title="ນຳເຂົ້າລາຍການຢາຈາກ Excel">
                    <i class="fas fa-file-import" style="color:var(--primary);"></i> ນຳເຂົ້າ Excel
                </button>
                <button type="button" class="btn btn-outline" onclick="exportProductsToExcel()" style="border-color:var(--border-color);color:var(--text-dark);background:#ffffff;font-weight:600;display:inline-flex;align-items:center;gap:6px;" title="ສົ່ງອອກລາຍການຢາເປັນ Excel">
                    <i class="fas fa-file-excel" style="color:var(--info);"></i> ສົ່ງອອກ Excel
                </button>
                <button class="btn btn-primary" onclick="openAddProductModal()">
                    <i class="fas fa-plus"></i> ເພີ່ມສິນຄ້າໃໝ່
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <div class="product-view-toggle" title="ປ່ຽນມຸມມອງ">
                    <button type="button" class="btn-view-mode active" id="btnViewTable" onclick="setProductViewMode('table')" title="ສະແດງແບບຕາຕະລາງ">
                        <i class="fas fa-table"></i>
                    </button>
                    <button type="button" class="btn-view-mode" id="btnViewCard" onclick="setProductViewMode('card')" title="ສະແດງແບບກາດ (Card List)">
                        <i class="fas fa-th-large"></i>
                    </button>
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="ຄົ້ນຫາສິນຄ້າ..." value="<?= $search ?>" oninput="filterProductsCombined('searchProd')" id="searchProd">
                </div>
            </div>
        </div>

        <?php if (empty($products)): ?>
        <div class="empty-state" style="padding:80px 20px;">
            <i class="fas fa-box-open"></i>
            <?php if (!empty($search) || !empty($selectedCategory)): ?>
                <p>ບໍ່ພົບສິນຄ້າທີ່ກົງກັບເງື່ອນໄຂການຄົ້ນຫາ</p>
                <div style="display:flex;gap:10px;justify-content:center;margin-top:12px;flex-wrap:wrap;">
                    <a href="products.php" class="btn btn-outline"><i class="fas fa-undo"></i> ລ້າງຕົວກອງ ແລະ ຄຳຄົ້ນຫາ</a>
                </div>
            <?php else: ?>
                <p>ບໍ່ມີສິນຄ້າ</p>
                <div style="display:flex;gap:10px;justify-content:center;margin-top:12px;flex-wrap:wrap;">
                    <button class="btn btn-primary" onclick="openAddProductModal()"><i class="fas fa-plus"></i> ເພີ່ມສິນຄ້າໃໝ່</button>
                    <button class="btn btn-outline" onclick="openImportExcelModal()" style="border-color:#10b981;color:#059669;background:#f0fdf4;font-weight:600;"><i class="fas fa-file-import"></i> ນຳເຂົ້າຈາກ Excel</button>
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- 1. Table View Container -->
        <div class="card" id="productTableViewContainer">
            <div class="table-responsive">
                <table class="table" id="productTable">
                    <thead>
                        <tr>
                            <th style="width:38px;text-align:center;">
                                <input type="checkbox" id="selectAllProducts" onchange="toggleSelectAllProducts(this.checked)" title="ເລືອກທັງໝົດ" style="cursor:pointer;width:16px;height:16px;accent-color:var(--primary);">
                            </th>
                            <th>#</th>
                            <th style="width:55px;text-align:center;">ຮູບພາບ</th>
                            <th>ບາໂຄດ</th>
                            <th>ຊື່ສິນຄ້າ</th>
                            <th>ໝວດໝູ່</th>
                            <th>ລາຄາທຶນ</th>
                            <th>ລາຄາຂາຍ</th>
                            <th>ສະຕ໋ອກ</th>
                            <th>ໜ່ວຍ</th>
                            <th>ວັນໝົດອາຍຸ</th>
                            <th>ຈັດການ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $i => $p): ?>
                        <tr>
                            <td style="text-align:center;">
                                <?php if (!empty($p['barcode'])): ?>
                                <input type="checkbox" class="product-chk" 
                                    data-id="<?= $p['id'] ?>" 
                                    data-barcode="<?= htmlspecialchars($p['barcode']) ?>" 
                                    data-name="<?= htmlspecialchars($p['name']) ?>" 
                                    data-price="<?= formatCurrency($p['selling_price']) ?>"
                                    onchange="updateBatchPrintButton()"
                                    style="cursor:pointer;width:18px;height:18px;accent-color:var(--primary);">
                                <?php else: ?>
                                <span style="color:var(--text-light);">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $i + 1 ?></td>
                            <td style="text-align:center;">
                                <?php if (!empty($p['image']) && file_exists('assets/images/products/' . $p['image'])): ?>
                                    <img src="assets/images/products/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="table-prod-img" onclick="previewImageLightbox('assets/images/products/<?= htmlspecialchars($p['image']) ?>', '<?= htmlspecialchars(addslashes($p['name'])) ?>')" title="ຄລິກເພື່ອເບິ່ງຮູບ">
                                <?php else: ?>
                                    <div class="table-prod-placeholder" title="ບໍ່ມີຮູບ"><i class="fas fa-pills"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['barcode'])): ?>
                                    <span style="font-family:monospace;font-size:0.85rem;"><?= $p['barcode'] ?></span>
                                <?php else: ?>
                                    <button class="btn btn-sm" style="padding:4px 10px;font-size:0.8rem;min-height:36px;background:var(--primary-bg);color:var(--primary);border:1px solid rgba(46,125,50,0.3);border-radius:6px;cursor:pointer;" data-id="<?= (int)$p['id'] ?>" data-name="<?= sanitize($p['name']) ?>" onclick="generateBarcode(this.dataset.id, this.dataset.name)" title="ສ້າງບາໂຄດໃໝ່">
                                        <i class="fas fa-barcode"></i> ສ້າງ
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= sanitize($p['name']) ?></strong></td>
                            <td><span class="badge badge-success"><?= sanitize($p['category_name'] ?? 'ອື່ນໆ') ?></span></td>
                            <td><?= formatCurrency($p['cost_price']) ?> ກີບ</td>
                            <td class="text-primary fw-bold"><?= formatCurrency($p['selling_price']) ?> ກີບ</td>
                            <td>
                                <?php if ($p['stock_quantity'] <= $p['min_stock']): ?>
                                    <span class="badge badge-danger"><?= $p['stock_quantity'] ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?= $p['stock_quantity'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize($p['unit']) ?></td>
                            <td><?= $p['expiry_date'] ? formatDate($p['expiry_date']) : '-' ?></td>
                            <td>
                                <div style="display:flex;gap:8px;">
                                    <button class="btn-icon btn-white btn-sm" onclick="openMultiBarcodeModal(<?= (int)$p['id'] ?>, '<?= sanitize($p['name']) ?>', '<?= sanitize($p['unit']) ?>', <?= (float)$p['selling_price'] ?>)" title="ຈັດການຫຼາຍບາໂຄດ" style="color:var(--primary);">
                                        <i class="fas fa-layer-group"></i>
                                    </button>
                                    <?php if (!empty($p['barcode'])): ?>
                                    <button class="btn-icon btn-white btn-sm" data-id="<?= (int)$p['id'] ?>" data-barcode="<?= htmlspecialchars($p['barcode']) ?>" data-name="<?= sanitize($p['name']) ?>" data-price="<?= formatCurrency($p['selling_price']) ?>" onclick="openPrintBarcode(this.dataset.id, this.dataset.barcode, this.dataset.name, this.dataset.price)" title="ພິມບາໂຄດ">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn-icon btn-white btn-sm" onclick="editProduct(<?= $p['id'] ?>)" title="ແກ້ໄຂ">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon btn-white btn-sm" style="color:var(--danger);" onclick="deleteProduct(<?= $p['id'] ?>)" title="ລຶບ">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Responsive Card List View (Mobile & Touch Friendly) -->
        <div class="product-cards-grid" id="productCardView" style="display:none;">
            <?php foreach ($products as $i => $p): ?>
                <div class="product-item-card" data-barcode="<?= htmlspecialchars($p['barcode'] ?? '') ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-category="<?= htmlspecialchars($p['category_name'] ?? '') ?>">
                    <div class="product-card-top">
                        <div class="product-card-img-wrap">
                            <?php if (!empty($p['image']) && file_exists('assets/images/products/' . $p['image'])): ?>
                                <img src="assets/images/products/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-card-img" onclick="previewImageLightbox('assets/images/products/<?= htmlspecialchars($p['image']) ?>', '<?= htmlspecialchars(addslashes($p['name'])) ?>')" title="ຄລິກເພື່ອເບິ່ງຮູບ">
                            <?php else: ?>
                                <div class="product-card-placeholder" title="ບໍ່ມີຮູບ"><i class="fas fa-pills"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="product-card-header-info">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:6px; margin-bottom:4px;">
                                <span class="badge badge-success" style="font-size:11px;"><?= sanitize($p['category_name'] ?? 'ອື່ນໆ') ?></span>
                                <?php if ($p['stock_quantity'] <= $p['min_stock']): ?>
                                    <span class="badge badge-danger" style="font-size:11px;">ຍັງເຫຼືອ: <?= $p['stock_quantity'] ?> <?= sanitize($p['unit']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success" style="font-size:11px;">ຍັງເຫຼືອ: <?= $p['stock_quantity'] ?> <?= sanitize($p['unit']) ?></span>
                                <?php endif; ?>
                            </div>
                            <h4 class="product-card-title"><?= sanitize($p['name']) ?></h4>
                            <div class="product-card-barcode">
                                <i class="fas fa-barcode"></i> <?= !empty($p['barcode']) ? htmlspecialchars($p['barcode']) : '<span class="text-muted">ບໍ່ມີບາໂຄດ</span>' ?>
                            </div>
                        </div>
                    </div>

                    <div class="product-card-details">
                        <div class="product-card-price-row">
                            <div>
                                <span class="text-muted" style="font-size:11px;">ຕົ້ນທຶນ:</span>
                                <div style="font-size:12.5px;"><?= formatCurrency($p['cost_price']) ?> ₭</div>
                            </div>
                            <div style="text-align:right;">
                                <span class="text-muted" style="font-size:11px;">ລາຄາຂາຍ:</span>
                                <div class="text-primary fw-bold" style="font-size:15px;"><?= formatCurrency($p['selling_price']) ?> ₭</div>
                            </div>
                        </div>
                        <?php if ($p['expiry_date']): ?>
                        <div style="margin-top:6px; font-size:11.5px; color:#5e6c84;">
                            <i class="far fa-calendar-alt"></i> ໝົດອາຍຸ: <?= formatDate($p['expiry_date']) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="product-card-actions">
                        <button type="button" class="btn btn-outline btn-sm" onclick="openMultiBarcodeModal(<?= (int)$p['id'] ?>, '<?= sanitize($p['name']) ?>', '<?= sanitize($p['unit']) ?>', <?= (float)$p['selling_price'] ?>)" title="ຈັດການຫຼາຍບາໂຄດ">
                            <i class="fas fa-layer-group"></i> ບາໂຄດ
                        </button>
                        <?php if (!empty($p['barcode'])): ?>
                        <button type="button" class="btn btn-outline btn-sm" data-id="<?= (int)$p['id'] ?>" data-barcode="<?= htmlspecialchars($p['barcode']) ?>" data-name="<?= sanitize($p['name']) ?>" data-price="<?= formatCurrency($p['selling_price']) ?>" onclick="openPrintBarcode(this.dataset.id, this.dataset.barcode, this.dataset.name, this.dataset.price)" title="ພິມບາໂຄດ">
                            <i class="fas fa-print"></i> ພິມ
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline btn-sm" onclick="editProduct(<?= $p['id'] ?>)" title="ແກ້ໄຂ">
                            <i class="fas fa-edit"></i> ແກ້ໄຂ
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:#fed7d7;" onclick="deleteProduct(<?= $p['id'] ?>)" title="ລຶບ">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Add Product Modal -->
<div class="modal-overlay" id="addProductModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3 id="productModalTitle">ເພີ່ມສິນຄ້າໃໝ່</h3>
            <button class="modal-close" onclick="closeModal('addProductModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="productForm" onsubmit="event.preventDefault(); saveProduct();">
                <input type="hidden" id="prodId" value="">
                <input type="hidden" id="removeExistingImage" value="0">

                <!-- Image Upload Area -->
                <div class="product-image-upload-wrapper">
                    <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
                        <span><i class="fas fa-image" style="color:var(--primary);margin-right:6px;"></i>ຮູບພາບສິນຄ້າ</span>
                        <small style="color:var(--text-light);font-size:11px;">JPG, PNG, WEBP (ສູງສຸດ 5MB)</small>
                    </label>
                    <div class="image-upload-box" id="imageDropZone" tabindex="0" role="button" aria-label="ເລືອກຮູບພາບສິນຄ້າ" onclick="document.getElementById('prodImageInput').click()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();document.getElementById('prodImageInput').click();}">
                        <input type="file" id="prodImageInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="handleProductImageSelect(this)">
                        
                        <div id="imageUploadPlaceholder" class="upload-placeholder">
                            <div class="upload-icon-circle">
                                <i class="fas fa-camera"></i>
                            </div>
                            <div class="upload-text">
                                <strong>ຄລິກເພື່ອເລືອກຮູບ</strong> ຫຼື ລາກໄຟລ໌ມາໃສ່ທີ່ນີ້
                            </div>
                        </div>
                        
                        <div id="imagePreviewContainer" class="upload-preview-container" style="display:none;">
                            <img id="imagePreviewImg" src="" alt="ຕົວຢ່າງຮູບ" class="uploaded-preview-img">
                            <button type="button" class="btn-remove-image" onclick="removeProductImage(event)" title="ລຶບຮູບພາບ">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
                            <span>ບາໂຄດ (Barcode)</span>
                            <button type="button" class="btn btn-outline btn-xs" onclick="generateBarcodeForProductModal()" title="ສ້າງບາໂຄດ CODE 128 ອັດຕະໂນມັດ" style="padding:2px 8px;font-size:11px;border-radius:4px;color:var(--primary);border-color:var(--primary);">
                                <i class="fas fa-magic"></i> ສ້າງອັດຕະໂນມັດ
                            </button>
                        </label>
                        <input type="text" class="form-control" id="prodBarcode" placeholder="ສະແກນ ຫຼື ປ້ອນບາໂຄດ" oninput="validateProductBarcode(this.value)">
                        <div id="productBarcodeAlert" style="font-size:11px;margin-top:4px;display:none;"></div>
                        <div id="editMultiBarcodeBox" style="display:none;margin-top:8px;">
                            <button type="button" class="btn btn-outline btn-xs" onclick="openMultiBarcodeModalFromProductModal()" style="width:100%;display:flex;align-items:center;justify-content:center;gap:6px;padding:6px 10px;border-style:dashed;color:var(--primary);border-color:var(--primary);font-weight:600;">
                                <i class="fas fa-barcode"></i> ຈັດການຫຼາຍບາໂຄດສຳລັບສິນຄ້ານີ້ (ກ່ອງ / ແຜງ / ເມັດ)
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ໝວດໝູ່</label>
                        <div style="display:flex; gap:8px;">
                            <select class="form-control" id="prodCategory" style="flex:1;">
                                <option value="">ເລືອກໝວດໝູ່</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-primary" onclick="openAddCategoryModal()" style="padding: 0 12px;" title="ເພີ່ມໝວດໝູ່"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">ຊື່ສິນຄ້າ *</label>
                    <input type="text" class="form-control" id="prodName" required placeholder="ຊື່ຢາ / ສິນຄ້າ">
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">ລາຄາທຶນ (ກີບ) *</label>
                        <input type="number" class="form-control" id="prodCostPrice" required min="0" placeholder="0" oninput="calculateSellingPrice()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">+ ກຳໄລ (%)</label>
                        <input type="number" class="form-control" id="prodMarkup" value="20" min="0" step="1" oninput="calculateSellingPrice()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ລາຄາຂາຍ (ກີບ) *</label>
                        <input type="number" class="form-control" id="prodSellingPrice" required min="0" placeholder="0" oninput="calculateMarkup()">
                    </div>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">ຈຳນວນສະຕ໋ອກ</label>
                        <input type="number" class="form-control" id="prodStock" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ຂັ້ນຕ່ຳ</label>
                        <input type="number" class="form-control" id="prodMinStock" value="5" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ໜ່ວຍ</label>
                        <div style="display:flex; gap:8px;">
                            <select class="form-control" id="prodUnit" style="flex:1;">
                                <option value="">ເລືອກໜ່ວຍ</option>
                                <?php foreach ($units as $u): ?>
                                <option value="<?= sanitize($u['name']) ?>"><?= sanitize($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-primary" onclick="openAddUnitModal()" style="padding: 0 12px;" title="ເພີ່ມຫົວໜ່ວຍ"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">ວັນໝົດອາຍຸ</label>
                    <input type="date" class="form-control" id="prodExpiry">
                </div>
                <div class="modal-footer" style="padding: 16px 0 0 0; margin-top: 16px; border-top: 1px solid var(--border-color); display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" class="btn btn-white" onclick="closeModal('addProductModal')">ຍົກເລີກ</button>
                    <button type="submit" class="btn btn-primary" id="saveProductBtn"><i class="fas fa-save"></i> ບັນທຶກ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal-overlay" id="addCategoryModal" style="z-index: 2000;">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3>ເພີ່ມໝວດໝູ່ໃໝ່</h3>
            <button class="modal-close" onclick="closeModal('addCategoryModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">ຊື່ໝວດໝູ່ *</label>
                <input type="text" class="form-control" id="newCategoryName" placeholder="ຕົວຢ່າງ: ຢາແກ້ປວດ">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-white" onclick="closeModal('addCategoryModal')">ຍົກເລີກ</button>
            <button class="btn btn-primary" id="saveCategoryBtn" onclick="saveNewCategory()"><i class="fas fa-save"></i> ບັນທຶກ</button>
        </div>
    </div>
</div>

<!-- Add Unit Modal -->
<div class="modal-overlay" id="addUnitModal" style="z-index: 2000;">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3>ເພີ່ມຫົວໜ່ວຍໃໝ່</h3>
            <button class="modal-close" onclick="closeModal('addUnitModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">ຊື່ຫົວໜ່ວຍ *</label>
                <input type="text" class="form-control" id="newUnitName" placeholder="ຕົວຢ່າງ: ລັງ">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-white" onclick="closeModal('addUnitModal')">ຍົກເລີກ</button>
            <button class="btn btn-primary" id="saveUnitBtn" onclick="saveNewUnit()"><i class="fas fa-save"></i> ບັນທຶກ</button>
        </div>
    </div>
</div>

<!-- Import Products from Excel Modal -->
<div class="modal-overlay" id="importExcelModal">
    <div class="modal" style="max-width:850px;width:95%;">
        <div class="modal-header" style="border-bottom:1px solid #e2e8f0;padding:16px 22px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:38px;height:38px;border-radius:10px;background:#ecfdf5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                    <i class="fas fa-file-excel"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:1.15rem;font-weight:700;color:var(--text-dark);">ນຳເຂົ້າລາຍການຢາຈາກໄຟລ໌ Excel / CSV</h3>
                    <p style="margin:2px 0 0;font-size:0.8rem;color:var(--text-light);">ຮອງຮັບໄຟລ໌ .xlsx, .xls, .csv ພ້ອມກວດສອບ ແລະ ສະແດງຕົວຢ່າງກ່ອນບັນທຶກ</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('importExcelModal')"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="modal-body" style="padding:20px 24px;max-height:calc(85vh - 140px);overflow-y:auto;">
            <!-- Top Action / Download Template Card -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <i class="fas fa-lightbulb" style="color:#eab308;font-size:1.2rem;"></i>
                    <div style="font-size:0.85rem;color:#475569;">
                        ຍັງບໍ່ມີໄຟລ໌ແບບຟອມ? ດາວໂຫຼດແມ່ແບບຕົວຢ່າງເພື່ອໄປປ້ອນຂໍ້ມູນໄດ້ງ່າຍຂຶ້ນ
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="downloadExcelTemplate('xlsx')" style="border-color:#10b981;color:#059669;background:#fff;font-weight:600;">
                        <i class="fas fa-download"></i> ດາວໂຫຼດແມ່ແບບ Excel (.xlsx)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="downloadExcelTemplate('csv')" style="border-color:#64748b;color:#475569;background:#fff;">
                        <i class="fas fa-file-csv"></i> ດາວໂຫຼດ .CSV
                    </button>
                </div>
            </div>

            <!-- Upload Area & Options (Step 1) -->
            <div id="importStep1">
                <!-- Drop Zone -->
                <div id="excelDropZone" onclick="document.getElementById('excelFileInput').click()" style="border:2px dashed #cbd5e1;border-radius:14px;padding:36px 20px;text-align:center;background:#fafafa;cursor:pointer;transition:all 0.2s ease;">
                    <input type="file" id="excelFileInput" accept=".xlsx, .xls, .csv" style="display:none;" onchange="handleExcelFileSelect(this)">
                    <div style="width:60px;height:60px;border-radius:50%;background:#e0f2fe;color:#0284c7;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 14px;">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div style="font-size:1rem;font-weight:600;color:#1e293b;margin-bottom:6px;">
                        ຄລິກເພື່ອເລືອກໄຟລ໌ Excel / CSV ຫຼື ລາກໄຟລ໌ມາໃສ່ທີ່ນີ້
                    </div>
                    <div style="font-size:0.82rem;color:#64748b;">
                        ຮອງຮັບໄຟລ໌: <strong>.xlsx, .xls, .csv</strong> (ສູງສຸດ 15MB)
                    </div>
                </div>

                <!-- Settings / Options -->
                <div style="margin-top:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;">
                    <div style="font-weight:700;font-size:0.9rem;color:#1e293b;margin-bottom:12px;">
                        <i class="fas fa-cog" style="color:var(--primary);margin-right:6px;"></i>ການຕັ້ງຄ່າການນຳເຂົ້າ:
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;">
                        <div>
                            <label style="display:block;font-size:0.82rem;font-weight:600;color:#334155;margin-bottom:8px;">
                                ເມື່ອພົບສິນຄ້າທີ່ມີບາໂຄດຊ້ຳກັນ:
                            </label>
                            <div style="display:flex;flex-direction:column;gap:8px;font-size:0.85rem;">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="radio" name="duplicateAction" value="update" checked style="accent-color:var(--primary);width:16px;height:16px;">
                                    <span><strong>ອັບເດດຂໍ້ມູນສິນຄ້າເດີມ</strong> (ປັບລາຄາ, ສະຕ໋ອກ, ວັນໝົດອາຍຸ)</span>
                                </label>
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="radio" name="duplicateAction" value="skip" style="accent-color:var(--primary);width:16px;height:16px;">
                                    <span><strong>ຂ້າມລາຍການທີ່ຊ້ຳ</strong> (ບໍ່ແກ້ໄຂສິນຄ້າເດີມ)</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label style="display:block;font-size:0.82rem;font-weight:600;color:#334155;margin-bottom:8px;">
                                ຕົວເລືອກເພີ່ມເຕີມ:
                            </label>
                            <div style="display:flex;flex-direction:column;gap:8px;font-size:0.85rem;">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" id="importAutoCat" checked style="accent-color:var(--primary);width:16px;height:16px;">
                                    <span>ສ້າງ <strong>ໝວດໝູ່ໃໝ່</strong> ອັດຕະໂນມັດ ຖ້າຍັງບໍ່ມີໃນລະບົບ</span>
                                </label>
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" id="importAutoUnit" checked style="accent-color:var(--primary);width:16px;height:16px;">
                                    <span>ສ້າງ <strong>ຫົວໜ່ວຍໃໝ່</strong> ອັດຕະໂນມັດ ຖ້າຍັງບໍ່ມີໃນລະບົບ</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Area (Step 2) -->
            <div id="importStep2" style="display:none;">
                <!-- File details & Stats bar -->
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;background:#f0fdf4;border:1px solid #bbf7d0;padding:12px 16px;border-radius:10px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <i class="fas fa-file-check" style="font-size:1.5rem;color:#16a34a;"></i>
                        <div>
                            <div style="font-weight:700;font-size:0.9rem;color:#15803d;" id="importFileName">file.xlsx</div>
                            <div style="font-size:0.75rem;color:#16a34a;" id="importFileSize">0 KB</div>
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <span class="badge" style="background:#e0f2fe;color:#0369a1;padding:6px 12px;font-size:0.82rem;">
                            ທັງໝົດ: <strong id="importTotalRows">0</strong> ລາຍການ
                        </span>
                        <span class="badge" style="background:#dcfce7;color:#15803d;padding:6px 12px;font-size:0.82rem;">
                            ພ້ອມນຳເຂົ້າ: <strong id="importValidRows">0</strong> ລາຍການ
                        </span>
                        <button type="button" class="btn btn-white btn-sm" onclick="resetImportStep()" style="border:1px solid #cbd5e1;font-size:0.8rem;">
                            <i class="fas fa-undo"></i> ເລືອກໄຟລ໌ໃໝ່
                        </button>
                    </div>
                </div>

                <!-- Preview Table -->
                <div style="max-height:360px;overflow-y:auto;overflow-x:auto;border:1px solid #e2e8f0;border-radius:10px;">
                    <table class="table" style="font-size:0.82rem;margin-bottom:0;" id="importPreviewTable">
                        <thead>
                            <tr style="position:sticky;top:0;background:#f8fafc;z-index:2;">
                                <th style="width:40px;text-align:center;">#</th>
                                <th>ບາໂຄດ</th>
                                <th>ຊື່ສິນຄ້າ / ຢາ *</th>
                                <th>ໝວດໝູ່</th>
                                <th>ລາຄາທຶນ</th>
                                <th>ລາຄາຂາຍ</th>
                                <th>ສະຕ໋ອກ</th>
                                <th>ໜ່ວຍ</th>
                                <th>ວັນໝົດອາຍຸ</th>
                                <th>ສະຖານະ</th>
                            </tr>
                        </thead>
                        <tbody id="importPreviewTbody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Progress Indicator (During saving) -->
            <div id="importProgressBarWrapper" style="display:none;margin-top:16px;">
                <div style="display:flex;justify-content:space-between;font-size:0.85rem;font-weight:600;margin-bottom:6px;color:#334155;">
                    <span id="importProgressStatus"><i class="fas fa-spinner fa-spin"></i> ກຳລັງນຳເຂົ້າຂໍ້ມູນສິນຄ້າ...</span>
                    <span id="importProgressPercent">0%</span>
                </div>
                <div style="height:10px;background:#e2e8f0;border-radius:5px;overflow:hidden;">
                    <div id="importProgressBar" style="width:0%;height:100%;background:linear-gradient(90deg, #10b981, #059669);transition:width 0.3s ease;"></div>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="padding:14px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <div style="font-size:0.82rem;color:#64748b;">
                * ຊື່ສິນຄ້າ ແລະ ລາຄາຂາຍ ເປັນຂໍ້ມູນທີ່ຈຳເປັນຕ້ອງມີ
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-white" onclick="closeModal('importExcelModal')">ຍົກເລີກ</button>
                <button type="button" class="btn btn-primary" id="btnConfirmImport" onclick="executeExcelImport()" style="display:none;background:#10b981;border-color:#10b981;">
                    <i class="fas fa-check-circle"></i> ຢືນຢັນນຳເຂົ້າ (<span id="importConfirmCountBadge">0</span>)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Floating Batch Barcode Action Bar -->
<div id="batchActionBar" style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:10px 18px;border-radius:50px;box-shadow:0 10px 30px rgba(0,0,0,0.35);z-index:2000;align-items:center;gap:16px;">
    <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;font-weight:600;">
        <span style="background:var(--primary);color:#fff;min-width:24px;height:24px;padding:0 6px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;" id="batchBarCount">0</span>
        <span>ລາຍການທີ່ເລືອກ</span>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
        <button type="button" class="btn btn-primary btn-sm" onclick="openBatchPrintBarcode()" style="border-radius:30px;padding:6px 16px;font-size:13px;box-shadow:0 4px 12px rgba(30,142,62,0.4);">
            <i class="fas fa-barcode"></i> ພິມບາໂຄດທີ່ເລືອກ
        </button>
        <button type="button" class="btn btn-sm" onclick="clearProductSelections()" style="border-radius:30px;padding:6px 12px;font-size:12px;background:rgba(255,255,255,0.15);color:#f1f5f9;border:none;cursor:pointer;">
            ຍົກເລີກ
        </button>
    </div>
</div>

<!-- Barcode Print Modal (Single & Batch) -->
<div class="modal-overlay" id="barcodePrintModal">
    <div class="modal" style="max-width:750px;">
        <div class="modal-header">
            <h3><i class="fas fa-barcode" style="margin-right:8px;color:var(--primary);"></i><span id="barcodeModalHeading">ພິມສະຕິກເກີບາໂຄດ</span></h3>
            <button class="modal-close" onclick="closeModal('barcodePrintModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">
            <!-- Batch Products List (Visible only in Batch Mode) -->
            <div id="batchProductsListWrapper" style="display:none;margin-bottom:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
                    <span style="font-weight:700;font-size:13px;color:var(--text-dark);"><i class="fas fa-boxes" style="color:var(--primary);margin-right:4px;"></i>ລາຍການສິນຄ້າທີ່ເລືອກ (<span id="batchListCount">0</span> ລາຍການ)</span>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="font-size:12px;color:var(--text-light);">ກຳນົດຈຳນວນທຸກລາຍການ:</span>
                        <input type="number" id="batchApplyAllQty" value="10" min="1" max="100" style="width:65px;height:30px;padding:2px 6px;font-size:12px;border:1px solid #cbd5e1;border-radius:6px;text-align:center;">
                        <button type="button" class="btn btn-outline btn-sm" style="padding:2px 10px;font-size:12px;height:30px;" onclick="applyQtyToAllBatch()">ນຳໃຊ້</button>
                    </div>
                </div>
                <div id="batchItemsTable" style="max-height:160px;overflow-y:auto;background:#fff;border:1px solid #e2e8f0;border-radius:8px;">
                    <!-- Rendered by JS -->
                </div>
            </div>

            <!-- Single Product Fields (Visible only in Single Mode) -->
            <div id="singleProductFields" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:12px;">
                <div class="form-group" style="margin-bottom:0;flex:2;min-width:200px;">
                    <label class="form-label">ຊື່ສິນຄ້າ</label>
                    <input type="text" class="form-control" id="barcodeProductName" readonly style="background:#f5f5f5;">
                </div>
                <div class="form-group" style="margin-bottom:0;width:110px;">
                    <label class="form-label">ຈຳນວນດວງ</label>
                    <input type="number" class="form-control" id="barcodePrintQty" value="12" min="1" max="100" onchange="onSingleQtyChange()">
                </div>
            </div>

            <!-- Shared Controls: Size & Printer Selection -->
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:12px;color:#166534;display:flex;align-items:flex-start;gap:8px;">
                <i class="fas fa-info-circle" style="margin-top:2px;font-size:14px;color:#15803d;flex-shrink:0;"></i>
                <div>
                    <strong>ຄຳແນະນຳສຳລັບເຄື່ອງພິມສະຕິກເກີ Grozziie TP878:</strong><br>
                    ເມື່ອໜ້າຕ່າງພິມ Chrome ຂຶ້ນມາ: ໃຫ້ຄລິກ <strong>More settings (ການຕັ້ງຄ່າເພີ່ມເຕີມ)</strong> &rarr; ເລືອກ <strong>Paper size</strong> ໃຫ້ຕົງກັບຂະໜາດສະຕິກເກີ (ເຊັ່ນ 40x30mm) ແລະ ປັບ <strong>Margins</strong> ເປັນ <strong>None (ບໍ່ມີຂອບ)</strong>.
                </div>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:14px;">
                <div class="form-group" style="margin-bottom:0;flex:1.8;min-width:200px;">
                    <label class="form-label">ຂະໜາດເຈ້ຍສະຕິກເກີ</label>
                    <select class="form-control" id="barcodeTemplateSize" onchange="localStorage.setItem('pos_barcode_template', this.value); renderBarcodeGrid();">
                        <optgroup label="ສະຕິກເກີ 1 ແຖວ (ມາດຕະຖານ Grozziie TP878 / Xprinter / Barcode Printer)">
                            <option value="40x30_1col" selected>ສະຕິກເກີ 4.0 x 3.0 ຊມ (1 ແຖວ - ແນະນຳສຳລັບ Grozziie TP878)</option>
                            <option value="50x30_1col">ສະຕິກເກີ 5.0 x 3.0 ຊມ (1 ແຖວ)</option>
                            <option value="30x40_1col">ສະຕິກເກີ 3.0 x 4.0 ຊມ (1 ແຖວ - ແນວຕັ້ງ)</option>
                            <option value="30x50_1col">ສະຕິກເກີ 3.0 x 5.0 ຊມ (1 ແຖວ - ແນວຕັ້ງ)</option>
                            <option value="40x50_1col">ສະຕິກເກີ 4.0 x 5.0 ຊມ (1 ແຖວ - ແນວຕັ້ງ)</option>
                            <option value="40x20_1col">ສະຕິກເກີ 4.0 x 2.0 ຊມ (1 ແຖວ)</option>
                            <option value="40x25_1col">ສະຕິກເກີ 4.0 x 2.5 ຊມ (1 ແຖວ)</option>
                            <option value="30x20_1col">ສະຕິກເກີ 3.0 x 2.0 ຊມ (1 ແຖວ - ຂະໜາດນ້ອຍ)</option>
                            <option value="50x25_1col">ສະຕິກເກີ 5.0 x 2.5 ຊມ (1 ແຖວ)</option>
                            <option value="60x40_1col">ສະຕິກເກີ 6.0 x 4.0 ຊມ (1 ແຖວ)</option>
                            <option value="70x50_1col">ສະຕິກເກີ 7.0 x 5.0 ຊມ (1 ແຖວ)</option>
                            <option value="80x50_1col">ສະຕິກເກີ 8.0 x 5.0 ຊມ (1 ແຖວ)</option>
                            <option value="80x60_1col">ສະຕິກເກີ 8.0 x 6.0 ຊມ (1 ແຖວ)</option>
                        </optgroup>
                        <optgroup label="ສະຕິກເກີຫຼາຍແຖວ (Multi-column Rolls)">
                            <option value="40x30_2col">ສະຕິກເກີ 4.0 x 3.0 ຊມ (2 ແຖວ)</option>
                            <option value="50x30_2col">ສະຕິກເກີ 5.0 x 3.0 ຊມ (2 ແຖວ)</option>
                            <option value="32x25_3col">ສະຕິກເກີ 3.2 x 2.5 ຊມ (3 ແຖວ - ມ້ວນໃຫຍ່ 100mm)</option>
                        </optgroup>
                        <optgroup label="ເຈ້ຍມ້ວນໃບບິນ (Thermal Receipt Roll - ຕໍ່ເນື່ອງບໍ່ຕັດ)">
                            <option value="80mm_roll">ເຈ້ຍມ້ວນໃບບິນ 80mm</option>
                            <option value="58mm_roll">ເຈ້ຍມ້ວນໃບບິນ 58mm</option>
                        </optgroup>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;width:150px;">
                    <label class="form-label"><i class="fas fa-arrows-alt-v" style="margin-right:4px;color:var(--primary);"></i>ແນວເຈ້ຍ (Orientation)</label>
                    <select class="form-control" id="barcodeOrientation" onchange="localStorage.setItem('pos_barcode_orientation', this.value); renderBarcodeGrid();">
                        <option value="portrait" selected>ແນວຕັ້ງ (Portrait)</option>
                        <option value="landscape">ແນວນອນ (Landscape)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;flex:1.4;min-width:180px;">
                    <label class="form-label"><i class="fas fa-print" style="margin-right:4px;color:var(--primary);"></i>ເລືອກເຄື່ອງພິມ (Printer)</label>
                    <select class="form-control" id="barcodePrinterSelect" onchange="onBarcodePrinterChange(this.value)">
                        <option value="">ກຳລັງໂຫຼດເຄື່ອງພິມ...</option>
                    </select>
                </div>
            </div>

            <!-- Total Stickers Count Badge & Preview Area -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span style="font-size:12px;font-weight:600;color:var(--text-light);"><i class="fas fa-eye"></i> ຕົວຢ່າງສະຕິກເກີບາໂຄດ:</span>
                <span id="barcodeTotalStickersBadge" class="badge badge-success" style="font-size:12px;padding:3px 8px;">ລວມ: 12 ດວງ</span>
            </div>
            <div id="barcodePreviewArea" style="border:1px dashed #cbd5e1;border-radius:10px;padding:12px;max-height:360px;overflow-y:auto;background:#fff;"></div>
        </div>
        <div class="modal-footer" style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;">
            <div id="barcodePrinterStatusHint" style="font-size:12px;color:var(--text-light);display:flex;align-items:center;gap:6px;">
                <i class="fas fa-check-circle" style="color:var(--primary);"></i>
                <span id="barcodeActivePrinterName">ພ້ອມສັ່ງພິມ</span>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" class="btn btn-white" onclick="closeModal('barcodePrintModal')">ຍົກເລີກ</button>
                <button type="button" class="btn btn-primary" id="btnExecuteBarcodePrint" onclick="printBarcodeGrid()"><i class="fas fa-print"></i> ພິມບາໂຄດ</button>
            </div>
        </div>
    </div>
</div>

<!-- JsBarcode Local Bundle -->
<script src="<?= BASE_URL ?>assets/vendor/JsBarcode.all.min.js"></script>

<!-- SheetJS Local Bundle for Excel Import & Export -->
<script src="<?= BASE_URL ?>assets/vendor/xlsx.full.min.js"></script>

<script>
function openAddCategoryModal() {
    document.getElementById('newCategoryName').value = '';
    openModal('addCategoryModal');
}

async function saveNewCategory() {
    const name = document.getElementById('newCategoryName').value.trim();
    if (!name) {
        showToast('ກະລຸນາປ້ອນຊື່ໝວດໝູ່', 'warning');
        return;
    }
    const btn = document.getElementById('saveCategoryBtn');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...'; }
    try {
        const result = await fetchAPI(`${BASE_URL}api/categories.php`, {
            method: 'POST',
            body: JSON.stringify({ name: name })
        });
        if (result.success) {
            showToast(result.message, 'success');
            const select = document.getElementById('prodCategory');
            const option = document.createElement('option');
            option.value = result.category.id;
            option.textContent = result.category.name;
            select.appendChild(option);
            select.value = result.category.id;
            closeModal('addCategoryModal');
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
    }
}

function openAddUnitModal() {
    document.getElementById('newUnitName').value = '';
    openModal('addUnitModal');
}

async function saveNewUnit() {
    const name = document.getElementById('newUnitName').value.trim();
    if (!name) {
        showToast('ກະລຸນາປ້ອນຊື່ຫົວໜ່ວຍ', 'warning');
        return;
    }
    const btn = document.getElementById('saveUnitBtn');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...'; }
    try {
        const result = await fetchAPI(`${BASE_URL}api/units.php`, {
            method: 'POST',
            body: JSON.stringify({ name: name })
        });
        if (result.success) {
            showToast(result.message, 'success');
            const select = document.getElementById('prodUnit');
            const option = document.createElement('option');
            option.value = result.unit.name;
            option.textContent = result.unit.name;
            select.appendChild(option);
            select.value = result.unit.name;
            closeModal('addUnitModal');
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
    }
}
function calculateSellingPrice() {
    const cost = parseFloat(document.getElementById('prodCostPrice').value) || 0;
    const markup = parseFloat(document.getElementById('prodMarkup').value) || 0;
    if (cost > 0) {
        const sellingPrice = cost + (cost * (markup / 100));
        document.getElementById('prodSellingPrice').value = Math.round(sellingPrice);
    } else {
        document.getElementById('prodSellingPrice').value = '';
    }
}

function calculateMarkup() {
    const cost = parseFloat(document.getElementById('prodCostPrice').value) || 0;
    const selling = parseFloat(document.getElementById('prodSellingPrice').value) || 0;
    if (cost > 0 && selling > 0) {
        const markup = ((selling - cost) / cost) * 100;
        document.getElementById('prodMarkup').value = Math.round(markup * 10) / 10;
    }
}

function resetImagePreview() {
    const fileInput = document.getElementById('prodImageInput');
    if (fileInput) fileInput.value = '';
    const previewContainer = document.getElementById('imagePreviewContainer');
    const placeholder = document.getElementById('imageUploadPlaceholder');
    const previewImg = document.getElementById('imagePreviewImg');
    if (previewContainer) previewContainer.style.display = 'none';
    if (placeholder) placeholder.style.display = 'flex';
    if (previewImg) previewImg.src = '';
}

function handleProductImageSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            showToast('ຂະໜາດຮູບພາບຕ້ອງບໍ່ເກີນ 5MB', 'warning');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('imagePreviewImg');
            const previewContainer = document.getElementById('imagePreviewContainer');
            const placeholder = document.getElementById('imageUploadPlaceholder');
            
            previewImg.src = e.target.result;
            previewContainer.style.display = 'inline-block';
            placeholder.style.display = 'none';
            document.getElementById('removeExistingImage').value = '0';
        };
        reader.readAsDataURL(file);
    }
}

function removeProductImage(e) {
    if (e) e.stopPropagation();
    resetImagePreview();
    document.getElementById('removeExistingImage').value = '1';
}

function previewImageLightbox(imgUrl, title) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title || 'ຮູບພາບສິນຄ້າ',
            imageUrl: imgUrl,
            imageAlt: title,
            imageWidth: 320,
            imageHeight: 320,
            imageClass: 'lightbox-preview-img',
            confirmButtonText: 'ປິດ',
            customClass: { popup: 'swal2-lao-popup' }
        });
    }
}

function openAddProductModal() {
    document.getElementById('prodId').value = '';
    document.getElementById('productForm').reset();
    document.getElementById('removeExistingImage').value = '0';
    resetImagePreview();
    document.getElementById('productModalTitle').textContent = 'ເພີ່ມສິນຄ້າໃໝ່';
    const multiBox = document.getElementById('editMultiBarcodeBox');
    if (multiBox) multiBox.style.display = 'none';
    const alertBox = document.getElementById('productBarcodeAlert');
    if (alertBox) alertBox.style.display = 'none';
    openModal('addProductModal');
}

async function saveProduct() {
    const id = document.getElementById('prodId').value;
    const name = document.getElementById('prodName').value.trim();
    const costPrice = document.getElementById('prodCostPrice').value;
    const sellingPrice = document.getElementById('prodSellingPrice').value;
    
    if (!name || !costPrice || !sellingPrice) {
        ['prodName', 'prodCostPrice', 'prodSellingPrice'].forEach(fid => {
            const el = document.getElementById(fid);
            if (el) {
                if (!el.value.trim()) {
                    el.classList.add('is-invalid');
                    el.style.borderColor = 'var(--danger)';
                    el.addEventListener('input', function onInputClear() {
                        el.classList.remove('is-invalid');
                        el.style.borderColor = '';
                        el.removeEventListener('input', onInputClear);
                    });
                } else {
                    el.classList.remove('is-invalid');
                    el.style.borderColor = '';
                }
            }
        });
        const firstEmpty = ['prodName', 'prodCostPrice', 'prodSellingPrice'].find(fid => !document.getElementById(fid)?.value?.trim());
        if (firstEmpty) document.getElementById(firstEmpty)?.focus();
        showToast('ກະລຸນາປ້ອນຂໍ້ມູນທີ່ຈຳເປັນ (ຊື່ສິນຄ້າ, ລາຄາທຶນ, ລາຄາຂາຍ) ໃຫ້ຄົບ', 'warning');
        return;
    }
    
    const formData = new FormData();
    if (id) {
        formData.append('id', id);
    }
    formData.append('barcode', document.getElementById('prodBarcode').value.trim());
    formData.append('name', name);
    formData.append('category_id', document.getElementById('prodCategory').value || '');
    formData.append('cost_price', parseFloat(costPrice));
    formData.append('selling_price', parseFloat(sellingPrice));
    formData.append('stock_quantity', parseInt(document.getElementById('prodStock').value) || 0);
    formData.append('min_stock', parseInt(document.getElementById('prodMinStock').value) || 5);
    formData.append('unit', document.getElementById('prodUnit').value || 'ແຜງ');
    formData.append('expiry_date', document.getElementById('prodExpiry').value || '');
    
    const fileInput = document.getElementById('prodImageInput');
    if (fileInput && fileInput.files && fileInput.files[0]) {
        formData.append('image', fileInput.files[0]);
    }
    if (document.getElementById('removeExistingImage').value === '1') {
        formData.append('remove_image', '1');
    }
    
    const btn = document.getElementById('saveProductBtn');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...'; }

    try {
        const url = `${BASE_URL}api/products.php`;
        const result = await fetchAPI(url, {
            method: 'POST',
            body: formData,
        });
        if (result.success) {
            showToast(id ? 'ແກ້ໄຂສິນຄ້າສຳເລັດ' : 'ເພີ່ມສິນຄ້າສຳເລັດ', 'success');
            closeModal('addProductModal');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
    }
}

async function deleteProduct(id) {
    if (!await confirmDelete('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບສິນຄ້ານີ້?')) return;
    if (typeof Swal !== 'undefined' && Swal.isVisible()) {
        Swal.showLoading();
    }
    try {
        const result = await fetchAPI(`${BASE_URL}api/products.php?id=${id}`, { method: 'DELETE' });
        if (result.success) {
            showToast('ລຶບສິນຄ້າສຳເລັດ', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
    }
}

async function editProduct(id) {
    const activeBtn = document.activeElement;
    if (activeBtn && activeBtn.tagName === 'BUTTON') {
        activeBtn.disabled = true;
        activeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
    try {
        const result = await fetchAPI(`${BASE_URL}api/products.php?id=${id}`);
        if (result.success && result.product) {
            const p = result.product;
            document.getElementById('prodId').value = p.id;
            document.getElementById('prodBarcode').value = p.barcode || '';
            document.getElementById('prodCategory').value = p.category_id || '';
            document.getElementById('prodName').value = p.name;
            document.getElementById('prodCostPrice').value = Math.round(p.cost_price);
            document.getElementById('prodSellingPrice').value = Math.round(p.selling_price);
            calculateMarkup();
            document.getElementById('prodStock').value = p.stock_quantity;
            document.getElementById('prodMinStock').value = p.min_stock;
            document.getElementById('prodUnit').value = p.unit || 'ແຜງ';
            document.getElementById('prodExpiry').value = p.expiry_date || '';
            document.getElementById('removeExistingImage').value = '0';
            
            // Image preview
            const previewContainer = document.getElementById('imagePreviewContainer');
            const placeholder = document.getElementById('imageUploadPlaceholder');
            const previewImg = document.getElementById('imagePreviewImg');
            const fileInput = document.getElementById('prodImageInput');
            if (fileInput) fileInput.value = '';
            
            if (p.image) {
                previewImg.src = `assets/images/products/${p.image}`;
                previewContainer.style.display = 'inline-block';
                placeholder.style.display = 'none';
            } else {
                previewContainer.style.display = 'none';
                placeholder.style.display = 'flex';
                previewImg.src = '';
            }
            
            document.getElementById('productModalTitle').textContent = 'ແກ້ໄຂຂໍ້ມູນສິນຄ້າ';
            const multiBox = document.getElementById('editMultiBarcodeBox');
            if (multiBox) multiBox.style.display = 'block';
            const alertBox = document.getElementById('productBarcodeAlert');
            if (alertBox) alertBox.style.display = 'none';
            openModal('addProductModal');
        } else {
            showToast('ບໍ່ສາມາດດຶງຂໍ້ມູນສິນຄ້າໄດ້', 'error');
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການດຶງຂໍ້ມູນ', 'error');
    } finally {
        if (activeBtn && activeBtn.tagName === 'BUTTON') {
            activeBtn.disabled = false;
            activeBtn.innerHTML = '<i class="fas fa-edit"></i>';
        }
    }
}

function openMultiBarcodeModalFromProductModal() {
    const id = document.getElementById('prodId')?.value;
    const name = document.getElementById('prodName')?.value || 'ສິນຄ້າ';
    const unit = document.getElementById('prodUnit')?.value || 'ອັນ';
    const price = document.getElementById('prodSellingPrice')?.value || 0;
    if (id && typeof openMultiBarcodeModal === 'function') {
        openMultiBarcodeModal(id, name, unit, price);
    }
}

// === Barcode Generation & Printing ===

async function generateBarcode(productId, productName) {
    const result = await Swal.fire({
        title: 'ສ້າງບາໂຄດ',
        text: `ສ້າງບາໂຄດ EAN-13 ໃຫ້ "${productName}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ສ້າງ',
        cancelButtonText: 'ຍົກເລີກ',
        customClass: { popup: 'swal2-lao-popup' }
    });
    if (!result.isConfirmed) return;

    try {
        const res = await fetchAPI(`${BASE_URL}api/barcode_gen.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'generate' })
        });
        if (!res.success) { showToast(res.message || 'ເກີດຂໍ້ຜິດພາດ', 'error'); return; }

        const assignRes = await fetchAPI(`${BASE_URL}api/barcode_gen.php`, {
            method: 'POST',
            body: JSON.stringify({ action: 'assign', product_id: productId, barcode: res.barcode })
        });
        if (assignRes.success) {
            showToast('ສ້າງ ແລະ ກຳນົດບາໂຄດສຳເລັດ: ' + res.barcode, 'success');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            showToast(assignRes.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (err) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
    }
}

async function loadBarcodePrinters() {
    const select = document.getElementById('barcodePrinterSelect');
    const hintName = document.getElementById('barcodeActivePrinterName');
    if (!select) return;

    // Restore saved template if available
    const savedTpl = localStorage.getItem('pos_barcode_template');
    const tplSelect = document.getElementById('barcodeTemplateSize');
    if (savedTpl && tplSelect) {
        tplSelect.value = savedTpl;
    }

    // Restore saved orientation if available
    const savedOri = localStorage.getItem('pos_barcode_orientation') || 'portrait';
    const oriSelect = document.getElementById('barcodeOrientation');
    if (savedOri && oriSelect) {
        oriSelect.value = savedOri;
    }

    if (window.api && typeof window.api.getPrinters === 'function') {
        try {
            const printers = await window.api.getPrinters();
            select.innerHTML = '';
            
            const savedPrinter = localStorage.getItem('pos_barcode_printer') || '';
            let selectedName = '';

            if (Array.isArray(printers) && printers.length > 0) {
                // Find label printer if no saved printer
                if (savedPrinter && printers.some(p => p.name === savedPrinter)) {
                    selectedName = savedPrinter;
                } else {
                    const preferredLabelPrinter = printers.find(p => /grozziie|tp878|label|sticker|barcode/i.test(p.name));
                    if (preferredLabelPrinter) {
                        selectedName = preferredLabelPrinter.name;
                        // Auto-select standard 40x30 1-col sticker template if Grozziie TP878 is detected and no template saved
                        if (!savedTpl && tplSelect) {
                            tplSelect.value = '40x30_1col';
                        }
                    } else {
                        const defaultP = printers.find(p => p.isDefault) || printers[0];
                        selectedName = defaultP ? defaultP.name : '';
                    }
                }

                printers.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.name;
                    let tag = '';
                    const n = p.name.toLowerCase();
                    if (n.includes('grozziie') || n.includes('tp878') || n.includes('label') || n.includes('sticker') || n.includes('barcode')) {
                        tag = ' 🏷️ [ເຄື່ອງພິມສະຕິກເກີ/Label]';
                    } else if (n.includes('c80') || n.includes('pos') || n.includes('receipt') || n.includes('80')) {
                        tag = ' 🧾 [ເຄື່ອງພິມໃບບິນ 80mm]';
                    }
                    opt.textContent = p.name + tag + (p.isDefault ? ' (Default)' : '');
                    if (p.name === selectedName) {
                        opt.selected = true;
                    }
                    select.appendChild(opt);
                });

                if (hintName && selectedName) {
                    hintName.textContent = `ເຄື່ອງພິມ: ${selectedName}`;
                }
            } else {
                select.innerHTML = '<option value="">ເຄື່ອງພິມເລີ່ມຕົ້ນ (Default)</option>';
                if (hintName) hintName.textContent = 'ເຄື່ອງພິມເລີ່ມຕົ້ນ (Default)';
            }
        } catch (e) {
            console.warn('Failed to load printers:', e);
            select.innerHTML = '<option value="">ເຄື່ອງພິມເລີ່ມຕົ້ນ (Default)</option>';
        }
    } else {
        select.innerHTML = '<option value="">ເຄື່ອງພິມຂອງລະບົບ (System Default)</option>';
        if (hintName) hintName.textContent = 'ເຄື່ອງພິມຂອງລະບົບ';
    }
}

function onBarcodePrinterChange(printerName) {
    localStorage.setItem('pos_barcode_printer', printerName);
    localStorage.setItem('pos_selected_printer', printerName);
    const hintName = document.getElementById('barcodeActivePrinterName');
    if (hintName) {
        hintName.textContent = printerName ? `ເຄື່ອງພິມ: ${printerName}` : 'ເຄື່ອງພິມເລີ່ມຕົ້ນ';
    }
    showToast(`ເລືອກເຄື່ອງພິມ: ${printerName || 'Default'}`, 'info');
}

let currentBarcodePrint = { barcode: '', name: '', price: '' };
let barcodePrintItems = [];
let isBatchBarcodeMode = false;

function toggleSelectAllProducts(checked) {
    const checkboxes = document.querySelectorAll('.product-chk');
    checkboxes.forEach(chk => {
        const row = chk.closest('tr');
        if (!row || row.style.display !== 'none') {
            chk.checked = checked;
        }
    });
    updateBatchPrintButton();
}

function updateBatchPrintButton() {
    const checked = document.querySelectorAll('.product-chk:checked');
    const btn = document.getElementById('btnBatchPrintBarcode');
    const badge = document.getElementById('batchCountBadge');
    const bar = document.getElementById('batchActionBar');
    const barCount = document.getElementById('batchBarCount');
    const selectAll = document.getElementById('selectAllProducts');

    const count = checked.length;

    if (btn && badge) {
        if (count > 0) {
            btn.style.display = 'inline-flex';
            badge.textContent = count;
        } else {
            btn.style.display = 'none';
        }
    }

    if (bar && barCount) {
        if (count > 0) {
            bar.style.display = 'flex';
            barCount.textContent = count;
        } else {
            bar.style.display = 'none';
        }
    }

    if (selectAll) {
        const all = document.querySelectorAll('.product-chk');
        selectAll.checked = all.length > 0 && count === all.length;
    }
}

function clearProductSelections() {
    document.querySelectorAll('.product-chk').forEach(c => c.checked = false);
    const selectAll = document.getElementById('selectAllProducts');
    if (selectAll) selectAll.checked = false;
    updateBatchPrintButton();
}

function openPrintBarcode(productId, barcode, name, price) {
    const checked = document.querySelectorAll('.product-chk:checked');
    if (checked.length > 1) {
        openBatchPrintBarcode();
        return;
    }
    isBatchBarcodeMode = false;
    currentBarcodePrint = { id: productId, barcode, name, price };
    const defaultQty = parseInt(document.getElementById('barcodePrintQty')?.value) || 12;
    barcodePrintItems = [{ id: productId, barcode, name, price, qty: defaultQty }];
    setupBarcodeModalView();
}

function openBatchPrintBarcode() {
    const checked = document.querySelectorAll('.product-chk:checked');
    if (checked.length === 0) {
        showToast('ກະລຸນາເລືອກສິນຄ້າທີ່ຕ້ອງການພິມຢ່າງໜ້ອຍ 1 ລາຍການ', 'warning');
        return;
    }

    isBatchBarcodeMode = true;
    const defaultBatchQty = parseInt(document.getElementById('batchApplyAllQty')?.value) || 10;
    barcodePrintItems = Array.from(checked).map(chk => ({
        id: chk.dataset.id,
        barcode: chk.dataset.barcode,
        name: chk.dataset.name,
        price: chk.dataset.price,
        qty: defaultBatchQty
    }));
    setupBarcodeModalView();
}

function setupBarcodeModalView() {
    const heading = document.getElementById('barcodeModalHeading');
    const singleFields = document.getElementById('singleProductFields');
    const batchWrapper = document.getElementById('batchProductsListWrapper');

    if (isBatchBarcodeMode) {
        if (heading) heading.textContent = `ພິມສະຕິກເກີບາໂຄດຫຼາຍສິນຄ້າ (${barcodePrintItems.length} ລາຍການ)`;
        if (singleFields) singleFields.style.display = 'none';
        if (batchWrapper) batchWrapper.style.display = 'block';
        renderBatchItemsTable();
    } else {
        if (heading) heading.textContent = 'ພິມສະຕິກເກີບາໂຄດ';
        if (singleFields) singleFields.style.display = 'flex';
        if (batchWrapper) batchWrapper.style.display = 'none';
        const nameInput = document.getElementById('barcodeProductName');
        const qtyInput = document.getElementById('barcodePrintQty');
        if (nameInput) nameInput.value = barcodePrintItems[0]?.name || '';
        if (qtyInput) qtyInput.value = barcodePrintItems[0]?.qty || 12;
    }

    loadBarcodePrinters();
    openModal('barcodePrintModal');
    setTimeout(renderBarcodeGrid, 150);
}

function renderBatchItemsTable() {
    const container = document.getElementById('batchItemsTable');
    const countEl = document.getElementById('batchListCount');
    if (!container) return;

    if (countEl) countEl.textContent = barcodePrintItems.length;

    if (barcodePrintItems.length === 0) {
        container.innerHTML = '<div style="padding:15px;text-align:center;color:#94a3b8;font-size:12px;">ບໍ່ມີລາຍການສິນຄ້າ</div>';
        return;
    }

    let html = `
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead style="background:#f1f5f9;border-bottom:1px solid #e2e8f0;position:sticky;top:0;z-index:2;">
            <tr>
                <th style="padding:6px 10px;text-align:left;font-weight:600;color:#475569;">ສິນຄ້າ</th>
                <th style="padding:6px 10px;text-align:left;font-weight:600;color:#475569;width:120px;">ບາໂຄດ</th>
                <th style="padding:6px 10px;text-align:right;font-weight:600;color:#475569;width:95px;">ລາຄາ</th>
                <th style="padding:6px 10px;text-align:center;font-weight:600;color:#475569;width:90px;">ຈຳນວນດວງ</th>
                <th style="padding:6px 8px;text-align:center;width:36px;"></th>
            </tr>
        </thead>
        <tbody>
    `;

    barcodePrintItems.forEach((item, idx) => {
        html += `
        <tr style="border-bottom:1px solid #f1f5f9;">
            <td style="padding:6px 10px;font-weight:600;color:#1e293b;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${item.name}">${item.name}</td>
            <td style="padding:6px 10px;font-family:monospace;color:#64748b;">${item.barcode}</td>
            <td style="padding:6px 10px;text-align:right;color:var(--primary);font-weight:600;">₭ ${item.price}</td>
            <td style="padding:6px 10px;text-align:center;">
                <input type="number" value="${item.qty}" min="1" max="100" 
                    onchange="onBatchItemQtyChange(${idx}, this.value)" 
                    style="width:58px;height:26px;padding:2px 4px;font-size:12px;border:1px solid #cbd5e1;border-radius:4px;text-align:center;font-weight:600;">
            </td>
            <td style="padding:6px 8px;text-align:center;">
                <button type="button" onclick="removeBatchItem(${idx})" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:2px;font-size:12px;" title="ລຶບອອກ">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>
        `;
    });

    html += `</tbody></table>`;
    container.innerHTML = html;
}

function onBatchItemQtyChange(idx, newQty) {
    const qty = parseInt(newQty) || 1;
    if (barcodePrintItems[idx]) {
        barcodePrintItems[idx].qty = Math.max(1, qty);
        renderBarcodeGrid();
    }
}

function removeBatchItem(idx) {
    if (barcodePrintItems[idx]) {
        barcodePrintItems.splice(idx, 1);
        if (barcodePrintItems.length === 0) {
            closeModal('barcodePrintModal');
            showToast('ບໍ່ມີສິນຄ້າເຫຼືອໃນລາຍການ', 'info');
        } else {
            renderBatchItemsTable();
            renderBarcodeGrid();
        }
    }
}

function applyQtyToAllBatch() {
    const qtyInput = document.getElementById('batchApplyAllQty');
    const qty = parseInt(qtyInput ? qtyInput.value : 10) || 10;
    barcodePrintItems.forEach(item => { item.qty = qty; });
    renderBatchItemsTable();
    renderBarcodeGrid();
    showToast(`ກຳນົດຈຳນວນ ${qty} ດວງໃຫ້ທຸກລາຍການແລ້ວ`, 'info');
}

function onSingleQtyChange() {
    const qty = parseInt(document.getElementById('barcodePrintQty').value) || 12;
    if (barcodePrintItems[0]) {
        barcodePrintItems[0].qty = qty;
        renderBarcodeGrid();
    }
}

function renderBarcodeGrid() {
    const template = document.getElementById('barcodeTemplateSize').value;
    const area = document.getElementById('barcodePreviewArea');
    area.innerHTML = '';

    // Determine grid columns based on template
    let cols = 3;
    if (template.endsWith('3col')) cols = 3;
    else if (template.endsWith('2col')) cols = 2;
    else if (template.endsWith('1col')) cols = 1;

    const grid = document.createElement('div');
    grid.style.cssText = `display:grid;grid-template-columns:repeat(${cols},1fr);gap:8px;`;
    grid.id = 'barcodeGridContent';

    // Style parameters based on size
    let padding = '6px 4px';
    let fontSizeName = '0.65rem';
    let fontSizePrice = '0.65rem';
    let barcodeHeight = 35;
    let barcodeWidth = 1.2;
    let barcodeFontSize = 10;
    let cellMaxWidth = 'none';

    if (template === '80mm_roll') {
        cols = 2;
        padding = '6px 4px';
        fontSizeName = '0.72rem';
        fontSizePrice = '0.72rem';
        barcodeHeight = 35;
        barcodeWidth = 1.15;
        barcodeFontSize = 9.5;
        cellMaxWidth = '160px';
    } else if (template === '58mm_roll') {
        cols = 1;
        padding = '6px 4px';
        fontSizeName = '0.72rem';
        fontSizePrice = '0.72rem';
        barcodeHeight = 32;
        barcodeWidth = 1.1;
        barcodeFontSize = 9;
        cellMaxWidth = '200px';
    } else if (template === '40x30_1col') {
        cols = 1;
        padding = '6px 6px';
        fontSizeName = '0.78rem';
        fontSizePrice = '0.8rem';
        barcodeHeight = 34;
        barcodeWidth = 1.25;
        barcodeFontSize = 10;
        cellMaxWidth = '210px';
    } else if (template === '50x30_1col') {
        cols = 1;
        padding = '6px 6px';
        fontSizeName = '0.82rem';
        fontSizePrice = '0.85rem';
        barcodeHeight = 38;
        barcodeWidth = 1.35;
        barcodeFontSize = 10;
        cellMaxWidth = '240px';
    } else if (template === '30x40_1col') {
        cols = 1;
        padding = '6px 6px';
        fontSizeName = '0.78rem';
        fontSizePrice = '0.8rem';
        barcodeHeight = 38;
        barcodeWidth = 1.2;
        barcodeFontSize = 10;
        cellMaxWidth = '180px';
    } else if (template === '30x50_1col') {
        cols = 1;
        padding = '8px 6px';
        fontSizeName = '0.82rem';
        fontSizePrice = '0.85rem';
        barcodeHeight = 46;
        barcodeWidth = 1.25;
        barcodeFontSize = 10;
        cellMaxWidth = '190px';
    } else if (template === '40x50_1col') {
        cols = 1;
        padding = '10px 8px';
        fontSizeName = '0.9rem';
        fontSizePrice = '0.9rem';
        barcodeHeight = 50;
        barcodeWidth = 1.35;
        barcodeFontSize = 11;
        cellMaxWidth = '230px';
    } else if (template === '40x20_1col') {
        cols = 1;
        padding = '4px 4px';
        fontSizeName = '0.68rem';
        fontSizePrice = '0.68rem';
        barcodeHeight = 26;
        barcodeWidth = 1.1;
        barcodeFontSize = 9;
        cellMaxWidth = '190px';
    } else if (template === '40x25_1col') {
        cols = 1;
        padding = '5px 4px';
        fontSizeName = '0.72rem';
        fontSizePrice = '0.72rem';
        barcodeHeight = 28;
        barcodeWidth = 1.15;
        barcodeFontSize = 9.5;
        cellMaxWidth = '200px';
    } else if (template === '30x20_1col') {
        cols = 1;
        padding = '3px 3px';
        fontSizeName = '0.62rem';
        fontSizePrice = '0.62rem';
        barcodeHeight = 22;
        barcodeWidth = 0.95;
        barcodeFontSize = 8.5;
        cellMaxWidth = '160px';
    } else if (template === '50x25_1col') {
        cols = 1;
        padding = '5px 6px';
        fontSizeName = '0.75rem';
        fontSizePrice = '0.75rem';
        barcodeHeight = 30;
        barcodeWidth = 1.3;
        barcodeFontSize = 9.5;
        cellMaxWidth = '230px';
    } else if (template === '60x40_1col') {
        cols = 1;
        padding = '8px 8px';
        fontSizeName = '0.9rem';
        fontSizePrice = '0.9rem';
        barcodeHeight = 44;
        barcodeWidth = 1.5;
        barcodeFontSize = 11;
        cellMaxWidth = '280px';
    } else if (template === '70x50_1col') {
        cols = 1;
        padding = '10px 10px';
        fontSizeName = '1.0rem';
        fontSizePrice = '1.0rem';
        barcodeHeight = 50;
        barcodeWidth = 1.6;
        barcodeFontSize = 11.5;
        cellMaxWidth = '310px';
    } else if (template === '80x50_1col') {
        cols = 1;
        padding = '12px 10px';
        fontSizeName = '1.05rem';
        fontSizePrice = '1.05rem';
        barcodeHeight = 52;
        barcodeWidth = 1.7;
        barcodeFontSize = 12;
        cellMaxWidth = '330px';
    } else if (template === '80x60_1col') {
        cols = 1;
        padding = '15px 12px';
        fontSizeName = '1.1rem';
        fontSizePrice = '1.1rem';
        barcodeHeight = 60;
        barcodeWidth = 1.8;
        barcodeFontSize = 12;
        cellMaxWidth = '340px';
    } else if (template === '40x30_2col') {
        cols = 2;
        padding = '8px 6px';
        fontSizeName = '0.75rem';
        fontSizePrice = '0.75rem';
        barcodeHeight = 40;
        barcodeWidth = 1.3;
        barcodeFontSize = 10;
    } else if (template === '50x30_2col') {
        cols = 2;
        padding = '8px 8px';
        fontSizeName = '0.8rem';
        fontSizePrice = '0.8rem';
        barcodeHeight = 40;
        barcodeWidth = 1.4;
        barcodeFontSize = 10;
    } else {
        // 32x25_3col
        cols = 3;
        padding = '6px 4px';
        fontSizeName = '0.65rem';
        fontSizePrice = '0.65rem';
        barcodeHeight = 35;
        barcodeWidth = 1.2;
        barcodeFontSize = 10;
    }

    let totalStickers = 0;
    let cardIdx = 0;

    barcodePrintItems.forEach(item => {
        const itemQty = parseInt(item.qty) || 0;
        totalStickers += itemQty;
        for (let i = 0; i < itemQty; i++) {
            const cell = document.createElement('div');
            const maxWStyle = cellMaxWidth !== 'none' ? `max-width:${cellMaxWidth};margin:0 auto;width:100%;` : '';
            cell.style.cssText = `border:1px solid #e0e0e0;border-radius:6px;padding:${padding};text-align:center;background:#fff;box-sizing:border-box;${maxWStyle}`;
            cell.innerHTML = `
                <div style="font-size:${fontSizeName};font-weight:700;line-height:1.4;padding-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;" title="${item.name}">${item.name}</div>
                <svg class="barcode-svg" id="bc_card_${cardIdx}"></svg>
                <div style="font-size:${fontSizePrice};color:#666;margin-top:1px;">₭ ${item.price}</div>
            `;
            grid.appendChild(cell);
            cardIdx++;
        }
    });

    area.appendChild(grid);

    // Update badges
    const totalBadge = document.getElementById('barcodeTotalStickersBadge');
    if (totalBadge) totalBadge.textContent = `ລວມ: ${totalStickers} ດວງ`;
    const execBtn = document.getElementById('btnExecuteBarcodePrint');
    if (execBtn) execBtn.innerHTML = `<i class="fas fa-print"></i> ພິມບາໂຄດ (${totalStickers} ດວງ)`;

    // Render barcodes with JsBarcode
    setTimeout(() => {
        let renderIdx = 0;
        barcodePrintItems.forEach(item => {
            const itemQty = parseInt(item.qty) || 0;
            for (let i = 0; i < itemQty; i++) {
                const elId = `#bc_card_${renderIdx}`;
                try {
                    JsBarcode(elId, item.barcode, {
                        format: 'EAN13',
                        width: barcodeWidth,
                        height: barcodeHeight,
                        fontSize: barcodeFontSize,
                        margin: 0,
                        displayValue: true
                    });
                } catch(e) {
                    try {
                        JsBarcode(elId, item.barcode, {
                            format: 'CODE128',
                            width: barcodeWidth,
                            height: barcodeHeight,
                            fontSize: barcodeFontSize,
                            margin: 0,
                            displayValue: true
                        });
                    } catch(err) {
                        console.error('Barcode render error:', err);
                    }
                }
                renderIdx++;
            }
        });
    }, 60);
}

function printBarcodeGrid() {
    const totalStickers = barcodePrintItems.reduce((sum, item) => sum + (parseInt(item.qty) || 0), 0);
    if (totalStickers === 0) {
        showToast('ບໍ່ມີຈຳນວນສະຕິກເກີທີ່ຕ້ອງພິມ', 'warning');
        return;
    }

    const gridContent = document.getElementById('barcodeGridContent');
    if (!gridContent) return;

    const template = document.getElementById('barcodeTemplateSize').value;
    const orientation = document.getElementById('barcodeOrientation')?.value || 'portrait';

    let cols = 3;
    if (template.endsWith('3col')) cols = 3;
    else if (template.endsWith('2col')) cols = 2;
    else if (template.endsWith('1col')) cols = 1;

    let cellPadding = '4px 2px';
    let fontSizeName = '7pt';
    let fontSizePrice = '7pt';
    let labelWidth = '';
    let labelHeight = '';
    let printBarcodeHeight = 35;

    let pageCss = `@page { size: ${orientation}; margin: 0; }`;
    let gridCss = `display: grid; grid-template-columns: repeat(${cols}, 1fr); gap: 4px; padding: 4px;`;
    let itemExtraCss = ``;

    if (template === '80mm_roll') {
        cellPadding = '4px 4px';
        fontSizeName = '7.5pt';
        fontSizePrice = '7.5pt';
        printBarcodeHeight = 35;
        pageCss = '@page { size: portrait; margin: 2mm 3mm; }';
        gridCss = 'display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px; padding: 2px; width: 64mm; margin: 0 auto;';
        itemExtraCss = `page-break-inside: avoid; break-inside: avoid; margin-bottom: 4px; border: 1px dashed #888; border-radius: 4px; width: 100%;`;
    } else if (template === '58mm_roll') {
        cellPadding = '4px 4px';
        fontSizeName = '7.5pt';
        fontSizePrice = '7.5pt';
        printBarcodeHeight = 30;
        pageCss = '@page { size: portrait; margin: 2mm 2mm; }';
        gridCss = 'display: block; padding: 2px; width: 44mm; margin: 0 auto;';
        itemExtraCss = `page-break-inside: avoid; break-inside: avoid; margin-bottom: 4px; border: 1px dashed #888; border-radius: 4px; width: 100%;`;
    } else if (template === '40x30_1col') {
        cellPadding = '1.5mm 2mm';
        fontSizeName = '8.5pt';
        fontSizePrice = '9pt';
        labelWidth = '40mm';
        labelHeight = '30mm';
        printBarcodeHeight = 34;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '50x30_1col') {
        cellPadding = '2mm 3mm';
        fontSizeName = '9pt';
        fontSizePrice = '9.5pt';
        labelWidth = '50mm';
        labelHeight = '30mm';
        printBarcodeHeight = 38;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '30x40_1col') {
        cellPadding = '2mm 2mm';
        fontSizeName = '8pt';
        fontSizePrice = '8.5pt';
        labelWidth = '30mm';
        labelHeight = '40mm';
        printBarcodeHeight = 40;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '30x50_1col') {
        cellPadding = '2.5mm 2mm';
        fontSizeName = '8.5pt';
        fontSizePrice = '9pt';
        labelWidth = '30mm';
        labelHeight = '50mm';
        printBarcodeHeight = 46;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '40x50_1col') {
        cellPadding = '3mm 2.5mm';
        fontSizeName = '9pt';
        fontSizePrice = '9.5pt';
        labelWidth = '40mm';
        labelHeight = '50mm';
        printBarcodeHeight = 50;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '40x20_1col') {
        cellPadding = '1mm 1.5mm';
        fontSizeName = '7pt';
        fontSizePrice = '7.5pt';
        labelWidth = '40mm';
        labelHeight = '20mm';
        printBarcodeHeight = 24;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '40x25_1col') {
        cellPadding = '1.5mm 2mm';
        fontSizeName = '7.5pt';
        fontSizePrice = '8pt';
        labelWidth = '40mm';
        labelHeight = '25mm';
        printBarcodeHeight = 28;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '30x20_1col') {
        cellPadding = '1mm 1mm';
        fontSizeName = '6.5pt';
        fontSizePrice = '6.5pt';
        labelWidth = '30mm';
        labelHeight = '20mm';
        printBarcodeHeight = 22;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '50x25_1col') {
        cellPadding = '1.5mm 2mm';
        fontSizeName = '8pt';
        fontSizePrice = '8.5pt';
        labelWidth = '50mm';
        labelHeight = '25mm';
        printBarcodeHeight = 30;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '60x40_1col') {
        cellPadding = '2.5mm 3mm';
        fontSizeName = '9.5pt';
        fontSizePrice = '10pt';
        labelWidth = '60mm';
        labelHeight = '40mm';
        printBarcodeHeight = 44;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '70x50_1col') {
        cellPadding = '3mm 4mm';
        fontSizeName = '10.5pt';
        fontSizePrice = '11pt';
        labelWidth = '70mm';
        labelHeight = '50mm';
        printBarcodeHeight = 52;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '80x50_1col') {
        cellPadding = '3mm 4mm';
        fontSizeName = '11pt';
        fontSizePrice = '11.5pt';
        labelWidth = '80mm';
        labelHeight = '50mm';
        printBarcodeHeight = 54;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '80x60_1col') {
        cellPadding = '4mm 4mm';
        fontSizeName = '12pt';
        fontSizePrice = '12pt';
        labelWidth = '80mm';
        labelHeight = '60mm';
        printBarcodeHeight = 60;
        gridCss = `display: block; margin: 0; padding: 0;`;
        itemExtraCss = `width: 100%; height: 100%; box-sizing: border-box; overflow: hidden; border: none;`;
    } else if (template === '40x30_2col') {
        cellPadding = '2px 4px';
        fontSizeName = '7.5pt';
        fontSizePrice = '7.5pt';
        labelWidth = '84mm';
        labelHeight = '30mm';
        printBarcodeHeight = 35;
        gridCss = `display: grid; grid-template-columns: repeat(2, 1fr); gap: 2mm; width: 82mm; height: ${labelHeight}; margin: 0 auto; padding: 1mm 0; box-sizing: border-box;`;
        itemExtraCss = `width: 40mm; height: 28mm; max-width: 40mm; max-height: 28mm; box-sizing: border-box; overflow: hidden; page-break-inside: avoid; break-inside: avoid; border: none;`;
    } else if (template === '50x30_2col') {
        cellPadding = '2px 4px';
        fontSizeName = '8pt';
        fontSizePrice = '8pt';
        labelWidth = '104mm';
        labelHeight = '30mm';
        printBarcodeHeight = 38;
        gridCss = `display: grid; grid-template-columns: repeat(2, 1fr); gap: 2mm; width: 102mm; height: ${labelHeight}; margin: 0 auto; padding: 1mm 0; box-sizing: border-box;`;
        itemExtraCss = `width: 50mm; height: 28mm; max-width: 50mm; max-height: 28mm; box-sizing: border-box; overflow: hidden; page-break-inside: avoid; break-inside: avoid; border: none;`;
    } else { // 32x25_3col (Standard 3-column label roll)
        cellPadding = '2px 2px';
        fontSizeName = '6.5pt';
        fontSizePrice = '6.5pt';
        labelWidth = '104mm';
        labelHeight = '25mm';
        printBarcodeHeight = 28;
        gridCss = `display: grid; grid-template-columns: repeat(3, 1fr); gap: 2mm; width: 100mm; height: ${labelHeight}; margin: 0 auto; padding: 0.5mm 0; box-sizing: border-box;`;
        itemExtraCss = `width: 32mm; height: 24mm; max-width: 32mm; max-height: 24mm; box-sizing: border-box; overflow: hidden; page-break-inside: avoid; break-inside: avoid; border: none;`;
    }

    let rowWidth = '100mm';
    if (template === '40x30_2col') rowWidth = '82mm';
    else if (template === '50x30_2col') rowWidth = '102mm';
    else if (template === '32x25_3col') rowWidth = '100mm';

    // Calculate exact width and height based on template
    let pw = labelWidth || '40mm';
    let ph = labelHeight || '30mm';

    if (template.endsWith('1col')) {
        if (orientation === 'landscape') {
            pageCss = `@page { size: landscape; margin: 0; }`;
        } else {
            pageCss = `@page { size: portrait; margin: 0; }`;
        }
    } else if (template === '80mm_roll' || template === '58mm_roll') {
        pageCss = `@page { size: portrait; margin: 2mm 3mm; }`;
    } else {
        pageCss = `@page { size: ${rowWidth} ${labelHeight}; margin: 0; }`;
    }

    let bodyHtml = '';
    const cells = Array.from(gridContent.children);

    if (template.endsWith('3col')) {
        const rows = [];
        for (let i = 0; i < cells.length; i += 3) {
            rows.push(cells.slice(i, i + 3));
        }
        bodyHtml = rows.map((rowCells, rIdx) => {
            const isLast = rIdx === rows.length - 1;
            let rowInner = rowCells.map(c => `
                <div class="label-item">
                    ${c.innerHTML}
                </div>
            `).join('');
            for (let k = rowCells.length; k < 3; k++) {
                rowInner += `<div class="label-item" style="visibility:hidden;border:none !important;"></div>`;
            }
            return `<div class="label-row ${isLast ? 'last-row' : ''}">${rowInner}</div>`;
        }).join('');
    } else if (template.endsWith('2col')) {
        const rows = [];
        for (let i = 0; i < cells.length; i += 2) {
            rows.push(cells.slice(i, i + 2));
        }
        bodyHtml = rows.map((rowCells, rIdx) => {
            const isLast = rIdx === rows.length - 1;
            let rowInner = rowCells.map(c => `
                <div class="label-item">
                    ${c.innerHTML}
                </div>
            `).join('');
            for (let k = rowCells.length; k < 2; k++) {
                rowInner += `<div class="label-item" style="visibility:hidden;border:none !important;"></div>`;
            }
            return `<div class="label-row ${isLast ? 'last-row' : ''}">${rowInner}</div>`;
        }).join('');
    } else if (template.endsWith('1col')) {
        bodyHtml = cells.map((c, idx) => {
            const isLast = idx === cells.length - 1;
            return `<div class="label-single-row ${isLast ? 'last-row' : ''}"><div class="label-item">${c.innerHTML}</div></div>`;
        }).join('');
    } else {
        // Continuous thermal roll: 80mm_roll, 58mm_roll
        bodyHtml = `<div class="continuous-grid">${cells.map(c => `<div class="label-item continuous-item">${c.innerHTML}</div>`).join('')}</div>`;
    }

    const titleText = isBatchBarcodeMode ? `ພິມບາໂຄດ (${barcodePrintItems.length} ລາຍການ)` : `ພິມບາໂຄດ - ${barcodePrintItems[0]?.name || ''}`;

    const fullHtml = `<!DOCTYPE html>
        <html><head><title>${titleText}</title>
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fonts/noto-sans-lao.css">
        <style>
            ${pageCss}
            * { box-sizing: border-box; margin: 0; padding: 0; }
            html, body {
                margin: 0;
                padding: 0;
                font-family: 'Noto Sans Lao', Arial, sans-serif;
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @media print {
                html, body {
                    width: 100% !important;
                    height: auto !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #fff;
                }
            }
            .label-row {
                display: grid;
                grid-template-columns: repeat(${cols}, 1fr);
                gap: 2mm;
                width: ${rowWidth};
                height: ${labelHeight};
                max-height: ${labelHeight};
                margin: 0 auto;
                padding: 0.5mm 0;
                box-sizing: border-box;
                page-break-after: always;
                break-after: page;
                page-break-inside: avoid;
                break-inside: avoid;
                overflow: hidden;
            }
            .label-row.last-row {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
            .label-single-row {
                width: ${pw} !important;
                height: ${ph} !important;
                max-width: ${pw} !important;
                max-height: ${ph} !important;
                margin: 0 auto;
                box-sizing: border-box;
                page-break-after: always;
                break-after: page;
                page-break-inside: avoid;
                break-inside: avoid;
                overflow: hidden;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
            }
            .label-single-row.last-row {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
            .label-single-row .label-item {
                width: 100% !important;
                height: 100% !important;
                max-width: ${pw} !important;
                max-height: ${ph} !important;
                margin: 0 auto !important;
                border: none !important;
                box-sizing: border-box;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                gap: 1.5mm !important;
                padding: ${cellPadding} !important;
                overflow: hidden;
            }
            .continuous-grid {
                ${gridCss}
            }
            .label-item {
                border: none;
                padding: ${cellPadding};
                text-align: center;
                page-break-inside: avoid;
                break-inside: avoid;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                background: #fff;
                overflow: hidden;
                ${itemExtraCss}
            }
            .continuous-item {
                border: 1px dashed #ccc;
                border-radius: 4px;
                margin-bottom: 4px;
            }
            .label-item > div:first-child {
                font-size: ${fontSizeName} !important;
                line-height: 1.2;
                font-weight: 700;
                overflow: hidden;
                white-space: normal;
                word-break: break-word;
                max-width: 100%;
                color: #000;
                text-align: center;
            }
            .label-item > div:last-child {
                font-size: ${fontSizePrice} !important;
                line-height: 1.15;
                font-weight: 800;
                color: #000;
                text-align: center;
            }
            svg { display: block !important; margin: 0 auto !important; max-width: 95% !important; height: ${printBarcodeHeight}px !important; }
        </style>
        </head><body>
        ${bodyHtml}
        </body></html>`;

    // In Desktop App, use silent print directly if available to completely bypass Windows print dialog
    const barcodePrinter = localStorage.getItem('pos_barcode_printer') || '';
    if (window.api && typeof window.api.printReceiptSilent === 'function') {
        showToast(`ກຳລັງສັ່ງພິມບາໂຄດ ${barcodePrintItems.length} ລາຍການ (${totalStickers} ດວງ)...`, 'info');
        closeModal('barcodePrintModal');
        const pwNum = parseInt(pw) || 40;
        const phNum = parseInt(ph) || 30;
        window.api.printReceiptSilent({
            html: fullHtml,
            deviceName: barcodePrinter,
            landscape: (orientation === 'landscape'),
            pageSize: { width: pwNum * 1000, height: phNum * 1000 }
        }).then(res => {
            if (res && res.success) {
                showToast(`ພິມບາໂຄດສຳເລັດ`, 'success');
            }
        });
        return;
    }

    // Open print window directly (for Web App or fallback)
    const printWindow = window.open('', '_blank', 'width=850,height=850');
    if (printWindow) {
        printWindow.document.write(fullHtml + `
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        window.onafterprint = function() { window.close(); };
                    }, 350);
                };
            <\/script>
        `);
        printWindow.document.close();
        closeModal('barcodePrintModal');
        showToast(`ກຳລັງເປີດພິມບາໂຄດ ${barcodePrintItems.length} ລາຍການ (${totalStickers} ດວງ)...`, 'info');
        return;
    }
    printWindow.document.write(fullHtml + `
        <script>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                    window.onafterprint = function() { window.close(); };
                }, 350);
            };
        <\/script>
    `);
    printWindow.document.close();
}

// ============================================
// Excel / CSV Import & Export Module
// ============================================

let parsedImportProducts = [];
let rawImportWorkbook = null;

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openImportExcelModal() {
    resetImportStep();
    openModal('importExcelModal');
}

function resetImportStep() {
    parsedImportProducts = [];
    rawImportWorkbook = null;
    const fileInput = document.getElementById('excelFileInput');
    if (fileInput) fileInput.value = '';
    
    const step1 = document.getElementById('importStep1');
    const step2 = document.getElementById('importStep2');
    const pBar = document.getElementById('importProgressBarWrapper');
    if (step1) step1.style.display = 'block';
    if (step2) step2.style.display = 'none';
    if (pBar) pBar.style.display = 'none';
    
    const confirmBtn = document.getElementById('btnConfirmImport');
    if (confirmBtn) {
        confirmBtn.style.display = 'none';
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-check-circle"></i> ຢືນຢັນນຳເຂົ້າ (<span id="importConfirmCountBadge">0</span>)';
    }
}

// Download Sample Template (.xlsx or .csv)
function downloadExcelTemplate(type = 'xlsx') {
    if (type === 'csv') {
        window.location.href = `${BASE_URL}api/products_export.php?action=template`;
        return;
    }
    
    if (typeof XLSX === 'undefined') {
        showToast('ກຳລັງໂຫຼດລະບົບ Excel... ກະລຸນາລອງໃໝ່ອີກຄັ້ງ', 'warning');
        return;
    }
    
    const headers = [
        'ບາໂຄດ',
        'ຊື່ສິນຄ້າ *',
        'ໝວດໝູ່',
        'ລາຄາທຶນ',
        'ລາຄາຂາຍ *',
        'ຈຳນວນສະຕ໋ອກ',
        'ຂັ້ນຕ່ຳສະຕ໋ອກ',
        'ຫົວໜ່ວຍ',
        'ວັນໝົດອາຍຸ (YYYY-MM-DD)'
    ];
    
    const sampleData = [
        ['8850000000001', 'ພາຣາເຊຕາໂມນ 500mg (Paracetamol)', 'ຢາແກ້ປວດ', 15000, 20000, 100, 10, 'ແຜງ', '2027-12-31'],
        ['8850000000002', 'ໄອບູໂພຣເຟັນ 400mg (Ibuprofen)', 'ຢາແກ້ປວດ', 20000, 30000, 80, 10, 'ແຜງ', '2027-10-15'],
        ['8850000000003', 'ອາໂມຊີຊິລິນ 500mg (Amoxicillin)', 'ຢາຕ້ານເຊື້ອ', 25000, 40000, 50, 5, 'ແຜງ', '2027-08-20'],
        ['8850000000004', 'ວິຕາມິນ C 1000mg', 'ວິຕາມິນ', 30000, 45000, 40, 5, 'ກ່ອງ', '2028-01-30'],
        ['8850000000005', 'ຢາແກ້ໄອ Dextromethorphan', 'ຢາແກ້ໄອ', 10000, 15000, 20, 5, 'ຂວດ', '2027-05-10'],
    ];
    
    const ws = XLSX.utils.aoa_to_sheet([headers, ...sampleData]);
    ws['!cols'] = [
        { wch: 18 }, // Barcode
        { wch: 38 }, // Name
        { wch: 18 }, // Category
        { wch: 14 }, // Cost
        { wch: 14 }, // Selling
        { wch: 15 }, // Stock
        { wch: 14 }, // Min stock
        { wch: 12 }, // Unit
        { wch: 22 }, // Expiry
    ];
    
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'ແມ່ແບບນຳເຂົ້າຢາ');
    XLSX.writeFile(wb, 'template_import_products.xlsx');
    showToast('ດາວໂຫຼດແມ່ແບບ Excel (.xlsx) ສຳເລັດແລ້ວ', 'success');
}

// Export All Products to Excel (.xlsx)
async function exportProductsToExcel() {
    if (typeof XLSX === 'undefined') {
        window.location.href = `${BASE_URL}api/products_export.php?format=csv`;
        return;
    }
    
    try {
        showToast('ກຳລັງດຶງຂໍ້ມູນສິນຄ້າເພື່ອສ້າງໄຟລ໌ Excel...', 'info');
        
        const res = await fetchAPI(`${BASE_URL}api/products_export.php?format=json`);
        if (!res || !res.success || !res.products) {
            throw new Error(res?.message || 'ບໍ່ສາມາດໂຫຼດຂໍ້ມູນສິນຄ້າໄດ້');
        }
        
        const products = res.products;
        if (products.length === 0) {
            showToast('ບໍ່ມີຂໍ້ມູນສິນຄ້າໃນລະບົບໃຫ້ສົ່ງອອກ', 'warning');
            return;
        }
        
        const headers = [
            'ບາໂຄດ',
            'ຊື່ສິນຄ້າ / ຊື່ຢາ',
            'ໝວດໝູ່',
            'ລາຄາທຶນ',
            'ລາຄາຂາຍ',
            'ຈຳນວນສະຕ໋ອກ',
            'ຂັ້ນຕ່ຳສະຕ໋ອກ',
            'ຫົວໜ່ວຍ',
            'ວັນໝົດອາຍຸ'
        ];
        
        const rows = [headers];
        
        products.forEach(p => {
            let barcodeVal = p.barcode ? String(p.barcode).trim() : '';
            rows.push([
                barcodeVal,
                p.name || '',
                p.category_name || 'ອື່ນໆ',
                parseFloat(p.cost_price || 0),
                parseFloat(p.selling_price || 0),
                parseInt(p.stock_quantity || 0, 10),
                parseInt(p.min_stock || 5, 10),
                p.unit || 'ແຜງ',
                p.expiry_date || ''
            ]);
        });
        
        const ws = XLSX.utils.aoa_to_sheet(rows);
        ws['!cols'] = [
            { wch: 18 }, // Barcode
            { wch: 34 }, // Name
            { wch: 18 }, // Category
            { wch: 14 }, // Cost
            { wch: 14 }, // Selling
            { wch: 14 }, // Stock
            { wch: 12 }, // Min
            { wch: 12 }, // Unit
            { wch: 14 }, // Expiry
        ];
        
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'ລາຍການຢາ');
        
        const today = new Date().toISOString().split('T')[0];
        const filename = `pospharma_products_${today}.xlsx`;
        
        XLSX.writeFile(wb, filename);
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'ສົ່ງອອກສຳເລັດ!',
                text: `ສົ່ງອອກລາຍການຢາທັງໝົດ ${products.length} ລາຍການ ເປັນໄຟລ໌ ${filename} ຮຽບຮ້ອຍແລ້ວ.`,
                confirmButtonColor: 'var(--primary)',
                confirmButtonText: 'ຕົກລົງ'
            });
        } else {
            showToast(`ສົ່ງອອກ ${products.length} ລາຍການສຳເລັດ`, 'success');
        }
    } catch (err) {
        console.error('Export error:', err);
        showToast('ການສົ່ງອອກລົ້ມເຫຼວ: ' + err.message, 'error');
    }
}

// Helpers for Excel Data Parsing
function cleanExcelBarcode(val) {
    if (val === null || val === undefined || val === '') return '';
    if (typeof val === 'number') {
        return BigInt(Math.floor(val)).toString();
    }
    let str = String(val).trim();
    if (/^[0-9]+(\.[0-9]+)?e\+[0-9]+$/i.test(str)) {
        try { str = BigInt(Math.floor(Number(str))).toString(); } catch (e) {}
    }
    return str;
}

function cleanExcelNumber(val, defaultVal = 0) {
    if (val === null || val === undefined || val === '') return defaultVal;
    if (typeof val === 'number') return isNaN(val) ? defaultVal : val;
    const cleanStr = String(val).replace(/[^0-9.-]/g, '');
    const num = parseFloat(cleanStr);
    return isNaN(num) ? defaultVal : num;
}

function formatExcelDate(val) {
    if (!val) return '';
    if (val instanceof Date && !isNaN(val.getTime())) {
        const y = val.getFullYear();
        const m = String(val.getMonth() + 1).padStart(2, '0');
        const d = String(val.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    if (typeof val === 'number' && val > 20000 && val < 90000) {
        try {
            if (typeof XLSX !== 'undefined' && XLSX.SSF) {
                const dateInfo = XLSX.SSF.parse_date_code(val);
                if (dateInfo) {
                    return `${dateInfo.y}-${String(dateInfo.m).padStart(2, '0')}-${String(dateInfo.d).padStart(2, '0')}`;
                }
            }
        } catch (e) {}
    }
    const str = String(val).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(str)) return str;
    const m1 = str.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (m1) return `${m1[3]}-${String(m1[2]).padStart(2, '0')}-${String(m1[1]).padStart(2, '0')}`;
    const m2 = str.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/);
    if (m2) return `${m2[1]}-${String(m2[2]).padStart(2, '0')}-${String(m2[3]).padStart(2, '0')}`;
    return str;
}

function normalizeHeaderKey(str) {
    return String(str || '').toLowerCase().trim().replace(/[\s\_\-\(\)\*\/]/g, '');
}

function detectColumnMapping(headers) {
    const mapping = {
        barcode: -1,
        name: -1,
        category: -1,
        cost_price: -1,
        selling_price: -1,
        stock_quantity: -1,
        min_stock: -1,
        unit: -1,
        expiry_date: -1
    };

    headers.forEach((h, idx) => {
        const norm = normalizeHeaderKey(h);
        if (mapping.barcode === -1 && (norm.includes('ບາໂຄດ') || norm.includes('barcode') || norm === 'code' || norm.includes('ລະຫັດ'))) {
            mapping.barcode = idx;
        } else if (mapping.name === -1 && (norm.includes('ຊື່ສິນຄ້າ') || norm.includes('ຊື່ຢາ') || norm.includes('ລາຍການ') || norm.includes('product') || norm === 'name' || norm.includes('ຊື່'))) {
            mapping.name = idx;
        } else if (mapping.category === -1 && (norm.includes('ໝວດໝູ່') || norm.includes('ໝວດ') || norm.includes('category') || norm.includes('ປະເພດ'))) {
            mapping.category = idx;
        } else if (mapping.cost_price === -1 && (norm.includes('ລາຄາທຶນ') || norm.includes('ຕົ້ນທຶນ') || norm.includes('cost') || norm.includes('ລາຄາຊື້') || norm.includes('buyingprice'))) {
            mapping.cost_price = idx;
        } else if (mapping.selling_price === -1 && (norm.includes('ລາຄາຂາຍ') || norm.includes('sellingprice') || norm.includes('saleprice') || norm === 'ລາຄາ' || norm === 'price')) {
            mapping.selling_price = idx;
        } else if (mapping.stock_quantity === -1 && (norm.includes('ຈຳນວນສະຕ໋ອກ') || norm.includes('ສະຕ໋ອກ') || norm.includes('stock') || norm.includes('qty') || norm.includes('quantity') || norm.includes('ຈຳນວນ'))) {
            mapping.stock_quantity = idx;
        } else if (mapping.min_stock === -1 && (norm.includes('ຂັ້ນຕ່ຳ') || norm.includes('minstock') || norm.includes('minimum') || norm === 'min')) {
            mapping.min_stock = idx;
        } else if (mapping.unit === -1 && (norm.includes('ຫົວໜ່ວຍ') || norm.includes('ໜ່ວຍ') || norm.includes('unit'))) {
            mapping.unit = idx;
        } else if (mapping.expiry_date === -1 && (norm.includes('ວັນໝົດອາຍຸ') || norm.includes('ໝົດອາຍຸ') || norm.includes('expiry') || norm.includes('exp') || norm.includes('expdate'))) {
            mapping.expiry_date = idx;
        }
    });

    if (mapping.name === -1 && headers.length >= 2) {
        mapping.barcode = 0;
        mapping.name = 1;
        mapping.category = 2;
        mapping.cost_price = 3;
        mapping.selling_price = 4;
        mapping.stock_quantity = 5;
        mapping.min_stock = 6;
        mapping.unit = 7;
        mapping.expiry_date = 8;
    }

    return mapping;
}

// File Selection & Drag & Drop Handler
function handleExcelFileSelect(input) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    processSelectedExcelFile(file);
}

function processSelectedExcelFile(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['xlsx', 'xls', 'csv'].includes(ext)) {
        showToast('ກະລຸນາເລືອກໄຟລ໌ .xlsx, .xls ຫຼື .csv ເທົ່ານັ້ນ', 'warning');
        return;
    }
    
    if (file.size > 15 * 1024 * 1024) {
        showToast('ຂະໜາດໄຟລ໌ສູງເກີນ 15MB', 'warning');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array', cellDates: true });
            
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const rawRows = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });
            
            if (!rawRows || rawRows.length < 2) {
                showToast('ໄຟລ໌ບໍ່ມີຂໍ້ມູນ ຫຼື ບໍ່ມີແຖວຂໍ້ມູນສິນຄ້າ', 'warning');
                return;
            }

            const headerRow = rawRows[0];
            const colMap = detectColumnMapping(headerRow);
            
            if (colMap.name === -1) {
                showToast('ບໍ່ສາມາດກວດພົບຖັນ "ຊື່ສິນຄ້າ" ໃນໄຟລ໌. ກະລຸນາກວດສອບຫົວຖັນ ຫຼື ດາວໂຫຼດແມ່ແບບ', 'error');
                return;
            }

            parsedImportProducts = [];
            let validCount = 0;

            for (let i = 1; i < rawRows.length; i++) {
                const row = rawRows[i];
                if (!row || row.length === 0 || row.every(c => c === '')) continue;
                
                const rawName = colMap.name >= 0 ? row[colMap.name] : '';
                const name = String(rawName || '').trim();
                
                const rawBarcode = colMap.barcode >= 0 ? row[colMap.barcode] : '';
                const barcode = cleanExcelBarcode(rawBarcode);
                
                const rawCategory = colMap.category >= 0 ? row[colMap.category] : '';
                const category = String(rawCategory || '').trim();
                
                const rawCost = colMap.cost_price >= 0 ? row[colMap.cost_price] : 0;
                const costPrice = cleanExcelNumber(rawCost, 0);
                
                const rawSelling = colMap.selling_price >= 0 ? row[colMap.selling_price] : 0;
                const sellingPrice = cleanExcelNumber(rawSelling, 0);
                
                const rawStock = colMap.stock_quantity >= 0 ? row[colMap.stock_quantity] : 0;
                const stockQty = Math.floor(cleanExcelNumber(rawStock, 0));
                
                const rawMinStock = colMap.min_stock >= 0 ? row[colMap.min_stock] : 5;
                const minStock = Math.floor(cleanExcelNumber(rawMinStock, 5));
                
                const rawUnit = colMap.unit >= 0 ? row[colMap.unit] : '';
                const unit = String(rawUnit || '').trim() || 'ແຜງ';
                
                const rawExp = colMap.expiry_date >= 0 ? row[colMap.expiry_date] : '';
                const expiryDate = formatExcelDate(rawExp);

                const isValid = (name.length > 0 && sellingPrice >= 0);
                if (isValid) validCount++;

                parsedImportProducts.push({
                    barcode: barcode,
                    name: name,
                    category: category,
                    cost_price: costPrice,
                    selling_price: sellingPrice,
                    stock_quantity: stockQty,
                    min_stock: minStock,
                    unit: unit,
                    expiry_date: expiryDate,
                    isValid: isValid
                });
            }

            if (parsedImportProducts.length === 0) {
                showToast('ບໍ່ພົບແຖວຂໍ້ມູນສິນຄ້າໃນໄຟລ໌', 'warning');
                return;
            }

            // Render Step 2 Preview
            renderImportPreview(file, parsedImportProducts, validCount);

        } catch (err) {
            console.error('File parsing error:', err);
            showToast('ບໍ່ສາມາດອ່ານໄຟລ໌ Excel ໄດ້: ' + err.message, 'error');
        }
    };
    reader.readAsArrayBuffer(file);
}

function renderImportPreview(file, products, validCount) {
    document.getElementById('importStep1').style.display = 'none';
    document.getElementById('importStep2').style.display = 'block';

    document.getElementById('importFileName').textContent = file.name;
    document.getElementById('importFileSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
    document.getElementById('importTotalRows').textContent = products.length;
    document.getElementById('importValidRows').textContent = validCount;

    const tbody = document.getElementById('importPreviewTbody');
    tbody.innerHTML = '';

    // Render up to 100 rows in preview for snappy UI
    const previewList = products.slice(0, 100);

    previewList.forEach((p, idx) => {
        const tr = document.createElement('tr');
        if (!p.isValid) {
            tr.style.background = '#fff1f2';
        }
        
        tr.innerHTML = `
            <td style="text-align:center;color:#64748b;">${idx + 1}</td>
            <td><code>${p.barcode || '-'}</code></td>
            <td><strong>${escapeHtml(p.name || '(ບໍ່ມີຊື່)')}</strong></td>
            <td><span class="badge badge-success">${escapeHtml(p.category || 'ອື່ນໆ')}</span></td>
            <td>${formatCurrency(p.cost_price)}</td>
            <td class="text-primary fw-bold">${formatCurrency(p.selling_price)}</td>
            <td>${p.stock_quantity}</td>
            <td>${escapeHtml(p.unit)}</td>
            <td>${p.expiry_date || '-'}</td>
            <td>
                ${p.isValid 
                    ? '<span class="badge badge-success" style="font-size:11px;"><i class="fas fa-check"></i> ພ້ອມ</span>' 
                    : '<span class="badge badge-danger" style="font-size:11px;"><i class="fas fa-times"></i> ບໍ່ສົມບູນ</span>'}
            </td>
        `;
        tbody.appendChild(tr);
    });

    if (products.length > 100) {
        const trMore = document.createElement('tr');
        trMore.innerHTML = `
            <td colspan="10" style="text-align:center;padding:10px;background:#f8fafc;color:#64748b;font-style:italic;">
                ... ແລະ ອີກ ${products.length - 100} ລາຍການ (ຈະຖືກນຳເຂົ້າທັງໝົດ) ...
            </td>
        `;
        tbody.appendChild(trMore);
    }

    const confirmBtn = document.getElementById('btnConfirmImport');
    const badge = document.getElementById('importConfirmCountBadge');
    if (confirmBtn && badge) {
        badge.textContent = validCount;
        confirmBtn.style.display = 'inline-flex';
        confirmBtn.disabled = (validCount === 0);
    }
}

// Execute Import Action
async function executeExcelImport() {
    if (!parsedImportProducts || parsedImportProducts.length === 0) {
        showToast('ບໍ່ມີຂໍ້ມູນໃຫ້ນຳເຂົ້າ', 'warning');
        return;
    }

    const validProducts = parsedImportProducts.filter(p => p.isValid);
    if (validProducts.length === 0) {
        showToast('ບໍ່ມີລາຍການສິນຄ້າທີ່ສົມບູນພໍໃຫ້ນຳເຂົ້າ (ກະລຸນາກວດສອບຊື່ສິນຄ້າ)', 'warning');
        return;
    }

    const duplicateAction = document.querySelector('input[name="duplicateAction"]:checked')?.value || 'update';
    const autoCat = document.getElementById('importAutoCat')?.checked ?? true;
    const autoUnit = document.getElementById('importAutoUnit')?.checked ?? true;

    const confirmBtn = document.getElementById('btnConfirmImport');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງນຳເຂົ້າ...';
    }

    const progressWrapper = document.getElementById('importProgressBarWrapper');
    const progressBar = document.getElementById('importProgressBar');
    const progressPercent = document.getElementById('importProgressPercent');
    const progressStatus = document.getElementById('importProgressStatus');
    
    if (progressWrapper) progressWrapper.style.display = 'block';
    if (progressBar) progressBar.style.width = '30%';
    if (progressPercent) progressPercent.textContent = '30%';
    if (progressStatus) progressStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງປະມວນຜົນ ແລະ ບັນທຶກລົງຖານຂໍ້ມູນ...';

    try {
        const payload = {
            duplicate_action: duplicateAction,
            auto_create_categories: autoCat,
            auto_create_units: autoUnit,
            products: validProducts
        };

        const res = await fetchAPI(`${BASE_URL}api/products_import.php`, {
            method: 'POST',
            body: JSON.stringify(payload)
        });

        if (progressBar) progressBar.style.width = '100%';
        if (progressPercent) progressPercent.textContent = '100%';

        if (res && res.success) {
            const stats = res.stats || {};
            const created = stats.created || 0;
            const updated = stats.updated || 0;
            const skipped = stats.skipped || 0;
            const total = stats.total || validProducts.length;

            closeModal('importExcelModal');

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'ນຳເຂົ້າຂໍ້ມູນສິນຄ້າສຳເລັດ!',
                    html: `
                        <div style="text-align:left;font-size:0.95rem;background:#f8fafc;padding:14px;border-radius:10px;border:1px solid #e2e8f0;margin-top:10px;">
                            <div style="margin-bottom:6px;"><i class="fas fa-boxes" style="color:var(--primary);margin-right:8px;"></i><strong>ລວມທັງໝົດ:</strong> ${total} ລາຍການ</div>
                            <div style="margin-bottom:6px;color:#16a34a;"><i class="fas fa-plus-circle" style="margin-right:8px;"></i><strong>ເພີ່ມສິນຄ້າໃໝ່:</strong> ${created} ລາຍການ</div>
                            <div style="margin-bottom:6px;color:#2563eb;"><i class="fas fa-sync-alt" style="margin-right:8px;"></i><strong>ອັບເດດສິນຄ້າເດີມ:</strong> ${updated} ລາຍການ</div>
                            ${skipped > 0 ? `<div style="color:#d97706;"><i class="fas fa-forward" style="margin-right:8px;"></i><strong>ຂ້າມ:</strong> ${skipped} ລາຍການ</div>` : ''}
                        </div>
                    `,
                    confirmButtonColor: 'var(--primary)',
                    confirmButtonText: 'ຕົກລົງ'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                showToast(res.message, 'success');
                setTimeout(() => window.location.reload(), 1500);
            }
        } else {
            throw new Error(res?.message || 'ການນຳເຂົ້າລົ້ມເຫຼວ');
        }
    } catch (err) {
        console.error('Import execution error:', err);
        if (progressWrapper) progressWrapper.style.display = 'none';
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check-circle"></i> ຢືນຢັນນຳເຂົ້າ (<span id="importConfirmCountBadge">' + validProducts.length + '</span>)';
        }
        showToast('ເກີດຂໍ້ຜິດພາດ: ' + err.message, 'error');
    }
}

// Drag and drop setup for Dropzone
function setupExcelDropZone() {
    const dropZone = document.getElementById('excelDropZone');
    if (!dropZone) return;

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.style.borderColor = 'var(--primary)';
            dropZone.style.background = '#f0fdf4';
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.style.borderColor = '#cbd5e1';
            dropZone.style.background = '#fafafa';
        }, false);
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            processSelectedExcelFile(files[0]);
        }
    }, false);
}

// Product View Mode (Table vs Card)
function setProductViewMode(mode) {
    const tableContainer = document.getElementById('productTableViewContainer');
    const cardView = document.getElementById('productCardView');
    const btnTable = document.getElementById('btnViewTable');
    const btnCard = document.getElementById('btnViewCard');

    if (!tableContainer || !cardView) return;

    if (mode === 'card') {
        tableContainer.style.display = 'none';
        cardView.style.display = 'grid';
        if (btnTable) btnTable.classList.remove('active');
        if (btnCard) btnCard.classList.add('active');
    } else {
        tableContainer.style.display = 'block';
        cardView.style.display = 'none';
        if (btnTable) btnTable.classList.add('active');
        if (btnCard) btnCard.classList.remove('active');
    }

    try {
        localStorage.setItem('pos_products_view_mode', mode);
    } catch(e) {}
}

// Combined Search Filter for both Table and Card Views
function filterProductsCombined(inputId) {
    if (typeof filterTable === 'function') {
        filterTable(inputId, 'productTable');
    }

    const input = document.getElementById(inputId);
    const container = document.getElementById('productCardView');
    if (!input || !container) return;

    const filter = input.value.toLowerCase().trim();
    const cards = container.querySelectorAll('.product-item-card');
    let visible = 0;

    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        const match = text.includes(filter);
        card.style.display = match ? 'flex' : 'none';
        if (match) visible++;
    });

    let emptyEl = container.querySelector('.no-filter-card-results');
    if (visible === 0 && cards.length > 0) {
        if (!emptyEl) {
            emptyEl = document.createElement('div');
            emptyEl.className = 'no-filter-card-results empty-state';
            emptyEl.style.gridColumn = '1 / -1';
            emptyEl.style.padding = '40px 20px';
            emptyEl.style.textAlign = 'center';
            emptyEl.innerHTML = '<i class="fas fa-search" style="font-size:2rem;color:var(--text-light);margin-bottom:10px;"></i><p>ບໍ່ພົບສິນຄ້າທີ່ກົງກັບຄຳຄົ້ນຫາ</p>';
            container.appendChild(emptyEl);
        }
        emptyEl.style.display = 'block';
    } else if (emptyEl) {
        emptyEl.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadBarcodePrinters();
    setupExcelDropZone();

    // Auto-detect or restore products view mode
    try {
        const savedMode = localStorage.getItem('pos_products_view_mode');
        if (savedMode === 'card' || savedMode === 'table') {
            setProductViewMode(savedMode);
        } else if (window.innerWidth <= 768) {
            setProductViewMode('card');
        } else {
            setProductViewMode('table');
        }
    } catch(e) {
        if (window.innerWidth <= 768) setProductViewMode('card');
    }
});
</script>

<?php include 'includes/multi_barcode_modal.php'; ?>
<script src="assets/js/multi_barcode.js?v=<?= time() ?>"></script>

<?php include 'includes/footer.php'; ?>
