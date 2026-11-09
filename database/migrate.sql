-- ===================================================
-- Migration: Add missing columns, tables, indexes
-- Run this on an EXISTING pospharma database
-- ===================================================

-- Add missing shop_settings columns
ALTER TABLE `shop_settings`
  ADD COLUMN IF NOT EXISTS `open_time` VARCHAR(10) DEFAULT '08:00' AFTER `email`,
  ADD COLUMN IF NOT EXISTS `close_time` VARCHAR(10) DEFAULT '20:00' AFTER `open_time`,
  ADD COLUMN IF NOT EXISTS `show_in_receipt` TINYINT(1) NOT NULL DEFAULT 1 AFTER `close_time`,
  ADD COLUMN IF NOT EXISTS `receipt_size` VARCHAR(10) NOT NULL DEFAULT '80mm' AFTER `show_in_receipt`,
  ADD COLUMN IF NOT EXISTS `tax_type` ENUM('included','excluded','none') NOT NULL DEFAULT 'excluded' AFTER `tax_rate`,
  ADD COLUMN IF NOT EXISTS `favicon` VARCHAR(255) DEFAULT NULL AFTER `logo`,
  ADD COLUMN IF NOT EXISTS `footer_image` VARCHAR(255) DEFAULT NULL AFTER `favicon`,
  ADD COLUMN IF NOT EXISTS `qr_code` VARCHAR(255) DEFAULT NULL AFTER `footer_image`,
  ADD COLUMN IF NOT EXISTS `cfd_media` VARCHAR(255) DEFAULT NULL AFTER `qr_code`;

-- Add split payment method
ALTER TABLE `sales` MODIFY COLUMN `payment_method` ENUM('cash','transfer','credit','split') NOT NULL DEFAULT 'cash';

-- Add cash_amount and transfer_amount if missing
ALTER TABLE `sales`
  ADD COLUMN IF NOT EXISTS `cash_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `note`,
  ADD COLUMN IF NOT EXISTS `transfer_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `cash_amount`,
  ADD COLUMN IF NOT EXISTS `synced` TINYINT(1) NOT NULL DEFAULT 0 AFTER `created_at`;

-- Add uuid and synced to expenses if missing
ALTER TABLE `expenses`
  ADD COLUMN IF NOT EXISTS `uuid` VARCHAR(50) DEFAULT NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `synced` TINYINT(1) NOT NULL DEFAULT 0 AFTER `uuid`;

-- Create units table
CREATE TABLE IF NOT EXISTS `units` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `units` (`name`) VALUES
('ແຜງ'), ('ກ່ອງ'), ('ຂວດ'), ('ມ້ວນ'), ('ແຄັບຊູນ'), ('ຖົງ'), ('ລັງ'), ('ອັນ'), ('ຊອງ'), ('ໜ່ວຍ');

-- Performance indexes
CREATE INDEX IF NOT EXISTS `idx_sales_status_date` ON `sales` (`payment_status`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_expenses_date` ON `expenses` (`expense_date`);
CREATE INDEX IF NOT EXISTS `idx_products_active_category` ON `products` (`is_active`, `category_id`);
CREATE INDEX IF NOT EXISTS `idx_products_barcode` ON `products` (`barcode`);

-- Create suppliers table
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

-- Multi-Barcode Support
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

-- Barcode Label Design Templates
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

-- Barcode Printer Configurations
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

-- Seed default barcode templates
INSERT IGNORE INTO `barcode_templates` (`id`, `name`, `width_mm`, `height_mm`, `columns`, `show_name`, `show_code`, `show_barcode`, `show_barcode_text`, `show_price`, `show_shop_name`, `show_unit`, `font_size_name`, `font_size_price`, `barcode_height`, `orientation`, `is_default`) VALUES
(1, 'ສະຕິກເກີ 40x30mm ມາດຕະຖານ (1 ຖັນ - Grozziie TP878)', 40.00, 30.00, 1, 1, 0, 1, 1, 1, 0, 1, '8.5pt', '9pt', 32, 'portrait', 1),
(2, 'ສະຕິກເກີ 50x30mm (1 ຖັນ)', 50.00, 30.00, 1, 1, 0, 1, 1, 1, 0, 1, '9pt', '9.5pt', 34, 'portrait', 0),
(3, 'ສະຕິກເກີ 40x30mm (2 ຖັນ - ແຖວຄູ່)', 40.00, 30.00, 2, 1, 0, 1, 1, 1, 0, 1, '8pt', '8.5pt', 30, 'portrait', 0),
(4, 'ສະຕິກເກີ 32x25mm (3 ຖັນ - ເຈ້ຍ A4 / ມ້ວນ 3 ດວງ)', 32.00, 25.00, 3, 1, 0, 1, 1, 1, 0, 0, '7.5pt', '8pt', 26, 'portrait', 0);

-- Seed default barcode printer config
INSERT IGNORE INTO `barcode_printer_configs` (`id`, `printer_name`, `printer_type`, `command_language`, `dpi`, `density`, `speed`, `offset_x_mm`, `offset_y_mm`, `is_default`) VALUES
(1, 'Grozziie TP878 (USB)', 'thermal_label', 'driver_gdi', 203, 8, 4, 0.00, 0.00, 1);

-- Alter stock_history to add supplier_id
ALTER TABLE `stock_history` ADD COLUMN IF NOT EXISTS `supplier_id` INT(11) DEFAULT NULL AFTER `user_id`;
ALTER TABLE `stock_history` ADD CONSTRAINT `fk_stockhist_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL;
