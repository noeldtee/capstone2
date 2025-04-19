<?php
require 'libs/phpqrcode/qrlib.php';
$text = "https://example.com";
$file = 'uploads/qrcodes/test.png';
QRcode::png($text, $file, QR_ECLEVEL_L, 4);
echo "QR code generated at $file";
?>