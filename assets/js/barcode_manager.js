/**
 * POSPharma - Barcode Center & Label Designer Engine
 * Manages Print Hub, Visual Label Designer, Multi-Barcodes, and Thermal Printers
 */

let allProductsData = [];
let currentTemplates = [];
let activeTemplate = null;

document.addEventListener('DOMContentLoaded', async () => {
    await loadTemplates();
    await loadProducts();
    initPrinterConfig();

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('source') === 'po' && urlParams.get('po_id')) {
        const poId = urlParams.get('po_id');
        const filterSrc = document.getElementById('filterSource');
        if (filterSrc) filterSrc.value = 'po';
        handleSourceChange('po');
        const poSelect = document.getElementById('selectedPoId');
        if (poSelect) poSelect.value = poId;
        loadPoItems(poId);
    }
});

// ===================================================
// 1. Tab Navigation
// ===================================================
function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content-panel').forEach(p => p.style.display = 'none');

    if (tabId === 'print') {
        document.getElementById('tabBtnPrint').classList.add('active');
        document.getElementById('tabContentPrint').style.display = 'block';
        updateLivePrintPreview();
    } else if (tabId === 'designer') {
        document.getElementById('tabBtnDesigner').classList.add('active');
        document.getElementById('tabContentDesigner').style.display = 'block';
        renderDesignerPreview();
    } else if (tabId === 'printer') {
        document.getElementById('tabBtnPrinter').classList.add('active');
        document.getElementById('tabContentPrinter').style.display = 'block';
    }
}

