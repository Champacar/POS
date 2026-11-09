<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
foreach (['product_batches', 'purchase_orders', 'purchase_order_items', 'products'] as $tbl) {
    echo "=== $tbl ===\n";
    foreach ($db->query("DESCRIBE $tbl") as $col) {
        echo "{$col['Field']} ({$col['Type']})\n";
    }
}
