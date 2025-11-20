<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ✔ Render 永久保存目录 */
$JSON_PATH = "/opt/render/project/.data/foods.json";
define("JSON_FILE", $JSON_PATH);

$PASSWORD = "888";
$VIEW_ONLY = isset($_GET['view']);
$REFRESH_SEC = 60;

// 🧪 如果 JSON 不存在 → 自动创建
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

// 保存食材
if (!$VIEW_ONLY && isset($_SESSION['food_admin']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? "";

    if ($action === "add") {
        $foods[] = [
            "name"       => $_POST['name'],
            "name_en"    => $_POST['name_en'] ?? "",
            "category"   => $_POST['category'] ?? "other",
            "image_url"  => $_POST['image_url'] ?? "",
            "start_date" => $_POST['start_date'],      // now supports Y-m-d H:i format
            "cycle_days" => intval($_POST['cycle_days']),
        ];
    }

    if ($action === "delete") {
        unset($foods[intval($_POST['index'])]);
        $foods = array_values($foods);
    }

    // 🔄 自动续期 – 更新 start_date 为现在时间
    if ($action === "renew") {
        $idx = intval($_POST['index']);
        $foods[$idx]["start_date"] = date("Y-m-d H:i");  // reset start time to NOW
    }

    // 写入 JSON & 复制到公开目录
    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    copy(JSON_FILE, __DIR__ . "/foods.json");
    header("Location: index.php?saved=1");
    exit;
}

/* ----------- 计算周期 ----------- */
function get_cycle($start_date, $cycle_days) {
    if (!$start_date || intval($cycle_days) <= 0) {
        return ["from" => "-", "to" => "-", "left" => 0, "status" => "normal"];
    }
    $s = strtotime($start_date);
    $left = intval((($s + $cycle_days * 86400) - time()) / 86400);
    $left = max(0, $left);
    $status = ($left == 0) ? "expired" : (($left <= 2) ? "warning" : "normal");

    return [
        "from" => date("m-d H:i", $s),
        "to"   => date("m-d H:i", $s + $cycle_days * 86400),
        "left" => $left,
        "status" => $status,
    ];
}

usort($foods, function($a, $b){
    $c1 = get_cycle($a['start_date'], $a['cycle_days']);
    $c2 = get_cycle($b['start_date'], $b['cycle_days']);
    return $c1['left'] <=> $c2['left'];
});
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>厨房食材管理系统 Kitchen Inventory System</title>
<link rel="stylesheet" href="assets/style.css">
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>

<!-- 顶部 -->
<div class="header">
    <h1>🍽 厨房食材管理系统 <span class="en">Kitchen Inventory System</span></h1>
    <div class="time">更新时间 / Updated：<?= date("Y-m-d H:i:s") ?></div>
</div>

<?php if ($VIEW_ONLY): ?>
<div class="category-tabs">
    <button onclick="filterCategory('all')">全部 All</button>
    <button onclick="filterCategory('meat')">🥩 肉类 Meat</button>
    <button onclick="filterCategory('vegetable')">🥬 蔬菜 Vegetable</button>
    <button onclick="filterCategory('seafood')">🐟 海鲜 Seafood</button>
    <button onclick="filterCategory('dairy')">🥛 奶制品 Dairy</button>
</div>

<div class="grid">
<?php foreach ($foods as $f): $c = get_cycle($f["start_date"], $f["cycle_days"]); ?>
    <div class="card <?= $c['status'] ?>" data-category="<?= $f['category'] ?>">
        <?php if ($f["image_url"]): ?><img src="<?= $f["image_url"] ?>" class="food-img"><?php endif; ?>
        <div class="name"><?= $f["name"] ?><?= $f["name_en"] ? " / ".$f["name_en"] : "" ?></div>
        <div class="date">周期 / Cycle: <?= $c["from"] ?> ~ <?= $c["to"] ?></div>
        <div class="left"><?= $c["left"]>0 ? "剩余：".$c["left"]." 天" : "⚠ 已过期" ?></div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$VIEW_ONLY && !isset($_SESSION['food_admin'])): ?>
<div class="login-box">
    <h2>🔒 后台登录 / Admin Login</h2>
    <form method="post">
        <input name="login_password" type="password" placeholder="输入密码 Password" required>
        <button>登录 Login</button>
    </form>
</div>
<?php endif; ?>

<?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
<div class="admin-box">
    <h2>📌 后台管理 / Admin Panel</h2>
    <a class="btn-link" href="?view=1">👀 切换展示模式 View Mode</a>
    <a class="btn-logout" href="?logout=1">🚪 退出登录 Logout</a>

    <h3>➕ 添加 / Add New</h3>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <input name="name" required placeholder="中文 Chinese name">
        <input name="name_en" placeholder="English name (optional)">
        <input name="image_url" placeholder="Image URL (optional)">
        <input name="start_date" type="datetime-local" required>
        <input name="cycle_days" type="number" placeholder="天 / Days">
        <button>保存 Save</button>
    </form>

    <h3>📋 当前食材 / Current Items</h3>
    <?php foreach ($foods as $i=>$f): $c = get_cycle($f["start_date"], $f["cycle_days"]); ?>
        <form method="post" class="row-edit">
            <?= $f["name"] ?>  
            <small>(<?= $c["left"] ?> 天 left)</small>
            <input type="hidden" name="index" value="<?= $i ?>">

            <button name="action" value="renew">🔄 自动续期 Renew</button>
            <button name="action" value="delete">🗑 删除 Delete</button>
        </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function filterCategory(c) {
    document.querySelectorAll('.card').forEach(el=>{
        el.style.display = (c=='all'||el.dataset.category==c)?'block':'none';
    });
}
</script>
</body>
</html>
