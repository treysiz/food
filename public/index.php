<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ======================================
   ✔ Render 永久保存目录（不会消失）
   ====================================== */
$JSON_PATH = "/opt/render/project/.data/foods.json";
define("JSON_FILE", $JSON_PATH);

$PASSWORD = "888";    // 后台密码
$VIEW_ONLY = isset($_GET['view']);  // 只显示模式
$REFRESH_SEC = 60;    // 自动刷新秒数

/* ======================================
   🧪 如果 JSON 文件不存在 → 自动创建
   ====================================== */
if (!file_exists(JSON_FILE)) {
    file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

/* ======================================
   🔑 登录处理
   ====================================== */
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

/* ======================================
   💾 添加 / 删除 / 自动续期
   ====================================== */
if (!$VIEW_ONLY && isset($_SESSION['food_admin']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? "";
    $id = intval($_POST['index'] ?? -1);

    if ($action === "add") {
        $foods[] = [
            "name"       => trim($_POST['name']),
            "name_en"    => trim($_POST['name_en'] ?? ""),
            "category"   => $_POST['category'] ?? "other",
            "image_url"  => trim($_POST['image_url'] ?? ""),
            "start_date" => date("Y-m-d H:i:s"),  // 🔥 自动带时间
            "cycle_days" => intval($_POST['cycle_days']),
        ];
    }

    if ($action === "delete" && $id >= 0) {
        unset($foods[$id]);
        $foods = array_values($foods);
    }

    if ($action === "renew" && $id >= 0) {  // 🔄 自动续期按钮
        $foods[$id]['start_date'] = date("Y-m-d H:i:s");
    }

    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @copy(JSON_FILE, __DIR__ . "/foods.json");  // 手动备份

    header("Location: index.php?saved=1");
    exit;
}

/* ======================================
   ⏱ 计算周期函数
   ====================================== */
function get_cycle($start_date, $cycle_days) {
    if (!$start_date || intval($cycle_days) <= 0) {
        return ["from" => "-", "to" => "-", "left" => 0, "status" => "normal"];
    }
    $s = strtotime($start_date);
    $end_ts = $s + $cycle_days * 86400;
    $left = max(0, intval(($end_ts - time()) / 86400));
    $status = ($left == 0) ? "expired" : (($left <= 2) ? "warning" : "normal");
    return [
        "from" => date("m-d H:i", $s),
        "to"   => date("m-d H:i", $end_ts),
        "left" => $left,
        "status" => $status,
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>厨房食材管理系统 Kitchen Inventory System</title>
<link rel="stylesheet" href="assets/style.css">
<meta name="viewport" content="width=device-width,initial-scale=1">

<?php if ($VIEW_ONLY): ?>
<meta http-equiv="refresh" content="<?= $REFRESH_SEC ?>">
<script>document.addEventListener("DOMContentLoaded",()=>{document.body.requestFullscreen?.();});</script>
<?php endif; ?>
</head>

<body>

<!-- 成功提示 -->
<?php if (isset($_GET['saved'])): ?>
<div class="alert success">✔ 数据保存成功 Data Saved!</div>
<?php endif; ?>

<!-- 顶部 -->
<div class="header">
    <h1>🍽 厨房食材管理系统 <span class="en">Kitchen Inventory System</span></h1>
    <div class="time">更新时间 / Updated：<?= date("Y-m-d H:i:s") ?></div>
</div>

<!-- ================= 展示模式 =============== -->
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
        <?php if ($f["image_url"]): ?>
        <img src="<?= $f["image_url"] ?>" class="food-img">
        <?php endif; ?>

        <div class="name">
            <?= htmlspecialchars($f["name"]) ?>
            <?php if ($f["name_en"]): ?><span class="en"> / <?= htmlspecialchars($f["name_en"]) ?></span><?php endif; ?>
        </div>
        <div class="date">📅 <?= $c["from"] ?> → <?= $c["to"] ?></div>
        <div class="left"><?= $c["left"]>0 ? "剩余：" . $c["left"] . "天" : "⚠ 已过期" ?></div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ================= 后台登录 =============== -->
<?php if (!$VIEW_ONLY && !isset($_SESSION['food_admin'])): ?>
<div class="login-box">
    <h2>🔒 后台登录 / Admin Login</h2>
    <form method="post">
        <input name="login_password" type="password" placeholder="密码 / Password" required>
        <button>登录 Login</button>
    </form>
</div>
<?php endif; ?>

<!-- ================= 后台管理 =============== -->
<?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
<div class="admin-box">
    <h2>📌 后台管理系统 / Admin Panel</h2>
    <a href="?view=1" class="btn-link">👀 展示模式 View Mode</a>
    <a href="?logout=1" class="btn-logout">🚪 退出 Logout</a>

    <hr><h2>➕ 添加食材 / Add Item</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <input name="name" placeholder="中文名称 Chinese" required>
        <input name="name_en" placeholder="英文名称 English">
        <select name="category">
            <option value="meat">肉类 Meat</option>
            <option value="vegetable">蔬菜 Vegetable</option>
            <option value="seafood">海鲜 Seafood</option>
            <option value="dairy">奶制品 Dairy</option>
        </select>
        <input name="image_url" placeholder="图片URL Image URL">
        <input name="cycle_days" type="number" placeholder="周期天数 Days" required>
        <button>保存 Save</button>
    </form>

    <hr><h2>📋 当前食材 / Current Items</h2>
    <?php foreach ($foods as $i => $f):
        $c = get_cycle($f["start_date"], $f["cycle_days"]); ?>
    <form method="post" class="row-edit">
        <?= $i+1 ?>. <?= htmlspecialchars($f["name"]) ?>（<?= $f["start_date"] ?>）
        <input type="hidden" name="index" value="<?= $i ?>">
        <button name="action" value="renew">🔄 续期 Renew</button>
        <button name="action" value="delete">🗑 删除 Delete</button>
    </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function filterCategory(c) {
    document.querySelectorAll('.card').forEach(el=>{
        el.style.display = (c=='all' || el.dataset.category==c)?'block':'none';
    });
}
</script>

</body>
</html>

