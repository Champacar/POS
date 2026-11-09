<?php
/**
 * Purchase Orders API
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
                // Fetch specific PO and its items
                $stmt = $db->prepare("
                    SELECT po.*, s.name as supplier_name, s.phone as supplier_phone, s.address as supplier_address
                    FROM purchase_orders po
                    LEFT JOIN suppliers s ON po.supplier_id = s.id
                    WHERE po.id = ?
                ");
                $stmt->execute([$id]);
                $po = $stmt->fetch();
                
                if ($po) {
                    $stmtItems = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?");
                    $stmtItems->execute([$id]);
                    $po['items'] = $stmtItems->fetchAll();
                    echo json_encode(['success' => true, 'po' => $po]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນໃບສັ່ງຊື້']);
                }
            } else {
                // Fetch all POs
                $stmt = $db->query("
                    SELECT po.*, s.name as supplier_name
                    FROM purchase_orders po
                    LEFT JOIN suppliers s ON po.supplier_id = s.id
                    ORDER BY po.created_at DESC
                ");
                echo json_encode(['success' => true, 'purchase_orders' => $stmt->fetchAll()]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['items'])) {
                echo json_encode(['success' => false, 'message' => 'ກະລຸນາເລືອກລາຍການຢາທີ່ຈະສັ່ງຊື້']);
                exit;
            }
            
            $db->beginTransaction();
            
            // Generate unique PO number: PO-YYYYMMDD-XXXX
            $poNumber = 'PO-' . date('Ymd') . '-' . rand(100, 999);
            
            $stmt = $db->prepare("INSERT INTO purchase_orders (po_number, supplier_id, total_amount, note, status) VALUES (?, ?, ?, ?, 'draft')");
            $stmt->execute([
                $poNumber,
                $input['supplier_id'] ?: null,
                $input['total_amount'] ?? 0,
                $input['note'] ?? ''
            ]);
            
            $poId = $db->lastInsertId();
            
            $stmtItem = $db->prepare("INSERT INTO purchase_order_items (po_id, product_id, product_name, quantity, unit_price) VALUES (?, ?, ?, ?, ?)");
            foreach ($input['items'] as $item) {
                $stmtItem->execute([
                    $poId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price']
                ]);
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'ສ້າງໃບສັ່ງຊື້ຢາສຳເລັດ',
                'po_id' => $poId
            ]);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $action = $input['action'] ?? $_GET['action'] ?? '';
            $id = (int)($input['id'] ?? $input['po_id'] ?? $_GET['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID ໃບສັ່ງຊື້ບໍ່ຖືກຕ້ອງ']);
                exit;
            }
            
            if ($action === 'receive' || ($input['status'] ?? '') === 'received') {
                $stmtPo = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
                $stmtPo->execute([$id]);
                $po = $stmtPo->fetch();
                
                if (!$po) {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນໃບສັ່ງຊື້']);
                    exit;
                }
                
                if (in_array($po['status'], ['received', 'cancelled'], true)) {
                    echo json_encode(['success' => false, 'message' => 'ໃບສັ່ງຊື້ນີ້ຖືກດຳເນີນການໄປແລ້ວ']);
                    exit;
                }
                
                $stmtItems = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?");
                $stmtItems->execute([$id]);
                $items = $stmtItems->fetchAll();
                
                $db->beginTransaction();
                
                $stmtUpdatePo = $db->prepare("UPDATE purchase_orders SET status = 'received' WHERE id = ?");
                $stmtUpdatePo->execute([$id]);
                
                $expiryDate = !empty($input['expiry_date']) ? $input['expiry_date'] : date('Y-m-d', strtotime('+1 year'));
                $batchNumber = !empty($input['batch_number']) ? $input['batch_number'] : $po['po_number'];
                $supplierId = !empty($po['supplier_id']) ? (int)$po['supplier_id'] : null;
                $userId = getCurrentUserId();
                
                $stmtGetProd = $db->prepare("SELECT selling_price, cost_price FROM products WHERE id = ?");
                $stmtUpdateProd = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ?, cost_price = ? WHERE id = ?");
                $stmtInsertBatch = $db->prepare("INSERT INTO product_batches (product_id, batch_number, cost_price, selling_price, initial_quantity, quantity, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtInsertHist = $db->prepare("INSERT INTO stock_history (product_id, type, quantity, reference, note, user_id, supplier_id) VALUES (?, 'in', ?, ?, ?, ?, ?)");
                
                foreach ($items as $item) {
                    $productId = (int)($item['product_id'] ?? 0);
                    $qty = (int)($item['quantity'] ?? 0);
                    $unitPrice = (float)($item['unit_price'] ?? 0);
                    
                    if ($productId > 0 && $qty > 0) {
                        $stmtGetProd->execute([$productId]);
                        $prodInfo = $stmtGetProd->fetch();
                        if ($prodInfo) {
                            $costPrice = $unitPrice > 0 ? $unitPrice : (float)$prodInfo['cost_price'];
                            $sellingPrice = (float)$prodInfo['selling_price'];
                            
                            $stmtUpdateProd->execute([$qty, $costPrice, $productId]);
                            $stmtInsertBatch->execute([
                                $productId,
                                $batchNumber,
                                $costPrice,
                                $sellingPrice,
                                $qty,
                                $qty,
                                $expiryDate
                            ]);
                            $stmtInsertHist->execute([
                                $productId,
                                $qty,
                                $po['po_number'],
                                'ຮັບສິນຄ້າຈາກໃບສັ່ງຊື້ ' . $po['po_number'],
                                $userId,
                                $supplierId
                            ]);
                        }
                    }
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'ຮັບສິນຄ້າເຂົ້າສາງສຳເລັດ']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ຄຳສັ່ງບໍ່ຖືກຕ້ອງ']);
            }
            break;
            
        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID ບໍ່ຖືກຕ້ອງ']);
                exit;
            }
            
            $db->beginTransaction();
            
            // Delete PO items and PO
            $stmt = $db->prepare("DELETE FROM purchase_orders WHERE id = ?");
            $stmt->execute([$id]);
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'ລຶບໃບສັ່ງຊື້ຢາສຳເລັດ']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
