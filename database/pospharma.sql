-- ===================================================
-- POS Pharmacy Database Schema
-- ລະບົບຂາຍຢາດີ - POINT OF SALE
-- ===================================================

CREATE DATABASE IF NOT EXISTS `pospharma` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pospharma`;

-- ---------------------------------------------------
-- Shop Settings
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `shop_name` VARCHAR(255) NOT NULL DEFAULT 'ຮ້ານຂາຍຢາດີ',
  `shop_name_en` VARCHAR(255) DEFAULT 'Good Pharmacy',
  `address` TEXT,
  `phone` VARCHAR(50),
  `email` VARCHAR(100),
  `open_time` VARCHAR(10) DEFAULT '08:00',
  `close_time` VARCHAR(10) DEFAULT '20:00',
  `show_in_receipt` TINYINT(1) NOT NULL DEFAULT 1,
  `receipt_size` VARCHAR(10) NOT NULL DEFAULT '80mm',
  `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  `tax_type` ENUM('included','excluded','none') NOT NULL DEFAULT 'excluded',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'ກີບ',
  `logo` VARCHAR(255) DEFAULT NULL,
  `favicon` VARCHAR(255) DEFAULT NULL,
  `footer_image` VARCHAR(255) DEFAULT NULL,
  `qr_code` VARCHAR(255) DEFAULT NULL,
  `cfd_media` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `shop_settings` (`shop_name`, `shop_name_en`, `address`, `phone`, `tax_rate`, `currency`) VALUES
('ຮ້ານຂາຍຢາດີ', 'Good Pharmacy', 'ນະຄອນຫຼວງວຽງຈັນ', '', 10.00, 'ກີບ');

-- ---------------------------------------------------
-- Users / Staff
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin','cashier','manager') NOT NULL DEFAULT 'cashier',
  `phone` VARCHAR(50),
  `email` VARCHAR(100),
  `avatar` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`username`, `password`, `full_name`, `role`, `phone`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ຜູ້ດູແລລະບົບ', 'admin', '020 99999999');

-- ---------------------------------------------------
-- Categories
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `icon` VARCHAR(50) DEFAULT 'fa-pills',
  `color` VARCHAR(20) DEFAULT '#4caf50',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`name`, `description`, `icon`, `color`, `sort_order`) VALUES
('ຢາແກ້ປວດ', 'Pain relievers', 'fa-tablets', '#e53935', 1),
('ຢາແກ້ໄຂ້', 'Fever medicines', 'fa-thermometer', '#fb8c00', 2),
('ຢາແກ້ໄອ', 'Cough medicines', 'fa-lungs', '#7b1fa2', 3),
('ຢາຕ້ານເຊື້ອ', 'Antibiotics', 'fa-capsules', '#1565c0', 4),
('ວິຕາມິນ', 'Vitamins & Supplements', 'fa-leaf', '#2e7d32', 5),
('ອຸປະກອນການແພດ', 'Medical supplies', 'fa-kit-medical', '#00838f', 6),
('ເຄື່ອງສຳອາງ', 'Cosmetics', 'fa-pump-soap', '#ad1457', 7),
('ອື່ນໆ', 'Others', 'fa-box', '#546e7a', 8);

-- ---------------------------------------------------
-- Units
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `units` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `units` (`name`) VALUES
('ແຜງ'), ('ກ່ອງ'), ('ຂວດ'), ('ມ້ວນ'), ('ແຄັບຊູນ'), ('ຖົງ'), ('ລັງ'), ('ອັນ'), ('ຊອງ'), ('ໜ່ວຍ');

