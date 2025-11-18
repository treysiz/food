<?php
//-----------------------------------------------------------
// 食物周期管理系统（最终完整版）
// 功能：显示 + 编辑后台 + 排序 + 删除 + 多语言 + 扫码后台登入
// Render 可直接运行 / JSON 保存 / mobile OK
//-----------------------------------------------------------

// 显示错误（调试用，正式可关）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// JSON 数据文件
define("JSON_FILE", __DIR__ . "/foods.json");

// 初始化文件
if (!file_exists(JSON_FILE)) {
    file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}

$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

// 参数设置
$PASSWORD      = "888";   // 后台密码
$VIEW_ONLY     = isset($_GET['view']);   // 纯电视模式
$LANG          = $_GET['lang'] ?? 'zh';  // 语言切换
$REFRESH_SEC   = 60;                     // 自动刷新时间

// 多语言
$LANG_TEXT = [
    "zh" => ["title" => "食物周期显示系统", "settings" => "设置区（需密码）", "enter_pwd"=>"请输入密码（默认888）", "add"=>"添加","logout"=>"退出","scan"=>"📱 扫码进入设置","name"=>"名称","start"=>"开始日期","days"=>"周期天数","save"=>"保存","del"=>"删除","up"=>"↑","down"=>"↓"],
    "en" => ["title" => "Food Cycle System", "settings" => "Settings (Password Required)", "enter_pwd"=>"Enter Password (default 888)", "add"=>"Add","logout"=>"Logout","scan"=>"📱 Scan for Settings","name"=>"Name","start"=>"Start Date","days"=>"Days","save"=>"Save","del"=>"Delete","up"=>"↑","down"=>"↓"],
    "es" => ["title" => "Sistema de Ciclo de Alimentos", "settings" => "Ajustes (con contraseña)", "enter_pwd"=>"Ingrese contraseña (888)", "add"=>"Añadir","logout"=>"Salir","scan"=>"📱 Escanee para Ajustes","name"=>"Nombre","start"=>"Fecha de inicio","days"=>"Días","save"=>"Guardar","del"=>"Eliminar","up"=>"↑","down"=>"↓"],
];
$T = $LANG_TEXT[$LANG] ?? $LANG_TEXT["zh"];


// 二维码生成（无需 phpqrcode）
function qr($url) {
    return "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($url);
}


// 登录操作
if (isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $PASSWORD) {
        $_SESSION['food_admin'] = true;
    } else {
        $error_msg = "密码错误!";
    }
}

// 退出操作
if (isset($_GET['logout'])) {
    unset($_SESSION['food_admin']);
    header("Location: index.php");
    exit;
}


// 保存 / 编辑 / 删除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['food_admin'])) {
    $action = $_POST['action'] ?? "";

    if ($action === "add") {
        $foods[] = [
            "name"       => $_POST['name'],
            "start_date" => $_POST['start_date'],
            "cycle_days" => intval($_POST['cycle_days'])
        ];
    }

    if ($action === "update") {
        $i = intval($_POST['index']);
        $foods[$i]['name']       = $_POST['name'];
        $foods[$i]['start_date'] = $_POST['start_date'];
        $foods[$i]['cycle_days'] = intval($_POST['cycle_days']);
    }

    if ($action === "delete") {
        $i = intval($_POST['index']);
        unset($foods[$i]);
        $foods = array_values($foods);
    }

    if ($action === "up") {
        $i = intval($_POST['index']);
        if ($i > 0) {
            $tmp = $foods[$i - 1];
            $foods[$i - 1] = $foods[$i];
            $foods[$i] = $tmp;
        }
    }

    if ($action === "down") {
        $i = intval($_POST['index']);
        if ($i < count($foods) - 1) {
            $tmp = $foods[$i + 1];
            $foods[$i + 1] = $foods[$i];
            $foods[$i] = $tmp;
        }
    }

    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: index.php");
    exit;
}


