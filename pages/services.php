<?php
session_start();
require_once '../auth.php';
requireAuth();
// Только для пользователя
if ($_SESSION['role'] !== 'user') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}
require_once '../config.php';
try {
    $pdo = getDBConnection(); // ✅ Универсальное подключение
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
// Загружаем ТОЛЬКО услуги с ценой > 0
$stmt = $pdo->prepare("SELECT id, name, description, price FROM services WHERE price > 0 ORDER BY sort_order");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Загружаем пакеты
$stmt = $pdo->query("SELECT * FROM tariff_plans ORDER BY sort_order LIMIT 3");
$packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Наши услуги — ИТ-Сервис</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/services.css">
</head>
<body>
    <!-- Панель пользователя -->
    <div class="user-panel">
        <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? 'imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username']) ?></strong><br>
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
        <div class="header">
            <h1>Наши услуги</h1>
            <p>Профессиональное управление инфраструктурой, безопасность и поддержка 24/7</p>
        </div>
        <div class="services-page">
            <?php if (empty($services)): ?>
                <div class="no-services">Нет доступных платных услуг.</div>
            <?php else: ?>
                <div class="services-grid">
                    <?php foreach ($services as $service): ?>
                        <div class="service-card">
                            <h3><?= htmlspecialchars($service['name']) ?></h3>
                            <p><?= htmlspecialchars($service['description']) ?></p>
                            <div class="price">Цена: <?= number_format($service['price'], 0, '', ' ') ?> ₽</div>
                            <a href="../actions/add_to_cart.php?type=service&id=<?= (int)$service['id'] ?>" class="btn-cart">Добавить в корзину</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <!-- Пакеты -->
        <div class="packages-section">
            <h2>Наши пакеты</h2>
            <div class="packages-grid">
                <?php foreach ($packages as $package): ?>
                    <div class="package-card">
                        <h3><?= htmlspecialchars($package['name']) ?></h3>
                        <div class="price">Цена: <?= number_format($package['price'], 0, '', ' ') ?> ₽</div>
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
                        <a href="../actions/add_to_cart.php?type=package&id=<?= (int)$package['id'] ?>" class="btn">Выбрать пакет</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="actions">
            <a href="user_dashboard.php" class="btn btn-back">Назад</a>
            <a href="../logout.php" class="btn btn-logout">Выйти</a>
        </div>
    </div>
    <?php include '../footer.php'; ?>
</body>
</html>