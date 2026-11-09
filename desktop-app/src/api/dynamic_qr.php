<?php
/**
 * Dynamic LAO QR API
 * ສົ່ງຄືນ Dynamic QR Code SVG ແລະ ຂໍ້ມູນບັນຊີທະນາຄານ
 * ຮອງຮັບການກວດສອບ Raw QR, ແປງ Static ເປັນ Dynamic, ແລະ ສ້າງ QR ມາດຕະຖານ BCEL One / LAPNet
 */
require_once __DIR__ . '/../includes/lao_qr_helper.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. Validate / Decode Action
if ($action === 'validate' || isset($_GET['validate'])) {
    $rawPayload = trim($_POST['payload'] ?? $_GET['payload'] ?? '');
    if (empty($rawPayload)) {
        echo json_encode(['success' => false, 'message' => 'ກະລຸນາປ້ອນຂໍ້ມູນ QR Payload']);
        exit;
    }

    $parsed = parseEmvcoQr($rawPayload);
    $tags = $parsed['tags'];

    // Detect details from parsed tags
    $merchantName = $tags['59'] ?? '-';
    $currencyCode = $tags['53'] ?? '418';
    $currency = ($currencyCode === '764' ? 'THB' : ($currencyCode === '840' ? 'USD' : 'LAK'));
    $pointOfInitiation = ($tags['01'] ?? '') === '12' ? 'Dynamic' : 'Static';

    // Bank or Scheme detection
    $detectedBank = 'Generic LAO QR';
    if (isset($tags['33']) && stripos($tags['33'], 'BCEL') !== false) {
        $detectedBank = 'BCEL One / OnePay';
    } elseif (isset($tags['26']) && stripos($tags['26'], 'BCEL') !== false) {
        $detectedBank = 'BCEL One';
    } elseif (isset($tags['29']) && stripos($tags['29'], 'lapnet') !== false) {
        $detectedBank = 'LAPNet Interoperable';
    }

    echo json_encode([
        'success' => true,
        'valid_crc' => $parsed['valid_crc'],
        'detected_bank' => $detectedBank,
        'merchant_name' => $merchantName,
        'currency' => $currency,
        'qr_type' => $pointOfInitiation,
        'tags_count' => count($tags),
        'has_prefix' => !empty($parsed['prefix']),
        'prefix' => $parsed['prefix'],
        'tags' => $tags
    ]);
    exit;
}

// 2. Standard or Test Generation Action
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : (isset($_POST['amount']) ? (float)$_POST['amount'] : 0);
$currency = isset($_GET['currency']) ? trim($_GET['currency']) : (isset($_POST['currency']) ? trim($_POST['currency']) : 'LAK');
$billNo = isset($_GET['bill_no']) ? trim($_GET['bill_no']) : (isset($_POST['bill_no']) ? trim($_POST['bill_no']) : '');
$size = isset($_GET['size']) ? (int)$_GET['size'] : 220;

if ($size <= 50 || $size > 800) {
    $size = 220;
}

// Support custom test payload override
$customPayload = trim($_POST['custom_payload'] ?? $_GET['custom_payload'] ?? '');
if (!empty($customPayload)) {
    $injected = injectAmountIntoEmvcoQr($customPayload, $amount, $currency, $billNo);
    if (!empty($injected)) {
        $svg = generateQrCodeSvg($injected, $size);
        echo json_encode([
            'success' => true,
            'payload' => $injected,
            'svg' => $svg,
            'data_uri' => 'data:image/svg+xml;base64,' . base64_encode($svg),
            'amount' => $amount,
            'currency' => $currency,
            'bill_no' => $billNo,
            'generation_method' => 'custom_injected'
        ]);
        exit;
    }
}

$result = getDynamicLaoQrSvg($amount, $currency, $billNo, $size);

if ($result['success']) {
    $dataUri = 'data:image/svg+xml;base64,' . base64_encode($result['svg']);
    $result['data_uri'] = $dataUri;
}

echo json_encode($result);
