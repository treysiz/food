<?php
//-----------------------------------------------------------
// 食物周期管理系统（Render 修复版）
// 功能：周期显示 / 红黄绿提醒 / 密码后台 / 排序 / 大屏
// 多语言 + 无需 phpqrcode 服务器兼容 + TV 全屏
//-----------------------------------------------------------

session_start();

// 是否纯显示模式 ?view=1
$VIEW_ONLY = isset($_GET['view']);

// 密码
$PASSWORD = "888";

// 自动刷新秒数
$REFRESH_SECONDS = 60;

//------------------------------ 多语言 ------------------------------
$lang = $_GET['lang'] ?? 'zh';

$L = [
    "zh" => [
        "title" => "食物周期显示系统",
        "current" => "当前周期",
        "left" => "剩余",
        "days" => "天",
        "expired" => "已过期",
        "settings" => "设置区（需密码）",
        "enter_pwd" => "请输入密码（默认888）",
        "add" => "添加",
        "logout" => "退出设置",
        "scan" => "📱 扫码进入设置",
        "nodata" => "暂无数据，请先添加食材！",
    ],
    "en" => [
        "title" => "Food Cycle Display System",
        "current" => "Cycle",
        "left" => "Left",
        "days" => "days",
        "expired" => "Expired",
        "settings" => "Settings (Password Required)",
        "enter_pwd" => "Enter Password (default 888)",
        "add" => "Add",
        "logout" => "Logout",
        "scan" => "📱 Scan to Edit",
        "nodata" => "No data yet, please add items!",
    ],
    "es" => [
        "title" => "Sistema de Ciclo de Alimentos",
        "current" => "Ciclo",
        "left" => "Queda",
        "days" => "días",
        "expired" => "Vencido",
        "settings" => "Ajustes (Contraseña)",
        "enter_pwd" => "Ingrese contraseña (888)",
        "add" => "Añadir",
        "logout" => "Salir",
        "scan" => "📱 Escanee para Ajustes",
        "nodata" => "No hay datos, ¡agregue ingredientes!",
    ],
];
$T = $L[$lang] ?? $L["zh"];

//------------------------------ 图标识别 ------------------------------
$ICONS = [
    "牛" => "🥩", "肉" => "🥩", "猪" => "🥩", "羊" => "🥩",
    "鸡" => "🍗", "鸭" => "🍗",
    "鱼" => "🐟", "虾" => "🦐", "蟹" => "🦀",
    "菜" => "🥬", "青" => "🥬", "生菜" => "🥬",
    "奶" => "🥛", "奶油" => "🥛", "奶酪" => "🧀",
    "米" => "🍚", "饭" => "🍚", "面" => "🍜"
];
function get_icon($name, $ICONS) {
    foreach ($ICONS as $k => $v) {
        if (mb_strpos($name, $k) !== false) return $v;
    }
    return "📦";
}

//------------------------------ 二维码 (Render OK) ------------------------------
function qr($path = "/") {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $domain = $_SERVER['HTTP_HOST'] ?? "localhost";
    $url = urlencode("{$protocol}://{$domain}{$path}");
    return "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl={$url}";
}

//------------------------------ 数据 ------------------------------
$dataFile = __DIR__ . "/foods.json";
if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([], JSON_UNESCAPED_UNICODE));
$foods = json_decode(file_get_contents($dataFile), true) ?: [];

//------------------------------ 后台登录 ------------------------------
if (isset($_POST['login_password']) && $_POST['login_password'] === $PASSWORD) {
    $_SESSION['food_admin'] = true;
}
if (isset($_GET['logout'])) {
    unset($_SESSION['food_admin']);
    header("Location: index.php");
    exit;
}

//------------------------------ 保存动作 ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$VIEW_ONLY && isset($_SESSION['food_admin'])) {
    $action = $_POST['action'] ?? "";

    if ($action === "add") {
        $foods[] = [
            "name" => trim($_POST['name']),
            "start_date" => $_POST['start_date'],
            "cycle_days" => intval($_POST['cycle_days'])
        ];
    }

    if (isset($_POST['index'])) {
        $i = intval($_POST['index']);
        if ($action === "update") {
            $foods[$i]['name'] = $_POST['name'];
            $foods[$i]['start_date'] = $_POST['start_date'];
            $foods[$i]['cycle_days'] = intval($_POST['cycle_days']);
        }
        if ($action === "delete") {
            unset($foods[$i]);
            $foods = array_values($foods);
        }
    }

    file_put_contents($dataFile, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: index.php");
    exit;
}

//------------------------------ 计算周期 ------------------------------
function get_cycle($start_date, $cycle_days) {
    $cycle_days = max(1, intval($cycle_days));

    $start = strtotime($start_date);
    $today = strtotime(date("Y-m-d"));
    $days_passed = max(0, floor(($today - $start) / 86400));

    $cycle_index = floor($days_passed / $cycle_days);
    $cycle_start = strtotime("+".($cycle_index * $cycle_days)." days", $start);
    $cycle_end = strtotime("+".($cycle_days - 1)." days", $cycle_start);
    $days_left = floor(($cycle_end - $today) / 86400) + 1;

    $status = ($days_left <= 0) ? "expired" : (($days_left == 1) ? "warning" : "normal");

    return [
        "from" => date("m-d", $cycle_start),
        "to" => date("m-d", $cycle_end),
        "left" => max(0, $days_left),
        "status" => $status
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= $T["title"] ?></title>
<meta http-equiv="refresh" content="<?= $REFRESH_SECONDS ?>">
<style>
body{background:#111;color:#fff;margin:0;font-family:Arial,"Microsoft YaHei";}
.card{padding:28px;border-radius:20px;font-size:32px;background:#1c1c1c;}
.card.normal{border-left:12px solid #34c759;}
.card.warning{border-left:12px solid #ffcc00;background:#3a3100;}
.card.expired{border-left:12px solid #ff3b30;background:#3a0000;}
.food-name{font-size:48px;font-weight:bold;display:flex;gap:16px;}
.wrapper{max-width:1200px;margin:auto;padding:20px;}
.card-list{display:grid;gap:22px;margin-top:22px;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));}
</style>
</head>
<body>

<div class="wrapper">
    <div style="display:flex;justify-content:space-between;">
        <div>
            <h1><?= $T["title"] ?></h1>
            <p>更新时间：<?= date("Y-m-d H:i:s") ?>（<?= $REFRESH_SECONDS ?> 秒自动刷新）</p>
        </div>
        <button onclick="toggleFull()">全屏</button>
    </div>

<?php if(empty($foods)): ?>
    <div style="text-align:center;margin:80px 0;">
        <h2><?= $T["nodata"] ?></h2>
        <?php if($VIEW_ONLY): ?>
            <p><?= $T["scan"] ?></p>
            <img src="<?= qr($_SERVER['PHP_SELF']) ?>" width="200">
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card-list">
    <?php foreach ($foods as $f): $c = get_cycle($f['start_date'], $f['cycle_days']); ?>
        <div class="card <?= $c['status'] ?>">
            <div class="food-name"><?= get_icon($f["name"], $ICONS) ?><?= $f["name"] ?></div>
            <p><?= $T["current"] ?>：<?= $c["from"] ?> ~ <?= $c["to"] ?></p>
            <p><?= $c["left"] > 0 ? $T["left"]."：".$c["left"]." ".$T["days"] : $T["expired"] ?></p>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>

<script>
function toggleFull(){
    if (!document.fullscreenElement) document.documentElement.requestFullscreen();
    else document.exitFullscreen();
}
</script>
</body>
</html>
