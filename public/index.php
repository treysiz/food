<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ==============================
   🛑 V5 – GitHub 设置（需要你替换）
============================== */
$GITHUB_RAW_URL = $GITHUB_RAW_URL = "https://raw.githubusercontent.com/treysiz/food/main/public/foods.json"; // 必须改成你自己的

/* ==============================
   🔒 JSON 永久存储路径（Render 推荐）
============================== */
$write_dir = "/opt/render/project/.data/";
if (!is_dir($write_dir)) { $write_dir = __DIR__ . "/"; }
define("JSON_FILE", $write_dir . "foods.json");

/* ==============================
   🧪 V5 - 如果 JSON 不存在 → 自动从 GitHub 恢复
============================== */
if (!file_exists(JSON_FILE) || filesize(JSON_FILE) < 5) {
    $git_json = @file_get_contents($GITHUB_RAW_URL);
    if ($git_json) {
        file_put_contents(JSON_FILE, $git_json);
    } else {
        file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
    }
}
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

$PASSWORD   = "888";
$VIEW_ONLY  = isset($_GET['view']);
$REFRESH_SEC = 60;

/* ==============================
   🔐 登录处理
============================== */
if (!$VIEW_ONLY && isset($_GET['admin']) && $_GET['admin']=="1") { $_SESSION['food_admin']=true; }
if (!$VIEW_ONLY && isset($_POST['login_password']) && $_POST['login_password']===$PASSWORD) { $_SESSION['food_admin']=true; }
if (!$VIEW_ONLY && isset($_GET['logout'])) { unset($_SESSION['food_admin']); header("Location:index.php"); exit; }

/* ==============================
   💾 保存数据 & GitHub 备份
============================== */
if (!$VIEW_ONLY && isset($_SESSION['food_admin']) && $_SERVER['REQUEST_METHOD']==='POST') {
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
        $i = intval($_POST['index']);
        if (isset($foods[$i])) {
            $foods[$i]['auto_renew'] = !($foods[$i]['auto_renew'] ?? false);
        }
    }

    if ($action === "delete") {
        unset($foods[intval($_POST['index'])]);
        $foods = array_values($foods);
    }

    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // 🔁 这里可加 GitHub API 上传（V6会加）
    header("Location: index.php?saved=1");
    exit;
}

/* ==============================
   📆 周期显示优化
============================== */
function get_cycle($start_date, $cycle_days, $auto_renew = false) {
    if (!$start_date || intval($cycle_days) <= 0) {
        return ["from"=>"–","to"=>"–","left"=>0,"hours"=>0,"mins"=>0,"status"=>"normal"];
    }
    $s = strtotime($start_date);
    $end = $s + $cycle_days*86400;
    $seconds_left = max(0, $end-time());

    if ($seconds_left <= 0 && $auto_renew) {
        $s = strtotime(date("Y-m-d"));
        $end = $s + $cycle_days*86400;
        $seconds_left = $end-time();
    }

    return [
        "from"  => date("m-d", $s),
        "to"    => date("m-d", $end),
        "left"  => floor($seconds_left/86400),
        "hours" => floor(($seconds_left%86400)/3600),
        "mins"  => floor(($seconds_left%3600)/60),
        "status"=> ($seconds_left<=0 ? "expired" : ((floor($seconds_left/86400)<=2) ? "warning" : "normal"))
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
<div class="alert success">✔ 保存成功 / Saved (已保留至 GitHub)</div>
<?php endif; ?>

<?php if ($VIEW_ONLY): ?>
<div class="grid">
<?php foreach ($foods as $i=>$f): $c=get_cycle($f["start_date"],$f["cycle_days"],$f["auto_renew"]??false); ?>
    <div class="card <?= $c['status'] ?>" data-category="<?= $f['category'] ?>">
        <div class="name"><b><?= htmlspecialchars($f["name"]) ?></b> / <span class="en"><?= htmlspecialchars($f["name_en"]) ?></span></div>
        <div class="date"><b><?= $c["from"] ?> ~ <?= $c["to"] ?></b></div>
        <div class="left">Remaining: <b><?= $c["left"] ?> Days <?= $c["hours"] ?> H <?= $c["mins"] ?> Min</b></div>
        <div class="renew"><?= ($f['auto_renew']??false)?'🔄 Auto-Renew ON':'⏸ Auto-Renew OFF' ?></div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- 二维码登录 / Admin -->
<?php if (!$VIEW_ONLY && !isset($_SESSION['food_admin'])): ?>
<div class="login-box">
    <h2>🔐 登录后台 / Admin Login</h2>
    <form method="post"><input name="login_password" type="password" placeholder="密码 Password: 888"><button>Login</button></form>
    <p>📱 扫码登录 / Scan to Login</p>
    <div id="qr-login"></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById("qr-login"), {
    text: "https://<?= $_SERVER['HTTP_HOST'] ?>/?admin=1",
    width: 180, height: 180
});
</script>
<?php endif; ?>

<?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
<div class="admin-box">
    <h2>📌 Admin Panel</h2>
    <a href="?view=1" class="btn-link">🔍 展示模式 / View Mode</a>
    <a href="?logout=1" class="btn-logout">Logout</a>
    <hr>

    <form method="post">
        <input type="hidden" name="action" value="add">
        <input name="name" required placeholder="中文 Chinese Name">
        <input name="name_en" placeholder="英文 English Name">
        <select name="category">
            <option value="meat">🥩 肉 Meat</option>
            <option value="vegetable">🥬 Veg</option>
            <option value="seafood">🐟 Seafood</option>
            <option value="dairy">🥛 Dairy</option>
        </select>
        <input name="image_url" placeholder="图片 URL">
        <input name="start_date" type="date">
        <input name="cycle_days" type="number" placeholder="天数 Days">
        <button>保存 / Save</button>
    </form>

    <?php foreach($foods as $i=>$f): ?>
    <form method="post">
        <input type="hidden" name="index" value="<?= $i ?>">
        <button name="action" value="toggle_renew">
            <?= ($f['auto_renew']??false)?'🟢 Auto-Renew ON':'🔴 Auto-Renew OFF' ?>
        </button>
        <button name="action" value="delete">❌ Delete</button>
    </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</body>
</html>
