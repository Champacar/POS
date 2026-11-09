<?php
/**
 * POSPharma - Barcode Stock Audit & Physical Stock Take API
 * ລະບົບກວດນັບສະຕ໋ອກດ້ວຍບາໂຄດ & ປັບປຸງຍອດສະຕ໋ອກອັດຕະໂນມັດ 100% Offline
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ']);
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$userId = $_SESSION['user_id'] ?? 1;

try {
    // ----------------------------------------------------
    // Action: Get Current Active Audit Session
    // ----------------------------------------------------
    if ($action === 'current_session') {
        $stmt = $db->query("
            SELECT * FROM stock_audits 
            WHERE status = 'in_progress' 
            ORDER BY id DESC LIMIT 1
        ");
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            echo json_encode(['success' => true, 'has_active' => false]);
            exit;
        }

        // Fetch audit items
        $stmtItems = $db->prepare("
            SELECT sai.*, p.category_id, c.name as category_name
            FROM stock_audit_items sai
            LEFT JOIN products p ON sai.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE sai.audit_id = ?
            ORDER BY sai.scanned_at DESC, sai.id ASC
        ");
        $stmtItems->execute([$session['id']]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // Calculate summary
        $totalProducts = count($items);
        $countedProducts = 0;
        $matchedProducts = 0;
        $discrepancyProducts = 0;
        $missingCount = 0;
        $excessCount = 0;
        $netCostVariance = 0;

        foreach ($items as $it) {
            $counted = (int)$it['counted_quantity'];
            $variance = (int)$it['variance'];
            $costVar = (float)$it['cost_variance'];

            if ($counted > 0) {
                $countedProducts++;
            }
            if ($variance === 0 && $counted > 0) {
                $matchedProducts++;
            } elseif ($variance < 0) {
                $discrepancyProducts++;
                $missingCount += abs($variance);
                $netCostVariance += $costVar;
            } elseif ($variance > 0) {
                $discrepancyProducts++;
                $excessCount += $variance;
                $netCostVariance += $costVar;
            }
        }

        echo json_encode([
            'success' => true,
            'has_active' => true,
            'session' => $session,
            'summary' => [
                'total_products' => $totalProducts,
                'counted_products' => $countedProducts,
                'matched_products' => $matchedProducts,
                'discrepancy_products' => $discrepancyProducts,
                'missing_count' => $missingCount,
                'excess_count' => $excessCount,
                'net_cost_variance' => $netCostVariance
            ],
            'items' => $items
        ]);
        exit;
    }

    // ----------------------------------------------------
    // Action: Start New Audit Session
    // ----------------------------------------------------
    if ($action === 'start_session') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $title = trim($input['title'] ?? 'ກວດນັບສະຕ໋ອກປະຈຳວັນ ' . date('d/m/Y H:i'));
        $note = trim($input['note'] ?? '');
        $categoryId = !empty($input['category_id']) ? (int)$input['category_id'] : 0;

        // Check if there is already an in_progress session
        $existing = $db->query("SELECT id FROM stock_audits WHERE status = 'in_progress' LIMIT 1")->fetchColumn();
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'ມີຮອບກວດນັບສະຕ໋ອກທີ່ກຳລັງດຳເນີນການຢູ່ແລ້ວ']);
            exit;
        }

        $db->beginTransaction();

        // Generate Audit Code: AUD-YYYYMMDD-XXX
        $prefix = 'AUD-' . date('Ymd') . '-';
        $cntStmt = $db->prepare("SELECT COUNT(*) FROM stock_audits WHERE audit_code LIKE ?");
        $cntStmt->execute([$prefix . '%']);
        $cnt = (int)$cntStmt->fetchColumn() + 1;
        $auditCode = $prefix . str_pad($cnt, 3, '0', STR_PAD_LEFT);

        $stmtAudit = $db->prepare("
            INSERT INTO stock_audits (audit_code, title, status, created_by, note, created_at)
            VALUES (?, ?, 'in_progress', ?, ?, NOW())
        ");
        $stmtAudit->execute([$auditCode, $title, $userId, $note]);
        $auditId = $db->lastInsertId();

        // Fetch products to audit
        $sql = "SELECT id, barcode, name, unit, stock_quantity, cost_price FROM products WHERE is_active = 1";
        $params = [];
        if ($categoryId > 0) {
            $sql .= " AND category_id = ?";
            $params[] = $categoryId;
        }
        $sql .= " ORDER BY name ASC";

        $pStmt = $db->prepare($sql);
        $pStmt->execute($params);
        $products = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        $itemInsert = $db->prepare("
            INSERT INTO stock_audit_items (audit_id, product_id, barcode, product_name, unit, system_quantity, counted_quantity, variance, cost_price, cost_variance)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
        ");

        foreach ($products as $p) {
            $sysQty = (int)$p['stock_quantity'];
            $costPrice = (float)$p['cost_price'];
            $variance = 0 - $sysQty; // Initially counted is 0
            $costVar = $variance * $costPrice;

            $itemInsert->execute([
                $auditId,
                $p['id'],
                $p['barcode'] ?: '',
                $p['name'],
                $p['unit'] ?: 'ແຜງ',
                $sysQty,
                $variance,
                $costPrice,
                $costVar
            ]);
        }

        // Update total items count on audit header
        $db->prepare("UPDATE stock_audits SET total_items = ? WHERE id = ?")->execute([count($products), $auditId]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'ເລີ່ມຮອບກວດນັບສະຕ໋ອກຮຽບຮ້ອຍແລ້ວ',
            'audit_id' => $auditId,
            'audit_code' => $auditCode,
            'total_items' => count($products)
        ]);
        exit;
    }

    // ----------------------------------------------------
    // Action: Scan Barcode / Add Count
    // ----------------------------------------------------
    if ($action === 'scan') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $auditId = (int)($input['audit_id'] ?? 0);
        $barcode = trim($input['barcode'] ?? '');
        $productId = (int)($input['product_id'] ?? 0);
        $addQty = isset($input['quantity']) ? (int)$input['quantity'] : 1;

        if ($auditId <= 0) {
            // Find active audit
            $auditId = (int)$db->query("SELECT id FROM stock_audits WHERE status = 'in_progress' ORDER BY id DESC LIMIT 1")->fetchColumn();
            if ($auditId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຮອບກວດນັບສະຕ໋ອກທີ່ເປີດຢູ່']);
                exit;
            }
        }

        // Find item in stock_audit_items by barcode or productId or product_barcodes table
        $targetItem = null;
        if ($productId > 0) {
            $stmt = $db->prepare("SELECT * FROM stock_audit_items WHERE audit_id = ? AND product_id = ? LIMIT 1");
            $stmt->execute([$auditId, $productId]);
            $targetItem = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (!empty($barcode)) {
            // Direct barcode match
            $stmt = $db->prepare("SELECT * FROM stock_audit_items WHERE audit_id = ? AND barcode = ? LIMIT 1");
            $stmt->execute([$auditId, $barcode]);
            $targetItem = $stmt->fetch(PDO::FETCH_ASSOC);

            // If not found, check product_barcodes multi-barcode table
            if (!$targetItem) {
                $pId = (int)$db->prepare("SELECT product_id FROM product_barcodes WHERE barcode = ? AND is_active = 1 LIMIT 1");
                $pId->execute([$barcode]);
                $foundPid = (int)$pId->fetchColumn();
                if ($foundPid > 0) {
                    $stmt2 = $db->prepare("SELECT * FROM stock_audit_items WHERE audit_id = ? AND product_id = ? LIMIT 1");
                    $stmt2->execute([$auditId, $foundPid]);
                    $targetItem = $stmt2->fetch(PDO::FETCH_ASSOC);
                }
            }
        }

        if (!$targetItem) {
            echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບສິນຄ້າທີ່ມີບາໂຄດນີ້ໃນຮອບກວດນັບ']);
            exit;
        }

        $newCount = (int)$targetItem['counted_quantity'] + $addQty;
        $sysQty = (int)$targetItem['system_quantity'];
        $costPrice = (float)$targetItem['cost_price'];
        $newVariance = $newCount - $sysQty;
        $newCostVar = $newVariance * $costPrice;

        $updateStmt = $db->prepare("
            UPDATE stock_audit_items 
            SET counted_quantity = ?, variance = ?, cost_variance = ?, scanned_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$newCount, $newVariance, $newCostVar, $targetItem['id']]);

        echo json_encode([
            'success' => true,
            'message' => "ນັບ '{$targetItem['product_name']}' ແລ້ວ {$newCount} {$targetItem['unit']}",
            'item' => [
                'id' => $targetItem['id'],
                'product_id' => $targetItem['product_id'],
                'product_name' => $targetItem['product_name'],
                'barcode' => $targetItem['barcode'],
                'unit' => $targetItem['unit'],
                'system_quantity' => $sysQty,
                'counted_quantity' => $newCount,
                'variance' => $newVariance,
                'cost_price' => $costPrice,
                'cost_variance' => $newCostVar
            ]
        ]);
        exit;
    }

    // ----------------------------------------------------
    // Action: Set Item Quantity Directly (Manual Edit)
    // ----------------------------------------------------
    if ($action === 'set_quantity') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $itemId = (int)($input['item_id'] ?? 0);
        $counted = max(0, (int)($input['counted_quantity'] ?? 0));

        $stmt = $db->prepare("SELECT * FROM stock_audit_items WHERE id = ?");
        $stmt->execute([$itemId]);
        $targetItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetItem) {
            echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບລາຍການ']);
            exit;
        }

        $sysQty = (int)$targetItem['system_quantity'];
        $costPrice = (float)$targetItem['cost_price'];
        $newVariance = $counted - $sysQty;
        $newCostVar = $newVariance * $costPrice;

        $updateStmt = $db->prepare("
            UPDATE stock_audit_items 
            SET counted_quantity = ?, variance = ?, cost_variance = ?, scanned_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$counted, $newVariance, $newCostVar, $itemId]);

        echo json_encode([
            'success' => true,
            'message' => 'ອັບເດດຈຳນວນຮຽບຮ້ອຍແລ້ວ',
            'variance' => $newVariance,
            'cost_variance' => $newCostVar
        ]);
        exit;
    }

    // ----------------------------------------------------
    // Action: Reconcile Stock (1-Click Update System Stock)
    // ----------------------------------------------------
    if ($action === 'reconcile') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $auditId = (int)($input['audit_id'] ?? 0);

        if ($auditId <= 0) {
            $auditId = (int)$db->query("SELECT id FROM stock_audits WHERE status = 'in_progress' ORDER BY id DESC LIMIT 1")->fetchColumn();
        }

        $stmtAudit = $db->prepare("SELECT * FROM stock_audits WHERE id = ? AND status = 'in_progress'");
        $stmtAudit->execute([$auditId]);
        $audit = $stmtAudit->fetch(PDO::FETCH_ASSOC);

        if (!$audit) {
            echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຮອບກວດນັບທີ່ກຳລັງເປີດຢູ່']);
            exit;
        }

        $db->beginTransaction();

        $stmtItems = $db->prepare("SELECT * FROM stock_audit_items WHERE audit_id = ?");
        $stmtItems->execute([$auditId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $reconciledCount = 0;
        $totalCostVar = 0;
        $matchedCount = 0;
        $discrepancyCount = 0;

        $updateProdStmt = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $logHistoryStmt = $db->prepare("
            INSERT INTO stock_history (product_id, type, quantity, reference, note, user_id, created_at)
            VALUES (?, 'adjust', ?, ?, ?, ?, NOW())
        ");

        foreach ($items as $it) {
            $counted = (int)$it['counted_quantity'];
            $sys = (int)$it['system_quantity'];
            $variance = (int)$it['variance'];
            $costVar = (float)$it['cost_variance'];

            if ($variance === 0) {
                $matchedCount++;
            } else {
                $discrepancyCount++;
                $totalCostVar += $costVar;
                $diffText = ($variance > 0) ? "+{$variance}" : "{$variance}";

                // Update product stock quantity
                $updateProdStmt->execute([$counted, $it['product_id']]);

                // Log into stock_history
                $note = "ປັບປຸງຍອດຈາກຮອບກວດນັບ {$audit['audit_code']}: ຍອດເດີມ {$sys} -> ຍອດນັບຈິງ {$counted} ({$diffText})";
                $logHistoryStmt->execute([
                    $it['product_id'],
                    abs($variance),
                    $audit['audit_code'],
                    $note,
                    $userId
                ]);

                $reconciledCount++;
            }
        }

        // Close audit session
        $closeStmt = $db->prepare("
            UPDATE stock_audits 
            SET status = 'completed', 
                matched_items = ?, 
                discrepancy_items = ?, 
                total_cost_variance = ?,
                reconciled_by = ?, 
                reconciled_at = NOW()
            WHERE id = ?
        ");
        $closeStmt->execute([$matchedCount, $discrepancyCount, $totalCostVar, $userId, $auditId]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => "ປັບປຸງຍອດສະຕ໋ອກສຳເລັດ {$reconciledCount} ລາຍການ!",
            'audit_code' => $audit['audit_code'],
            'matched_count' => $matchedCount,
            'discrepancy_count' => $discrepancyCount,
            'total_cost_variance' => $totalCostVar
        ]);
        exit;
    }

    // ----------------------------------------------------
    // Action: Cancel Session
    // ----------------------------------------------------
    if ($action === 'cancel_session') {
        $auditId = (int)($_POST['audit_id'] ?? $_GET['audit_id'] ?? 0);
        $db->prepare("UPDATE stock_audits SET status = 'cancelled' WHERE id = ? AND status = 'in_progress'")->execute([$auditId]);
        echo json_encode(['success' => true, 'message' => 'ຍົກເລີກຮອບກວດນັບແລ້ວ']);
        exit;
    }

    // ----------------------------------------------------
    // Action: Audit History
    // ----------------------------------------------------
    if ($action === 'history') {
        $stmt = $db->query("
            SELECT sa.*, u.full_name as creator_name, ur.full_name as reconciler_name
            FROM stock_audits sa
            LEFT JOIN users u ON sa.created_by = u.id
            LEFT JOIN users ur ON sa.reconciled_by = ur.id
            ORDER BY sa.id DESC LIMIT 30
        ");
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'history' => $history]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
