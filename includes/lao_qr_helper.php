<?php
/**
 * Dynamic LAO QR & EMVCo Standard Payment Helper
 * ລະບົບສ້າງ QR Code ຮັບເງິນແບບ Dynamic ພ້ອມຝັງຍອດເງິນອັດຕະໂນມັດ 100% Offline
 * ມາດຕະຖານ Bank of the Lao PDR (BOL) / LAPNet / BCEL One / OnePay / EMVCo
 */

require_once __DIR__ . '/qrcode_helper.php';
require_once __DIR__ . '/../config/database.php';

if (!function_exists('crc16_ccitt')) {
    /**
     * Compute CRC16-CCITT checksum (Poly 0x1021, Init 0xFFFF)
     * Standard EMVCo CCITT-FALSE
     */
    function crc16_ccitt(string $str): int {
        $crc = 0xFFFF;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($str[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return $crc;
    }
}

if (!function_exists('emvco_tlv')) {
    /**
     * Format Tag-Length-Value
     */
    function emvco_tlv(string $tag, string $value): string {
        $tagNum = is_numeric($tag) ? sprintf('%02d', (int)$tag) : $tag;
        return sprintf('%s%02d%s', $tagNum, strlen($value), $value);
    }
}

if (!function_exists('parseEmvcoQr')) {
    /**
     * Parse any EMVCo QR code string into associative tags
     *
     * @param string $payload
     * @return array [ 'prefix' => string, 'tags' => array, 'valid_crc' => bool ]
     */
    function parseEmvcoQr(string $payload): array {
        $payload = trim($payload);
        $prefix = '';
        $idx = strpos($payload, '000201');
        if ($idx !== false) {
            $prefix = substr($payload, 0, $idx);
            $payload = substr($payload, $idx);
        }

        $tags = [];
        $i = 0;
        $len = strlen($payload);

        while ($i < $len) {
            if ($i + 4 > $len) break;
            $tag = substr($payload, $i, 2);
            $tagLenStr = substr($payload, $i + 2, 2);
            if (!ctype_digit($tagLenStr)) break;
            $tagLen = (int)$tagLenStr;
            $val = substr($payload, $i + 4, $tagLen);
            $tags[$tag] = $val;
            $i += 4 + $tagLen;
            if ($tag === '63') break;
        }

        // Validate CRC if present
        $validCrc = false;
        if (isset($tags['63']) && strlen($tags['63']) === 4) {
            $crcIdx = strrpos($payload, '6304');
            if ($crcIdx !== false) {
                $checkStr = substr($payload, 0, $crcIdx + 4);
                $expected = strtoupper(substr($payload, $crcIdx + 4, 4));
                $calc = strtoupper(str_pad(dechex(crc16_ccitt($checkStr)), 4, '0', STR_PAD_LEFT));
                $validCrc = ($calc === $expected);
            }
        }

        return [
            'prefix' => $prefix,
            'tags' => $tags,
            'valid_crc' => $validCrc
        ];
    }
}

if (!function_exists('injectAmountIntoEmvcoQr')) {
    /**
     * Takes an authentic static QR code string (from BCEL One, LDB, etc.)
     * and dynamically injects the transaction amount, currency, and bill number,
     * recalculating the exact CRC16 checksum.
     *
     * @param string $basePayload Raw QR string from bank (e.g. 000201... or onepay://qr/...)
     * @param float $amount Transaction amount
     * @param string $currency Currency code: LAK (418), THB (764), USD (840)
     * @param string $billNo Invoice / reference number
     * @param bool $useUriScheme Whether to preserve onepay://qr/ prefix
     * @return string
     */
    function injectAmountIntoEmvcoQr(string $basePayload, float $amount, string $currency = 'LAK', string $billNo = '', bool $useUriScheme = false): string {
        $parsed = parseEmvcoQr($basePayload);
        $tags = $parsed['tags'];
        $prefix = $parsed['prefix'];

        if (empty($tags)) {
            // If parsing failed, fallback to standard generator
            return '';
        }

        // Tag 01: Set to 12 (Dynamic with preset amount)
        $tags['01'] = ($amount > 0 ? '12' : '11');

        // Tag 53: Transaction Currency
        $currUpper = strtoupper(trim($currency));
        $currCode = '418'; // LAK
        if ($currUpper === 'THB') $currCode = '764';
        elseif ($currUpper === 'USD') $currCode = '840';
        $tags['53'] = $currCode;

        // Tag 54: Transaction Amount
        if ($amount > 0) {
            $amountStr = ($currCode === '418') ? sprintf('%.0f', $amount) : sprintf('%.2f', $amount);
            $tags['54'] = $amountStr;
        } else {
            unset($tags['54']);
        }

        // Tag 62: Additional Data Field (Invoice / Reference)
        if (!empty($billNo)) {
            $cleanBill = preg_replace('/[^A-Za-z0-9\-_]/', '', trim($billNo));
            $tags['62'] = emvco_tlv('01', substr($cleanBill, 0, 25));
        }

        // Remove existing Tag 63 before reassembling
        unset($tags['63'], $tags[63]);

        // Reassemble in ascending numeric tag order (excluding Tag 63)
        $tagKeys = array_keys($tags);
        usort($tagKeys, function($a, $b) {
            return (int)$a - (int)$b;
        });

        $assembled = '';
        foreach ($tagKeys as $t) {
            if ((int)$t === 63) continue;
            $tagStr = sprintf('%02d', (int)$t);
            $assembled .= emvco_tlv($tagStr, $tags[$t]);
        }

        // Calculate Tag 63 CRC
        $toCrc = $assembled . '6304';
        $crc = crc16_ccitt($toCrc);
        $crcHex = strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
        $rawPayload = $toCrc . $crcHex;

        if ($useUriScheme) {
            return 'onepay://qr/' . $rawPayload;
        } elseif (!empty($prefix) && str_starts_with($prefix, 'onepay://qr/')) {
            return $prefix . $rawPayload;
        }

        return $rawPayload;
    }
}

if (!function_exists('generateLaoQrPayload')) {
    /**
     * Generate standard Dynamic LAO QR Payload string
     *
     * @param string $account Bank account or phone number
     * @param float $amount Payment amount
     * @param string $currency Currency code: LAK (418), THB (764), USD (840)
     * @param string $merchantName Store name / Account name
     * @param string $billNo Invoice or bill reference number
     * @param string $bankName BCEL One, LDB, JDB, etc.
     * @param string $mode 'auto', 'bcel', 'lapnet'
     * @param bool $useUriScheme Prepend onepay://qr/
     * @return string
     */
    function generateLaoQrPayload(
        string $account,
        float $amount,
        string $currency = 'LAK',
        string $merchantName = 'DEE PHARMA',
        string $billNo = '',
        string $bankName = 'BCEL One',
        string $mode = 'auto',
        bool $useUriScheme = false
    ): string {
        $cleanAcc = preg_replace('/[^0-9]/', '', trim($account));
        if (empty($cleanAcc)) {
            throw new InvalidArgumentException('ກະລຸນາລະບຸເລກບັນຊີທະນາຄານໃນໜ້າຕັ້ງຄ່າຮ້ານກ່ອນ (Bank account number required)');
        }

        // Format international mobile if phone number
        $isPhone = (str_starts_with($cleanAcc, '020') || str_starts_with($cleanAcc, '20') || (strlen($cleanAcc) >= 8 && strlen($cleanAcc) <= 11));
        if (str_starts_with($cleanAcc, '020')) {
            $intlPhone = '0085620' . substr($cleanAcc, 3);
        } elseif (str_starts_with($cleanAcc, '20')) {
            $intlPhone = '0085620' . substr($cleanAcc, 2);
        } else {
            $intlPhone = $cleanAcc;
        }

        $payload = '';

        // Tag 00: Payload Format Indicator (01)
        $payload .= emvco_tlv('00', '01');

        // Tag 01: Point of Initiation Method (12 = Dynamic with Amount, 11 = Static)
        $payload .= emvco_tlv('01', ($amount > 0 ? '12' : '11'));

        $isBcel = (stripos($bankName, 'BCEL') !== false || $mode === 'bcel' || $mode === 'auto');

        if ($isBcel) {
            // Tag 26: Domestic Scheme Template (LA.BCEL)
            $sub26 = emvco_tlv('00', 'LA.BCEL') . emvco_tlv('01', $cleanAcc);
            $payload .= emvco_tlv('26', $sub26);

            // Tag 33: BCEL OnePay Merchant & Account Template
            $sub33 = emvco_tlv('00', 'BCEL') . emvco_tlv('01', 'ONEPAY') . emvco_tlv('02', $cleanAcc);
            $payload .= emvco_tlv('33', $sub33);
        }

        // Tag 29: LAPNet National Interoperability Template (Works with all Lao banks: BCEL, LDB, JDB, APB...)
        $sub29 = emvco_tlv('00', 'com.lapnet') . emvco_tlv('01', ($isPhone ? $intlPhone : $cleanAcc));
        $payload .= emvco_tlv('29', $sub29);

        // Tag 52: Merchant Category Code (5912 = Drug Stores and Pharmacies)
        $payload .= emvco_tlv('52', '5912');

        // Tag 53: Transaction Currency (418 = LAK, 764 = THB, 840 = USD)
        $currUpper = strtoupper(trim($currency));
        $currCode = '418';
        if ($currUpper === 'THB') $currCode = '764';
        elseif ($currUpper === 'USD') $currCode = '840';
        $payload .= emvco_tlv('53', $currCode);

        // Tag 54: Transaction Amount
        if ($amount > 0) {
            $amountStr = ($currCode === '418') ? sprintf('%.0f', $amount) : sprintf('%.2f', $amount);
            $payload .= emvco_tlv('54', $amountStr);
        }

        // Tag 58: Country Code (LA)
        $payload .= emvco_tlv('58', 'LA');

        // Tag 59: Merchant Name (alphanumeric, max 25 chars)
        $cleanName = preg_replace('/[^A-Za-z0-9 ]/', '', $merchantName);
        if (empty($cleanName)) $cleanName = 'PHARMACY';
        $payload .= emvco_tlv('59', substr(trim($cleanName), 0, 25));

        // Tag 60: Merchant City (VIENTIANE)
        $payload .= emvco_tlv('60', 'VIENTIANE');

        // Tag 62: Additional Data Field Template (Bill/Reference Number)
        if (!empty($billNo)) {
            $cleanBill = preg_replace('/[^A-Za-z0-9\-_]/', '', trim($billNo));
            $subRef = emvco_tlv('01', substr($cleanBill, 0, 25));
            $payload .= emvco_tlv('62', $subRef);
        }

        // Tag 63: Checksum header (Length 04)
        $payloadToCrc = $payload . '6304';
        $crc = crc16_ccitt($payloadToCrc);
        $crcHex = strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
        $rawPayload = $payloadToCrc . $crcHex;

        if ($useUriScheme) {
            return 'onepay://qr/' . $rawPayload;
        }

        return $rawPayload;
    }
}

if (!function_exists('getDynamicLaoQrSvg')) {
    /**
     * Generate dynamic LAO QR SVG markup directly for any amount and currency
     */
    function getDynamicLaoQrSvg(float $amount, string $currency = 'LAK', string $billNo = '', int $size = 220): array {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT shop_name, shop_name_en, enable_dynamic_qr, bank_name, bank_account_number, bank_account_name, qr_code, qr_payload, qr_mode, qr_uri_scheme FROM shop_settings WHERE id = 1 LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $account = !empty($settings['bank_account_number']) ? trim($settings['bank_account_number']) : '';
            $merchant = !empty($settings['bank_account_name']) ? $settings['bank_account_name'] : (!empty($settings['shop_name_en']) ? $settings['shop_name_en'] : (!empty($settings['shop_name']) ? $settings['shop_name'] : 'PHARMACY'));
            $bank = !empty($settings['bank_name']) ? $settings['bank_name'] : 'BCEL One';
            $qrPayload = !empty($settings['qr_payload']) ? trim($settings['qr_payload']) : '';
            $qrMode = !empty($settings['qr_mode']) ? $settings['qr_mode'] : 'auto';
            $useUriScheme = !empty($settings['qr_uri_scheme']);

            if (empty($account) && empty($qrPayload)) {
                return [
                    'success' => false,
                    'error' => 'ກະລຸນາຕັ້ງຄ່າເລກບັນຊີທະນາຄານໃນໜ້າຕັ້ງຄ່າຮ້ານກ່ອນ',
                    'payload' => '',
                    'svg' => ''
                ];
            }

            $payload = '';
            $generationMethod = 'generated';

            // Method 1: If the shop uploaded/pasted an authentic BCEL One / LAPNet QR code string,
            // dynamically inject the amount and bill number into their real QR!
            if (!empty($qrPayload)) {
                $injected = injectAmountIntoEmvcoQr($qrPayload, $amount, $currency, $billNo, $useUriScheme);
                if (!empty($injected)) {
                    $payload = $injected;
                    $generationMethod = 'injected_from_static';
                }
            }

            // Method 2: If no static payload or injection failed, generate compliant multi-tag QR
            if (empty($payload)) {
                $payload = generateLaoQrPayload($account, $amount, $currency, $merchant, $billNo, $bank, $qrMode, $useUriScheme);
                $generationMethod = 'generated_multi_scheme';
            }

            $svg = generateQrCodeSvg($payload, $size);

            return [
                'success' => true,
                'payload' => $payload,
                'svg' => $svg,
                'bank_name' => $bank,
                'account_number' => $account,
                'account_name' => $merchant,
                'amount' => $amount,
                'currency' => $currency,
                'bill_no' => $billNo,
                'generation_method' => $generationMethod,
                'is_uri_scheme' => str_starts_with($payload, 'onepay://qr/')
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
