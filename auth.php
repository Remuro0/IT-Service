<?php
/**
 * auth.php — единая точка контроля авторизации
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['message'] = "❌ Необходимо войти в систему.";
        header("Location: login.php");
        exit;
    }
    
    // 🕒 Проверка таймаута
    if (isset($_SESSION['last_activity']) && isset($_SESSION['session_timeout'])) {
        $inactiveTime = time() - $_SESSION['last_activity'];
        if ($inactiveTime > $_SESSION['session_timeout']) {
            session_destroy();
            $_SESSION['message'] = "🔒 Сессия закрыта из-за неактивности.";
            header("Location: ../login.php");
            exit;
        }
    }
    
    // 🔄 Обновляем время активности
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $skipPaths = ['/api/get_system_metrics.php', '/api/captcha.php', '/api/mark_notification_read.php'];
    if (!in_array($currentPath, $skipPaths)) {
        $_SESSION['last_activity'] = time();
    }
    
    // 🧾 Загружаем роль и аватар из БД
    if (!isset($_SESSION['role'])) {
        require_once 'config.php';
        $pdo = getDBConnection(); // ✅ Добавлено
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password); // ✅ Добавлено
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            session_destroy();
            $_SESSION['message'] = "❌ Ошибка подключения к БД.";
            header("Location: login.php");
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT role, avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['role'] = $user['role'] ?? 'user';
            $_SESSION['avatar'] = $user['avatar'] ?? 'imang/default.png';
        } else {
            session_destroy();
            $_SESSION['message'] = "❌ Пользователь не найден.";
            header("Location: login.php");
            exit;
        }
    }
}

function logout() {
    session_destroy();
    header("Location: index.php");
    exit;
}

function extendSession($seconds = 900) {
    if ($_SESSION['role'] !== 'admin') return false;
    $seconds = max(60, min(604800, (int)$seconds));
    $_SESSION['session_timeout'] = $seconds;
    $_SESSION['last_activity'] = time();
    return true;
}
?>