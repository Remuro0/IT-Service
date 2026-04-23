<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';
// Только для менеджера и выше
if (!in_array($_SESSION['role'], ['manager', 'admin'])) {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}
require_once '../config.php';
try {
    $pdo = getDBConnection(); // ✅ Универсальное подключение
    // Все заявки
    $stmt = $pdo->query("
        SELECT t.id, t.subject, t.message, t.status, t.created_at,
               u.username AS user_name
        FROM support_tickets t
        JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
    ");
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Все заявки поддержки</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/support.css">
    <link rel="stylesheet" href="../css/support_tickets_all.css">
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
            <a href="manager_dashboard.php">Главная</a>
            <a href="view_billing_summary.php">Биллинг</a>
            <a href="support_tickets_all.php">Поддержка</a>
            <a href="edit_profile.php">Профиль</a>
            <hr>
            <a href="../logout.php" class="logout">Выход</a>
        </div>
    </div>

    <div class="content-wrapper">
        <h1 style="text-align: center; color: #c7b8ff;">Все заявки поддержки</h1>
        <p style="text-align: center; color: #a090cc; margin-bottom: 30px;">
            Полный список обращений пользователей.
        </p>

        <div class="support-container">
            <?php if (empty($tickets)): ?>
                <div class="empty-tickets">Заявок пока нет.</div>
            <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-item">
                        <div class="ticket-header">
                            <h4><?= htmlspecialchars($ticket['subject']) ?></h4>
                            <span class="ticket-status <?= $ticket['status'] === 'open' ? 'status-open' : 'status-closed' ?>">
                                <?= $ticket['status'] === 'open' ? 'Открыта' : 'Закрыта' ?>
                            </span>
                        </div>
                        <div class="ticket-body">
                            <?= nl2br(htmlspecialchars($ticket['message'])) ?>
                        </div>
                        <div class="ticket-date">
                            Пользователь: <strong><?= htmlspecialchars($ticket['user_name']) ?></strong><br>
                            Дата: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($ticket['created_at']))) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>