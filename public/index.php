<<?php
// ---------- 显示错误（调试白屏用） ----------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// ---------- 自动判断数据文件 ----------
define("BASE_DIR", realpath(__DIR__));
define("JSON_FILE", BASE_DIR . "/foods.json");

if (!file_exists(JSON_FILE)) {
    file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

// ---------- 后台登录 ----------
$PASSWORD = "888";
$VIEW_ONLY = isset($_GET['view']); // view=1 显示模式

if (isset($_POST['login_password']) && $_POST['login_password'] === $PASSWORD) {
    $_SESSION['food_admin'] = true;
}
if (isset($_GET['logout'])) {
    unset($_SESSION['food_admin']);
    header("Location: index.php");
    exit;
}

// ---------- 添加数据 ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['food_admin'])) {
    $action = $_POST['action'] ?? "";
    if ($action === "add") {
        $foods[] = [
            "name" => $_POST['name'],
            "start_date" => $_POST['start_date'],
            "cycle_days" => intval($_POST['cycle_days'])
        ];
    }
    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: index.php");
    exit;
}

// ---------- 周期计算 ----------
function get_cycle($start_date, $cycle_days) {
    $start = strtotime($start_date);
    $today = strtotime(date("Y-m-d"));

    if ($cycle_days <= 0) return ["from" => "-", "to" => "-", "left" => 0, "status" => "expired"];

    $days_passed = max(0, floor(($today - $start) / 86400));
    $cycle_index = floor($days_passed / $cycle_days);
    $cycle_start = strtotime("+".($cycle_index * $cycle_days)." days", $start);
    $cycle_end = strtotime("+".($cycle_days - 1)." days", $cycle_start);
    $days_left = floor(($cycle_end - $today) / 86400) + 1;

    if ($days_left <= 0) $status = "expired";
    elseif ($days_left == 1) $status = "warning";
    else $status = "normal";

    return [
        "from" => date("m-d", $cycle_start),
        "to" => date("m-d", $cycle_end),
        "left" => $days_left,
        "status" => $status
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>食物周期显示系统</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background:#111; color:#fff; font-family:Arial,"Microsoft YaHei"; margin:0; padding:20px; }
        .card { padding:20px; border-radius:10px; background:#1c1c1c; margin-bottom:10px; }
        .normal { border-left:10px solid #34c759; }
        .warning { border-left:10px solid #ffcc00; }
        .expired { border-left:10px solid #ff3b30; }
        input, button { padding:10px; border-radius:5px; }
    </style>
</head>
<body>

<h1>食物周期显示系统</h1>
<p>更新时间：<?= date("Y-m-d H:i:s") ?></p>

<?php if ($VIEW_ONLY): ?>
    <?php
    // 手机扫码进入后台设置
    $qr_link = "http://" . $_SERVER['HTTP_HOST'] . "/?view=0";
    $qr_url  = "https://chart.googleapis.com/chart?chs=280x280&cht=qr&chl=" . urlencode($qr_link);
    ?>
    <?php if(empty($foods)): ?>
        <h2>📁 暂无数据，请先添加食材！</h2>
    <?php endif; ?>

    <div style="text-align:center; margin-top:30px;">
        <h3>📱 扫码进入设置</h3>
        <img src="<?= $qr_url ?>" width="260" alt="QR Code">
    </div>
<?php endif; ?>

<!-- ---------- 食材卡片显示 ---------- -->
<?php foreach ($foods as $f): 
    $c = get_cycle($f['start_date'], $f['cycle_days']); ?>
    <div class="card <?= $c['status'] ?>">
        <h2><?= htmlspecialchars($f['name']) ?></h2>
        <p>周期：<?= $c['from'] ?> ~ <?= $c['to'] ?></p>
        <p>剩余：<?= $c['left'] ?> 天</p>
    </div>
<?php endforeach; ?>

<!-- ---------- 后台设置 ---------- -->
<?php if (!$VIEW_ONLY): ?>
    <hr>
    <h2>设置区（需密码）</h2>

    <?php if (!isset($_SESSION['food_admin'])): ?>
        <form method="post">
            <input type="password" name="login_password" placeholder="请输入密码（默认888）">
            <button>登录</button>
        </form>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <input name="name" placeholder="名称" required>
            <input type="date" name="start_date" required>
            <input type="number" name="cycle_days" placeholder="天数" required>
            <button>添加</button>
        </form>
        <p><a href="?logout=1" style="color:#ff3b30;">退出设置</a></p>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
