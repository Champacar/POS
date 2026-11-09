<?php
/**
 * Sales API
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
            // Get sale by Invoice Number (for Barcode / QR Scanner lookup)
            $invoice = isset($_GET['invoice']) ? trim($_GET['invoice']) : '';
            if (!empty($invoice)) {
                $stmt = $db->prepare("SELECT s.*, u.full_name as cashier FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.invoice_number = ?");
                $stmt->execute([$invoice]);
                $sale = $stmt->fetch();
                if ($sale) {
                    $stmt2 = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
                    $stmt2->execute([$sale['id']]);
                    $sale['items'] = $stmt2->fetchAll();
                    echo json_encode(['success' => true, 'sale' => $sale]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນບິນ']);
                }
                exit;
            }

            // Get sale by ID
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $stmt = $db->prepare("SELECT * FROM sales WHERE id = ?");
                $stmt->execute([$id]);
                $sale = $stmt->fetch();
                
                if ($sale) {
                    $stmt2 = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
                    $stmt2->execute([$id]);
                    $sale['items'] = $stmt2->fetchAll();
                    echo json_encode(['success' => true, 'sale' => $sale]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນ']);
                }
            } else {
                // List recent sales
                $startDate = $_GET['start'] ?? date('Y-m-d');
                $endDate = $_GET['end'] ?? date('Y-m-d');
                $stmt = $db->prepare("SELECT * FROM sales WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 50");
                $stmt->execute([$startDate, $endDate]);
                echo json_encode(['success' => true, 'sales' => $stmt->fetchAll()]);
            }
            break;
            
        case 'POST':
            // Verify CSRF token
            requireCSRFToken();

            $input = json_decode(file_get_contents('php://input'), true);

            // Handle retroactive Tax Invoice issuance
            if (($input['action'] ?? '') === 'issue_tax_invoice') {
                $saleId = (int)($input['sale_id'] ?? 0);
                if ($saleId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID ໃບບິນບໍ່ຖືກຕ້ອງ']);
                    exit;
                }

                $stmtSale = $db->prepare("SELECT * FROM sales WHERE id = ?");
                $stmtSale->execute([$saleId]);
                $sale = $stmtSale->fetch();
                if (!$sale) {
                    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນການຂາຍ']);
                    exit;
                }

                $taxInvoiceNumber = $sale['tax_invoice_number'] ?: generateTaxInvoiceNumber();
                $custTaxId = trim($input['customer_tax_id'] ?? $sale['customer_tax_id'] ?? '');
                $custCompany = trim($input['customer_company'] ?? $sale['customer_company'] ?? '');
                $custAddress = trim($input['customer_address'] ?? $sale['customer_address'] ?? '');

                // Optional custom VAT rate recalculation if requested
                $taxAmount = (float)$sale['tax_amount'];
                $totalAmount = (float)$sale['total_amount'];
                if (isset($input['tax_rate']) && is_numeric($input['tax_rate'])) {
                    $vatRate = (float)$input['tax_rate'];
                    $netSubtotal = (float)$sale['subtotal'] - (float)$sale['discount_amount'];
                    if ($vatRate > 0) {
                        $taxAmount = round($netSubtotal * ($vatRate / 100));
                        $totalAmount = $netSubtotal + $taxAmount;
                    }
                }

                $stmtUp = $db->prepare("UPDATE sales SET is_tax_invoice = 1, tax_invoice_number = ?, customer_tax_id = ?, customer_company = ?, customer_address = ?, tax_amount = ?, total_amount = ? WHERE id = ?");
                $stmtUp->execute([
                    $taxInvoiceNumber,
                    $custTaxId ?: null,
                    $custCompany ?: null,
                    $custAddress ?: null,
                    $taxAmount,
                    $totalAmount,
                    $saleId
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'ອອກໃບກຳກັບພາສີສຳເລັດ',
                    'tax_invoice_number' => $taxInvoiceNumber,
                    'sale_id' => $saleId
                ]);
                exit;
            }
            
            // Create new sale
            if (empty($input['items'])) {
                echo json_encode(['success' => false, 'message' => 'ບໍ່ມີລາຍການ']);
                exit;
            }
            
            $db->beginTransaction();
            
            // Validate items, quantities, expiry and stock availability before proceeding
            $today = date('Y-m-d');
            $computedSubtotal = 0;

            foreach ($input['items'] as $item) {
                $qty = (int)($item['quantity'] ?? 0);
                if ($qty <= 0) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'ຈຳນວນສິນຄ້າຕ້ອງຫຼາຍກວ່າ 0']);
                    exit;
                }
                
                $unitPrice = (float)($item['unit_price'] ?? $item['price'] ?? 0);
                $computedSubtotal += ($qty * $unitPrice);

                if (!empty($item['product_id'])) {
                    $stmtCheck = $db->prepare("SELECT name, stock_quantity, expiry_date FROM products WHERE id = ?");
                    $stmtCheck->execute([$item['product_id']]);
                    $prod = $stmtCheck->fetch();
                    if ($prod) {
                        // Check expiry date
                        if (!empty($prod['expiry_date']) && $prod['expiry_date'] < $today) {
                            $db->rollBack();
                            echo json_encode([
                                'success' => false,
                                'message' => "ສິນຄ້າ '{$prod['name']}' ໝົດອາຍຸແລ້ວ ({$prod['expiry_date']}) ບໍ່ສາມາດຂາຍໄດ້!"
                            ]);
                            exit;
                        }
                        if ((int)$prod['stock_quantity'] < $qty) {
                            $db->rollBack();
                            $avail = (int)$prod['stock_quantity'];
                            echo json_encode([
                                'success' => false,
                                'message' => "ສິນຄ້າ '{$item['product_name']}' ສະຕ໋ອກບໍ່ພຽງພໍ (ຍັງເຫຼືອ {$avail})"
                            ]);
                            exit;
                        }
                    }
                }
            }

            // Recalculate discount, tax, and total on server
            $discountAmount = max(0, min($computedSubtotal, (float)($input['discount_amount'] ?? 0)));
            $afterDiscount = max(0, $computedSubtotal - $discountAmount);

            $shopSettings = null;
            try { $shopSettings = getShopSettings(); } catch (Exception $e) {}
            $taxRate = (float)($shopSettings['tax_rate'] ?? 7);
            $taxType = $shopSettings['tax_type'] ?? 'excluded';

            $isTaxInvoice = !empty($input['is_tax_invoice']) ? 1 : 0;
            $taxInvoiceNumber = null;
            $customerTaxId = null;
            $customerCompany = null;
            $customerAddress = null;

            if ($isTaxInvoice) {
                $taxInvoiceNumber = generateTaxInvoiceNumber();
                $customerTaxId = trim($input['customer_tax_id'] ?? '');
                $customerCompany = trim($input['customer_company'] ?? '');
                $customerAddress = trim($input['customer_address'] ?? '');
                if (isset($input['tax_rate']) && is_numeric($input['tax_rate'])) {
                    $taxRate = (float)$input['tax_rate'];
                }
            }

            $computedTax = 0;
            $computedTotal = $afterDiscount;
            if ($taxType === 'excluded' || ($isTaxInvoice && $taxRate > 0)) {
                $computedTax = round($afterDiscount * ($taxRate / 100));
                $computedTotal = $afterDiscount + $computedTax;
            } elseif ($taxType === 'included') {
                $computedTax = round($afterDiscount - ($afterDiscount / (1 + ($taxRate / 100))));
                $computedTotal = $afterDiscount;
            } else {
                $computedTax = 0;
                $computedTotal = $afterDiscount;
            }
            
            $invoiceNumber = generateInvoiceNumber();
            $paymentMethod = $input['payment_method'] ?? 'cash';
            $paymentStatus = ($paymentMethod === 'credit') ? 'pending' : 'paid';
            
            $currency = $input['currency'] ?? 'LAK';
            $exchangeRate = (float)($input['exchange_rate'] ?? 1.00);
            $paidForeign = (float)($input['paid_amount_foreign'] ?? 0.00);
            $changeForeign = (float)($input['change_amount_foreign'] ?? 0.00);
            $changeAmount = (float)($input['change_amount'] ?? 0.00);

            // Find active open shift for this user
            $currentShiftId = null;
            try {
                $shiftStmt = $db->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
                $shiftStmt->execute([getCurrentUserId()]);
                $currentShiftId = $shiftStmt->fetchColumn() ?: null;
            } catch (Exception $e) {}
            
            $stmt = $db->prepare("INSERT INTO sales (invoice_number, tax_invoice_number, user_id, customer_name, customer_tax_id, customer_company, customer_address, is_tax_invoice, subtotal, discount_amount, tax_amount, total_amount, payment_method, payment_status, cash_amount, transfer_amount, currency, exchange_rate, paid_amount_foreign, change_amount_foreign, change_amount, shift_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $invoiceNumber,
                $taxInvoiceNumber,
                getCurrentUserId(),
                $input['customer_name'] ?? null,
                $customerTaxId ?: null,
                $customerCompany ?: null,
                $customerAddress ?: null,
                $isTaxInvoice,
                $computedSubtotal,
                $discountAmount,
                $computedTax,
                $computedTotal,
                $paymentMethod,
                $paymentStatus,
                $input['cash_amount'] ?? 0,
                $input['transfer_amount'] ?? 0,
                $currency,
                $exchangeRate,
                $paidForeign,
                $changeForeign,
                $changeAmount,
                $currentShiftId
            ]);
            
            $saleId = $db->lastInsertId();
            
            // Insert sale items & update stock
            $stmtItem = $db->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, cost_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $costStmt = $db->prepare("SELECT cost_price FROM products WHERE id = ?");
            
            foreach ($input['items'] as $item) {
                $productId = $item['product_id'] ?? $item['id'] ?? null;
                $productName = $item['product_name'] ?? $item['name'] ?? 'ສິນຄ້າ';
                $qty = (int)($item['quantity'] ?? 1);
                $unitPrice = (float)($item['unit_price'] ?? $item['price'] ?? 0);
                $totalPrice = (float)($item['total_price'] ?? ($unitPrice * $qty));

                $costPrice = 0;
                if ($productId) {
                    $costStmt->execute([$productId]);
                    $costPrice = (float)$costStmt->fetchColumn();
                }

                $stmtItem->execute([
                    $saleId,
                    $productId,
                    $productName,
                    $qty,
                    $unitPrice,
                    $costPrice,
                    $totalPrice,
                ]);
                
                // Deduct stock via FIFO batches
                if ($productId) {
                    deductStockFIFO($productId, $qty);
                }
            }
            
            $db->commit();
            
            // Log user activity
            logActivity('sale_create', "ຂາຍສິນຄ້າໃໝ່ ເລກບິນ {$invoiceNumber} (ຍອດລວມ: " . number_format($totalAmount) . " ກີບ, ຮູບແບບ: {$paymentMethod})", 'sales', [
                'sale_id' => $saleId,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'items_count' => count($input['items'] ?? [])
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'ບັນທຶກການຂາຍສຳເລັດ',
                'sale_id' => $saleId,
                'invoice_number' => $invoiceNumber,
                'is_tax_invoice' => $isTaxInvoice,
                'tax_invoice_number' => $taxInvoiceNumber,
            ]);
            break;
            
        case 'DELETE':
            // Verify permission and CSRF token
            requirePermission('can_delete_sales');
            requireCSRFToken();

            // Cancel sale
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID ບໍ່ຖືກຕ້ອງ']);
                exit;
            }
            
            $db->beginTransaction();
            
            // Restore stock
            $items = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $items->execute([$id]);
            $saleItems = $items->fetchAll();
            
            foreach ($saleItems as $item) {
                if ($item['product_id']) {
                    restoreStockFIFO($item['product_id'], $item['quantity']);
                }
            }
            
            // Get invoice info before cancelling
            $saleStmt = $db->prepare("SELECT invoice_number, total_amount FROM sales WHERE id = ?");
            $saleStmt->execute([$id]);
            $saleRecord = $saleStmt->fetch();

            // Mark as cancelled
            $db->prepare("UPDATE sales SET payment_status = 'cancelled', synced = 0 WHERE id = ?")->execute([$id]);
            
            $db->commit();
            
            // Log user activity
            logActivity('sale_cancel', "ຍົກເລີກບິນການຂາຍ " . ($saleRecord['invoice_number'] ?? "ID: {$id}") . " (ຍອດ: " . number_format($saleRecord['total_amount'] ?? 0) . " ກີບ)", 'sales', [
                'sale_id' => $id,
                'invoice_number' => $saleRecord['invoice_number'] ?? null
            ]);
            
            echo json_encode(['success' => true, 'message' => 'ຍົກເລີກການຂາຍສຳເລັດ']);
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
