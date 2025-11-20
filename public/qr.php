<?php
require_once realpath(__DIR__ . "/lib/phpqrcode/qrlib.php");

$login_url = "https://" . $_SERVER['HTTP_HOST'] . "/?admin=1";

ob_clean();
header("Content-Type: image/png");
QRcode::png($login_url, false, QR_ECLEVEL_L, 8);
exit;
?>
