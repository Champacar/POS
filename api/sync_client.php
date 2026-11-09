<?php
/**
 * Local Sync Client API - Standalone Mode
 * ລະບົບ Desktop App ແລະ Web App ແຍກອອກຈາກກັນຢ່າງສົມບູນ ບໍ່ມີການ Sync ຂໍ້ມູນ
 */

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'standalone' => true,
    'message' => 'ລະບົບຢູ່ໃນໂໝດ Standalone ແຍກອອກຈາກກັນຢ່າງສົມບູນ (ບໍ່ມີການ Sync ຂໍ້ມູນ)'
]);
exit;
