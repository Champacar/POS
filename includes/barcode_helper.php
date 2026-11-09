<?php
/**
 * POSPharma - Barcode Standards & Printing Helper
 * 
 * Standards Supported:
 * - CODE 128 (Default for in-store barcodes)
 * - EAN-13 (Retail items, 13 digits with Modulo 10 checksum)
 * - EAN-8 (Small items, 8 digits with Modulo 10 checksum)
 * - UPC-A (12 digits with Modulo 10 checksum)
 * - QR Code (Rich product details / batches)
 */

if (!defined('APP_ENV')) {
    require_once __DIR__ . '/../config/database.php';
}

/**
 * Calculate GS1 standard Modulo 10 Check Digit (for EAN-13, EAN-8, UPC-A)
 * 
 * @param string $digits Numbers without check digit
 * @return int 0-9
 */
function calculateGs1CheckDigit(string $digits): int {
    $digits = preg_replace('/\D/', '', $digits);
    $len = strlen($digits);
    if ($len === 0) return 0;

    $sum = 0;
    // Iterate from right to left
    $multiplier = 3;
    for ($i = $len - 1; $i >= 0; $i--) {
        $sum += ((int)$digits[$i]) * $multiplier;
        $multiplier = ($multiplier === 3) ? 1 : 3;
    }

    $mod = $sum % 10;
    return ($mod === 0) ? 0 : (10 - $mod);
}

/**
 * Validate EAN-13 (13 numeric digits with valid check digit)
 */
function validateEan13(string $barcode): bool {
    $b = trim($barcode);
    if (!preg_match('/^[0-9]{13}$/', $b)) {
        return false;
    }
    $payload = substr($b, 0, 12);
    $expectedCheck = calculateGs1CheckDigit($payload);
    return ((int)$b[12] === $expectedCheck);
}

/**
 * Validate EAN-8 (8 numeric digits with valid check digit)
 */
function validateEan8(string $barcode): bool {
    $b = trim($barcode);
    if (!preg_match('/^[0-9]{8}$/', $b)) {
        return false;
    }
    $payload = substr($b, 0, 7);
    $expectedCheck = calculateGs1CheckDigit($payload);
    return ((int)$b[7] === $expectedCheck);
}

/**
 * Validate UPC-A (12 numeric digits with valid check digit)
 */
function validateUpcA(string $barcode): bool {
    $b = trim($barcode);
    if (!preg_match('/^[0-9]{12}$/', $b)) {
        return false;
    }
    $payload = substr($b, 0, 11);
    $expectedCheck = calculateGs1CheckDigit($payload);
    return ((int)$b[11] === $expectedCheck);
}

/**
 * Validate CODE 128 (ASCII 32 to 126, length 1 to 64)
 */
function validateCode128(string $barcode): bool {
    $b = trim($barcode);
    if (strlen($b) < 1 || strlen($b) > 64) {
        return false;
    }
    return (bool)preg_match('/^[A-Za-z0-9\-_.\/:$#+=@ ]+$/', $b);
}

/**
 * Detect barcode format
 */
function detectBarcodeFormat(string $barcode): string {
    $b = trim($barcode);
    if (preg_match('/^[0-9]{13}$/', $b) && validateEan13($b)) {
        return 'EAN13';
    }
    if (preg_match('/^[0-9]{8}$/', $b) && validateEan8($b)) {
        return 'EAN8';
    }
    if (preg_match('/^[0-9]{12}$/', $b) && validateUpcA($b)) {
        return 'UPCA';
    }
    if (str_starts_with($b, 'QR:') || str_starts_with($b, 'http://') || str_starts_with($b, 'https://') || strlen($b) > 64) {
        return 'QR';
    }
    return 'CODE128';
}

/**
 * Generate safe internal barcode adhering to standards:
 * - Default: CODE 128 (e.g. P000123)
 * - In-store EAN-13: Uses GS1 restricted internal prefix (20-29)
 */
function generateStoreBarcode(string $type = 'CODE128', ?int $productId = null): string {
    $type = strtoupper(trim($type));
    
    if ($type === 'EAN13') {
        // GS1 Restricted internal store range 200000000000x - 299999999999x
        $prefix = '20';
        $mid = str_pad((string)($productId ?: random_int(1000, 999999)), 10, '0', STR_PAD_LEFT);
        $payload = substr($prefix . $mid, 0, 12);
        $check = calculateGs1CheckDigit($payload);
        return $payload . $check;
    }

    if ($type === 'EAN8') {
        // GS1 Restricted internal EAN8 starts with 2
        $prefix = '2';
        $mid = str_pad((string)($productId ?: random_int(100, 9999)), 6, '0', STR_PAD_LEFT);
        $payload = substr($prefix . $mid, 0, 7);
        $check = calculateGs1CheckDigit($payload);
        return $payload . $check;
    }

    if ($type === 'UPCA') {
        // UPC-A internal store prefix 2 (02-04)
        $prefix = '02';
        $mid = str_pad((string)($productId ?: random_int(1000, 99999)), 9, '0', STR_PAD_LEFT);
        $payload = substr($prefix . $mid, 0, 11);
        $check = calculateGs1CheckDigit($payload);
        return $payload . $check;
    }

    // Default: CODE 128 (Recommended for internal retail barcodes)
    $num = $productId ? str_pad((string)$productId, 6, '0', STR_PAD_LEFT) : date('ymd') . random_int(100, 999);
    return 'P' . $num;
}

