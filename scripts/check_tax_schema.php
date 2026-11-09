<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
echo "--- shop_settings ---\n";
$cols1 = $db->query("DESCRIBE shop_settings")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols1 as $c) echo $c['Field'] . " | " . $c['Type'] . "\n";

echo "\n--- sales ---\n";
$cols2 = $db->query("DESCRIBE sales")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols2 as $c) echo $c['Field'] . " | " . $c['Type'] . "\n";
