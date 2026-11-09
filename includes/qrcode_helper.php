<?php
/**
 * POSPharma - Offline QR Code Helper
 * Generates crisp SVG and Data URI QR codes 100% offline.
 */

spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'BaconQrCode\\')) {
        $file = __DIR__ . '/qrcode/bacon-qr-code/src/' . str_replace('\\', '/', substr($class, 12)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    } elseif (str_starts_with($class, 'DASPRiD\\Enum\\')) {
        $file = __DIR__ . '/qrcode/dasprid-enum/src/' . str_replace('\\', '/', substr($class, 13)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Generate standalone SVG markup for a QR code
 *
 * @param string $data Data to encode (e.g. invoice number)
 * @param int $size Width/height in pixels
 * @param int $margin Margin around code (in modules, default 1)
 * @return string Clean inline SVG markup
 */
function generateQrCodeSvg(string $data, int $size = 90, int $margin = 1): string {
    if (empty($data)) {
        return '';
    }
    try {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($data);
        
        // Remove XML declaration for clean HTML5 inline embedding
        $svg = preg_replace('/<\?xml[^>]*\?>\s*/i', '', $svg);
        return trim($svg);
    } catch (\Throwable $e) {
        error_log('QR Code Generation Error: ' . $e->getMessage());
        return '';
    }
}

/**
 * Generate a Data URI (data:image/svg+xml;base64,...) for use in <img src="...">
 *
 * @param string $data Data to encode
 * @param int $size Width/height in pixels
 * @param int $margin Margin
 * @return string Data URI string
 */
function generateQrCodeDataUri(string $data, int $size = 90, int $margin = 1): string {
    $svg = generateQrCodeSvg($data, $size, $margin);
    if (empty($svg)) {
        return '';
    }
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
