-- SQLite Schema for Stand-alone Offline Mode
-- ລະບົບຂາຍຢາດີ - POINT OF SALE

CREATE TABLE IF NOT EXISTS `shop_settings` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `shop_name` TEXT NOT NULL,
  `shop_name_en` TEXT DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `phone` TEXT DEFAULT NULL,
  `email` TEXT DEFAULT '',
  `tax_rate` REAL NOT NULL DEFAULT 0.00,
  `tax_type` TEXT NOT NULL DEFAULT 'excluded',
  `currency` TEXT NOT NULL DEFAULT 'ກີບ',
  `logo` TEXT DEFAULT NULL,
  `favicon` TEXT DEFAULT NULL,
  `footer_image` TEXT DEFAULT '',
  `qr_code` TEXT DEFAULT '',
  `cfd_media` TEXT DEFAULT '',
  `receipt_size` TEXT NOT NULL DEFAULT '80mm',
  `show_in_receipt` INTEGER NOT NULL DEFAULT 1,
  `open_time` TEXT DEFAULT '08:00',
  `close_time` TEXT DEFAULT '20:00',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO `shop_settings` (`shop_name`, `shop_name_en`, `address`, `phone`, `tax_rate`, `currency`) VALUES
('ຮ້ານຂາຍຢາດີ', 'Good Pharmacy', 'ນະຄອນຫຼວງວຽງຈັນ', '', 10.00, 'ກີບ');

CREATE TABLE IF NOT EXISTS `users` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `username` TEXT NOT NULL UNIQUE,
  `password` TEXT NOT NULL,
  `full_name` TEXT NOT NULL,
  `role` TEXT NOT NULL DEFAULT 'cashier',
  `phone` TEXT,
  `email` TEXT,
  `avatar` TEXT DEFAULT NULL,
  `is_active` INTEGER NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin user (password: password)
