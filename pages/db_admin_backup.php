<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';

// Только для db_admin
if ($_SESSION['role'] !== 'db_admin') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../pages/db_admin_dashboard.php");
    exit;
}

require_once '../config.php';
$pdo = getDBConnection();

// Путь к папке backups — относительно корня проекта
$backup_dir = __DIR__ . '/../backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// Имя файла
$filename = 'backup_' . date('Ymd_His') . '.sql';
$filepath = $backup_dir . $filename; // абсолютный путь для записи на диск
$relative_path = '/backups/' . $filename; // относительный путь для сохранения в БД

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем список таблиц
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    // Формируем SQL-дамп
    $sql = "-- Backup of database `$dbname`\n";
    $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";

    foreach ($tables as $table) {
        // Структура таблицы
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $sql .= $create[1] . ";\n";

        // Данные
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $columns = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $col = $stmt->getColumnMeta($i);
            $columns[] = "`" . $col['name'] . "`";
        }

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $values = [];
            foreach ($row as $val) {
                $values[] = $val === null ? 'NULL' : "'" . addslashes($val) . "'";
            }
            $sql .= "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    // Сохраняем файл на диск
    if (file_put_contents($filepath, $sql)) {
        try {
            // Получаем server_id
            $server_name = 'Prod-DB-01';
            $stmt = $pdo->prepare("SELECT id FROM servers WHERE name = ? LIMIT 1");
            $stmt->execute([$server_name]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$server) {
                $stmt = $pdo->query("SELECT id FROM servers ORDER BY id LIMIT 1");
                $server = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$server) {
                    throw new Exception("Нет серверов в таблице 'servers'.");
                }
            }

            $server_id = $server['id'];
            $file_size_mb = round(filesize($filepath) / (1024 * 1024), 2);

            $insert = $pdo->prepare("
                INSERT INTO backups (server_id, backup_date, file_path, size_mb, status)
                VALUES (?, NOW(), ?, ?, 'success')
            ");
            $insert->execute([$server_id, $relative_path, $file_size_mb]);

            $_SESSION['message'] = "✅ Бэкап '$filename' создан и записан в БД.";
            logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'BACKUP_CREATED', "Файл: $relative_path");
        } catch (Exception $inner_e) {
            $_SESSION['message'] = "⚠️ Бэкап создан, но ошибка записи в БД: " . htmlspecialchars($inner_e->getMessage());
        }
    } else {
        $_SESSION['message'] = "❌ Не удалось сохранить файл бэкапа.";
    }
} catch (Exception $e) {
    $_SESSION['message'] = "❌ Ошибка при создании бэкапа: " . htmlspecialchars($e->getMessage());
}

header("Location: ../pages/db_admin_dashboard.php");
exit;
?>