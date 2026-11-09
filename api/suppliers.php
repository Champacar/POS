<?php
/**
 * Suppliers API
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';
requireAPILogin();
setSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    switch ($method) {
        case 'GET':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
                $stmt->execute([$id]);
                $supplier = $stmt->fetch();
                if ($supplier) {
                    echo json_encode(['success' => true, 'supplier' => $supplier]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນຜູ້ສະໜອງ']);
                }
            } else {
                $stmt = $db->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC");
                echo json_encode(['success' => true, 'suppliers' => $stmt->fetchAll()]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['name'])) {
                echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນຊື່ຜູ້ສະໜອງ']);
                exit;
            }
            
            if (!empty($input['id'])) {
                // Update
                $stmt = $db->prepare("UPDATE suppliers SET name = ?, phone = ?, email = ?, address = ?, note = ? WHERE id = ?");
                $stmt->execute([
                    $input['name'],
                    $input['phone'] ?? null,
                    $input['email'] ?? null,
                    $input['address'] ?? null,
                    $input['note'] ?? null,
                    $input['id']
                ]);
                echo json_encode(['success' => true, 'message' => 'ແກ້ໄຂຂໍ້ມູນຜູ້ສະໜອງສຳເລັດ']);
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO suppliers (name, phone, email, address, note) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $input['name'],
                    $input['phone'] ?? null,
                    $input['email'] ?? null,
                    $input['address'] ?? null,
                    $input['note'] ?? null
                ]);
                echo json_encode(['success' => true, 'message' => 'ເພີ່ມຜູ້ສະໜອງສຳເລັດ', 'id' => $db->lastInsertId()]);
            }
            break;
            
        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID ບໍ່ຖືກຕ້ອງ']);
                exit;
            }
            
            // Soft delete supplier
            $db->prepare("UPDATE suppliers SET is_active = 0 WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'ລຶບຜູ້ສະໜອງສຳເລັດ']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
