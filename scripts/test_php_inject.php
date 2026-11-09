<?php
require_once __DIR__ . '/../includes/lao_qr_helper.php';

$sample_bcel = "00020101021233980004BCEL0118ONEPAYSUBSCRIPTION0216mch679ae2c0a187d0320SBDMQTGMVMSRUVQUZOLY0406RULE-5051083030620155204729953034185802LA624602102030-06-01050410000820TAITTJVQEKRJZZJUFMIR6304DABB";
$injected = injectAmountIntoEmvcoQr($sample_bcel, 35000, 'LAK', 'TEST-001');

echo "Injected: " . $injected . PHP_EOL;
$parsed = parseEmvcoQr($injected);
echo "CRC Valid? " . ($parsed['valid_crc'] ? "YES" : "NO") . PHP_EOL;
echo "Tag 54: " . ($parsed['tags']['54'] ?? 'MISSING') . PHP_EOL;
echo "Occurrences of 6304: " . substr_count($injected, '6304') . PHP_EOL;
