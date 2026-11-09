<?php
/**
 * Run Schema Migration
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once __DIR__ . '/../includes/functions.php';

if (php_sapi_name() !== 'cli' && ($_SESSION['role'] ?? '') !== 'developer') {
    http_response_code(403);
    exit('Forbidden');
}

try {
    $db = getDB();
    $sql = file_get_contents(__DIR__ . '/update_schema.sql');
    
    // Split queries by semicolon
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($queries as $q) {
        if (!empty($q)) {
            $db->exec($q);
        }
    }
    
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
