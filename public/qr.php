<?php
// 🔐 手机扫码自动登录后台（不用任何PHP库）
$login_url = "https://" . $_SERVER['HTTP_HOST'] . "/?admin=1";

// ⭐ 使用稳定API生成二维码（Render兼容）⭐
$qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($login_url);

// ⛔⚠ 不要输出任何HTML ！直接跳转图片
header("Location: $qr_api");
exit;
?>
