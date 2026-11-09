<?php
/**
 * Products API
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';
requireAPILogin();
setSecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Handle product image upload and sync across desktop/dist directories
 */
function handleProductImageUpload($existingImage = null) {
    if (!empty($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        if ($existingImage) {
            deleteProductImageFiles($existingImage);
        }
        return null;
    }
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return $existingImage;
    }
    
    $file = $_FILES['image'];
    
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('ຂະໜາດຮູບພາບຕ້ອງບໍ່ເກີນ 5MB');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    
    if (!array_key_exists($mimeType, $allowedTypes)) {
        throw new Exception('ຮອງຮັບສະເພາະໄຟລ໌ຮູບພາບ (JPG, PNG, WEBP, GIF)');
    }
    
    $extension = $allowedTypes[$mimeType];
    $filename = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetDir = __DIR__ . '/../assets/images/products/';
    
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    
    $targetPath = $targetDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('ບໍ່ສາມາດບັນທຶກໄຟລ໌ຮູບພາບໄດ້');
    }
    
    // Sync to other known environments if they exist
    $syncDirs = [
        'D:/Project/pospharma/assets/images/products/',
        'D:/Project/pospharma/desktop-app/src/assets/images/products/',
        'D:/Project/pospharma/dist/desktop/win-unpacked/resources/app/src/assets/images/products/'
    ];
    
    foreach ($syncDirs as $dir) {
        if (is_dir($dir) && realpath($dir) !== realpath($targetDir)) {
            @copy($targetPath, $dir . $filename);
        }
    }
    
    if ($existingImage && $existingImage !== $filename) {
        deleteProductImageFiles($existingImage);
    }
    
    return $filename;
}

