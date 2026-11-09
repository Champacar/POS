<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    // 1. Alter shop_settings to add dynamic QR columns if not exist
    $columns = $db->query("SHOW COLUMNS FROM shop_settings")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('enable_dynamic_qr', $columns)) {
        $db->exec("ALTER TABLE shop_settings ADD COLUMN enable_dynamic_qr TINYINT(1) DEFAULT 0 AFTER enable_multi_currency");
        echo "Added column enable_dynamic_qr\n";
    }

    if (!in_array('bank_name', $columns)) {
        $db->exec("ALTER TABLE shop_settings ADD COLUMN bank_name VARCHAR(100) DEFAULT 'BCEL One' AFTER enable_dynamic_qr");
        echo "Added column bank_name\n";
    }

    if (!in_array('bank_account_number', $columns)) {
        $db->exec("ALTER TABLE shop_settings ADD COLUMN bank_account_number VARCHAR(100) DEFAULT '' AFTER bank_name");
        echo "Added column bank_account_number\n";
    }

    if (!in_array('bank_account_name', $columns)) {
        $db->exec("ALTER TABLE shop_settings ADD COLUMN bank_account_name VARCHAR(150) DEFAULT '' AFTER bank_account_number");
        echo "Added column bank_account_name\n";
    }

    // Set default sample bank account if empty
    $currBank = $db->query("SELECT bank_account_number FROM shop_settings WHERE id = 1 LIMIT 1")->fetchColumn();
    if (empty($currBank)) {
        $db->exec("UPDATE shop_settings SET enable_dynamic_qr = 1, bank_name = 'BCEL One', bank_account_number = '010120000998877', bank_account_name = 'DEE PHARMA' WHERE id = 1");
        echo "Set default sample bank settings for Shop 1\n";
    }

    // 2. Create stock_audits table
    $db->exec("
        CREATE TABLE IF NOT EXISTS stock_audits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            audit_code VARCHAR(50) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            status ENUM('in_progress', 'completed', 'cancelled') DEFAULT 'in_progress',
            total_items INT DEFAULT 0,
            matched_items INT DEFAULT 0,
            discrepancy_items INT DEFAULT 0,
            total_cost_variance DECIMAL(15,2) DEFAULT 0,
            created_by INT,
            reconciled_by INT NULL,
            reconciled_at DATETIME NULL,
            note TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Checked/created table stock_audits\n";

    // 3. Create stock_audit_items table
    $db->exec("
        CREATE TABLE IF NOT EXISTS stock_audit_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            audit_id INT NOT NULL,
            product_id INT NOT NULL,
            barcode VARCHAR(100) DEFAULT '',
            product_name VARCHAR(255) NOT NULL,
            unit VARCHAR(50) DEFAULT 'ແຜງ',
            system_quantity INT DEFAULT 0,
            counted_quantity INT DEFAULT 0,
            variance INT DEFAULT 0,
            cost_price DECIMAL(12,2) DEFAULT 0,
            cost_variance DECIMAL(15,2) DEFAULT 0,
            scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_audit (audit_id),
            INDEX idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Checked/created table stock_audit_items\n";

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
