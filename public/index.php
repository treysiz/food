<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ==============================
   🔒 JSON 永久存储路径（Render 推荐）
   ============================== */
$write_dir = "/opt/render/project/.data/";  // Render 最安全的写入目录
if (!is_dir($write_dir)) {                  // 本地开发模式：写当前目录
    $write_dir = __DIR__ . "/";
}
define("JSON_FILE", $write_dir . "foods.json");

$PASSWORD   = "888";
$VIEW_ONLY  = isset($_GET['view']);
$REFRESH_SEC = 60;

/* ==============================
   🧪 JSON 初始化（若不存在 → 创建）
   ============================== */
if (!file_exists(JSON_FILE)) {
    file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];


/* ==============================
   🔐 登录处理
   ============================== */
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

// 保存食材（写入 JSON）
if (!$VIEW_ONLY && isset($_SESSION['food_admin']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? "";

    if ($action === "add") {
        $foods[] = [
            "name"       => $_POST['name'],
            "name_en"    => $_POST['name_en'] ?? "",
            "category"   => $_POST['category'] ?? "other",
            "image_url"  => $_POST['image_url'] ?? "",
            "start_date" => $_POST['start_date'],
            "cycle_days" => intval($_POST['cycle_days']),
        ];
    }

    if ($action === "delete") {
        unset($foods[intval($_POST['index'])]);
        $foods = array_values($foods);
    }

    // ✔ 写入 JSON 文件 (永久存储)
    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // 🔁 复制一份到公开目录 /var/www/html/foods.json
    copy(JSON_FILE, __DIR__ . "/foods.json");

    // 🔍 可见写入信息（调试用，确认成功后可删除）
    $bytes = filesize(JSON_FILE);
    echo "<div style='color:green'>✔ 写入成功!<br>JSON路径: " . JSON_FILE . "<br>写入字节: $bytes</div>";

    header("Location: index.php?saved=1");
    exit;
}
    // 🔥 写 JSON（成功返回写入字节数，可 debug）
    $res = file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    header("Location: index.php?saved={$res}");
    exit;
}


/* ==============================
   📆 计算食材周期剩余天数
   ============================== */
function get_cycle($start_date, $cycle_days) {
    if (!$start_date || intval($cycle_days) <= 0) {
        return ["from" => "-", "to" => "-", "left" => 0, "status" => "normal"];
    }
    $s    = strtotime($start_date);
    $left = max(0, intval((($s + $cycle_days * 86400) - time()) / 86400));
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
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($VIEW_ONLY): ?>
<meta http-equiv="refresh" content="<?= $REFRESH_SEC ?>">
<script>
document.addEventListener("DOMContentLoaded",()=>{ document.body.requestFullscreen?.(); });
</script>
<?php endif; ?>
</head>
<body>

<!-- 顶部 -->
<div class="header">
    <h1>🍽 厨房食材管理系统 <span class="en">Kitchen Inventory System</span></h1>
    <div class="time">更新时间 / Updated：<?= date("Y-m-d H:i:s") ?></div>
</div>

<!-- 🧪 JSON 写入测试显示 -->
<?php if (isset($_GET['saved'])): ?>
<div class="alert success">
    ✔ 数据写入成功（写入字节：<?= $_GET['saved'] ?>）<br>
    📂 路径： <?= JSON_FILE ?>
</div>
<?php endif; ?>

<!-- 展示模式 -->
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

        <div class="name"><?= htmlspecialchars($f["name"]) ?> 
            <?php if ($f["name_en"]): ?><span class="en"> / <?= htmlspecialchars($f["name_en"]) ?></span><?php endif; ?>
        </div>
        <div class="date">周期 / Cycle: <?= $c["from"] ?> ~ <?= $c["to"] ?></div>
        <div class="left"><?= $c["left"]>0 ? "剩余：" . $c["left"] . "天" : "⚠ 已过期" ?></div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>


<!-- 登录页面 -->
<?php if (!$VIEW_ONLY && !isset($_SESSION['food_admin'])): ?>
<div class="login-box">
    <h2>🔒 后台登录</h2>
    <form method="post">
        <input name="login_password" type="password" placeholder="输入密码 888"><button>登录</button>
    </form>

    <p>📱 手机扫码登录后台</p>
    <div id="qr-login"></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    new QRCode(document.getElementById("qr-login"), {
        text: "https://<?= $_SERVER['HTTP_HOST'] ?>/?admin=1",
        width: 180, height: 180
    });
    </script>
</div>
<?php endif; ?>


<!-- 后台管理 -->
<?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
<div class="admin-box">
    <h2>📌 后台管理</h2>
    <a href="?view=1" class="btn-link">切换展示模式</a>
    <a href="?logout=1" class="btn-logout">退出登录</a>
    <hr>

    <h2>➕ 添加食材</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <input name="name" required placeholder="中文名称">
        <input name="name_en" placeholder="英文名称 (可选)">
        <select name="category">
            <option value="meat">肉类 Meat</option>
            <option value="vegetable">蔬菜 Vegetable</option>
            <option value="seafood">海鲜 Seafood</option>
            <option value="dairy">奶制品 Dairy</option>
        </select>
        <input name="image_url" placeholder="图片 URL">
        <input name="start_date" type="date" required>
        <input name="cycle_days" type="number" placeholder="天数">
        <button>保存</button>
    </form>

    <h2>📋 当前食材</h2>
    <?php foreach ($foods as $i=>$f): ?>
        <form method="post">
            <?= $i+1 ?>. <?= htmlspecialchars($f["name"]) ?> (<?= $f["start_date"] ?>)
            <input type="hidden" name="index" value="<?= $i ?>">
            <button name="action" value="delete">删除</button>
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
