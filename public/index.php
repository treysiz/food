<?php
session_start();
ini_set('display_errors', 0);      // 关闭警告信息（建议生产环境必须关闭）
error_reporting(E_ALL);

// ----------------------------------------------
// 基础设置
// ----------------------------------------------
define("JSON_FILE", __DIR__ . "/foods.json");
$PASSWORD = "888";                // 后台密码
$VIEW_ONLY = isset($_GET['view']); // ?view=1 进入展示模式
$REFRESH_SEC = 60;                // 展示屏自动刷新间隔

// ----------------------------------------------
// JSON 初始化
// ----------------------------------------------
if (!file_exists(JSON_FILE)) {
    file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

// ----------------------------------------------
// 登录处理
// ----------------------------------------------
if (!$VIEW_ONLY && isset($_POST['login_password']) && $_POST['login_password'] === $PASSWORD) {
    $_SESSION['food_admin'] = true;
}
if (!$VIEW_ONLY && isset($_GET['logout'])) {
    unset($_SESSION['food_admin']);
    header("Location: index.php");
    exit;
}

// ----------------------------------------------
// 保存食材（后台模式）
// ----------------------------------------------
if (!$VIEW_ONLY && isset($_SESSION['food_admin']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? "";

   if ($action === "add") {
    $foods[] = [
        "name"  => $_POST['name'],
        "name_en" => $_POST['name_en'] ?? "",
        "category" => $_POST['category'] ?? "other",
        "image_url" => $_POST['image_url'] ?? "",
        "start_date" => $_POST['start_date'],
        "cycle_days" => intval($_POST['cycle_days'])
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

// ----------------------------------------------
// 周期计算函数（防止空值报错）
// ----------------------------------------------
function get_cycle($start_date, $cycle_days) {
    if (empty($start_date) || intval($cycle_days) <= 0) {
        return ["from" => "-", "to" => "-", "left" => 0];
    }
    $s = strtotime($start_date);
    $t = strtotime(date("Y-m-d"));
    $remain = max(0, intval(($s + $cycle_days * 86400 - $t) / 86400));
    return [
        "from" => date("m-d", $s),
        "to"   => date("m-d", $s + $cycle_days * 86400),
        "left" => $remain,
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>食物周期显示系统</title>
    <link rel="stylesheet" href="assets/style.css">

    <?php if ($VIEW_ONLY): ?>
        <!-- 展示屏模式：横屏+自动全屏+自动刷新 -->
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="refresh" content="<?= $REFRESH_SEC ?>">
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                document.body.requestFullscreen?.();
            });
        </script>
    <?php endif; ?>
</head>
<body>

<!-- ===========================  标题区  =========================== -->
<div class="header">
    <h1>🍽 食物周期显示系统</h1>
    <div>更新时间：<?= date("Y-m-d H:i:s") ?></div>
</div>

<!-- ===========================   食材卡片   =========================== -->
<div class="grid">
<?php foreach ($foods as $f):
    $c = get_cycle($f["start_date"], $f["cycle_days"]);
    $cls = ($c["left"]==0)? "expired" : (($c["left"]<=2)? "warning" : "normal");
?>
    <div class="card <?= $cls ?>">
        <!-- 🔥 中文 -->
        <div class="name"><?= htmlspecialchars($f["name"]) ?></div>

        <!-- 🔥 英文（如果有英文才显示） -->
        <?php if (!empty($f["name_en"])): ?>
            <div class="name-en"><?= htmlspecialchars($f["name_en"]) ?></div>
        <?php endif; ?>

        <div class="date"><?= $c["from"] ?> ~ <?= $c["to"] ?></div>
        <div class="left"><?= $c["left"] > 0 ? "剩余：{$c["left"]} 天" : "⚠ 已过期" ?></div>
    </div>
<?php endforeach; ?>
</div>


<!-- ===========================   后台管理区   =========================== -->
<!-- ===========================   添加食材（升级版）   =========================== -->
<?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
<div class="admin-box">
    <h2>🔧 添加食材</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">

        <input name="name" placeholder="中文名称" required>
        <input name="name_en" placeholder="英文名称 (可空)">
        
        <select name="category" required>
            <option value="">选择分类</option>
            <option value="meat">🥩 肉类 meat</option>
            <option value="vegetable">🥬 蔬菜 vegetable</option>
            <option value="seafood">🐟 海鲜 seafood</option>
            <option value="dairy">🥛 奶制品 dairy</option>
        </select>

        <input name="image_url" placeholder="图片地址 (可空)">
        <input type="date" name="start_date" required>
        <input type="number" name="cycle_days" placeholder="周期天数" required>

        <button>保存</button>
    </form>
</div>
<?php endif; ?>


            <h2>📋 当前食材</h2>
            <?php foreach ($foods as $i => $f): ?>
                <form method="post" class="row-edit">
                    <?= $i+1 ?>. <?= htmlspecialchars($f["name"]) ?>（<?= $f["start_date"] ?>）
                    <input type="hidden" name="index" value="<?= $i ?>">
                    <button name="action" value="delete" class="btn-danger">删除</button>
                </form>
            <?php endforeach; ?>

            <a href="?logout=1" class="btn-logout">退出设置</a>
        </div>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
