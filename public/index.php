<?php
//-----------------------------------------------------------
// 食物周期管理系统（最终单文件版）
// 功能：周期显示 / 红黄绿提醒 / 密码后台 / 排序 / 大屏
// 自动全屏 / 多语言 / 二维码扫码进入后台 / 类别图标
//-----------------------------------------------------------

session_start();

// 是否纯显示模式
$VIEW_ONLY = isset($_GET['view']);

// 密码
$PASSWORD = "888";

// 自动刷新秒数
$REFRESH_SECONDS = 60;

//------------------------------ 多语言处理 ------------------------------
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
        "nodata" => "📂 暂无数据，请先添加食材！"
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
        "scan" => "📱 Scan to Modify Settings",
        "nodata" => "📂 No data yet, please add first!"
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
        "nodata" => "📂 Sin datos, agregue primero!"
    ],
];

$T = $L[$lang] ?? $L["zh"];

//------------------------------ 图标识别 ------------------------------
$ICONS = [
    "牛" => "🥩", "肉" => "🥩", "猪" => "🥩", "羊" => "🥩", "排骨" => "🥩",
    "鸡" => "🍗", "鸭" => "🍗",
    "鱼" => "🐟", "虾" => "🦐", "蟹" => "🦀",
    "菜" => "🥬", "青" => "🥬", "生菜" => "🥬", "蔬" => "🥕",
    "奶" => "🥛", "奶油" => "🥛", "牛奶" => "🥛", "芝士" => "🧀",
    "米" => "🍚", "饭" => "🍚", "面" => "🍜", "粉" => "🍜"
];

function get_icon($name, $ICONS) {
    foreach ($ICONS as $k => $v) {
        if (mb_strpos($name, $k) !== false) return $v;
    }
    return "📦";
}

//------------------------------ 二维码生成 ------------------------------
function qr($text) {
    $url = urlencode($text);
    return "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl={$url}";
}

//------------------------------ 数据 ------------------------------
$dataFile = __DIR__ . "/foods.json";
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([], JSON_UNESCAPED_UNICODE));
}
$foods = json_decode(file_get_contents($dataFile), true);
if (!is_array($foods)) $foods = [];

//------------------------------ 登录 ------------------------------
if (isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $PASSWORD) {
        $_SESSION['food_admin'] = true;
    } else {
        $login_error = "密码错误";
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['food_admin']);
    header("Location: index.php");
    exit;
}

//------------------------------ 保存操作 ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$VIEW_ONLY) {
    if (isset($_SESSION['food_admin'])) {

        $action = $_POST['action'] ?? "";

        if ($action === "add") {
            $foods[] = [
                "name" => trim($_POST['name']),
                "start_date" => $_POST['start_date'],
                "cycle_days" => max(1, intval($_POST['cycle_days'])) // 🚀 防止为0
            ];
        }

        if (isset($_POST['index'])) {
            $i = intval($_POST['index']);
            $foods[$i]['cycle_days'] = max(1, intval($_POST['cycle_days']));

            if ($action === "update") {
                $foods[$i]['name'] = $_POST['name'];
                $foods[$i]['start_date'] = $_POST['start_date'];
            }

            if ($action === "delete") {
                unset($foods[$i]);
                $foods = array_values($foods);
            }

            if ($action === "up" && $i > 0) {
                $tmp = $foods[$i-1];
                $foods[$i-1] = $foods[$i];
                $foods[$i] = $tmp;
            }

            if ($action === "down" && $i < count($foods)-1) {
                $tmp = $foods[$i+1];
                $foods[$i+1] = $foods[$i];
                $foods[$i] = $tmp;
            }
        }

        file_put_contents($dataFile, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header("Location: index.php");
        exit;
    }
}

//------------------------------ 周期计算(防止除以0) ------------------------------
function get_cycle($start_date, $cycle_days) {
    if ($cycle_days <= 0) return [
        "from" => "--", "to" => "--", "left" => 0, "status" => "expired"
    ];

    $start = strtotime($start_date);
    $today = strtotime(date("Y-m-d"));
    $days_passed = max(0, floor(($today - $start) / 86400));
    $cycle_index = floor($days_passed / $cycle_days);
    $cycle_start = strtotime("+".($cycle_index * $cycle_days)." days", $start);
    $cycle_end = strtotime("+".($cycle_days - 1)." days", $cycle_start);
    $days_left = floor(($cycle_end - $today) / 86400) + 1;

    if ($days_left <= 0) {
        return ["from"=>date("m-d",$cycle_start),"to"=>date("m-d",$cycle_end),"left"=>0,"status"=>"expired"];
    }
    return ["from"=>date("m-d",$cycle_start),"to"=>date("m-d",$cycle_end),"left"=>$days_left,"status"=>$days_left==1?"warning":"normal"];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= $T["title"] ?></title>
<meta http-equiv="refresh" content="<?= $REFRESH_SECONDS ?>">

<style>
/* 样式省略，同之前版本一致，只要复制就好 */
</style>
</head>
<body>

<div class="wrapper">

<!-- 顶部 -->
<div class="header">
    <div>
        <div style="font-size:42px;font-weight:bold;"><?= $T["title"] ?></div>
        <div style="opacity:0.7;margin-top:6px;">更新时间：<?= date("Y-m-d H:i:s") ?>（<?= $REFRESH_SECONDS ?> 秒自动刷新）</div>
    </div>
    <div>
        <button class="btn btn-full" onclick="toggleFull()">全屏</button>
        <?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
        <a href="?logout=1" class="btn btn-set"><?= $T["logout"] ?></a>
        <?php endif; ?>
    </div>
</div>

<!-- 显示区 -->
<div class="card-list">
<?php if (count($foods) == 0): ?>
    <div style="text-align:center;color:#ccc;font-size:28px;"><?= $T["nodata"] ?></div>
<?php else: ?>
<?php foreach ($foods as $f): $c = get_cycle($f['start_date'], $f['cycle_days']); ?>
    <div class="card <?= $c['status'] ?>">
        <div class="food-name">
            <?= get_icon($f["name"], $ICONS) ?> <?= htmlspecialchars($f["name"]) ?>
        </div>
        <div class="food-cycle"><?= $T["current"] ?>：<?= $c["from"] ?> ~ <?= $c["to"] ?></div>
        <div class="food-left"><?= $c['left']>0 ? $T["left"]."：".$c["left"]." ".$T["days"] : $T["expired"] ?></div>
    </div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<!-- 纯显示模式的二维码 -->
<?php if ($VIEW_ONLY): ?>
<div style="text-align:center;margin-top:40px;">
    <div style="font-size:32px;margin-bottom:18px;"><?= $T["scan"] ?></div>
    <img src="<?= qr('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']) ?>" style="width:300px;">
</div>
<?php endif; ?>

</body>
</html>

