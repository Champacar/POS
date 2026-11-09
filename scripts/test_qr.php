<?php
require_once __DIR__ . '/../includes/lao_qr_helper.php';

$res = getDynamicLaoQrSvg(50000, 'LAK', 'INV-001', 200);
echo "Payload: " . $res['payload'] . PHP_EOL;
echo "Bank: " . $res['bank_name'] . PHP_EOL;
echo "Account: " . $res['account_number'] . PHP_EOL;
echo "Account Name: " . $res['account_name'] . PHP_EOL;
