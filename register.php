<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'log_action.php';

// Если уже авторизован — редирект
if (isset($_SESSION['user_id'])) {
    header("Location: pages/view_db.php");
    exit;
}

$error = '';
$success = '';

if ($_POST) {
    // Подключаем config.php — получаем функцию getDBConnection()
    require_once 'config.php';
    
    $form_username = trim($_POST['username'] ?? '');
    $form_password = $_POST['password'] ?? '';
    $form_confirm = $_POST['confirm_password'] ?? '';
    $form_email = trim($_POST['email'] ?? '');
    
    // Валидация
    if (empty($form_username) || empty($form_password)) {
        $error = "Логин и пароль обязательны.";
    } elseif ($form_password !== $form_confirm) {
        $error = "Пароли не совпадают.";
    } elseif (strlen($form_password) < 6) {
        $error = "Пароль должен быть не короче 6 символов.";
    } else {
        try {
            // ✅ ИСПОЛЬЗУЕМ getDBConnection() для поддержки Local/Global
            $pdo = getDBConnection();
            
            // Проверка на существование логина
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$form_username]);
            if ($stmt->fetch()) {
                $error = "Пользователь с таким логином уже существует.";
            } else {
                // Определяем имя поля для хэша пароля
                $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
                $password_col = null;
                foreach (['password_hash', 'PASSWORD_HASH', 'password', 'pass', 'passwd'] as $field) {
                    if (in_array($field, $columns)) {
                        $password_col = $field;
                        break;
                    }
                }
                
                if (!$password_col) {
                    $error = "В таблице users нет поля для пароля.";
                } else {
                    $hashed = password_hash($form_password, PASSWORD_DEFAULT);
                    $role = 'user';
                    $email = $form_email ?: 'no-email@example.com';
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, `$password_col`, email, role, created_at, password_created_at)
                        VALUES (?, ?, ?, ?, NOW(), NOW())
                    ");
                    
                    if ($stmt->execute([$form_username, $hashed, $email, $role])) {
                        // ✅ Получаем ID нового пользователя
                        $userId = $pdo->lastInsertId();
                        
                        // ✅ Логируем регистрацию
                        logAction($pdo, $userId, $form_username, 'REGISTER_SUCCESS', "Email: $email");
                        
                        // ✅ Устанавливаем сессию
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['username'] = $form_username;
                        $_SESSION['role'] = 'user';
                        $_SESSION['avatar'] = 'imang/default.png';
                        $_SESSION['last_activity'] = time();
                        $_SESSION['session_timeout'] = 900;
                        $_SESSION['db_mode'] = 'local'; // 🔥 Устанавливаем локальный режим по умолчанию
                        
                        $success = "✅ Регистрация успешна! Теперь вы можете войти.";
                        
                        // Редирект через 2 секунды
                        header("Refresh: 2; url=login.php");
                    } else {
                        $error = "❌ Ошибка при сохранении.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "❌ Ошибка БД: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="auth-box">
        <h2>Регистрация</h2>
        
        <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Логин" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            <input type="email" name="email" placeholder="Email (необязательно)" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <input type="password" name="password" placeholder="Пароль (мин. 6 символов)" required>
            <input type="password" name="confirm_password" placeholder="Повторите пароль" required>
            <button type="submit" class="register-button">Зарегистрироваться</button>
        </form>
        <?php endif; ?>
        
        <div class="auth-links">
            Уже есть аккаунт? <a href="login.php">Войти</a>
        </div>
    </div>
</body>
</html>