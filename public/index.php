<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ==============================
   🔑 V5.6 — GitHub 设置（已填好）
============================== */
define("GITHUB_TOKEN", "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"); // 你的 Token
define("GITHUB_USER",  "treysiz");  
define("GITHUB_REPO",  "food");      
define("GITHUB_FILE",  "public/foods.json");
define("GITHUB_RAW",   "https://raw.githubusercontent.com/".GITHUB_USER."/".GITHUB_REPO."/main/".GITHUB_FILE);

/* ==============================
   📂 JSON 本地存储（Render 推荐）
============================== */
$write_dir = "/opt/render/project/.data/";
if (!is_dir($write_dir)) { $write_dir = __DIR__ . "/"; }
define("JSON_FILE", $write_dir . "foods.json");

/* ==============================
   🔄 🔍 如果 JSON 不存在 → GitHub 恢复
============================== */
if (!file_exists(JSON_FILE) || filesize(JSON_FILE) < 5) {
    $remote = @file_get_contents(GITHUB_RAW);
    file_put_contents(JSON_FILE, $remote ?: "[]");
}
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

/* ==============================
   🔐 登录处理
============================== */
$PASSWORD = "888";
$VIEW_ONLY = isset($_GET['view']);
if (!$VIEW_ONLY && isset($_GET['admin']) && $_GET['admin']=="1") { $_SESSION['food_admin']=true; }
if (!$VIEW_ONLY && isset($_POST['login_password']) && $_POST['login_password']===$PASSWORD) { $_SESSION['food_admin']=true; }
if (!$VIEW_ONLY && isset($_GET['logout'])) { unset($_SESSION['food_admin']); header("Location:index.php"); exit; }


/* ==============================
   💾 保存 + GitHub 上传（含 SHA）
============================== */
function save_and_push($foods){
    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $api = "https://api.github.com/repos/".GITHUB_USER."/".GITHUB_REPO."/contents/".GITHUB_FILE;

    // 🔍 取得当前 SHA
    $sha_res = @file_get_contents($api, false, stream_context_create([
        "http" => [
            "method"=>"GET",
            "header"=>[
                "User-Agent: PHP",
                "Authorization: token ".GITHUB_TOKEN
            ]
        ]
    ]));
    $sha = $sha_res ? json_decode($sha_res, true)['sha'] : null;

    // 🚀 上传内容
    $data = [
        "message"=> "Update foods.json",
        "content"=> base64_encode(json_encode($foods, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)),
        "sha"    => $sha
    ];

    $context = stream_context_create([
        "http" => [
            "method"=>"PUT",
            "header"=>[
                "User-Agent: PHP",
                "Authorization: token ".GITHUB_TOKEN,
                "Content-Type: application/json"
            ],
            "content"=> json_encode($data)
        ]
    ]);

    @file_get_contents($api, false, $context);
}


/* ==============================
   📌 接收后台提交动作
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
        $foods[$i]['auto_renew'] = !($foods[$i]['auto_renew'] ?? false);
    }

    if ($action === "delete") {
        unset($foods[intval($_POST['index'])]);
        $foods = array_values($foods);
    }

    save_and_push($foods);  // 💾 保存 + GitHub 上传
    header("Location:index.php?saved=1");
    exit;
}


/* ==============================
   🕒 周期计算
============================== */
function get_cycle($start_date, $cycle_days, $auto_renew = false) {
    if (!$start_date || intval($cycle_days) <= 0)
        return ["from"=>"–","to"=>"–","left"=>0,"hours"=>0,"mins"=>0];
    
    $s = strtotime($start_date);
    $end = $s + $cycle_days*86400;
    $sec = max(0, $end - time());

    if ($sec <= 0 && $auto_renew) {
        $s = strtotime(date("Y-m-d"));
        $end = $s + $cycle_days*86400;
        $sec = $end - time();
    }

    return [
        "from"=>date("m-d",$s),
        "to"=>date("m-d",$end),
        "left"=>floor($sec/86400),
        "hours"=>floor(($sec%86400)/3600),
        "mins"=>floor(($sec%3600)/60),
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
</head>
<body>

<?php if (isset($_GET['saved'])): ?>
<div class="alert success">✔ 已保存，并上传到 GitHub</div>
<?php endif; ?>


<!-- ========================= 展示模式 ========================= -->
<?php if ($VIEW_ONLY): ?>
<div class="grid">
<?php foreach ($foods as $i=>$f): $c = get_cycle($f["start_date"],$f["cycle_days"],$f["auto_renew"]??false); ?>
    <div class="card" data-category="<?= $f['category'] ?>">
        <div class="name"><b><?= htmlspecialchars($f["name"]) ?></b> / <span class="en"><?= htmlspecialchars($f["name_en"]) ?></span></div>
        <div class="date">Cycle: <b><?= $c["from"] ?> ~ <?= $c["to"] ?></b></div>
        <div class="left">Remaining: <b><?= $c["left"] ?> Days <?= $c["hours"] ?> H <?= $c["mins"] ?> Min</b></div>
        <div class="renew"><?= ($f['auto_renew']??false)?'🔄 Auto-Renew ON':'⏸ Auto-Renew OFF' ?></div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>


<!-- ========================= 二维码登录 ========================= -->
<?php if (!$VIEW_ONLY && !isset($_SESSION['food_admin'])): ?>
<div class="login-box">
    <h2>🔐 Admin Login</h2>
    <form method="post">
        <input name="login_password" type="password" placeholder="Password: 888">
        <button>Login</button>
    </form>
    <p>📱 Scan to Login</p>
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


<!-- ========================= 后台管理 ========================= -->
<?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
<div class="admin-box">
    <h2>📌 Admin Panel</h2>
    <a href="?view=1" class="btn-link">🔍 View Mode</a>
    <a href="?logout=1" class="btn-logout">Logout</a>
    <hr>

    <h2>➕ Add Food</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <input name="name"  required placeholder="中文 Chinese Name">
        <input name="name_en" placeholder="英文 English Name">
        <select name="category">
            <option value="meat">🥩 Meat</option>
            <option value="vegetable">🥬 Veg</option>
            <option value="seafood">🐟 Seafood</option>
            <option value="dairy">🥛 Dairy</option>
        </select>
        <input name="image_url" placeholder="Image URL">
        <input name="start_date" type="date">
        <input name="cycle_days" type="number" placeholder="Days">
        <button>Save</button>
    </form>

    <h2>📋 Current Foods</h2>
    <?php foreach($foods as $i=>$f): ?>
    <form method="post" style="margin-bottom:8px;">
        <input type="hidden" name="index" value="<?= $i ?>">
        <b><?= $i+1 ?>. <?= htmlspecialchars($f["name"]) ?></b>

        <button name="action" value="toggle_renew"
            style="background:<?= ($f['auto_renew']??false)?'#4CAF50':'#777' ?>;color:white;">
            <?= ($f['auto_renew']??false)?'🟢 Auto-Renew ON':'🔴 Auto-Renew OFF' ?>
        </button>

        <button name="action" value="delete" style="background:#f44336;color:white;">❌ Delete</button>
    </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</body>
</html>