-- ---------------------------------------------------
-- Products
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `barcode` VARCHAR(50) DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `category_id` INT(11) DEFAULT NULL,
  `cost_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` INT(11) NOT NULL DEFAULT 0,
  `min_stock` INT(11) NOT NULL DEFAULT 5,
  `unit` VARCHAR(50) DEFAULT 'ແຜງ',
  `image` VARCHAR(255) DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_product_category` (`category_id`),
  KEY `idx_products_active_category` (`is_active`, `category_id`),
  KEY `idx_products_barcode` (`barcode`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `products` (`barcode`, `name`, `category_id`, `cost_price`, `selling_price`, `stock_quantity`, `min_stock`, `unit`) VALUES
('8850000000001', 'ພາຣາເຊຕາໂມນ 500mg', 1, 15000, 20000, 100, 10, 'ແຜງ'),
('8850000000002', 'ໄອບູໂພຣເຟັນ 400mg', 1, 20000, 30000, 80, 10, 'ແຜງ'),
('8850000000003', 'ອາໂມຊີຊິລິນ 500mg', 4, 25000, 40000, 50, 5, 'ແຜງ'),
('8850000000004', 'ວິຕາມິນ C 1000mg', 5, 30000, 45000, 60, 10, 'ກ່ອງ'),
('8850000000005', 'ຢາແກ້ໄອ Dextromethorphan', 3, 18000, 25000, 40, 5, 'ຂວດ'),
('8850000000006', 'ຢາຢອດຕາ', 6, 12000, 18000, 30, 5, 'ຂວດ'),
('8850000000007', 'ແອສໄພຣິນ 300mg', 1, 10000, 15000, 90, 10, 'ແຜງ'),
('8850000000008', 'ຢາແກ້ແພ້ Cetirizine', 3, 15000, 22000, 45, 5, 'ແຜງ'),
('8850000000009', 'ວິຕາມິນ B Complex', 5, 35000, 50000, 55, 10, 'ກ່ອງ'),
('8850000000010', 'ຜ້າກ໋ອສ ມ້ວນໃຫຍ່', 6, 8000, 12000, 70, 15, 'ມ້ວນ');

-- ---------------------------------------------------
-- Sales
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(50) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `customer_name` VARCHAR(100) DEFAULT NULL,
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash','transfer','credit','split') NOT NULL DEFAULT 'cash',
  `payment_status` ENUM('paid','pending','cancelled') NOT NULL DEFAULT 'paid',
  `cash_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `transfer_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `note` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `synced` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `fk_sale_user` (`user_id`),
  KEY `idx_sales_status_date` (`payment_status`, `created_at`),
  CONSTRAINT `fk_sale_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Sale Items
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sale_id` INT(11) NOT NULL,
  `product_id` INT(11) DEFAULT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(15,2) NOT NULL,
  `total_price` DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_saleitem_sale` (`sale_id`),
  KEY `fk_saleitem_product` (`product_id`),
  CONSTRAINT `fk_saleitem_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_saleitem_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Expense Categories
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `expense_categories` (`name`) VALUES
('ຄ່າເຊົ່າ'),
('ຄ່ານ້ຳຄ່າໄຟ'),
('ເງິນເດືອນ'),
('ຊື້ຢາເຂົ້າ'),
('ຄ່າຂົນສົ່ງ'),
('ອື່ນໆ');

-- ---------------------------------------------------
-- Expenses
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `description` TEXT,
  `expense_date` DATE NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `uuid` VARCHAR(50) DEFAULT NULL,
  `synced` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_expense_category` (`category_id`),
  KEY `fk_expense_user` (`user_id`),
  KEY `idx_expenses_date` (`expense_date`),
  CONSTRAINT `fk_expense_category` FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expense_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Notifications
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT,
  `type` ENUM('info','warning','danger','success') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `link` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Suppliers
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `contact_name` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Product Batches
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_batches` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `batch_number` VARCHAR(50) NOT NULL,
  `cost_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `initial_quantity` INT(11) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `expiry_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_batch_product` (`product_id`),
  CONSTRAINT `fk_batch_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Purchase Orders
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `po_number` VARCHAR(50) NOT NULL UNIQUE,
  `supplier_id` INT(11) DEFAULT NULL,
  `status` ENUM('draft','sent','received','cancelled') NOT NULL DEFAULT 'draft',
  `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `note` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Purchase Order Items
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `po_id` INT(11) NOT NULL,
  `product_id` INT(11) DEFAULT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_po_item_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Stock History (for inventory tracking)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `type` ENUM('in','out','adjust') NOT NULL,
  `quantity` INT(11) NOT NULL,
  `reference` VARCHAR(100) DEFAULT NULL,
  `note` TEXT,
  `user_id` INT(11) DEFAULT NULL,
  `supplier_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_stockhist_product` (`product_id`),
  KEY `fk_stockhist_user` (`user_id`),
  KEY `fk_stockhist_supplier` (`supplier_id`),
  CONSTRAINT `fk_stockhist_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stockhist_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stockhist_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
