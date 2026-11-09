<?php
/**
 * POSPharma - Profit & Loss and Sales Analytics API
 * ລະບົບຄຳນວນລາຍງານກຳໄລ-ຂາດທຶນ & ວິເຄາະຍອດຂາຍເຊີງເລິກ 100% Offline
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ']);
    exit;
}

if (!canViewProfitLoss()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນກຳໄລ-ຂາດທຶນ']);
    exit;
}

$db = getDB();

$startDate = isset($_GET['start']) ? trim($_GET['start']) : date('Y-m-01');
$endDate = isset($_GET['end']) ? trim($_GET['end']) : date('Y-m-d');

// Basic date validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $endDate = date('Y-m-d');
if ($startDate > $endDate) {
    $tmp = $startDate;
    $startDate = $endDate;
    $endDate = $tmp;
}

try {
    // ----------------------------------------------------
    // 1. Executive Sales KPIs
    // ----------------------------------------------------
    $salesStmt = $db->prepare("
        SELECT 
            COALESCE(COUNT(*), 0) as bill_count,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(subtotal), 0) as total_subtotal,
            COALESCE(SUM(discount_amount), 0) as total_discount,
            COALESCE(SUM(tax_amount), 0) as total_tax,
            COALESCE(SUM(cash_amount), 0) as cash_sales,
            COALESCE(SUM(transfer_amount), 0) as transfer_sales,
            COALESCE(SUM(CASE WHEN currency = 'LAK' OR currency IS NULL OR currency = '' THEN total_amount ELSE 0 END), 0) as lak_sales,
            COALESCE(SUM(CASE WHEN currency = 'THB' THEN total_amount ELSE 0 END), 0) as thb_sales,
            COALESCE(SUM(CASE WHEN currency = 'USD' THEN total_amount ELSE 0 END), 0) as usd_sales
        FROM sales
        WHERE payment_status != 'cancelled'
        AND DATE(created_at) BETWEEN ? AND ?
    ");
    $salesStmt->execute([$startDate, $endDate]);
    $salesKpi = $salesStmt->fetch(PDO::FETCH_ASSOC);

    $totalRevenue = (float)$salesKpi['total_revenue'];
    $billCount = (int)$salesKpi['bill_count'];

    // ----------------------------------------------------
    // 2. COGS (Cost of Goods Sold) & Items Sold Count
    // ----------------------------------------------------
    $cogsStmt = $db->prepare("
        SELECT 
            COALESCE(SUM(si.quantity), 0) as total_items_sold,
            COALESCE(SUM(si.quantity * COALESCE(NULLIF(si.cost_price, 0), p.cost_price, 0)), 0) as total_cogs
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        LEFT JOIN products p ON si.product_id = p.id
        WHERE s.payment_status != 'cancelled'
        AND DATE(s.created_at) BETWEEN ? AND ?
    ");
    $cogsStmt->execute([$startDate, $endDate]);
    $cogsRow = $cogsStmt->fetch(PDO::FETCH_ASSOC);

    $totalCogs = (float)$cogsRow['total_cogs'];
    $itemsSold = (int)$cogsRow['total_items_sold'];

    // ----------------------------------------------------
    // 3. Operating Expenses (OpEx)
    // ----------------------------------------------------
    $expStmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_expenses
        FROM expenses
        WHERE expense_date BETWEEN ? AND ?
    ");
    $expStmt->execute([$startDate, $endDate]);
    $totalExpenses = (float)$expStmt->fetchColumn();

    // Financial Margins
    $grossProfit = $totalRevenue - $totalCogs;
    $grossMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;
    $netProfit = $grossProfit - $totalExpenses;
    $netMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;
    $avgBill = $billCount > 0 ? ($totalRevenue / $billCount) : 0;

    // ----------------------------------------------------
    // 4. Trend Over Time (Daily or Monthly Data Points)
    // ----------------------------------------------------
    $trendStmt = $db->prepare("
        SELECT 
            DATE(s.created_at) as sale_date,
            COALESCE(SUM(s.total_amount), 0) as daily_revenue,
            COALESCE(SUM(si.quantity * COALESCE(NULLIF(si.cost_price, 0), p.cost_price, 0)), 0) as daily_cogs
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        LEFT JOIN products p ON si.product_id = p.id
        WHERE s.payment_status != 'cancelled'
        AND DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY DATE(s.created_at)
        ORDER BY DATE(s.created_at) ASC
    ");
    $trendStmt->execute([$startDate, $endDate]);
    $trendRows = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

    // Map daily expenses
    $expDailyStmt = $db->prepare("
        SELECT expense_date, COALESCE(SUM(amount), 0) as daily_expense
        FROM expenses
        WHERE expense_date BETWEEN ? AND ?
        GROUP BY expense_date
    ");
    $expDailyStmt->execute([$startDate, $endDate]);
    $dailyExpensesMap = [];
    foreach ($expDailyStmt->fetchAll(PDO::FETCH_ASSOC) as $er) {
        $dailyExpensesMap[$er['expense_date']] = (float)$er['daily_expense'];
    }

    $trendLabels = [];
    $trendRevenue = [];
    $trendCogs = [];
    $trendGrossProfit = [];
    $trendExpenses = [];
    $trendNetProfit = [];

    foreach ($trendRows as $tr) {
        $d = $tr['sale_date'];
        $rev = (float)$tr['daily_revenue'];
        $cogs = (float)$tr['daily_cogs'];
        $gp = $rev - $cogs;
        $exp = $dailyExpensesMap[$d] ?? 0;
        $np = $gp - $exp;

        $trendLabels[] = date('d/m', strtotime($d));
        $trendRevenue[] = $rev;
        $trendCogs[] = $cogs;
        $trendGrossProfit[] = $gp;
        $trendExpenses[] = $exp;
        $trendNetProfit[] = $np;
    }

    // ----------------------------------------------------
    // 5. Category Profitability Breakdown
    // ----------------------------------------------------
    $catStmt = $db->prepare("
        SELECT 
            COALESCE(c.id, 0) as category_id,
            COALESCE(c.name, 'ອື່ນໆ') as category_name,
            COALESCE(SUM(si.quantity), 0) as items_sold,
            COALESCE(SUM(si.total_price), 0) as category_revenue,
            COALESCE(SUM(si.quantity * COALESCE(NULLIF(si.cost_price, 0), p.cost_price, 0)), 0) as category_cogs
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        LEFT JOIN products p ON si.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE s.payment_status != 'cancelled'
        AND DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY c.id, c.name
        ORDER BY category_revenue DESC
    ");
    $catStmt->execute([$startDate, $endDate]);
    $catRows = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    $categoriesData = [];
    foreach ($catRows as $cr) {
        $cRev = (float)$cr['category_revenue'];
        $cCogs = (float)$cr['category_cogs'];
        $cProfit = $cRev - $cCogs;
        $cMargin = $cRev > 0 ? ($cProfit / $cRev) * 100 : 0;
        $cShare = $totalRevenue > 0 ? ($cRev / $totalRevenue) * 100 : 0;

        $categoriesData[] = [
            'category_id' => (int)$cr['category_id'],
            'category_name' => $cr['category_name'],
            'items_sold' => (int)$cr['items_sold'],
            'revenue' => $cRev,
            'cogs' => $cCogs,
            'profit' => $cProfit,
            'margin_percent' => round($cMargin, 1),
            'revenue_share_percent' => round($cShare, 1)
        ];
    }

    // ----------------------------------------------------
    // 6. Peak Sales Hours (08:00 - 22:00)
    // ----------------------------------------------------
    $peakStmt = $db->prepare("
        SELECT 
            HOUR(created_at) as sale_hour,
            COUNT(*) as bill_count,
            COALESCE(SUM(total_amount), 0) as hourly_revenue
        FROM sales
        WHERE payment_status != 'cancelled'
        AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY HOUR(created_at)
        ORDER BY HOUR(created_at) ASC
    ");
    $peakStmt->execute([$startDate, $endDate]);
    $peakRaw = $peakStmt->fetchAll(PDO::FETCH_ASSOC);

    $peakMap = [];
    foreach ($peakRaw as $pr) {
        $peakMap[(int)$pr['sale_hour']] = [
            'bills' => (int)$pr['bill_count'],
            'revenue' => (float)$pr['hourly_revenue']
        ];
    }

    $peakHours = [];
    for ($h = 8; $h <= 21; $h++) {
        $hourStr = sprintf('%02d:00', $h);
        $peakHours[] = [
            'hour' => $hourStr,
            'bills' => $peakMap[$h]['bills'] ?? 0,
            'revenue' => $peakMap[$h]['revenue'] ?? 0
        ];
    }

    // ----------------------------------------------------
    // 7. Profit by Product (Medicine Drilldown Table)
    // ----------------------------------------------------
    $prodStmt = $db->prepare("
        SELECT 
            si.product_id,
            si.product_name,
            COALESCE(p.barcode, '') as barcode,
            COALESCE(p.unit, 'ແຜງ') as unit,
            COALESCE(c.name, 'ທົ່ວໄປ') as category_name,
            COALESCE(SUM(si.quantity), 0) as quantity_sold,
            COALESCE(AVG(si.unit_price), 0) as avg_selling_price,
            COALESCE(AVG(COALESCE(NULLIF(si.cost_price, 0), p.cost_price, 0)), 0) as avg_cost_price,
            COALESCE(SUM(si.total_price), 0) as total_revenue,
            COALESCE(SUM(si.quantity * COALESCE(NULLIF(si.cost_price, 0), p.cost_price, 0)), 0) as total_cogs
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        LEFT JOIN products p ON si.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE s.payment_status != 'cancelled'
        AND DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY si.product_id, si.product_name, p.barcode, p.unit, c.name
        ORDER BY (SUM(si.total_price) - SUM(si.quantity * COALESCE(NULLIF(si.cost_price, 0), p.cost_price, 0))) DESC
        LIMIT 100
    ");
    $prodStmt->execute([$startDate, $endDate]);
    $prodRows = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

    $productsData = [];
    foreach ($prodRows as $pr) {
        $pRev = (float)$pr['total_revenue'];
        $pCogs = (float)$pr['total_cogs'];
        $pProfit = $pRev - $pCogs;
        $pMargin = $pRev > 0 ? ($pProfit / $pRev) * 100 : 0;

        $productsData[] = [
            'product_id' => (int)$pr['product_id'],
            'product_name' => $pr['product_name'],
            'barcode' => $pr['barcode'],
            'unit' => $pr['unit'],
            'category_name' => $pr['category_name'],
            'quantity_sold' => (int)$pr['quantity_sold'],
            'avg_selling_price' => (float)$pr['avg_selling_price'],
            'avg_cost_price' => (float)$pr['avg_cost_price'],
            'total_revenue' => $pRev,
            'total_cogs' => $pCogs,
            'total_profit' => $pProfit,
            'margin_percent' => round($pMargin, 1)
        ];
    }

    echo json_encode([
        'success' => true,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'kpis' => [
            'total_revenue' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => round($grossMargin, 1),
            'operating_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'net_margin' => round($netMargin, 1),
            'bill_count' => $billCount,
            'items_sold' => $itemsSold,
            'average_bill_value' => round($avgBill, 0),
            'cash_sales' => (float)$salesKpi['cash_sales'],
            'transfer_sales' => (float)$salesKpi['transfer_sales'],
            'currencies' => [
                'lak' => (float)$salesKpi['lak_sales'],
                'thb' => (float)$salesKpi['thb_sales'],
                'usd' => (float)$salesKpi['usd_sales']
            ]
        ],
        'trend' => [
            'labels' => $trendLabels,
            'revenue' => $trendRevenue,
            'cogs' => $trendCogs,
            'gross_profit' => $trendGrossProfit,
            'expenses' => $trendExpenses,
            'net_profit' => $trendNetProfit
        ],
        'categories' => $categoriesData,
        'peak_hours' => $peakHours,
        'products' => $productsData
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
