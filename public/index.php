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
    header("Location: food.php");
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
        header("Location: food.php");
        exit;
    }
}

//------------------------------ 周期计算 ------------------------------
function get_cycle($start_date, $cycle_days) {
    $start = strtotime($start_date);
    $today = strtotime(date("Y-m-d"));

    $days_passed = max(0, floor(($today - $start) / 86400));

    $cycle_index = floor($days_passed / $cycle_days);

    $cycle_start = strtotime("+".($cycle_index * $cycle_days)." days", $start);
    $cycle_end = strtotime("+".($cycle_days - 1)." days", $cycle_start);

    $days_left = floor(($cycle_end - $today) / 86400) + 1;

    if ($days_left <= 0) {
        $status = "expired";
        $days_left = 0;
    } elseif ($days_left == 1) {
        $status = "warning";
    } else {
        $status = "normal";
    }

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
<title><?= $T["title"] ?></title>
<meta http-equiv="refresh" content="<?= $REFRESH_SECONDS ?>">

<style>
body{
    background:#111;
    color:#fff;
    margin:0;padding:0;
    font-family:Arial,"Microsoft YaHei";
}

/* 大电视卡片样式（55 寸） */
.card{
    padding:28px;
    border-radius:20px;
    background:#1c1c1c;
    box-shadow:0 5px 15px rgba(0,0,0,0.5);
    font-size:32px;
}
.food-name{
    font-size:48px;
    font-weight:bold;
    display:flex;
    align-items:center;
    gap:16px;
}
.food-cycle,.food-left{
    font-size:38px;
    margin-top:12px;
}

.card.normal{ border-left:12px solid #34c759; }
.card.warning{ border-left:12px solid #ffcc00; background:#3a3100; }
.card.expired{ border-left:12px solid #ff3b30; background:#3a0000; }

.wrapper{max-width:1200px;margin:0 auto;padding:20px;}

.header{display:flex;justify-content:space-between;align-items:center;}
.btn{padding:12px 18px;border:none;border-radius:8px;color:#fff;cursor:pointer;font-size:20px;}
.btn-full{background:#007aff;}
.btn-set{background:#444;}

.card-list{
    margin-top:22px;
    display:grid;
    gap:22px;
    grid-template-columns:repeat(auto-fit,minmax(350px,1fr));
}

.box{
    background:#161616;
    padding:20px;
    border-radius:16px;
    margin-top:30px;
}
.row{display:flex;gap:12px;margin-top:12px;}
.row div{flex:1;}
input{
    padding:12px;
    border-radius:6px;
    border:1px solid #555;
    background:#222;
    color:#fff;
    font-size:18px;
    width:100%;
}

.table td{
    padding:12px;
    border-bottom:1px solid #333;
}

.btn-sm{
    padding:8px 12px;
    border-radius:6px;
    font-size:16px;
    margin-right:6px;
}
.btn-save{background:#007aff;}
.btn-del{background:#ff3b30;}
.btn-up,.btn-down{background:#777;}

.error{color:#ff3b30;margin-top:10px;font-size:18px;}
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
<?php foreach ($foods as $f):
    $c = get_cycle($f['start_date'], $f['cycle_days']);
?>
    <div class="card <?= $c['status'] ?>">
        <div class="food-name">
            <?= get_icon($f["name"], $ICONS) ?>
            <?= htmlspecialchars($f["name"]) ?>
        </div>

        <div class="food-cycle">
            <?= $T["current"] ?>：<?= $c["from"] ?> ~ <?= $c["to"] ?>
        </div>

        <div class="food-left">
            <?php if ($c['left'] > 0): ?>
                <?= $T["left"] ?>：<?= $c["left"] ?> <?= $T["days"] ?>
            <?php else: ?>
                <?= $T["expired"] ?>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>


<!-- 纯显示模式要显示二维码 -->
<?php if ($VIEW_ONLY): ?>
<div style="text-align:center;margin-top:40px;">
    <div style="font-size:32px;margin-bottom:18px;"><?= $T["scan"] ?></div>
    <img src="<?= qr('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']) ?>" style="width:300px;">
</div>
<?php endif; ?>


<!-- 设置区（非 view 模式） -->
<?php if (!$VIEW_ONLY): ?>
<div class="box">
    <h2><?= $T["settings"] ?></h2>

<?php if (!isset($_SESSION['food_admin'])): ?>

    <form method="post">
        <div class="row">
            <div><input type="password" name="login_password" placeholder="<?= $T["enter_pwd"] ?>"></div>
            <div style="flex:0 0 auto;"><button class="btn btn-save"><?= $T["enter_pwd"] ?></button></div>
        </div>
    </form>

    <?php if (!empty($login_error)): ?>
        <div class="error"><?= $login_error ?></div>
    <?php endif; ?>

<?php else: ?>

    <!-- 添加 -->
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="row">
            <div><input name="name" placeholder="名称"></div>
            <div><input type="date" name="start_date"></div>
            <div><input type="number" name="cycle_days" placeholder="天数"></div>
            <div style="flex:0 0 auto;">
                <button class="btn btn-save"><?= $T["add"] ?></button>
            </div>
        </div>
    </form>

    <!-- 列表 -->
    <table class="table">
    <?php foreach ($foods as $i => $f): ?>
        <tr>
            <td colspan="4">
                <form method="post" style="display:flex;gap:10px;align-items:center;">
                    <input type="hidden" name="index" value="<?= $i ?>">
                    <input name="name" value="<?= $f['name'] ?>">
                    <input type="date" name="start_date" value="<?= $f['start_date'] ?>">
                    <input type="number" name="cycle_days" value="<?= $f['cycle_days'] ?>">
                    <button class="btn-sm btn-save" name="action" value="update">保存</button>
                    <button class="btn-sm btn-up" name="action" value="up">↑</button>
                    <button class="btn-sm btn-down" name="action" value="down">↓</button>
                    <button class="btn-sm btn-del" name="action" value="delete" onclick="return confirm('删除？');">删</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </table>

<?php endif; ?>
</div>
<?php endif; ?>

</div>

<script>
// 自动全屏
document.addEventListener("DOMContentLoaded", () => {
    setTimeout(() => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(()=>{});
        }
    }, 500);
});

// 手动全屏切换
function toggleFull(){
    if (!document.fullscreenElement){
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}
</script>

</body>
</html>

