<?php 
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ==============================
   🔒 JSON 永久存储路径（Render 推荐）
   ============================== */
$write_dir = "/opt/render/project/.data/";
if (!is_dir($write_dir)) { $write_dir = __DIR__ . "/"; }
define("JSON_FILE", $write_dir . "foods.json");

$PASSWORD   = "888";
$VIEW_ONLY  = isset($_GET['view']);
$REFRESH_SEC = 60;

/* ==============================
   🧪 JSON 初始化
   ============================== */
if (!file_exists(JSON_FILE)) {
    file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

/* ==============================
   🔐 登录
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

/* ==============================
   💾 保存数据（后台）
   ============================== */
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
            "auto_renew" => false
        ];
    }

    if ($action === "toggle_renew") {
        $index = intval($_POST['index']);
        if (isset($foods[$index])) {
            $foods[$index]['auto_renew'] = !($foods[$index]['auto_renew'] ?? false);
        }
    }

    if ($action === "delete") {
        unset($foods[intval($_POST['index'])]);
        $foods = array_values($foods);
    }

    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: index.php?saved=1");
    exit;
}

/* ==============================
   🧮 周期显示（英文格式显示）
   ============================== */
function get_cycle($start_date, $cycle_days, $auto_renew = false) {
    if (!$start_date || intval($cycle_days) <= 0) {
        return ["from" => "-", "to" => "-", "left" => 0, "hours" => 0, "mins" => 0, "status" => "normal"];
    }
    $s = strtotime($start_date);
    $end = $s + $cycle_days * 86400;
    $seconds_left = max(0, $end - time());

    if ($seconds_left <= 0 && $auto_renew) {
        $s = strtotime(date("Y-m-d"));
        $end = $s + $cycle_days * 86400;
        $seconds_left = $end - time();
    }

    return [
        "from"  => date("m-d", $s),
        "to"    => date("m-d", $end),
        "left"  => floor($seconds_left / 86400),
        "hours" => floor(($seconds_left % 86400) / 3600),
        "mins"  => floor(($seconds_left % 3600) / 60),
        "status" => ($seconds_left <= 0 ? "expired" : ((floor($seconds_left / 86400) <= 2) ? "warning" : "normal"))
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
<?php if ($VIEW_ONLY): ?><meta http-equiv="refresh" content="<?= $REFRESH_SEC ?>"><?php endif; ?>
</head>
<body>

<div class="header">
    <h1>🍽 厨房食材管理系统 <span class="en">Kitchen Inventory System</span></h1>
    <div class="time">更新时间 / Updated：<?= date("Y-m-d H:i:s") ?></div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert success">✔ 保存成功 / Data Saved</div>
<?php endif; ?>

<!-- 展示模式 -->
<?php if ($VIEW_ONLY): ?>
<div class="grid">
<?php foreach ($foods as $i=>$f):
    $c = get_cycle($f["start_date"], $f["cycle_days"], $f["auto_renew"] ?? false); ?>
    <div class="card <?= $c['status'] ?>">
        <div class="name"><b><?= htmlspecialchars($f["name"]) ?></b> / <span class="en"><?= htmlspecialchars($f["name_en"]) ?></span></div>
        <div class="date">周期 / Cycle: <?= $c["from"] ?> ~ <?= $c["to"] ?></div>
        <div class="left">剩余 / Left: <?= $c["left"] ?> Days <?= $c["hours"] ?> Hours <?= $c["mins"] ?> Min</div>
        <?php if ($f['auto_renew'] ?? false): ?>
            <div class="renew">🔄 自动续期 / Auto-Renew ON</div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- 登录 -->
<?php if (!$VIEW_ONLY && !isset($_SESSION['food_admin'])): ?>
<div class="login-box">
    <h2>🔒 登录后台 / Admin Login</h2>
    <form method="post"><input name="login_password" type="password" placeholder="密码 / Password"><button>登录/Login</button></form>
    <p>📱 扫码登录 / Scan to Login</p>
</div>
<?php endif; ?>

<!-- 后台管理 -->
<?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
<div class="admin-box">
    <h2>📌 后台管理 / Admin Panel</h2>
    <a href="?view=1">👀 展示模式 / View Mode</a> | <a href="?logout=1">退出 / Logout</a>
    <hr>

    <h2>➕ 添加食材 / Add Item</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <input name="name" required placeholder="中文名称 / Chinese name">
        <input name="name_en"  placeholder="英文名称 / English name">
        <input name="start_date" type="date" required>
        <input name="cycle_days" type="number" placeholder="天数 / Days">
        <button>保存 / Save</button>
    </form>
</div>
<?php endif; ?>
</body>
</html>
