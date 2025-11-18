<?php
//-----------------------------------------------------------
// 食物周期管理系统（public/index.php 最终版）
//-----------------------------------------------------------

session_start();

// 是否纯显示
$VIEW_ONLY = isset($_GET['view']);
$PASSWORD = "888";
$REFRESH_SECONDS = 60;
$lang = $_GET['lang'] ?? 'zh';

// 多语言
$L = [
    "zh" => ["title"=>"食物周期显示系统","scan"=>"📱 扫码进入设置","add"=>"添加","logout"=>"退出设置","enter_pwd"=>"请输入密码（默认888）","settings"=>"设置区（需密码）","current"=>"当前周期","left"=>"剩余","days"=>"天","expired"=>"已过期"],
    "en" => ["title"=>"Food Cycle Display System","scan"=>"📱 Scan to Modify","add"=>"Add","logout"=>"Logout","enter_pwd"=>"Enter Password","settings"=>"Settings","current"=>"Cycle","left"=>"Left","days"=>"days","expired"=>"Expired"],
    "es" => ["title"=>"Sistema de Ciclo de Alimentos","scan"=>"📱 Escanee para Ajustes","add"=>"Añadir","logout"=>"Salir","enter_pwd"=>"Ingrese contraseña","settings"=>"Ajustes","current"=>"Ciclo","left"=>"Queda","days"=>"días","expired"=>"Vencido"]
];
$T = $L[$lang] ?? $L["zh"];

// 数据文件
$dataFile = __DIR__ . "/foods.json";
if (!file_exists($dataFile)) file_put_contents($dataFile, "[]");
$foods = json_decode(file_get_contents($dataFile), true);

// QR code 本地生成
require_once __DIR__ . "/../phpqrcode/qrlib.php";
function qr_img($url) {
    $filename = sys_get_temp_dir()."/qr_food.png";
    QRcode::png($url, $filename, QR_ECLEVEL_L, 8);
    return $filename;
}

// 周期计算
function get_cycle($start_date, $cycle_days) {
    if ($cycle_days == 0) return ["from"=>"","to"=>"","left"=>0,"status"=>"expired"];
    $start = strtotime($start_date);
    $today = strtotime(date("Y-m-d"));
    $days_passed = floor(($today - $start) / 86400);
    $index = floor($days_passed / $cycle_days);
    $cycle_start = strtotime("+".($index*$cycle_days)." days", $start);
    $cycle_end = strtotime("+".($cycle_days-1)." days", $cycle_start);
    $left = max(0, floor(($cycle_end - $today) / 86400) + 1);

    $status = $left<=0 ? "expired" : ($left==1?"warning":"normal");
    return ["from"=>date("m-d",$cycle_start),"to"=>date("m-d",$cycle_end),"left"=>$left,"status"=>$status];
}

// 登录
if (isset($_POST['login_password'])) {
    if ($_POST['login_password']===$PASSWORD) $_SESSION['food_admin']=1;
}

// 操作保存
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_SESSION['food_admin'])) {
    $act = $_POST['action'];
    if ($act==="add") {
        $foods[] = ["name"=>$_POST['name'],"start_date"=>$_POST['start_date'],"cycle_days"=>intval($_POST['cycle_days'])];
    }
    file_put_contents($dataFile, json_encode($foods, JSON_UNESCAPED_UNICODE));
    header("Location: index.php"); exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"><title><?= $T["title"] ?></title>
<meta http-equiv="refresh" content="<?= $REFRESH_SECONDS ?>">
<style>
body{background:#fff;font-family:Arial;margin:0;padding:20px;}
.card{padding:20px;border-radius:10px;margin:10px;background:#f5f5f5;}
</style>
</head>
<body>

<h1><?= $T["title"] ?></h1>
<p>更新时间：<?= date("Y-m-d H:i:s") ?>（<?= $REFRESH_SECONDS ?>秒自动刷新）</p>

<?php if($VIEW_ONLY): ?>
    <p><?= $T["scan"] ?></p>
    <img src="data:image/png;base64,<?= base64_encode(file_get_contents(qr_img('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']))); ?>">
<?php endif; ?>

<?php if (!$VIEW_ONLY && !isset($_SESSION['food_admin'])): ?>
    <form method="post">
        <input type="password" name="login_password" placeholder="<?= $T['enter_pwd'] ?>">
        <button><?= $T['enter_pwd'] ?></button>
    </form>
<?php endif; ?>

<?php if (isset($_SESSION['food_admin'])): ?>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <input name="name" placeholder="名称">
        <input name="start_date" type="date">
        <input name="cycle_days" type="number" placeholder="天数">
        <button><?= $T["add"] ?></button>
    </form>
<?php endif; ?>

<?php foreach($foods as $f): $c=get_cycle($f['start_date'],$f['cycle_days']); ?>
    <div class="card">
        <b><?= $f['name'] ?></b> — <?= $c['left'].$T['days'] ?>
    </div>
<?php endforeach; ?>

</body></html>
