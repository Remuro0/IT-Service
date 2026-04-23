<?php
session_start();
require_once '../auth.php';
requireAuth();
require_once '../config.php';
try {
    $pdo = getDBConnection(); // ✅ Используем универсальную функцию
} catch (Exception $e) {
    die("Ошибка подключения к БД: " . htmlspecialchars($e->getMessage()));
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    die("Пользователь не найден.");
}

// Получаем данные пользователя
$stmt = $pdo->prepare("SELECT username, email, role, avatar FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die("Пользователь не найден.");
}

$error = '';
$success = '';

// 🔹 Обработка сброса аватарки к дефолтной
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'reset_avatar') {
    $old_avatar = $user['avatar'];
    
    // Удаляем старый аватар (если это не default.png)
    if ($old_avatar !== 'imang/default.png' && file_exists('../' . $old_avatar)) {
        unlink('../' . $old_avatar);
    }
    
    // Обновляем в БД
    $stmt = $pdo->prepare("UPDATE users SET avatar = 'imang/default.png' WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        // Обновляем сессию
        $_SESSION['avatar'] = 'imang/default.png';
        $_SESSION['message'] = "✅ Аватарка сброшена к стандартной.";
        header("Location: edit_profile.php");
        exit;
    } else {
        $error = "❌ Ошибка при сбросе аватарки.";
    }
}

// Обработка обновления профиля
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_username = trim($_POST['username'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Валидация
    if (empty($new_username)) {
        $error = "Логин не может быть пустым.";
    } else {
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt_check->execute([$new_username, $user_id]);
        if ($stmt_check->fetch()) {
            $error = "Пользователь с таким логином уже существует.";
        }
    }
    
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Неверный формат email.";
    }
    
    if ($error) {
        goto show_form;
    }
    
    // Обработка аватарки (с кропом из base64)
    $avatar_path = $user['avatar'];
    if (!empty($_POST['avatar_cropped'])) {
        $cropped_data = $_POST['avatar_cropped'];
        
        if (preg_match('/^data:image\/(jpeg|png|gif);base64,/', $cropped_data)) {
            $upload_dir = '../imang/';
            $file_ext = preg_match('/jpeg/', $cropped_data) ? 'jpg' : 'png';
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
            $target_path = $upload_dir . $new_filename;
            
            $image_data = preg_replace('/^image\/(jpeg|png|gif);base64,/', '', $cropped_data);
            $image_data = base64_decode($image_data);
            
            if ($image_data && file_put_contents($target_path, $image_data)) {
                // Удаляем старый аватар (если не default)
                $old_avatar = $user['avatar'];
                if ($old_avatar !== 'imang/default.png' && file_exists('../' . $old_avatar)) {
                    unlink('../' . $old_avatar);
                }
                $avatar_path = 'imang/' . $new_filename;
            } else {
                $error = "Ошибка при сохранении аватарки.";
                goto show_form;
            }
        }
    }
    
    // Подготовка обновления
    $updates = [];
    $params = [];
    
    if ($new_username !== $user['username']) {
        $updates[] = "username = ?";
        $params[] = $new_username;
    }
    
    if ($new_email !== $user['email']) {
        $updates[] = "email = ?";
        $params[] = $new_email;
    }
    
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $error = "Пароли не совпадают.";
            goto show_form;
        }
        $updates[] = "password_hash = ?";
        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
    }
    
    if ($avatar_path !== $user['avatar']) {
        $updates[] = "avatar = ?";
        $params[] = $avatar_path;
    }
    
    if (empty($updates)) {
        $success = "Нет изменений.";
    } else {
        $params[] = $user_id;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt_update = $pdo->prepare($sql);
        if ($stmt_update->execute($params)) {
            $success = "✅ Профиль обновлён.";
            // Обновляем сессию
            $_SESSION['username'] = $new_username;
            $_SESSION['avatar'] = $avatar_path;
            $_SESSION['message'] = $success;
            header("Location: view_db.php");
            exit;
        } else {
            $error = "❌ Ошибка при сохранении.";
        }
    }
}

