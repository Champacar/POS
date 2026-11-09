<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    // Check existing columns
    $stmt = $db->query("SHOW COLUMNS FROM shop_settings");
    $existing = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing[] = $row['Field'];
    }

    if (!in_array('qr_payload', $existing)) {
        $db->exec("ALTER TABLE shop_settings ADD COLUMN qr_payload TEXT NULL AFTER bank_account_name");
        echo "Added column: qr_payload\n";
    }

    if (!in_array('qr_mode', $existing)) {
        $db->exec("ALTER TABLE shop_settings ADD COLUMN qr_mode VARCHAR(20) DEFAULT 'auto' AFTER qr_payload");
        echo "Added column: qr_mode\n";
    }

    if (!in_array('qr_uri_scheme', $existing)) {
        $db->exec("ALTER TABLE shop_settings ADD COLUMN qr_uri_scheme TINYINT(1) DEFAULT 0 AFTER qr_mode");
        echo "Added column: qr_uri_scheme\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
