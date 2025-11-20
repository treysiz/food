<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ==============================
   🔒 Render 永久数据存储目录
   ============================== */
$write_dir = "/opt/render/project/.data/";
if (!is_dir($write_dir)) $write_dir = __DIR__ . "/";
define("JSON_FILE", $write_dir . "foods.json");

$PASSWORD = "888";
$VIEW_ONLY = isset($_GET['view']);
$REFRESH_SEC = 60;

/* ==============================
   🧪 JSON INIT
   ============================== */
if (!file_exists(JSON_FILE)) {
    file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];


/* ==============================
   🔐 LOGIN HANDLER
   ============================== */
if (!$VIEW_ONLY && isset($_GET['admin']) && $_GET['admin']=="1") {
    $_SESSION['food_admin'] = true;
}
if (!$VIEW_ONLY && isset($_POST['login_password']) && $_POST['login_password']===$PASSWORD) {
    $_SESSION['food_admin'] = true;
}
if (!$VIEW_ONLY && isset($_GET['logout'])) {
    unset($_SESSION['food_admin']);
    header("Location: index.php");
    exit;
}


/* ==============================
   💾 SAVE / DELETE / RENEW
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
            "created_at" => date("Y-m-d H:i:s")  // ⭐ 新增：记录添加时间
        ];
    }

    if ($action === "delete") {
        unset($foods[intval($_POST['index'])]);
        $foods = array_values($foods);
    }

    // ⭐ 自动续期
    if ($action === "renew") {
        $idx = intval($_POST['index']);
        if (isset($foods[$idx])) {
            $foods[$idx]['start_date'] = date("Y-m-d");
        }
    }

    // 写入 JSON
    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: index.php?saved=1");
    exit;
}


/* ==============================
   📆 计算周期
   ============================== */
function get_cycle($start_date, $cycle_days) {
    if (!$start_date || intval($cycle_days) <= 0) {
        return ["from"=>"-", "to"=>"-", "left"=>9999, "status"=>"normal"]; // 永不过期
    }
    $s = strtotime($start_date);
    $left = intval((($s + $cycle_days * 86400) - time()) / 86400);
    if ($left < 0) $left = 0;  // ⭐ 自动续期不会影响显示
    $status = ($left == 0) ? "expired" : (($left <= 2) ? "warning" : "normal");
    return [
        "from"   => date("m-d", $s),
        "to"     => date("m-d", $s + $cycle_days * 86400),
        "left"   => $left,
        "status" => $status
    ];
}

/* ⭐ 排序：已过期 → 快过期 → 正常 */
usort($foods, function($a, $b){
    $c1 = get_cycle($a['start_date'], $a['cycle_days'])['left'];
    $c2 = get_cycle($b['start_date'], $b['cycle_days'])['left'];
    return $c1 <=> $c2;
});

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>厨房食材管理系统 Kitchen Inventory System</title>
<link rel="stylesheet" href="assets/style.css">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if ($VIEW_ONLY): ?>
<meta http-equiv="refresh" content="<?= $REFRESH_SEC ?>">
<?php endif; ?>
</head>
<body>

<!-- 顶部 -->
<div class="header">
  <h1>🍽 厨房食材管理系统 <span class="en">Kitchen Inventory System</span></h1>
  <div class="time">更新时间 / Updated：<?= date("Y-m-d H:i:s") ?></div> <!-- 精确时间 -->
</div>


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


<!-- 后台登录 -->
<?php if (!$VIEW_ONLY && !isset($_SESSION['food_admin'])): ?>
<div class="login-box">
  <h2>🔒 后台登录 / Admin Login</h2>
  <form method="post"><input name="login_password" type="password"><button>登录 Login</button></form>
</div>
<?php endif; ?>


<!-- 后台管理 -->
<?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
<div class="admin-box">
  <h2>📌 后台管理</h2>
  <a href="?view=1" class="btn-link">切换展示模式</a>
  <a href="?logout=1" class="btn-logout">退出登录</a><hr>

  <h2>➕ 添加食材</h2>
  <form method="post">
    <input type="hidden" name="action" value="add">
    <input name="name" required placeholder="中文名称">
    <input name="name_en" placeholder="英文名称">
    <select name="category">
      <option value="meat">肉类 Meat</option>
      <option value="vegetable">蔬菜 Vegetable</option>
      <option value="seafood">海鲜 Seafood</option>
      <option value="dairy">奶制品 Dairy</option>
    </select>
    <input name="image_url" placeholder="图片 URL">
    <input name="start_date" type="date" required>
    <input name="cycle_days" type="number" placeholder="天数">
    <button>保存 Save</button>
  </form>

  <h2>📋 当前食材</h2>
  <?php foreach ($foods as $i=>$f): ?>
  <form method="post" style="display:flex;gap:8px;">
     <?= htmlspecialchars($f["name"]) ?>（开始:<?= $f["start_date"] ?>）
     <input type="hidden" name="index" value="<?= $i ?>">
     <button name="action" value="renew">续期 / Renew</button>
     <button name="action" value="delete">删除 / Delete</button>
  </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>


<script>
function filterCategory(c){
  document.querySelectorAll('.card').forEach(el=>{
    el.style.display = (c=='all'||el.dataset.category==c)?'block':'none';
  });
}
</script>

</body>
</html>
