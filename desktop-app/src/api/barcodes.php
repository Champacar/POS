<?php
/**
 * POSPharma - Barcode Management & Printing API
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/barcode_helper.php';

// Authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = getDB();

    switch ($action) {
        // ============================================
        // 1. Validate & Check Duplicate Barcode
        // ============================================
        case 'check_duplicate':
            $barcode = trim($_GET['barcode'] ?? $_POST['barcode'] ?? '');
            $excludeId = !empty($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;

            if ($barcode === '') {
                echo json_encode(['success' => true, 'exists' => false, 'valid' => true]);
                exit;
            }

            // Check standard validation
            $detectedType = detectBarcodeFormat($barcode);
            $isValidChecksum = true;
            $validationMsg = '';

            if (preg_match('/^[0-9]{13}$/', $barcode)) {
                $isValidChecksum = validateEan13($barcode);
                if (!$isValidChecksum) {
                    $expected = calculateGs1CheckDigit(substr($barcode, 0, 12));
                    $validationMsg = "ຫຼັກກວດສອບ Check-digit ຂອງ EAN-13 ບໍ່ຖືກຕ້ອງ (ຄວນລົງທ້າຍດ້ວຍ {$expected})";
                }
            } elseif (preg_match('/^[0-9]{8}$/', $barcode)) {
                $isValidChecksum = validateEan8($barcode);
                if (!$isValidChecksum) {
                    $expected = calculateGs1CheckDigit(substr($barcode, 0, 7));
                    $validationMsg = "ຫຼັກກວດສອບ Check-digit ຂອງ EAN-8 ບໍ່ຖືກຕ້ອງ (ຄວນລົງທ້າຍດ້ວຍ {$expected})";
                }
            } elseif (preg_match('/^[0-9]{12}$/', $barcode)) {
                $isValidChecksum = validateUpcA($barcode);
                if (!$isValidChecksum) {
                    $expected = calculateGs1CheckDigit(substr($barcode, 0, 11));
                    $validationMsg = "ຫຼັກກວດສອບ Check-digit ຂອງ UPC-A ບໍ່ຖືກຕ້ອງ (ຄວນລົງທ້າຍດ້ວຍ {$expected})";
                }
            }

            $check = checkBarcodeExists($pdo, $barcode, $excludeId);

            echo json_encode([
                'success' => true,
                'exists' => $check['exists'],
                'source' => $check['source'] ?? null,
                'product_id' => $check['product_id'] ?? null,
                'product_name' => $check['product_name'] ?? null,
                'unit' => $check['unit'] ?? null,
                'detected_type' => $detectedType,
                'valid_checksum' => $isValidChecksum,
                'validation_msg' => $validationMsg
            ]);
            exit;

        // ============================================
        // 2. Generate Safe In-Store Barcode
        // ============================================
        case 'generate_barcode':
            $type = $_GET['type'] ?? 'CODE128';
            $productId = !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null;

            $generated = '';
            $attempts = 0;
            do {
                $generated = generateStoreBarcode($type, $productId);
                $chk = checkBarcodeExists($pdo, $generated);
                $attempts++;
            } while ($chk['exists'] && $attempts < 10);

            echo json_encode([
                'success' => true,
                'barcode' => $generated,
                'barcode_type' => $type
            ]);
            exit;

        // ============================================
        // 3. Get Product Barcodes (Multi-Barcode)
        // ============================================
        case 'get_product_barcodes':
            $productId = (int)($_GET['product_id'] ?? 0);
            if ($productId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບລະຫັດສິນຄ້າ']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT pb.*, p.name AS product_name, p.unit AS base_unit, p.selling_price AS base_price 
                FROM product_barcodes pb 
                JOIN products p ON pb.product_id = p.id 
                WHERE pb.product_id = ? 
                ORDER BY pb.is_default DESC, pb.id ASC
            ");
            $stmt->execute([$productId]);
            $barcodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $barcodes
            ]);
            exit;

        // ============================================
        // 4. Save / Add / Edit Barcode
        // ============================================
        case 'save_barcode':
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $productId = (int)($_POST['product_id'] ?? 0);
            $barcode = trim($_POST['barcode'] ?? '');
            $type = $_POST['barcode_type'] ?? 'CODE128';
            $unit = trim($_POST['unit'] ?? '');
            $multiplier = max(1, (int)($_POST['multiplier'] ?? 1));
            $price = isset($_POST['price']) && $_POST['price'] !== '' ? (float)$_POST['price'] : null;
            $variantName = trim($_POST['variant_name'] ?? '');
            $isDefault = !empty($_POST['is_default']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

            if ($productId <= 0 || $barcode === '') {
                echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນລະຫັດບາໂຄດ ແລະ ເລືອກສິນຄ້າ']);
                exit;
            }

            // Check duplicate
            $dup = checkBarcodeExists($pdo, $barcode, $id);
            if ($dup['exists'] && $dup['product_id'] !== $productId) {
                echo json_encode([
                    'success' => false,
                    'message' => "ບາໂຄດ '{$barcode}' ຊ້ຳກັບສິນຄ້າ '{$dup['product_name']}'"
                ]);
                exit;
            }

            if ($id) {
                // Update
                if ($isDefault) {
                    $pdo->prepare("UPDATE product_barcodes SET is_default = 0 WHERE product_id = ?")->execute([$productId]);
                }
                $stmt = $pdo->prepare("
                    UPDATE product_barcodes 
                    SET barcode = ?, barcode_type = ?, unit = ?, multiplier = ?, price = ?, variant_name = ?, is_default = ?, is_active = ?
                    WHERE id = ? AND product_id = ?
                ");
                $stmt->execute([$barcode, $type, $unit, $multiplier, $price, $variantName, $isDefault, $isActive, $id, $productId]);
            } else {
                // Insert
                if ($isDefault) {
                    $pdo->prepare("UPDATE product_barcodes SET is_default = 0 WHERE product_id = ?")->execute([$productId]);
                }
                $stmt = $pdo->prepare("
                    INSERT INTO product_barcodes 
                    (product_id, barcode, barcode_type, unit, multiplier, price, variant_name, is_default, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$productId, $barcode, $type, $unit, $multiplier, $price, $variantName, $isDefault, $isActive]);
                $id = (int)$pdo->lastInsertId();
            }

            // Sync with products.barcode if is_default
            if ($isDefault) {
                $pdo->prepare("UPDATE products SET barcode = ? WHERE id = ?")->execute([$barcode, $productId]);
            }

            echo json_encode(['success' => true, 'id' => $id, 'message' => 'ບັນທຶກບາໂຄດສຳເລັດ']);
            exit;

        // ============================================
        // 5. Delete Barcode
        // ============================================
        case 'delete_barcode':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID ບໍ່ຖືກຕ້ອງ']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT product_id, is_default FROM product_barcodes WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $pdo->prepare("DELETE FROM product_barcodes WHERE id = ?")->execute([$id]);
                if ($row['is_default']) {
                    // Reassign next default
                    $next = $pdo->prepare("SELECT id, barcode FROM product_barcodes WHERE product_id = ? LIMIT 1");
                    $next->execute([$row['product_id']]);
                    $nextRow = $next->fetch(PDO::FETCH_ASSOC);
                    if ($nextRow) {
                        $pdo->prepare("UPDATE product_barcodes SET is_default = 1 WHERE id = ?")->execute([$nextRow['id']]);
                        $pdo->prepare("UPDATE products SET barcode = ? WHERE id = ?")->execute([$nextRow['barcode'], $row['product_id']]);
                    } else {
                        $pdo->prepare("UPDATE products SET barcode = NULL WHERE id = ?")->execute([$row['product_id']]);
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => 'ລຶບບາໂຄດສຳເລັດ']);
            exit;

        // ============================================
        // 6. Set Default Barcode
        // ============================================
        case 'set_default':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT product_id, barcode FROM product_barcodes WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບລາຍການບາໂຄດ']);
                exit;
            }

            $pdo->prepare("UPDATE product_barcodes SET is_default = 0 WHERE product_id = ?")->execute([$row['product_id']]);
            $pdo->prepare("UPDATE product_barcodes SET is_default = 1 WHERE id = ?")->execute([$id]);
            $pdo->prepare("UPDATE products SET barcode = ? WHERE id = ?")->execute([$row['barcode'], $row['product_id']]);

            echo json_encode(['success' => true, 'message' => 'ຕັ້ງເປັນບາໂຄດຫຼັກຮຽບຮ້ອຍແລ້ວ']);
            exit;

        // ============================================
        // 7. Get Templates
        // ============================================
        case 'get_templates':
            $stmt = $pdo->query("SELECT * FROM barcode_templates ORDER BY is_default DESC, id ASC");
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $templates]);
            exit;

        // ============================================
        // 8. Save / Update Template
        // ============================================
        case 'save_template':
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $name = trim($_POST['name'] ?? '');
            $widthMm = (float)($_POST['width_mm'] ?? 40);
            $heightMm = (float)($_POST['height_mm'] ?? 30);
            $columns = max(1, (int)($_POST['columns'] ?? 1));
            $showName = !empty($_POST['show_name']) ? 1 : 0;
            $showCode = !empty($_POST['show_code']) ? 1 : 0;
            $showBarcode = !empty($_POST['show_barcode']) ? 1 : 0;
            $showBarcodeText = !empty($_POST['show_barcode_text']) ? 1 : 0;
            $showPrice = !empty($_POST['show_price']) ? 1 : 0;
            $showShopName = !empty($_POST['show_shop_name']) ? 1 : 0;
            $showUnit = !empty($_POST['show_unit']) ? 1 : 0;
            $showVariant = !empty($_POST['show_variant']) ? 1 : 0;
            $showLotExpiry = !empty($_POST['show_lot_expiry']) ? 1 : 0;
            $fontSizeName = trim($_POST['font_size_name'] ?? '8.5pt');
            $fontSizePrice = trim($_POST['font_size_price'] ?? '9pt');
            $fontSizeMeta = trim($_POST['font_size_meta'] ?? '6.5pt');
            $barcodeHeight = max(15, (int)($_POST['barcode_height'] ?? 32));
            $orientation = ($_POST['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
            $isDefault = !empty($_POST['is_default']) ? 1 : 0;

            if ($name === '') {
                echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນຊື່ Template']);
                exit;
            }

            if ($isDefault) {
                $pdo->exec("UPDATE barcode_templates SET is_default = 0");
            }

            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE barcode_templates 
                    SET name=?, width_mm=?, height_mm=?, columns=?, show_name=?, show_code=?, show_barcode=?, show_barcode_text=?,
                        show_price=?, show_shop_name=?, show_unit=?, show_variant=?, show_lot_expiry=?, font_size_name=?,
                        font_size_price=?, font_size_meta=?, barcode_height=?, orientation=?, is_default=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $name, $widthMm, $heightMm, $columns, $showName, $showCode, $showBarcode, $showBarcodeText,
                    $showPrice, $showShopName, $showUnit, $showVariant, $showLotExpiry, $fontSizeName,
                    $fontSizePrice, $fontSizeMeta, $barcodeHeight, $orientation, $isDefault, $id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO barcode_templates 
                    (name, width_mm, height_mm, columns, show_name, show_code, show_barcode, show_barcode_text,
                     show_price, show_shop_name, show_unit, show_variant, show_lot_expiry, font_size_name,
                     font_size_price, font_size_meta, barcode_height, orientation, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name, $widthMm, $heightMm, $columns, $showName, $showCode, $showBarcode, $showBarcodeText,
                    $showPrice, $showShopName, $showUnit, $showVariant, $showLotExpiry, $fontSizeName,
                    $fontSizePrice, $fontSizeMeta, $barcodeHeight, $orientation, $isDefault
                ]);
                $id = (int)$pdo->lastInsertId();
            }

            echo json_encode(['success' => true, 'id' => $id, 'message' => 'ບັນທຶກ Template ສຳເລັດ']);
            exit;

        // ============================================
        // 9. Delete Template
        // ============================================
        case 'delete_template':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM barcode_templates WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'ລຶບ Template ສຳເລັດ']);
            exit;

        // ============================================
        // 10. Get Purchase Order Items for Sticker Print
        // ============================================
        case 'get_po_items':
            $poId = (int)($_GET['po_id'] ?? 0);
            if ($poId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບໃບສັ່ງຊື້/ໃບຮັບສິນຄ້າ']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT poi.*, p.name AS product_name, p.barcode, p.selling_price, p.unit 
                FROM purchase_order_items poi 
                JOIN products p ON poi.product_id = p.id 
                WHERE poi.po_id = ?
            ");
            $stmt->execute([$poId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $items]);
            exit;

        // ============================================
        // 11. Get Products Without Barcodes
        // ============================================
        case 'get_unbarcoded_products':
            $stmt = $pdo->query("
                SELECT id, name, unit, selling_price, stock_quantity 
                FROM products 
                WHERE barcode IS NULL OR TRIM(barcode) = ''
                ORDER BY name ASC
            ");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $items]);
            exit;

        // ============================================
        // 12. Batch Auto-Assign Barcodes
        // ============================================
        case 'batch_assign_barcodes':
            $stmt = $pdo->query("SELECT id, unit, selling_price FROM products WHERE barcode IS NULL OR TRIM(barcode) = ''");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $assigned = 0;

            $update = $pdo->prepare("UPDATE products SET barcode = ? WHERE id = ?");

            foreach ($products as $p) {
                $pid = (int)$p['id'];
                $attempts = 0;
                do {
                    $code = generateStoreBarcode('CODE128', $pid);
                    $chk = checkBarcodeExists($pdo, $code);
                    $attempts++;
                } while ($chk['exists'] && $attempts < 10);

                $update->execute([$code, $pid]);
                
                // Check if product_barcodes default exists
                $chkPb = $pdo->prepare("SELECT id FROM product_barcodes WHERE product_id = ? AND is_default = 1");
                $chkPb->execute([$pid]);
                $existingPb = $chkPb->fetch(PDO::FETCH_ASSOC);

                if ($existingPb) {
                    $pdo->prepare("UPDATE product_barcodes SET barcode = ?, is_active = 1 WHERE id = ?")->execute([$code, $existingPb['id']]);
                } else {
                    $pdo->prepare("INSERT INTO product_barcodes (product_id, barcode, barcode_type, unit, multiplier, price, is_default, is_active) VALUES (?, ?, 'CODE128', ?, 1.0, ?, 1, 1)")
                        ->execute([$pid, $code, $p['unit'] ?: 'ອັນ', $p['selling_price'] ?: 0]);
                }
                $assigned++;
            }

            echo json_encode(['success' => true, 'assigned' => $assigned, 'message' => "ສ້າງບາໂຄດໃຫ້ສິນຄ້າ {$assigned} ລາຍການຮຽບຮ້ອຍແລ້ວ"]);
            exit;

        // ============================================
        // 13. Generate TSPL Raw Commands (for Thermal Label Printers)
        // ============================================
        case 'generate_tspl':
            $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $items = $payload['items'] ?? [];
            $template = $payload['template'] ?? [];
            $config = $payload['config'] ?? [];

            $fullTspl = '';
            foreach ($items as $item) {
                $fullTspl .= generateTsplCommand($item, $template, $config) . "\n";
            }

            echo json_encode([
                'success' => true,
                'tspl_code' => $fullTspl
            ]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Action ບໍ່ຖືກຕ້ອງ']);
            exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

