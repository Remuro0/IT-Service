<?php
session_start();
require_once '../auth.php';
requireAuth();

// Только для админа
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';
try {
    $pdo = getDBConnection();
    
    // Получаем логи — БЕЗ user_id!
    $stmt = $pdo->query("
        SELECT id, event_type, message, ip_address, user_agent, created_at, source
        FROM logs
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Проверяем, существует ли таблица system_metrics
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'system_metrics'");
    $use_real_data = $stmt_check->rowCount() > 0;
    
} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Логи системы</title>
    <link rel="stylesheet" href="../css/view_db.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .metrics-section {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
            background: rgba(30, 25, 45, 0.8);
            border-radius: 12px;
            border: 1px solid rgba(100, 60, 180, 0.3);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
        }
        .metrics-header {
            color: #c7b8ff;
            text-align: center;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.4rem;
        }
        .chart-container {
            height: 200px;
            position: relative;
        }
        .data-source {
            text-align: center;
            font-size: 0.9rem;
            color: #a090cc;
            margin-top: 10px;
        }
        .log-item {
            background: rgba(40, 30, 60, 0.9);
            border: 1px solid rgba(100, 60, 180, 0.3);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        .log-message {
            margin-top: 8px;
            color: #d0d0d0;
            line-height: 1.5;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <!-- Верхняя панель -->
    <div class="topbar">
        <img src="../<?= htmlspecialchars($_SESSION['avatar'] ?? 'imang/default.png') ?>" alt="Аватарка">
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['username']) ?></strong><br>
            <span class="role">Роль: <?= htmlspecialchars($_SESSION['role']) ?></span>
        </div>
        <button onclick="toggleUserMenu()" style="background: none; border: none; color: #c7b8ff; font-size: 16px; cursor: pointer;">▼</button>
    </div>
    
    <div id="userMenu" class="dropdown-content" style="display: none;">
        <div style="padding: 8px 12px; color: #c7b8ff; font-weight: bold; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
             onclick="toggleTablesMenu()">
            📋 Таблицы
            <span id="tablesArrow" style="font-size: 14px;">▼</span>
        </div>
        <div id="tablesList" style="display: none; margin-top: 8px;">
            <?php
            $stmt = $pdo->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $table = $row[0];
                echo "<a href='view_db.php?table=" . urlencode($table) . "'>$table</a>";
            }
            ?>
        </div>
        <hr>
        <a href="edit_profile.php">🖼️ Редактировать профиль</a>
        <a href="../actions/create_table_form.php">➕ Создать таблицу</a>
        <a href="../actions/backup.php">💾 Создать бэкап</a>
        <a href="sql_console.php">💻 SQL-консоль</a>
        <hr>
        <a href="../logout.php">🚪 Выйти</a>
    </div>
    
    <div class="content-wrapper">
        <!-- График системной нагрузки -->
        <div class="metrics-section">
            <h2 class="metrics-header">Системная нагрузка (обновляется каждые 5 сек)</h2>
            <div class="chart-container">
                <canvas id="systemChart"></canvas>
            </div>
            <div class="data-source" id="dataSource">
                <?= $use_real_data ? 'Данные: реальные (из таблицы system_metrics)' : 'Данные: симулированные (фальшивые)' ?>
            </div>
        </div>
        
        <!-- Логи в виде строк -->
        <h1 style="text-align: center; color: #c7b8ff;">Логи системы</h1>
        <div class="logs-container">
            <?php if (empty($logs)): ?>
            <div class="empty-logs" style="text-align: center; color: #aaa; font-size: 1.2rem; margin: 60px 0;">Нет логов.</div>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <div class="log-item">
                <span style="color: <?=
                    strtolower($log['event_type']) === 'error' ? '#ffaaaa' :
                    (strtolower($log['event_type']) === 'critical' ? '#ff6b6b' :
                    (strtolower($log['event_type']) === 'warning' ? '#ffcc66' : '#aaffaa'))
                ?>; font-weight: bold;">
                    <?= htmlspecialchars($log['event_type']) ?>
                </span>
                <span style="color: #d0d0d0; margin-left: 8px;">
                    Пользователь: <?= htmlspecialchars($log['source']) ?>
                </span>
                <span style="color: #a090cc; margin-left: 8px;">
                    IP: <?= htmlspecialchars($log['ip_address']) ?>
                </span>
                <span style="color: #a090cc; margin-left: 8px;">
                    <?= htmlspecialchars(date('d.m.Y H:i:s', strtotime($log['created_at']))) ?>
                </span>
                <div class="log-message">
                    <?= nl2br(htmlspecialchars($log['message'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Инициализация графика
    const ctx = document.getElementById('systemChart').getContext('2d');
    let systemChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'CPU (%)',
                    data: [],
                    borderColor: '#ff6b6b',
                    backgroundColor: 'rgba(255, 107, 107, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.3
                },
                {
                    label: 'Память (%)',
                    data: [],
                    borderColor: '#6ab7ff',
                    backgroundColor: 'rgba(106, 183, 255, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.3
                },
                {
                    label: 'Диск (%)',
                    data: [],
                    borderColor: '#aaffaa',
                    backgroundColor: 'rgba(170, 255, 170, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#e0e0e0' }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { color: '#a090cc' },
                    grid: { color: 'rgba(100, 80, 130, 0.2)' }
                },
                x: {
                    ticks: { color: '#a090cc' },
                    grid: { display: false }
                }
            }
        }
    });
    
    // Функция обновления данных
    async function updateChartData() {
        try {
            const response = await fetch('../api/get_system_metrics.php');
            const data = await response.json();
            systemChart.data.labels = data.labels;
            systemChart.data.datasets[0].data = data.cpu;
            systemChart.data.datasets[1].data = data.memory;
            systemChart.data.datasets[2].data = data.disk;
            systemChart.update();
            document.getElementById('dataSource').textContent = data.source;
        } catch (error) {
            console.error('Ошибка обновления графика:', error);
        }
    }
    
    // Первый запуск и обновление каждые 5 секунд
    updateChartData();
    setInterval(updateChartData, 5000);
    
    function toggleUserMenu() {
        const menu = document.getElementById('userMenu');
        if (menu.style.display === 'block') {
            menu.style.display = 'none';
        } else {
            menu.style.display = 'block';
        }
    }
    
    function toggleTablesMenu() {
        const list = document.getElementById('tablesList');
        const arrow = document.getElementById('tablesArrow');
        if (list.style.display === 'block') {
            list.style.display = 'none';
            arrow.textContent = '▼';
        } else {
            list.style.display = 'block';
            arrow.textContent = '▲';
        }
    }
    
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('userMenu');
        const button = document.querySelector('.topbar button');
        if (!button.contains(event.target) && !menu.contains(event.target)) {
            menu.style.display = 'none';
        }
    });
    </script>
    
    <?php include '../footer.php'; ?>
</body>
</html>