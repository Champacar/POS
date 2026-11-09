/**
 * POSPharma - Multi-Barcode Management & Validation Client Library
 */

function closeMultiBarcodeModal() {
    if (typeof closeModal === 'function') {
        closeModal('multiBarcodeModal');
    } else {
        const m = document.getElementById('multiBarcodeModal');
        if (m) {
            m.classList.remove('show');
            m.style.display = 'none';
        }
    }
}

async function openMultiBarcodeModal(productId, productName, baseUnit, basePrice) {
    const modal = document.getElementById('multiBarcodeModal');
    if (!modal) return;

    document.getElementById('mbProductId').value = productId;
    document.getElementById('mbProductName').innerText = productName || 'ສິນຄ້າ';
    document.getElementById('mbProductBaseUnit').innerText = baseUnit || '-';
    document.getElementById('mbProductBasePrice').innerText = Number(basePrice || 0).toLocaleString();
    document.getElementById('mbEditBarcodeId').value = '';
    document.getElementById('mbBarcodeInput').value = '';
    const alertBox = document.getElementById('mbBarcodeAlert');
    if (alertBox) alertBox.style.display = 'none';

    await loadProductBarcodes(productId);
    
    if (typeof openModal === 'function') {
        openModal('multiBarcodeModal');
    } else {
        modal.classList.add('show');
        modal.style.display = 'flex';
    }
}

async function loadProductBarcodes(productId) {
    const tbody = document.getElementById('multiBarcodeListTbody');
    if (!tbody) return;
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
                    <td><span class="badge badge-secondary" style="font-size:0.75rem;">${escapeHtml(b.barcode_type)}</span></td>
                    <td>${escapeHtml(b.unit || '-')}</td>
                    <td>x${b.multiplier || 1}</td>
                    <td>${b.price ? Number(b.price).toLocaleString() + ' ₭' : '<em style="color:#888;">ລາຄາຫຼັກ</em>'}</td>
                    <td>${b.is_default ? '<span class="badge badge-success" style="font-size:0.75rem;">ບາໂຄດຫຼັກ</span>' : '<span class="badge badge-light" style="font-size:0.75rem;">ສຳຮອງ</span>'}</td>
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
            const alertBox = document.getElementById('mbBarcodeAlert');
            if (alertBox) alertBox.style.display = 'none';
            await loadProductBarcodes(productId);
            if (typeof loadProducts === 'function') loadProducts();
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
            if (typeof loadProducts === 'function') loadProducts();
        }
    } catch (e) {}
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
            if (typeof loadProducts === 'function') loadProducts();
        }
    } catch (e) {}
}

// Helpers for Product Form in products.php
async function generateBarcodeForProductModal() {
    const prodId = document.getElementById('prodId')?.value || '';
    try {
        const res = await fetch(`api/barcodes.php?action=generate_barcode&type=CODE128&product_id=${prodId}`);
        const json = await res.json();
        if (json.success && json.barcode) {
            const input = document.getElementById('prodBarcode');
            if (input) {
                input.value = json.barcode;
                validateProductBarcode(json.barcode);
            }
        }
    } catch (e) {
        showToast('ບໍ່ສາມາດສ້າງບາໂຄດໄດ້', 'error');
    }
}

async function validateProductBarcode(val) {
    const alertBox = document.getElementById('productBarcodeAlert');
    if (!alertBox) return;

    val = (val || '').trim();
    if (!val) {
        alertBox.style.display = 'none';
        return;
    }

    try {
        const prodId = document.getElementById('prodId')?.value || '';
        const res = await fetch(`api/barcodes.php?action=check_duplicate&barcode=${encodeURIComponent(val)}&exclude_id=${prodId}`);
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
            alertBox.innerHTML = `<i class="fas fa-check-circle"></i> ບາໂຄດຖືກຕ້ອງ (${json.detected_type})`;
        }
    } catch (e) {}
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
