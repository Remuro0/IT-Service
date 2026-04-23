<?php
session_start();
require_once '../auth.php';
requireAuth();
if ($_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}
require_once '../config.php';
try {
    $pdo = getDBConnection(); // ✅ Универсальное подключение
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
$stmt = $pdo->prepare("
    SELECT p.id, p.item_type, p.service_id, p.package_id, p.price, p.purchased_at,
           s.name AS service_name,
           t.name AS package_name
    FROM purchases p
    LEFT JOIN services s ON p.service_id = s.id
    LEFT JOIN tariff_plans t ON p.package_id = t.id
    WHERE p.user_id = ?
    ORDER BY p.purchased_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Биллинг</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/billing.css">
</head>
<body>
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
            <a href="notifications.php">Уведомления</a>
            <a href="referral.php">Рефералы</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>
    <div class="content-wrapper">
        <h1 style="text-align: center; color: #c7b8ff;">Биллинг</h1>
        <p style="text-align: center; color: #a090cc; margin-bottom: 30px;">Все ваши платежи за услуги и пакеты.</p>
        <div class="billing-container">
            <?php if (empty($purchases)): ?>
                <div class="empty-billing">У вас пока нет платежей.</div>
            <?php else: ?>
                <?php foreach ($purchases as $purchase): ?>
                    <div class="invoice-item">
                        <div class="invoice-header">
                            <h3 class="invoice-title">
                                <?= htmlspecialchars($purchase['item_type'] === 'service' ? $purchase['service_name'] : $purchase['package_name']) ?>
                            </h3>
                            <span class="invoice-id">ID: <?= htmlspecialchars($purchase['id']) ?></span>
                        </div>
                        <div class="invoice-details">
                            <span class="invoice-total">Сумма: <?= number_format($purchase['price'], 0, '', ' ') ?> ₽</span>
                            <span class="invoice-date">Дата: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($purchase['purchased_at']))) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>