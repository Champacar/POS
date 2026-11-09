<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    echo "Running database migration for Multi-Currency & Cash Drawer...\n";

    // 1. shop_settings columns
    $shopCols = [
        'enable_multi_currency' => 'TINYINT(1) DEFAULT 1',
        'exchange_rate_thb' => 'DECIMAL(12,2) DEFAULT 680.00',
        'exchange_rate_usd' => 'DECIMAL(12,2) DEFAULT 22000.00',
        'enable_cash_drawer' => 'TINYINT(1) DEFAULT 1',
        'cash_drawer_mode' => "VARCHAR(20) DEFAULT 'driver'"
    ];

    foreach ($shopCols as $col => $def) {
        try {
            $db->exec("ALTER TABLE shop_settings ADD COLUMN $col $def");
            echo "Added shop_settings.$col\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "shop_settings.$col already exists\n";
            } else {
                echo "Notice on shop_settings.$col: " . $e->getMessage() . "\n";
            }
        }
    }

    // 2. sales columns
    $salesCols = [
        'currency' => "VARCHAR(10) DEFAULT 'LAK'",
        'exchange_rate' => 'DECIMAL(12,2) DEFAULT 1.00',
        'paid_amount_foreign' => 'DECIMAL(12,2) DEFAULT 0.00',
        'change_amount_foreign' => 'DECIMAL(12,2) DEFAULT 0.00',
        'change_amount' => 'DECIMAL(15,2) DEFAULT 0.00'
    ];

    foreach ($salesCols as $col => $def) {
        try {
            $db->exec("ALTER TABLE sales ADD COLUMN $col $def");
            echo "Added sales.$col\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "sales.$col already exists\n";
            } else {
                echo "Notice on sales.$col: " . $e->getMessage() . "\n";
            }
        }
    }

    // Set defaults for row id = 1 in shop_settings
    $db->exec("UPDATE shop_settings SET 
        enable_multi_currency = IFNULL(enable_multi_currency, 1),
        exchange_rate_thb = IFNULL(exchange_rate_thb, 680.00),
        exchange_rate_usd = IFNULL(exchange_rate_usd, 22000.00),
        enable_cash_drawer = IFNULL(enable_cash_drawer, 1),
        cash_drawer_mode = IFNULL(cash_drawer_mode, 'driver')
        WHERE id = 1");

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
