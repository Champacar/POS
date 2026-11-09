<?php
/**
 * Expiry FEFO & Auto Reorder Suggestions API
 * ລະບົບແນະນຳການສັ່ງຊື້ຢາເຕີມສະຕ໋ອກ & ຈັດການວັນໝົດອາຍຸ FEFO
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

try {
    // ----------------------------------------------------
    // Action: Suggestions (Low Stock & Auto Reorder List)
    // ----------------------------------------------------
    if ($action === 'suggestions') {
        $supplierFilter = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

        $sql = "
            SELECT 
                p.id, p.barcode, p.name, p.unit, p.stock_quantity, p.min_stock, 
                p.cost_price, p.selling_price, p.expiry_date, p.supplier_id,
                COALESCE(s.name, 'ບໍ່ໄດ້ກຳນົດຜູ້ສະໜອງ') as supplier_name,
                s.phone as supplier_phone,
                s.email as supplier_email,
                c.name as category_name
            FROM products p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_active = 1 AND p.stock_quantity <= p.min_stock
        ";

        $params = [];
        if ($supplierFilter > 0) {
            $sql .= " AND p.supplier_id = ?";
            $params[] = $supplierFilter;
        }

        $sql .= " ORDER BY (p.stock_quantity = 0) DESC, (p.stock_quantity / GREATEST(p.min_stock, 1)) ASC, p.name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rawProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by supplier
        $grouped = [];
        $totalEstimatedCost = 0;
        $totalItemsCount = count($rawProducts);
        $totalOutOfStock = 0;

        foreach ($rawProducts as $p) {
            $minStock = max(1, (int)$p['min_stock']);
            $currStock = (int)$p['stock_quantity'];
            if ($currStock <= 0) $totalOutOfStock++;

            // Recommended order formula: Target buffer = min_stock * 2; order diff or at least 10 units
            $suggestedQty = max($minStock * 2 - $currStock, 10);
            $costPrice = (float)$p['cost_price'];
            $estCost = $suggestedQty * $costPrice;

            $totalEstimatedCost += $estCost;

            $sId = (int)($p['supplier_id'] ?? 0);
            $sKey = $sId > 0 ? (string)$sId : 'unassigned';

            if (!isset($grouped[$sKey])) {
                $grouped[$sKey] = [
                    'supplier_id' => $sId,
                    'supplier_name' => $p['supplier_name'],
                    'supplier_phone' => $p['supplier_phone'] ?: '',
                    'supplier_email' => $p['supplier_email'] ?: '',
                    'total_items' => 0,
                    'total_cost' => 0,
                    'items' => []
                ];
            }

            $grouped[$sKey]['total_items']++;
            $grouped[$sKey]['total_cost'] += $estCost;
            $grouped[$sKey]['items'][] = [
                'id' => (int)$p['id'],
                'barcode' => $p['barcode'],
                'name' => $p['name'],
                'category_name' => $p['category_name'] ?: 'ທົ່ວໄປ',
                'unit' => $p['unit'] ?: 'ກ່ອງ',
                'stock_quantity' => $currStock,
                'min_stock' => $minStock,
                'cost_price' => $costPrice,
                'selling_price' => (float)$p['selling_price'],
                'suggested_quantity' => $suggestedQty,
                'estimated_cost' => $estCost,
                'expiry_date' => $p['expiry_date']
            ];
        }

        echo json_encode([
            'success' => true,
            'summary' => [
                'total_low_stock_products' => $totalItemsCount,
                'total_out_of_stock' => $totalOutOfStock,
                'total_estimated_budget' => $totalEstimatedCost,
                'total_suppliers' => count($grouped)
            ],
            'suppliers' => array_values($grouped)
        ]);
        exit;
    }

    // ----------------------------------------------------
    // Action: Expiry Tiers (FEFO Breakdown)
    // ----------------------------------------------------
    if ($action === 'expiry_tiers') {
        $today = date('Y-m-d');
        $date30 = date('Y-m-d', strtotime('+30 days'));
        $date60 = date('Y-m-d', strtotime('+60 days'));
        $date90 = date('Y-m-d', strtotime('+90 days'));

        // Query active batches
        $stmtBatches = $db->query("
            SELECT 
                pb.id as batch_id, pb.batch_number, pb.quantity, pb.cost_price, pb.expiry_date,
                p.id as product_id, p.name as product_name, p.unit, p.barcode,
                c.name as category_name
            FROM product_batches pb
            JOIN products p ON pb.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE pb.quantity > 0 AND pb.expiry_date IS NOT NULL
            ORDER BY pb.expiry_date ASC
        ");
        $batches = $stmtBatches->fetchAll(PDO::FETCH_ASSOC);

        $tiers = [
            'expired' => ['count' => 0, 'total_qty' => 0, 'total_value' => 0, 'items' => []],
            'tier_30' => ['count' => 0, 'total_qty' => 0, 'total_value' => 0, 'items' => []],
            'tier_60' => ['count' => 0, 'total_qty' => 0, 'total_value' => 0, 'items' => []],
            'tier_90' => ['count' => 0, 'total_qty' => 0, 'total_value' => 0, 'items' => []],
            'safe'    => ['count' => 0, 'total_qty' => 0, 'total_value' => 0, 'items' => []]
        ];

        foreach ($batches as $b) {
            $exp = $b['expiry_date'];
            $qty = (int)$b['quantity'];
            $cost = (float)$b['cost_price'];
            $val = $qty * $cost;

            $daysLeft = (int)((strtotime($exp) - strtotime($today)) / 86400);
            $item = array_merge($b, ['days_left' => $daysLeft, 'total_value' => $val]);

            if ($exp < $today) {
                $target = 'expired';
            } elseif ($exp <= $date30) {
                $target = 'tier_30';
            } elseif ($exp <= $date60) {
                $target = 'tier_60';
            } elseif ($exp <= $date90) {
                $target = 'tier_90';
            } else {
                $target = 'safe';
            }

            $tiers[$target]['count']++;
            $tiers[$target]['total_qty'] += $qty;
            $tiers[$target]['total_value'] += $val;
            if (count($tiers[$target]['items']) < 100) {
                $tiers[$target]['items'][] = $item;
            }
        }

        echo json_encode([
            'success' => true,
            'today' => $today,
            'tiers' => $tiers
        ]);
        exit;
    }

    // ----------------------------------------------------
    // Action: Create Auto PO (1-Click Purchase Order)
    // ----------------------------------------------------
    if ($action === 'create_auto_po') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $supplierId = (int)($input['supplier_id'] ?? 0);
        $items = $input['items'] ?? [];
        $note = trim($input['note'] ?? 'ສ້າງອັດຕະໂນມັດຈາກລະບົບແນະນຳສັ່ງຊື້ຢາ (Auto Reorder)');

        if (empty($items) || !is_array($items)) {
            echo json_encode(['success' => false, 'message' => 'ບໍ່ມີລາຍການຢາທີ່ຕ້ອງການສັ່ງຊື້']);
            exit;
        }

        // If no supplier_id provided, find supplier from first item or default
        if ($supplierId <= 0) {
            $firstProdId = (int)($items[0]['product_id'] ?? 0);
            if ($firstProdId > 0) {
                $pStmt = $db->prepare("SELECT supplier_id FROM products WHERE id = ?");
                $pStmt->execute([$firstProdId]);
                $supplierId = (int)$pStmt->fetchColumn();
            }
            if ($supplierId <= 0) {
                $supplierId = (int)$db->query("SELECT id FROM suppliers WHERE is_active = 1 LIMIT 1")->fetchColumn();
            }
        }

        $db->beginTransaction();

        // Generate PO Number: PO-YYYYMMDD-XXXX
        $todayPrefix = 'PO-' . date('Ymd') . '-';
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM purchase_orders WHERE po_number LIKE ?");
        $stmtCount->execute([$todayPrefix . '%']);
        $poCount = (int)$stmtCount->fetchColumn() + 1;
        $poNumber = $todayPrefix . str_pad($poCount, 3, '0', STR_PAD_LEFT);

        // Calculate total amount
        $totalAmount = 0;
        foreach ($items as $item) {
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $price = (float)($item['unit_price'] ?? $item['cost_price'] ?? 0);
            $totalAmount += ($qty * $price);
        }

        // Insert into purchase_orders
        $stmtPO = $db->prepare("
            INSERT INTO purchase_orders (po_number, supplier_id, status, total_amount, note, created_at)
            VALUES (?, ?, 'draft', ?, ?, NOW())
        ");
        $stmtPO->execute([$poNumber, $supplierId ?: null, $totalAmount, $note]);
        $poId = $db->lastInsertId();

        // Insert items
        $stmtItem = $db->prepare("
            INSERT INTO purchase_order_items (po_id, product_id, product_name, quantity, unit_price)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $prodId = (int)($item['product_id'] ?? $item['id'] ?? 0);
            $prodName = $item['product_name'] ?? $item['name'] ?? 'ຢາ';
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $price = (float)($item['unit_price'] ?? $item['cost_price'] ?? 0);

            $stmtItem->execute([$poId, $prodId ?: null, $prodName, $qty, $price]);
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => "ສ້າງໃບສັ່ງຊື້ $poNumber ສຳເລັດແລ້ວ!",
            'po_id' => $poId,
            'po_number' => $poNumber,
            'total_amount' => $totalAmount,
            'items_count' => count($items)
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
