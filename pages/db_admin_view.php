<?php
session_start();
require_once '../auth.php';
requireAuth();
if ($_SESSION['role'] !== 'db_admin') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}
require_once '../config.php';
try {
    $pdo = getDBConnection(); // ✅
    $stmt = $pdo->query("SHOW TABLES");
    $all_tables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $all_tables[] = $row[0];
    }
    $current_table = $_GET['table'] ?? '';
    if (!in_array($current_table, $all_tables)) {
        $current_table = $all_tables[0] ?? '';
    }
} catch (PDOException $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}

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
    if (in_array($status, ['up', 'active'])) return 'rgba(40, 200, 80, 0.3)';
    if (in_array($status, ['down', 'inactive'])) return 'rgba(200, 50, 50, 0.3)';
    return 'rgba(100, 100, 100, 0.3)';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>DB-админ: <?= htmlspecialchars($current_table) ?></title>
    <link rel="stylesheet" href="../css/view_db.css">
</head>
<body>
    <div class="topbar">
        <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? 'imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
        </div>
        <button onclick="toggleUserMenu()">▼</button>
    </div>
    <div id="userMenu" class="dropdown-content" style="display:none;">
        <a href="db_admin_dashboard.php">Главная</a>
        <a href="../actions/create_table_form.php">Создать таблицу</a>
        <a href="../actions/backup.php">Бэкап</a>
        <a href="db_admin_sql_console.php">SQL-консоль</a>
        <a href="edit_profile.php">Профиль</a>
        <hr>
        <a href="../logout.php">🚪 Выйти</a>
    </div>
    <div class="content-wrapper">
        <?php if (!empty($_SESSION['message'])): ?>
            <div class="message"><?= htmlspecialchars($_SESSION['message']) ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:15px;">
            <h2>Таблица: <?= htmlspecialchars($current_table) ?></h2>
            <a href="../actions/db_admin_add.php?table=<?= urlencode($current_table) ?>" class="btn-add">+ Добавить запись</a>
        </div>
        <div class="table-container">
            <?php
            try {
                $stmt = $pdo->query("SELECT * FROM `$current_table`");
                $cols = [];
                for ($i = 0; $i < $stmt->columnCount(); $i++) {
                    $cols[] = $stmt->getColumnMeta($i)['name'];
                }
                echo "<table><tr>";
                foreach ($cols as $c) echo "<th>" . htmlspecialchars($c) . "</th>";
                if (in_array('id', $cols)) echo "<th>Действия</th>";
                echo "</tr>";
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    foreach ($cols as $c) {
                        $v = $row[$c];
                        if ($current_table === 'backups' && $c === 'server_id') $v = getServerName($pdo, $v);
                        echo "<td>" . htmlspecialchars($v) . "</td>";
                    }
                    if (in_array('id', $cols)) {
                        $id = $row['id'];
                        echo "<td class='actions'>";
                        echo "<a href='../actions/db_admin_edit.php?table=" . urlencode($current_table) . "&id=$id' class='btn-edit'>✏️</a> ";
                        echo "<a href='../actions/db_admin_delete.php?table=" . urlencode($current_table) . "&id=$id' class='btn-delete' onclick='return confirm(\"Удалить?\")'>🗑️</a>";
                        echo "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } catch (PDOException $e) {
                echo "<div class='error'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
            }
            ?>
        </div>
    </div>
    <script>
        function toggleUserMenu() {
            const m = document.getElementById('userMenu');
            m.style.display = m.style.display === 'block' ? 'none' : 'block';
        }
        document.addEventListener('click', e => {
            const m = document.getElementById('userMenu'), b = document.querySelector('.topbar button');
            if (!b.contains(e.target) && !m.contains(e.target)) m.style.display = 'none';
        });
    </script>
    <?php include '../footer.php'; ?>
</body>
</html>