<?php
// 手机扫码自动登录后台：/?admin=1
$login_url = "https://" . $_SERVER['HTTP_HOST'] . "/?admin=1";

// ⭐ 用在线API生成二维码（Render兼容，不需要PHP扩展）
$qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($login_url);

// 📌 最安全做法：直接 302 跳转到生成的二维码
header("Location: $qr_api");
exit;
?>
