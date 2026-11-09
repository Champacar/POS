<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$stmt = $db->query('DESCRIBE shop_settings');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ')' . PHP_EOL;
}
