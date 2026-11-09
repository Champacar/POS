<?php
// Load .env file if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

/**
 * Database Configuration
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 * 
 * ຮອງຮັບ: cPanel, VPS, Cloud hosting
 * ການຕັ້ງຄ່າ: ໃຊ້ environment variables ຫຼື ແກ້ໄຂຄ່າ default ດ້ານລຸ່ມ
 */

// ==============================
// Environment Detection
// ==============================
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // 'development' or 'production'

// ==============================
// Error Display (auto by environment)
// ==============================
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// ==============================
// Database Configuration
// ==============================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'pospharma');
define('DB_USER', getenv('DB_USER') ?: 'root');
$rawDbPass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS');
if ($rawDbPass === 'NONE' || $rawDbPass === false) {
    $rawDbPass = '';
}
define('DB_PASS', $rawDbPass);
define('DB_CHARSET', 'utf8mb4');
define('DB_CONNECTION', $_ENV['DB_CONNECTION'] ?? $_SERVER['DB_CONNECTION'] ?? (getenv('DB_CONNECTION') ?: 'mysql'));
define('SQLITE_PATH', $_ENV['SQLITE_PATH'] ?? $_SERVER['SQLITE_PATH'] ?? (getenv('SQLITE_PATH') ?: __DIR__ . '/../database/pospharma.db'));

// ==============================
// Application Settings
// ==============================
define('BASE_URL', $_ENV['BASE_URL'] ?? $_SERVER['BASE_URL'] ?? (getenv('BASE_URL') ?: '/pospharma/'));
define('SYNC_MODE', $_ENV['SYNC_MODE'] ?? $_SERVER['SYNC_MODE'] ?? (getenv('SYNC_MODE') ?: 'standalone'));
define('SITE_NAME', 'ລະບົບຂາຍຢາດີ');
define('SITE_NAME_EN', 'POINT OF SALE');

// ==============================
// Security Settings
// ==============================
define('CSRF_TOKEN_NAME', 'csrf_token');
define('RATE_LIMIT_MAX_ATTEMPTS', 5);      // ຈຳນວນຄັ້ງສູງສຸດທີ່ login ຜິດ
define('RATE_LIMIT_LOCKOUT_MINUTES', 15);  // ລັອກ (ນາທີ) ຫຼັງ login ຜິດ
define('SESSION_LIFETIME', 28800);          // Session lifetime: 8 ຊົ່ວໂມງ (28800 ວິນາທີ)

// ==============================
// File Upload Settings
// ==============================
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_DIR', __DIR__ . '/../assets/images/');

// ==============================
// Backup Settings (auto-detect paths)
// ==============================
define('MYSQLDUMP_PATH', findExecutable('mysqldump'));
define('MYSQL_PATH', findExecutable('mysql'));

/**
 * Find MySQL executables across different hosting environments
 */
