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

// Проверяем, был ли email уже подтверждён
try {
    $pdo = getDBConnection(); // ✅ Универсальное подключение
    $stmt = $pdo->prepare("SELECT last_verification_code_sent_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $last_sent = $stmt->fetchColumn();
    if ($last_sent) {
        // Уже подтверждён → пропускаем
        unset($_SESSION['verification_code']);
        unset($_SESSION['pending_email']);
        header("Location: payment_method.php");
        exit;
    }
} catch (PDOException $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}

// Обработка ввода кода
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $entered_code = trim($_POST['code'] ?? '');
    if (empty($entered_code)) {
        $error = "❌ Код подтверждения обязателен.";
    } elseif ($entered_code === ($_SESSION['verification_code'] ?? '')) {
        try {
            $email = $_SESSION['pending_email'] ?? '';
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                $_SESSION['email'] = $email;
                logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'EMAIL_VERIFIED', "Email: $email");
            }
            unset($_SESSION['verification_code']);
            unset($_SESSION['pending_email']);
            $_SESSION['message'] = "✅ Email подтверждён.";
            header("Location: payment_method.php");
            exit;
        } catch (PDOException $e) {
            $error = "❌ Ошибка при сохранении email.";
        }
    } else {
        $error = "❌ Неверный код подтверждения.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подтвердите Email</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/verify_email.css">
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
            <a href="edit_profile.php">Профиль</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>
    <div class="content-wrapper">
        <div class="verification-box">
            <h2>Подтвердите Email</h2>
            <p>На ваш email был отправлен код подтверждения. Введите его ниже.</p>
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="code" placeholder="Введите код подтверждения" required>
                <button type="submit">Подтвердить</button>
            </form>
        </div>
    </div>
</body>
</html>