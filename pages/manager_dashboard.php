<?php
session_start();
require_once '../auth.php';
requireAuth();

// Только для менеджера и выше
if (!in_array($_SESSION['role'], ['manager', 'admin'])) {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';
$pdo = getDBConnection();
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // === Основные метрики ===
    $stmt = $pdo->query("SELECT COUNT(*) FROM incidents");
    $incident_count = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM servers");
    $server_count = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT SUM(price) FROM purchases");
    $total_revenue = $stmt->fetchColumn() ?: 0;

    // === График: Выручка за последние 7 дней ===
    $stmt = $pdo->prepare("
        SELECT DATE(purchased_at) as day, COALESCE(SUM(price), 0) as total
        FROM purchases
        WHERE purchased_at >= CURDATE() - INTERVAL 6 DAY
        GROUP BY day
        ORDER BY day
    ");
    $stmt->execute();
    $daily_revenue = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $revenue_labels = [];
    $revenue_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $revenue_labels[] = date('d.m', strtotime($date));
        $revenue_data[] = (float)($daily_revenue[$date] ?? 0);
    }

    // === График: Статусы инцидентов ===
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM incidents
        GROUP BY status
    ");
    $status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $incident_status_labels = array_keys($status_counts);
    $incident_status_data = array_values($status_counts);

    // === График: Регистрации пользователей за 7 дней ===
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as day, COUNT(*) as count
        FROM users
        WHERE created_at >= CURDATE() - INTERVAL 6 DAY
        GROUP BY day
        ORDER BY day
    ");
    $stmt->execute();
    $daily_registrations = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $reg_labels = [];
    $reg_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $reg_labels[] = date('d.m', strtotime($date));
        $reg_data[] = (int)($daily_registrations[$date] ?? 0);
    }

    // === Последние инциденты ===
    $stmt = $pdo->prepare("
        SELECT id, title, status, created_at 
        FROM incidents 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель менеджера</title>
    <link rel="stylesheet" href="../css/guest.css">
    <link rel="stylesheet" href="../css/userbar.css">
    <link rel="stylesheet" href="../css/manager_dashboard.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- Панель пользователя -->
<div class="user-panel">
    <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? '../imang/default.png') ?>" alt="Аватарка">
    <div class="user-info">
        <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Менеджер') ?></strong><br>
        <span class="role">Роль: <?= htmlspecialchars($_SESSION['role']) ?></span>
    </div>
    <div class="user-menu">
        <a href="manager_dashboard.php">Главная</a>
        <a href="view_billing_summary.php">Биллинг</a>
        <a href="support_tickets_all.php">Поддержка</a>
        <a href="edit_profile.php">Профиль</a>
        <a href="../logout.php" class="logout">Выход</a>
    </div>
</div>

