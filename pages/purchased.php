<?php
session_start();
require_once '../auth.php';
requireAuth();
if ($_SESSION['role'] !== 'user') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}
require_once '../config.php';
try {
    $pdo = getDBConnection(); // ✅
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
// Загружаем ТОЛЬКО купленные товары из purchases
$stmt = $pdo->prepare("
    SELECT p.item_type, p.service_id, p.package_id, p.price, p.purchased_at,
           s.name AS service_name,
           t.name AS package_name
    FROM purchases p
    LEFT JOIN services s ON p.service_id = s.id
    LEFT JOIN tariff_plans t ON p.package_id = t.id
    WHERE p.user_id = ?
    ORDER BY p.purchased_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мои покупки</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/purchased.css">
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
            <a href="notifications.php">Уведомления</a>
            <a href="referral.php">Рефералы</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>
    <div class="content-wrapper">
        <h1 style="text-align: center; color: #c7b8ff;">Мои покупки</h1>
        <?php if (empty($items)): ?>
            <div class="empty-purchases">
                <img src="../imang/default.png" alt="Нет покупок">
                У вас пока нет покупок.
                <a href="services.php" class="buy-more-btn">Купить услуги</a>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="purchased-item">
                    <?php if ($item['item_type'] === 'service'): ?>
                        <h3>Услуга: <?= htmlspecialchars($item['service_name']) ?></h3>
                    <?php else: ?>
                        <h3>Пакет: <?= htmlspecialchars($item['package_name']) ?></h3>
                    <?php endif; ?>
                    <div class="price">Цена: <?= number_format($item['price'], 0, '', ' ') ?> ₽</div>
                    <div class="date">Куплено: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($item['purchased_at']))) ?></div>
                </div>
            <?php endforeach; ?>
            <div style="text-align: center; margin-top: 30px;">
                <a href="services.php" class="buy-more-btn">Купить ещё</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>