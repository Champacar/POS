<?php
/**
 * Medicine Label Print Modal Component
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */
$modalSettings = $settings ?? [];
if (empty($modalSettings)) {
    try {
        $modalSettings = getShopSettings();
    } catch (Exception $e) {
        $modalSettings = [];
    }
}
?>
<!-- Medicine Label Print Modal -->
<div class="modal-overlay" id="medicineLabelModal" style="z-index: 2600;">
    <div class="modal" style="max-width:750px;">
        <div class="modal-header">
            <h3><i class="fas fa-prescription-bottle-alt" style="margin-right:8px; color:var(--primary);"></i>ພິມສະຫຼາກຢາ</h3>
            <button class="modal-close" aria-label="ປິດໜ້າຕ່າງພິມສະຫຼາກຢາ" onclick="closeModal('medicineLabelModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-bottom:16px;">
                <div class="form-group" style="margin-bottom:0; flex:1.8; min-width:180px;">
                    <label class="form-label">ຊື່ຄົນເຈັບ (Patient Name)</label>
                    <input type="text" class="form-control" id="labelPatientName" placeholder="ປ້ອນຊື່ຄົນເຈັບ..." oninput="updateLabelPreview()">
                </div>
                <div class="form-group" style="margin-bottom:0; flex:1.2; min-width:150px;">
                    <label class="form-label">ຂະໜາດເຈ້ຍສະຫຼາກ</label>
                    <select class="form-control" id="labelStickerSize" onchange="updateLabelPreview()">
                        <option value="8x5">8.0 x 5.0 ຊມ (ຂະໜາດມາດຕະຖານ)</option>
                        <option value="7x5">7.0 x 5.0 ຊມ (ຂະໜາດກາງ)</option>
                        <option value="5x3">5.0 x 3.0 ຊມ (ສະຕິກເກີ 5x3)</option>
                        <option value="4x3">4.0 x 3.0 ຊມ (ສະຕິກເກີ 4x3)</option>
                        <option value="80mm_roll">ເຈ້ຍມ້ວນໃບບິນ 80mm (Thermal Roll)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0; flex:1.4; min-width:160px;" id="labelPrinterGroup">
                    <label class="form-label"><i class="fas fa-print" style="color:var(--primary);margin-right:4px;"></i>ເລືອກເຄື່ອງພິມ</label>
                    <select class="form-control" id="labelPrinterSelect" onchange="onLabelPrinterChange(this.value)">
                        <option value="">ກຳລັງໂຫຼດເຄື່ອງພິມ...</option>
                    </select>
                </div>
            </div>

            <!-- Live Medicine Label Preview -->
            <div style="background:#f8faf9; border:1px dashed var(--border-color); border-radius:var(--radius-md); padding:12px; margin-bottom:15px;">
                <div style="font-size:0.8rem; color:var(--text-light); margin-bottom:8px; display:flex; justify-content:space-between;">
                    <span><i class="fas fa-eye"></i> ຕົວຢ່າງສະຫຼາກຢາ (Preview)</span>
                    <span id="labelPreviewSizeBadge">8.0 x 5.0 ຊມ</span>
                </div>
                <div id="labelPreviewBox" style="background:#fff; border:2px solid #263238; border-radius:4px; padding:10px 12px; max-width:320px; margin:0 auto; font-size:0.82rem; line-height:1.45; color:#000;">
                    <div style="border-bottom:1px solid #000; padding-bottom:4px; margin-bottom:6px; font-weight:700; display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <?php if (!empty($modalSettings['logo']) && file_exists(__DIR__ . '/../assets/images/' . $modalSettings['logo'])): ?>
                                <img src="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($modalSettings['logo']) ?>" style="max-height:22px; max-width:36px; object-fit:contain;" alt="Logo">
                            <?php endif; ?>
                            <span><?= htmlspecialchars($modalSettings['shop_name'] ?? SITE_NAME) ?></span>
                        </div>
                        <span style="font-weight:400; font-size:0.75rem;"><?= date('d/m/Y') ?></span>
                    </div>
                    <div id="labelPreviewPatient" style="font-size:0.78rem; margin-bottom:4px;">ຊື່ຄົນເຈັບ: -</div>
                    <div id="labelPreviewProduct" style="font-weight:700; font-size:0.9rem; margin-bottom:4px;">ຊື່ຢາ: -</div>
                    <div id="labelPreviewUsage" style="background:#f4f6f8; padding:4px 6px; border-radius:4px; font-weight:600;">ວິທີໃຊ້: -</div>
                </div>
            </div>
            
            <div class="table-responsive" style="margin-top:15px; max-height:350px; overflow-y:auto;">
                <table class="table" style="font-size:0.9rem;">
                    <thead>
                        <tr>
                            <th>ຊື່ຢາ</th>
                            <th>ຈຳນວນ</th>
                            <th>ວິທີໃຊ້ / ວິທີກິນ</th>
                            <th style="text-align:right;">ຈັດການ</th>
                        </tr>
                    </thead>
                    <tbody id="labelItemsTableBody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="font-size:12px; color:var(--text-light); display:flex; align-items:center; gap:6px;">
                <i class="fas fa-check-circle" style="color:var(--primary);"></i>
                <span id="labelPrinterStatusHint">ພ້ອມສັ່ງພິມສະຫຼາກຢາ</span>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn btn-primary" id="btnPrintAllLabels" onclick="printAllLabels()"><i class="fas fa-print"></i> ພິມທຸກລາຍການ</button>
                <button type="button" class="btn btn-white" onclick="closeModal('medicineLabelModal')">ຍົກເລີກ</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden iframe for silent printing -->
<iframe id="printFrame" style="position:absolute; width:0; height:0; border:0;" title="Print Frame"></iframe>

