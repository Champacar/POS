<?php
/**
 * Units API
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';
requireAPILogin();
setSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນຊື່ຫົວໜ່ວຍ']);
            exit;
        }
        
        // Check if exists
        $stmt = $db->prepare("SELECT id FROM units WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'ຫົວໜ່ວຍນີ້ມີຢູ່ແລ້ວ']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO units (name) VALUES (?)");
        $stmt->execute([$name]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'ເພີ່ມຫົວໜ່ວຍສຳເລັດ',
            'unit' => [
                'id' => $db->lastInsertId(),
                'name' => $name
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
