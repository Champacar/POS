<?php
/**
 * Shift Management API
 * ລະບົບຈັດການກະຂາຍ ແລະ ປິດຍອດປະຈຳວັນ (POS Shift API)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ']);
    exit;
}

$db = getDB();
$userId = getCurrentUserId();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    if ($action === 'current') {
        // Find open shift for current user
        $stmt = $db->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$shift) {
            echo json_encode(['success' => true, 'has_shift' => false]);
            exit;
        }

        // Calculate live sales statistics for this shift
        $stmtStats = $db->prepare("
            SELECT 
                COUNT(*) as total_bills,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(SUM(cash_amount), 0) as total_cash,
                COALESCE(SUM(transfer_amount), 0) as total_transfer,
                COALESCE(SUM(discount_amount), 0) as total_discount,
                COALESCE(SUM(CASE WHEN payment_method = 'credit' THEN total_amount ELSE 0 END), 0) as total_credit,
                COALESCE(SUM(CASE WHEN currency = 'THB' THEN paid_amount_foreign ELSE 0 END), 0) as total_thb_collected,
                COALESCE(SUM(CASE WHEN currency = 'USD' THEN paid_amount_foreign ELSE 0 END), 0) as total_usd_collected
            FROM sales 
            WHERE shift_id = ? AND payment_status != 'cancelled'
        ");
        $stmtStats->execute([$shift['id']]);
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

        $startingCash = (float)$shift['starting_cash'];
        $totalCash = (float)$stats['total_cash'];
        $expectedCash = $startingCash + $totalCash;

        echo json_encode([
            'success' => true,
            'has_shift' => true,
            'shift' => array_merge($shift, [
                'total_bills' => (int)$stats['total_bills'],
                'total_sales' => (float)$stats['total_sales'],
                'total_cash' => $totalCash,
                'total_transfer' => (float)$stats['total_transfer'],
                'total_discount' => (float)$stats['total_discount'],
                'total_credit' => (float)$stats['total_credit'],
                'total_thb_collected' => (float)$stats['total_thb_collected'],
                'total_usd_collected' => (float)$stats['total_usd_collected'],
                'expected_cash' => $expectedCash
            ])
        ]);
        exit;
    }

    if ($action === 'open') {
        // Check if there is already an open shift
        $stmtCheck = $db->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' LIMIT 1");
        $stmtCheck->execute([$userId]);
        if ($stmtCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'ທ່ານມີກະຂາຍທີ່ເປີດຢູ່ແລ້ວ!']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $startingCash = max(0, (float)($input['starting_cash'] ?? 0));
        $note = trim($input['note'] ?? '');

        // Generate shift number: SH-YYYYMMDD-COUNT
        $todayPrefix = 'SH-' . date('Ymd') . '-';
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM shifts WHERE shift_number LIKE ?");
        $stmtCount->execute([$todayPrefix . '%']);
        $countToday = (int)$stmtCount->fetchColumn() + 1;
        $shiftNumber = $todayPrefix . str_pad($countToday, 2, '0', STR_PAD_LEFT);

        $stmtInsert = $db->prepare("
            INSERT INTO shifts (shift_number, user_id, opened_at, starting_cash, status, note)
            VALUES (?, ?, NOW(), ?, 'open', ?)
        ");
        $stmtInsert->execute([$shiftNumber, $userId, $startingCash, $note]);
        $shiftId = $db->lastInsertId();

        logActivity('shift_open', "ເປີດກະຂາຍໃໝ່ {$shiftNumber} (ເງິນທອນຕັ້ງຕົ້ນ: " . number_format($startingCash) . " ກີບ)", 'shift', [
            'shift_id' => $shiftId,
            'shift_number' => $shiftNumber,
            'starting_cash' => $startingCash
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'ເປີດກະຂາຍສຳເລັດ!',
            'shift_id' => $shiftId,
            'shift_number' => $shiftNumber,
            'starting_cash' => $startingCash
        ]);
        exit;
    }

    if ($action === 'close') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $shiftId = (int)($input['shift_id'] ?? 0);

        if ($shiftId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບລະຫັດກະທີ່ຕ້ອງການປິດ']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM shifts WHERE id = ? AND status = 'open'");
        $stmt->execute([$shiftId]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$shift) {
            echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບກະຂາຍ ຫຼື ກະນີ້ຖືກປິດແລ້ວ']);
            exit;
        }

        // Shop settings for exchange rates
        $settings = getShopSettings();
        $rateThb = (float)($settings['exchange_rate_thb'] ?? 680.00);
        $rateUsd = (float)($settings['exchange_rate_usd'] ?? 22000.00);

        // Calculate live sales statistics
        $stmtStats = $db->prepare("
            SELECT 
                COUNT(*) as total_bills,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(SUM(cash_amount), 0) as total_cash,
                COALESCE(SUM(transfer_amount), 0) as total_transfer,
                COALESCE(SUM(discount_amount), 0) as total_discount,
                COALESCE(SUM(CASE WHEN payment_method = 'credit' THEN total_amount ELSE 0 END), 0) as total_credit
            FROM sales 
            WHERE shift_id = ? AND payment_status != 'cancelled'
        ");
        $stmtStats->execute([$shiftId]);
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

        $startingCash = (float)$shift['starting_cash'];
        $totalSales = (float)$stats['total_sales'];
        $totalCash = (float)$stats['total_cash'];
        $totalTransfer = (float)$stats['total_transfer'];
        $totalCredit = (float)$stats['total_credit'];
        $totalDiscount = (float)$stats['total_discount'];
        $totalBills = (int)$stats['total_bills'];

        $expectedCashLak = $startingCash + $totalCash;

        // Cash counted by cashier
        $cashCountedLak = max(0, (float)($input['cash_counted_lak'] ?? 0));
        $cashCountedThb = max(0, (float)($input['cash_counted_thb'] ?? 0));
        $cashCountedUsd = max(0, (float)($input['cash_counted_usd'] ?? 0));
        $note = trim($input['note'] ?? '');

        // Convert foreign counted cash to LAK equivalent for cash balancing
        $totalCountedLak = $cashCountedLak + ($cashCountedThb * $rateThb) + ($cashCountedUsd * $rateUsd);
        $differenceLak = $totalCountedLak - $expectedCashLak;

        // Update shift record
        $stmtUpdate = $db->prepare("
            UPDATE shifts SET
                closed_at = NOW(),
                total_sales_amount = ?,
                total_cash_amount = ?,
                total_transfer_amount = ?,
                total_credit_amount = ?,
                total_bills = ?,
                total_discount = ?,
                cash_counted_lak = ?,
                cash_counted_thb = ?,
                cash_counted_usd = ?,
                expected_cash_lak = ?,
                difference_lak = ?,
                status = 'closed',
                note = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $totalSales,
            $totalCash,
            $totalTransfer,
            $totalCredit,
            $totalBills,
            $totalDiscount,
            $cashCountedLak,
            $cashCountedThb,
            $cashCountedUsd,
            $expectedCashLak,
            $differenceLak,
            $note,
            $shiftId
        ]);

        logActivity('shift_close', "ປິດກະຂາຍ {$shift['shift_number']} (ຍອດນັບ: " . number_format($cashCountedLak) . " ກີບ, ຜົນຕ່າງ: " . number_format($differenceLak) . " ກີບ, ຍອດຂາຍ: " . number_format($totalSales) . " ກີບ)", 'shift', [
            'shift_id' => $shiftId,
            'shift_number' => $shift['shift_number'],
            'total_sales' => $totalSales,
            'counted_cash' => $cashCountedLak,
            'difference' => $differenceLak
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'ປິດກະຂາຍສຳເລັດ!',
            'shift_id' => $shiftId,
            'difference_lak' => $differenceLak
        ]);
        exit;
    }

    if ($action === 'summary') {
        $shiftId = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("
            SELECT s.*, u.full_name as cashier_name, u.username as cashier_username
            FROM shifts s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$shiftId]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$shift) {
            echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນກະ']);
            exit;
        }

        echo json_encode(['success' => true, 'shift' => $shift]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => safeErrorMessage($e)]);
}
