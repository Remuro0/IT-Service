<?php
session_start();
require_once '../auth.php';
requireAuth();

// Только для админа
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = "❌ Доступ запрещён.";
    header("Location: view_db.php");
    exit;
}

// Вспомогательная функция — форматирование таймаута для отображения
function formatTimeout($seconds) {
    if ($seconds < 3600) return intval($seconds / 60) . ' мин';
    if ($seconds < 86400) return intval($seconds / 3600) . ' ч';
    if ($seconds < 604800) return intval($seconds / 86400) . ' дн';
    if ($seconds < 2592000) return intval($seconds / 604800) . ' нед';
    if ($seconds < 31536000) return intval($seconds / 2592000) . ' мес';
    return intval($seconds / 31536000) . ' г';
}

// Подключаемся к БД — в зависимости от $_SESSION['db_mode']
require_once '../config.php';
try {
    $pdo = getDBConnection(); // ← Используем универсальную функцию из config.php
} catch (Exception $e) {
    die("❌ Ошибка подключения к БД: " . htmlspecialchars($e->getMessage()));
}

// Загружаем текущие значения
$stmt = $pdo->query("SELECT role, timeout_seconds FROM session_timeouts ORDER BY FIELD(role, 'user','engineer','manager','db_admin','admin')");
$timeouts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $timeouts[$row['role']] = (int)$row['timeout_seconds'];
}

