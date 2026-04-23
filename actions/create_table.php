<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../config.php';
$pdo = getDBConnection();
require_once '../log_action.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['message'] = "❌ Ошибка подключения к БД.";
    header("Location: ../pages/view_db.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $table_name = trim($_POST['table_name'] ?? '');
    $column_count = (int)($_POST['column_count'] ?? 0);
    $fill_data = isset($_POST['fill_data']);

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
        $_SESSION['message'] = "❌ Недопустимое имя таблицы. Используйте буквы, цифры и _ (не начинайте с цифры).";
        header("Location: ../pages/view_db.php");
        exit;
    }

    if ($column_count < 1) {
        $_SESSION['message'] = "❌ Укажите количество столбцов.";
        header("Location: ../pages/view_db.php");
        exit;
    }

    $columns = [];
    for ($i = 1; $i <= $column_count; $i++) {
        $name = trim($_POST["column_$i"] ?? '');
        $type = trim($_POST["type_$i"] ?? '');
        $length = trim($_POST["length_$i"] ?? '');
        $default = trim($_POST["default_$i"] ?? '');
        $collation = trim($_POST["collation_$i"] ?? '');
        $attributes = trim($_POST["attributes_$i"] ?? '');
        $null = trim($_POST["null_$i"] ?? '');
        $comment = trim($_POST["comment_$i"] ?? '');

        if (empty($name) || empty($type)) {
            $_SESSION['message'] = "❌ Все заголовки и типы обязательны.";
            header("Location: ../pages/view_db.php");
            exit;
        }

        $type_str = $type;
        if ($length !== '') {
            $type_str .= "($length)";
        }

        $extra_str = '';
        if ($attributes !== '') {
            $extra_str .= " $attributes";
        }
        if ($null !== '') {
            $extra_str .= " $null";
        }
        if ($default !== '') {
            if ($default === 'NULL') {
                $extra_str .= " DEFAULT NULL";
            } elseif ($default === "CURRENT_TIMESTAMP") {
                $extra_str .= " DEFAULT CURRENT_TIMESTAMP";
            } else {
                $extra_str .= " DEFAULT '$default'";
            }
        }
        if ($collation !== '') {
            $extra_str .= " COLLATE $collation";
        }
        if ($comment !== '') {
            $extra_str .= " COMMENT '$comment'";
        }

        $columns[] = "`$name` $type_str$extra_str";
    }

    $sql_structure = implode(', ', $columns);

    try {
        $full_sql = "CREATE TABLE `$table_name` ($sql_structure) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($full_sql);

        $log_message = "Таблица: $table_name";

        if ($fill_data) {
            $rows_to_insert = 3;
            $inserts = [];
            for ($row = 1; $row <= $rows_to_insert; $row++) {
                $values = [];
                for ($col = 1; $col <= $column_count; $col++) {
                    $val = $_POST["data_{$row}_{$col}"] ?? '';
                    $values[] = $pdo->quote($val);
                }
                $inserts[] = "(" . implode(', ', $values) . ")";
            }

            if (!empty($inserts)) {
                $insert_sql = "INSERT INTO `$table_name` VALUES " . implode(', ', $inserts);
                $pdo->exec($insert_sql);
                $log_message .= ", вставлено 3 строки";
            }
        }

        $_SESSION['message'] = "✅ Таблица `$table_name` успешно создана.";
        logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'CREATE_TABLE', $log_message);

    } catch (PDOException $e) {
        $_SESSION['message'] = "❌ Ошибка при создании таблицы: " . htmlspecialchars($e->getMessage());
    }
} else {
    $_SESSION['message'] = "❌ Неверный метод запроса.";
}

header("Location: ../pages/view_db.php");
exit;
?>