<?php
require_once __DIR__ . '/../includes/lao_qr_helper.php';

$res = getDynamicLaoQrSvg(50000, 'LAK', 'INV-001', 200);
$payload = $res['payload'];
$parsed = parseEmvcoQr($payload);

echo "Payload: " . $payload . PHP_EOL;
echo "CRC Valid? " . ($parsed['valid_crc'] ? "YES (100% Valid EMVCo Checksum)" : "NO") . PHP_EOL;
echo "Tags: " . implode(', ', array_keys($parsed['tags'])) . PHP_EOL;
