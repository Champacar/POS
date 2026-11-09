<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM shop_settings WHERE id = 1");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
