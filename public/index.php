<?php
// ---------- 显示错误（调试白屏用） ----------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// 自动判断正确路径（兼容 Render）
define("BASE_DIR", realpath(__DIR__));
define("JSON_FILE", BASE_DIR . "/foods.json");

// 数据文件不存在就创建
if (!file_exists(JSON_FILE)) {
    file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}

// 读取数据
$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];

// 密码设置
$PASSWORD = "888";
$VIEW_ONLY = isset($_GET['view']);

if (isset($_POST['login_password']) && $_POST['login_password'] === $PASSWORD) {
    $_SESSION['food_admin'] = true;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['food_admin']);
    header("Location: index.php");
    exit;
}

// 保存数据
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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>食物周期显示系统</title>
</head>
<body>
<h1>🎉 系统成功运行！</h1>
<p>如果你看到这个，Render 已经部署成功！</p>
<p>当前数据文件位置：<code><?= JSON_FILE ?></code></p>
<?php if(empty($foods)): ?>
    <p>当前无数据，请先添加！</p>
<?php endif; ?>
</body>
</html>
