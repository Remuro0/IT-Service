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
$user_id = $_SESSION['user_id'];
// Получаем или генерируем реферальный код
$stmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || empty($user['referral_code'])) {
    $referral_code = $user_id . '-' . substr(md5($_SESSION['username']), 0, 8);
    $stmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
    $stmt->execute([$referral_code, $user_id]);
} else {
    $referral_code = $user['referral_code'];
}
// Считаем приглашённых — ИСПРАВЛЕНО: поиск по referral_code
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referral_code = ?");
$stmt->execute([$referral_code]);
$invited = (int)$stmt->fetchColumn();
// Считаем бонусы — ИСПРАВЛЕНО: join по referral_code
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(p.price), 0)
    FROM purchases p
    JOIN users u ON p.user_id = u.id
    WHERE u.referral_code = ?
");
$stmt->execute([$referral_code]);
$total_bonus = round($stmt->fetchColumn() * 0.1, 2);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Реферальная программа</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/referral.css">
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
            <a href="support.php">Поддержка</a>
            <a href="edit_profile.php">Профиль</a>
            <a href="billing.php">Биллинг</a>
            <a href="notifications.php">Уведомления</a>
            <a href="referral.php">Рефералы</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>
    <div class="content-wrapper">
        <div class="referral-container">
            <h2 class="referral-header">
                <span class="icon">🌟</span>
                Реферальная программа
            </h2>
            <p class="referral-intro">Приглашайте друзей и получайте бонусы!</p>
            <div class="referral-link-box">
                <input type="text" value="https://<?php echo $_SERVER['HTTP_HOST']; ?>/register.php?ref=<?= urlencode($referral_code) ?>" readonly>
                <button class="referral-copy-btn" onclick="copyReferralLink()">Скопировать ссылку</button>
            </div>
            <div class="referral-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= $invited ?></div>
                    <div class="stat-label">Приглашено</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($total_bonus, 2, '.', ' ') ?> ₽</div>
                    <div class="stat-label">Бонусы</div>
                </div>
            </div>
            <p class="referral-info">
                За каждого друга, который зарегистрируется по вашей ссылке и совершит покупку, вы получаете 10% от суммы его первой покупки.
            </p>
            <a href="user_dashboard.php" class="back-btn">Назад</a>
        </div>
    </div>
    <script>
        function copyReferralLink() {
            const link = document.querySelector('.referral-link-box input').value;
            navigator.clipboard.writeText(link).then(() => {
                alert('Ссылка скопирована!');
            }).catch(() => {
                alert('Не удалось скопировать.');
            });
        }
    </script>
</body>
</html>