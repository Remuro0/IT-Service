<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';
if ($_SESSION['role'] !== 'db_admin') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: db_admin_dashboard.php");
    exit;
}
require_once '../config.php';
$result = null;
$error = null;
$last_query = '';

if ($_POST && isset($_POST['sql_query'])) {
    $last_query = trim($_POST['sql_query']);
    if (!empty($last_query)) {
        try {
            $pdo = getDBConnection(); // ✅
            $q = strtoupper(ltrim($last_query));
            if (strpos($q, 'SELECT') === 0) {
                $stmt = $pdo->query($last_query);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $count = $pdo->exec($last_query);
                $result = "✅ Выполнено. Изменено строк: $count";
                logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'SQL_EXEC', $last_query);
            }
        } catch (PDOException $e) {
            $error = "❌ " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>SQL-консоль (DB-админ)</title>
    <link rel="stylesheet" href="../css/view_db.css">
</head>
<body>
    <div style="max-width:1200px;margin:20px auto;padding:0 20px;">
        <h2 style="color:#c7b8ff;text-align:center;">SQL-консоль</h2>
        <form method="POST">
            <textarea name="sql_query" rows="6" placeholder="SELECT * FROM users;"
                style="width:100%;padding:10px;background:#1e192d;border:1px solid #5a1a8f;color:white;border-radius:6px;font-family:monospace;">
                <?= htmlspecialchars($last_query) ?>
            </textarea>
            <div style="text-align:center;margin-top:10px;">
                <button type="submit" style="background:linear-gradient(to right,#ff3b3b,#ff6b6b);color:white;padding:10px 24px;border:0;border-radius:6px;cursor:pointer;font-weight:bold;">
                    Выполнить
                </button>
                <a href="db_admin_dashboard.php" style="color:#6ab7ff;margin-left:15px;">← Назад</a>
            </div>
        </form>
        <?php if ($error): ?>
            <div style="background:rgba(200,50,50,0.3);padding:12px;border-radius:6px;margin:10px 0;border:1px solid #8a3a3a;">
                <?= $error ?>
            </div>
        <?php endif; ?>
        <?php if (is_array($result)): ?>
            <div class="table-container"><table><thead><tr>
                <?php foreach (array_keys($result[0]) as $c): ?>
                    <th><?= htmlspecialchars($c) ?></th>
                <?php endforeach; ?></tr></thead><tbody>
                <?php foreach ($result as $r): ?>
                    <tr><?php foreach ($r as $cell): ?>
                        <td><?= htmlspecialchars($cell) ?></td>
                    <?php endforeach; ?></tr>
                <?php endforeach; ?></tbody></table></div>
        <?php elseif (is_string($result)): ?>
            <div style="background:rgba(40,200,80,0.3);padding:12px;border-radius:6px;margin:10px 0;border:1px solid #3a8a3a;">
                <?= $result ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>