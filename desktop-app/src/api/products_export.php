<?php
/**
 * Products Export & Template API
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once __DIR__ . '/../includes/functions.php';
requireLogin();
setSecurityHeaders();

$action = $_GET['action'] ?? 'export';
$format = $_GET['format'] ?? 'csv';

// Download Template
if ($action === 'template') {
    $filename = 'template_import_products.csv';
    $headers = [
        'ບາໂຄດ',
        'ຊື່ສິນຄ້າ',
        'ໝວດໝູ່',
        'ລາຄາທຶນ',
        'ລາຄາຂາຍ',
        'ຈຳນວນສະຕ໋ອກ',
        'ຂັ້ນຕ່ຳສະຕ໋ອກ',
        'ຫົວໜ່ວຍ',
        'ວັນໝົດອາຍຸ'
    ];
    $sampleRows = [
        ['8850000000001', 'ພາຣາເຊຕາໂມນ 500mg (Paracetamol)', 'ຢາແກ້ປວດ', 15000, 20000, 100, 10, 'ແຜງ', '2027-12-31'],
        ['8850000000002', 'ໄອບູໂພຣເຟັນ 400mg (Ibuprofen)', 'ຢາແກ້ປວດ', 20000, 30000, 80, 10, 'ແຜງ', '2027-10-15'],
        ['8850000000003', 'ອາໂມຊີຊິລິນ 500mg (Amoxicillin)', 'ຢາຕ້ານເຊື້ອ', 25000, 40000, 50, 5, 'ແຜງ', '2027-08-20'],
        ['8850000000004', 'ວິຕາມິນ C 1000mg', 'ວິຕາມິນ', 30000, 45000, 40, 5, 'ກ່ອງ', '2028-01-30'],
        ['8850000000005', 'ຢາແກ້ໄອ Dextromethorphan', 'ຢາແກ້ໄອ', 10000, 15000, 20, 5, 'ຂວດ', '2027-05-10'],
    ];
    exportCSV($filename, $headers, $sampleRows);
    exit;
}

// Export Products
try {
    $db = getDB();
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.is_active = 1 
            ORDER BY p.name";
    $products = $db->query($sql)->fetchAll();
    
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'products' => $products]);
        exit;
    }
    
    // CSV Export default
    $filename = 'products_pospharma_' . date('Y-m-d') . '.csv';
    $headers = [
        'ບາໂຄດ',
        'ຊື່ສິນຄ້າ',
        'ໝວດໝູ່',
        'ລາຄາທຶນ',
        'ລາຄາຂາຍ',
        'ຈຳນວນສະຕ໋ອກ',
        'ຂັ້ນຕ່ຳສະຕ໋ອກ',
        'ຫົວໜ່ວຍ',
        'ວັນໝົດອາຍຸ'
    ];
    
    $rows = [];
    foreach ($products as $p) {
        $rows[] = [
            $p['barcode'] ?? '',
            $p['name'] ?? '',
            $p['category_name'] ?? 'ອື່ນໆ',
            (float)($p['cost_price'] ?? 0),
            (float)($p['selling_price'] ?? 0),
            (int)($p['stock_quantity'] ?? 0),
            (int)($p['min_stock'] ?? 5),
            $p['unit'] ?? 'ແຜງ',
            $p['expiry_date'] ?? ''
        ];
    }
    
    exportCSV($filename, $headers, $rows);
} catch (Exception $e) {
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
    } else {
        echo "ເກີດຂໍ້ຜິດພາດໃນການສົ່ງອອກ: " . htmlspecialchars($e->getMessage());
    }
}
