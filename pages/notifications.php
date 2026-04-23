<?php
session_start();
require_once '../auth.php';
requireAuth();

// Только для пользователей
if ($_SESSION['role'] !== 'user') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';

try {
    // ✅ ИСПОЛЬЗУЕМ getDBConnection() для поддержки Local/Global
    $pdo = getDBConnection();
    
    // Получаем уведомления пользователя
    $stmt = $pdo->prepare("
        SELECT id, type, title, message, created_at, is_read
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Отмечаем все как прочитанные
    $stmt_mark = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt_mark->execute([$_SESSION['user_id']]);
    
} catch (PDOException $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Уведомления</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        .notification-item {
            background: rgba(30, 25, 45, 0.8);
            border: 1px solid rgba(100, 60, 180, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        .notification-item.unread {
            border-left: 4px solid #ffcc00;
        }
        .notification-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .notification-type {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .notification-type.info { background: rgba(40, 200, 80, 0.3); color: #aaffaa; }
        .notification-type.warning { background: rgba(255, 165, 0, 0.3); color: #ffcc66; }
        .notification-type.error { background: rgba(200, 50, 50, 0.3); color: #ffaaaa; }
        .notification-type.critical { background: rgba(255, 107, 107, 0.3); color: #ff6b6b; }
        .notification-title {
            color: #c7b8ff;
            margin: 0 0 10px;
            font-size: 1.1rem;
        }
        .notification-content {
            color: #d0d0d0;
            line-height: 1.5;
        }
        .notification-date {
            font-size: 0.9rem;
            color: #a090cc;
            text-align: right;
        }
        .empty-notifications {
            text-align: center;
            color: #aaa;
            font-size: 1.2rem;
            margin: 60px 0;
        }
    </style>
</head>
<body>
    <!-- Панель пользователя -->
    <div class="user-panel">
        <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? 'imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
        </div>
        <div class="user-menu">
            <a href="user_dashboard.php">Главная</a>
            <a href="cart.php">Корзина</a>
            <a href="purchased.php">Мои покупки</a>
            <a href="history.php">История</a>
            <a href="support.php">Поддержка</a>
            <a href="edit_profile.php">Профиль</a>
            <a href="billing.php">Биллинг</a>
            <a href="referral.php">Рефералы</a>
            <a href="notifications.php" class="active">Уведомления</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>
    
    <div class="content-wrapper">
        <h1 style="text-align: center; color: #c7b8ff;">Уведомления</h1>
        
        <?php if (empty($notifications)): ?>
        <div class="empty-notifications">У вас пока нет уведомлений.</div>
        <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $notif): ?>
            <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>">
                <div class="notification-header">
                    <span class="notification-type <?= strtolower(htmlspecialchars($notif['type'])) ?>">
                        <?= htmlspecialchars($notif['type']) ?>
                    </span>
                    <span class="notification-date">
                        <?= date('d.m.Y H:i', strtotime($notif['created_at'])) ?>
                    </span>
                </div>
                <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                <div class="notification-content"><?= nl2br(htmlspecialchars($notif['message'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="user_dashboard.php" class="btn" style="background: linear-gradient(to right, #3a0d6a, #5a1a8f); color: white;">Назад</a>
        </div>
    </div>
    
    <?php include '../footer.php'; ?>
</body>
</html>