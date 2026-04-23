<?php
session_start();

// Если уже авторизован — редирект
if (isset($_SESSION['user_id'])) {
    header("Location: pages/view_db.php");
    exit;
}

$error = '';
$captchaShown = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Капча пройдена — проверяем логин/пароль
    if (isset($_POST['captcha_solved']) && $_POST['captcha_solved'] === 'true') {
        require_once 'config.php';
        
        $input_username = trim($_POST['username'] ?? '');
        $input_password = $_POST['password'] ?? '';
        
        if (empty($input_username) || empty($input_password)) {
            $error = "Логин и пароль обязательны.";
        } else {
            try {
                // 🔥 ПОДКЛЮЧАЕМСЯ К ЛОКАЛЬНОЙ БД через config.php
                $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // --- 🔐 Проверка блокировки ---
                $stmt = $pdo->prepare("
                    SELECT id, username, password_hash, role, avatar, is_blocked, lock_until, failed_attempts
                    FROM users WHERE username = ?
                ");
                $stmt->execute([$input_username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    $error = "Неверный логин или пароль.";
                    goto increment_attempt;
                }
                
                // Админы не блокируются
                if ($user['role'] === 'admin') {
                    // Пропускаем проверку блокировки
                } else {
                    // Проверяем, заблокирован ли пользователь
                    if ($user['is_blocked']) {
                        if ($user['lock_until'] && strtotime($user['lock_until']) > time()) {
                            $remaining = ceil((strtotime($user['lock_until']) - time()) / 60);
                            $error = "🔒 Вход заблокирован. Осталось: $remaining минут.";
                            goto end;
                        } else {
                            // Время вышло — снимаем авто-блокировку
                            $pdo->prepare("UPDATE users SET is_blocked = 0, lock_until = NULL WHERE id = ?")->execute([$user['id']]);
                            $user['is_blocked'] = 0;
                            $user['lock_until'] = null;
                        }
                    }
                    
                    // Проверяем, есть ли активная блокировка
                    if ($user['lock_until'] && strtotime($user['lock_until']) > time()) {
                        $remaining = ceil((strtotime($user['lock_until']) - time()) / 60);
                        $error = "🔒 Вход временно заблокирован. Осталось: $remaining минут.";
                        goto end;
                    }
                }
                
                // Проверяем пароль
                if (password_verify($input_password, $user['password_hash'])) {
                    // ✅ Успешный вход — сброс счётчиков
                    $pdo->prepare("
                        UPDATE users SET failed_attempts = 0, lock_until = NULL, is_blocked = 0 WHERE id = ?
                    ")->execute([$user['id']]);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'] ?? 'user';
                    $_SESSION['avatar'] = $user['avatar'] ?? 'imang/default.png';
                    $_SESSION['last_activity'] = time();
                    $_SESSION['db_mode'] = 'local'; // 🔥 Устанавливаем локальный режим
                    
                    header("Location: pages/view_db.php");
                    exit;
                } else {
                    $error = "Неверный логин или пароль.";
                    goto increment_attempt;
                }
                
            } catch (PDOException $e) {
                $error = "Ошибка подключения к базе данных: " . htmlspecialchars($e->getMessage());
            }
            
            end:;
        }
    } else {
        // Капча не пройдена — показываем капчу
        $captchaShown = true;
    }
    
    // --- 🔁 Увеличиваем счётчик неудачных попыток ---
    increment_attempt: {
        if (!isset($pdo)) {
            require_once 'config.php';
            try {
                $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                $error = "Ошибка подключения к БД. Проверьте, запущен ли MySQL.";
                goto skip_increment;
            }
        }
        
        // Получаем пользователя (ещё раз, если не был найден)
        $stmt = $pdo->prepare("SELECT id, failed_attempts, is_blocked FROM users WHERE username = ?");
        $stmt->execute([$input_username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Создаём "тень" для анонимов (защита от перебора)
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password_hash, role, failed_attempts, lock_until, is_blocked, email)
                VALUES (?, ?, 'guest', 1, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 1, 'no-email@example.com')
                ON DUPLICATE KEY UPDATE
                failed_attempts = failed_attempts + 1,
                lock_until = CASE
                    WHEN failed_attempts < 3 THEN DATE_ADD(NOW(), INTERVAL 10 MINUTE)
                    WHEN failed_attempts < 6 THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                    ELSE NULL
                END,
                is_blocked = CASE
                    WHEN failed_attempts >= 8 THEN 1
                    ELSE 0
                END
            ");
            $fake_hash = password_hash('fake', PASSWORD_DEFAULT);
            $stmt->execute([$input_username, $fake_hash]);
        } else {
            $new_attempts = $user['failed_attempts'] + 1;
            $lock_until = null;
            $is_blocked = 0;
            
            if ($new_attempts >= 9) {
                // 3 × 3 = 9 → окончательная блокировка
                $is_blocked = 1;
            } elseif ($new_attempts >= 6) {
                // 2-й цикл — 20 мин
                $lock_until = date('Y-m-d H:i:s', time() + 20 * 60);
            } elseif ($new_attempts >= 3) {
                // 1-й цикл — 10 мин
                $lock_until = date('Y-m-d H:i:s', time() + 10 * 60);
            }
            
            $pdo->prepare("
                UPDATE users SET
                failed_attempts = ?,
                lock_until = ?,
                is_blocked = ?
                WHERE id = ?
            ")->execute([$new_attempts, $lock_until, $is_blocked, $user['id']]);
        }
        
        skip_increment:
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Авторизация</title>
    <link rel="stylesheet" href="css/auth.css">
    <style>
        /* Стили для капчи */
        #captcha-puzzle {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: rgba(30, 25, 45, 0.95);
            border-radius: 12px;
            border: 1px solid rgba(100, 60, 180, 0.3);
            box-shadow: 0 8px 24px rgba(100, 30, 200, 0.4);
        }
        #captcha-puzzle h3 {
            text-align: center;
            color: #c7b8ff;
            margin: 0 0 15px;
        }
        .captcha-container {
            display: flex;
            gap: 20px;
            justify-content: space-between;
            align-items: flex-start;
        }
        .target-area {
            width: 200px;
            height: 200px;
            border: 2px dashed #6ab7ff;
            position: relative;
            background: #1e192d;
            overflow: hidden;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(2, 1fr);
            gap: 2px;
        }
        .target-slot {
            border: 1px solid rgba(100, 80, 150, 0.3);
            background: rgba(100, 80, 150, 0.1);
            position: relative;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .fragments-area {
            width: 200px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 5px;
        }
        .fragment-item {
            background: rgba(40, 35, 55, 0.7);
            border: 1px solid rgba(100, 60, 180, 0.3);
            border-radius: 6px;
            padding: 5px;
            cursor: grab;
            transition: transform 0.2s ease;
            overflow: hidden;
        }
        .fragment-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }
        .fragment-item:hover {
            transform: scale(1.05);
        }
        .fragment-item.dragging {
            opacity: 0.7;
        }
        .btn-verify, .btn-reset {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 12px;
            font-weight: bold;
        }
        .btn-verify {
            background: linear-gradient(to right, #00c853, #64dd17);
            color: white;
        }
        .btn-reset {
            background: linear-gradient(to right, #ff3b3b, #ff6b6b);
            color: white;
        }
        .btn-verify:disabled {
            background: linear-gradient(to right, #8a8a8a, #6a6a6a);
            cursor: not-allowed;
        }
        .message {
            text-align: center;
            padding: 10px;
            margin: 10px 0;
            border-radius: 6px;
            font-weight: bold;
        }
        .message.error {
            background: rgba(200, 50, 50, 0.3);
            color: #ffaaaa;
        }
        .message.success {
            background: rgba(40, 200, 80, 0.3);
            color: #aaffaa;
        }
    </style>
</head>
<body>
    <div class="auth-box">
        <h2>Вход</h2>
        
        <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Форма входа -->
        <form method="POST" id="loginForm" style="<?= $captchaShown ? 'display: none;' : '' ?>">
            <input type="text" name="username" placeholder="Логин" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            <input type="password" name="password" placeholder="Пароль" required>
            <button type="submit" id="loginBtn">Войти</button>
        </form>
        
        <!-- Капча-пазл -->
        <div id="captcha-puzzle" style="<?= $captchaShown ? 'display: block;' : '' ?>">
            <h3>Соберите пазл</h3>
            <p style="color: #a090cc; text-align: center; font-size: 0.9rem; margin-bottom: 15px;">
                Перетащите фрагменты в правильном порядке
            </p>
            <div class="captcha-container">
                <!-- Левая часть — цель сборки -->
                <div class="target-area" id="targetArea"></div>
                <!-- Правая часть — фрагменты -->
                <div class="fragments-area" id="fragmentsArea"></div>
            </div>
            <button type="button" class="btn-verify" id="verifyCaptchaBtn" disabled>
                Проверить
            </button>
            <button type="button" class="btn-reset" id="resetCaptchaBtn">
                Сбросить капчу
            </button>
        </div>
        
        <div class="auth-links">
            Нет аккаунта? <a href="register.php">Зарегистрироваться</a>
        </div>
        
        <div style="margin-top: 15px; text-align: center;">
            <a href="index.php" style="color: #6ab7ff; text-decoration: none; font-size: 0.9rem;">
                ← Вернуться на главную
            </a>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('loginForm');
        const captchaPuzzle = document.getElementById('captcha-puzzle');
        const targetArea = document.getElementById('targetArea');
        const fragmentsArea = document.getElementById('fragmentsArea');
        const verifyCaptchaBtn = document.getElementById('verifyCaptchaBtn');
        const resetCaptchaBtn = document.getElementById('resetCaptchaBtn');
        const loginBtn = document.getElementById('loginBtn');
        
        let isSolved = false;
        
        function generatePuzzle() {
            const imgSrc = 'imang/Capcha/df4afab504d97849e195e13c26cc2421e1a560d0r1-1280-720v2_hq.jpg';
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.src = imgSrc;
            
            img.onload = function() {
                const fragmentSize = 100;
                const fragments = [];
                
                const tempCanvas = document.createElement('canvas');
                tempCanvas.width = 200;
                tempCanvas.height = 200;
                const tempCtx = tempCanvas.getContext('2d');
                tempCtx.drawImage(img, 0, 0, 200, 200);
                
                const positions = [
                    { name: 'fragment1', row: 1, col: 0 },
                    { name: 'fragment2', row: 1, col: 1 },
                    { name: 'fragment3', row: 0, col: 1 },
                    { name: 'fragment4', row: 0, col: 0 }
                ];
                
                positions.forEach(({ name, row, col }) => {
                    const canvas = document.createElement('canvas');
                    canvas.width = fragmentSize;
                    canvas.height = fragmentSize;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(tempCanvas, col * fragmentSize, row * fragmentSize, fragmentSize, fragmentSize, 0, 0, fragmentSize, fragmentSize);
                    
                    const fragment = document.createElement('div');
                    fragment.className = 'fragment-item';
                    fragment.dataset.name = name;
                    fragment.dataset.correctRow = row;
                    fragment.dataset.correctCol = col;
                    fragment.draggable = true;
                    fragment.innerHTML = `<img src="${canvas.toDataURL()}" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">`;
                    
                    fragment.addEventListener('dragstart', function(e) {
                        e.dataTransfer.setData('text/plain', name);
                        this.classList.add('dragging');
                    });
                    
                    fragment.addEventListener('dragend', function() {
                        this.classList.remove('dragging');
                    });
                    
                    fragments.push(fragment);
                });
                
                fragments.sort(() => Math.random() - 0.5);
                
                targetArea.innerHTML = '';
                for (let r = 0; r < 2; r++) {
                    for (let c = 0; c < 2; c++) {
                        const slot = document.createElement('div');
                        slot.className = 'target-slot';
                        slot.dataset.slotRow = r;
                        slot.dataset.slotCol = c;
                        targetArea.appendChild(slot);
                    }
                }
                
                fragmentsArea.innerHTML = '';
                fragments.forEach(frag => fragmentsArea.appendChild(frag));
                
                targetArea.addEventListener('dragover', e => e.preventDefault());
                targetArea.addEventListener('drop', function(e) {
                    e.preventDefault();
                    const name = e.dataTransfer.getData('text/plain');
                    const dragged = document.querySelector(`[data-name="${name}"]`);
                    if (!dragged) return;
                    
                    const slots = Array.from(targetArea.querySelectorAll('.target-slot'));
                    let closest = null, minDist = Infinity;
                    
                    slots.forEach(slot => {
                        const rect = slot.getBoundingClientRect();
                        const cx = rect.left + rect.width / 2;
                        const cy = rect.top + rect.height / 2;
                        const dist = Math.hypot(e.clientX - cx, e.clientY - cy);
                        if (dist < minDist) {
                            minDist = dist;
                            closest = slot;
                        }
                    });
                    
                    if (closest && !closest.querySelector('.fragment-item')) {
                        dragged.style.position = 'absolute';
                        dragged.style.width = '100%';
                        dragged.style.height = '100%';
                        closest.appendChild(dragged);
                        checkIfSolved();
                    }
                });
                
                verifyCaptchaBtn.disabled = true;
            };
            
            img.onerror = function() {
                console.error('Ошибка загрузки изображения капчи:', imgSrc);
            };
        }
        
        function checkIfSolved() {
            const slots = Array.from(targetArea.querySelectorAll('.target-slot'));
            let correct = 0;
            
            slots.forEach(slot => {
                const frag = slot.querySelector('.fragment-item');
                if (frag) {
                    const sRow = parseInt(slot.dataset.slotRow);
                    const sCol = parseInt(slot.dataset.slotCol);
                    const fRow = parseInt(frag.dataset.correctRow);
                    const fCol = parseInt(frag.dataset.correctCol);
                    if (sRow === fRow && sCol === fCol) correct++;
                }
            });
            
            isSolved = correct === 4;
            verifyCaptchaBtn.disabled = !isSolved;
            verifyCaptchaBtn.textContent = isSolved ? '✅ Готово!' : 'Проверить';
        }
        
        verifyCaptchaBtn.addEventListener('click', function() {
            if (!isSolved) {
                alert('❌ Пазл не собран правильно. Попробуйте снова.');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const u = document.querySelector('input[name="username"]').value;
            const p = document.querySelector('input[name="password"]').value;
            
            form.innerHTML = `
                <input type="hidden" name="username" value="${u}">
                <input type="hidden" name="password" value="${p}">
                <input type="hidden" name="captcha_solved" value="true">
            `;
            
            document.body.appendChild(form);
            form.submit();
        });
        
        resetCaptchaBtn.addEventListener('click', generatePuzzle);
        
        loginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            loginForm.style.display = 'none';
            captchaPuzzle.style.display = 'block';
            generatePuzzle();
        });
    });
    </script>
</body>
</html>