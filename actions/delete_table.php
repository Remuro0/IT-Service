<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../config.php';
$pdo = getDBConnection();
require_once '../log_action.php';

// Только для админа
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "❌ Удалять таблицы может только администратор.";
    header("Location: ../pages/view_db.php");
    exit;
}

$table = $_GET['table'] ?? '';

if (empty($table)) {
    $_SESSION['message'] = "❌ Не указана таблица для удаления.";
    header("Location: ../pages/view_db.php");
    exit;
}

// Защита от удаления системных таблиц
$protected_tables = ['users', 'logs', 'backups'];
if (in_array($table, $protected_tables)) {
    $_SESSION['message'] = "❌ Запрещено удалять системные таблицы.";
    header("Location: ../pages/view_db.php");
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Проверка существования таблицы
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    if (!$stmt->fetch()) {
        $_SESSION['message'] = "❌ Таблица '$table' не существует.";
        header("Location: ../pages/view_db.php");
        exit;
    }

    // Удаление таблицы
    $pdo->exec("DROP TABLE `$table`");
    $_SESSION['message'] = "✅ Таблица '$table' успешно удалена.";

    // Логируем ТОЛЬКО после успешного удаления
    logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'DELETE_TABLE', "Таблица: $table");

} catch (PDOException $e) {
    $_SESSION['message'] = "❌ Ошибка при удалении таблицы: " . htmlspecialchars($e->getMessage());
}

header("Location: ../pages/view_db.php");
exit;
?>