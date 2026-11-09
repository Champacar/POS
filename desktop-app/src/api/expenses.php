<?php
/**
 * Expenses API
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';
requireAPILogin();
requireRoles(['admin', 'manager']);
setSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    switch ($method) {
        case 'GET':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $stmt = $db->prepare("SELECT * FROM expenses WHERE id = ?");
                $stmt->execute([$id]);
                $expense = $stmt->fetch();
                if ($expense) {
                    echo json_encode(['success' => true, 'expense' => $expense]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນ']);
                }
            } else {
                $startDate = $_GET['start'] ?? date('Y-m-01');
                $endDate = $_GET['end'] ?? date('Y-m-d');
                $stmt = $db->prepare("SELECT e.*, ec.name as category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC");
                $stmt->execute([$startDate, $endDate]);
                echo json_encode(['success' => true, 'expenses' => $stmt->fetchAll()]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['title']) || empty($input['amount'])) {
                echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນຂໍ້ມູນໃຫ້ຄົບ']);
                exit;
            }
            
            if (!empty($input['id'])) {
                $stmt = $db->prepare("UPDATE expenses SET category_id = ?, title = ?, amount = ?, description = ?, expense_date = ?, synced = 0 WHERE id = ?");
                $stmt->execute([
                    $input['category_id'] ?? null,
                    $input['title'],
                    $input['amount'],
                    $input['description'] ?? '',
                    $input['expense_date'] ?? date('Y-m-d'),
                    $input['id']
                ]);
                echo json_encode(['success' => true, 'message' => 'ແກ້ໄຂລາຍຈ່າຍສຳເລັດ']);
            } else {
                $uuid = uniqid('EXP-', true);
                $stmt = $db->prepare("INSERT INTO expenses (category_id, title, amount, description, expense_date, user_id, uuid, synced) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([
                    $input['category_id'] ?? null,
                    $input['title'],
                    $input['amount'],
                    $input['description'] ?? '',
                    $input['expense_date'] ?? date('Y-m-d'),
                    getCurrentUserId(),
                    $uuid
                ]);
                echo json_encode(['success' => true, 'message' => 'ບັນທຶກລາຍຈ່າຍສຳເລັດ', 'id' => $db->lastInsertId()]);
            }
            break;
            
        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID ບໍ່ຖືກຕ້ອງ']);
                exit;
            }
            
            $db->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'ລຶບລາຍຈ່າຍສຳເລັດ']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
