<?php
session_start();
require_once '../auth.php';

// 💡 Вспомогательная функция для получения имени пользователя
function getUserName($pdo, $user_id) {
    if (!$user_id) return '—';
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ? htmlspecialchars($user['username']) : "ID: $user_id";
}

// 🔒 Только для инженера
if ($_SESSION['role'] !== 'engineer') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';
try {
    $pdo = getDBConnection(); // ✅ Используем универсальное подключение
    // Загружаем все инциденты
    $stmt = $pdo->query("SELECT * FROM incidents ORDER BY created_at DESC");
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Все инциденты</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/view_incidents.css">
</head>
<body>
    <!-- Панель пользователя -->
    <div class="user-panel">
        <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? 'imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username']) ?></strong><br>
            <span class="role">Роль: <?= htmlspecialchars($_SESSION['role']) ?></span>
        </div>
        <div class="user-menu">
            <a href="engineer_dashboard.php">🏠 Главная</a>
            <a href="view_incidents.php">🚨 Инциденты</a>
            <a href="view_servers.php">💻 Серверы</a>
            <a href="view_network.php">🌐 Сеть</a>
            <a href="edit_profile.php">✏️ Профиль</a>
            <hr>
            <a href="../logout.php">🚪 Выйти</a>
        </div>
    </div>
    <div class="content-wrapper">
        <h1 style="text-align: center; color: #c7b8ff;">Все инциденты</h1>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Заголовок</th>
                        <th>Статус</th>
                        <th>Назначен</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incidents as $i): ?>
                        <tr>
                            <td><?= (int)$i['id'] ?></td>
                            <td><?= htmlspecialchars($i['title']) ?></td>
                            <td>
                                <span class="incident-status <?= htmlspecialchars($i['status']) ?>">
                                    <?= htmlspecialchars($i['status']) ?>
                                </span>
                            </td>
                            <td><?= getUserName($pdo, $i['assigned_to']) ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($i['created_at'])) ?></td>
                            <td class="actions">
                                <a href="update_incident.php?id=<?= (int)$i['id'] ?>" class="btn-edit">✏️ Обновить</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>