function findExecutable($name) {
    $possiblePaths = [
        // Bundled Portable MariaDB in Desktop App
        __DIR__ . '/../../bin/mysql/bin/' . $name . '.exe',
        __DIR__ . '/../desktop-app/bin/mysql/bin/' . $name . '.exe',
        // Linux hosting (cPanel, VPS, Cloud)
        '/usr/bin/' . $name,
        '/usr/local/bin/' . $name,
        '/usr/local/mysql/bin/' . $name,
        '/usr/sbin/' . $name,
        // XAMPP on Windows
        'c:\\xampp\\mysql\\bin\\' . $name . '.exe',
        'C:\\xampp\\mysql\\bin\\' . $name . '.exe',
        // WAMP on Windows
        'c:\\wamp\\bin\\mysql\\mysql5.7.36\\bin\\' . $name . '.exe',
        'c:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\' . $name . '.exe',
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Fallback: try using system PATH
    return $name;
}

/**
 * Get PDO database connection
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            if (defined('DB_CONNECTION') && DB_CONNECTION === 'sqlite') {
                $dbFile = SQLITE_PATH;
                $isNew = !file_exists($dbFile);
                
                $dir = dirname($dbFile);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                
                $pdo = new PDO("sqlite:" . $dbFile, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                
                $pdo->exec("PRAGMA foreign_keys = ON;");
                $pdo->exec("PRAGMA journal_mode = WAL;");
                
                // MySQL Compatibility functions for SQLite
                $pdo->sqliteCreateFunction('NOW', function() {
                    return date('Y-m-d H:i:s');
                });
                $pdo->sqliteCreateFunction('CURDATE', function() {
                    return date('Y-m-d');
                });
                $pdo->sqliteCreateFunction('CONCAT', function(...$args) {
                    return implode('', $args);
                });
                $pdo->sqliteCreateFunction('IFNULL', function($val, $default) {
                    return $val !== null ? $val : $default;
                });
                $pdo->sqliteCreateFunction('HOUR', function($dt) {
                    return $dt ? (int)date('G', strtotime($dt)) : 0;
                }, 1);
                
                if ($isNew) {
                    $schemaFile = __DIR__ . '/../database/pospharma_sqlite.sql';
                    if (file_exists($schemaFile)) {
                        $sql = file_get_contents($schemaFile);
                        $pdo->exec($sql);
                    }
                } else {
                    // Auto-migration for existing SQLite databases missing columns
                    $supCols = array_column($pdo->query("PRAGMA table_info(suppliers)")->fetchAll(), 'name');
                    if (!empty($supCols) && !in_array('note', $supCols, true)) {
                        $pdo->exec("ALTER TABLE suppliers ADD COLUMN note TEXT");
                    }
                    
                    $shopCols = array_column($pdo->query("PRAGMA table_info(shop_settings)")->fetchAll(), 'name');
                    if (!empty($shopCols)) {
                        $missingShopCols = [
                            'email' => "TEXT DEFAULT ''",
                            'footer_image' => "TEXT DEFAULT ''",
                            'qr_code' => "TEXT DEFAULT ''",
                            'cfd_media' => "TEXT DEFAULT ''",
                        ];
                        foreach ($missingShopCols as $col => $def) {
                            if (!in_array($col, $shopCols, true)) {
                                $pdo->exec("ALTER TABLE shop_settings ADD COLUMN {$col} {$def}");
                            }
                        }
                    }

                    $expCols = array_column($pdo->query("PRAGMA table_info(expenses)")->fetchAll(), 'name');
                    if (!empty($expCols)) {
                        if (!in_array('uuid', $expCols, true)) {
                            $pdo->exec("ALTER TABLE expenses ADD COLUMN uuid TEXT");
                        }
                        if (!in_array('synced', $expCols, true)) {
                            $pdo->exec("ALTER TABLE expenses ADD COLUMN synced INTEGER NOT NULL DEFAULT 0");
                        }
                    }

                    $saleCols = array_column($pdo->query("PRAGMA table_info(sales)")->fetchAll(), 'name');
                    if (!empty($saleCols) && !in_array('synced', $saleCols, true)) {
                        $pdo->exec("ALTER TABLE sales ADD COLUMN synced INTEGER NOT NULL DEFAULT 0");
                    }
                }
            } else {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $dbOptions = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                $dbSsl = $_ENV['DB_SSL'] ?? $_SERVER['DB_SSL'] ?? (getenv('DB_SSL') ?: 'false');
                if ($dbSsl === 'true' || $dbSsl === '1') {
                    $dbOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }

                $dbOptions[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $dbOptions);

                // Auto-seed if tables do not exist in MySQL (Zero manual import needed)
                try {
                    $hasUsers = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
                    if (!$hasUsers) {
                        $schemaFile = __DIR__ . '/../database/pospharma.sql';
                        if (file_exists($schemaFile)) {
                            $sql = file_get_contents($schemaFile);
                            $sql = preg_replace('/CREATE\s+DATABASE[^\n;]+;?/i', '', $sql);
                            $sql = preg_replace('/USE\s+[^\n;]+;?/i', '', $sql);
                            $pdo->exec($sql);
                        }
                    }
                } catch (Throwable $seedErr) {
                    error_log("MySQL Auto-seed note: " . $seedErr->getMessage());
                }

                // Auto-migration for MySQL missing columns
                try {
                    // 1. Auto-migrate users table (role ENUM & permissions)
                    try {
                        $userCols = array_column($pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(), 'Field');
                        if (!empty($userCols)) {
                            if (!in_array('permissions', $userCols, true)) {
                                $pdo->exec("ALTER TABLE `users` ADD COLUMN `permissions` TEXT NULL AFTER `role`");
                            }
                            $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','cashier','manager','developer','pharmacist') NOT NULL DEFAULT 'cashier'");
                        }
                    } catch (Throwable $e) {
                        error_log("Users migration note: " . $e->getMessage());
                    }

                    // 2. Auto-migrate shop_settings columns
                    $shopCols = array_column($pdo->query("SHOW COLUMNS FROM `shop_settings`")->fetchAll(), 'Field');
                    if (!empty($shopCols)) {
                        $missingShopCols = [
                            'email' => "VARCHAR(100) DEFAULT ''",
                            'company_name' => "VARCHAR(255) NULL",
                            'tax_id' => "VARCHAR(50) NULL",
                            'tax_invoice_prefix' => "VARCHAR(20) DEFAULT 'TAX-'",
                            'footer_image' => "VARCHAR(255) DEFAULT NULL",
                            'qr_code' => "VARCHAR(255) DEFAULT NULL",
                            'cfd_media' => "VARCHAR(255) DEFAULT NULL",
                            'enable_multi_currency' => "TINYINT(1) DEFAULT 1",
                            'exchange_rate_thb' => "DECIMAL(12,2) DEFAULT 680.00",
                            'exchange_rate_usd' => "DECIMAL(12,2) DEFAULT 22000.00",
                            'enable_cash_drawer' => "TINYINT(1) DEFAULT 1",
                            'cash_drawer_mode' => "VARCHAR(20) DEFAULT 'driver'",
                            'enable_dynamic_qr' => "TINYINT(1) DEFAULT 0",
                            'bank_name' => "VARCHAR(100) DEFAULT 'BCEL One'",
                            'bank_account_number' => "VARCHAR(50) DEFAULT ''",
                            'bank_account_name' => "VARCHAR(100) DEFAULT ''",
                            'qr_payload' => "TEXT NULL",
                            'qr_mode' => "VARCHAR(20) DEFAULT 'auto'",
                            'qr_uri_scheme' => "TINYINT(1) DEFAULT 0",
                        ];
                        foreach ($missingShopCols as $col => $def) {
                            if (!in_array($col, $shopCols, true)) {
                                $pdo->exec("ALTER TABLE `shop_settings` ADD COLUMN `{$col}` {$def}");
                            }
                        }
                    }

                    // 3. Auto-migrate sales columns
                    $saleCols = array_column($pdo->query("SHOW COLUMNS FROM `sales`")->fetchAll(), 'Field');
                    if (!empty($saleCols)) {
                        $missingSaleCols = [
                            'shift_id' => "INT DEFAULT NULL",
                            'synced' => "TINYINT(1) NOT NULL DEFAULT 0",
                            'cash_amount' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
                            'transfer_amount' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
                            'currency' => "VARCHAR(10) DEFAULT 'LAK'",
                            'exchange_rate' => "DECIMAL(12,2) DEFAULT 1.00",
                            'paid_amount_foreign' => "DECIMAL(12,2) DEFAULT 0.00",
                            'change_amount_foreign' => "DECIMAL(12,2) DEFAULT 0.00",
                            'change_amount' => "DECIMAL(15,2) DEFAULT 0.00",
                            'tax_invoice_number' => "VARCHAR(50) NULL",
                            'customer_tax_id' => "VARCHAR(50) NULL",
                            'customer_company' => "VARCHAR(255) NULL",
                            'customer_address' => "TEXT NULL",
                            'is_tax_invoice' => "TINYINT(1) DEFAULT 0",
                        ];
                        foreach ($missingSaleCols as $col => $def) {
                            if (!in_array($col, $saleCols, true)) {
                                $pdo->exec("ALTER TABLE `sales` ADD COLUMN `{$col}` {$def}");
                            }
                        }
                        try {
                            $pdo->exec("ALTER TABLE `sales` MODIFY COLUMN `payment_method` ENUM('cash','transfer','credit','split') NOT NULL DEFAULT 'cash'");
                        } catch (Throwable $e) {}
                    }

                    // 4. Auto-migrate sale_items cost_price
                    try {
                        $saleItemCols = array_column($pdo->query("SHOW COLUMNS FROM `sale_items`")->fetchAll(), 'Field');
                        if (!empty($saleItemCols) && !in_array('cost_price', $saleItemCols, true)) {
                            $pdo->exec("ALTER TABLE `sale_items` ADD COLUMN `cost_price` DECIMAL(15,2) DEFAULT 0.00 AFTER `unit_price`");
                        }
                    } catch (Throwable $e) {}

                    // 5. Auto-migrate products supplier_id
                    try {
                        $prodCols = array_column($pdo->query("SHOW COLUMNS FROM `products`")->fetchAll(), 'Field');
                        if (!empty($prodCols) && !in_array('supplier_id', $prodCols, true)) {
                            $pdo->exec("ALTER TABLE `products` ADD COLUMN `supplier_id` INT DEFAULT NULL");
                        }
                    } catch (Throwable $e) {}

                    // 6. Auto-migrate expenses columns
                    $expCols = array_column($pdo->query("SHOW COLUMNS FROM `expenses`")->fetchAll(), 'Field');
                    if (!empty($expCols)) {
                        if (!in_array('uuid', $expCols, true)) {
                            $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `uuid` VARCHAR(50) DEFAULT NULL AFTER `user_id`");
                        }
                        if (!in_array('synced', $expCols, true)) {
                            $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `synced` TINYINT(1) NOT NULL DEFAULT 0 AFTER `uuid`");
                        }
                    }

                    // 7. Auto-migrate stock_history columns
                    $stockHistCols = array_column($pdo->query("SHOW COLUMNS FROM `stock_history`")->fetchAll(), 'Field');
                    if (!empty($stockHistCols) && !in_array('supplier_id', $stockHistCols, true)) {
                        $pdo->exec("ALTER TABLE `stock_history` ADD COLUMN `supplier_id` INT(11) DEFAULT NULL AFTER `user_id`");
                    }

                    // 8. Auto-create migration tables if missing (shifts, stock_audits, product_barcodes, units, suppliers)
                    $hasShifts = $pdo->query("SHOW TABLES LIKE 'shifts'")->fetch();
                    $hasStockAudits = $pdo->query("SHOW TABLES LIKE 'stock_audits'")->fetch();
                    $hasBarcodes = $pdo->query("SHOW TABLES LIKE 'product_barcodes'")->fetch();

                    if (!$hasShifts || !$hasStockAudits || !$hasBarcodes) {
                        $migFile = __DIR__ . '/../database/migrate.sql';
                        if (file_exists($migFile)) {
                            $rawSql = file_get_contents($migFile);
                            $cleanSql = preg_replace('/--[^\n]*\n/', "\n", $rawSql);
                            $queries = array_filter(array_map('trim', explode(';', $cleanSql)));
                            foreach ($queries as $q) {
                                if (!empty($q)) {
                                    try {
                                        $pdo->exec($q);
                                    } catch (Throwable $t) {
                                        // Ignore already existing errors
                                    }
                                }
                            }
                        }
                    }
                } catch (Throwable $migrationErr) {
                    error_log("MySQL Auto-migration note: " . $migrationErr->getMessage());
                }
            }
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            
            $isApiOrCli = php_sapi_name() === 'cli' 
                || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
                || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false);
                
            if ($isApiOrCli) {
                http_response_code(500);
                $msg = APP_ENV === 'development' ? "ການເຊື່ອມຕໍ່ຖານຂໍ້ມູນລົ້ມເຫຼວ: " . $e->getMessage() : "ການເຊື່ອມຕໍ່ຖານຂໍ້ມູນລົ້ມເຫຼວ. ກະລຸນາຕິດຕໍ່ຜູ້ດູແລລະບົບ.";
                die(json_encode(['success' => false, 'message' => $msg]));
            } else {
                http_response_code(500);
                $errDetail = APP_ENV === 'development' ? htmlspecialchars($e->getMessage()) : 'ກະລຸນາກວດສອບການຕັ້ງຄ່າຖານຂໍ້ມູນ ຫຼື ຕິດຕໍ່ຜູ້ດູແລລະບົບ (IT Admin)';
                die("<!DOCTYPE html>
<html lang=\"lo\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>ຂໍ້ຜິດພາດການເຊື່ອມຕໍ່ - POS Pharmacy</title>
    <link rel=\"stylesheet\" href=\"assets/vendor/fonts/noto-sans-lao.css\">
    <link rel=\"stylesheet\" href=\"assets/vendor/fontawesome/css/all.min.css\">
    <style>
        body { font-family: 'Noto Sans Lao', sans-serif; background: #f4f6f8; color: #263238; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .error-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); max-width: 480px; width: 100%; padding: 40px 30px; text-align: center; }
        .error-icon { width: 70px; height: 70px; border-radius: 50%; background: #ffebee; color: #d32f2f; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 20px; }
        h1 { font-size: 1.4rem; color: #1b5e20; margin-bottom: 10px; }
        p { font-size: 0.95rem; color: #546e7a; line-height: 1.6; margin-bottom: 25px; }
        .error-box { background: #fafafa; border: 1px dashed #cfd8dc; border-radius: 8px; padding: 12px; font-size: 0.85rem; color: #78909c; word-break: break-all; margin-bottom: 25px; text-align: left; }
        .btn-retry { display: inline-flex; align-items: center; gap: 8px; background: #2e7d32; color: #fff; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; transition: background 0.2s; }
        .btn-retry:hover { background: #1b5e20; }
    </style>
</head>
<body>
    <div class=\"error-card\">
        <div class=\"error-icon\"><i class=\"fas fa-database\"></i></div>
        <h1>ການເຊື່ອມຕໍ່ຖານຂໍ້ມູນຂັດຂ້ອງ</h1>
        <p>ລະບົບບໍ່ສາມາດເຊື່ອມຕໍ່ກັບຖານຂໍ້ມູນໄດ້ໃນຂະນະນີ້</p>
        <div class=\"error-box\"><i class=\"fas fa-info-circle\"></i> {$errDetail}</div>
        <button class=\"btn-retry\" onclick=\"window.location.reload()\"><i class=\"fas fa-redo\"></i> ລອງໃໝ່ອີກຄັ້ງ</button>
    </div>
</body>
</html>");
            }
        }
    }
    return $pdo;
}