function deleteProductImageFiles($imageName) {
    if (empty($imageName) || strpos($imageName, '..') !== false || strpos($imageName, '/') !== false || strpos($imageName, '\\') !== false) {
        return;
    }
    $dirs = [
        __DIR__ . '/../assets/images/products/',
        'D:/Project/pospharma/assets/images/products/',
        'D:/Project/pospharma/desktop-app/src/assets/images/products/',
        'D:/Project/pospharma/dist/desktop/win-unpacked/resources/app/src/assets/images/products/'
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $imageName;
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}

try {
    $db = getDB();
    
    // Ensure image column exists
    try {
        $db->query("SELECT image FROM products LIMIT 1");
    } catch (Exception $e) {
        try {
            $db->exec("ALTER TABLE products ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER unit");
        } catch (Exception $ex) {}
    }
    
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';
            if ($action === 'get_batches') {
                $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
                $stmt = $db->prepare("SELECT * FROM product_batches WHERE product_id = ? ORDER BY expiry_date ASC, id ASC");
                $stmt->execute([$productId]);
                echo json_encode(['success' => true, 'batches' => $stmt->fetchAll()]);
                break;
            }
            
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'product' => $stmt->fetch()]);
            } else {
                $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
                $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1";
                $params = [];
                if ($category > 0) {
                    $sql .= " AND p.category_id = ?";
                    $params[] = $category;
                }
                $sql .= " ORDER BY p.name";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'products' => $stmt->fetchAll()]);
            }
            break;
            
        case 'POST':
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isJson = (strpos($contentType, 'application/json') !== false);
            
            if ($isJson) {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
            } else {
                $input = $_POST;
            }

            $action = $input['action'] ?? $_GET['action'] ?? '';
            if (in_array($action, ['stock_in', 'stock_out', 'stock_adjust'], true)) {
                if (!hasPermission('can_manage_stock') && !hasPermission('can_manage_products')) {
                    requirePermission('can_manage_stock');
                }
            } else {
                requirePermission('can_manage_products');
            }
            
            // Handle update via POST (e.g. from FormData multipart)
            $productId = isset($input['id']) ? (int)$input['id'] : 0;
            if ($productId > 0) {
                if (empty($input['name'])) {
                    echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນຊື່ສິນຄ້າ']);
                    exit;
                }
                
                $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $currentProd = $stmt->fetch();
                if (!$currentProd) {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບສິນຄ້າ']);
                    exit;
                }
                
                $imageName = handleProductImageUpload($currentProd['image'] ?? null);
                
                $stmt = $db->prepare("UPDATE products SET barcode=?, name=?, category_id=?, cost_price=?, selling_price=?, stock_quantity=?, min_stock=?, unit=?, expiry_date=?, image=? WHERE id=?");
                $stmt->execute([
                    !empty($input['barcode']) ? $input['barcode'] : null,
                    $input['name'],
                    !empty($input['category_id']) ? (int)$input['category_id'] : null,
                    $input['cost_price'] ?? 0,
                    $input['selling_price'] ?? 0,
                    $input['stock_quantity'] ?? 0,
                    $input['min_stock'] ?? 5,
                    $input['unit'] ?? 'ແຜງ',
                    !empty($input['expiry_date']) ? $input['expiry_date'] : null,
                    $imageName,
                    $productId,
                ]);
                
                logActivity('product_edit', "ແກ້ໄຂຂໍ້ມູນຢາ ID {$productId}: {$input['name']}", 'inventory', ['product_id' => $productId]);
                echo json_encode(['success' => true, 'message' => 'ແກ້ໄຂສິນຄ້າສຳເລັດ', 'image' => $imageName]);
                break;
            }
            
            // New Product (Insert)
            if (empty($input['name'])) {
                echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນຊື່ສິນຄ້າ']);
                exit;
            }
            
            $imageName = handleProductImageUpload(null);
            
            $stmt = $db->prepare("INSERT INTO products (barcode, name, category_id, cost_price, selling_price, stock_quantity, min_stock, unit, expiry_date, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                !empty($input['barcode']) ? $input['barcode'] : null,
                $input['name'],
                !empty($input['category_id']) ? (int)$input['category_id'] : null,
                $input['cost_price'] ?? 0,
                $input['selling_price'] ?? 0,
                $input['stock_quantity'] ?? 0,
                $input['min_stock'] ?? 5,
                $input['unit'] ?? 'ແຜງ',
                !empty($input['expiry_date']) ? $input['expiry_date'] : null,
                $imageName,
            ]);
            
            $newProdId = $db->lastInsertId();
            logActivity('product_add', "ເພີ່ມຢາໃໝ່ ID {$newProdId}: {$input['name']}", 'inventory', [
                'product_id' => $newProdId,
                'barcode' => $input['barcode'] ?? null,
                'selling_price' => $input['selling_price'] ?? 0
            ]);

            echo json_encode(['success' => true, 'message' => 'ເພີ່ມສິນຄ້າສຳເລັດ', 'id' => $newProdId, 'image' => $imageName]);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            
            if (isset($input['action']) && $input['action'] === 'stock_in') {
                // Stock in
                $productId = (int)($input['product_id'] ?? 0);
                $quantity = (int)($input['quantity'] ?? 0);
                
                if ($productId <= 0 || $quantity <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ຂໍ້ມູນບໍ່ຖືກຕ້ອງ']);
                    exit;
                }
                
                $db->beginTransaction();
                
                $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")
                   ->execute([$quantity, $productId]);
                
                $supplierId = isset($input['supplier_id']) && $input['supplier_id'] > 0 ? (int)$input['supplier_id'] : null;
                
                $db->prepare("INSERT INTO stock_history (product_id, type, quantity, note, user_id, supplier_id) VALUES (?, 'in', ?, ?, ?, ?)")
                   ->execute([$productId, $quantity, $input['note'] ?? '', getCurrentUserId(), $supplierId]);
                
                // Save batch details if provided
                if (!empty($input['batch_number'])) {
                    $expiryDate = !empty($input['expiry_date']) ? $input['expiry_date'] : date('Y-m-d', strtotime('+1 year'));
                    $costPrice = isset($input['cost_price']) ? (double)$input['cost_price'] : 0;
                    $sellingPrice = isset($input['selling_price']) ? (double)$input['selling_price'] : 0;
                    
                    // Add to product_batches
                    $stmtBatch = $db->prepare("INSERT INTO product_batches (product_id, batch_number, cost_price, selling_price, initial_quantity, quantity, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmtBatch->execute([$productId, $input['batch_number'], $costPrice, $sellingPrice, $quantity, $quantity, $expiryDate]);
                    
                    // Update main product prices to match the latest batch prices
                    if ($costPrice > 0 || $sellingPrice > 0) {
                        $db->prepare("UPDATE products SET cost_price = ?, selling_price = ? WHERE id = ?")
                           ->execute([$costPrice, $sellingPrice, $productId]);
                    }
                }
                
                $db->commit();
                
                logActivity('stock_in', "ຮັບຢາເຂົ້າສາງ: ID {$productId} ຈຳນວນ +{$quantity}" . (!empty($input['batch_number']) ? " (Lot: {$input['batch_number']})" : ""), 'inventory', [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'batch_number' => $input['batch_number'] ?? null
                ]);

                echo json_encode(['success' => true, 'message' => 'ນຳເຂົ້າສິນຄ້າສຳເລັດ']);
            } else if (isset($input['action']) && $input['action'] === 'stock_out') {
                // Stock out (Deduct/Adjust stock)
                $productId = (int)($input['product_id'] ?? 0);
                $quantity = (int)($input['quantity'] ?? 0);
                
                if ($productId <= 0 || $quantity <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ຂໍ້ມູນບໍ່ຖືກຕ້ອງ']);
                    exit;
                }
                
                $stmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $prod = $stmt->fetch();
                if (!$prod || $prod['stock_quantity'] < $quantity) {
                    echo json_encode(['success' => false, 'message' => 'ຈຳນວນສະຕ໋ອກຄົງເຫຼືອບໍ່ພໍໃຫ້ຫັກອອກ']);
                    exit;
                }
                
                $db->beginTransaction();
                
                deductStockFIFO($productId, $quantity);
                
                $db->prepare("INSERT INTO stock_history (product_id, type, quantity, note, user_id) VALUES (?, 'out', ?, ?, ?)")
                   ->execute([$productId, $quantity, $input['note'] ?? '', getCurrentUserId()]);
                
                $db->commit();
                
                logActivity('stock_out', "ຕັດສະຕ໋ອກຢາ: ID {$productId} ຈຳນວນ -{$quantity}" . (!empty($input['note']) ? " ({$input['note']})" : ""), 'inventory', [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'note' => $input['note'] ?? null
                ]);

                echo json_encode(['success' => true, 'message' => 'ຕັດສະຕ໋ອກອອກສຳເລັດ']);
            } else {
                // Update product
                $id = (int)($input['id'] ?? 0);
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID ບໍ່ຖືກຕ້ອງ']);
                    exit;
                }
                
                $stmt = $db->prepare("UPDATE products SET barcode=?, name=?, category_id=?, cost_price=?, selling_price=?, stock_quantity=?, min_stock=?, unit=?, expiry_date=?, image=COALESCE(?, image) WHERE id=?");
                $stmt->execute([
                    !empty($input['barcode']) ? $input['barcode'] : null,
                    $input['name'],
                    !empty($input['category_id']) ? (int)$input['category_id'] : null,
                    $input['cost_price'] ?? 0,
                    $input['selling_price'] ?? 0,
                    $input['stock_quantity'] ?? 0,
                    $input['min_stock'] ?? 5,
                    $input['unit'] ?? 'ແຜງ',
                    !empty($input['expiry_date']) ? $input['expiry_date'] : null,
                    $input['image'] ?? null,
                    $id,
                ]);
                
                echo json_encode(['success' => true, 'message' => 'ແກ້ໄຂສິນຄ້າສຳເລັດ']);
            }
            break;
            
        case 'DELETE':
            requirePermission('can_manage_products');
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID ບໍ່ຖືກຕ້ອງ']);
                exit;
            }
            
            $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?")->execute([$id]);
            logActivity('product_delete', "ລຶບຢາ ID {$id} ອອກຈາກລະບົບ", 'inventory', ['product_id' => $id]);
            echo json_encode(['success' => true, 'message' => 'ລຶບສິນຄ້າສຳເລັດ']);
            break;
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
