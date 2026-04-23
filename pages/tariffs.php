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
    $pdo = getDBConnection(); // ✅ Универсальное подключение
    // Загружаем тарифные планы
    $stmt = $pdo->query("SELECT * FROM tariff_plans ORDER BY sort_order");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Загружаем отдельные услуги
    $stmt = $pdo->query("SELECT id, name, description, price FROM services WHERE price > 0 ORDER BY sort_order");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тарифные планы — ИТ-Сервис</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/tariffs.css">
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
            <a href="notifications.php">Уведомления</a>
            <a href="referral.php">Рефералы</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="header">
            <h1>Тарифные планы</h1>
            <p>Выберите подходящий пакет услуг для вашего бизнеса</p>
        </div>

        <!-- Тарифные пакеты -->
        <div class="tariffs-section">
            <h2>Наши тарифные планы</h2>
            <div class="packages-grid">
                <?php foreach ($packages as $package): ?>
                    <div class="package-card <?= $package['is_recommended'] ? 'recommended' : '' ?>">
                        <h3><?= htmlspecialchars($package['name']) ?></h3>
                        <div class="price">
                            <?= number_format($package['price'], 0, '', ' ') ?> ₽
                        </div>
                        <div class="price-label">в месяц</div>
                        <ul>
                            <?php 
                            $features = explode("\n", $package['features']);
                            foreach ($features as $feature):
                                if (!empty(trim($feature))):
                            ?>
                                <li><?= htmlspecialchars(trim($feature)) ?></li>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </ul>
                        <a href="../actions/add_to_cart.php?type=package&id=<?= (int)$package['id'] ?>" class="btn">
                            <?= $package['is_recommended'] ? 'Рекомендуем' : 'Выбрать' ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Отдельные услуги -->
        <div class="services-list">
            <h2>Отдельные услуги</h2>
            <?php if (empty($services)): ?>
                <p style="text-align: center; color: #aaa;">Нет доступных услуг.</p>
            <?php else: ?>
                <?php foreach ($services as $service): ?>
                    <div class="service-item">
                        <div class="desc">
                            <strong><?= htmlspecialchars($service['name']) ?></strong><br>
                            <?= htmlspecialchars($service['description']) ?>
                        </div>
                        <div class="price"><?= number_format($service['price'], 0, '', ' ') ?> ₽</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Карточка "Подключите услуги" -->
        <div class="connect-services">
            <h3>Не нашли подходящий тариф?</h3>
            <p>Свяжитесь с нашим менеджером для подбора индивидуального решения под ваши задачи.</p>
            <a href="support.php" class="btn">Написать менеджеру</a>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>