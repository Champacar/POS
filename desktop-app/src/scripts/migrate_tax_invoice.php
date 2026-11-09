<?php
/**
 * Database Migration for Advanced Financial Statements & Tax Invoice
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    echo "Starting Tax Invoice migration...\n";

    // 1. Update shop_settings
    $colsShop = $db->query("SHOW COLUMNS FROM shop_settings")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('tax_id', $colsShop, true)) {
        $db->exec("ALTER TABLE shop_settings ADD COLUMN tax_id VARCHAR(50) NULL AFTER email");
        echo "✓ Added 'tax_id' to shop_settings.\n";
    }
    if (!in_array('company_name', $colsShop, true)) {
        $db->exec("ALTER TABLE shop_settings ADD COLUMN company_name VARCHAR(255) NULL AFTER shop_name_en");
        echo "✓ Added 'company_name' to shop_settings.\n";
    }
    if (!in_array('tax_invoice_prefix', $colsShop, true)) {
        $db->exec("ALTER TABLE shop_settings ADD COLUMN tax_invoice_prefix VARCHAR(20) DEFAULT 'TAX-' AFTER tax_type");
        echo "✓ Added 'tax_invoice_prefix' to shop_settings.\n";
    }

    // 2. Update sales
    $colsSales = $db->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('tax_invoice_number', $colsSales, true)) {
        $db->exec("ALTER TABLE sales ADD COLUMN tax_invoice_number VARCHAR(50) NULL AFTER invoice_number");
        echo "✓ Added 'tax_invoice_number' to sales.\n";
    }
    if (!in_array('customer_tax_id', $colsSales, true)) {
        $db->exec("ALTER TABLE sales ADD COLUMN customer_tax_id VARCHAR(50) NULL AFTER customer_name");
        echo "✓ Added 'customer_tax_id' to sales.\n";
    }
    if (!in_array('customer_company', $colsSales, true)) {
        $db->exec("ALTER TABLE sales ADD COLUMN customer_company VARCHAR(255) NULL AFTER customer_name");
        echo "✓ Added 'customer_company' to sales.\n";
    }
    if (!in_array('customer_address', $colsSales, true)) {
        $db->exec("ALTER TABLE sales ADD COLUMN customer_address TEXT NULL AFTER customer_tax_id");
        echo "✓ Added 'customer_address' to sales.\n";
    }
    if (!in_array('is_tax_invoice', $colsSales, true)) {
        $db->exec("ALTER TABLE sales ADD COLUMN is_tax_invoice TINYINT(1) DEFAULT 0 AFTER payment_status");
        echo "✓ Added 'is_tax_invoice' to sales.\n";
    }

    // Seed default shop tax settings if empty
    $curr = $db->query("SELECT tax_id, company_name FROM shop_settings LIMIT 1")->fetch();
    if (empty($curr['tax_id'])) {
        $db->exec("UPDATE shop_settings SET tax_id = '01-00294821', company_name = 'ບໍລິສັດ ໂພສຟາມາ ຢາດີ ຈຳກັດຜູ້ດຽວ' WHERE id = 1");
        echo "✓ Seeded default tax_id and company_name for pharmacy.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
