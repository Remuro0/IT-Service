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
    die("Ошибка подключения к БД: " . htmlspecialchars($e->getMessage()));
}
// Загружаем 4 услуги (9–12)
$stmt = $pdo->query("SELECT * FROM services WHERE id IN (9, 10, 11, 12)");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>ИТ-Сервис — Добро пожаловать</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
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
        <div class="header">
            <h1>Добро пожаловать в ИТ-Сервис</h1>
            <p>Профессиональное управление инфраструктурой, безопасность и поддержка 24/7</p>
        </div>
        <div class="services-grid">
            <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <h3><?= htmlspecialchars($service['name']) ?></h3>
                    <p><?= htmlspecialchars($service['description']) ?></p>
                </div>
            <?php endforeach; ?>
            <!-- Все услуги -->
            <div class="service-card">
                <h3>Все услуги</h3>
                <p>Посмотрите полный список наших услуг.</p>
                <a href="services.php" class="btn" style="background: linear-gradient(to right, #00c853, #64dd17); color: white; text-decoration: none; margin-top: 10px; display: inline-block; padding: 10px 20px; border-radius: 6px;">Перейти</a>
            </div>
        </div>
        <div class="support-section">
            <h2>🛠️ Техническая поддержка</h2>
            <p>Нужна помощь? Мы всегда на связи!</p>
            <div class="support-info">
                <div class="contact-item"><span class="icon">📧</span> support@it-service.com</div>
                <div class="contact-item"><span class="icon">📞</span> +7 (999) 123-45-67</div>
                <div class="contact-item"><span class="icon">🕒</span> Работаем 24/7</div>
            </div>
        </div>
    </div>
    <?php include '../footer.php'; ?>
</body>
</html>