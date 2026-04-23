<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // для локального теста

session_start();

// Защита: только авторизованные админы
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

// 🔢 Генерация одного случайного значения в пределах 10–90
$metric = rand(10, 90);

// ✅ Отправляем JSON
echo json_encode([
    'metric' => $metric,
    'min' => 10,
    'max' => 90,
    'timestamp' => date('H:i:s')
]);
?>