<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';

// Только для admin/db_admin
if (!in_array($_SESSION['role'], ['admin', 'db_admin'])) {
    $_SESSION['message'] = "❌ Только DB-администратор или админ может использовать эту функцию.";
    header("Location: view_db.php");
    exit;
}

require_once '../config.php';

$result = null;
$error = null;
$last_query = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql_query'])) {
    $last_query = trim($_POST['sql_query']);
    if (!empty($last_query)) {
        try {
            $pdo = getDBConnection(); // ✅ Универсальное подключение

            $query_upper = strtoupper(ltrim($last_query));
            if (strpos($query_upper, 'SELECT') === 0) {
                $stmt = $pdo->query($last_query);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $count = $pdo->exec($last_query);
                $result = "✅ Запрос выполнен успешно. Затронуто строк: $count";
                logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'SQL_EXEC', "Запрос: $last_query");
            }
        } catch (PDOException $e) {
            $error = "❌ Ошибка выполнения запроса: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = "❌ Запрос не может быть пустым.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>SQL-консоль</title>
    <link rel="stylesheet" href="../css/view_db.css">
</head>
<body>
    <div style="max-width: 1200px; margin: 20px auto; padding: 0 20px;">
        <h2 style="color: #c7b8ff; text-align: center;">SQL-консоль</h2>
        <form method="POST" style="margin-bottom: 20px;">
            <label style="display: block; color: #c7b8ff; margin-bottom: 8px;">Введите SQL-запрос:</label>
            <textarea name="sql_query" rows="6" style="width: 100%; padding: 10px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px; font-family: monospace; font-size: 14px;" placeholder="SELECT * FROM users;"><?= htmlspecialchars($last_query) ?></textarea>
            <div style="text-align: center; margin-top: 10px;">
                <button type="submit" style="background: linear-gradient(to right, #ff3b3b, #ff6b6b); color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">Выполнить</button>
                <a href="view_db.php" style="color: #6ab7ff; text-decoration: none; margin-left: 15px;">← Назад</a>
            </div>
        </form>
        <?php if ($error): ?>
            <div style="background: rgba(200, 50, 50, 0.3); color: #ffaaaa; padding: 12px; border-radius: 6px; margin: 10px 0; border: 1px solid #8a3a3a;">
                <?= $error ?>
            </div>
        <?php endif; ?>
        <?php if (is_array($result) && !empty($result)): ?>
            <h3 style="color: #c7b8ff;">Результат запроса:</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <?php foreach (array_keys($result[0]) as $col): ?>
                                <th><?= htmlspecialchars($col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?= htmlspecialchars($cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (is_string($result)): ?>
            <div style="background: rgba(40, 200, 80, 0.3); color: #aaffaa; padding: 12px; border-radius: 6px; margin: 10px 0; border: 1px solid #3a8a3a;">
                <?= $result ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>