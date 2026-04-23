<?php
session_start();
require_once '../auth.php';
requireAuth();

// 🔒 Только для инженера
if ($_SESSION['role'] !== 'engineer') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';
try {
    $pdo = getDBConnection(); // ✅ Универсальное подключение
    // Загружаем серверы
    $stmt = $pdo->prepare("SELECT * FROM servers ORDER BY name");
    $stmt->execute();
    $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Серверы</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/view_servers.css">
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
        <h1 style="text-align: center; color: #c7b8ff;">Серверы</h1>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>IP-адрес</th>
                        <th>Статус</th>
                        <th>Описание</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servers as $server): ?>
                        <tr>
                            <td><?= (int)$server['id'] ?></td>
                            <td><?= htmlspecialchars($server['name']) ?></td>
                            <td><?= htmlspecialchars($server['ip_address']) ?></td>
                            <td>
                                <span class="status-<?= strtolower(htmlspecialchars($server['status'])) ?>">
                                    <?= htmlspecialchars($server['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($server['description'] ?? '') ?></td>
                            <td class="actions">
                                <a href="update_server.php?id=<?= (int)$server['id'] ?>" class="btn-edit">✏️ Обновить</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>