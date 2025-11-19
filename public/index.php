<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

define("JSON_FILE", __DIR__ . "/foods.json");
$PASSWORD = "888";
$VIEW_ONLY = isset($_GET['view']);
$REFRESH_SEC = 60;

// 🔥 载入二维码库
require_once __DIR__ . "/lib/phpqrcode/qrlib.php";

// JSON Init
if (!file_exists(JSON_FILE)) file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

// 处理登录
if (!$VIEW_ONLY && isset($_GET['admin']) && $_GET['admin'] == "1") {
    $_SESSION['food_admin'] = true;   // 扫码自动登录后台
}
if (!$VIEW_ONLY && isset($_POST['login_password']) && $_POST['login_password'] === $PASSWORD) {
    $_SESSION['food_admin'] = true;
}
if (!$VIEW_ONLY && isset($_GET['logout'])) {
    unset($_SESSION['food_admin']);
    header("Location: index.php");
    exit;
}

// 保存食材(后台模式)
if (!$VIEW_ONLY && isset($_SESSION['food_admin']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? "";

    if ($action === "add") {
        $foods[] = [
            "name"       => $_POST['name'],
            "name_en"    => $_POST['name_en"] ?? "",
            "category"   => $_POST['category"] ?? "other",
            "image_url"  => $_POST['image_url"] ?? "",
            "start_date" => $_POST['start_date"],
            "cycle_days" => intval($_POST['cycle_days"])
        ];
    }
    if ($action === "delete") {
        $i = intval($_POST['index']);
        unset($foods[$i]);
        $foods = array_values($foods);
    }

    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: index.php");
    exit;
}

// 🔥 计算周期
function get_cycle($start_date, $cycle_days) {
    if (!$start_date || intval($cycle_days)<=0){
        return ["from"=>"-","to"=>"-","left"=>0,"status"=>"normal"];
    }
    $s=strtotime($start_date); $t=strtotime(date("Y-m-d"));
    $left=max(0, intval(($s+$cycle_days*86400-$t)/86400));
    $cls=($left==0)? "expired":(($left<=2)? "warning":"normal");
    return ["from"=>date("m-d",$s),"to"=>date("m-d",$s+$cycle_days*86400),"left"=>$left,"status"=>$cls];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>厨房食材管理系统</title>
<link rel="stylesheet" href="assets/style.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<?php if ($VIEW_ONLY): ?>
<meta http-equiv="refresh" content="<?= $REFRESH_SEC ?>">
<script>document.addEventListener("DOMContentLoaded",()=>{document.body.requestFullscreen?.();});</script>
<?php endif; ?>
</head>
<body>

<!-- ===================== 标题 ===================== -->
<div class="header">
    <h1>🍽 厨房食材管理系统</h1>
    <div>更新时间：<?= date("Y-m-d H:i:s") ?></div>
</div>

<!-- ===================== 展示模式 ===================== -->
<?php if ($VIEW_ONLY): ?>
<div class="category-tabs">
    <button onclick="filterCategory('all')">全部</button>
    <button onclick="filterCategory('meat')">🥩 肉类</button>
    <button onclick="filterCategory('vegetable')">🥬 蔬菜</button>
    <button onclick="filterCategory('seafood')">🐟 海鲜</button>
    <button onclick="filterCategory('dairy')">🥛 奶制品</button>
</div>
<?php endif; ?>

<div class="grid">
<?php foreach ($foods as $f):
$c = get_cycle($f["start_date"], $f["cycle_days"]); ?>
<div class="card <?= $c['status'] ?>" data-category="<?= $f['category'] ?>">
    <?php if(!empty($f["image_url"])): ?>
        <img src="<?= $f["image_url"] ?>" class="food-img">
    <?php endif; ?>
    <div class="name"><?= htmlspecialchars($f["name"]) ?></div>
    <?php if (!empty($f["name_en"])): ?>
        <div class="name-en"><?= htmlspecialchars($f["name_en"]) ?></div>
    <?php endif; ?>
    <div class="date"><?= $c["from"] ?> ~ <?= $c["to"] ?></div>
    <div class="left"><?= $c["left"]>0? "剩余：{$c["left"]} 天":"⚠ 已过期"; ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- ===================== 后台管理 ===================== -->
<?php if (!$VIEW_ONLY): ?>
<?php if (!isset($_SESSION['food_admin'])): ?>

<!-- 登录页面 -->
<div class="login-box">
    <h2>🔒 后台管理登录</h2>
    <form method="post">
        <input type="password" name="login_password" placeholder="输入密码 888">
        <button>登录</button>
    </form>

    <!-- 🔥 生成二维码 -->
    <p>📱 手机扫码快速登录后台：</p>
    <?php
        $login_url = "https://" . $_SERVER['HTTP_HOST'] . "/?admin=1";
        QRcode::png($login_url, false, QR_ECLEVEL_L, 8);
    ?>
</div>

<?php else: ?>
<!-- 已登录，显示后台 -->
<div class="admin-box">
    <h2>📌 当前后台登录成功</h2>
    <a href="?view=1" class="btn-link">切换到厨房展示屏模式</a>
    <a href="?logout=1" class="btn-logout">退出登录</a>
    <hr>

    <h2>➕ 添加食材</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <input name="name" placeholder="中文名称" required>
        <input name="name_en" placeholder="英文名称">
        <input name="image_url" placeholder="图片URL">
        <input type="date" name="start_date" required>
        <input type="number" name="cycle_days" placeholder="周期天数">
        <button>保存</button>
    </form>

    <h2>📋 已有食材</h2>
    <?php foreach ($foods as $i => $f): ?>
        <form method="post" class="row-edit">
            <?= $i+1 ?>. <?= $f["name"] ?>（<?= $f["start_date"] ?>）
            <input type="hidden" name="index" value="<?= $i ?>">
            <button name="action" value="delete">删除</button>
        </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
function filterCategory(c) {
    document.querySelectorAll('.card').forEach(el=>{
        el.style.display = (c=='all' || el.dataset.category==c) ? 'block':'none';
    });
}
</script>
</body>
</html>
