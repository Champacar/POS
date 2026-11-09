<?php
require_once __DIR__ . '/../includes/lao_qr_helper.php';

$str = "00020101021133730004BCEL0106ONEPAY7216mch6541c0373ede30314202403271909590513CLOSEWHENDONE53034185405100005803VTE6002LA625305368cc876b4-a4af-4886-81f1-3890453eb5560809Buy Pants6304";
$calculated = strtoupper(str_pad(dechex(crc16_ccitt($str)), 4, '0', STR_PAD_LEFT));
echo "Expected: 40FF" . PHP_EOL;
echo "Calculated: " . $calculated . PHP_EOL;
if ($calculated === '40FF') {
    echo "CRC MATCHES!" . PHP_EOL;
} else {
    echo "CRC MISMATCH!" . PHP_EOL;
}
