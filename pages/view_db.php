<?php
session_start();
require_once '../auth.php';
requireAuth();

// 🔒 Только админ
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "❌ У вас нет прав на просмотр базы данных.";
    header("Location: ../index.php");
    exit;
}

// === Переключение БД ===
if (isset($_GET['switch_db'])) {
    $new_mode = $_GET['switch_db'] === 'global' ? 'global' : 'local';
    
    if ($new_mode === 'global') {
        // Запускаем синхронизацию без редиректа
        ob_start();
        require_once '../sync_to_global.php';
        ob_end_clean();
        
        // Если синхронизация прошла успешно — переключаемся
        if (strpos($_SESSION['message'] ?? '', '✅') !== false) {
            $_SESSION['db_mode'] = 'global';
            $_SESSION['message'] .= " 🔁 Переключено на глобальную БД.";
        }
    } else {
        $_SESSION['db_mode'] = 'local';
        $_SESSION['message'] = "🔁 Переключено на локальную БД.";
    }
    
    // Убираем GET-параметр
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// === Подключаемся к БД в нужном режиме ===
require_once '../config.php';

try {
    $pdo = getDBConnection(); // ← Используем универсальную функцию из config.php
} catch (Exception $e) {
    die("❌ Ошибка подключения к БД: " . htmlspecialchars($e->getMessage()));
}

// Получаем все таблицы
$stmt = $pdo->query("SHOW TABLES");
$all_tables = [];
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $all_tables[] = $row[0];
}

$current_table = $_GET['table'] ?? '';
if (!in_array($current_table, $all_tables)) {
    $current_table = $all_tables[0] ?? '';
}

