/**
 * Medicine Label Printing Module
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

let currentLabelItems = [];
let activePreviewIndex = 0;

function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/[&<>'"]/g, 
        tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
    );
}

async function loadLabelPrinters() {
    const select = document.getElementById('labelPrinterSelect');
    if (!select) return;
    if (window.api && typeof window.api.getPrinters === 'function') {
        try {
            const printers = await window.api.getPrinters();
            select.innerHTML = '';
            const saved = localStorage.getItem('pos_label_printer') || localStorage.getItem('pos_barcode_printer') || localStorage.getItem('pos_selected_printer') || '';
            let selectedName = '';

            if (printers && printers.length > 0) {
                printers.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.name;
                    let tag = '';
                    const n = p.name.toLowerCase();
                    if (n.includes('grozziie') || n.includes('tp878') || n.includes('label') || n.includes('sticker')) {
                        tag = ' 🏷️ [ເຄື່ອງພິມສະຕິກເກີ/Label]';
                    } else if (n.includes('c80') || n.includes('pos') || n.includes('receipt') || n.includes('80')) {
                        tag = ' 🧾 [ເຄື່ອງພິມໃບບິນ 80mm]';
                    }
                    opt.textContent = p.name + tag + (p.isDefault ? ' (Default)' : '');
                    if (saved ? p.name === saved : (n.includes('grozziie') || n.includes('tp878') || n.includes('label'))) {
                        opt.selected = true;
                        selectedName = p.name;
                    }
                    select.appendChild(opt);
                });

                if (!selectedName) {
                    const best = printers.find(p => p.name.includes('Grozziie') || p.name.includes('TP878') || p.name.includes('Label')) || printers.find(p => p.name.includes('GP-')) || printers.find(p => p.isDefault) || printers[0];
                    if (best) {
                        select.value = best.name;
                        selectedName = best.name;
                    }
                }
            } else {
                select.innerHTML = '<option value="">ບໍ່ພົບເຄື່ອງພິມ</option>';
            }

            const hint = document.getElementById('labelPrinterStatusHint');
            if (hint && selectedName) {
                hint.textContent = 'ເຄື່ອງພິມ: ' + selectedName;
            }
        } catch (e) {
            console.error('Failed to get printers:', e);
            select.innerHTML = '<option value="">ເຄື່ອງພິມຂອງລະບົບ (Default)</option>';
        }
    } else {
        const grp = document.getElementById('labelPrinterGroup');
        if (grp) grp.style.display = 'none';
    }
}

function onLabelPrinterChange(val) {
    if (val) {
        localStorage.setItem('pos_label_printer', val);
        const hint = document.getElementById('labelPrinterStatusHint');
        if (hint) hint.textContent = 'ເຄື່ອງພິມ: ' + val;
    }
}

async function openLabelModal(saleId, customerName) {
    loadLabelPrinters();
    const patientInput = document.getElementById('labelPatientName');
    if (patientInput) {
        patientInput.value = (customerName && customerName !== '-') ? customerName : '';
    }
    const body = document.getElementById('labelItemsTableBody');
    if (body) {
        body.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> ກຳລັງໂຫຼດຂໍ້ມູນຢາ...</td></tr>`;
    }
    openModal('medicineLabelModal');

    try {
        const result = await fetchAPI(`${BASE_URL}api/sales.php?id=${saleId}`);
        if (result.success && result.sale && result.sale.items) {
            currentLabelItems = result.sale.items;
            renderLabelItemsTable();
        } else {
            showToast('ບໍ່ສາມາດດຶງຂໍ້ມູນລາຍການຂາຍໄດ້', 'error');
            closeModal('medicineLabelModal');
        }
    } catch (e) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່', 'error');
        closeModal('medicineLabelModal');
    }
}

function renderLabelItemsTable() {
    const body = document.getElementById('labelItemsTableBody');
    if (!body) return;
    body.innerHTML = '';

    if (!currentLabelItems || currentLabelItems.length === 0) {
        body.innerHTML = `<tr><td colspan="4" style="text-align:center; color:var(--text-light);">ບໍ່ມີລາຍການຢາ</td></tr>`;
        return;
    }

    currentLabelItems.forEach((item, index) => {
        const presets = [
            "ກິນເທື່ອລະ 1 ເມັດ, 3 ເວລາ ຫຼັງອາຫານ (ເຊົ້າ, ສວຍ, ແລງ)",
            "ກິນເທື່ອລະ 1 ເມັດ, 2 ເວລາ ຫຼັງອາຫານ (ເຊົ້າ, ແລງ)",
            "ກິນເທື່ອລະ 1 ເມັດ, 1 ເວລາ ຫຼັງອາຫານ (ເຊົ້າ)",
            "ກິນເທື່ອລະ 1 ເມັດ, 1 ເວລາ ກ່ອນນອນ",
            "ກິນເທື່ອລະ 2 ເມັດ, 3 ເວລາ ຫຼັງອາຫານ (ເຊົ້າ, ສວຍ, ແລງ)",
            "ຢອດຕາ ເທື່ອລະ 1-2 ຢອດ, 3-4 ເທື່ອຕໍ່ວັນ",
            "ທາບໍລິເວນທີ່ມີອາການ 2-3 ເທື່ອຕໍ່ວັນ",
            "ປ້ອນເອງ (Custom Instructions)"
        ];

        let optionsHtml = presets.map(p => `<option value="${p}">${p}</option>`).join('');

        body.innerHTML += `
            <tr style="vertical-align: middle; cursor:pointer;" onclick="updateLabelPreview(${index})">
                <td><strong>${escapeHTML(item.product_name)}</strong></td>
                <td>${item.quantity}</td>
                <td>
                    <select class="form-control" id="preset_${index}" onchange="toggleCustomInput(${index})" onfocus="updateLabelPreview(${index})" onclick="event.stopPropagation()" style="padding:6px; font-size:0.85rem; margin-bottom:5px; width: 100%;">
                        ${optionsHtml}
                    </select>
                    <input type="text" class="form-control" id="custom_usage_${index}" oninput="updateLabelPreview(${index})" onfocus="updateLabelPreview(${index})" onclick="event.stopPropagation()" style="display:none; font-size:0.85rem; padding:6px;" placeholder="ປ້ອນວິທີໃຊ້ດ້ວຍຕົນເອງ...">
                </td>
                <td style="text-align:right;">
                    <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); printLabel(${index})" style="padding:6px 12px; white-space: nowrap;">
                        <i class="fas fa-print"></i> ພິມສະຫຼາກ
                    </button>
                </td>
            </tr>
        `;
    });
    updateLabelPreview(0);
}

function updateLabelPreview(index) {
    if (typeof index === 'number') activePreviewIndex = index;
    const item = currentLabelItems[activePreviewIndex] || currentLabelItems[0];
    const patient = (document.getElementById('labelPatientName')?.value || '').trim() || '-';
    const sizeSelect = document.getElementById('labelStickerSize');
    const sizeVal = sizeSelect?.value || '8x5';
    const sizeMap = { '8x5': '8.0 x 5.0 ຊມ', '7x5': '7.0 x 5.0 ຊມ', '5x3': '5.0 x 3.0 ຊມ', '4x3': '4.0 x 3.0 ຊມ', '80mm_roll': '80mm Roll' };
    const badge = document.getElementById('labelPreviewSizeBadge');
    if (badge) badge.textContent = sizeMap[sizeVal] || sizeVal;

    const pEl = document.getElementById('labelPreviewPatient');
    const prodEl = document.getElementById('labelPreviewProduct');
    const usageEl = document.getElementById('labelPreviewUsage');
    if (pEl) pEl.textContent = 'ຊື່ຄົນເຈັບ: ' + patient;
    if (item) {
        if (prodEl) prodEl.textContent = `${item.product_name} (ຈຳນວນ: ${item.quantity})`;
        const sel = document.getElementById(`preset_${activePreviewIndex}`);
        let usage = sel ? sel.value : '';
        if (usage === 'ປ້ອນເອງ (Custom Instructions)') {
            usage = (document.getElementById(`custom_usage_${activePreviewIndex}`)?.value || '').trim() || 'ກິນຕາມແພດສັ່ງ';
        }
        if (usageEl) usageEl.textContent = 'ວິທີໃຊ້: ' + usage;
    }
}

function toggleCustomInput(index) {
    const select = document.getElementById(`preset_${index}`);
    const input = document.getElementById(`custom_usage_${index}`);
    if (!select || !input) return;
    if (select.value === 'ປ້ອນເອງ (Custom Instructions)') {
        input.style.display = 'block';
    } else {
        input.style.display = 'none';
    }
    updateLabelPreview(index);
}

async function printLabel(index) {
    const item = currentLabelItems[index];
    if (!item) return false;
    const patientName = encodeURIComponent(document.getElementById('labelPatientName')?.value.trim() || '');
    const size = document.getElementById('labelStickerSize')?.value || '8x5';
    const select = document.getElementById(`preset_${index}`);
    let usage = select ? select.value : '';
    if (usage === 'ປ້ອນເອງ (Custom Instructions)') {
        usage = (document.getElementById(`custom_usage_${index}`)?.value || '').trim() || 'ກິນຕາມແພດສັ່ງ';
    }
    usage = encodeURIComponent(usage);

    const productName = encodeURIComponent(item.product_name);
    const qty = item.quantity;
    const productId = item.product_id || 0;

    const url = `${BASE_URL}api/medicine_label.php?product_id=${productId}&product_name=${productName}&qty=${qty}&patient_name=${patientName}&usage=${usage}&size=${size}&autoprint=1`;
    const labelPrinter = localStorage.getItem('pos_label_printer') || '';
    
    // In Desktop App, use silent print directly if available to bypass Windows print dialog
    if (window.api && typeof window.api.printReceiptSilent === 'function') {
        showToast(`ກຳລັງສັ່ງພິມສະຫຼາກ: ${item.product_name}...`, 'info');
        const res = await window.api.printReceiptSilent({
            url: url,
            deviceName: labelPrinter
        });
        if (res && res.success) {
            showToast(`ພິມສະຫຼາກ ${item.product_name} ສຳເລັດ`, 'success');
            return true;
        }
    }

    // Fallback or Web App (Open popup)
    const printWindow = window.open(url, '_blank', 'width=420,height=520');
    if (printWindow) { 
        printWindow.focus(); 
        showToast(`ກຳລັງເປີດພິມສະຫຼາກ: ${item.product_name}...`, 'info');
        return true;
    } else {
        const iframe = document.getElementById('printFrame');
        if (iframe) iframe.src = url;
        showToast(`ກຳລັງສັ່ງພິມສະຫຼາກ: ${item.product_name}...`, 'info');
        return true;
    }
}

async function printAllLabels() {
    if (!currentLabelItems || currentLabelItems.length === 0) {
        showToast('ບໍ່ມີລາຍການຢາທີ່ຕ້ອງພິມ', 'warning');
        return;
    }
    const btn = document.getElementById('btnPrintAllLabels');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງພິມ...';
    }
    try {
        let successCount = 0;
        for (let i = 0; i < currentLabelItems.length; i++) {
            const ok = await printLabel(i);
            if (ok) successCount++;
            if (i < currentLabelItems.length - 1) {
                await new Promise(r => setTimeout(r, 600));
            }
        }
        showToast(`ພິມສຳເລັດ ${successCount}/${currentLabelItems.length} ລາຍການ`, 'success');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadLabelPrinters();
});
