<?php
// 自动生成后台扫码登录二维码（不需要任何库）
$login_url = "https://" . $_SERVER['HTTP_HOST'] . "/?admin=1";

// 使用 Google API 生成二维码
$qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($login_url);

// 跳转 / 显示二维码
header("Location: $qr_api");
exit;
?>
