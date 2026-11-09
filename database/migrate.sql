-- ===================================================
-- Comprehensive Migration: POS Pharma
-- Ensures all 21 tables and missing columns exist
-- ===================================================

-- 1. Users table updates (Role & Permissions)
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','cashier','manager','developer','pharmacist') NOT NULL DEFAULT 'cashier';
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `permissions` TEXT NULL AFTER `role`;

-- 2. Shifts table
CREATE TABLE IF NOT EXISTS `shifts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shift_number` VARCHAR(50) NOT NULL,
  `user_id` INT NOT NULL,
  `opened_at` DATETIME NOT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `starting_cash` DECIMAL(15,2) DEFAULT 0.00,
  `total_sales_amount` DECIMAL(15,2) DEFAULT 0.00,
  `total_cash_amount` DECIMAL(15,2) DEFAULT 0.00,
  `total_transfer_amount` DECIMAL(15,2) DEFAULT 0.00,
  `total_credit_amount` DECIMAL(15,2) DEFAULT 0.00,
  `total_bills` INT DEFAULT 0,
  `total_discount` DECIMAL(15,2) DEFAULT 0.00,
  `cash_counted_lak` DECIMAL(15,2) DEFAULT 0.00,
  `cash_counted_thb` DECIMAL(12,2) DEFAULT 0.00,
  `cash_counted_usd` DECIMAL(12,2) DEFAULT 0.00,
  `expected_cash_lak` DECIMAL(15,2) DEFAULT 0.00,
  `difference_lak` DECIMAL(15,2) DEFAULT 0.00,
  `status` ENUM('open', 'closed') DEFAULT 'open',
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_shifts_user` (`user_id`),
  INDEX `idx_shifts_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Stock Audits tables
CREATE TABLE IF NOT EXISTS `stock_audits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `audit_code` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `status` ENUM('in_progress', 'completed', 'cancelled') DEFAULT 'in_progress',
  `total_items` INT DEFAULT 0,
  `matched_items` INT DEFAULT 0,
  `discrepancy_items` INT DEFAULT 0,
  `total_cost_variance` DECIMAL(15,2) DEFAULT 0,
  `created_by` INT,
  `reconciled_by` INT NULL,
  `reconciled_at` DATETIME NULL,
  `note` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_audit_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `audit_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `barcode` VARCHAR(100) DEFAULT '',
  `product_name` VARCHAR(255) NOT NULL,
  `unit` VARCHAR(50) DEFAULT 'ແຜງ',
  `system_quantity` INT DEFAULT 0,
  `counted_quantity` INT DEFAULT 0,
  `variance` INT DEFAULT 0,
  `cost_price` DECIMAL(12,2) DEFAULT 0,
  `cost_variance` DECIMAL(15,2) DEFAULT 0,
  `scanned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_audit` (`audit_id`),
  INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Shop Settings columns
ALTER TABLE `shop_settings`
  ADD COLUMN IF NOT EXISTS `company_name` VARCHAR(255) NULL AFTER `shop_name_en`,
  ADD COLUMN IF NOT EXISTS `tax_id` VARCHAR(50) NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `open_time` VARCHAR(10) DEFAULT '08:00' AFTER `email`,
  ADD COLUMN IF NOT EXISTS `close_time` VARCHAR(10) DEFAULT '20:00' AFTER `open_time`,
  ADD COLUMN IF NOT EXISTS `show_in_receipt` TINYINT(1) NOT NULL DEFAULT 1 AFTER `close_time`,
  ADD COLUMN IF NOT EXISTS `receipt_size` VARCHAR(10) NOT NULL DEFAULT '80mm' AFTER `show_in_receipt`,
  ADD COLUMN IF NOT EXISTS `tax_type` ENUM('included','excluded','none') NOT NULL DEFAULT 'excluded' AFTER `tax_rate`,
  ADD COLUMN IF NOT EXISTS `tax_invoice_prefix` VARCHAR(20) DEFAULT 'TAX-' AFTER `tax_type`,
  ADD COLUMN IF NOT EXISTS `favicon` VARCHAR(255) DEFAULT NULL AFTER `logo`,
  ADD COLUMN IF NOT EXISTS `footer_image` VARCHAR(255) DEFAULT NULL AFTER `favicon`,
  ADD COLUMN IF NOT EXISTS `qr_code` VARCHAR(255) DEFAULT NULL AFTER `footer_image`,
  ADD COLUMN IF NOT EXISTS `cfd_media` VARCHAR(255) DEFAULT NULL AFTER `qr_code`,
  ADD COLUMN IF NOT EXISTS `enable_multi_currency` TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `exchange_rate_thb` DECIMAL(12,2) DEFAULT 680.00,
  ADD COLUMN IF NOT EXISTS `exchange_rate_usd` DECIMAL(12,2) DEFAULT 22000.00,
  ADD COLUMN IF NOT EXISTS `enable_cash_drawer` TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `cash_drawer_mode` VARCHAR(20) DEFAULT 'driver',
  ADD COLUMN IF NOT EXISTS `enable_dynamic_qr` TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `bank_name` VARCHAR(100) DEFAULT 'BCEL One',
  ADD COLUMN IF NOT EXISTS `bank_account_number` VARCHAR(100) DEFAULT '',
  ADD COLUMN IF NOT EXISTS `bank_account_name` VARCHAR(150) DEFAULT '',
  ADD COLUMN IF NOT EXISTS `qr_payload` TEXT NULL,
  ADD COLUMN IF NOT EXISTS `qr_mode` VARCHAR(20) DEFAULT 'auto',
  ADD COLUMN IF NOT EXISTS `qr_uri_scheme` TINYINT(1) DEFAULT 0;

