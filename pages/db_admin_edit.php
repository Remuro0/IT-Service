<?php
session_start();
require_once '../auth.php';
requireAuth();

// Только для db_admin
if ($_SESSION['role'] !== 'db_admin') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';
$pdo = getDBConnection();
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения: " . htmlspecialchars($e->getMessage()));
}

$table = $_GET['table'] ?? '';
$id = (int)($_GET['id'] ?? 0);
if (empty($table) || !$id) {
    die("Недопустимые параметры.");
}

require_once '../log_action.php';

// Проверка существования таблицы
$stmt = $pdo->prepare("SHOW TABLES LIKE ?");
$stmt->execute([$table]);
if (!$stmt->fetch()) {
    die("Таблица '$table' не существует.");
}

// Получаем запись
$stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$record) die("Запись не найдена.");

// Обработка сохранения
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update') {
    $columns_info = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $set = [];
    $values = [];
    foreach ($columns_info as $col) {
        $field = $col['Field'];
        if ($field === 'id') continue;
        $set[] = "`$field` = ?";
        $value = $_POST[$field] ?? '';
        // Хешируем пароль, если поле называется 'password' и значение не пустое
        if ($field === 'password' && $value !== '') {
            $value = password_hash($value, PASSWORD_DEFAULT);
        }
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
    header("Location: ../pages/db_admin_view.php?table=" . urlencode($table));
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать — <?= htmlspecialchars($table) ?></title>
    <link rel="stylesheet" href="../css/view_db.css">
</head>
<body>
    <div style="max-width: 800px; margin: 30px auto; background: rgba(30,25,45,0.95); padding: 20px; border-radius: 12px; border: 1px solid #5a1a8f;">
        <h2 style="text-align: center; color: #c7b8ff;">Редактировать запись в таблице: <?= htmlspecialchars($table) ?></h2>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $id ?>">
            <?php foreach ($record as $col => $value): ?>
                <?php if ($col === 'id') continue; ?>
                <div style="margin: 12px 0;">
                    <label style="display: block; color: #c7b8ff; margin-bottom: 4px;"><?= htmlspecialchars($col) ?>:</label>
                    <?php if ($col === 'password'): ?>
                        <input type="text" name="<?= $col ?>" value="" placeholder="Оставьте пустым, чтобы не менять"
                               style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
                    <?php else: ?>
                        <input type="text" name="<?= $col ?>" value="<?= htmlspecialchars($value) ?>"
                               style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" style="background: linear-gradient(to right, #3a0d6a, #5a1a8f); color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer;">💾 Сохранить</button>
                <a href="../pages/db_admin_view.php?table=<?= urlencode($table) ?>" style="color: #ff6b6b; text-decoration: none; margin-left: 15px;">❌ Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>