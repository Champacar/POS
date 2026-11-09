<?php
/**
 * Database Seeder
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once __DIR__ . '/../includes/functions.php';

if (php_sapi_name() !== 'cli' && ($_SESSION['role'] ?? '') !== 'developer') {
    http_response_code(403);
    exit('Forbidden');
}

try {
    $db = getDB();
    
    // Clear old data to prevent duplication
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE sale_items;");
    $db->exec("TRUNCATE TABLE sales;");
    $db->exec("TRUNCATE TABLE expenses;");
    $db->exec("TRUNCATE TABLE stock_history;");
    $db->exec("TRUNCATE TABLE notifications;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "Cleared existing transactions.\n";

    // Products list for reference
    $products = [
        ['id' => 1, 'name' => 'ພາຣາເຊຕາໂມນ 500mg', 'cost' => 15000, 'sell' => 20000],
        ['id' => 2, 'name' => 'ໄອບູໂພຣເຟັນ 400mg', 'cost' => 20000, 'sell' => 30000],
        ['id' => 3, 'name' => 'ອາໂມຊີຊິລິນ 500mg', 'cost' => 25000, 'sell' => 40000],
        ['id' => 4, 'name' => 'ວິຕາມິນ C 1000mg', 'cost' => 30000, 'sell' => 45000],
        ['id' => 5, 'name' => 'ຢາແກ້ໄອ Dextromethorphan', 'cost' => 18000, 'sell' => 25000],
        ['id' => 6, 'name' => 'ຢາຢອດຕາ', 'cost' => 12000, 'sell' => 18000],
        ['id' => 7, 'name' => 'ແອສໄພຣິນ 300mg', 'cost' => 10000, 'sell' => 15000],
        ['id' => 8, 'name' => 'ຢາແກ້ແພ້ Cetirizine', 'cost' => 15000, 'sell' => 22000],
        ['id' => 9, 'name' => 'ວິຕາມິນ B Complex', 'cost' => 35000, 'sell' => 50000],
        ['id' => 10, 'name' => 'ຜ້າກ໋ອສ ມ້ວນໃຫຍ່', 'cost' => 8000, 'sell' => 12000],
    ];

    // Reset stock quantities to healthy levels
    $stmtUpdateStock = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
    foreach ($products as $p) {
        $stmtUpdateStock->execute([rand(50, 150), $p['id']]);
    }
    echo "Reset product stock levels.\n";

    // 1. Generate Sales for the last 30 days
    $today = new DateTime();
    $insertSale = $db->prepare("INSERT INTO sales (invoice_number, user_id, customer_name, subtotal, discount_amount, tax_amount, total_amount, payment_method, payment_status, created_at) VALUES (?, 1, NULL, ?, ?, ?, ?, ?, 'paid', ?)");
    $insertSaleItem = $db->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
    
    $billSeq = 1;
    
    for ($i = 30; $i >= 0; $i--) {
        $date = clone $today;
        $date->sub(new DateInterval("P{$i}D"));
        
        // Random number of sales per day (more on weekends, some variance)
        $dayOfWeek = (int)$date->format('w');
        $numSales = ($dayOfWeek == 0 || $dayOfWeek == 6) ? rand(10, 20) : rand(5, 12);
        
        for ($s = 0; $s < $numSales; $s++) {
            // Set random hour between 8:00 and 19:00
            $hour = rand(8, 18);
            $minute = rand(0, 59);
            $second = rand(0, 59);
            $saleTime = clone $date;
            $saleTime->setTime($hour, $minute, $second);
            
            $formattedTime = $saleTime->format('Y-m-d H:i:s');
            
            // Generate items
            $numItems = rand(1, 3);
            $chosenProducts = array_rand($products, $numItems);
            if (!is_array($chosenProducts)) {
                $chosenProducts = [$chosenProducts];
            }
            
            $subtotal = 0;
            $itemsData = [];
            
            foreach ($chosenProducts as $pidIdx) {
                $prod = $products[$pidIdx];
                $qty = rand(1, 3);
                $totalPrice = $prod['sell'] * $qty;
                $subtotal += $totalPrice;
                
                $itemsData[] = [
                    'id' => $prod['id'],
                    'name' => $prod['name'],
                    'qty' => $qty,
                    'price' => $prod['sell'],
                    'total' => $totalPrice
                ];
            }
            
            // Random discount
            $discount = (rand(1, 10) > 8) ? (rand(1, 5) * 5000) : 0;
            if ($discount > $subtotal) $discount = 0;
            
            $afterDiscount = $subtotal - $discount;
            $tax = round($afterDiscount * 0.10);
            $total = $afterDiscount + $tax;
            
            // Invoice number
            $invoiceNumber = 'INV' . $saleTime->format('Ymd') . str_pad($billSeq++, 4, '0', STR_PAD_LEFT);
            
            // Payment method
            $payMethod = (rand(1, 10) > 4) ? 'cash' : 'transfer';
            
            $insertSale->execute([
                $invoiceNumber,
                $subtotal,
                $discount,
                $tax,
                $total,
                $payMethod,
                $formattedTime
            ]);
            
            $saleId = $db->lastInsertId();
            
            foreach ($itemsData as $item) {
                $insertSaleItem->execute([
                    $saleId,
                    $item['id'],
                    $item['name'],
                    $item['qty'],
                    $item['price'],
                    $item['total']
                ]);
                
                // Deduct stock in DB
                $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?")->execute([$item['qty'], $item['id']]);
            }
        }
    }
    
    echo "Generated sales transactions successfully.\n";

    // 2. Generate Expenses
    $insertExpense = $db->prepare("INSERT INTO expenses (category_id, title, amount, description, expense_date, user_id) VALUES (?, ?, ?, ?, ?, 1)");
    
    for ($i = 30; $i >= 0; $i--) {
        $date = clone $today;
        $date->sub(new DateInterval("P{$i}D"));
        $formattedDate = $date->format('Y-m-d');
        
        // Monthly Rent (on the 1st of month)
        if ($date->format('d') == '01') {
            $insertExpense->execute([1, 'ຄ່າເຊົ່າຮ້ານປະຈຳເດືອນ', 1500000, 'ຄ່າເຊົ່າຕຶກຮ້ານຂາຍຢາ', $formattedDate]);
        }
        
        // Electricity & Water (around the 15th)
        if ($date->format('d') == '15') {
            $insertExpense->execute([2, 'ຄ່າໄຟຟ້າປະຈຳເດືອນ', 450000, 'ຄ່າໄຟຟ້າຮ້ານ', $formattedDate]);
            $insertExpense->execute([2, 'ຄ່ານ້ຳປະປາປະຈຳເດືອນ', 80000, 'ຄ່ານ້ຳປະປາຮ້ານ', $formattedDate]);
        }
        
        // Staff salary (around the 28th)
        if ($date->format('d') == '28') {
            $insertExpense->execute([3, 'ເງິນເດືອນພະນັກງານ', 2500000, 'ເງິນເດືອນພະນັກງານຂາຍຢາ', $formattedDate]);
        }
        
        // Medicine imports (every 10 days)
        if ($i % 10 == 3) {
            $insertExpense->execute([4, 'ຊື້ຢານຳເຂົ້າປະຈຳງວດ', 3200000, 'ນຳເຂົ້າຢາພາຣາ, ອາໂມຊີ, ວິຕາມິນ', $formattedDate]);
        }
        
        // Miscellaneous (randomly)
        if (rand(1, 15) == 5) {
            $insertExpense->execute([6, 'ຊື້ເຄື່ອງໃຊ້ຫ້ອງການ', 120000, 'ເຈ້ຍ A4, ປາກກາ, ສະກັອດ', $formattedDate]);
        }
    }
    
    echo "Generated expense records successfully.\n";
    
    // 3. Generate some low stock notifications
    $stmtNotify = $db->prepare("INSERT INTO notifications (title, message, type, is_read) VALUES (?, ?, ?, 0)");
    $stmtNotify->execute(['ແຈ້ງເຕືອນສະຕ໋ອກຕໍ່າ', 'ຢາ ອາໂມຊີຊິລິນ 500mg ຍັງເຫຼືອ 4 ແຜງ (ຕ່ຳກວ່າກຳນົດ 5 ແຜງ)', 'warning']);
    $stmtNotify->execute(['ແຈ້ງເຕືອນສະຕ໋ອກຕໍ່າ', 'ຢາ ໄອບູໂພຣເຟັນ 400mg ຍັງເຫຼືອ 8 ແຜງ (ຕ່ຳກວ່າກຳນົດ 10 ແຜງ)', 'warning']);
    
    echo "Generated mock notifications.\n";
    
} catch (Exception $e) {
    echo "Error seeding database: " . $e->getMessage() . "\n";
}
