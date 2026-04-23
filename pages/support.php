<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';
if ($_SESSION['role'] !== 'user') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}
require_once '../config.php';
$error = '';
$success = '';
// Обработка отправки формы
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (empty($subject) || empty($message)) {
        $error = "Заполните тему и сообщение.";
    } else {
        try {
            $pdo = getDBConnection(); // ✅
            $stmt = $pdo->prepare("
                INSERT INTO support_tickets (user_id, subject, message, status, created_at)
                VALUES (?, ?, ?, 'open', NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $subject, $message]);
            logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'SUPPORT_TICKET_CREATED', "Тема: $subject");
            $success = "✅ Заявка создана. Мы свяжемся с вами в ближайшее время.";
        } catch (PDOException $e) {
            $error = "❌ Ошибка при создании заявки.";
        }
    }
}
// Загрузка заявок
try {
    $pdo = getDBConnection(); // ✅
    $stmt = $pdo->prepare("
        SELECT t.id, t.subject, t.message, t.status, t.created_at,
               u.username AS user_name
        FROM support_tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Техподдержка</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/support.css">
</head>
<body>
    <!-- Панель пользователя -->
    <div class="user-panel">
        <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? 'imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
        </div>
        <div class="user-menu">
            <a href="services.php">Услуги</a>
            <a href="cart.php">Корзина</a>
            <a href="purchased.php">Мои покупки</a>
            <a href="history.php">История</a>
            <a href="edit_profile.php">Профиль</a>
            <a href="billing.php">Биллинг</a>
            <a href="notifications.php">Уведомления</a>
            <a href="referral.php">Рефералы</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>
    <div class="content-wrapper">
        <h1 style="text-align: center; color: #c7b8ff;">Техническая поддержка</h1>
        <p style="text-align: center; color: #a090cc; margin-bottom: 30px;">
            Нужна помощь? Оставьте заявку — мы ответим в течение 24 часов.
        </p>
        <div class="support-container">
            <!-- Форма создания заявки -->
            <div class="ticket-form">
                <h3 style="color: #c7b8ff; margin-top: 0;">Создать новую заявку</h3>
                <?php if ($error): ?>
                    <div style="text-align: center; padding: 10px; background: rgba(200, 50, 50, 0.3); color: #ffaaaa; margin: 10px 0; border-radius: 6px;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div style="text-align: center; padding: 10px; background: rgba(40, 200, 80, 0.3); color: #aaffaa; margin: 10px 0; border-radius: 6px;">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="create_ticket">
                    <label style="display: block; color: #c7b8ff; margin-bottom: 4px;">Тема:</label>
                    <input type="text" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required
                           style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
                    <label style="display: block; color: #c7b8ff; margin-bottom: 4px; margin-top: 10px;">Сообщение:</label>
                    <textarea name="message" rows="6" required
                              style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    <button type="submit" style="background: linear-gradient(to right, #3a0d6a, #5a1a8f); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; margin-top: 10px;">Отправить заявку</button>
                </form>
            </div>
            <!-- Список заявок -->
            <h3 style="color: #c7b8ff; margin-top: 40px;">Ваши заявки</h3>
            <?php if (empty($tickets)): ?>
                <div class="empty-tickets">У вас пока нет заявок.</div>
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
                            <?= htmlspecialchars($ticket['message']) ?>
                        </div>
                        <div class="ticket-date">
                            Создана: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($ticket['created_at']))) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>