show_form:
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать профиль</title>
    <link rel="stylesheet" href="../css/view_db.css">
    <style>
        .crop-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        
        .crop-container {
            background: rgba(30, 25, 45, 0.98);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #5a1a8f;
            max-width: 700px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(100, 30, 200, 0.5);
        }
        
        .crop-container h2 {
            color: #c7b8ff;
            text-align: center;
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .crop-instructions {
            color: #a090cc;
            text-align: center;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .crop-wrapper {
            position: relative;
            margin: 20px auto;
            background: #1e192d;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 300px;
            height: 300px;
            border: 3px solid #6ab7ff;
            box-shadow: 0 0 20px rgba(106, 183, 255, 0.3);
        }
        
        #cropCanvas {
            display: block;
            cursor: move;
            touch-action: none;
            border-radius: 50%;
        }
        
        .circle-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
            border-radius: 50%;
            box-shadow: inset 0 0 0 9999px rgba(0, 0, 0, 0.7);
        }
        
        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            justify-content: center;
            margin: 20px 0;
            padding: 15px;
            background: rgba(40, 35, 55, 0.8);
            border-radius: 8px;
        }
        
        .zoom-controls label {
            color: #c7b8ff;
            font-weight: bold;
        }
        
        .zoom-slider {
            flex: 1;
            max-width: 300px;
            height: 8px;
            accent-color: #6ab7ff;
        }
        
        .zoom-value {
            color: #6ab7ff;
            font-weight: bold;
            min-width: 60px;
            text-align: center;
        }
        
        .crop-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }
        
        .crop-buttons button {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .btn-crop-apply {
            background: linear-gradient(to right, #00c853, #64dd17);
            color: white;
        }
        
        .btn-crop-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 200, 83, 0.4);
        }
        
        .btn-crop-cancel {
            background: linear-gradient(to right, #ff3b3b, #ff6b6b);
            color: white;
        }
        
        .btn-crop-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 59, 59, 0.4);
        }
        
        .avatar-preview-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #5a1a8f;
            margin: 10px auto;
            display: block;
            box-shadow: 0 4px 12px rgba(100, 30, 200, 0.3);
            background: #1e192d;
        }
        
        .avatar-preview-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .upload-btn {
            display: inline-block;
            padding: 10px 24px;
            background: linear-gradient(to right, #3a0d6a, #5a1a8f);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(100, 30, 200, 0.4);
        }
        
        #avatarInput {
            display: none;
        }
    </style>
