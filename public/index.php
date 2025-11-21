<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ========== 🔑 GitHub 设置 ========== */
define("GITHUB_TOKEN", "github_pat_XXX");  //⚠ 改成你自己的
define("GITHUB_USER",  "treysiz");
define("GITHUB_REPO",  "food");
define("GITHUB_FILE",  "public/foods.json");
define("GITHUB_RAW",   "https://raw.githubusercontent.com/".GITHUB_USER."/".GITHUB_REPO."/main/".GITHUB_FILE);

/* ========== 📂 JSON 本地储存 ========== */
$write_dir = "/opt/render/project/.data/";
if (!is_dir($write_dir)) { $write_dir = __DIR__ . "/"; }
define("JSON_FILE", $write_dir . "foods.json");

if (!file_exists(JSON_FILE) || filesize(JSON_FILE) < 5) {
    $remote = @file_get_contents(GITHUB_RAW);
    file_put_contents(JSON_FILE, $remote ?: "[]");
}
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

/* ========== 🔐 登录处理 ========== */
$PASSWORD = "888";
$VIEW_ONLY = isset($_GET['view']);
if (!$VIEW_ONLY && isset($_GET['admin']) && $_GET['admin']=="1") $_SESSION['food_admin']=true;
if (!$VIEW_ONLY && isset($_POST['login_password']) && $_POST['login_password']===$PASSWORD) $_SESSION['food_admin']=true;
if (!$VIEW_ONLY && isset($_GET['logout'])) { unset($_SESSION['food_admin']); header("Location:index.php"); exit; }

/* ========== 💾 保存食材 + GitHub 上传 ========== */
function save_and_push($foods){
    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $url = "https://api.github.com/repos/".GITHUB_USER."/".GITHUB_REPO."/contents/".GITHUB_FILE;
    $data = ["message" => "Update foods.json",
             "content" => base64_encode(json_encode($foods, JSON_UNESCAPED_UNICODE))];
    $context = stream_context_create(["http" => [
            "method"=>"PUT","header"=>[
                "User-Agent: PHP","Authorization: token ".GITHUB_TOKEN,
                "Content-Type: application/json"],
            "content"=>json_encode($data)
    ]]);
    @file_get_contents($url, false, $context);
}

/* ========== 🧠 操作处理 ========== */
if (!$VIEW_ONLY && isset($_SESSION['food_admin']) && $_SERVER['REQUEST_METHOD']==='POST') {
    $i = $_POST['index'] ?? null;
    if ($_POST['action']=="add")
        $foods[] = [
            "name"=>$_POST['name'], "name_en"=>$_POST['name_en'] ?? "",
            "category"=>$_POST['category'], "image_url"=>$_POST['image_url'] ?? "",
            "start_date"=>$_POST['start_date'], "cycle_days"=>intval($_POST['cycle_days']),
            "auto_renew"=>false
        ];
    if ($_POST['action']=="toggle_renew") $foods[$i]['auto_renew'] = !($foods[$i]['auto_renew'] ?? false);
    if ($_POST['action']=="delete") { unset($foods[$i]); $foods = array_values($foods); }

    save_and_push($foods);
    header("Location:index.php?saved=1");
    exit;
}

/* ========== ⏳ 计算周期 ========== */
function get_cycle($start,$days,$renew=false){
    if (!$start || $days<=0) return ["from"=>"–","to"=>"–","left"=>0,"hours"=>0,"mins"=>0,"status"=>"normal"];
    $s=strtotime($start); $end=$s+$days*86400;
    $remain=max(0,$end-time());
    if ($remain<=0 && $renew){ $s=strtotime(date("Y-m-d")); $end=$s+$days*86400; $remain=$end-time(); }

    $status = ($remain<=0 ? "expired" : (($remain/86400<=2) ? "warning" : "normal")); 
    return [
        "from"=>date("m-d",$s), "to"=>date("m-d",$end), 
        "left"=>floor($remain/86400),
        "hours"=>floor(($remain%86400)/3600),
        "mins"=>floor(($remain%3600)/60),
        "status"=>$status   // 🆕 加状态（决定颜色）
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Kitchen System V6.3</title>
<link rel="stylesheet" href="assets/style.css">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if ($VIEW_ONLY): ?> 
<meta http-equiv="refresh" content="60">   <!-- 🆕 自动刷新 -->
<?php endif; ?>
</head>

<body>
<div class="header"><h1>🍽 Kitchen Inventory System</h1></div>
<?php if (isset($_GET['saved'])): ?><div class="alert success">✔ Data Saved!</div><?php endif; ?>

<!-- VIEW MODE -->
<?php if ($VIEW_ONLY): ?>
<input type="text" id="search" placeholder="🔍 Search / 搜索">
<script>
document.getElementById("search").onkeyup=function(){
  let kw = this.value.toLowerCase();
  document.querySelectorAll('.card').forEach(c=>{
    c.style.display = c.innerText.toLowerCase().includes(kw) ? 'block' : 'none';
  });
};
</script>

<div class="grid">
<?php foreach($foods as $f): $c = get_cycle($f["start_date"],$f["cycle_days"],$f["auto_renew"]??false); ?>
    <div class="card <?= $c['status'] ?>">   <!-- 🆕 加颜色 -->
        <div class="name"><?= $f["name"] ?> / <span class="en"><?= $f["name_en"] ?></span></div>
        <div><?= $c["from"] ?> ~ <?= $c["to"] ?></div>
        <div><?= $c["left"] ?> Days <?= $c["hours"] ?> H <?= $c["mins"] ?> Min</div>
        <div class="renew"><?= $f['auto_renew']?'🔄 ON':'⏸ OFF' ?></div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- LOGIN -->
<?php if (!$VIEW_ONLY && !isset($_SESSION['food_admin'])): ?>
<div class="login-box">
    <h2>🔐 Admin Login</h2>
    <form method="post"><input name="login_password" type="password" placeholder="Password: 888"><button>Login</button></form>

    <p>📱 手机扫码登入</p>
    <div id="qr-login"></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById("qr-login"),{
    text:"http://<?= $_SERVER['SERVER_ADDR'] ?>/index.php?admin=1",
    width:180,height:180
});
</script>
<?php endif; ?>

<!-- ADMIN -->
<?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
<div class="admin-box">
    <a href="?view=1" class="btn-link">👁 View</a>
    <a href="?logout=1" class="btn-logout">Logout</a>
    <hr>

    <form method="post">
        <input type="hidden" name="action" value="add">
        <input name="name" placeholder="中文">
        <input name="name_en" placeholder="English">
        <input name="start_date" type="date">
        <input name="cycle_days" type="number" placeholder="Days">
        <button>Save</button>
    </form>

    <?php foreach($foods as $i=>$f): ?>
    <form method="post">
        <input type="hidden" name="index" value="<?= $i ?>">
        <button name="action" value="toggle_renew"><?= $f['auto_renew']?'🟢 ON':'🔴 OFF' ?></button>
        <button name="action" value="delete" style="background:#f44336;">🗑 Delete</button>
    </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</body>
</html>
