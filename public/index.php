<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

define("JSON_FILE", __DIR__ . "/../data/foods.json");
$PASSWORD = "888";
$VIEW_ONLY = isset($_GET['view']);
$REFRESH_SEC = 60;

// JSON 初始化
if (!file_exists(JSON_FILE)) {
    file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

// 登录处理
if (!$VIEW_ONLY && isset($_GET['admin']) && $_GET['admin'] == "1") {
    $_SESSION['food_admin'] = true;
}
if (!$VIEW_ONLY && isset($_POST['login_password']) && $_POST['login_password'] === $PASSWORD) {
    $_SESSION['food_admin'] = true;
}
if (!$VIEW_ONLY && isset($_GET['logout'])) {
    unset($_SESSION['food_admin']);
    header("Location: index.php");
    exit;
}

// 保存数据
if (!$VIEW_ONLY && isset($_SESSION['food_admin']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? "";

    if ($action === "add") {
        $foods[] = [
            "name"       => $_POST['name'],
            "name_en"    => $_POST['name_en'] ?? "",
            "category"   => $_POST['category'] ?? "other",
            "image_url"  => $_POST['image_url'] ?? "",
            "start_date" => $_POST['start_date'],
            "cycle_days" => intval($_POST['cycle_days'])
        ];
    }

    if ($action === "delete") {
        unset($foods[intval($_POST['index'])]);
        $foods = array_values($foods);
    }

    // ⚠ 写入 JSON 文件
    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: index.php");
    exit;
}

// ---------------- 计算周期函数 ---------------------
function get_cycle($start_date, $cycle_days) {
    if (empty($start_date) || intval($cycle_days) <= 0) {
        return ["from" => "-", "to" => "-", "left" => 0, "status" => "normal"];
    }
    $s = strtotime($start_date);
    $t = strtotime(date("Y-m-d"));
    $left = max(0, intval(($s + $cycle_days * 86400 - $t) / 86400));
    $status = ($left == 0) ? "expired" : (($left <= 2) ? "warning" : "normal");

    return [
        "from"   => date("m-d", $s),
        "to"     => date("m-d", $s + $cycle_days * 86400),
        "left"   => $left,
        "status" => $status
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>厨房食材管理系统 Kitchen Inventory System</title>
<link rel="stylesheet" href="assets/style.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if ($VIEW_ONLY): ?>
<meta http-equiv="refresh" content="<?= $REFRESH_SEC ?>">
<script>document.addEventListener("DOMContentLoaded",()=>{document.body.requestFullscreen?.();});</script>
<?php endif; ?>
</head>
<body>

<!-- 顶部标题 -->
<div class="header">
    <h1>🍽 厨房食材管理系统 <span class="en">Kitchen Inventory System</span></h1>
    <div class="time">更新时间 / Updated：<?= date("Y-m-d H:i:s") ?></div>
</div>

<!-- 只展示 -->
<?php if ($VIEW_ONLY): ?>
<div class="category-tabs">
    <button onclick="filterCategory('all')">全部 All</button>
    <button onclick="filterCategory('meat')">🥩 肉类 Meat</button>
    <button onclick="filterCategory('vegetable')">🥬 蔬菜 Vegetable</button>
    <button onclick="filterCategory('seafood')">🐟 海鲜 Seafood</button>
    <button onclick="filterCategory('dairy')">🥛 奶制品 Dairy</button>
</div>

<div class="grid">
<?php foreach ($foods as $f):
$c = get_cycle($f["start_date"], $f["cycle_days"]); ?>
    <div class="card <?= $c['status'] ?>" data-category="<?= $f['category'] ?>">
        <?php if(!empty($f["image_url"])): ?>
            <img src="<?= $f["image_url"] ?>" class="food-img">
        <?php endif; ?>
        <div class="name"><?= htmlspecialchars($f["name"]) ?> 
            <?php if (!empty($f["name_en"])): ?><span class="en"> / <?= htmlspecialchars($f["name_en"]) ?></span><?php endif; ?>
        </div>
        <div class="date">周期 / Cycle: <?= $c["from"] ?> ~ <?= $c["to"] ?></div>
        <div class="left"><?= $c["left"]>0? "剩余 / Left：{$c["left"]} 天 Days":"⚠ 已过期 / Expired"; ?></div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- 后台：登录 -->
<?php if (!$VIEW_ONLY && !isset($_SESSION['food_admin'])): ?>
<div class="login-box">
    <h2>🔒 后台登录</h2>
    <form method="post">
        <input type="password" name="login_password" placeholder="输入密码 888">
        <button>登录</button>
    </form>

    <p>📱 手机扫码登录后台：</p>
    <div id="qr-login"></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        new QRCode(document.getElementById("qr-login"), {
            text: "https://<?= $_SERVER['HTTP_HOST'] ?>/?admin=1",
            width: 180,
            height: 180
        });
    </script>
</div>
<?php endif; ?>

<!-- 后台：已登录 -->
<?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
<div class="admin-box">
    <h2>📌 后台管理系统</h2>
    <a href="?view=1" class="btn-link">切换厨房模式</a>
    <a href="?logout=1" class="btn-logout">退出登录</a>
    <hr>

    <h2>➕ 添加食材</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <input name="name" placeholder="中文名称" required>
        <input name="name_en" placeholder="英文名称 (可选)">
        <select name="category">
            <option value="meat">肉类 Meat</option>
            <option value="vegetable">蔬菜 Vegetable</option>
            <option value="seafood">海鲜 Seafood</option>
            <option value="dairy">奶制品 Dairy</option>
        </select>
        <input name="image_url" placeholder="图片URL">
        <input type="date" name="start_date" required>
        <input type="number" name="cycle_days" placeholder="天数">
        <button>保存</button>
    </form>

    <h2>📋 当前食材</h2>
    <?php foreach ($foods as $i => $f): ?>
        <form method="post" class="row-edit">
            <?= $i+1 ?>. <?= $f["name"] ?>（<?= $f["start_date"] ?>）
            <input type="hidden" name="index" value="<?= $i ?>">
            <button name="action" value="delete">删除</button>
        </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function filterCategory(c) {
    document.querySelectorAll('.card').forEach(el=>{
        el.style.display = (c=='all' || el.dataset.category==c) ? 'block' : 'none';
    });
}
</script>

</body>
</html>
