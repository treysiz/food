<?php
// 测试写入权限
$testFile = __DIR__ . "/foods.json";   // 你现有的 JSON 路径

$data = [
    "test" => "ok",
    "time" => date("Y-m-d H:i:s")
];

$result = file_put_contents($testFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($result === false) {
    echo "<h2 style='color:red'>❌ 写入失败：Render 服务器没有写入权限</h2>";
} else {
    echo "<h2 style='color:green'>✔ 写入成功！foods.json 已更新</h2>";
}

echo "<pre>";
echo "写入路径：$testFile\n";
echo "写入返回值：$result\n";
echo "</pre>";
