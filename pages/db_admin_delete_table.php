<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';

// Только для db_admin или admin
if (!in_array($_SESSION['role'], ['db_admin', 'admin'])) {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

// ✅ Подключаем конфиг и используем getDBConnection()
require_once '../config.php';
$pdo = getDBConnection(); // ← Универсальное подключение (учитывает Local/Global режим)

$table = $_GET['table'] ?? '';
if (empty($table)) {
    $_SESSION['message'] = "❌ Не указана таблица для удаления.";
    header("Location: db_admin_dashboard.php");
    exit;
}

// Защита системных таблиц
$protected_tables = ['users', 'logs', 'backups', 'session_timeouts'];
if (in_array($table, $protected_tables)) {
    $_SESSION['message'] = "❌ Запрещено удалять системные таблицы.";
    header("Location: db_admin_dashboard.php");
    exit;
}

// Проверка существования таблицы
$stmt = $pdo->prepare("SHOW TABLES LIKE ?");
$stmt->execute([$table]);
if (!$stmt->fetch()) {
    $_SESSION['message'] = "❌ Таблица '$table' не существует.";
    header("Location: db_admin_dashboard.php");
    exit;
}

// Удаление таблицы
try {
    $pdo->exec("DROP TABLE `$table`");
    $_SESSION['message'] = "✅ Таблица '$table' успешно удалена.";
    logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'DELETE_TABLE', "Таблица: $table");
} catch (PDOException $e) {
    $_SESSION['message'] = "❌ Ошибка при удалении таблицы: " . htmlspecialchars($e->getMessage());
}

header("Location: db_admin_dashboard.php");
exit;
?>