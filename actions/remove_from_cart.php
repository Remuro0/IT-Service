<?php
session_start();
require_once '../auth.php';
requireAuth();

if ($_SESSION['role'] !== 'user') {
    $_SESSION['message'] = "❌ Операция доступна только пользователям.";
    header("Location: ../pages/cart.php");
    exit;
}

require_once '../config.php';
$pdo = getDBConnection();

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['message'] = "❌ Ошибка подключения к БД.";
    header("Location: ../pages/cart.php");
    exit;
}

$action = $_GET['action'] ?? '';
$cart_id = (int)($_GET['id'] ?? 0);

if ($action === 'single' && $cart_id > 0) {
    // Удалить одну запись
    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$cart_id, $_SESSION['user_id']])) {
        $_SESSION['message'] = "✅ Товар удалён из корзины.";
    } else {
        $_SESSION['message'] = "❌ Не удалось удалить товар.";
    }
} elseif ($action === 'all') {
    // Удалить всю корзину
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    if ($stmt->execute([$_SESSION['user_id']])) {
        $_SESSION['message'] = "✅ Корзина очищена.";
    } else {
        $_SESSION['message'] = "❌ Не удалось очистить корзину.";
    }
} else {
    $_SESSION['message'] = "❌ Некорректный запрос.";
}

header("Location: ../pages/cart.php");
exit;
?>