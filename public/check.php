<?php
require "index.php";  // 引入配置

echo "<h2>JSON 测试文件</h2>";
echo "JSON_FILE = <b>" . JSON_FILE . "</b><br><br>";

if (file_exists(JSON_FILE)) {
    echo "✔ 文件存在<br>";
    echo "<hr>";
    echo "<pre>";
    echo htmlspecialchars(file_get_contents(JSON_FILE));
    echo "</pre>";
} else {
    echo "❌ 文件不存在：". JSON_FILE;
}
