<?php
session_start();
require_once '../auth.php';
requireAuth();
// Только для менеджера и выше
if (!in_array($_SESSION['role'], ['manager', 'admin'])) {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../pages/index.php");
    exit;
}
require_once '../config.php';
try {
    $pdo = getDBConnection(); // ✅ Универсальное подключение
    // Общая выручка
    $stmt = $pdo->query("SELECT SUM(price) FROM purchases");
    $total_revenue = $stmt->fetchColumn() ?: 0;
    // Кол-во покупок
    $stmt = $pdo->query("SELECT COUNT(*) FROM purchases");
    $total_purchases = $stmt->fetchColumn();
    // Кол-во уникальных покупателей
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM purchases");
    $unique_customers = $stmt->fetchColumn();
    // Топ-5 покупателей
    $stmt = $pdo->prepare("
        SELECT u.username, SUM(p.price) AS total_spent, COUNT(p.id) AS purchase_count
        FROM purchases p
        JOIN users u ON p.user_id = u.id
        GROUP BY p.user_id
        ORDER BY total_spent DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Последние 10 покупок
    $stmt = $pdo->prepare("
        SELECT p.id, p.price, p.purchased_at, p.item_type,
               s.name AS service_name,
               t.name AS package_name,
               u.username AS buyer
        FROM purchases p
        LEFT JOIN services s ON p.service_id = s.id
        LEFT JOIN tariff_plans t ON p.package_id = t.id
        JOIN users u ON p.user_id = u.id
        ORDER BY p.purchased_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Биллинг — Сводка</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/view_billing_summary.css">
</head>
<body>
    <!-- Панель пользователя -->
    <div class="user-panel">
        <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? 'imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username']) ?></strong><br>
            <span class="role">Роль: <?= htmlspecialchars($_SESSION['role']) ?></span>
        </div>
        <div class="user-menu">
            <a href="manager_dashboard.php">🏠 Главная</a>
            <a href="view_billing_summary.php">💰 Биллинг</a>
            <a href="support_tickets_all.php">📨 Поддержка</a>
            <a href="edit_profile.php">✏️ Профиль</a>
            <hr>
            <a href="../logout.php" class="logout">🚪 Выйти</a>
        </div>
    </div>
    <div class="content-wrapper">
        <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert"><?= htmlspecialchars($_SESSION['message']) ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <h1 style="text-align: center; color: #c7b8ff;">Финансовая сводка по покупкам</h1>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Общая выручка</div>
                <div class="summary-value"><?= number_format($total_revenue, 0, '', ' ') ?> ₽</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Покупок всего</div>
                <div class="summary-value"><?= $total_purchases ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Покупателей</div>
                <div class="summary-value"><?= $unique_customers ?></div>
            </div>
        </div>
        <h2 class="section-title">Топ-5 покупателей</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Потрачено</th>
                        <th>Покупок</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_customers)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #aaa;">Нет данных</td></tr>
                    <?php else: ?>
                        <?php foreach ($top_customers as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['username']) ?></td>
                                <td><?= number_format($c['total_spent'], 0, '', ' ') ?> ₽</td>
                                <td><?= $c['purchase_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <h2 class="section-title">Последние покупки</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Покупатель</th>
                        <th>Товар</th>
                        <th>Сумма</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_purchases)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #aaa;">Нет покупок</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_purchases as $p): ?>
                            <tr>
                                <td><?= $p['id'] ?></td>
                                <td><?= htmlspecialchars($p['buyer']) ?></td>
                                <td>
                                    <?php if ($p['item_type'] === 'service'): ?>
                                        Услуга: <?= htmlspecialchars($p['service_name']) ?>
                                    <?php else: ?>
                                        Пакет: <?= htmlspecialchars($p['package_name']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($p['price'], 0, '', ' ') ?> ₽</td>
                                <td><?= date('d.m.Y H:i', strtotime($p['purchased_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php include '../footer.php'; ?>
</body>
</html>