/**
 * Check if barcode already exists in products or product_barcodes
 */
function checkBarcodeExists(PDO $pdo, string $barcode, ?int $excludeBarcodeId = null): array {
    $b = trim($barcode);
    if ($b === '') {
        return ['exists' => false];
    }

    // 1. Check in product_barcodes
    $sql = "SELECT pb.id, pb.product_id, pb.barcode, pb.unit, p.name AS product_name 
            FROM product_barcodes pb 
            LEFT JOIN products p ON pb.product_id = p.id 
            WHERE pb.barcode = ?";
    $params = [$b];
    if ($excludeBarcodeId) {
        $sql .= " AND pb.id != ?";
        $params[] = $excludeBarcodeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $foundPb = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($foundPb) {
        return [
            'exists' => true,
            'source' => 'product_barcodes',
            'product_id' => (int)$foundPb['product_id'],
            'product_name' => $foundPb['product_name'] ?? '',
            'unit' => $foundPb['unit'] ?? ''
        ];
    }

    // 2. Check in products.barcode
    $sql2 = "SELECT id, name, unit FROM products WHERE barcode = ?";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([$b]);
    $foundProd = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($foundProd) {
        return [
            'exists' => true,
            'source' => 'products',
            'product_id' => (int)$foundProd['id'],
            'product_name' => $foundProd['name'],
            'unit' => $foundProd['unit'] ?? ''
        ];
    }

    return ['exists' => false];
}

/**
 * Generate TSPL Command String for Thermal Label Printers (Grozziie, TSC, Xprinter)
 */
function generateTsplCommand(array $item, array $template, array $config = []): string {
    $widthMm = (float)($template['width_mm'] ?? 40);
    $heightMm = (float)($template['height_mm'] ?? 30);
    $density = (int)($config['density'] ?? 8);
    $speed = (int)($config['speed'] ?? 4);
    $copies = (int)($item['qty'] ?? 1);

    $barcode = $item['barcode'] ?? '';
    $name = mb_substr($item['name'] ?? '', 0, 30, 'UTF-8');
    $price = number_format((float)($item['price'] ?? 0)) . ' KIP';

    $tspl = "SIZE {$widthMm} mm,{$heightMm} mm\n";
    $tspl .= "GAP 2 mm,0 mm\n";
    $tspl .= "DENSITY {$density}\n";
    $tspl .= "SPEED {$speed}\n";
    $tspl .= "DIRECTION 1\n";
    $tspl .= "CLS\n";
    
    // Title
    $tspl .= "TEXT 20,20,\"TSS24.BF2\",0,1,1,\"{$name}\"\n";
    
    // Barcode
    $type = ($template['barcode_type'] ?? 'CODE128') === 'EAN13' ? 'EAN13' : '128';
    $tspl .= "BARCODE 20,60,\"{$type}\",42,1,0,2,2,\"{$barcode}\"\n";
    
    // Price
    $tspl .= "TEXT 20,115,\"3\",0,1,1,\"{$price}\"\n";
    $tspl .= "PRINT {$copies}\n";

    return $tspl;
}

/**
 * Generate ZPL Command String for Zebra Printers
 */
function generateZplCommand(array $item, array $template, array $config = []): string {
    $copies = (int)($item['qty'] ?? 1);
    $barcode = $item['barcode'] ?? '';
    $name = $item['name'] ?? '';
    $price = number_format((float)($item['price'] ?? 0)) . ' KIP';

    $zpl = "^XA\n";
    $zpl .= "^PW320\n";
    $zpl .= "^LL240\n";
    $zpl .= "^FO20,20^A0N,25,25^FD{$name}^FS\n";
    $zpl .= "^FO20,55^BY2,2,40^BCN,40,Y,N,N^FD{$barcode}^FS\n";
    $zpl .= "^FO20,110^A0N,28,28^FD{$price}^FS\n";
    $zpl .= "^PQ{$copies}\n";
    $zpl .= "^XZ\n";

    return $zpl;
}

