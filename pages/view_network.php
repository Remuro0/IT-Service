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
    // Загружаем сетевые устройства
    $stmt = $pdo->prepare("SELECT * FROM `network_devices` ORDER BY `name`");
    $stmt->execute();
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сетевые устройства</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/view_network.css">
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
        <h1 style="text-align: center; color: #c7b8ff;">Сетевые устройства</h1>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>IP-адрес</th>
                        <th>Тип</th>
                        <th>Статус</th>
                        <th>Последняя проверка</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $device): ?>
                        <tr>
                            <td><?= (int)$device['id'] ?></td>
                            <td><?= htmlspecialchars($device['name']) ?></td>
                            <td><?= htmlspecialchars($device['ip_address']) ?></td>
                            <td><?= htmlspecialchars($device['device_type']) ?></td>
                            <td>
                                <span class="status-<?= strtolower(htmlspecialchars($device['status'])) ?>">
                                    <?= htmlspecialchars($device['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($device['last_checked']) ?></td>
                            <td class="actions">
                                <a href="update_device.php?id=<?= (int)$device['id'] ?>" class="btn-edit">✏️ Обновить</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>