-- 5. Sales columns
ALTER TABLE `sales` MODIFY COLUMN `payment_method` ENUM('cash','transfer','credit','split') NOT NULL DEFAULT 'cash';
ALTER TABLE `sales`
  ADD COLUMN IF NOT EXISTS `shift_id` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `cash_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `note`,
  ADD COLUMN IF NOT EXISTS `transfer_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `cash_amount`,
  ADD COLUMN IF NOT EXISTS `currency` VARCHAR(10) DEFAULT 'LAK',
  ADD COLUMN IF NOT EXISTS `exchange_rate` DECIMAL(12,2) DEFAULT 1.00,
  ADD COLUMN IF NOT EXISTS `paid_amount_foreign` DECIMAL(12,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `change_amount_foreign` DECIMAL(12,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `change_amount` DECIMAL(15,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `tax_invoice_number` VARCHAR(50) NULL AFTER `invoice_number`,
  ADD COLUMN IF NOT EXISTS `customer_tax_id` VARCHAR(50) NULL AFTER `customer_name`,
  ADD COLUMN IF NOT EXISTS `customer_company` VARCHAR(255) NULL AFTER `customer_name`,
  ADD COLUMN IF NOT EXISTS `customer_address` TEXT NULL AFTER `customer_tax_id`,
  ADD COLUMN IF NOT EXISTS `is_tax_invoice` TINYINT(1) DEFAULT 0 AFTER `payment_status`,
  ADD COLUMN IF NOT EXISTS `synced` TINYINT(1) NOT NULL DEFAULT 0 AFTER `created_at`;

-- 6. Sale Items cost_price
ALTER TABLE `sale_items` ADD COLUMN IF NOT EXISTS `cost_price` DECIMAL(15,2) DEFAULT 0.00 AFTER `unit_price`;

-- 7. Products supplier_id
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `supplier_id` INT DEFAULT NULL;

-- 8. Expenses columns
ALTER TABLE `expenses`
  ADD COLUMN IF NOT EXISTS `uuid` VARCHAR(50) DEFAULT NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `synced` TINYINT(1) NOT NULL DEFAULT 0 AFTER `uuid`;

-- 9. Units table
CREATE TABLE IF NOT EXISTS `units` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `units` (`name`) VALUES
('ແຜງ'), ('ກ່ອງ'), ('ຂວດ'), ('ມ້ວນ'), ('ແຄັບຊູນ'), ('ຖົງ'), ('ລັງ'), ('ອັນ'), ('ຊອງ'), ('ໜ່ວຍ');

-- 10. Suppliers table
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `contact_person` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Multi-Barcode Support
CREATE TABLE IF NOT EXISTS `product_barcodes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `barcode` VARCHAR(64) NOT NULL,
  `barcode_type` ENUM('CODE128', 'EAN13', 'EAN8', 'UPCA', 'QR') NOT NULL DEFAULT 'CODE128',
  `unit` VARCHAR(50) DEFAULT NULL,
  `multiplier` INT(11) NOT NULL DEFAULT 1,
  `price` DECIMAL(15,2) DEFAULT NULL,
  `variant_name` VARCHAR(100) DEFAULT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_barcode` (`barcode`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_is_default` (`is_default`),
  CONSTRAINT `fk_pbc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Barcode Label Templates
CREATE TABLE IF NOT EXISTS `barcode_templates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `width_mm` DECIMAL(6,2) NOT NULL DEFAULT 40.00,
  `height_mm` DECIMAL(6,2) NOT NULL DEFAULT 30.00,
  `columns` INT(11) NOT NULL DEFAULT 1,
  `show_name` TINYINT(1) NOT NULL DEFAULT 1,
  `show_code` TINYINT(1) NOT NULL DEFAULT 0,
  `show_barcode` TINYINT(1) NOT NULL DEFAULT 1,
  `show_barcode_text` TINYINT(1) NOT NULL DEFAULT 1,
  `show_price` TINYINT(1) NOT NULL DEFAULT 1,
  `show_shop_name` TINYINT(1) NOT NULL DEFAULT 0,
  `show_unit` TINYINT(1) NOT NULL DEFAULT 1,
  `show_variant` TINYINT(1) NOT NULL DEFAULT 0,
  `show_lot_expiry` TINYINT(1) NOT NULL DEFAULT 0,
  `font_size_name` VARCHAR(20) NOT NULL DEFAULT '8.5pt',
  `font_size_price` VARCHAR(20) NOT NULL DEFAULT '9pt',
  `font_size_meta` VARCHAR(20) NOT NULL DEFAULT '6.5pt',
  `barcode_height` INT(11) NOT NULL DEFAULT 32,
  `orientation` ENUM('portrait', 'landscape') NOT NULL DEFAULT 'portrait',
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Barcode Printer Configurations
CREATE TABLE IF NOT EXISTS `barcode_printer_configs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `printer_name` VARCHAR(150) NOT NULL,
  `printer_type` ENUM('thermal_label', 'thermal_receipt', 'desktop_label', 'a4_laser') NOT NULL DEFAULT 'thermal_label',
  `command_language` ENUM('driver_gdi', 'tspl', 'zpl', 'esc_pos') NOT NULL DEFAULT 'driver_gdi',
  `dpi` INT(11) NOT NULL DEFAULT 203,
  `density` INT(11) NOT NULL DEFAULT 8,
  `speed` INT(11) NOT NULL DEFAULT 4,
  `offset_x_mm` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `offset_y_mm` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `is_default` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `barcode_templates` (`id`, `name`, `width_mm`, `height_mm`, `columns`, `show_name`, `show_code`, `show_barcode`, `show_barcode_text`, `show_price`, `show_shop_name`, `show_unit`, `font_size_name`, `font_size_price`, `barcode_height`, `orientation`, `is_default`) VALUES
(1, 'ສະຕິກເກີ 40x30mm ມາດຕະຖານ (1 ຖັນ - Grozziie TP878)', 40.00, 30.00, 1, 1, 0, 1, 1, 1, 0, 1, '8.5pt', '9pt', 32, 'portrait', 1),
(2, 'ສະຕິກເກີ 50x30mm (1 ຖັນ)', 50.00, 30.00, 1, 1, 0, 1, 1, 1, 0, 1, '9pt', '9.5pt', 34, 'portrait', 0),
(3, 'ສະຕິກເກີ 40x30mm (2 ຖັນ - ແຖວຄູ່)', 40.00, 30.00, 2, 1, 0, 1, 1, 1, 0, 1, '8pt', '8.5pt', 30, 'portrait', 0),
(4, 'ສະຕິກເກີ 32x25mm (3 ຖັນ - ເຈ້ຍ A4 / ມ້ວນ 3 ດວງ)', 32.00, 25.00, 3, 1, 0, 1, 1, 1, 0, 0, '7.5pt', '8pt', 26, 'portrait', 0);

INSERT IGNORE INTO `barcode_printer_configs` (`id`, `printer_name`, `printer_type`, `command_language`, `dpi`, `density`, `speed`, `offset_x_mm`, `offset_y_mm`, `is_default`) VALUES
(1, 'Grozziie TP878 (USB)', 'thermal_label', 'driver_gdi', 203, 8, 4, 0.00, 0.00, 1);

-- 14. Stock History supplier_id
ALTER TABLE `stock_history` ADD COLUMN IF NOT EXISTS `supplier_id` INT(11) DEFAULT NULL AFTER `user_id`;

-- 15. Performance indexes
CREATE INDEX IF NOT EXISTS `idx_sales_status_date` ON `sales` (`payment_status`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_expenses_date` ON `expenses` (`expense_date`);
CREATE INDEX IF NOT EXISTS `idx_products_active_category` ON `products` (`is_active`, `category_id`);
CREATE INDEX IF NOT EXISTS `idx_products_barcode` ON `products` (`barcode`);
