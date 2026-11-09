<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    echo "Running database migration for Shift Management...\n";

    // 1. Create shifts table
    $sqlShifts = "CREATE TABLE IF NOT EXISTS shifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shift_number VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        opened_at DATETIME NOT NULL,
        closed_at DATETIME DEFAULT NULL,
        starting_cash DECIMAL(15,2) DEFAULT 0.00,
        total_sales_amount DECIMAL(15,2) DEFAULT 0.00,
        total_cash_amount DECIMAL(15,2) DEFAULT 0.00,
        total_transfer_amount DECIMAL(15,2) DEFAULT 0.00,
        total_credit_amount DECIMAL(15,2) DEFAULT 0.00,
        total_bills INT DEFAULT 0,
        total_discount DECIMAL(15,2) DEFAULT 0.00,
        cash_counted_lak DECIMAL(15,2) DEFAULT 0.00,
        cash_counted_thb DECIMAL(12,2) DEFAULT 0.00,
        cash_counted_usd DECIMAL(12,2) DEFAULT 0.00,
        expected_cash_lak DECIMAL(15,2) DEFAULT 0.00,
        difference_lak DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('open', 'closed') DEFAULT 'open',
        note TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_shifts_user (user_id),
        INDEX idx_shifts_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sqlShifts);
    echo "Created table 'shifts' (or already exists).\n";

    // 2. Add shift_id to sales table if not exists
    try {
        $db->exec("ALTER TABLE sales ADD COLUMN shift_id INT DEFAULT NULL");
        $db->exec("ALTER TABLE sales ADD INDEX idx_sales_shift (shift_id)");
        echo "Added sales.shift_id column.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "sales.shift_id already exists.\n";
        } else {
            echo "Notice on sales.shift_id: " . $e->getMessage() . "\n";
        }
    }

    echo "Migration for shifts completed successfully!\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
