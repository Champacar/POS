<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    echo "Checking product supplier link...\n";

    // Add supplier_id to products if not exists
    try {
        $db->exec("ALTER TABLE products ADD COLUMN supplier_id INT DEFAULT NULL");
        $db->exec("ALTER TABLE products ADD INDEX idx_products_supplier (supplier_id)");
        echo "Added products.supplier_id column.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "products.supplier_id already exists.\n";
        } else {
            echo "Notice on products.supplier_id: " . $e->getMessage() . "\n";
        }
    }

    // Populate supplier_id from the latest stock_history if available
    $db->exec("
        UPDATE products p
        JOIN (
            SELECT product_id, supplier_id
            FROM stock_history
            WHERE supplier_id IS NOT NULL AND supplier_id > 0
            ORDER BY id DESC
        ) sh ON p.id = sh.product_id
        SET p.supplier_id = sh.supplier_id
        WHERE p.supplier_id IS NULL
    ");

    // If still null, assign to first active supplier if any exists
    $firstSupplierId = $db->query("SELECT id FROM suppliers WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($firstSupplierId) {
        $db->exec("UPDATE products SET supplier_id = $firstSupplierId WHERE supplier_id IS NULL");
        echo "Assigned default supplier (ID: $firstSupplierId) to products without supplier.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
