<?php
session_start();
require_once '../auth.php';
requireAuth();

if ($_SESSION['role'] !== 'user') {
    $_SESSION['message'] = "❌ Добавлять в корзину могут только пользователи.";
    header("Location: ../pages/services.php");
    exit;
}

require_once '../config.php';
$pdo = getDBConnection();

$type = $_GET['type'] ?? 'service'; // 'service' или 'package'
$id = (int)($_GET['id'] ?? 0);

if (!in_array($type, ['service', 'package']) || $id <= 0) {
    $_SESSION['message'] = "❌ Некорректные параметры.";
    header("Location: ../pages/services.php");
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Проверка существования
    if ($type === 'service') {
        $stmt = $pdo->prepare("SELECT id FROM services WHERE id = ? AND price > 0");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $_SESSION['message'] = "❌ Услуга недоступна.";
            header("Location: ../pages/services.php");
            exit;
        }
        $col_id = 'service_id';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM tariff_plans WHERE id = ? AND price > 0");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $_SESSION['message'] = "❌ Пакет недоступен.";
            header("Location: ../pages/services.php");
            exit;
        }
        $col_id = 'package_id';
    }

    // Проверка дубликата
    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND type = ? AND `$col_id` = ?");
    $stmt->execute([$_SESSION['user_id'], $type, $id]);
    if ($stmt->fetch()) {
        $_SESSION['message'] = "ℹ️ Уже в корзине.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, `$col_id`, type) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $id, $type]);
        $_SESSION['message'] = "✅ Добавлено в корзину!";
    }

} catch (PDOException $e) {
    $_SESSION['message'] = "❌ Ошибка при добавлении в корзину.";
}

header("Location: ../pages/services.php");
exit;
?>