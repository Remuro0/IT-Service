<?php
session_start();
require_once '../auth.php';
requireAuth();

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $role = trim($input['role'] ?? '');
    $seconds = (int)($input['seconds'] ?? 0);

    if (!in_array($role, ['user', 'engineer', 'manager', 'db_admin', 'admin'])) {
        throw new Exception('Недопустимая роль');
    }
    if ($seconds < 60 || $seconds > 315360000) { // 10 лет максимум
        throw new Exception('Диапазон: 60 сек – 10 лет');
    }

    require_once '../config.php';
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        INSERT INTO session_timeouts (role, timeout_seconds)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE timeout_seconds = VALUES(timeout_seconds)
    ");
    $stmt->execute([$role, $seconds]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}