// Убеждаемся, что все роли присутствуют (на случай отсутствия записи)
$roles = ['user', 'engineer', 'manager', 'db_admin', 'admin'];
foreach ($roles as $role) {
    if (!isset($timeouts[$role])) {
        $timeouts[$role] = $role === 'user' ? 86400 : 300; // user: 24h, остальные: 5 мин
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Настройка таймаутов сессий</title>
    <link rel="stylesheet" href="../css/view_db.css">
    <style>
        .timeout-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 12px;
            max-width: 600px;
            margin: 30px auto;
        }
        .role-label {
            font-weight: bold;
            color: #c7b8ff;
            text-align: right;
            padding: 8px 0;
        }
        .timeout-input {
            width: 100%;
            padding: 8px;
            background: #1e192d;
            border: 1px solid #5a1a8f;
            color: white;
            border-radius: 6px;
            font-size: 14px;
        }
        .hint {
            font-size: 0.85rem;
            color: #a090cc;
            margin-top: 4px;
        }
        /* Боковой регулятор */
        .regulator-panel {
            width: 300px;
            background: rgba(40, 35, 55, 0.9);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(100, 60, 180, 0.3);
            box-shadow: 0 6px 16px rgba(100, 30, 200, 0.3);
        }
        .regulator-header {
            text-align: center;
            color: #c7b8ff;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        .regulator-input {
            width: 100%;
            padding: 8px;
            margin: 6px 0;
            background: #1e192d;
            border: 1px solid #5a1a8f;
            color: white;
            border-radius: 6px;
            font-size: 14px;
        }
        .slider-value {
            display: block;
            text-align: center;
            margin: 8px 0;
            color: #6ab7ff;
            font-weight: bold;
            font-size: 1.2rem;
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

    <!-- Выпадающее меню (минимум для выхода) -->
    <div id="userMenu" class="dropdown-content" style="display: none;">
        <a href="view_db.php">🏠 Главная</a>
        <a href="edit_profile.php">✏️ Профиль</a>
        <hr>
        <a href="../logout.php">🚪 Выйти</a>
    </div>

    <!-- Основной контент -->
    <div class="content-wrapper">
        <h2 style="text-align: center; color: #c7b8ff;">Управление таймаутами сессий</h2>
        <p style="text-align: center; color: #a090cc; margin-bottom: 20px;">
            Настройте время неактивности (в секундах). Минимум — 60 сек.
        </p>

        <!-- Две колонки: таблица + регулятор -->
        <div style="display: flex; gap: 30px; max-width: 900px; margin: 0 auto; flex-wrap: wrap;">
            <!-- Таблица текущих значений -->
            <div style="flex: 1; min-width: 300px;">
                <h3 style="color: #c7b8ff; margin-bottom: 10px;">Текущие настройки</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: rgba(40, 35, 55, 0.7);">
                            <th style="padding: 8px; text-align: left; color: #c7b8ff;">Роль</th>
                            <th style="padding: 8px; text-align: left; color: #c7b8ff;">Таймаут</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (['user', 'engineer', 'manager', 'db_admin', 'admin'] as $role): ?>
                            <tr style="border-bottom: 1px solid rgba(100, 80, 130, 0.2);">
                                <td style="padding: 8px; color: #e0e0e0;"><?= htmlspecialchars($role) ?></td>
                                <td style="padding: 8px; color: #6ab7ff;">
                                    <span id="timeout-display-<?= $role ?>">
                                        <?= formatTimeout($timeouts[$role]) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Боковой регулятор -->
            <div class="regulator-panel">
                <h3 class="regulator-header">Установить таймаут</h3>

                <label style="display: block; color: #c7b8ff; margin-bottom: 5px;">Роль:</label>
                <select id="timeout-role" class="regulator-input">
                    <option value="user">Пользователь</option>
                    <option value="engineer">Инженер</option>
                    <option value="manager">Менеджер</option>
                    <option value="db_admin">DB-админ</option>
                    <option value="admin">Админ</option>
                </select>

                <label style="display: block; color: #c7b8ff; margin: 10px 0 5px;">Время:</label>
                <div class="slider-value" id="slider-display">15 мин</div>

                <input type="range" id="timeout-slider" min="1" max="720" value="15"
                       style="width: 100%; height: 8px; accent-color: #5a1a8f; margin-bottom: 10px;">

                <label style="display: block; color: #c7b8ff; margin: 10px 0 5px;">Единица:</label>
                <select id="timeout-unit" class="regulator-input">
                    <option value="minutes" selected>минуты</option>
                    <option value="hours">часы</option>
                    <option value="days">дни</option>
                    <option value="weeks">недели</option>
                    <option value="months">месяцы</option>
                    <option value="years">годы</option>
                </select>

                <button id="save-timeout-btn"
                        style="width: 100%; padding: 10px; margin-top: 15px;
                               background: linear-gradient(to right, #00c853, #64dd17);
                               color: white; border: none; border-radius: 6px; font-weight: bold;">
                    💾 Применить
                </button>

                <div id="timeout-status" style="
                    margin-top: 10px; padding: 8px; border-radius: 6px; text-align: center;
                    display: none;
                "></div>
            </div>
        </div>

        <div style="max-width: 800px; margin: 40px auto; padding: 15px; background: rgba(40, 35, 55, 0.7); border-radius: 8px;">
            <h3 style="color: #c7b8ff; margin: 0 0 10px;">Как это работает</h3>
            <ul style="color: #d0d0d0; font-size: 0.95rem; line-height: 1.5;">
                <li>Таймаут отсчитывается с момента последнего <strong>активного действия</strong> (не фоновые запросы).</li>
                <li>При превышении — сессия уничтожается, пользователь перенаправляется на <code>login.php</code>.</li>
                <li>Настройки применяются мгновенно и хранятся в таблице <code>session_timeouts</code>.</li>
                <li>1 год = 365 дней = 31 536 000 секунд.</li>
            </ul>
        </div>
    </div>

    <script>
        // Вспомогательные функции
        function secondsToDisplay(seconds) {
            const mins = Math.floor(seconds / 60);
            const hours = Math.floor(seconds / 3600);
            const days = Math.floor(seconds / 86400);
            if (seconds < 3600) return `${mins} мин`;
            if (seconds < 86400) return `${hours} ч`;
            return `${days} дн`;
        }

        function getSliderConfig(unit) {
            const configs = {
                minutes: { min: 1,   max: 1440, step: 1,  default: 15 },
                hours:   { min: 1,   max: 720,  step: 1,  default: 1  },
                days:    { min: 1,   max: 365,  step: 1,  default: 1  },
                weeks:   { min: 1,   max: 52,   step: 1,  default: 1  },
                months:  { min: 1,   max: 24,   step: 1,  default: 1  },
                years:   { min: 1,   max: 5,    step: 1,  default: 1  }
            };
            return configs[unit] || configs.minutes;
        }

        function toSeconds(value, unit) {
            const multipliers = {
                minutes: 60,
                hours: 3600,
                days: 86400,
                weeks: 604800,
                months: 2592000,
                years: 31536000
            };
            return Math.round(value * (multipliers[unit] || 60));
        }

        // Элементы
        const slider = document.getElementById('timeout-slider');
        const roleSelect = document.getElementById('timeout-role');
        const unitSelect = document.getElementById('timeout-unit');
        const display = document.getElementById('slider-display');
        const statusDiv = document.getElementById('timeout-status');
        const saveBtn = document.getElementById('save-timeout-btn');

        // Обновление UI при смене единиц
        function updateSliderFromUnit() {
            const unit = unitSelect.value;
            const config = getSliderConfig(unit);
            slider.min = config.min;
            slider.max = config.max;
            slider.step = config.step;
            slider.value = config.default;
            updateDisplay();
        }

        function updateDisplay() {
            const value = parseInt(slider.value);
            const unit = unitSelect.value;
            const sec = toSeconds(value, unit);
            display.textContent = secondsToDisplay(sec);
        }

        // Сохранение
        saveBtn.addEventListener('click', async () => {
            const role = roleSelect.value;
            const unit = unitSelect.value;
            const value = parseInt(slider.value);
            const seconds = toSeconds(value, unit);

            if (seconds < 60) {
                statusDiv.style.background = 'rgba(200, 50, 50, 0.3)';
                statusDiv.style.color = '#ffaaaa';
                statusDiv.textContent = '❌ Минимум — 60 сек';
                statusDiv.style.display = 'block';
                return;
            }

            statusDiv.style.display = 'block';
            statusDiv.style.background = 'rgba(100, 80, 150, 0.3)';
            statusDiv.style.color = '#6ab7ff';
            statusDiv.textContent = 'Сохранение...';

            try {
                const res = await fetch('save_timeout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ role, seconds })
                });

                const data = await res.json();
                if (data.success) {
                    statusDiv.style.background = 'rgba(40, 200, 80, 0.3)';
                    statusDiv.style.color = '#aaffaa';
                    statusDiv.textContent = '✅ Сохранено!';
                    // Обновляем отображение в таблице
                    document.getElementById(`timeout-display-${role}`).textContent = secondsToDisplay(seconds);
                    setTimeout(() => statusDiv.style.display = 'none', 2000);
                } else {
                    throw new Error(data.message || 'Неизвестная ошибка');
                }
            } catch (e) {
                statusDiv.style.background = 'rgba(200, 50, 50, 0.3)';
                statusDiv.style.color = '#ffaaaa';
                statusDiv.textContent = `❌ ${e.message}`;
            }
        });

        // Инициализация
        unitSelect.addEventListener('change', updateSliderFromUnit);
        slider.addEventListener('input', updateDisplay);
        updateSliderFromUnit();
    </script>

    <script>
        function toggleUserMenu() {
            const menu = document.getElementById('userMenu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('userMenu');
            const btn = document.querySelector('.topbar button');
            if (!menu.contains(e.target) && !btn.contains(e.target)) {
                menu.style.display = 'none';
            }
        });
    </script>
</body>
</html>