/// ---------- 周期计算函数（彻底修复 Deprecated 错误） ----------
function get_cycle($start_date, $cycle_days) {

    // ❗防止空值导致 strtotime(null) 报错
    if (empty($start_date) || empty($cycle_days) || intval($cycle_days) <= 0) {
        return [
            "from"   => "-",
            "to"     => "-",
            "left"   => 0,
            "status" => "expired"
        ];
    }

    $start  = strtotime($start_date);
    $today  = strtotime(date("Y-m-d"));

    // ❗如果日期格式错误，也自动处理
    if ($start === false) {
        return [
            "from"   => "-",
            "to"     => "-",
            "left"   => 0,
            "status" => "expired"
        ];
    }

    $days_passed = max(0, floor(($today - $start) / 86400));
    $cycle_index = floor($days_passed / $cycle_days);
    $cycle_start = strtotime("+".($cycle_index * $cycle_days)." days", $start);
    $cycle_end   = strtotime("+".($cycle_days - 1)." days", $cycle_start);
    $days_left   = floor(($cycle_end - $today) / 86400) + 1;

    if ($days_left <= 0) {
        $status = "expired";
    } elseif ($days_left == 1) {
        $status = "warning";
    } else {
        $status = "normal";
    }

    return [
        "from"   => date("m-d", $cycle_start),
        "to"     => date("m-d", $cycle_end),
        "left"   => $days_left,
        "status" => $status
    ];
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= $T["title"] ?></title>
<meta http-equiv="refresh" content="<?= $REFRESH_SEC ?>">
<style>
body{ background:#111;color:white;font-family:Arial;margin:0;padding:0; }
.wrapper{ max-width:1300px;margin:auto;padding:20px; }
.card{ padding:25px;border-radius:15px;font-size:28px;margin-bottom:15px;background:#222; }
.card.normal{border-left:12px solid #34c759;}
.card.warning{border-left:12px solid #ffcc00;}
.card.expired{border-left:12px solid #ff3b30;}
input{padding:10px;border-radius:5px;width:100%;background:#333;color:white;}
.btn{padding:8px 14px;border:none;border-radius:6px;background:#007aff;color:white;}
.btn-del{background:#ff3b30;}
</style>
</head>
<body>
<div class="wrapper">

<!-- 标题 -->
<h1><?= $T["title"] ?></h1>
<p>更新时间：<?= date("Y-m-d H:i:s") ?>（<?= $REFRESH_SEC ?>秒刷新）</p>

<!-- 显示模式 -->
<?php foreach($foods as $i=>$f): 
    $cycle = get_cycle($f['start_date'], $f['cycle_days']);
?>
<div class="card <?= $cycle['status'] ?>">
    <b><?= htmlspecialchars($f['name']) ?></b><br>
    <?= $cycle['from'] ?> ~ <?= $cycle['to'] ?><br>
    剩余：<?= $cycle['left'] ?>天
</div>
<?php endforeach; ?>


<!-- 显示模式下，显示二维码 -->
<?php if ($VIEW_ONLY): ?>
<?php 
    $qr_url = qr("http://" . $_SERVER['HTTP_HOST'] . "/index.php?view=0&lang=<?= $LANG ?>");
?>
<hr>
<p style="text-align:center"><?= $T["scan"] ?></p>
<div style="text-align:center"><img src="<?= $qr_url ?>" style="width:240px;"></div>
<?php endif; ?>


<!-- 编辑后台 -->
<?php if(!$VIEW_ONLY): ?>

<h2><?= $T["settings"] ?></h2>

<!-- 未登录 -->
<?php if (!isset($_SESSION['food_admin'])): ?>
<form method="post">
    <input type="password" name="login_password" placeholder="<?= $T["enter_pwd"] ?>">
    <button class="btn">登录</button>
</form>
<?php if(isset($error_msg)) echo "<p style='color:red'>$error_msg</p>"; ?>
<?php else: ?>

<!-- 添加新数据 -->
<form method="post">
    <input type="hidden" name="action" value="add">
    <input name="name" placeholder="<?= $T["name"] ?>">
    <input type="date" name="start_date">
    <input type="number" name="cycle_days" placeholder="<?= $T["days"] ?>">
    <button class="btn"><?= $T["add"] ?></button>
</form>

<!-- 数据编辑 -->
<?php foreach($foods as $i=>$f): ?>
<form method="post">
    <input type="hidden" name="index" value="<?= $i ?>">
    <input name="name" value="<?= $f['name'] ?>">
    <input type="date" name="start_date" value="<?= $f['start_date'] ?>">
    <input type="number" name="cycle_days" value="<?= $f['cycle_days'] ?>">
    <button class="btn" name="action" value="update"><?= $T["save"] ?></button>
    <button class="btn" name="action" value="up"><?= $T["up"] ?></button>
    <button class="btn" name="action" value="down"><?= $T["down"] ?></button>
    <button class="btn btn-del" name="action" value="delete" onclick="return confirm('Delete?')"><?= $T["del"] ?></button>
</form>
<?php endforeach; ?>

<a href="?logout=1" class="btn"><?= $T["logout"] ?></a>

<?php endif; ?>
<?php endif; ?>

</div>
</body>
</html>

