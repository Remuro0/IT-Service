<?php
session_start();

// Если уже авторизован — перенаправляем
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'user':
            header("Location: pages/user_dashboard.php");
            break;
        case 'engineer':
            header("Location: pages/engineer_dashboard.php");
            break;
        case 'manager':
            header("Location: pages/manager_dashboard.php");
            break;
        case 'db_admin':
            header("Location: pages/db_admin_dashboard.php");
            break;
        case 'admin':
            header("Location: pages/view_db.php");
            break;
        default:
            header("Location: index.php");
    }
    exit;
}

// Подключение к БД — используем config.php
require_once 'config.php';

$services = [];
$db_error = false;

try {
    // Используем getDBConnection() для правильного подключения
    $pdo = getDBConnection();
    
    // Загрузка услуг с id 9, 10, 11, 12
    $stmt = $pdo->query("SELECT * FROM services WHERE id IN (9, 10, 11, 12)");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Если БД недоступна — продолжаем без услуг
    $db_error = true;
    $services = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>ИТ-Сервис — Добро пожаловать</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="header">
        <h1>Добро пожаловать в ИТ-Сервис</h1>
        <p>Профессиональное управление инфраструктурой, безопасность и поддержка 24/7</p>
    </div>
    
    <?php if ($db_error): ?>
    <div style="text-align: center; padding: 20px; background: rgba(200, 50, 50, 0.3); color: #ffaaaa; margin: 20px auto; max-width: 600px; border-radius: 8px;">
        ⚠️ База данных временно недоступна. Попробуйте позже.
    </div>
    <?php endif; ?>
    
    <div class="services-grid">
        <?php if (empty($services)): ?>
        <div class="service-card">
            <h3>🔧 Услуги</h3>
            <p>Раздел услуг временно недоступен</p>
        </div>
        <div class="service-card">
            <h3>📦 Пакеты</h3>
            <p>Раздел пакетов временно недоступен</p>
        </div>
        <div class="service-card">
            <h3>☁️ Хостинг</h3>
            <p>Раздел хостинга временно недоступен</p>
        </div>
        <div class="service-card">
            <h3>🔒 Безопасность</h3>
            <p>Раздел безопасности временно недоступен</p>
        </div>
        <?php else: ?>
        <?php foreach ($services as $service): ?>
        <div class="service-card">
            <h3><?= htmlspecialchars($service['name']) ?></h3>
            <p><?= htmlspecialchars($service['description']) ?></p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- 5-я карточка — переход на страницу входа -->
        <div class="service-card">
            <h3>Все услуги</h3>
            <p>Посмотрите полный список наших услуг.</p>
            <a href="login.php" style="background: linear-gradient(to right, #00c853, #64dd17); color: white; text-decoration: none; margin-top: 10px; display: inline-block; padding: 10px 20px; border-radius: 6px;">Перейти</a>
        </div>
    </div>
    
    <div class="support-section">
        <h2>🛠️ Техническая поддержка</h2>
        <p>Нужна помощь? Мы всегда на связи!</p>
        <div class="support-info">
            <div class="contact-item">
                <span class="icon">📧</span>
                <span class="text">support@it-service.com</span>
            </div>
            <div class="contact-item">
                <span class="icon">📞</span>
                <span class="text">+7 (999) 123-45-67</span>
            </div>
            <div class="contact-item">
                <span class="icon">🕒</span>
                <span class="text">Работаем 24/7</span>
            </div>
        </div>
    </div>
    
    <div class="actions">
        <a href="login.php" class="btn btn-login">Войти в систему</a>
        <a href="register.php" class="btn btn-register">Зарегистрироваться</a>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>