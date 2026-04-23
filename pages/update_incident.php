<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../log_action.php';
// Только для инженера
if ($_SESSION['role'] !== 'engineer') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../pages/engineer_dashboard.php");
    exit;
}
require_once '../config.php';
$incident_id = (int)($_GET['id'] ?? 0);
if (!$incident_id) {
    $_SESSION['message'] = "❌ Не указан ID инцидента.";
    header("Location: ../pages/view_incidents.php");
    exit;
}
try {
    $pdo = getDBConnection(); // ✅ Универсальное подключение
    // Получаем инцидент
    $stmt = $pdo->prepare("SELECT * FROM incidents WHERE id = ?");
    $stmt->execute([$incident_id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$incident) {
        $_SESSION['message'] = "❌ Инцидент не найден.";
        header("Location: ../pages/view_incidents.php");
        exit;
    }
    // Получаем имя назначенного пользователя
    $assigned_user = '—';
    if ($incident['assigned_to']) {
        $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt_user->execute([$incident['assigned_to']]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
        $assigned_user = $user ? htmlspecialchars($user['username']) : "ID: {$incident['assigned_to']}";
    }
    // Обработка формы
    if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update') {
        $new_status = $_POST['status'] ?? '';
        $allowed_statuses = ['open', 'in_progress', 'resolved', 'closed'];
        if (!in_array($new_status, $allowed_statuses)) {
            $_SESSION['message'] = "❌ Недопустимый статус.";
        } else {
            $stmt = $pdo->prepare("UPDATE incidents SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $incident_id])) {
                $_SESSION['message'] = "✅ Статус обновлён.";
                logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 'INCIDENT_STATUS_UPDATED', "ID: $incident_id, Новый статус: $new_status");
            } else {
                $_SESSION['message'] = "❌ Ошибка при обновлении.";
            }
        }
        header("Location: ../pages/engineer_dashboard.php");
        exit;
    }
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Обновить инцидент</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/update_incident.css">
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
            <a href="engineer_dashboard.php">🏠 Главная</a>
            <a href="view_incidents.php">🚨 Инциденты</a>
            <a href="view_servers.php">💻 Серверы</a>
            <a href="view_network.php">🌐 Сеть</a>
            <a href="edit_profile.php">✏️ Профиль</a>
            <hr>
            <a href="../logout.php">🚪 Выйти</a>
        </div>
    </div>
    <div class="content-wrapper">
        <div class="form-card">
            <h2 style="text-align: center; color: #c7b8ff;">Обновить инцидент</h2>
            <h3 style="margin: 10px 0;"><?= htmlspecialchars($incident['title']) ?></h3>
            <p><strong>Описание:</strong> <?= htmlspecialchars($incident['description']) ?></p>
            <p><strong>Назначен:</strong> <?= $assigned_user ?></p>
            <p><strong>Текущий статус:</strong> 
                <span class="incident-status <?= $incident['status'] === 'open' ? 'status-open' : ($incident['status'] === 'in_progress' ? 'status-in_progress' : 'status-resolved') ?>">
                    <?= htmlspecialchars($incident['status']) ?>
                </span>
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $incident_id ?>">
                <label for="status" style="display: block; color: #c7b8ff; margin: 10px 0 5px;">Новый статус:</label>
                <select name="status" id="status" required style="width: 100%; padding: 8px; background: #1e192d; color: white; border: 1px solid #5a1a8f; border-radius: 6px; font-size: 14px;">
                    <option value="open" <?= $incident['status'] === 'open' ? 'selected' : '' ?>>Открыт</option>
                    <option value="in_progress" <?= $incident['status'] === 'in_progress' ? 'selected' : '' ?>>В работе</option>
                    <option value="resolved" <?= $incident['status'] === 'resolved' ? 'selected' : '' ?>>Решён</option>
                    <option value="closed" <?= $incident['status'] === 'closed' ? 'selected' : '' ?>>Закрыт</option>
                </select>
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="save-btn">Сохранить</button>
                    <a href="../pages/engineer_dashboard.php" class="cancel-btn">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>