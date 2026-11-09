<!-- Multi-Barcode Management Modal (Shared across Products and Barcode Manager) -->
<div class="modal-overlay" id="multiBarcodeModal" style="z-index:9999;" onclick="if(event.target===this)closeMultiBarcodeModal()">
    <div class="modal" style="max-width:700px;width:95%;">
        <div class="modal-header">
            <h3 style="margin:0;font-size:1.15rem;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-barcode" style="color:var(--primary);"></i> ຈັດການຫຼາຍບາໂຄດຕໍ່ສິນຄ້າ (Multi-Barcodes)
            </h3>
            <button type="button" class="modal-close" onclick="closeMultiBarcodeModal()"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="modal-body" style="padding:20px;max-height:75vh;overflow-y:auto;">
            <div style="background:#f4f6f8;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
                <strong id="mbProductName" style="font-size:1.05rem;color:var(--text-dark);">ຊື່ສິນຄ້າ</strong>
                <div style="font-size:0.82rem;color:var(--text-medium);margin-top:2px;">
                    ໜ່ວຍຫຼັກ: <span id="mbProductBaseUnit">-</span> • ລາຄາຫຼັກ: <span id="mbProductBasePrice">0</span> ກີບ
                </div>
            </div>

            <!-- Existing Barcodes Table -->
            <h4 style="font-size:0.9rem;margin:0 0 10px 0;color:var(--text-dark);">ລາຍການບາໂຄດທັງໝົດຂອງສິນຄ້ານີ້:</h4>
            <div style="overflow-x:auto;margin-bottom:20px;">
                <table class="table" style="width:100%;font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th>ບາໂຄດ</th>
                            <th>ປະເພດ</th>
                            <th>ໜ່ວຍສິນຄ້າ</th>
                            <th>ອັດຕາຄູນ</th>
                            <th>ລາຄາຂາຍ</th>
                            <th>ສະຖານະ</th>
                            <th style="text-align:right;">ຈັດການ</th>
                        </tr>
                    </thead>
                    <tbody id="multiBarcodeListTbody">
                        <!-- Loaded via JS -->
                    </tbody>
                </table>
            </div>

            <!-- Add / Edit Barcode Form -->
            <div style="border-top:1px dashed #ddd;padding-top:16px;">
                <h4 style="font-size:0.95rem;margin:0 0 12px 0;color:var(--primary);" id="mbFormTitle">+ ເພີ່ມບາໂຄດໃໝ່ໃຫ້ສິນຄ້ານີ້:</h4>
                <input type="hidden" id="mbEditBarcodeId" value="">
                <input type="hidden" id="mbProductId" value="">

                <div style="display:grid;grid-template-columns:1.8fr 1fr;gap:12px;margin-bottom:12px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">ລະຫັດບາໂຄດ (Barcode)</label>
                        <div style="display:flex;gap:6px;">
                            <input type="text" id="mbBarcodeInput" class="form-control" placeholder="ປ້ອນ ຫຼື ສະແກນບາໂຄດ..." oninput="validateModalBarcode(this.value)">
                            <button type="button" class="btn btn-outline" onclick="generateBarcodeForModal()" title="ສ້າງບາໂຄດອັດຕະໂນມັດ" style="white-space:nowrap;">
                                <i class="fas fa-magic"></i> ສ້າງ
                            </button>
                        </div>
                        <div id="mbBarcodeAlert" style="font-size:0.75rem;margin-top:4px;display:none;"></div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">ປະເພດມາດຕະຖານ</label>
                        <select id="mbBarcodeType" class="form-control" onchange="validateModalBarcode(document.getElementById('mbBarcodeInput').value)">
                            <option value="CODE128">CODE 128 (ມາດຕະຖານຮ້ານຄ້າ)</option>
                            <option value="EAN13">EAN-13 (13 ຫຼັກ)</option>
                            <option value="EAN8">EAN-8 (8 ຫຼັກ)</option>
                            <option value="UPCA">UPC-A (12 ຫຼັກ)</option>
                            <option value="QR">QR Code</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr 1.2fr;gap:12px;margin-bottom:12px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">ໜ່ວຍສິນຄ້າ</label>
                        <select id="mbUnitSelect" class="form-control">
                            <?php 
                            $unitNames = [];
                            if (isset($units) && !empty($units)) {
                                foreach ($units as $u) {
                                    $unitNames[] = is_array($u) ? ($u['name'] ?? '') : (string)$u;
                                }
                            } else {
                                try {
                                    $unitNames = getDB()->query("SELECT name FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
                                } catch(Exception $e) { 
                                    $unitNames = ['ແຜງ', 'ກ່ອງ', 'ຂວດ', 'ອັນ']; 
                                }
                            }
                            foreach ($unitNames as $uName): 
                                if (empty($uName)) continue;
                            ?>
                                <option value="<?= sanitize($uName) ?>"><?= sanitize($uName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">ອັດຕາສ່ວນ (Multiplier)</label>
                        <input type="number" id="mbMultiplierInput" class="form-control" value="1" min="1">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">ລາຄາຂາຍສະເພາະໜ່ວຍນີ້ (ກີບ)</label>
                        <input type="number" id="mbPriceInput" class="form-control" placeholder="ປ່ອຍວ່າງ = ລາຄາຫຼັກ">
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;">
                    <label style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-size:0.85rem;">
                        <input type="checkbox" id="mbIsDefaultChk"> ຕັ້ງເປັນບາໂຄດຫຼັກ (Default Barcode)
                    </label>
                    <button type="button" class="btn btn-primary" onclick="saveMultiBarcodeItem()">
                        <i class="fas fa-save"></i> ບັນທຶກບາໂຄດ
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