</head>
<body>
    <div style="max-width: 600px; margin: 30px auto; background: rgba(30,25,45,0.95); padding: 20px; border-radius: 12px; border: 1px solid #5a1a8f;">
        <h2 style="text-align: center; color: #c7b8ff;">Редактировать профиль</h2>
        
        <?php if ($error): ?>
        <div style="text-align: center; padding: 10px; background: rgba(200, 50, 50, 0.3); color: #ffaaaa; margin: 10px auto; max-width: 600px; border-radius: 6px;">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div style="text-align: center; padding: 10px; background: rgba(40, 200, 80, 0.3); color: #aaffaa; margin: 10px auto; max-width: 600px; border-radius: 6px;">
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" id="profileForm">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="avatar_cropped" id="avatarCropped">
            
            <!-- Круглое превью аватарки -->
            <div class="avatar-preview-container">
                <img id="currentAvatar" src="../<?= htmlspecialchars($_SESSION['avatar'] ?? 'imang/default.png') ?>" alt="Аватарка" class="avatar-preview-circle">
                <div style="margin-top: 8px; font-size: 12px; color: #c7b8ff;">Текущая аватарка</div>
                <label for="avatarInput" class="upload-btn">📷 Загрузить новую</label>
                <input type="file" id="avatarInput" accept="image/*">
            </div>
            
            <!-- 🔹 Кнопка сброса аватарки -->
            <?php if ($_SESSION['avatar'] !== 'imang/default.png'): ?>
            <div style="text-align: center; margin: 15px 0;">
                <button type="submit" name="action" value="reset_avatar"
                    style="background: linear-gradient(to right, #ff3b3b, #ff6b6b); color: white; padding: 8px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;"
                    onclick="return confirm('Вернуть стандартную аватарку?')">
                    🔄 Сбросить аватарку
                </button>
            </div>
            <?php endif; ?>
            
            <div style="margin: 12px 0;">
                <label style="display: block; color: #c7b8ff; margin-bottom: 4px;">Логин:</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>"
                    style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;" required>
            </div>
            
            <div style="margin: 12px 0;">
                <label style="display: block; color: #c7b8ff; margin-bottom: 4px;">Email:</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>"
                    style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;" required>
            </div>
            
            <div style="margin: 12px 0;">
                <label style="display: block; color: #c7b8ff; margin-bottom: 4px;">Новый пароль (оставьте пустым, если не меняете):</label>
                <input type="password" name="password"
                    style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
            </div>
            
            <div style="margin: 12px 0;">
                <label style="display: block; color: #c7b8ff; margin-bottom: 4px;">Подтвердите пароль:</label>
                <input type="password" name="confirm_password"
                    style="width: 100%; padding: 8px; background: #1e192d; border: 1px solid #5a1a8f; color: white; border-radius: 6px;">
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit"
                    style="background: linear-gradient(to right, #3a0d6a, #5a1a8f); color: white; padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer;">
                    💾 Сохранить изменения
                </button>
                <a href="view_db.php" style="color: #ff6b6b; text-decoration: none; margin-left: 15px;">Отмена</a>
            </div>
        </form>
    </div>
    
    <!-- Модальное окно для кроппинга -->
    <div id="cropModal" class="crop-modal">
        <div class="crop-container">
            <h2>✂️ Выберите область для аватарки</h2>
            <p class="crop-instructions">Перетащите изображение и используйте зум. Выход за пределы круга невозможен.</p>
            
            <div class="crop-wrapper">
                <canvas id="cropCanvas" width="300" height="300"></canvas>
                <div class="circle-overlay"></div>
            </div>
            
            <div class="zoom-controls">
                <label>🔍 Зум:</label>
                <input type="range" id="zoomSlider" class="zoom-slider" min="0.1" max="3" step="0.1" value="1">
                <span id="zoomValue" class="zoom-value">100%</span>
                <button type="button" id="zoomReset" style="padding: 6px 12px; background: #5a1a8f; color: white; border: none; border-radius: 4px; cursor: pointer;">Сброс</button>
            </div>
            
            <div class="crop-buttons">
                <button type="button" class="btn-crop-apply" id="applyCropBtn">✅ Применить</button>
                <button type="button" class="btn-crop-cancel" onclick="cancelCrop()">❌ Отмена</button>
            </div>
        </div>
    </div>

    <script>
        let cropCanvas = document.getElementById('cropCanvas');
        let ctx = cropCanvas.getContext('2d');
        let cropModal = document.getElementById('cropModal');
        let avatarInput = document.getElementById('avatarInput');
        let avatarCropped = document.getElementById('avatarCropped');
        let currentAvatar = document.getElementById('currentAvatar');
        let applyCropBtn = document.getElementById('applyCropBtn');
        let zoomSlider = document.getElementById('zoomSlider');
        let zoomValue = document.getElementById('zoomValue');
        let zoomReset = document.getElementById('zoomReset');
        
        let uploadedImage = null;
        let imgX = 0, imgY = 0;
        let zoom = 1;
        let isDragging = false;
        let lastMouseX = 0, lastMouseY = 0;
        
        const canvasSize = 300;
        const circleRadius = canvasSize / 2 - 10;
        
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('❌ Файл слишком большой. Максимум 5MB.');
                    avatarInput.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(event) {
                    uploadedImage = new Image();
                    uploadedImage.onload = function() {
                        initCropCanvas();
                    };
                    uploadedImage.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
        
        // ✅ Инициализация — изображение видно сразу
        function initCropCanvas() {
            zoom = 1;
            
            const imgAspect = uploadedImage.width / uploadedImage.height;
            const targetSize = canvasSize * 0.8;
            
            if (imgAspect > 1) {
                zoom = targetSize / uploadedImage.width;
            } else {
                zoom = targetSize / uploadedImage.height;
            }
            
            zoom = Math.max(0.5, Math.min(3, zoom));
            zoomSlider.value = zoom;
            updateZoomDisplay();
            
            const imgWidth = uploadedImage.width * zoom;
            const imgHeight = uploadedImage.height * zoom;
            imgX = (canvasSize - imgWidth) / 2;
            imgY = (canvasSize - imgHeight) / 2;
            
            cropModal.style.display = 'flex';
            drawCanvas();
        }
        
        // ✅ Отрисовка — изображение яркое
        function drawCanvas() {
            ctx.clearRect(0, 0, canvasSize, canvasSize);
            
            let imgWidth = uploadedImage.width * zoom;
            let imgHeight = uploadedImage.height * zoom;
            ctx.drawImage(uploadedImage, imgX, imgY, imgWidth, imgHeight);
            
            ctx.beginPath();
            ctx.arc(canvasSize / 2, canvasSize / 2, circleRadius, 0, Math.PI * 2);
            ctx.strokeStyle = '#6ab7ff';
            ctx.lineWidth = 3;
            ctx.stroke();
        }
        
        function constrainPosition(newX, newY) {
            let imgWidth = uploadedImage.width * zoom;
            let imgHeight = uploadedImage.height * zoom;
            
            const minX = canvasSize / 2 - imgWidth - circleRadius;
            const maxX = canvasSize / 2 + circleRadius;
            const minY = canvasSize / 2 - imgHeight - circleRadius;
            const maxY = canvasSize / 2 + circleRadius;
            
            newX = Math.max(minX, Math.min(newX, maxX));
            newY = Math.max(minY, Math.min(newY, maxY));
            
            return { x: newX, y: newY };
        }
        
        cropCanvas.addEventListener('mousedown', function(e) {
            isDragging = true;
            const rect = cropCanvas.getBoundingClientRect();
            lastMouseX = e.clientX - rect.left;
            lastMouseY = e.clientY - rect.top;
            cropCanvas.style.cursor = 'grabbing';
        });
        
        cropCanvas.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            
            const rect = cropCanvas.getBoundingClientRect();
            const currentX = e.clientX - rect.left;
            const currentY = e.clientY - rect.top;
            
            const deltaX = currentX - lastMouseX;
            const deltaY = currentY - lastMouseY;
            
            let newX = imgX + deltaX;
            let newY = imgY + deltaY;
            
            const constrained = constrainPosition(newX, newY);
            imgX = constrained.x;
            imgY = constrained.y;
            
            lastMouseX = currentX;
            lastMouseY = currentY;
            
            drawCanvas();
        });
        
        cropCanvas.addEventListener('mouseup', function() {
            isDragging = false;
            cropCanvas.style.cursor = 'move';
        });
        
        cropCanvas.addEventListener('mouseleave', function() {
            isDragging = false;
            cropCanvas.style.cursor = 'move';
        });
        
        cropCanvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            isDragging = true;
            const rect = cropCanvas.getBoundingClientRect();
            const touch = e.touches[0];
            lastMouseX = touch.clientX - rect.left;
            lastMouseY = touch.clientY - rect.top;
        });
        
        cropCanvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            if (!isDragging) return;
            
            const rect = cropCanvas.getBoundingClientRect();
            const touch = e.touches[0];
            const currentX = touch.clientX - rect.left;
            const currentY = touch.clientY - rect.top;
            
            const deltaX = currentX - lastMouseX;
            const deltaY = currentY - lastMouseY;
            
            let newX = imgX + deltaX;
            let newY = imgY + deltaY;
            
            const constrained = constrainPosition(newX, newY);
            imgX = constrained.x;
            imgY = constrained.y;
            
            lastMouseX = currentX;
            lastMouseY = currentY;
            
            drawCanvas();
        });
        
        cropCanvas.addEventListener('touchend', function() {
            isDragging = false;
        });
        
        zoomSlider.addEventListener('input', function() {
            zoom = parseFloat(this.value);
            updateZoomDisplay();
            drawCanvas();
        });
        
        zoomReset.addEventListener('click', function() {
            zoom = 1;
            zoomSlider.value = 1;
            updateZoomDisplay();
            
            const imgWidth = uploadedImage.width * zoom;
            const imgHeight = uploadedImage.height * zoom;
            imgX = (canvasSize - imgWidth) / 2;
            imgY = (canvasSize - imgHeight) / 2;
            
            drawCanvas();
        });
        
        function updateZoomDisplay() {
            zoomValue.textContent = Math.round(zoom * 100) + '%';
        }
        
        applyCropBtn.addEventListener('click', function() {
            const finalCanvas = document.createElement('canvas');
            const finalSize = 200;
            finalCanvas.width = finalSize;
            finalCanvas.height = finalSize;
            
            const finalCtx = finalCanvas.getContext('2d');
            
            finalCtx.beginPath();
            finalCtx.arc(finalSize / 2, finalSize / 2, finalSize / 2 - 2, 0, Math.PI * 2);
            finalCtx.closePath();
            finalCtx.clip();
            
            finalCtx.fillStyle = '#1e192d';
            finalCtx.fillRect(0, 0, finalSize, finalSize);
            
            const scale = finalSize / canvasSize;
            finalCtx.drawImage(
                cropCanvas,
                0, 0, canvasSize, canvasSize,
                0, 0, finalSize, finalSize
            );
            
            const croppedDataUrl = finalCanvas.toDataURL('image/jpeg', 0.9);
            currentAvatar.src = croppedDataUrl;
            avatarCropped.value = croppedDataUrl;
            cropModal.style.display = 'none';
        });
        
        function cancelCrop() {
            cropModal.style.display = 'none';
            avatarInput.value = '';
            uploadedImage = null;
        }
        
        cropModal.addEventListener('click', function(e) {
            if (e.target === cropModal) {
                cancelCrop();
            }
        });
    </script>
</body>
</html>