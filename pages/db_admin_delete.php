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

$table = $_GET['table'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (empty($table) || !$id) {
    $_SESSION['message'] = "❌ Недопустимые параметры.";
    header("Location: ../pages/db_admin_view.php?table=" . urlencode($table));
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
        header("Location: ../pages/db_admin_view.php");
        exit;
    }

    // === Особая обработка для таблицы `backups` ===
    if ($table === 'backups') {
        $stmt = $pdo->prepare("SELECT file_path FROM backups WHERE id = ?");
        $stmt->execute([$id]);
        $backup = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$backup) {
            $_SESSION['message'] = "❌ Запись не найдена.";
            header("Location: ../pages/db_admin_view.php?table=backups");
            exit;
        }
        $file_path = $backup['file_path'];

        $stmt = $pdo->prepare("DELETE FROM backups WHERE id = ?");
        if ($stmt->execute([$id])) {
            // Удаляем файл с диска
            $full_path = __DIR__ . '/../' . ltrim($file_path, '/'); // корректный путь: /backups/... → ../backups/...
            if (file_exists($full_path)) {
                if (unlink($full_path)) {
                    $_SESSION['message'] = "✅ Запись и файл бэкапа удалены.";
                } else {
                    $_SESSION['message'] = "⚠️ Запись удалена, но файл не удалось удалить с диска.";
                }
            } else {
                $_SESSION['message'] = "✅ Запись удалена (файл уже отсутствовал на диске).";
            }
            logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'DELETE_RECORD', "Таблица: backups, ID: $id, файл: $file_path");
        } else {
            $_SESSION['message'] = "❌ Ошибка при удалении записи из БД.";
        }
    } else {
        // Обычное удаление для других таблиц
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['message'] = "✅ Запись удалена.";
            logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'DELETE_RECORD', "Таблица: $table, ID: $id");
        } else {
            $_SESSION['message'] = "❌ Ошибка при удалении.";
        }
    }
} catch (PDOException $e) {
    $_SESSION['message'] = "❌ Ошибка БД: " . htmlspecialchars($e->getMessage());
}

header("Location: ../pages/db_admin_view.php?table=" . urlencode($table));
exit;
?>