// Обновляем роль и аватар из БД
try {
    $stmt = $pdo->prepare("SELECT role, avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['avatar'] = $user['avatar'] ?? 'imang/default.png';
    } else {
        session_destroy();
        header("Location: ../login.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['role'] = $_SESSION['role'] ?? 'user';
    $_SESSION['avatar'] = $_SESSION['avatar'] ?? 'imang/default.png';
}

// === Вспомогательные функции ===
function getUserName($pdo, $user_id) {
    if (!$user_id) return '—';
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ? htmlspecialchars($user['username']) : "ID: $user_id";
}

function getServerName($pdo, $server_id) {
    if (!$server_id) return '—';
    $stmt = $pdo->prepare("SELECT name FROM servers WHERE id = ?");
    $stmt->execute([$server_id]);
    $server = $stmt->fetch(PDO::FETCH_ASSOC);
    return $server ? htmlspecialchars($server['name']) : "ID: $server_id";
}

function getStatusColor($status) {
    $status = strtolower(trim($status));
    if (in_array($status, ['up', 'active', 'planned', 'approved', 'implemented', 'resolved', 'closed', 'success', 'online', 'running'])) {
        return 'rgba(40, 200, 80, 0.3)';
    } elseif (in_array($status, ['down', 'inactive', 'failed', 'error', 'offline', 'stopped', 'deleted'])) {
        return 'rgba(200, 50, 50, 0.3)';
    } elseif (in_array($status, ['maintenance', 'updating', 'restarting'])) {
        return 'rgba(255, 165, 0, 0.3)';
    } elseif (in_array($status, ['open', 'in_progress', 'pending', 'processing'])) {
        return 'rgba(50, 100, 255, 0.3)';
    } else {
        return 'rgba(100, 100, 100, 0.3)';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>База данных: <?= htmlspecialchars($dbname) ?></title>
    <link rel="stylesheet" href="../css/view_db.css">
</head>
<body>
    <!-- Верхняя панель -->
    <div class="topbar">
        <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? 'imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username']) ?></strong><br>
            <span class="role">Роль: <?= htmlspecialchars($_SESSION['role']) ?></span>
        </div>
        <button onclick="toggleUserMenu()" style="background: none; border: none; color: #c7b8ff; font-size: 16px; cursor: pointer;">▼</button>
    </div>

    <!-- Выпадающее меню -->
    <div id="userMenu" class="dropdown-content" style="display: none;">
        <!-- Продление сессии (только для админа) -->
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <hr>
        <?php endif; ?>
        
        <div style="padding: 8px 12px; color: #c7b8ff; font-weight: bold; cursor: pointer;"
             onclick="toggleTablesMenu()">
            📋 Таблицы <span id="tablesArrow">▼</span>
        </div>
        <div id="tablesList" style="display: none; margin-top: 8px;">
            <?php foreach ($all_tables as $table): ?>
                <a href="?table=<?= urlencode($table) ?>" style="display: block; padding: 6px 12px; color: #e0e0e0; text-decoration: none;">
                    <?= htmlspecialchars($table) ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <hr>
        <a href="edit_profile.php">🖊️ Редактировать профиль</a>
        <a href="../actions/create_table_form.php">➕ Создать таблицу</a>
        
        <a href="../sync_to_global.php"
           onclick="return confirm('⚠️ Внимание!\nВсе данные в глобальной БД (project_Tkachenko) будут ЗАМЕНЕНЫ данными из локальной БД (local).\nПродолжить?')"
           style="display: block; padding: 8px 12px; color: #e0e0e0; text-decoration: none; border-radius: 4px; font-size: 13px; margin: 4px 0; background: rgba(100, 60, 180, 0.1);">
            🌐 Синхронизировать в глобальную БД
        </a>
        
        <hr>
        
        <!-- Переключатель БД -->
        <div style="padding: 8px 12px; color: #c7b8ff; font-weight: bold; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
             onclick="toggleDbSwitchMenu()">
            🌐 База данных: <strong><?= $_SESSION['db_mode'] ?? 'local' ?></strong>
            <span id="dbSwitchArrow" style="font-size: 14px;">▼</span>
        </div>
        <div id="dbSwitchMenu" style="display: none; margin-top: 8px;">
            <a href="?switch_db=local"
               style="display: block; padding: 6px 12px; color: #e0e0e0; text-decoration: none; border-radius: 4px; font-size: 13px;"
               onmouseover="this.style.backgroundColor='rgba(100, 80, 150, 0.3)'; this.style.color='white';"
               onmouseout="this.style.backgroundColor=''; this.style.color='#e0e0e0';">
                Локальная БД
            </a>
            <a href="?switch_db=global"
               style="display: block; padding: 6px 12px; color: #e0e0e0; text-decoration: none; border-radius: 4px; font-size: 13px;"
               onmouseover="this.style.backgroundColor='rgba(100, 80, 150, 0.3)'; this.style.color='white';"
               onmouseout="this.style.backgroundColor=''; this.style.color='#e0e0e0';">
                Глобальная БД
            </a>
        </div>
        
        <script>
        function toggleDbSwitchMenu() {
            const menu = document.getElementById('dbSwitchMenu');
            const arrow = document.getElementById('dbSwitchArrow');
            if (menu.style.display === 'block') {
                menu.style.display = 'none';
                arrow.textContent = '▼';
            } else {
                menu.style.display = 'block';
                arrow.textContent = '▲';
            }
        }
        </script>
        
        <a href="../actions/backup.php">💾 Создать бэкап</a>
        <a href="sql_console.php">💻 SQL-консоль</a>
        <a href="manage_timeouts.php">⏱️ Таймауты</a>
        <a href="view_logs.php">📋 Управление логами</a>
        
        <hr>
        <a href="../logout.php">🚪 Выйти</a>
    </div>

    <div class="content-wrapper">
        <?php if (!empty($_SESSION['message'])): ?>
            <div style="text-align: center; padding: 12px; background: rgba(40, 100, 40, 0.3); color: #aaffaa; margin: 10px auto; max-width: 600px; border-radius: 8px; border: 1px solid #3a8a3a;">
                <?= nl2br(htmlspecialchars($_SESSION['message'])) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <h2 style="margin: 0;">Таблица: <?= htmlspecialchars($current_table) ?></h2>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="../actions/add_record.php?table=<?= urlencode($current_table) ?>"
                   style="background: linear-gradient(to right, #00c853, #64dd17); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none;">
                    + Добавить запись
                </a>
                <?php if ($current_table !== 'users'): ?>
                    <a href="../actions/delete_table.php?table=<?= urlencode($current_table) ?>"
                       onclick="return confirm('Вы уверены, что хотите удалить ВСЮ таблицу <?= addslashes($current_table) ?>?')"
                       style="background: linear-gradient(to right, #ff3b3b, #ff6b6b); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none;">
                        🗑️ Удалить таблицу
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-container">
            <?php
            try {
                $stmt = $pdo->query("SELECT * FROM `$current_table`");
                $columns = [];
                for ($i = 0; $i < $stmt->columnCount(); $i++) {
                    $col = $stmt->getColumnMeta($i);
                    $columns[] = $col['name'];
                }
                
                echo "<table><tr>";
                foreach ($columns as $col) {
                    echo "<th>" . htmlspecialchars($col) . "</th>";
                }
                if (in_array('id', $columns)) {
                    echo "<th>Действия</th>";
                }
                echo "</tr>";
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    foreach ($columns as $col) {
                        $value = $row[$col];
                        
                        if ($current_table === 'changes' && in_array($col, ['requested_by', 'approved_by'])) {
                            $value = getUserName($pdo, $value);
                        } elseif ($current_table === 'incidents' && $col === 'assigned_to') {
                            $value = getUserName($pdo, $value);
                        } elseif ($current_table === 'backups' && $col === 'server_id') {
                            $value = getServerName($pdo, $value);
                        }
                        
                        if ($current_table === 'users' && $col === 'is_blocked') {
                            $display = $value ? '⛔ Заблокирован' : '✅ Активен';
                            $bgColor = $value ? 'rgba(200, 50, 50, 0.3)' : 'rgba(40, 200, 80, 0.3)';
                            echo "<td style='background: {$bgColor}; padding: 10px 12px; border-bottom: 1px solid rgba(100, 80, 130, 0.2); color: #ffffff;'>";
                            echo htmlspecialchars($display);
                            echo "</td>";
                        } elseif (stripos($col, 'status') !== false) {
                            $bgColor = getStatusColor($value);
                            echo "<td style='background: {$bgColor}; padding: 10px 12px; border-bottom: 1px solid rgba(100, 80, 130, 0.2);'>";
                            echo htmlspecialchars($value);
                            echo "</td>";
                        } else {
                            echo "<td>" . htmlspecialchars($value) . "</td>";
                        }
                    }
                    
                    if (in_array('id', $columns)) {
                        $id = $row['id'];
                        echo "<td class='actions'>";
                        echo "<a href='../actions/edit_record.php?table=" . urlencode($current_table) . "&id=$id' class='btn-edit'>✏️ Изм</a> ";
                        echo "<a href='../actions/delete_record.php?table=" . urlencode($current_table) . "&id=$id' class='btn-delete' onclick='return confirm(\"Удалить запись?\")'>🗑️ Уд</a>";
                        echo "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } catch (PDOException $e) {
                echo "<div style='color: #ff6b6b; padding: 10px; background: rgba(200, 50, 50, 0.3); border-radius: 6px;'>❌ Ошибка: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
            ?>
        </div>
    </div>

    <script>
    function toggleUserMenu() {
        const menu = document.getElementById('userMenu');
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }
    
    function toggleTablesMenu() {
        const list = document.getElementById('tablesList');
        const arrow = document.getElementById('tablesArrow');
        list.style.display = list.style.display === 'block' ? 'none' : 'block';
        arrow.textContent = list.style.display === 'block' ? '▲' : '▼';
    }
    
    function toggleDbSwitchMenu() {
        const menu = document.getElementById('dbSwitchMenu');
        const arrow = document.getElementById('dbSwitchArrow');
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        arrow.textContent = menu.style.display === 'block' ? '▲' : '▼';
    }
    
    document.addEventListener('click', function(e) {
        const menu = document.getElementById('userMenu');
        const btn = document.querySelector('.topbar button');
        if (!menu.contains(e.target) && !btn.contains(e.target)) {
            menu.style.display = 'none';
        }
    });
    </script>
    
    <?php include '../footer.php'; ?>
</body>
</html>