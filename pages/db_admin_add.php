<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';

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
if (empty($table)) {
    die("Не указана таблица.");
}

// Проверка существования таблицы
$stmt = $pdo->prepare("SHOW TABLES LIKE ?");
$stmt->execute([$table]);
if (!$stmt->fetch()) {
    die("Таблица '$table' не существует.");
}

// === Обработка отправки формы ===
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'insert') {
    $columns_info = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $columns = [];
    $values = [];
    $placeholders = [];
    $password_fields = ['password', 'pass', 'passwd', 'user_password', 'pwd', 'PASSWORD_HASH', 'password_hash'];

    foreach ($columns_info as $col) {
        $field = $col['Field'];
        if ($field === 'id' && strpos($col['Extra'], 'auto_increment') !== false) {
            continue;
        }
        $columns[] = "`$field`";
        $placeholders[] = "?";
        $value = $_POST[$field] ?? '';
        if (in_array($field, $password_fields) && $value !== '') {
            $value = password_hash($value, PASSWORD_DEFAULT);
        }
        $values[] = $value;
    }

    if (!empty($columns)) {
        $sql = "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($values)) {
            $_SESSION['message'] = "✅ Запись добавлена.";
            // Логируем с реальным ID
            $new_id = $pdo->lastInsertId();
            logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'ADD_RECORD', "Таблица: $table, ID: $new_id");
        } else {
            $_SESSION['message'] = "❌ Ошибка при добавлении.";
        }
    } else {
        $_SESSION['message'] = "❌ Нет полей для вставки.";
    }

    header("Location: ../pages/db_admin_view.php?table=" . urlencode($table));
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить запись — <?= htmlspecialchars($table) ?></title>
    <link rel="stylesheet" href="../css/view_db.css">
</head>
<body>
    <div style="max-width: 800px; margin: 30px auto; background: rgba(30,25,45,0.95); padding: 20px; border-radius: 12px; border: 1px solid #5a1a8f;">
        <h2 style="text-align: center; color: #c7b8ff;">Добавить запись в таблицу: <?= htmlspecialchars($table) ?></h2>
        <form method="POST">
            <input type="hidden" name="action" value="insert">
            <?php
            $columns_info = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns_info as $col):
                $field = $col['Field'];
                if ($field === 'id' && strpos($col['Extra'], 'auto_increment') !== false) {
                    continue;
                }
            ?>
                <div style="margin: 12px 0;">
                    <label style="display: block; color: #c7b8ff; margin-bottom: 4px;"><?= htmlspecialchars($field) ?>:</label>
                    <input type="text" name="<?= $field ?>" 
                           style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
                </div>
            <?php endforeach; ?>
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" style="background: linear-gradient(to right, #00c853, #64dd17); color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer;">Добавить</button>
                <a href="../pages/db_admin_view.php?table=<?= urlencode($table) ?>" style="color: #ff6b6b; text-decoration: none; margin-left: 15px;">Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>