<div class="content-wrapper">

    <!-- Приветствие -->
    <?php if (!isset($_SESSION['welcome_shown_manager'])): ?>
        <div class="welcome-banner">
            Добро пожаловать в систему, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>! Ваша роль: <strong><?= htmlspecialchars($_SESSION['role']) ?></strong>.
        </div>
        <?php $_SESSION['welcome_shown_manager'] = true; ?>
    <?php endif; ?>

    <h1 style="text-align: center; color: #c7b8ff;">Панель менеджера</h1>

    <!-- Статистика -->
    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-label">Инциденты</div>
            <div class="stat-value"><?= $incident_count ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Серверы</div>
            <div class="stat-value"><?= $server_count ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Выручка</div>
            <div class="stat-value"><?= number_format($total_revenue, 0, '', ' ') ?> ₽</div>
        </div>
    </div>

    <!-- Графики -->
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin: 20px 0 30px;">
        <!-- Выручка -->
        <div style="background: rgba(30, 25, 45, 0.8); padding: 15px; border-radius: 12px; border: 1px solid rgba(100, 60, 180, 0.3); height: 200px; position: relative;">
            <h3 style="color: #c7b8ff; text-align: center; margin-top: 0;">Выручка за неделю</h3>
            <canvas id="revenueChart" style="width: 100%; height: 150px;"></canvas>
        </div>

        <!-- Регистрации -->
        <div style="background: rgba(30, 25, 45, 0.8); padding: 15px; border-radius: 12px; border: 1px solid rgba(100, 60, 180, 0.3); height: 200px; position: relative;">
            <h3 style="color: #c7b8ff; text-align: center; margin-top: 0;">Регистрации пользователей</h3>
            <canvas id="registrationsChart" style="width: 100%; height: 150px;"></canvas>
        </div>

        <!-- Инциденты по статусам -->
        <div style="background: rgba(30, 25, 45, 0.8); padding: 15px; border-radius: 12px; border: 1px solid rgba(100, 60, 180, 0.3); height: 200px; position: relative;">
            <h3 style="color: #c7b8ff; text-align: center; margin-top: 0;">Инциденты по статусам</h3>
            <canvas id="incidentsChart" style="width: 100%; height: 150px;"></canvas>
        </div>
    </div>

    <script>
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($revenue_labels) ?>,
            datasets: [{
                label: 'Выручка (₽)',
                data: <?= json_encode($revenue_data) ?>,
                borderColor: '#6ab7ff',
                backgroundColor: 'rgba(106, 183, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { color: '#a090cc' } },
                x: { ticks: { color: '#a090cc' } }
            }
        }
    });

    const regCtx = document.getElementById('registrationsChart').getContext('2d');
    new Chart(regCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($reg_labels) ?>,
            datasets: [{
                label: 'Новые пользователи',
                data: <?= json_encode($reg_data) ?>,
                backgroundColor: '#ffcc00',
                borderColor: '#ff9800',
                borderWidth: 1
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { color: '#a090cc' } },
                x: { ticks: { color: '#a090cc' } }
            }
        }
    });

    const incidentsCtx = document.getElementById('incidentsChart').getContext('2d');
    const statusColors = {
        'open': '#ff6b6b',
        'in_progress': '#ffcc66',
        'resolved': '#aaffaa',
        'closed': '#8888ff',
        'default': '#c7b8ff'
    };
    const labels = <?= json_encode($incident_status_labels) ?>;
    const data = <?= json_encode($incident_status_data) ?>;
    const colors = labels.map(label => statusColors[label] || statusColors.default);

    new Chart(incidentsCtx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    setTimeout(() => {
        const ctx = incidentsCtx;
        const width = ctx.canvas.width;
        const height = ctx.canvas.height;
        const centerX = width / 2;
        const centerY = height / 2;
        let yOffset = -40;

        ctx.font = '12px Arial';
        ctx.fillStyle = '#ffffff';

        for (let i = 0; i < labels.length; i++) {
            const label = labels[i];
            const color = colors[i];

            ctx.fillStyle = color;
            ctx.fillRect(centerX - 60, centerY + yOffset, 10, 10);

            ctx.fillStyle = '#ffffff';
            ctx.fillText(`${label}`, centerX - 45, centerY + yOffset + 8);

            yOffset += 20;
        }
    }, 100);
    </script>

    <!-- Последние инциденты -->
    <h2 class="section-title">Последние инциденты</h2>
    <?php if (empty($recent_incidents)): ?>
        <p style="color: #aaa; text-align: center;">Нет инцидентов.</p>
    <?php else: ?>
        <ul class="incident-list">
            <?php foreach ($recent_incidents as $inc): ?>
                <li class="incident-item">
                    <strong><?= htmlspecialchars($inc['title']) ?></strong><br>
                    <small>
                        <?= date('d.m.Y H:i', strtotime($inc['created_at'])) ?> |
                        <span class="status-badge status-<?= htmlspecialchars($inc['status']) ?>">
                            <?= htmlspecialchars($inc['status']) ?>
                        </span>
                    </small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</div>

<?php include '../footer.php'; ?>

</body>
</html>