// ===================================================
// 2. Load Products & Filter
// ===================================================
async function loadProducts(source = 'all') {
    const tbody = document.getElementById('productTableBody');
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light);"><i class="fas fa-spinner fa-spin"></i> ກຳລັງໂຫຼດຂໍ້ມູນ...</td></tr>`;

    try {
        const res = await fetch(`api/products.php?limit=500`);
        const json = await res.json();
        const prods = json.products || json.data || [];
        if (json.success && Array.isArray(prods)) {
            allProductsData = prods.map(p => ({
                id: parseInt(p.id),
                name: p.name || '',
                sku: p.code || p.id,
                barcode: p.barcode || '',
                category_id: p.category_id,
                price: parseFloat(p.selling_price) || 0,
                stock: parseInt(p.stock_quantity) || 0,
                unit: p.unit || 'ແຜງ',
                qtyToPrint: 1,
                selected: false
            }));
            filterProductList();
        } else {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:20px;color:var(--danger);">ບໍ່ສາມາດດຶງຂໍ້ມູນສິນຄ້າໄດ້</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:20px;color:var(--danger);">ເກີດຂໍ້ຜິດພາດ: ${err.message}</td></tr>`;
    }
}

function handleSourceChange(source) {
    const poRow = document.getElementById('poPickerRow');
    if (source === 'po') {
        poRow.style.display = 'flex';
    } else {
        poRow.style.display = 'none';
        filterProductList();
    }
}

async function loadPoItems(poId) {
    if (!poId) return;
    try {
        const res = await fetch(`api/barcodes.php?action=get_po_items&po_id=${poId}`);
        const json = await res.json();
        if (json.success && json.data) {
            const poItems = json.data;
            // Mark products in po and set print qty = received qty
            allProductsData.forEach(p => {
                const found = poItems.find(item => parseInt(item.product_id) === p.id);
                if (found) {
                    p.selected = true;
                    p.qtyToPrint = parseInt(found.quantity_received) || parseInt(found.quantity_ordered) || 1;
                } else {
                    p.selected = false;
                }
            });
            renderProductTable(allProductsData.filter(p => p.selected));
            updatePrintSummary();
            showToast(`ດຶງລາຍການຈາກໃບສັ່ງຊື້ສຳເລັດ (${poItems.length} ລາຍການ)`, 'success');
        }
    } catch (e) {
        showToast('ບໍ່ສາມາດດຶງຂໍ້ມູນຈາກໃບສັ່ງຊື້ໄດ້', 'error');
    }
}

function filterProductList() {
    const query = (document.getElementById('filterSearch')?.value || '').toLowerCase().trim();
    const catId = document.getElementById('filterCategory')?.value || '';
    const source = document.getElementById('filterSource')?.value || 'all';

    let list = allProductsData.filter(p => {
        const matchesQuery = !query || p.name.toLowerCase().includes(query) || (p.barcode || '').toLowerCase().includes(query) || String(p.sku).includes(query);
        const matchesCat = !catId || String(p.category_id) === String(catId);
        
        let matchesSource = true;
        if (source === 'in_stock') matchesSource = p.stock > 0;
        else if (source === 'no_barcode') matchesSource = !p.barcode;

        return matchesQuery && matchesCat && matchesSource;
    });

    renderProductTable(list);
}

function renderProductTable(products) {
    const tbody = document.getElementById('productTableBody');
    if (!tbody) return;

    if (products.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light);"><i class="fas fa-inbox" style="font-size:2rem;margin-bottom:8px;display:block;"></i>ບໍ່ພົບສິນຄ້າຕາມເງື່ອນໄຂ</td></tr>`;
        return;
    }

    tbody.innerHTML = products.map((p, idx) => {
        let typeBadge = '<span class="badge badge-secondary" style="font-size:0.7rem;">CODE 128</span>';
        if (/^[0-9]{13}$/.test(p.barcode)) typeBadge = '<span class="badge badge-success" style="font-size:0.7rem;">EAN-13</span>';
        else if (/^[0-9]{8}$/.test(p.barcode)) typeBadge = '<span class="badge badge-info" style="font-size:0.7rem;">EAN-8</span>';
        else if (/^[0-9]{12}$/.test(p.barcode)) typeBadge = '<span class="badge badge-warning" style="font-size:0.7rem;">UPC-A</span>';
        else if (!p.barcode) typeBadge = '<span class="badge badge-danger" style="font-size:0.7rem;">ຍັງບໍ່ມີ</span>';

        return `
            <tr style="background:${p.selected ? '#f1f8e9' : 'transparent'};">
                <td style="text-align:center;">
                    <input type="checkbox" class="prod-select-chk" data-id="${p.id}" ${p.selected ? 'checked' : ''} onchange="toggleProductSelection(${p.id}, this.checked)">
                </td>
                <td>
                    <strong style="color:var(--text-dark);">${escapeHtml(p.name)}</strong>
                    <div style="font-size:0.75rem;color:var(--text-light);">SKU: ${escapeHtml(p.sku)}</div>
                </td>
                <td>
                    <span style="font-family:monospace;font-weight:700;color:var(--text-dark);">${p.barcode ? escapeHtml(p.barcode) : '<em style="color:#aaa;">-</em>'}</span>
                </td>
                <td>${typeBadge}</td>
                <td style="font-weight:700;color:var(--primary);">${formatCurrency(p.price)} ₭</td>
                <td>
                    <span style="font-weight:600;color:${p.stock <= 5 ? '#d32f2f' : 'inherit'};">${p.stock}</span> <small style="color:var(--text-light);">${escapeHtml(p.unit)}</small>
                </td>
                <td style="text-align:center;">
                    <div style="display:inline-flex;align-items:center;gap:4px;">
                        <input type="number" class="form-control" style="width:65px;padding:3px 6px;text-align:center;font-weight:700;" value="${p.qtyToPrint}" min="1" max="999" onchange="updateProductPrintQty(${p.id}, this.value)">
                        <button type="button" class="btn btn-outline btn-xs" onclick="setProductPrintQtyStock(${p.id})" title="ຕັ້ງເທົ່າກັບສະຕັອກ">=</button>
                    </div>
                </td>
                <td style="text-align:center;">
                    <button type="button" class="btn btn-white btn-xs" onclick="openMultiBarcodeModal(${p.id}, '${escapeHtml(p.name)}', '${escapeHtml(p.unit)}', ${p.price})" title="ຈັດການຫຼາຍບາໂຄດ">
                        <i class="fas fa-layer-group" style="color:var(--primary);"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');

    updatePrintSummary();
}

function toggleProductSelection(id, checked) {
    const prod = allProductsData.find(p => p.id === id);
    if (prod) {
        prod.selected = checked;
    }
    updatePrintSummary();
}

function toggleSelectAll(checked) {
    document.querySelectorAll('.prod-select-chk').forEach(chk => {
        chk.checked = checked;
        const id = parseInt(chk.dataset.id);
        const prod = allProductsData.find(p => p.id === id);
        if (prod) prod.selected = checked;
    });
    updatePrintSummary();
}

function setAllQuantities(val) {
    allProductsData.forEach(p => {
        if (p.selected) {
            p.qtyToPrint = (val === 'stock') ? Math.max(1, p.stock) : Math.max(1, parseInt(val) || 1);
        }
    });
    filterProductList();
}

function clearAllSelections() {
    allProductsData.forEach(p => p.selected = false);
    const selectAll = document.getElementById('selectAllChk');
    if (selectAll) selectAll.checked = false;
    filterProductList();
}

function updateProductPrintQty(id, val) {
    const prod = allProductsData.find(p => p.id === id);
    if (prod) {
        prod.qtyToPrint = Math.max(1, parseInt(val) || 1);
    }
    updatePrintSummary();
}

function setProductPrintQtyStock(id) {
    const prod = allProductsData.find(p => p.id === id);
    if (prod) {
        prod.qtyToPrint = Math.max(1, prod.stock);
    }
    filterProductList();
}

function updatePrintSummary() {
    const selected = allProductsData.filter(p => p.selected);
    const totalStickers = selected.reduce((sum, p) => sum + (parseInt(p.qtyToPrint) || 0), 0);

    const badge = document.getElementById('selectedCountBadge');
    if (badge) badge.innerText = `ເລືອກແລ້ວ: ${selected.length} ລາຍການ`;

    const totalEl = document.getElementById('totalPrintStickersCount');
    if (totalEl) totalEl.innerText = totalStickers;

    updateLivePrintPreview();
}

// ===================================================
// 3. Load Templates & Designer
// ===================================================
async function loadTemplates() {
    try {
        const res = await fetch('api/barcodes.php?action=get_templates');
        const json = await res.json();
        if (json.success && json.data) {
            currentTemplates = json.data;
            populateTemplateDropdowns();
            renderSavedTemplatesTable();
        }
    } catch (e) {
        console.error('Failed to load templates:', e);
    }
}

function populateTemplateDropdowns() {
    const select = document.getElementById('printTemplateSelect');
    if (!select) return;

    select.innerHTML = currentTemplates.map(t => `
        <option value="${t.id}" ${t.is_default ? 'selected' : ''}>
            ${escapeHtml(t.name)} (${t.width_mm}x${t.height_mm}mm - ${t.columns} ຖັນ)
        </option>
    `).join('');

    onPrintTemplateChanged();
}

function onPrintTemplateChanged() {
    const select = document.getElementById('printTemplateSelect');
    if (!select) return;

    const tplId = parseInt(select.value);
    activeTemplate = currentTemplates.find(t => t.id === tplId) || currentTemplates[0];

    const oriSelect = document.getElementById('printOrientationSelect');
    if (oriSelect && activeTemplate) {
        oriSelect.value = activeTemplate.orientation || 'portrait';
    }

    updateLivePrintPreview();
}

function renderSavedTemplatesTable() {
    const tbody = document.getElementById('savedTemplatesTbody');
    if (!tbody) return;

    tbody.innerHTML = currentTemplates.map(t => `
        <tr>
            <td>
                <strong>${escapeHtml(t.name)}</strong>
                <div style="font-size:0.75rem;color:var(--text-light);">
                    ${t.show_shop_name ? 'ຊື່ຮ້ານ • ' : ''}
                    ${t.show_lot_expiry ? 'Lot/EXP • ' : ''}
                    ${t.font_size_name}
                </div>
            </td>
            <td>${t.width_mm} x ${t.height_mm} mm</td>
            <td>${t.columns} ຖັນ</td>
            <td>${t.is_default ? '<span class="badge badge-success">Default</span>' : '-'}</td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-outline btn-xs" onclick="editTemplate(${t.id})" title="ແກ້ໄຂ"><i class="fas fa-edit"></i></button>
                <button type="button" class="btn btn-white btn-xs" onclick="deleteTemplate(${t.id})" title="ລຶບ" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

function updateLivePrintPreview() {
    const previewBox = document.getElementById('liveStickerPreviewBox');
    const content = document.getElementById('liveStickerContent');
    if (!previewBox || !content) return;

    const tpl = activeTemplate || currentTemplates[0] || {
        width_mm: 40,
        height_mm: 30,
        show_name: 1,
        show_price: 1,
        show_barcode: 1,
        show_barcode_text: 1,
        show_unit: 1,
        font_size_name: '8.5pt',
        font_size_price: '9pt',
        barcode_height: 32
    };

    const firstSelected = allProductsData.find(p => p.selected) || allProductsData[0] || {
        name: 'ພາຣາເຊຕາມອນ 500mg',
        price: 20000,
        barcode: '8850000000001',
        unit: 'ແຜງ'
    };

    // Calculate preview pixel size based on mm (at ~3.2px per mm for preview box)
    const scale = 3.2;
    const wPx = Math.max(120, Math.round(tpl.width_mm * scale));
    const hPx = Math.max(80, Math.round(tpl.height_mm * scale));

    previewBox.style.width = `${wPx}px`;
    previewBox.style.maxWidth = '100%';
    previewBox.style.height = `${hPx}px`;
    previewBox.style.boxSizing = 'border-box';

    content.innerHTML = `
        ${tpl.show_shop_name ? `<div style="font-size:6pt;font-weight:600;color:#555;text-transform:uppercase;">ຮ້ານຂາຍຢາ POSPharma</div>` : ''}
        ${tpl.show_name ? `<div style="font-size:${tpl.font_size_name};font-weight:700;color:#000;line-height:1.2;max-height:2.4em;overflow:hidden;word-break:break-word;">${escapeHtml(firstSelected.name)}</div>` : ''}
        ${tpl.show_code ? `<div style="font-size:6pt;color:#666;">SKU: ${escapeHtml(firstSelected.sku || '001')}</div>` : ''}
        ${tpl.show_barcode ? `<svg id="previewBarcodeSvg"></svg>` : ''}
        ${tpl.show_price ? `<div style="font-size:${tpl.font_size_price};font-weight:800;color:#000;line-height:1.2;">₭ ${formatCurrency(firstSelected.price)} ${tpl.show_unit ? `<small style="font-size:0.75em;font-weight:600;">/ ${escapeHtml(firstSelected.unit)}</small>` : ''}</div>` : ''}
        ${tpl.show_lot_expiry ? `<div style="font-size:6pt;color:#444;display:flex;gap:6px;"><span>LOT: 2401</span><span>EXP: 12/2026</span></div>` : ''}
    `;

    if (tpl.show_barcode && firstSelected.barcode) {
        setTimeout(() => {
            try {
                let fmt = detectBarcodeFormat(firstSelected.barcode);
                JsBarcode('#previewBarcodeSvg', firstSelected.barcode, {
                    format: (fmt === 'EAN13' || fmt === 'EAN8' || fmt === 'UPCA') ? fmt : 'CODE128',
                    width: 1.1,
                    height: Math.min(26, parseInt(tpl.barcode_height) || 24),
                    displayValue: Boolean(parseInt(tpl.show_barcode_text)),
                    fontSize: 9,
                    margin: 0
                });
            } catch (err) {
                JsBarcode('#previewBarcodeSvg', firstSelected.barcode, {
                    format: 'CODE128',
                    width: 1.2,
                    height: 28,
                    displayValue: true,
                    margin: 0
                });
            }
        }, 30);
    }
}

// ===================================================
// 4. Designer Interactive Preview
// ===================================================
function onDesignerDimensionChanged() {
    const w = parseFloat(document.getElementById('tplWidth')?.value) || 40;
    const h = parseFloat(document.getElementById('tplHeight')?.value) || 30;
    const badge = document.getElementById('previewDimensionsBadge');
    if (badge) badge.innerText = `${w} x ${h} mm`;
    renderDesignerPreview();
}

function renderDesignerPreview() {
    const canvas = document.getElementById('designerCanvas');
    if (!canvas) return;

    const wMm = parseFloat(document.getElementById('tplWidth')?.value) || 40;
    const hMm = parseFloat(document.getElementById('tplHeight')?.value) || 30;
    const showName = document.getElementById('showNameChk')?.checked;
    const showPrice = document.getElementById('showPriceChk')?.checked;
    const showBarcode = document.getElementById('showBarcodeChk')?.checked;
    const showBarcodeText = document.getElementById('showBarcodeTextChk')?.checked;
    const showShop = document.getElementById('showShopNameChk')?.checked;
    const showUnit = document.getElementById('showUnitChk')?.checked;
    const showCode = document.getElementById('showCodeChk')?.checked;
    const showLotExp = document.getElementById('showLotExpiryChk')?.checked;
    const fontSizeName = document.getElementById('sliderFontSizeName')?.value + 'pt';
    const fontSizePrice = document.getElementById('sliderFontSizePrice')?.value + 'pt';
    const barcodeHeight = parseInt(document.getElementById('sliderBarcodeHeight')?.value) || 32;

    const scale = 3.7795; // 96 DPI screen scale approx
    const wPx = Math.round(wMm * scale);
    const hPx = Math.round(hMm * scale);

    canvas.style.width = `${wPx}px`;
    canvas.style.height = `${hPx}px`;
    canvas.style.maxWidth = '100%';
    canvas.style.padding = '6px';

    canvas.innerHTML = `
        ${showShop ? `<div style="font-size:7pt;font-weight:600;color:#666;text-transform:uppercase;margin-bottom:2px;">ຮ້ານຂາຍຢາ POSPharma</div>` : ''}
        ${showName ? `<div style="font-size:${fontSizeName};font-weight:700;color:#000;line-height:1.25;max-height:2.6em;overflow:hidden;word-break:break-word;text-align:center;">ວິຕາມິນ B Complex 500mg</div>` : ''}
        ${showCode ? `<div style="font-size:6.5pt;color:#777;">SKU: MED-00109</div>` : ''}
        ${showBarcode ? `<svg id="designerBarcodeSvg" style="max-width:95%;margin:2px auto;"></svg>` : ''}
        ${showPrice ? `<div style="font-size:${fontSizePrice};font-weight:800;color:#000;line-height:1.2;text-align:center;">₭ 50,000 ${showUnit ? `<small style="font-size:0.75em;font-weight:600;">/ ກ່ອງ</small>` : ''}</div>` : ''}
        ${showLotExp ? `<div style="font-size:6.5pt;color:#555;display:flex;gap:8px;margin-top:2px;"><span>LOT: B2409</span><span>EXP: 10/2027</span></div>` : ''}
    `;

    if (showBarcode) {
        setTimeout(() => {
            try {
                JsBarcode('#designerBarcodeSvg', '8850000000009', {
                    format: 'CODE128',
                    width: 1.4,
                    height: barcodeHeight,
                    displayValue: showBarcodeText,
                    fontSize: 10,
                    margin: 0
                });
            } catch (e) {}
        }, 30);
    }
}

async function saveTemplateForm() {
    const id = document.getElementById('designerTemplateId')?.value;
    const name = document.getElementById('tplName')?.value.trim();
    if (!name) {
        showToast('ກະລຸນາປ້ອນຊື່ Template', 'warning');
        return;
    }

    const payload = new FormData();
    if (id) payload.append('id', id);
    payload.append('action', 'save_template');
    payload.append('name', name);
    payload.append('width_mm', document.getElementById('tplWidth')?.value || 40);
    payload.append('height_mm', document.getElementById('tplHeight')?.value || 30);
    payload.append('columns', document.getElementById('tplColumns')?.value || 1);
    payload.append('orientation', document.getElementById('tplOrientation')?.value || 'portrait');
    payload.append('show_name', document.getElementById('showNameChk')?.checked ? 1 : 0);
    payload.append('show_price', document.getElementById('showPriceChk')?.checked ? 1 : 0);
    payload.append('show_barcode', document.getElementById('showBarcodeChk')?.checked ? 1 : 0);
    payload.append('show_barcode_text', document.getElementById('showBarcodeTextChk')?.checked ? 1 : 0);
    payload.append('show_shop_name', document.getElementById('showShopNameChk')?.checked ? 1 : 0);
    payload.append('show_unit', document.getElementById('showUnitChk')?.checked ? 1 : 0);
    payload.append('show_code', document.getElementById('showCodeChk')?.checked ? 1 : 0);
    payload.append('show_lot_expiry', document.getElementById('showLotExpiryChk')?.checked ? 1 : 0);
    payload.append('font_size_name', document.getElementById('sliderFontSizeName')?.value + 'pt');
    payload.append('font_size_price', document.getElementById('sliderFontSizePrice')?.value + 'pt');
    payload.append('barcode_height', document.getElementById('sliderBarcodeHeight')?.value || 32);
    payload.append('is_default', document.getElementById('tplIsDefault')?.checked ? 1 : 0);

    try {
        const res = await fetch('api/barcodes.php', { method: 'POST', body: payload });
        const json = await res.json();
        if (json.success) {
            showToast('ບັນທຶກ Template ສຳເລັດ', 'success');
            await loadTemplates();
            resetDesignerForm();
        } else {
            showToast(json.message || 'ບັນທຶກບໍ່ສຳເລັດ', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການບັນທຶກ', 'error');
    }
}

function resetDesignerForm() {
    document.getElementById('designerTemplateId').value = '';
    document.getElementById('tplName').value = '';
    document.getElementById('tplWidth').value = 40;
    document.getElementById('tplHeight').value = 30;
    document.getElementById('tplColumns').value = 1;
    document.getElementById('tplOrientation').value = 'portrait';
    document.getElementById('showNameChk').checked = true;
    document.getElementById('showPriceChk').checked = true;
    document.getElementById('showBarcodeChk').checked = true;
    document.getElementById('showBarcodeTextChk').checked = true;
    document.getElementById('showShopNameChk').checked = false;
    document.getElementById('showUnitChk').checked = true;
    document.getElementById('showCodeChk').checked = false;
    document.getElementById('showLotExpiryChk').checked = false;
    document.getElementById('sliderFontSizeName').value = 8.5;
    document.getElementById('labelFontSizeName').innerText = '8.5pt';
    document.getElementById('sliderFontSizePrice').value = 9;
    document.getElementById('labelFontSizePrice').innerText = '9pt';
    document.getElementById('sliderBarcodeHeight').value = 32;
    document.getElementById('labelBarcodeHeight').innerText = '32px';
    document.getElementById('tplIsDefault').checked = false;
    renderDesignerPreview();
}

function editTemplate(id) {
    const t = currentTemplates.find(item => item.id === id);
    if (!t) return;

    document.getElementById('designerTemplateId').value = t.id;
    document.getElementById('tplName').value = t.name;
    document.getElementById('tplWidth').value = t.width_mm;
    document.getElementById('tplHeight').value = t.height_mm;
    document.getElementById('tplColumns').value = t.columns;
    document.getElementById('tplOrientation').value = t.orientation || 'portrait';
    document.getElementById('showNameChk').checked = Boolean(parseInt(t.show_name));
    document.getElementById('showPriceChk').checked = Boolean(parseInt(t.show_price));
    document.getElementById('showBarcodeChk').checked = Boolean(parseInt(t.show_barcode));
    document.getElementById('showBarcodeTextChk').checked = Boolean(parseInt(t.show_barcode_text));
    document.getElementById('showShopNameChk').checked = Boolean(parseInt(t.show_shop_name));
    document.getElementById('showUnitChk').checked = Boolean(parseInt(t.show_unit));
    document.getElementById('showCodeChk').checked = Boolean(parseInt(t.show_code));
    document.getElementById('showLotExpiryChk').checked = Boolean(parseInt(t.show_lot_expiry));
    
    const fnVal = parseFloat(t.font_size_name) || 8.5;
    document.getElementById('sliderFontSizeName').value = fnVal;
    document.getElementById('labelFontSizeName').innerText = fnVal + 'pt';

    const fpVal = parseFloat(t.font_size_price) || 9;
    document.getElementById('sliderFontSizePrice').value = fpVal;
    document.getElementById('labelFontSizePrice').innerText = fpVal + 'pt';

    const bhVal = parseInt(t.barcode_height) || 32;
    document.getElementById('sliderBarcodeHeight').value = bhVal;
    document.getElementById('labelBarcodeHeight').innerText = bhVal + 'px';

    document.getElementById('tplIsDefault').checked = Boolean(parseInt(t.is_default));

    switchTab('designer');
    renderDesignerPreview();
}

async function deleteTemplate(id) {
    let confirmed = false;
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: 'ຢືນຢັນການລຶບ Template?',
            text: 'ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບຮູບແບບສະຕິກເກີນີ້?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d32f2f',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: '<i class="fas fa-trash-alt"></i> ລຶບ Template',
            cancelButtonText: 'ຍົກເລີກ',
            reverseButtons: true,
            customClass: { popup: 'swal2-lao-popup' }
        });
        confirmed = result.isConfirmed;
    } else {
        confirmed = window.confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບ Template ນີ້?');
    }
    if (!confirmed) return;

    try {
        const payload = new FormData();
        payload.append('action', 'delete_template');
        payload.append('id', id);
        const res = await fetch('api/barcodes.php', { method: 'POST', body: payload });
        const json = await res.json();
        if (json.success) {
            showToast('ລຶບ Template ສຳເລັດ', 'success');
            loadTemplates();
        }
    } catch (e) {
        showToast('ບໍ່ສາມາດລຶບ Template ໄດ້', 'error');
    }
}

// ===================================================
// 5. Printing Execution Engine (Web + Thermal Desktop)
// ===================================================
function executeStickerPrint() {
    const selected = allProductsData.filter(p => p.selected);
    if (selected.length === 0) {
        showToast('ກະລຸນາເລືອກສິນຄ້າຢ່າງໜ້ອຍ 1 ລາຍການກ່ອນພິມ', 'warning');
        return;
    }

    const tpl = activeTemplate || currentTemplates[0] || {
        width_mm: 40,
        height_mm: 30,
        columns: 1,
        orientation: 'portrait',
        font_size_name: '8.5pt',
        font_size_price: '9pt',
        barcode_height: 32,
        show_name: 1,
        show_price: 1,
        show_barcode: 1,
        show_barcode_text: 1,
        show_unit: 1
    };

    const orientation = document.getElementById('printOrientationSelect')?.value || tpl.orientation || 'portrait';
    const copies = parseInt(document.getElementById('printCopiesInput')?.value) || 1;

    // Generate Sticker Items Array
    let itemsToPrint = [];
    selected.forEach(p => {
        const count = (parseInt(p.qtyToPrint) || 1) * copies;
        for (let i = 0; i < count; i++) {
            itemsToPrint.push({
                name: p.name,
                sku: p.sku,
                barcode: p.barcode || generateFallbackBarcode(p.id),
                price: p.price,
                unit: p.unit
            });
        }
    });

    const fullHtml = buildStickersPrintHtml(itemsToPrint, tpl, orientation);

    // Desktop App Silent Print
    const barcodePrinter = localStorage.getItem('pos_barcode_printer') || '';
    if (window.api && typeof window.api.printReceiptSilent === 'function') {
        showToast(`ກຳລັງສັ່ງພິມສະຕິກເກີ ${itemsToPrint.length} ດວງ...`, 'info');
        window.api.printReceiptSilent({
            html: fullHtml,
            deviceName: barcodePrinter,
            landscape: (orientation === 'landscape'),
            pageSize: {
                width: Math.round(tpl.width_mm * 1000),
                height: Math.round(tpl.height_mm * 1000)
            }
        }).then(res => {
            if (res && res.success) {
                showToast('ພິມສະຕິກເກີສຳເລັດແລ້ວ', 'success');
            }
        });
        return;
    }

    // Web Browser Window Print
    const printWindow = window.open('', '_blank', 'width=850,height=850');
    if (printWindow) {
        printWindow.document.write(fullHtml + `
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        window.onafterprint = function() { window.close(); };
                    }, 400);
                };
            <\/script>
        `);
        printWindow.document.close();
        showToast(`ກຳລັງເປີດໜ້າພິມ ${itemsToPrint.length} ດວງ...`, 'info');
    }
}

function buildStickersPrintHtml(items, tpl, orientation) {
    const pw = `${tpl.width_mm}mm`;
    const ph = `${tpl.height_mm}mm`;
    const columns = parseInt(tpl.columns) || 1;
    const isMultiColumn = columns > 1;

    let pageCss = '';
    let stickersBody = '';

    if (isMultiColumn) {
        // Multi-column layout (e.g. A4 sheet 3x7 or similar)
        pageCss = `
            @page {
                size: A4 portrait;
                margin: 6mm 5mm;
            }
            .sheet-grid {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(${columns}, ${pw});
                gap: 2.5mm 3mm;
                justify-content: center;
                page-break-after: always;
                break-after: page;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .sheet-grid:last-child {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
            .sticker-page {
                width: ${pw} !important;
                height: ${ph} !important;
                max-width: ${pw} !important;
                max-height: ${ph} !important;
                border: 1px dashed #e2e8f0;
                box-sizing: border-box !important;
                overflow: hidden !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                padding: 1mm 1.5mm !important;
            }
        `;

        // Chunk items by page capacity (e.g. columns * 7)
        const perPage = columns * 7;
        const pages = [];
        for (let i = 0; i < items.length; i += perPage) {
            pages.push(items.slice(i, i + perPage));
        }

        stickersBody = pages.map(pageItems => `
            <div class="sheet-grid">
                ${pageItems.map(item => `
                    <div class="sticker-page">
                        <div class="sticker-content">
                            ${tpl.show_shop_name ? `<div class="store-title">ຮ້ານຂາຍຢາ POSPharma</div>` : ''}
                            ${tpl.show_name ? `<div class="prod-name">${escapeHtml(item.name)}</div>` : ''}
                            ${tpl.show_code ? `<div class="prod-code">SKU: ${escapeHtml(item.sku || '')}</div>` : ''}
                            ${tpl.show_barcode ? `<svg class="barcode-svg" data-barcode="${escapeHtml(item.barcode)}"></svg>` : ''}
                            ${tpl.show_price ? `<div class="prod-price">₭ ${formatCurrency(item.price)} ${tpl.show_unit ? `<small>/ ${escapeHtml(item.unit || '')}</small>` : ''}</div>` : ''}
                            ${tpl.show_lot_expiry ? `<div class="prod-lot">LOT: 2409 EXP: 10/2027</div>` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `).join('');

    } else {
        // Continuous / Roll Thermal Labels (1 column: 40x30mm, 50x30mm, etc.)
        pageCss = `
            @page {
                size: ${pw} ${ph};
                margin: 0mm;
            }
            html, body {
                width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .sticker-page {
                width: ${pw} !important;
                height: ${ph} !important;
                max-width: ${pw} !important;
                max-height: ${ph} !important;
                margin: 0 auto !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
                box-sizing: border-box !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                padding: 1mm 1.5mm !important;
            }
            .sticker-page:last-child,
            .sticker-page.last-page {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
        `;

        stickersBody = items.map((item, idx) => {
            const isLast = idx === items.length - 1;
            return `
                <div class="sticker-page ${isLast ? 'last-page' : ''}">
                    <div class="sticker-content">
                        ${tpl.show_shop_name ? `<div class="store-title">ຮ້ານຂາຍຢາ POSPharma</div>` : ''}
                        ${tpl.show_name ? `<div class="prod-name">${escapeHtml(item.name)}</div>` : ''}
                        ${tpl.show_code ? `<div class="prod-code">SKU: ${escapeHtml(item.sku || '')}</div>` : ''}
                        ${tpl.show_barcode ? `<svg class="barcode-svg" data-barcode="${escapeHtml(item.barcode)}"></svg>` : ''}
                        ${tpl.show_price ? `<div class="prod-price">₭ ${formatCurrency(item.price)} ${tpl.show_unit ? `<small>/ ${escapeHtml(item.unit || '')}</small>` : ''}</div>` : ''}
                        ${tpl.show_lot_expiry ? `<div class="prod-lot">LOT: 2409 EXP: 10/2027</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    const bcodeW = tpl.width_mm <= 35 ? 1.0 : (tpl.width_mm <= 45 ? 1.1 : 1.3);
    const bcodeH = Math.min(parseInt(tpl.barcode_height) || 28, Math.round(tpl.height_mm * 0.9));
    const showTxt = Boolean(parseInt(tpl.show_barcode_text));

    return `<!DOCTYPE html>
    <html lang="lo">
    <head>
        <meta charset="UTF-8">
        <title>ພິມສະຕິກເກີບາໂຄດ (${items.length} ດວງ)</title>
        <link rel="stylesheet" href="assets/vendor/fonts/noto-sans-lao.css">
        <script src="assets/vendor/JsBarcode.all.min.js"><\/script>
        <style>
            ${pageCss}
            * { box-sizing: border-box; margin: 0; padding: 0; }
            html, body {
                font-family: 'Noto Sans Lao', Arial, sans-serif;
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .sticker-content {
                width: 100% !important;
                height: 100% !important;
                max-width: 100% !important;
                max-height: 100% !important;
                margin: 0 auto !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                gap: 0.8mm !important;
                padding: 0 !important;
                overflow: hidden !important;
                text-align: center !important;
            }
            .store-title {
                font-size: 5.5pt !important;
                font-weight: 600 !important;
                color: #555 !important;
                line-height: 1 !important;
                text-transform: uppercase !important;
            }
            .prod-name {
                font-size: ${tpl.font_size_name || '8.5pt'} !important;
                font-weight: 700 !important;
                line-height: 1.2 !important;
                white-space: normal !important;
                word-break: break-word !important;
                max-width: 100% !important;
                max-height: 2.5em !important;
                overflow: hidden !important;
                color: #000 !important;
            }
            .prod-code {
                font-size: 5.5pt !important;
                color: #666 !important;
                line-height: 1 !important;
            }
            .barcode-svg {
                display: block !important;
                margin: 0 auto !important;
                max-width: 96% !important;
                height: auto !important;
                max-height: ${bcodeH + (showTxt ? 14 : 0)}px !important;
            }
            .prod-price {
                font-size: ${tpl.font_size_price || '9pt'} !important;
                font-weight: 800 !important;
                line-height: 1.1 !important;
                color: #000 !important;
            }
            .prod-lot {
                font-size: 5.5pt !important;
                color: #444 !important;
                line-height: 1 !important;
            }
        </style>
    </head>
    <body>
        ${stickersBody}
        <script>
            document.querySelectorAll('.barcode-svg').forEach(function(svg) {
                var code = svg.dataset.barcode || '8850000000001';
                try {
                    var fmt = (code.length === 13 && /^[0-9]+$/.test(code)) ? 'EAN13' : 'CODE128';
                    JsBarcode(svg, code, {
                        format: fmt,
                        width: ${bcodeW},
                        height: ${bcodeH},
                        displayValue: ${showTxt},
                        fontSize: ${tpl.height_mm <= 25 ? 8 : 10},
                        textMargin: 1,
                        margin: 0
                    });
                } catch(e) {
                    JsBarcode(svg, code, {
                        format: 'CODE128',
                        width: 1.1,
                        height: 26,
                        displayValue: ${showTxt},
                        fontSize: 9,
                        textMargin: 1,
                        margin: 0
                    });
                }
            });
        <\/script>
    </body>
    </html>`;
}

function exportToPdf() {
    executeStickerPrint();
    showToast('ໃນໜ້າຕ່າງພິມ, ເລືອກ Destination ເປັນ "Save as PDF" ເພື່ອບັນທຶກເປັນໄຟລ໌ PDF', 'info', 5000);
}

async function downloadTsplCommands() {
    const selected = allProductsData.filter(p => p.selected);
    if (selected.length === 0) {
        showToast('ກະລຸນາເລືອກສິນຄ້າກ່ອນດາວໂຫຼດຄຳສັ່ງ', 'warning');
        return;
    }

    const tpl = activeTemplate || currentTemplates[0];
    const items = selected.map(p => ({
        name: p.name,
        barcode: p.barcode || generateFallbackBarcode(p.id),
        price: p.price,
        qty: parseInt(p.qtyToPrint) || 1
    }));

    try {
        const res = await fetch('api/barcodes.php?action=generate_tspl', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                items: items,
                template: tpl,
                config: {
                    density: parseInt(document.getElementById('cfgDensity')?.value) || 8,
                    speed: parseInt(document.getElementById('cfgSpeed')?.value) || 4
                }
            })
        });
        const json = await res.json();
        if (json.success && json.tspl_code) {
            const blob = new Blob([json.tspl_code], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `barcodes_${Date.now()}.tspl`;
            a.click();
            URL.revokeObjectURL(url);
            showToast('ດາວໂຫຼດໄຟລ໌ຄຳສັ່ງ TSPL ຮຽບຮ້ອຍແລ້ວ', 'success');
        }
    } catch (e) {
        showToast('ບໍ່ສາມາດສ້າງຄຳສັ່ງ TSPL ໄດ້', 'error');
    }
}

// ===================================================
// 6. Multi-Barcode Management Modal
// ===================================================
async function openMultiBarcodeModal(productId, productName, baseUnit, basePrice) {
    document.getElementById('mbProductId').value = productId;
    document.getElementById('mbProductName').innerText = productName;
    document.getElementById('mbProductBaseUnit').innerText = baseUnit;
    document.getElementById('mbProductBasePrice').innerText = formatCurrency(basePrice);
    document.getElementById('mbEditBarcodeId').value = '';
    document.getElementById('mbBarcodeInput').value = '';
    document.getElementById('mbBarcodeAlert').style.display = 'none';

    await loadProductBarcodes(productId);
    if (typeof openModal === 'function') {
        openModal('multiBarcodeModal');
    } else {
        const modal = document.getElementById('multiBarcodeModal');
        if (modal) modal.classList.add('show');
    }
}

async function loadProductBarcodes(productId) {
    const tbody = document.getElementById('multiBarcodeListTbody');
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:14px;"><i class="fas fa-spinner fa-spin"></i> ກຳລັງໂຫຼດ...</td></tr>`;

    try {
        const res = await fetch(`api/barcodes.php?action=get_product_barcodes&product_id=${productId}`);
        const json = await res.json();
        if (json.success && json.data) {
            if (json.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:14px;color:#888;">ຍັງບໍ່ມີບາໂຄດສຳລັບສິນຄ້ານີ້</td></tr>`;
                return;
            }
            tbody.innerHTML = json.data.map(b => `
                <tr style="${b.is_default ? 'background:#e8f5e9;font-weight:600;' : ''}">
                    <td><strong style="font-family:monospace;">${escapeHtml(b.barcode)}</strong></td>
                    <td><span class="badge badge-secondary">${escapeHtml(b.barcode_type)}</span></td>
                    <td>${escapeHtml(b.unit || '-')}</td>
                    <td>x${b.multiplier || 1}</td>
                    <td>${b.price ? formatCurrency(b.price) + ' ₭' : '<em style="color:#888;">ລາຄາຫຼັກ</em>'}</td>
                    <td>${b.is_default ? '<span class="badge badge-success">ບາໂຄດຫຼັກ</span>' : '<span class="badge badge-light">ສຳຮອງ</span>'}</td>
                    <td style="text-align:right;">
                        ${!b.is_default ? `<button type="button" class="btn btn-outline btn-xs" onclick="setDefaultBarcodeItem(${b.id}, ${b.product_id})" title="ຕັ້ງເປັນບາໂຄດຫຼັກ"><i class="fas fa-check"></i></button>` : ''}
                        <button type="button" class="btn btn-white btn-xs" onclick="deleteMultiBarcodeItem(${b.id}, ${b.product_id})" style="color:var(--danger);" title="ລຶບ"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--danger);">ເກີດຂໍ້ຜິດພາດ</td></tr>`;
    }
}

async function validateModalBarcode(val) {
    const alertBox = document.getElementById('mbBarcodeAlert');
    if (!alertBox) return;

    val = (val || '').trim();
    if (!val) {
        alertBox.style.display = 'none';
        return;
    }

    try {
        const editId = document.getElementById('mbEditBarcodeId')?.value || '';
        const res = await fetch(`api/barcodes.php?action=check_duplicate&barcode=${encodeURIComponent(val)}&exclude_id=${editId}`);
        const json = await res.json();

        alertBox.style.display = 'block';
        if (json.exists) {
            alertBox.style.color = '#d32f2f';
            alertBox.innerHTML = `<i class="fas fa-times-circle"></i> ບາໂຄດນີ້ຊ້ຳກັບສິນຄ້າ: <strong>${escapeHtml(json.product_name)}</strong>`;
        } else if (!json.valid_checksum) {
            alertBox.style.color = '#e65100';
            alertBox.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${json.validation_msg}`;
        } else {
            alertBox.style.color = '#2e7d32';
            alertBox.innerHTML = `<i class="fas fa-check-circle"></i> ບາໂຄດຖືກຕ້ອງ ແລະ ສາມາດໃຊ້ໄດ້ (${json.detected_type})`;
            const typeSelect = document.getElementById('mbBarcodeType');
            if (typeSelect && json.detected_type) typeSelect.value = json.detected_type;
        }
    } catch (e) {}
}

async function generateBarcodeForModal() {
    const type = document.getElementById('mbBarcodeType')?.value || 'CODE128';
    const prodId = document.getElementById('mbProductId')?.value || '';

    try {
        const res = await fetch(`api/barcodes.php?action=generate_barcode&type=${type}&product_id=${prodId}`);
        const json = await res.json();
        if (json.success && json.barcode) {
            document.getElementById('mbBarcodeInput').value = json.barcode;
            validateModalBarcode(json.barcode);
        }
    } catch (e) {
        showToast('ບໍ່ສາມາດສ້າງບາໂຄດໄດ້', 'error');
    }
}

async function saveMultiBarcodeItem() {
    const productId = document.getElementById('mbProductId')?.value;
    const barcode = document.getElementById('mbBarcodeInput')?.value.trim();
    const type = document.getElementById('mbBarcodeType')?.value || 'CODE128';
    const unit = document.getElementById('mbUnitSelect')?.value || '';
    const multiplier = document.getElementById('mbMultiplierInput')?.value || 1;
    const price = document.getElementById('mbPriceInput')?.value || '';
    const isDefault = document.getElementById('mbIsDefaultChk')?.checked ? 1 : 0;

    if (!barcode) {
        showToast('ກະລຸນາປ້ອນລະຫັດບາໂຄດ', 'warning');
        return;
    }

    const payload = new FormData();
    payload.append('action', 'save_barcode');
    payload.append('product_id', productId);
    payload.append('barcode', barcode);
    payload.append('barcode_type', type);
    payload.append('unit', unit);
    payload.append('multiplier', multiplier);
    payload.append('price', price);
    payload.append('is_default', isDefault);

    try {
        const res = await fetch('api/barcodes.php', { method: 'POST', body: payload });
        const json = await res.json();
        if (json.success) {
            showToast('ບັນທຶກບາໂຄດສຳເລັດ', 'success');
            document.getElementById('mbBarcodeInput').value = '';
            document.getElementById('mbBarcodeAlert').style.display = 'none';
            await loadProductBarcodes(productId);
            loadProducts(); // refresh products list
        } else {
            showToast(json.message || 'ບັນທຶກບໍ່ສຳເລັດ', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
    }
}

async function setDefaultBarcodeItem(id, productId) {
    try {
        const payload = new FormData();
        payload.append('action', 'set_default');
        payload.append('id', id);
        const res = await fetch('api/barcodes.php', { method: 'POST', body: payload });
        const json = await res.json();
        if (json.success) {
            showToast('ຕັ້ງເປັນບາໂຄດຫຼັກຮຽບຮ້ອຍແລ້ວ', 'success');
            await loadProductBarcodes(productId);
            loadProducts();
        } else {
            showToast(json.message || 'ບໍ່ສາມາດຕັ້ງບາໂຄດຫຼັກໄດ້', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດ: ' + (e.message || 'ບໍ່ສາມາດເຊື່ອມຕໍ່ລະບົບ'), 'error');
    }
}

async function deleteMultiBarcodeItem(id, productId) {
    let confirmed = false;
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: 'ຢືນຢັນການລຶບບາໂຄດ?',
            text: 'ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບລາຍການບາໂຄດນີ້?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d32f2f',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: '<i class="fas fa-trash-alt"></i> ລຶບບາໂຄດ',
            cancelButtonText: 'ຍົກເລີກ',
            reverseButtons: true,
            customClass: { popup: 'swal2-lao-popup' }
        });
        confirmed = result.isConfirmed;
    } else {
        confirmed = window.confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບບາໂຄດນີ້?');
    }
    if (!confirmed) return;

    try {
        const payload = new FormData();
        payload.append('action', 'delete_barcode');
        payload.append('id', id);
        const res = await fetch('api/barcodes.php', { method: 'POST', body: payload });
        const json = await res.json();
        if (json.success) {
            showToast('ລຶບຮຽບຮ້ອຍແລ້ວ', 'success');
            await loadProductBarcodes(productId);
            loadProducts();
        } else {
            showToast(json.message || 'ບໍ່ສາມາດລຶບບາໂຄດໄດ້', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດ: ' + (e.message || 'ບໍ່ສາມາດເຊື່ອມຕໍ່ລະບົບ'), 'error');
    }
}

// ===================================================
// 7. Batch Auto-Assign Barcodes for Missing Items
// ===================================================
async function batchAssignMissingBarcodes() {
    let confirmed = false;
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: 'ສ້າງບາໂຄດອັດຕະໂນມັດ?',
            html: `
                <div style="font-size: 1rem; color: #475569; line-height: 1.7; margin: 8px 0 16px;">
                    ລະບົບຈະສ້າງບາໂຄດມາດຕະຖານ <b>CODE 128</b> ພາຍໃນຮ້ານ<br>
                    ໃຫ້ກັບສິນຄ້າທັງໝົດທີ່<b>ຍັງບໍ່ທັນມີບາໂຄດ</b>ໂດຍອັດຕະໂນມັດ.
                </div>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 12px 16px; font-size: 0.88rem; color: #166534; display: flex; align-items: center; gap: 12px; text-align: left;">
                    <i class="fas fa-barcode" style="font-size: 1.6rem; color: #16a34a; flex-shrink: 0;"></i>
                    <span>ສິນຄ້າທີ່ສ້າງແລ້ວ ຈະສາມາດນຳໄປພິມສະຕິກເກີ ແລະ ສະແກນຂາຍໜ້າຮ້ານໄດ້ທັນທີ!</span>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2e7d32',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: '<i class="fas fa-magic"></i> ສືບຕໍ່ສ້າງບາໂຄດ',
            cancelButtonText: '<i class="fas fa-times"></i> ຍົກເລີກ',
            reverseButtons: true,
            customClass: {
                popup: 'swal2-lao-popup'
            }
        });
        confirmed = result.isConfirmed;
    } else {
        confirmed = window.confirm('ລະບົບຈະສ້າງບາໂຄດ CODE 128 ພາຍໃນຮ້ານໃຫ້ສິນຄ້າທັງໝົດທີ່ຍັງບໍ່ມີບາໂຄດ ທ່ານຕ້ອງການສືບຕໍ່ບໍ່?');
    }

    if (!confirmed) return;

    try {
        if (typeof showToast === 'function') showToast('ກຳລັງສ້າງບາໂຄດໃຫ້ສິນຄ້າ...', 'info');
        const res = await fetch('api/barcodes.php?action=batch_assign_barcodes');
        const json = await res.json();
        if (json.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'ສຳເລັດແລ້ວ!',
                    text: json.message || 'ສ້າງບາໂຄດໃຫ້ສິນຄ້າທັງໝົດຮຽບຮ້ອຍແລ້ວ',
                    icon: 'success',
                    confirmButtonColor: '#2e7d32',
                    confirmButtonText: 'ຕົກລົງ',
                    customClass: { popup: 'swal2-lao-popup' }
                });
            } else {
                showToast(json.message, 'success');
            }
            loadProducts();
        } else {
            showToast(json.message || 'ບໍ່ສາມາດດຳເນີນການໄດ້', 'error');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
    }
}

// ===================================================
// 8. Printer Integration & Test Print
// ===================================================
function initPrinterConfig() {
    const savedPrinter = localStorage.getItem('pos_barcode_printer');
    const select = document.getElementById('cfgPrinterName');
    if (savedPrinter && select) {
        select.value = savedPrinter;
    }

    // If running in Desktop App, populate available system printers
    if (window.api && typeof window.api.getPrinters === 'function') {
        window.api.getPrinters().then(printers => {
            if (printers && printers.length > 0 && select) {
                select.innerHTML = '<option value="">-- ເລືອກເຄື່ອງພິມ --</option>' + 
                    printers.map(p => `<option value="${escapeHtml(p.name)}" ${p.name === savedPrinter ? 'selected' : ''}>${escapeHtml(p.name)}${p.isDefault ? ' (Default)' : ''}</option>`).join('');
            }
        });
    }
}

function savePrinterSettings() {
    const pName = document.getElementById('cfgPrinterName')?.value;
    if (pName) localStorage.setItem('pos_barcode_printer', pName);
    showToast('ບັນທຶກການຕັ້ງຄ່າເຄື່ອງພິມຮຽບຮ້ອຍແລ້ວ', 'success');
}

function executeTestPrint() {
    const testItem = [{
        name: 'ທົດສອບພິມສະຕິກເກີ TEST',
        sku: 'TEST-001',
        barcode: '8850000000001',
        price: 15000,
        unit: 'ດວງ'
    }];
    const tpl = activeTemplate || currentTemplates[0] || { width_mm: 40, height_mm: 30, show_name: 1, show_price: 1, show_barcode: 1, show_barcode_text: 1 };
    const html = buildStickersPrintHtml(testItem, tpl, 'portrait');

    const barcodePrinter = localStorage.getItem('pos_barcode_printer') || '';
    if (window.api && typeof window.api.printReceiptSilent === 'function') {
        window.api.printReceiptSilent({
            html: html,
            deviceName: barcodePrinter,
            landscape: false,
            pageSize: { width: Math.round(tpl.width_mm * 1000), height: Math.round(tpl.height_mm * 1000) }
        }).then(res => {
            showToast('ສົ່ງຄຳສັ່ງທົດສອບພິມສຳເລັດແລ້ວ', 'success');
        });
    } else {
        const w = window.open('', '_blank', 'width=800,height=800');
        if (w) {
            w.document.write(html + `<script>window.onload = function() { setTimeout(function() { window.print(); window.close(); }, 350); };<\/script>`);
            w.document.close();
        }
    }
}

// ===================================================
// Helper Utilities
// ===================================================
function generateFallbackBarcode(id) {
    return 'P' + String(id || 1).padStart(6, '0');
}

function detectBarcodeFormat(code) {
    if (/^[0-9]{13}$/.test(code)) return 'EAN13';
    if (/^[0-9]{8}$/.test(code)) return 'EAN8';
    if (/^[0-9]{12}$/.test(code)) return 'UPCA';
    return 'CODE128';
}

function formatCurrency(num) {
    return Number(num || 0).toLocaleString();
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
