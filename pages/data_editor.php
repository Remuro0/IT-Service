<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';

// Только для админа
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

// ✅ Подключаем конфиг и используем getDBConnection()
require_once '../config.php';
$pdo = getDBConnection(); // ← Универсальное подключение (учитывает Local/Global режим)

// Получаем параметры
$table = $_GET['table'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (empty($table) || !$id) {
    $_SESSION['message'] = "❌ Недопустимые параметры.";
    header("Location: view_db.php");
    exit;
}

// Проверяем существование таблицы
$stmt = $pdo->prepare("SHOW TABLES LIKE ?");
$stmt->execute([$table]);
if (!$stmt->fetch()) {
    $_SESSION['message'] = "❌ Таблица '$table' не существует.";
    header("Location: view_db.php");
    exit;
}

// Получаем запись
$stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    $_SESSION['message'] = "❌ Запись не найдена.";
    header("Location: view_db.php?table=" . urlencode($table));
    exit;
}

// Получаем столбцы
$columns = array_keys($record);

// Обработка формы
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update') {
    $set = [];
    $values = [];
    
    foreach ($columns as $col) {
        if ($col === 'id') continue;
        
        $value = $_POST[$col] ?? '';
        $set[] = "`$col` = ?";
        $values[] = $value;
    }
    
    $values[] = $id;
    
    $sql = "UPDATE `$table` SET " . implode(', ', $set) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute($values)) {
        $_SESSION['message'] = "✅ Запись обновлена.";
        logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'EDIT_RECORD', "Таблица: $table, ID: $id");
    } else {
        $_SESSION['message'] = "❌ Ошибка при обновлении.";
    }
    
    header("Location: view_db.php?table=" . urlencode($table));
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать запись — <?= htmlspecialchars($table) ?></title>
    <link rel="stylesheet" href="../css/view_db.css">
</head>
<body>
    <!-- Верхняя панель с пользователем -->
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
        <a href="view_db.php">🏠 Главная</a>
        <a href="edit_profile.php">✏️ Профиль</a>
        <hr>
        <a href="../logout.php">🚪 Выйти</a>
    </div>

    <div class="content-wrapper">
        <div style="max-width: 800px; margin: 30px auto; background: rgba(30,25,45,0.95); padding: 20px; border-radius: 12px; border: 1px solid #5a1a8f;">
            <h2 style="text-align: center; color: #c7b8ff;">Редактировать запись в таблице: <?= htmlspecialchars($table) ?></h2>
            
            <?php if (!empty($_SESSION['message'])): ?>
            <div style="text-align: center; padding: 10px; background: rgba(40, 100, 40, 0.3); color: #aaffaa; margin: 10px auto; max-width: 600px; border-radius: 6px;">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $id ?>">
                
                <?php foreach ($columns as $col): ?>
                <?php if ($col === 'id') continue; ?>
                <div style="margin: 12px 0;">
                    <label style="display: block; color: #c7b8ff; margin-bottom: 4px;"><?= htmlspecialchars($col) ?>:</label>
                    <input type="text" name="<?= $col ?>" value="<?= htmlspecialchars($record[$col]) ?>"
                        style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
                </div>
                <?php endforeach; ?>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" style="background: linear-gradient(to right, #3a0d6a, #5a1a8f); color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer;">💾 Сохранить</button>
                    <a href="view_db.php?table=<?= urlencode($table) ?>" style="color: #ff6b6b; text-decoration: none; margin-left: 15px;">❌ Отмена</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    function toggleUserMenu() {
        const menu = document.getElementById('userMenu');
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
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