<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ==================== 配置区 ====================
define("JSON_FILE", __DIR__ . "/data/foods.json");
define("SETTINGS_FILE", __DIR__ . "/data/settings.json");
define("LOG_FILE", __DIR__ . "/data/activity.log");
$PASSWORD = "888";
$VIEW_ONLY = isset($_GET['view']);
$REFRESH_SEC = 60;

// ==================== 初始化 ====================
if (!is_dir(__DIR__ . "/data")) {
    mkdir(__DIR__ . "/data", 0755, true);
}

if (!file_exists(JSON_FILE)) {
    file_put_contents(JSON_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}

if (!file_exists(SETTINGS_FILE)) {
    $default_settings = [
        'warning_days' => 3,
        'expired_days' => 7,
        'auto_archive' => true,
        'notification_enabled' => true
    ];
    file_put_contents(SETTINGS_FILE, json_encode($default_settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$foods = json_decode(file_get_contents(JSON_FILE), true) ?: [];
$settings = json_decode(file_get_contents(SETTINGS_FILE), true) ?: [];

// ==================== 登录逻辑 ====================
if (!$VIEW_ONLY && isset($_POST['login_password']) && $_POST['login_password'] === $PASSWORD) {
    $_SESSION['food_admin'] = true;
    log_activity("管理员登录");
}

if (!$VIEW_ONLY && isset($_GET['logout'])) {
    log_activity("管理员登出");
    unset($_SESSION['food_admin']);
    header("Location: index.php");
    exit;
}

// ==================== POST 处理 ====================
if (!$VIEW_ONLY && isset($_SESSION['food_admin']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? "";

    if ($action === "add") {
        $new_food = [
            "id"          => uniqid('food_'),
            "name"        => trim($_POST['name']),
            "name_en"     => trim($_POST['name_en'] ?? ""),
            "category"    => $_POST['category'] ?? "other",
            "image_url"   => trim($_POST['image_url'] ?? ""),
            "start_date"  => $_POST['start_date'],
            "cycle_days"  => intval($_POST['cycle_days']),
            "location"    => trim($_POST['location'] ?? ""),
            "quantity"    => trim($_POST['quantity'] ?? ""),
            "notes"       => trim($_POST['notes'] ?? ""),
            "created_at"  => date("Y-m-d H:i:s"),
            "archived"    => false
        ];
        $foods[] = $new_food;
        log_activity("添加食材: " . $new_food['name']);
    }

    if ($action === "edit") {
        $id = $_POST['id'];
        foreach ($foods as &$food) {
            if ($food['id'] === $id) {
                $food['name'] = trim($_POST['name']);
                $food['name_en'] = trim($_POST['name_en'] ?? "");
                $food['category'] = $_POST['category'] ?? "other";
                $food['image_url'] = trim($_POST['image_url'] ?? "");
                $food['start_date'] = $_POST['start_date'];
                $food['cycle_days'] = intval($_POST['cycle_days']);
                $food['location'] = trim($_POST['location'] ?? "");
                $food['quantity'] = trim($_POST['quantity'] ?? "");
                $food['notes'] = trim($_POST['notes'] ?? "");
                log_activity("编辑食材: " . $food['name']);
                break;
            }
        }
    }

    if ($action === "delete") {
        $id = $_POST['id'];
        $foods = array_filter($foods, function($f) use ($id) {
            return $f['id'] !== $id;
        });
        $foods = array_values($foods);
        log_activity("删除食材 ID: " . $id);
    }

    if ($action === "archive") {
        $id = $_POST['id'];
        foreach ($foods as &$food) {
            if ($food['id'] === $id) {
                $food['archived'] = true;
                log_activity("归档食材: " . $food['name']);
                break;
            }
        }
    }

    if ($action === "batch_archive") {
        $archived_count = 0;
        foreach ($foods as &$food) {
            $cycle = get_cycle($food["start_date"], $food["cycle_days"], $settings);
            if ($cycle['left'] <= -$settings['expired_days']) {
                $food['archived'] = true;
                $archived_count++;
            }
        }
        log_activity("批量归档 {$archived_count} 个过期食材");
    }

    if ($action === "save_settings") {
        $settings['warning_days'] = intval($_POST['warning_days']);
        $settings['expired_days'] = intval($_POST['expired_days']);
        $settings['auto_archive'] = isset($_POST['auto_archive']);
        $settings['notification_enabled'] = isset($_POST['notification_enabled']);
        file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        log_activity("更新系统设置");
    }

    file_put_contents(JSON_FILE, json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: index.php" . (isset($_GET['tab']) ? "?tab=" . $_GET['tab'] : ""));
    exit;
}

// ==================== 功能函数 ====================
function get_cycle($start_date, $cycle_days, $settings) {
    if (empty($start_date) || intval($cycle_days) <= 0) {
        return ["from" => "-", "to" => "-", "left" => 0, "status" => "normal", "progress" => 0];
    }
    
    $s = strtotime($start_date);
    $end = $s + $cycle_days * 86400;
    $t = strtotime(date("Y-m-d"));
    $left = intval(($end - $t) / 86400);
    
    // 计算进度百分比
    $elapsed = max(0, $t - $s);
    $total = $end - $s;
    $progress = min(100, ($elapsed / $total) * 100);
    
    // 状态判断
    if ($left < 0) {
        $cls = "expired";
    } elseif ($left <= $settings['warning_days']) {
        $cls = "warning";
    } else {
        $cls = "normal";
    }
    
    return [
        "from"     => date("m-d", $s),
        "to"       => date("m-d", $end),
        "left"     => $left,
        "status"   => $cls,
        "progress" => round($progress)
    ];
}

function get_statistics($foods, $settings) {
    $active = array_filter($foods, fn($f) => !($f['archived'] ?? false));
    
    $total = count($active);
    $expired = 0;
    $warning = 0;
    $normal = 0;
    
    $by_category = [];
    
    foreach ($active as $food) {
        $cycle = get_cycle($food["start_date"], $food["cycle_days"], $settings);
        
        if ($cycle['status'] === 'expired') $expired++;
        elseif ($cycle['status'] === 'warning') $warning++;
        else $normal++;
        
        $cat = $food['category'] ?? 'other';
        $by_category[$cat] = ($by_category[$cat] ?? 0) + 1;
    }
    
    return compact('total', 'expired', 'warning', 'normal', 'by_category');
}

function log_activity($message) {
    $log = date("Y-m-d H:i:s") . " - " . $message . PHP_EOL;
    file_put_contents(LOG_FILE, $log, FILE_APPEND);
}

function get_category_info($category) {
    $categories = [
        'meat'      => ['emoji' => '🥩', 'name' => '肉类', 'name_en' => 'Meat', 'color' => '#ff6b6b'],
        'vegetable' => ['emoji' => '🥬', 'name' => '蔬菜', 'name_en' => 'Vegetables', 'color' => '#51cf66'],
        'seafood'   => ['emoji' => '🐟', 'name' => '海鲜', 'name_en' => 'Seafood', 'color' => '#339af0'],
        'dairy'     => ['emoji' => '🥛', 'name' => '奶制品', 'name_en' => 'Dairy', 'color' => '#ffd43b'],
        'fruit'     => ['emoji' => '🍎', 'name' => '水果', 'name_en' => 'Fruits', 'color' => '#ff8787'],
        'grain'     => ['emoji' => '🌾', 'name' => '谷物', 'name_en' => 'Grains', 'color' => '#fab005'],
        'frozen'    => ['emoji' => '🧊', 'name' => '冷冻', 'name_en' => 'Frozen', 'color' => '#91c7ff'],
        'other'     => ['emoji' => '📦', 'name' => '其他', 'name_en' => 'Other', 'color' => '#adb5bd']
    ];
    return $categories[$category] ?? $categories['other'];
}

// ==================== 数据准备 ====================
$active_foods = array_filter($foods, fn($f) => !($f['archived'] ?? false));
$archived_foods = array_filter($foods, fn($f) => ($f['archived'] ?? false));
$stats = get_statistics($foods, $settings);
$current_tab = $_GET['tab'] ?? 'display';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🍽 智能食物周期管理系统 | Food Cycle Manager</title>
<link rel="stylesheet" href="assets/modern-style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if ($VIEW_ONLY): ?>
<meta http-equiv="refresh" content="<?= $REFRESH_SEC ?>">
<script>
document.addEventListener("DOMContentLoaded", () => {
    document.body.requestFullscreen?.();
});
</script>
<?php endif; ?>

<script>
// 确认删除
function confirmDelete(name) {
    return confirm(`确定要删除「${name}」吗？\nAre you sure to delete "${name}"?`);
}

// 编辑模态框
function openEditModal(food) {
    const modal = document.getElementById('editModal');
    document.getElementById('edit_id').value = food.id;
    document.getElementById('edit_name').value = food.name;
    document.getElementById('edit_name_en').value = food.name_en || '';
    document.getElementById('edit_category').value = food.category;
    document.getElementById('edit_image_url').value = food.image_url || '';
    document.getElementById('edit_start_date').value = food.start_date;
    document.getElementById('edit_cycle_days').value = food.cycle_days;
    document.getElementById('edit_location').value = food.location || '';
    document.getElementById('edit_quantity').value = food.quantity || '';
    document.getElementById('edit_notes').value = food.notes || '';
    modal.style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// 批量归档确认
function confirmBatchArchive() {
    return confirm('确定要归档所有过期超过设定天数的食材吗？\n此操作不可撤销！');
}

// 图片预览
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.value) {
        preview.src = input.value;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}
</script>
</head>
<body>

<!-- 导航栏 -->
<nav class="navbar">
    <div class="nav-brand">
        <span class="brand-icon">🍽</span>
        <div class="brand-text">
            <h1>智能食物周期管理系统</h1>
            <p>Food Cycle Management System</p>
        </div>
    </div>
    
    <div class="nav-info">
        <div class="update-time">
            <span class="time-icon">🕐</span>
            <span><?= date("Y-m-d H:i:s") ?></span>
        </div>
        <?php if (!$VIEW_ONLY && isset($_SESSION['food_admin'])): ?>
            <a href="?logout=1" class="btn btn-logout">
                <span>🚪</span> 退出 Logout
            </a>
        <?php endif; ?>
    </div>
</nav>

<?php if ($VIEW_ONLY): ?>
    <!-- ==================== 展示模式 ==================== -->
    <div class="display-mode">
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">总计 Total</div>
            </div>
            <div class="stat-item stat-warning">
                <div class="stat-value"><?= $stats['warning'] ?></div>
                <div class="stat-label">预警 Warning</div>
            </div>
            <div class="stat-item stat-expired">
                <div class="stat-value"><?= $stats['expired'] ?></div>
                <div class="stat-label">过期 Expired</div>
            </div>
            <div class="stat-item stat-normal">
                <div class="stat-value"><?= $stats['normal'] ?></div>
                <div class="stat-label">正常 Normal</div>
            </div>
        </div>

        <div class="food-grid">
            <?php foreach ($active_foods as $f):
                $c = get_cycle($f["start_date"], $f["cycle_days"], $settings);
                $cat_info = get_category_info($f["category"]);
            ?>
                <div class="food-card status-<?= $c['status'] ?>">
                    <div class="card-header">
                        <span class="category-badge" style="background: <?= $cat_info['color'] ?>">
                            <?= $cat_info['emoji'] ?> <?= $cat_info['name'] ?>
                        </span>
                        <?php if (!empty($f["location"])): ?>
                            <span class="location-tag">📍 <?= htmlspecialchars($f["location"]) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($f["image_url"])): ?>
                        <div class="card-image">
                            <img src="<?= htmlspecialchars($f["image_url"]) ?>" alt="<?= htmlspecialchars($f["name"]) ?>">
                        </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <h3 class="food-name"><?= htmlspecialchars($f["name"]) ?></h3>
                        <?php if (!empty($f["name_en"])): ?>
                            <p class="food-name-en"><?= htmlspecialchars($f["name_en"]) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($f["quantity"])): ?>
                            <div class="quantity">数量: <?= htmlspecialchars($f["quantity"]) ?></div>
                        <?php endif; ?>

                        <div class="date-range">
                            <span><?= $c["from"] ?></span>
                            <span class="arrow">→</span>
                            <span><?= $c["to"] ?></span>
                        </div>

                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $c['progress'] ?>%"></div>
                        </div>

                        <div class="days-left">
                            <?php if ($c['left'] >= 0): ?>
                                <span class="icon">⏱</span>
                                剩余 <?= $c['left'] ?> 天 | <?= $c['left'] ?> days left
                            <?php else: ?>
                                <span class="icon">⚠️</span>
                                已过期 <?= abs($c['left']) ?> 天 | Expired <?= abs($c['left']) ?> days
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php else: ?>
    <!-- ==================== 管理模式 ==================== -->
    <?php if (!isset($_SESSION['food_admin'])): ?>
        <!-- 登录页面 -->
        <div class="login-container">
            <div class="login-box">
                <div class="login-header">
                    <span class="login-icon">🔐</span>
                    <h2>管理员登录</h2>
                    <p>Administrator Login</p>
                </div>
                <form method="post" class="login-form">
                    <div class="form-group">
                        <input type="password" 
                               name="login_password" 
                               placeholder="请输入管理密码 / Enter Password" 
                               required
                               autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <span>🚀</span> 登录进入系统
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- 标签导航 -->
        <div class="tabs">
            <a href="?tab=display" class="tab <?= $current_tab === 'display' ? 'active' : '' ?>">
                <span>📊</span> 食材展示
            </a>
            <a href="?tab=manage" class="tab <?= $current_tab === 'manage' ? 'active' : '' ?>">
                <span>⚙️</span> 食材管理
            </a>
            <a href="?tab=statistics" class="tab <?= $current_tab === 'statistics' ? 'active' : '' ?>">
                <span>📈</span> 数据统计
            </a>
            <a href="?tab=settings" class="tab <?= $current_tab === 'settings' ? 'active' : '' ?>">
                <span>🔧</span> 系统设置
            </a>
            <a href="?view=1" class="tab tab-special" target="_blank">
                <span>🖥</span> 全屏展示
            </a>
        </div>

        <div class="container">
            <?php if ($current_tab === 'display'): ?>
                <!-- 食材展示 -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-icon">📦</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $stats['total'] ?></div>
                            <div class="stat-title">总计食材</div>
                        </div>
                    </div>
                    <div class="stat-card card-warning">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $stats['warning'] ?></div>
                            <div class="stat-title">即将过期</div>
                        </div>
                    </div>
                    <div class="stat-card card-expired">
                        <div class="stat-icon">❌</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $stats['expired'] ?></div>
                            <div class="stat-title">已经过期</div>
                        </div>
                    </div>
                    <div class="stat-card card-normal">
                        <div class="stat-icon">✅</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $stats['normal'] ?></div>
                            <div class="stat-title">状态正常</div>
                        </div>
                    </div>
                </div>

                <div class="food-grid">
                    <?php foreach ($active_foods as $f):
                        $c = get_cycle($f["start_date"], $f["cycle_days"], $settings);
                        $cat_info = get_category_info($f["category"]);
                    ?>
                        <div class="food-card status-<?= $c['status'] ?>">
                            <div class="card-header">
                                <span class="category-badge" style="background: <?= $cat_info['color'] ?>">
                                    <?= $cat_info['emoji'] ?> <?= $cat_info['name'] ?>
                                </span>
                                <?php if (!empty($f["location"])): ?>
                                    <span class="location-tag">📍 <?= htmlspecialchars($f["location"]) ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($f["image_url"])): ?>
                                <div class="card-image">
                                    <img src="<?= htmlspecialchars($f["image_url"]) ?>" alt="<?= htmlspecialchars($f["name"]) ?>">
                                </div>
                            <?php endif; ?>

                            <div class="card-body">
                                <h3 class="food-name"><?= htmlspecialchars($f["name"]) ?></h3>
                                <?php if (!empty($f["name_en"])): ?>
                                    <p class="food-name-en"><?= htmlspecialchars($f["name_en"]) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($f["quantity"])): ?>
                                    <div class="quantity">数量: <?= htmlspecialchars($f["quantity"]) ?></div>
                                <?php endif; ?>

                                <div class="date-range">
                                    <span><?= $c["from"] ?></span>
                                    <span class="arrow">→</span>
                                    <span><?= $c["to"] ?></span>
                                </div>

                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $c['progress'] ?>%"></div>
                                </div>

                                <div class="days-left">
                                    <?php if ($c['left'] >= 0): ?>
                                        <span class="icon">⏱</span>
                                        剩余 <?= $c['left'] ?> 天
                                    <?php else: ?>
                                        <span class="icon">⚠️</span>
                                        已过期 <?= abs($c['left']) ?> 天
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($f["notes"])): ?>
                                    <div class="notes">📝 <?= htmlspecialchars($f["notes"]) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="card-actions">
                                <button onclick='openEditModal(<?= json_encode($f) ?>)' class="btn-icon" title="编辑">
                                    ✏️
                                </button>
                                <form method="post" style="display:inline;" onsubmit="return confirmDelete('<?= htmlspecialchars($f["name"]) ?>')">
                                    <input type="hidden" name="action" value="archive">
                                    <input type="hidden" name="id" value="<?= $f["id"] ?>">
                                    <button type="submit" class="btn-icon" title="归档">📥</button>
                                </form>
                                <form method="post" style="display:inline;" onsubmit="return confirmDelete('<?= htmlspecialchars($f["name"]) ?>')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $f["id"] ?>">
                                    <button type="submit" class="btn-icon btn-danger" title="删除">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($current_tab === 'manage'): ?>
                <!-- 食材管理 -->
                <div class="panel">
                    <div class="panel-header">
                        <h2>➕ 添加新食材</h2>
                        <p>Add New Food Item</p>
                    </div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>中文名称 *</label>
                                <input name="name" placeholder="例如：鸡胸肉" required>
                            </div>
                            <div class="form-group">
                                <label>英文名称</label>
                                <input name="name_en" placeholder="e.g., Chicken Breast">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>食材分类 *</label>
                                <select name="category" required>
                                    <option value="">选择分类 Select Category</option>
                                    <option value="meat">🥩 肉类 Meat</option>
                                    <option value="vegetable">🥬 蔬菜 Vegetables</option>
                                    <option value="seafood">🐟 海鲜 Seafood</option>
                                    <option value="dairy">🥛 奶制品 Dairy</option>
                                    <option value="fruit">🍎 水果 Fruits</option>
                                    <option value="grain">🌾 谷物 Grains</option>
                                    <option value="frozen">🧊 冷冻 Frozen</option>
                                    <option value="other">📦 其他 Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>存放位置</label>
                                <input name="location" placeholder="例如：冷冻柜A区">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>开始日期 *</label>
                                <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="form-group">
                                <label>周期天数 *</label>
                                <input type="number" name="cycle_days" placeholder="例如：7" required min="1">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>数量</label>
                                <input name="quantity" placeholder="例如：2kg 或 5盒">
                            </div>
                            <div class="form-group">
                                <label>图片链接</label>
                                <input name="image_url" placeholder="https://example.com/image.jpg" onchange="previewImage(this, 'add_preview')">
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label>备注说明</label>
                            <textarea name="notes" placeholder="其他需要记录的信息..." rows="2"></textarea>
                        </div>

                        <div class="form-group full-width">
                            <img id="add_preview" style="display:none; max-width:200px; border-radius:8px; margin-top:10px;">
                        </div>

                        <button type="submit" class="btn btn-primary btn-large">
                            <span>💾</span> 保存食材 Save
                        </button>
                    </form>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>📋 食材列表</h2>
                        <form method="post" style="display:inline;" onsubmit="return confirmBatchArchive()">
                            <input type="hidden" name="action" value="batch_archive">
                            <button type="submit" class="btn btn-warning">
                                <span>📥</span> 批量归档过期
                            </button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>食材名称</th>
                                    <th>分类</th>
                                    <th>位置</th>
                                    <th>数量</th>
                                    <th>开始日期</th>
                                    <th>周期</th>
                                    <th>剩余天数</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_foods as $f):
                                    $c = get_cycle($f["start_date"], $f["cycle_days"], $settings);
                                    $cat_info = get_category_info($f["category"]);
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($f["name"]) ?></strong>
                                            <?php if (!empty($f["name_en"])): ?>
                                                <br><small><?= htmlspecialchars($f["name_en"]) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="category-badge" style="background: <?= $cat_info['color'] ?>">
                                                <?= $cat_info['emoji'] ?> <?= $cat_info['name'] ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($f["location"] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($f["quantity"] ?? '-') ?></td>
                                        <td><?= $f["start_date"] ?></td>
                                        <td><?= $f["cycle_days"] ?> 天</td>
                                        <td><?= $c['left'] ?> 天</td>
                                        <td>
                                            <span class="status-badge status-<?= $c['status'] ?>">
                                                <?= $c['status'] === 'expired' ? '已过期' : ($c['status'] === 'warning' ? '预警' : '正常') ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <button onclick='openEditModal(<?= json_encode($f) ?>)' class="btn-small">编辑</button>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="archive">
                                                <input type="hidden" name="id" value="<?= $f["id"] ?>">
                                                <button type="submit" class="btn-small btn-warning">归档</button>
                                            </form>
                                            <form method="post" style="display:inline;" onsubmit="return confirmDelete('<?= htmlspecialchars($f["name"]) ?>')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $f["id"] ?>">
                                                <button type="submit" class="btn-small btn-danger">删除</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (count($archived_foods) > 0): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h2>📥 已归档食材</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>食材名称</th>
                                    <th>分类</th>
                                    <th>开始日期</th>
                                    <th>周期</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($archived_foods as $f):
                                    $cat_info = get_category_info($f["category"]);
                                ?>
                                    <tr style="opacity: 0.6;">
                                        <td><?= htmlspecialchars($f["name"]) ?></td>
                                        <td><?= $cat_info['emoji'] ?> <?= $cat_info['name'] ?></td>
                                        <td><?= $f["start_date"] ?></td>
                                        <td><?= $f["cycle_days"] ?> 天</td>
                                        <td>
                                            <form method="post" style="display:inline;" onsubmit="return confirmDelete('<?= htmlspecialchars($f["name"]) ?>')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $f["id"] ?>">
                                                <button type="submit" class="btn-small btn-danger">永久删除</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            <?php elseif ($current_tab === 'statistics'): ?>
                <!-- 数据统计 -->
                <div class="stats-grid">
                    <div class="panel">
                        <div class="panel-header">
                            <h2>📊 分类统计</h2>
                        </div>
                        <canvas id="categoryChart" width="400" height="300"></canvas>
                        <script>
                        const ctx = document.getElementById('categoryChart').getContext('2d');
                        new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: <?= json_encode(array_map(function($cat) {
                                    $info = get_category_info($cat);
                                    return $info['emoji'] . ' ' . $info['name'];
                                }, array_keys($stats['by_category']))) ?>,
                                datasets: [{
                                    data: <?= json_encode(array_values($stats['by_category'])) ?>,
                                    backgroundColor: <?= json_encode(array_map(function($cat) {
                                        return get_category_info($cat)['color'];
                                    }, array_keys($stats['by_category']))) ?>
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    }
                                }
                            }
                        });
                        </script>
                    </div>

                    <div class="panel">
                        <div class="panel-header">
                            <h2>📈 状态分布</h2>
                        </div>
                        <div class="stats-list">
                            <div class="stat-row">
                                <span class="stat-label">✅ 正常状态</span>
                                <span class="stat-value"><?= $stats['normal'] ?></span>
                                <div class="stat-bar">
                                    <div class="stat-bar-fill stat-normal" style="width: <?= $stats['total'] > 0 ? ($stats['normal']/$stats['total']*100) : 0 ?>%"></div>
                                </div>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">⚠️ 预警状态</span>
                                <span class="stat-value"><?= $stats['warning'] ?></span>
                                <div class="stat-bar">
                                    <div class="stat-bar-fill stat-warning" style="width: <?= $stats['total'] > 0 ? ($stats['warning']/$stats['total']*100) : 0 ?>%"></div>
                                </div>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">❌ 已过期</span>
                                <span class="stat-value"><?= $stats['expired'] ?></span>
                                <div class="stat-bar">
                                    <div class="stat-bar-fill stat-expired" style="width: <?= $stats['total'] > 0 ? ($stats['expired']/$stats['total']*100) : 0 ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>📝 操作日志</h2>
                    </div>
                    <div class="log-container">
                        <?php if (file_exists(LOG_FILE)):
                            $logs = array_reverse(array_filter(explode(PHP_EOL, file_get_contents(LOG_FILE))));
                            $recent_logs = array_slice($logs, 0, 20);
                            foreach ($recent_logs as $log): ?>
                                <div class="log-entry"><?= htmlspecialchars($log) ?></div>
                            <?php endforeach;
                        else: ?>
                            <div class="log-entry">暂无操作记录</div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($current_tab === 'settings'): ?>
                <!-- 系统设置 -->
                <div class="panel">
                    <div class="panel-header">
                        <h2>⚙️ 系统设置</h2>
                    </div>
                    <form method="post" class="settings-form">
                        <input type="hidden" name="action" value="save_settings">
                        
                        <div class="form-group">
                            <label>预警天数</label>
                            <input type="number" name="warning_days" value="<?= $settings['warning_days'] ?>" min="1" required>
                            <small>剩余天数小于等于此值时显示预警</small>
                        </div>

                        <div class="form-group">
                            <label>自动归档天数</label>
                            <input type="number" name="expired_days" value="<?= $settings['expired_days'] ?>" min="1" required>
                            <small>过期超过此天数的食材可批量归档</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="auto_archive" <?= $settings['auto_archive'] ? 'checked' : '' ?>>
                                <span>启用自动归档</span>
                            </label>
                            <small>自动归档过期超过设定天数的食材</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="notification_enabled" <?= $settings['notification_enabled'] ? 'checked' : '' ?>>
                                <span>启用通知提醒</span>
                            </label>
                            <small>食材即将过期时发送通知（需要配置通知服务）</small>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <span>💾</span> 保存设置 Save Settings
                        </button>
                    </form>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>📊 系统信息</h2>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">数据文件</div>
                            <div class="info-value"><?= basename(JSON_FILE) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">食材总数</div>
                            <div class="info-value"><?= count($foods) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">活跃食材</div>
                            <div class="info-value"><?= count($active_foods) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">已归档</div>
                            <div class="info-value"><?= count($archived_foods) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">系统版本</div>
                            <div class="info-value">v2.0</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">PHP 版本</div>
                            <div class="info-value"><?= phpversion() ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- 编辑模态框 -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>✏️ 编辑食材</h2>
            <button onclick="closeEditModal()" class="modal-close">✕</button>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label>中文名称 *</label>
                    <input name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>英文名称</label>
                    <input name="name_en" id="edit_name_en">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>食材分类 *</label>
                    <select name="category" id="edit_category" required>
                        <option value="meat">🥩 肉类</option>
                        <option value="vegetable">🥬 蔬菜</option>
                        <option value="seafood">🐟 海鲜</option>
                        <option value="dairy">🥛 奶制品</option>
                        <option value="fruit">🍎 水果</option>
                        <option value="grain">🌾 谷物</option>
                        <option value="frozen">🧊 冷冻</option>
                        <option value="other">📦 其他</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>存放位置</label>
                    <input name="location" id="edit_location">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>开始日期 *</label>
                    <input type="date" name="start_date" id="edit_start_date" required>
                </div>
                <div class="form-group">
                    <label>周期天数 *</label>
                    <input type="number" name="cycle_days" id="edit_cycle_days" required min="1">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>数量</label>
                    <input name="quantity" id="edit_quantity">
                </div>
                <div class="form-group">
                    <label>图片链接</label>
                    <input name="image_url" id="edit_image_url" onchange="previewImage(this, 'edit_preview')">
                </div>
            </div>

            <div class="form-group full-width">
                <label>备注说明</label>
                <textarea name="notes" id="edit_notes" rows="2"></textarea>
            </div>

            <div class="form-group full-width">
                <img id="edit_preview" style="display:none; max-width:200px; border-radius:8px; margin-top:10px;">
            </div>

            <div class="modal-actions">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">取消</button>
                <button type="submit" class="btn btn-primary">保存修改</button>
            </div>
        </form>
    </div>
</div>

<footer class="footer">
    <p>© 2024 Banyan City Restaurant | 榕城自助餐 | Powered by AI Voice Order System</p>
</footer>

</body>
</html>
