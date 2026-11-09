<?php
/**
 * POS Cash Drawer Kick Endpoint
 * ລະບົບສັ່ງເປີດລິ້ນຊັກເງິນ (ESC/POS & Driver Trigger)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

$isTest = isset($_GET['test']) && $_GET['test'] == '1';
$mode = $_GET['mode'] ?? 'html';

// Raw ESC/POS byte kick if raw requested
if ($mode === 'raw') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="drawer_kick.bin"');
    // ESC p 0 25 250 (Pin 2) + ESC p 1 25 250 (Pin 5)
    echo "\x1B\x70\x00\x19\xFA\x1B\x70\x01\x19\xFA";
    exit;
}
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <title>Cash Drawer Kick</title>
    <style>
        @page {
            size: 80mm 20mm;
            margin: 0;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: sans-serif;
            background: #fff;
            overflow: hidden;
            width: 80mm;
            height: 10mm;
        }
        .kick-indicator {
            display: none;
        }
        @media print {
            .kick-indicator {
                display: block;
                font-size: 8px;
                color: #ffffff; /* Invisible on white paper */
                line-height: 1;
            }
        }
    </style>
</head>
<body>
    <div class="kick-indicator">.</div>
    <script>
        // Trigger print immediately to pulse DK port via thermal printer driver
        window.addEventListener('DOMContentLoaded', () => {
            try {
                window.print();
            } catch (e) {
                console.error('Print trigger error:', e);
            }
        });
    </script>
</body>
</html>
