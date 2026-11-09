<?php
/**
 * POSPharma - Advanced Financial Statements & Tax Report API
 * 100% Offline Tax & Cash Flow Analytics
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
            $action = $_GET['action'] ?? 'summary';
            $startDate = isset($_GET['start']) ? trim($_GET['start']) : date('Y-m-01');
            $endDate = isset($_GET['end']) ? trim($_GET['end']) : date('Y-m-d');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-01');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = date('Y-m-d');
            if ($startDate > $endDate) {
                $tmp = $startDate;
                $startDate = $endDate;
                $endDate = $tmp;
            }

            if ($action === 'tax_invoices') {
                $stmt = $db->prepare("
                    SELECT s.*, u.full_name as cashier_name
                    FROM sales s
                    LEFT JOIN users u ON s.user_id = u.id
                    WHERE s.payment_status != 'cancelled'
                      AND s.is_tax_invoice = 1
                      AND DATE(s.created_at) BETWEEN ? AND ?
                    ORDER BY s.created_at DESC
                ");
                $stmt->execute([$startDate, $endDate]);
                $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'invoices' => $invoices
                ]);
                exit;
            }

            // Summary metrics
            // 1. VAT Sales summary
            $stmtVat = $db->prepare("
                SELECT 
                    COUNT(*) as total_bills,
                    COALESCE(SUM(total_amount), 0) as total_sales,
                    COALESCE(SUM(subtotal - discount_amount), 0) as net_sales,
                    COALESCE(SUM(tax_amount), 0) as total_vat,
                    COALESCE(SUM(CASE WHEN is_tax_invoice = 1 THEN 1 ELSE 0 END), 0) as tax_invoice_count,
                    COALESCE(SUM(CASE WHEN is_tax_invoice = 1 THEN total_amount ELSE 0 END), 0) as tax_invoice_total,
                    COALESCE(SUM(CASE WHEN is_tax_invoice = 1 THEN tax_amount ELSE 0 END), 0) as tax_invoice_vat,
                    COALESCE(SUM(CASE WHEN is_tax_invoice = 1 THEN (subtotal - discount_amount) ELSE 0 END), 0) as tax_invoice_subtotal
                FROM sales
                WHERE payment_status != 'cancelled'
                  AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmtVat->execute([$startDate, $endDate]);
            $vatStats = $stmtVat->fetch(PDO::FETCH_ASSOC);

            // 2. Cash Flow statement
            // Inflows: Cash & Transfer from sales
            $stmtInflow = $db->prepare("
                SELECT 
                    COALESCE(SUM(cash_amount), 0) as cash_sales,
                    COALESCE(SUM(transfer_amount), 0) as transfer_sales,
                    COALESCE(SUM(total_amount), 0) as total_inflow
                FROM sales
                WHERE payment_status != 'cancelled'
                  AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmtInflow->execute([$startDate, $endDate]);
            $inflow = $stmtInflow->fetch(PDO::FETCH_ASSOC);

            // Outflows: General Expenses
            $stmtExpense = $db->prepare("
                SELECT COALESCE(SUM(amount), 0) as total_general_expense
                FROM expenses
                WHERE DATE(expense_date) BETWEEN ? AND ?
            ");
            $stmtExpense->execute([$startDate, $endDate]);
            $generalExpense = (float)$stmtExpense->fetchColumn();

            // Outflows: Stock Purchases (Stock In * Cost Price or PO received)
            $stmtStockIn = $db->prepare("
                SELECT COALESCE(SUM(sh.quantity * p.cost_price), 0) as stock_purchase_amount
                FROM stock_history sh
                JOIN products p ON sh.product_id = p.id
                WHERE sh.type = 'in'
                  AND DATE(sh.created_at) BETWEEN ? AND ?
            ");
            $stmtStockIn->execute([$startDate, $endDate]);
            $stockPurchase = (float)$stmtStockIn->fetchColumn();

            // If stock_history was empty, check purchase_orders
            if ($stockPurchase == 0) {
                $stmtPo = $db->prepare("
                    SELECT COALESCE(SUM(total_amount), 0) FROM purchase_orders
                    WHERE status = 'received' AND DATE(created_at) BETWEEN ? AND ?
                ");
                $stmtPo->execute([$startDate, $endDate]);
                $stockPurchase = (float)$stmtPo->fetchColumn();
            }

            $totalOutflow = $generalExpense + $stockPurchase;
            $totalInflow = (float)$inflow['total_inflow'];
            $netCashFlow = $totalInflow - $totalOutflow;

            // Invoices list (top 50)
            $stmtList = $db->prepare("
                SELECT s.*, u.full_name as cashier_name
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.payment_status != 'cancelled'
                  AND s.is_tax_invoice = 1
                  AND DATE(s.created_at) BETWEEN ? AND ?
                ORDER BY s.created_at DESC
                LIMIT 100
            ");
            $stmtList->execute([$startDate, $endDate]);
            $invoices = $stmtList->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'period' => ['start' => $startDate, 'end' => $endDate],
                'vat_stats' => $vatStats,
                'cash_flow' => [
                    'cash_sales' => (float)$inflow['cash_sales'],
                    'transfer_sales' => (float)$inflow['transfer_sales'],
                    'total_inflow' => $totalInflow,
                    'general_expense' => $generalExpense,
                    'stock_purchase' => $stockPurchase,
                    'total_outflow' => $totalOutflow,
                    'net_cash_flow' => $netCashFlow
                ],
                'invoices' => $invoices
            ]);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';

            if ($action === 'save_tax_settings') {
                $taxId = trim($input['tax_id'] ?? '');
                $companyName = trim($input['company_name'] ?? '');
                $taxPrefix = trim($input['tax_invoice_prefix'] ?? 'TAX-');
                $taxRate = (float)($input['tax_rate'] ?? 7);
                $taxType = in_array($input['tax_type'] ?? '', ['excluded', 'included', 'none'], true) ? $input['tax_type'] : 'excluded';

                $stmt = $db->prepare("
                    UPDATE shop_settings 
                    SET tax_id = ?, company_name = ?, tax_invoice_prefix = ?, tax_rate = ?, tax_type = ?
                    WHERE id = 1
                ");
                $stmt->execute([
                    $taxId ?: null,
                    $companyName ?: null,
                    $taxPrefix ?: 'TAX-',
                    $taxRate,
                    $taxType
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'ບັນທຶກການຕັ້ງຄ່າຂໍ້ມູນອາກອນສຳເລັດ'
                ]);
                exit;
            }

            echo json_encode(['success' => false, 'message' => 'Action ບໍ່ຖືກຕ້ອງ']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
