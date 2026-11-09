<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    // Check if cost_price exists in sale_items
    $cols = $db->query("SHOW COLUMNS FROM sale_items")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('cost_price', $cols)) {
        $db->exec("ALTER TABLE sale_items ADD COLUMN cost_price DECIMAL(15,2) DEFAULT 0 AFTER unit_price");
        echo "Added column cost_price to sale_items\n";
    }

    // Backfill cost_price for existing sale_items from products.cost_price
    $stmt = $db->exec("
        UPDATE sale_items si 
        JOIN products p ON si.product_id = p.id 
        SET si.cost_price = p.cost_price 
        WHERE si.cost_price = 0 OR si.cost_price IS NULL
    ");
    echo "Backfilled cost_price for {$stmt} sale items from products catalog\n";

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
