<?php
/**
 * Products Import API
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';
requireAPILogin();
setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

/**
 * Helper to normalize and clean numeric inputs
 */
function parseCleanNumber($val, $default = 0.0) {
    if ($val === null || $val === '') return $default;
    if (is_numeric($val)) return (float)$val;
    // Remove non-numeric characters except period and minus
    $cleaned = preg_replace('/[^0-9.-]/', '', (string)$val);
    return is_numeric($cleaned) ? (float)$cleaned : $default;
}

/**
 * Helper to normalize date string to YYYY-MM-DD
 */
function parseCleanDate($val) {
    if (empty($val)) return null;
    $val = trim((string)$val);
    
    // Check if standard YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
        return $val;
    }
    // Check if DD/MM/YYYY or DD-MM-YYYY
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $val, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    // Check if YYYY/MM/DD
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $val, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    
    $ts = strtotime($val);
    if ($ts !== false && $ts > 0) {
        return date('Y-m-d', $ts);
    }
    return null;
}

try {
    $db = getDB();
    
    $productsToImport = [];
    $duplicateAction = 'update'; // 'update' or 'skip'
    $autoCreateCategories = true;
    $autoCreateUnits = true;
    
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $rawInput = file_get_contents('php://input');
    $input = !empty($rawInput) ? json_decode($rawInput, true) : null;
    if (!$input && isset($_POST['data'])) {
        $input = is_array($_POST['data']) ? $_POST['data'] : json_decode($_POST['data'], true);
    }
    if (!$input && isset($_POST['products'])) {
        $input = $_POST;
    }
    
    if (!empty($input) && is_array($input)) {
        // JSON payload from SheetJS frontend or API
        $productsToImport = $input['products'] ?? [];
        $duplicateAction = $input['duplicate_action'] ?? 'update';
        $autoCreateCategories = isset($input['auto_create_categories']) ? (bool)$input['auto_create_categories'] : true;
        $autoCreateUnits = isset($input['auto_create_units']) ? (bool)$input['auto_create_units'] : true;
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // Direct CSV upload fallback
        $duplicateAction = $_POST['duplicate_action'] ?? 'update';
        $autoCreateCategories = !empty($_POST['auto_create_categories']);
        $autoCreateUnits = !empty($_POST['auto_create_units']);
        
        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        if ($handle !== false) {
            // Read first line for BOM/Header
            $firstLine = fgets($handle);
            if ($firstLine !== false) {
                // Strip UTF-8 BOM if present
                $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
                $headerCols = str_getcsv($firstLine);
                
                // Map column positions
                $colMap = [
                    'barcode' => -1,
                    'name' => -1,
                    'category' => -1,
                    'cost_price' => -1,
                    'selling_price' => -1,
                    'stock_quantity' => -1,
                    'min_stock' => -1,
                    'unit' => -1,
                    'expiry_date' => -1,
                ];
                
                foreach ($headerCols as $idx => $headerName) {
                    $normHeader = mb_strtolower(trim($headerName), 'UTF-8');
                    if (strpos($normHeader, 'ບາໂຄດ') !== false || strpos($normHeader, 'barcode') !== false || $normHeader === 'code') {
                        $colMap['barcode'] = $idx;
                    } elseif (strpos($normHeader, 'ຊື່') !== false || strpos($normHeader, 'name') !== false || strpos($normHeader, 'product') !== false) {
                        $colMap['name'] = $idx;
                    } elseif (strpos($normHeader, 'ໝວດ') !== false || strpos($normHeader, 'category') !== false) {
                        $colMap['category'] = $idx;
                    } elseif (strpos($normHeader, 'ທຶນ') !== false || strpos($normHeader, 'cost') !== false) {
                        $colMap['cost_price'] = $idx;
                    } elseif (strpos($normHeader, 'ຂາຍ') !== false || strpos($normHeader, 'price') !== false) {
                        $colMap['selling_price'] = $idx;
                    } elseif (strpos($normHeader, 'ສະຕ໋ອກ') !== false || strpos($normHeader, 'stock') !== false || strpos($normHeader, 'qty') !== false || strpos($normHeader, 'ຈຳນວນ') !== false) {
                        $colMap['stock_quantity'] = $idx;
                    } elseif (strpos($normHeader, 'ຕ່ຳ') !== false || strpos($normHeader, 'min') !== false) {
                        $colMap['min_stock'] = $idx;
                    } elseif (strpos($normHeader, 'ໜ່ວຍ') !== false || strpos($normHeader, 'unit') !== false) {
                        $colMap['unit'] = $idx;
                    } elseif (strpos($normHeader, 'ໝົດອາຍຸ') !== false || strpos($normHeader, 'exp') !== false || strpos($normHeader, 'expiry') !== false) {
                        $colMap['expiry_date'] = $idx;
                    }
                }
                
                // Fallback positional if not mapped
                if ($colMap['name'] === -1 && count($headerCols) >= 2) {
                    $colMap['barcode'] = 0;
                    $colMap['name'] = 1;
                    $colMap['category'] = 2;
                    $colMap['cost_price'] = 3;
                    $colMap['selling_price'] = 4;
                    $colMap['stock_quantity'] = 5;
                    $colMap['min_stock'] = 6;
                    $colMap['unit'] = 7;
                    $colMap['expiry_date'] = 8;
                }
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (empty($row) || (count($row) === 1 && empty($row[0]))) continue;
                    $p = [];
                    foreach ($colMap as $key => $pos) {
                        $p[$key] = ($pos >= 0 && isset($row[$pos])) ? trim($row[$pos]) : '';
                    }
                    if (!empty($p['name'])) {
                        $productsToImport[] = $p;
                    }
                }
            }
            fclose($handle);
        }
    }
    
    if (empty($productsToImport)) {
        echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນສິນຄ້າທີ່ສາມາດນຳເຂົ້າໄດ້']);
        exit;
    }
    
    // Ensure image column exists in products table if missing
    try {
        $db->query("SELECT image FROM products LIMIT 1");
    } catch (Exception $e) {
        try {
            $db->exec("ALTER TABLE products ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER unit");
        } catch (Exception $ex) {}
    }
    
    // Load categories cache
    $catMap = []; // lowercase name => id
    $cats = $db->query("SELECT id, name FROM categories")->fetchAll();
    foreach ($cats as $c) {
        $key = mb_strtolower(trim($c['name']), 'UTF-8');
        $catMap[$key] = (int)$c['id'];
    }
    
    // Load units cache
    $unitMap = []; // lowercase name => original name
    try {
        $units = $db->query("SELECT id, name FROM units")->fetchAll();
        foreach ($units as $u) {
            $key = mb_strtolower(trim($u['name']), 'UTF-8');
            $unitMap[$key] = trim($u['name']);
        }
    } catch (Exception $e) {}
    
    $db->beginTransaction();
    
    $createdCount = 0;
    $updatedCount = 0;
    $skippedCount = 0;
    $errors = [];
    $currentUserId = getCurrentUserId();
    
    // Prepare statements for performance
    $stmtFindBarcode = $db->prepare("SELECT id, name, stock_quantity, unit FROM products WHERE barcode = ? LIMIT 1");
    $stmtFindNameNoBarcode = $db->prepare("SELECT id, name, stock_quantity, unit FROM products WHERE name = ? AND (barcode IS NULL OR barcode = '') LIMIT 1");
    
    $stmtInsertProd = $db->prepare("INSERT INTO products (barcode, name, category_id, cost_price, selling_price, stock_quantity, min_stock, unit, expiry_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    
    $stmtUpdateProd = $db->prepare("UPDATE products SET barcode = COALESCE(?, barcode), name = ?, category_id = COALESCE(?, category_id), cost_price = ?, selling_price = ?, stock_quantity = ?, min_stock = ?, unit = ?, expiry_date = ?, is_active = 1 WHERE id = ?");
    
    $stmtStockIn = $db->prepare("INSERT INTO stock_history (product_id, type, quantity, note, user_id) VALUES (?, 'in', ?, 'ນຳເຂົ້າຈາກ Excel', ?)");
    $stmtStockAdj = $db->prepare("INSERT INTO stock_history (product_id, type, quantity, note, user_id) VALUES (?, 'adjust', ?, 'ອັບເດດສະຕ໋ອກຈາກ Excel', ?)");
    
    $stmtNewCat = $db->prepare("INSERT INTO categories (name, is_active) VALUES (?, 1)");
    $stmtNewUnit = $db->prepare("INSERT INTO units (name) VALUES (?)");
    
    foreach ($productsToImport as $index => $item) {
        $rowNum = $index + 1;
        $name = trim($item['name'] ?? '');
        
        if (empty($name)) {
            $errors[] = "ແຖວທີ $rowNum: ບໍ່ມີຊື່ສິນຄ້າ (ຂ້າມ)";
            $skippedCount++;
            continue;
        }
        
        $barcode = !empty($item['barcode']) ? trim((string)$item['barcode']) : null;
        // Clean barcode string if scientific notation
        if ($barcode !== null && preg_match('/^[0-9]+(\.[0-9]+)?e\+[0-9]+$/i', $barcode)) {
            $barcode = number_format((float)$barcode, 0, '', '');
        }
        
        $costPrice = parseCleanNumber($item['cost_price'] ?? 0, 0.0);
        $sellingPrice = parseCleanNumber($item['selling_price'] ?? 0, 0.0);
        $stockQuantity = (int)parseCleanNumber($item['stock_quantity'] ?? 0, 0);
        $minStock = (int)parseCleanNumber($item['min_stock'] ?? 5, 5);
        $expiryDate = parseCleanDate($item['expiry_date'] ?? null);
        
        // Category resolution
        $categoryId = null;
        $catName = trim($item['category'] ?? '');
        if (!empty($catName)) {
            $normCat = mb_strtolower($catName, 'UTF-8');
            if (isset($catMap[$normCat])) {
                $categoryId = $catMap[$normCat];
            } elseif ($autoCreateCategories) {
                try {
                    $stmtNewCat->execute([$catName]);
                    $newCatId = (int)$db->lastInsertId();
                    $catMap[$normCat] = $newCatId;
                    $categoryId = $newCatId;
                } catch (Exception $ex) {
                    $categoryId = null;
                }
            }
        }
        
        // Unit resolution
        $unitName = trim($item['unit'] ?? '');
        if (empty($unitName)) {
            $unitName = 'ແຜງ';
        }
        $normUnit = mb_strtolower($unitName, 'UTF-8');
        if (isset($unitMap[$normUnit])) {
            $unitName = $unitMap[$normUnit];
        } elseif ($autoCreateUnits) {
            try {
                $stmtNewUnit->execute([$unitName]);
                $unitMap[$normUnit] = $unitName;
            } catch (Exception $ex) {}
        }
        
        // Check if existing product
        $existingProduct = null;
        if (!empty($barcode)) {
            $stmtFindBarcode->execute([$barcode]);
            $existingProduct = $stmtFindBarcode->fetch();
        } else {
            $stmtFindNameNoBarcode->execute([$name]);
            $existingProduct = $stmtFindNameNoBarcode->fetch();
        }
        
        if ($existingProduct) {
            if ($duplicateAction === 'skip') {
                $skippedCount++;
                continue;
            }
            
            // Update existing product
            $prodId = (int)$existingProduct['id'];
            $oldStock = (int)($existingProduct['stock_quantity'] ?? 0);
            
            $stmtUpdateProd->execute([
                $barcode,
                $name,
                $categoryId,
                $costPrice,
                $sellingPrice,
                $stockQuantity,
                $minStock,
                $unitName,
                $expiryDate,
                $prodId
            ]);
            
            // Log stock adjustment if stock quantity changed
            if ($stockQuantity !== $oldStock) {
                $diff = $stockQuantity - $oldStock;
                try {
                    $stmtStockAdj->execute([$prodId, $diff, $currentUserId]);
                } catch (Exception $ex) {}
            }
            
            $updatedCount++;
        } else {
            // Insert new product
            $stmtInsertProd->execute([
                $barcode,
                $name,
                $categoryId,
                $costPrice,
                $sellingPrice,
                $stockQuantity,
                $minStock,
                $unitName,
                $expiryDate
            ]);
            
            $newProdId = (int)$db->lastInsertId();
            
            // Log initial stock if > 0
            if ($stockQuantity > 0) {
                try {
                    $stmtStockIn->execute([$newProdId, $stockQuantity, $currentUserId]);
                } catch (Exception $ex) {}
            }
            
            $createdCount++;
        }
    }
    
    $db->commit();
    
    $totalProcessed = $createdCount + $updatedCount + $skippedCount;
    $msg = "ນຳເຂົ້າສິນຄ້າສຳເລັດ {$totalProcessed} ລາຍການ (ເພີ່ມໃໝ່: {$createdCount}, ອັບເດດ: {$updatedCount}";
    if ($skippedCount > 0) {
        $msg .= ", ຂ້າມ: {$skippedCount}";
    }
    $msg .= ")";
    
    echo json_encode([
        'success' => true,
        'message' => $msg,
        'stats' => [
            'total' => $totalProcessed,
            'created' => $createdCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'errors' => $errors
        ]
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'ການນຳເຂົ້າຂໍ້ມູນລົ້ມເຫຼວ: ' . safeErrorMessage($e)
    ]);
}
