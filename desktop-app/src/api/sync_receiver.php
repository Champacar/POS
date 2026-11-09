<?php
/**
 * Sync Receiver API - Standalone Mode
 * ລະບົບ Desktop App ແລະ Web App ແຍກອອກຈາກກັນຢ່າງສົມບູນ ບໍ່ມີການ Sync ຂໍ້ມູນ
 */

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'standalone' => true,
    'message' => 'ການ Sync ຖືກປິດໃຊ້ງານແລ້ວ (ລະບົບແຍກອອກຈາກກັນຢ່າງສົມບູນ Standalone)'
]);
exit;
