<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 路径处理
define("BASE_DIR", realpath(__DIR__));
define("JSON_FILE", BASE_DIR . "/foods.json");

// 初始化 JSON
if (!file_exists(JSON_FILE)) {
    file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

// 系统设置
$PASSWORD = "888";
$VIEW_ONLY = isset($_GET['view']);

// 登录逻辑
if (isset($_POST['login_password']) && $_POST['login_password'] === $PASSWORD) {
    $_SESSION['food_admin'] = true;
}
if (isset($_GET['logout'])) {
    unset($_SESSION['food_admin']);
    header("Location: index.php");
    exit;
}

// 保存食材
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['food_admin'])) {
    $action = $_POST['action'] ?? "";

    if ($action === "add") {
        $foods[] = [
            "name" => $_POST['name'],
            "start_date" => $_POST['start_date'],
            "cycle_days" => intval($_POST['cycle_days'])
        ];
    }
    if ($action === "delete") {
        $index = intval($_POST['index']);
        unset($foods[$index]);
        $foods = array_values($foods);
    }

    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: index.php");
    exit;
}

// 计算周期函数
function get_cycle($start, $days) {
    $s = strtotime($start);
    $t = strtotime(date("Y-m-d"));
    $remain = max(0, intval(($s + $days*86400 - $t) / 86400));

    return [
        "from" => date("m-d", $s),
        "to" => date("m-d", $s + $days*86400),
        "left" => $remain
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>食物周期显示系统</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="header">
    <h1>🍽 食物周期显示系统</h1>
    <div>更新时间：<?= date("Y-m-d H:i:s") ?></div>
</div>

<div class="grid">
<?php if(empty($foods)): ?>
    <div class="nodata">📂 暂无食材，请先添加</div>
<?php endif; ?>

<?php foreach($foods as $f): 
    $c = get_cycle($f["start_date"], $f["cycle_days"]);
    $cls = ($c["left"]==0)? "expired" : (($c["left"]<=2)? "warning" : "normal");
?>
    <div class="card <?= $cls ?>">
        <div class="name"><?= htmlspecialchars($f["name"]) ?></div>
        <div class="date"><?= $c["from"] ?> ~ <?= $c["to"] ?></div>
        <div class="left"><?= $c["left"] > 0 ? "剩余：{$c["left"]} 天" : "⚠ 已过期" ?></div>
    </div>
<?php endforeach; ?>
</div>

<hr>

<?php if(!isset($_SESSION['food_admin'])): ?>
    <form method="post" class="login-box">
        <input type="password" name="login_password" placeholder="输入密码进入后台">
        <button>进入设置</button>
    </form>
<?php else: ?>

<div class="admin-box">
    <h2>🔧 添加食材</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <input name="name" placeholder="名称" required>
        <input type="date" name="start_date" required>
        <input type="number" name="cycle_days" placeholder="周期天数" required>
        <button>保存</button>
    </form>

    <h2>📋 当前食材</h2>
    <?php foreach($foods as $i=>$f): ?>
        <form method="post" class="row-edit">
            <?= $i+1 ?>. <?= htmlspecialchars($f["name"]) ?>（<?= $f["start_date"] ?>）
            <input type="hidden" name="index" value="<?= $i ?>">
            <button name="action" value="delete" class="btn-danger">删除</button>
        </form>
    <?php endforeach; ?>

    <a href="?logout=1" class="btn-logout">退出设置</a>
</div>
<?php endif; ?>

</body>
</html>
