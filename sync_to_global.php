<?php
session_start();
require_once '../auth.php';
requireAuth();

if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "❌ Только админ может синхронизировать.";
    header("Location: ../pages/view_db.php");
    exit;
}

$message = '';
$error = false;

try {
    // === 1️⃣ Подключаемся к локальной БД через config.php (как раньше) ===
    require_once '../config.php';
    $pdo_local = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo_local->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // === 2️⃣ 🔥 ЖЁСТКО ПОДКЛЮЧАЕМСЯ К ГЛОБАЛЬНОЙ БД — как в вашем рабочем тесте ===
    $pdo_global = new PDO(
        "mysql:host=134.90.167.42;port=10306;dbname=project_Tkachenko;charset=utf8",
        'Tkachenko',
        'F6DRi_'
    );
    $pdo_global->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // === 3️⃣ Получаем ВСЕ таблицы из локальной БД ===
    $stmt = $pdo_local->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // === 4️⃣ Отключаем внешние ключи ===
    $pdo_global->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // === 5️⃣ Обрабатываем каждую таблицу ===
    foreach ($tables as $table) {
        try {
            // Получаем CREATE
            $stmt_create = $pdo_local->query("SHOW CREATE TABLE `$table`");
            $row = $stmt_create->fetch(PDO::FETCH_NUM);
            if (!$row) continue;
            $create_sql = $row[1];

            // Чистим от ON DELETE/UPDATE CASCADE (для MariaDB)
            $create_sql = preg_replace(
                '/,\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN KEY\s+\([^)]+\)\s+REFERENCES\s+`[^`]+`\s+\([^)]+\)(\s+ON\s+(DELETE|UPDATE)\s+\w+)*|' .
                ',\s*FOREIGN KEY\s+\([^)]+\)\s+REFERENCES\s+`[^`]+`\s+\([^)]+\)(\s+ON\s+(DELETE|UPDATE)\s+\w+)*/i',
                '',
                $create_sql
            );
            $create_sql = preg_replace('/,\s*\)/', ')', $create_sql);

            // Пересоздаём в глобальной
            $pdo_global->exec("DROP TABLE IF EXISTS `$table`");
            $pdo_global->exec($create_sql);

            // Переносим данные
            $stmt_select = $pdo_local->query("SELECT * FROM `$table`");
            $first_row = $stmt_select->fetch(PDO::FETCH_ASSOC);
            if (!$first_row) continue;

            $columns = array_keys($first_row);
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $stmt_insert = $pdo_global->prepare(
                "INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) VALUES ($placeholders)"
            );

            // Первая строка (с нормализацией)
            $values = array_values($first_row);
            foreach ($columns as $i => $col) {
                if (in_array(strtolower($col), ['status', 'event_type'])) {
                    $val = trim(strtolower((string)$values[$i]));
                    $stmt_desc = $pdo_global->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
                    $stmt_desc->execute([$col]);
                    $col_info = $stmt_desc->fetch(PDO::FETCH_ASSOC);
                    if ($col_info && strpos($col_info['Type'], 'varchar(') !== false) {
                        $len = (int)preg_replace('/[^0-9]/', '', $col_info['Type']);
                        if ($len > 0 && strlen($val) > $len) $val = substr($val, 0, $len);
                    }
                    $values[$i] = $val;
                }
            }
            $stmt_insert->execute($values);

            // Остальные строки
            while ($row = $stmt_select->fetch(PDO::FETCH_NUM)) {
                $values = $row;
                foreach ($columns as $i => $col) {
                    if (in_array(strtolower($col), ['status', 'event_type'])) {
                        $val = trim(strtolower((string)$values[$i]));
                        $stmt_desc = $pdo_global->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
                        $stmt_desc->execute([$col]);
                        $col_info = $stmt_desc->fetch(PDO::FETCH_ASSOC);
                        if ($col_info && strpos($col_info['Type'], 'varchar(') !== false) {
                            $len = (int)preg_replace('/[^0-9]/', '', $col_info['Type']);
                            if ($len > 0 && strlen($val) > $len) $val = substr($val, 0, $len);
                        }
                        $values[$i] = $val;
                    }
                }
                $stmt_insert->execute($values);
            }

        } catch (Exception $e) {
            error_log("sync_to_global: ошибка таблицы '$table': " . $e->getMessage());
        }
    }

    // === 6️⃣ Включаем FK ===
    $pdo_global->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // ✅ УСПЕШНО — переключаемся на глобальную БД в интерфейсе
    $_SESSION['db_mode'] = 'global';
    $message = "✅ Синхронизация завершена. Все таблицы скопированы.";

} catch (Exception $e) {
    $error = true;
    $message = "❌ Ошибка: " . htmlspecialchars(substr($e->getMessage(), 0, 150));
    if (isset($pdo_global)) {
        try {
            if ($pdo_global->inTransaction()) $pdo_global->rollback();
        } catch (Exception $ex) {}
        $pdo_global->exec("SET FOREIGN_KEY_CHECKS = 1;");
    }
}
function syncTableToGlobal($pdo_local, $pdo_global, $table, $id = null) {
    try {
        // Получаем структуру таблицы из local
        $columns_stmt = $pdo_local->query("SHOW COLUMNS FROM `$table`");
        $columns = $columns_stmt->fetchAll(PDO::FETCH_ASSOC);
        $column_names = array_column($columns, 'Field');
        
        // Проверяем существование таблицы в global
        $check_stmt = $pdo_global->prepare("SHOW TABLES LIKE ?");
        $check_stmt->execute([$table]);
        
        if (!$check_stmt->fetch()) {
            // Создаём таблицу в global
            $create_stmt = $pdo_local->query("SHOW CREATE TABLE `$table`");
            $create_row = $create_stmt->fetch(PDO::FETCH_NUM);
            if ($create_row) {
                $pdo_global->exec($create_row[1]);
            }
        }
        
        // Формируем запрос для получения данных
        if ($id !== null) {
            $sql = "SELECT * FROM `$table` WHERE id = ?";
            $stmt = $pdo_local->prepare($sql);
            $stmt->execute([$id]);
        } else {
            $sql = "SELECT * FROM `$table`";
            $stmt = $pdo_local->query($sql);
        }
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) {
            return ['success' => true, 'message' => 'Нет данных для синхронизации'];
        }
        
        // Вставляем/обновляем каждую запись в global
        $synced = 0;
        foreach ($rows as $row) {
            $columns_list = implode(', ', array_map(fn($c) => "`$c`", $column_names));
            $placeholders = implode(', ', array_fill(0, count($column_names), '?'));
            
            $upsert_sql = "INSERT INTO `$table` ($columns_list) VALUES ($placeholders)
                          ON DUPLICATE KEY UPDATE " . 
                          implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", $column_names));
            
            $insert_stmt = $pdo_global->prepare($upsert_sql);
            $values = array_values($row);
            
            if ($insert_stmt->execute($values)) {
                $synced++;
            }
        }
        
        return ['success' => true, 'synced' => $synced];
        
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

$_SESSION['message'] = $message;
header("Location: ../pages/view_db.php"); // ← правильный путь
exit;