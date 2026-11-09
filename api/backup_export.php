<?php
/**
 * Database Backup Export API
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // Admin only
setSecurityHeaders();

if (defined('DB_CONNECTION') && DB_CONNECTION === 'sqlite') {
    $dbFile = SQLITE_PATH;
    if (!file_exists($dbFile)) {
        die("ຖານຂໍ້ມູນ SQLite ບໍ່ມີຢູ່.");
    }
    
    $filename = 'backup_pospharma_' . date('Y-m-d_H-i-s') . '.db';
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($dbFile));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($dbFile);
    exit;
}

$mysqldumpPath = MYSQLDUMP_PATH;

if (!file_exists($mysqldumpPath) && $mysqldumpPath !== 'mysqldump') {
    die("ບໍ່ພົບໄຟລ໌ mysqldump ໃນເຄື່ອງເຊີເວີ. ກະລຸນາກວດສອບການຕິດຕັ້ງ.");
}

$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;
$dbName = DB_NAME;

$filename = 'backup_pospharma_' . date('Y-m-d_H-i-s') . '.sql';

// Set headers to force download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Execute mysqldump and stream output directly to PHP stdout
$command = sprintf(
    '"%s" --host="%s" --user="%s" --password="%s" "%s"',
    $mysqldumpPath,
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    escapeshellarg($dbPass),
    escapeshellarg($dbName)
);

// Pass through the command output
passthru($command, $resultCode);

if ($resultCode !== 0) {
    error_log("Backup export failed with code: $resultCode");
}
exit;
