<?php
/**
 * Database Backup Import API
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // Admin only
setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'backup.php');
    exit;
}

// Verify CSRF token
if (!verifyCSRFToken()) {
    $_SESSION['backup_message'] = "CSRF token ບໍ່ຖືກຕ້ອງ. ກະລຸນາ refresh ໜ້າ.";
    $_SESSION['backup_message_type'] = "error";
    header('Location: ' . BASE_URL . 'backup.php');
    exit;
}

if (defined('DB_CONNECTION') && DB_CONNECTION === 'sqlite') {
    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['backup_message'] = "ການອັບໂຫຼດໄຟລ໌ລົ້ມເຫຼວ.";
        $_SESSION['backup_message_type'] = "error";
        header('Location: ' . BASE_URL . 'backup.php');
        exit;
    }

    $uploadedFile = $_FILES['backup_file']['tmp_name'];
    $fileExt = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));

    if ($fileExt !== 'db' && $fileExt !== 'sqlite') {
        $_SESSION['backup_message'] = "ກະລຸນາອັບໂຫຼດໄຟລ໌ປະເພດ .db ຫຼື .sqlite ເທົ່ານັ້ນ.";
        $_SESSION['backup_message_type'] = "error";
        header('Location: ' . BASE_URL . 'backup.php');
        exit;
    }

    $dbFile = SQLITE_PATH;
    if (copy($uploadedFile, $dbFile)) {
        $_SESSION['backup_message'] = "ກູ້ຄືນຂໍ້ມູນສຳເລັດແລ້ວ!";
        $_SESSION['backup_message_type'] = "success";
    } else {
        $_SESSION['backup_message'] = "ເກີດຂໍ້ຜິດພາດໃນການກູ້ຄືນ.";
        $_SESSION['backup_message_type'] = "error";
    }
    
    header('Location: ' . BASE_URL . 'backup.php');
    exit;
}

$mysqlPath = MYSQL_PATH;

if (!file_exists($mysqlPath) && $mysqlPath !== 'mysql') {
    $_SESSION['backup_message'] = "ບໍ່ພົບໄຟລ໌ mysql ໃນເຄື່ອງເຊີເວີ. ກະລຸນາກວດສອບການຕິດຕັ້ງ.";
    $_SESSION['backup_message_type'] = "error";
    header('Location: ' . BASE_URL . 'backup.php');
    exit;
}

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['backup_message'] = "ການອັບໂຫຼດໄຟລ໌ລົ້ມເຫຼວ.";
    $_SESSION['backup_message_type'] = "error";
    header('Location: ' . BASE_URL . 'backup.php');
    exit;
}

// Validate file size (max 50MB for backup files)
if ($_FILES['backup_file']['size'] > 50 * 1024 * 1024) {
    $_SESSION['backup_message'] = "ໄຟລ໌ໃຫຍ່ເກີນໄປ (ສູງສຸດ 50MB).";
    $_SESSION['backup_message_type'] = "error";
    header('Location: ' . BASE_URL . 'backup.php');
    exit;
}

$uploadedFile = $_FILES['backup_file']['tmp_name'];
$fileExt = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));

if ($fileExt !== 'sql') {
    $_SESSION['backup_message'] = "ກະລຸນາອັບໂຫຼດໄຟລ໌ປະເພດ .sql ເທົ່ານັ້ນ.";
    $_SESSION['backup_message_type'] = "error";
    header('Location: ' . BASE_URL . 'backup.php');
    exit;
}

$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;
$dbName = DB_NAME;

// Construct the mysql import command
$command = sprintf(
    '"%s" --host="%s" --user="%s" --password="%s" "%s" < "%s"',
    $mysqlPath,
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    escapeshellarg($dbPass),
    escapeshellarg($dbName),
    $uploadedFile
);

// Execute the command
exec($command . ' 2>&1', $output, $resultCode);

if ($resultCode === 0) {
    $_SESSION['backup_message'] = "ກູ້ຄືນຂໍ້ມູນສຳເລັດແລ້ວ!";
    $_SESSION['backup_message_type'] = "success";
} else {
    error_log("Backup import failed: " . implode(" ", $output));
    $_SESSION['backup_message'] = "ເກີດຂໍ້ຜິດພາດໃນການກູ້ຄືນ. ກະລຸນາກວດສອບໄຟລ໌ SQL.";
    $_SESSION['backup_message_type'] = "error";
}

header('Location: ' . BASE_URL . 'backup.php');
exit;
