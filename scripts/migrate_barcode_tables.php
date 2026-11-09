<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();

    // 1. Multi-Barcode Support
    $db->exec("CREATE TABLE IF NOT EXISTS `product_barcodes` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Table product_barcodes created or exists.\n";

    // 2. Barcode Label Design Templates
    $db->exec("CREATE TABLE IF NOT EXISTS `barcode_templates` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Table barcode_templates created or exists.\n";

    // Seed default barcode templates if table is empty
    $count = $db->query("SELECT COUNT(*) FROM barcode_templates")->fetchColumn();
    if ($count == 0) {
        $db->exec("INSERT INTO `barcode_templates` (`name`, `width_mm`, `height_mm`, `columns`, `show_name`, `show_code`, `show_barcode`, `show_barcode_text`, `show_price`, `show_shop_name`, `show_unit`, `font_size_name`, `font_size_price`, `barcode_height`, `orientation`, `is_default`) VALUES
        ('ສະຕິກເກີ 40x30mm ມາດຕະຖານ (1 ຖັນ - Grozziie TP878)', 40.00, 30.00, 1, 1, 0, 1, 1, 1, 0, 1, '8.5pt', '9pt', 32, 'portrait', 1),
        ('ສະຕິກເກີ 50x30mm (1 ຖັນ)', 50.00, 30.00, 1, 1, 0, 1, 1, 1, 0, 1, '9pt', '9.5pt', 34, 'portrait', 0),
        ('ສະຕິກເກີ 40x30mm (2 ຖັນ - ແຖວຄູ່)', 40.00, 30.00, 2, 1, 0, 1, 1, 1, 0, 1, '8pt', '8.5pt', 30, 'portrait', 0),
        ('ສະຕິກເກີ 32x25mm (3 ຖັນ - ເຈ້ຍ A4 / ມ້ວນ 3 ດວງ)', 32.00, 25.00, 3, 1, 0, 1, 1, 1, 0, 0, '7.5pt', '8pt', 26, 'portrait', 0)");
        echo "✓ Seeded 4 default barcode templates.\n";
    }

    // 3. Barcode Printer Configurations
    $db->exec("CREATE TABLE IF NOT EXISTS `barcode_printer_configs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Table barcode_printer_configs created or exists.\n";

    $cfgCount = $db->query("SELECT COUNT(*) FROM barcode_printer_configs")->fetchColumn();
    if ($cfgCount == 0) {
        $db->exec("INSERT INTO `barcode_printer_configs` (`printer_name`, `printer_type`, `command_language`, `dpi`, `density`, `speed`, `offset_x_mm`, `offset_y_mm`, `is_default`) VALUES
        ('Grozziie TP878 (USB)', 'thermal_label', 'driver_gdi', 203, 8, 4, 0.00, 0.00, 1)");
        echo "✓ Seeded default barcode printer config.\n";
    }

    echo "\nAll barcode tables and default data ready!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