INSERT INTO `users` (`username`, `password`, `full_name`, `role`, `phone`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ຜູ້ດູແລລະບົບ', 'admin', '020 99999999');

-- Developer user (password: dev@pospharma2026)
INSERT INTO `users` (`username`, `password`, `full_name`, `role`, `phone`) VALUES
('developer', '$2y$10$WNkkVl.7lJfSBojJ5kNhWeirfH3rZceQkXm5FngH.RC2401qUYJPq', 'ນັກພັດທະນາ', 'developer', '020 99998888');

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` TEXT NOT NULL,
  `description` TEXT,
  `icon` TEXT DEFAULT 'fa-pills',
  `color` TEXT DEFAULT '#4caf50',
  `is_active` INTEGER NOT NULL DEFAULT 1,
  `sort_order` INTEGER DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO `categories` (`name`, `description`, `icon`, `color`, `sort_order`) VALUES
('ຢາແກ້ປວດ', 'Pain relievers', 'fa-tablets', '#e53935', 1),
('ຢາແກ້ໄຂ້', 'Fever medicines', 'fa-thermometer', '#fb8c00', 2),
('ຢາແກ້ໄອ', 'Cough medicines', 'fa-lungs', '#7b1fa2', 3),
('ຢາຕ້ານເຊື້ອ', 'Antibiotics', 'fa-capsules', '#1565c0', 4),
('ວິຕາມິນ', 'Vitamins & Supplements', 'fa-leaf', '#2e7d32', 5),
('ອຸປະກອນການແພດ', 'Medical supplies', 'fa-kit-medical', '#00838f', 6),
('ເຄື່ອງສຳອາງ', 'Cosmetics', 'fa-pump-soap', '#ad1457', 7),
('ອື່ນໆ', 'Others', 'fa-box', '#546e7a', 8);

CREATE TABLE IF NOT EXISTS `units` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO `units` (`name`) VALUES
('ແຜງ'), ('ກ່ອງ'), ('ຂວດ'), ('ມ້ວນ'), ('ແຄັບຊູນ'), ('ຖົງ'), ('ລັງ'), ('ອັນ'), ('ຊອງ'), ('ໜ່ວຍ');

CREATE TABLE IF NOT EXISTS `products` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `barcode` TEXT DEFAULT NULL,
  `name` TEXT NOT NULL,
  `description` TEXT,
  `category_id` INTEGER DEFAULT NULL,
  `cost_price` REAL NOT NULL DEFAULT 0.00,
  `selling_price` REAL NOT NULL DEFAULT 0.00,
  `stock_quantity` INTEGER NOT NULL DEFAULT 0,
  `min_stock` INTEGER NOT NULL DEFAULT 5,
  `unit` TEXT DEFAULT 'ແຜງ',
  `image` TEXT DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `is_active` INTEGER NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
);

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

CREATE TABLE IF NOT EXISTS `sales` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `invoice_number` TEXT NOT NULL UNIQUE,
  `user_id` INTEGER DEFAULT NULL,
  `customer_name` TEXT DEFAULT NULL,
  `subtotal` REAL NOT NULL DEFAULT 0.00,
  `discount_amount` REAL NOT NULL DEFAULT 0.00,
  `tax_amount` REAL NOT NULL DEFAULT 0.00,
  `total_amount` REAL NOT NULL DEFAULT 0.00,
  `payment_method` TEXT NOT NULL DEFAULT 'cash',
  `payment_status` TEXT NOT NULL DEFAULT 'paid',
  `cash_amount` REAL NOT NULL DEFAULT 0.00,
  `transfer_amount` REAL NOT NULL DEFAULT 0.00,
  `note` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `synced` INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `sale_id` INTEGER NOT NULL,
  `product_id` INTEGER DEFAULT NULL,
  `product_name` TEXT NOT NULL,
  `quantity` INTEGER NOT NULL DEFAULT 1,
  `unit_price` REAL NOT NULL,
  `total_price` REAL NOT NULL,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` TEXT NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO `expense_categories` (`name`) VALUES
('ຄ່າເຊົ່າ'), ('ຄ່ານ້ຳຄ່າໄຟ'), ('ເງິນເດືອນ'), ('ຊື້ຢາເຂົ້າ'), ('ຄ່າຂົນສົ່ງ'), ('ອື່ນໆ');

CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `category_id` INTEGER DEFAULT NULL,
  `title` TEXT NOT NULL,
  `amount` REAL NOT NULL DEFAULT 0.00,
  `description` TEXT,
  `expense_date` DATE NOT NULL,
  `user_id` INTEGER DEFAULT NULL,
  `uuid` TEXT UNIQUE,
  `synced` INTEGER NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `title` TEXT NOT NULL,
  `message` TEXT,
  `type` TEXT NOT NULL DEFAULT 'info',
  `is_read` INTEGER NOT NULL DEFAULT 0,
  `link` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` TEXT NOT NULL,
  `contact_name` TEXT DEFAULT NULL,
  `phone` TEXT DEFAULT NULL,
  `email` TEXT DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `is_active` INTEGER NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `product_batches` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `product_id` INTEGER NOT NULL,
  `batch_number` TEXT NOT NULL,
  `cost_price` REAL NOT NULL DEFAULT 0.00,
  `selling_price` REAL NOT NULL DEFAULT 0.00,
  `initial_quantity` INTEGER NOT NULL DEFAULT 0,
  `quantity` INTEGER NOT NULL DEFAULT 0,
  `expiry_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `po_number` TEXT NOT NULL UNIQUE,
  `supplier_id` INTEGER DEFAULT NULL,
  `user_id` INTEGER DEFAULT NULL,
  `total_amount` REAL NOT NULL DEFAULT 0.00,
  `status` TEXT NOT NULL DEFAULT 'pending',
  `note` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `po_id` INTEGER NOT NULL,
  `product_id` INTEGER DEFAULT NULL,
  `product_name` TEXT NOT NULL,
  `quantity` INTEGER NOT NULL DEFAULT 1,
  `unit_price` REAL NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `stock_history` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `product_id` INTEGER NOT NULL,
  `type` TEXT NOT NULL,
  `quantity` INTEGER NOT NULL,
  `reference` TEXT DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `user_id` INTEGER DEFAULT NULL,
  `supplier_id` INTEGER DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
);
