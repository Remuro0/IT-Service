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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    try {
        $pdo = getDBConnection(); // ✅
        // Получаем товары из корзины
        $stmt = $pdo->prepare("
            SELECT c.id AS cart_id, c.type, c.service_id, c.package_id,
                   COALESCE(s.price, p.price) AS price
            FROM cart c
            LEFT JOIN services s ON c.service_id = s.id AND c.type = 'service'
            LEFT JOIN tariff_plans p ON c.package_id = p.id AND c.type = 'package'
            WHERE c.user_id = ? AND c.added_at IS NOT NULL
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            $_SESSION['message'] = "❌ Корзина пуста.";
            header("Location: cart.php");
            exit;
        }

        $pdo->beginTransaction();
        // Копируем в purchases
        foreach ($items as $item) {
            $stmt_ins = $pdo->prepare("
                INSERT INTO purchases (user_id, item_type, service_id, package_id, price, purchased_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt_ins->execute([
                $_SESSION['user_id'],
                $item['type'],
                $item['type'] === 'service' ? $item['service_id'] : null,
                $item['type'] === 'package' ? $item['package_id'] : null,
                $item['price']
            ]);
        }
        // Очищаем корзину
        $stmt_del = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt_del->execute([$_SESSION['user_id']]);
        // Логируем
        logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'PURCHASE_SUCCESS', "Куплено: " . count($items) . " товаров");
        $pdo->commit();
        $_SESSION['message'] = "✅ Покупка успешно оформлена!";
        header("Location: purchased.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollback();
        $_SESSION['message'] = "❌ Ошибка при оформлении покупки: " . htmlspecialchars($e->getMessage());
        header("Location: cart.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Способ оплаты</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/payment_method.css">
</head>
<body>
    <div class="user-panel">
        <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? 'imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
        </div>
        <div class="user-menu">
            <a href="user_dashboard.php">Главная</a>
            <a href="cart.php">Корзина</a>
            <a href="edit_profile.php">Профиль</a>
            <a href="../logout.php">Выход</a>
        </div>
    </div>
    <div class="content-wrapper">
        <div class="payment-box">
            <h2>Выберите способ оплаты</h2>
            <form method="POST">
                <label>
                    <input type="radio" name="payment_method" value="sfp" required> СБП (Система быстрых платежей)
                </label>
                <label>
                    <input type="radio" name="payment_method" value="other"> Другой способ
                </label>
                <button type="submit">Оформить покупку</button>
            </form>
        </div>
    </div>
</body>
</html>