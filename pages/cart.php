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
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}

// Получаем товары из корзины
$stmt = $pdo->prepare("
SELECT c.id AS cart_id, c.type, c.service_id, c.package_id,
s.name AS service_name, s.price AS service_price,
p.name AS package_name, p.price AS package_price
FROM cart c
LEFT JOIN services s ON c.service_id = s.id AND c.type = 'service'
LEFT JOIN tariff_plans p ON c.package_id = p.id AND c.type = 'package'
WHERE c.user_id = ?
ORDER BY c.added_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Считаем общую сумму
$total = 0;
foreach ($items as $item) {
    $price = ($item['type'] === 'service') ? $item['service_price'] : $item['package_price'];
    $total += (float)$price;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Моя корзина</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/cart.css">
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
    <h1 style="text-align: center; color: #c7b8ff;">Моя корзина</h1>

    <?php if (empty($items)): ?>
        <!-- Если корзина пуста -->
        <div class="empty-cart">
            <p>Ваша корзина пуста.</p>
            <a href="services.php" class="btn" style="background: linear-gradient(to right, #00c853, #64dd17); color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; margin-top: 10px;">Перейти к услугам</a>
        </div>
    <?php else: ?>
        <!-- Список товаров -->
        <div style="max-width: 800px; margin: 0 auto;">
            <?php foreach ($items as $item): ?>
                <?php 
                    $name = ($item['type'] === 'service') ? $item['service_name'] : $item['package_name'];
                    $price = ($item['type'] === 'service') ? $item['service_price'] : $item['package_price'];
                ?>
                <div class="cart-item">
                    <div>
                        <h3><?= htmlspecialchars($name) ?></h3>
                        <div class="price"><?= number_format($price, 0, '', ' ') ?> ₽</div>
                    </div>
                    <div>
                        <a href="../actions/remove_from_cart.php?action=single&id=<?= $item['cart_id'] ?>" 
                           class="remove-btn" 
                           onclick="return confirm('Удалить этот товар из корзины?')">
                           🗑️
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Итого и кнопка -->
            <div class="total">
                Итого к оплате: <strong><?= number_format($total, 0, '', ' ') ?> ₽</strong>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="payment_method.php" class="btn" style="background: linear-gradient(to right, #3a0d6a, #5a1a8f); color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                    Оформить заказ
                </a>
                <a href="services.php" class="btn" style="background: transparent; border: 1px solid #5a1a8f; color: #c7b8ff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; margin-left: 10px;">
                    Продолжить покупки
                </a>
            </div>
            
            <!-- Кнопка очистить всю корзину -->
            <div style="text-align: center; margin-top: 20px;">
                <a href="../actions/remove_from_cart.php?action=all" 
                   class="clear-cart" 
                   onclick="return confirm('Вы уверены, что хотите очистить всю корзину?')">
                    Очистить корзину
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>
</body>
</html>