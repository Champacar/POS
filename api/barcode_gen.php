<?php
/**
 * Barcode Generator API
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 * 
 * Generates EAN-13 compatible barcodes and assigns them to products
 */

require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    switch ($method) {
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
            
            if ($action === 'generate') {
                // Generate a unique EAN-13 barcode
                $barcode = generateUniqueBarcode($db);
                echo json_encode(['success' => true, 'barcode' => $barcode]);
                
            } else if ($action === 'assign') {
                // Assign barcode to a product
                $productId = (int)($input['product_id'] ?? 0);
                $barcode = trim($input['barcode'] ?? '');
                
                if ($productId <= 0 || empty($barcode)) {
                    echo json_encode(['success' => false, 'message' => 'ຂໍ້ມູນບໍ່ຖືກຕ້ອງ']);
                    exit;
                }
                
                // Check if barcode is already used by another product
                $stmt = $db->prepare("SELECT id FROM products WHERE barcode = ? AND id != ?");
                $stmt->execute([$barcode, $productId]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'ບາໂຄດນີ້ຖືກໃຊ້ແລ້ວ']);
                    exit;
                }
                
                $stmt = $db->prepare("UPDATE products SET barcode = ? WHERE id = ?");
                $stmt->execute([$barcode, $productId]);
                
                echo json_encode(['success' => true, 'message' => 'ກຳນົດບາໂຄດສຳເລັດ']);
                
            } else {
                echo json_encode(['success' => false, 'message' => 'Action ບໍ່ຖືກຕ້ອງ']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}

/**
 * Generate a unique EAN-13 barcode
 */
function generateUniqueBarcode($db) {
    $maxAttempts = 100;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $barcode = generateEAN13();
        $stmt = $db->prepare("SELECT id FROM products WHERE barcode = ?");
        $stmt->execute([$barcode]);
        if (!$stmt->fetch()) {
            return $barcode;
        }
    }
    throw new Exception('ບໍ່ສາມາດສ້າງບາໂຄດໄດ້');
}

/**
 * Generate a random EAN-13 barcode with valid check digit
 */
function generateEAN13() {
    // Use prefix 200-299 (internal use range)
    $prefix = '2' . str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
    
    // Generate 9 random digits
    $body = '';
    for ($i = 0; $i < 9; $i++) {
        $body .= rand(0, 9);
    }
    
    $code = $prefix . $body;
    
    // Calculate EAN-13 check digit
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$code[$i] * ($i % 2 === 0 ? 1 : 3);
    }
    $checkDigit = (10 - ($sum % 10)) % 10;
    
    return $code . $checkDigit;
}
