<?php
session_start();
require_once '../auth.php';
requireAuth();
// Добавляем эту строку для подключения конфигурации и функции getDBConnection()
require_once '../config.php';

if ($_SESSION['role'] !== 'engineer') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

// Теперь можно безопасно использовать getDBConnection()
try {
    $pdo = getDBConnection(); // ✅ Универсальное подключение
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}

require_once '../config.php';
$pdo = getDBConnection();

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Загружаем открытые инциденты
    $stmt = $pdo->prepare("
        SELECT i.id, i.title, i.description, i.status, i.assigned_to, i.created_at,
               u.username AS assigned_username
        FROM incidents i
        LEFT JOIN users u ON i.assigned_to = u.id
        WHERE i.status IN ('open', 'in_progress')
        ORDER BY i.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Загружаем серверы
    $stmt = $pdo->prepare("SELECT id, name, ip_address, status FROM servers ORDER BY name");
    $stmt->execute();
    $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель инженера</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/engineer_dashboard.css">
</head>
<body>
    <!-- Панель пользователя -->
    <div class="user-panel">
        <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? '../imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Инженер') ?></strong><br>
            <span class="role">Роль: <?= htmlspecialchars($_SESSION['role'] ?? 'user') ?></span>
        </div>
        <div class="user-menu">
            <a href="engineer_dashboard.php">Главная</a>
            <a href="view_incidents.php">Инциденты</a>
            <a href="view_servers.php">Серверы</a>
            <a href="view_network.php">Сеть</a>
            <a href="edit_profile.php">Профиль</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>

    <div class="content-wrapper">
        <?php if (!isset($_SESSION['welcome_shown'])): ?>
            <div style="text-align: center; padding: 12px; background: rgba(40, 100, 40, 0.3); color: #aaffaa; margin: 10px auto; max-width: 600px; border-radius: 8px; border: 1px solid #3a8a3a;">
                Добро пожаловать в систему, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>! Ваша роль: <strong><?= htmlspecialchars($_SESSION['role']) ?></strong>.
            </div>
            <?php $_SESSION['welcome_shown'] = true; ?>
        <?php endif; ?>

        <div class="engineer-panel">
            <h1 class="engineer-header">Панель инженера</h1>

            <!-- Активные инциденты -->
            <div class="incidents-section">
                <h2 style="color: #c7b8ff; margin-top: 0; margin-bottom: 15px;">Активные инциденты</h2>
                <?php if (empty($incidents)): ?>
                    <p style="color: #aaa; text-align: center;">Нет активных инцидентов.</p>
                <?php else: ?>
                    <?php foreach ($incidents as $incident): ?>
                        <div class="incident-item">
                            <div class="incident-title"><?= htmlspecialchars($incident['title']) ?></div>
                            <div>Назначен: <?= htmlspecialchars($incident['assigned_username'] ?? '—') ?></div>
                            <div class="incident-status <?= $incident['status'] === 'open' ? 'status-open' : 'status-in_progress' ?>">
                                <?= htmlspecialchars($incident['status']) ?>
                            </div>
                            <div class="incident-actions">
                                <a href="update_incident.php?id=<?= $incident['id'] ?>">Обновить статус</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Серверы -->
            <div class="servers-section">
                <h2 style="color: #c7b8ff; margin-top: 0; margin-bottom: 15px;">Серверы</h2>
                <?php if (empty($servers)): ?>
                    <p style="color: #aaa; text-align: center;">Нет данных о серверах.</p>
                <?php else: ?>
                    <?php foreach ($servers as $server): ?>
                        <div class="server-item">
                            <div class="server-name"><?= htmlspecialchars($server['name']) ?></div>
                            <div class="server-ip">IP: <?= htmlspecialchars($server['ip_address']) ?></div>
                            <div class="server-status <?= $server['status'] === 'online' ? 'status-online' : ($server['status'] === 'offline' ? 'status-offline' : 'status-maintenance') ?>">
                                <?= htmlspecialchars($server['status']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>