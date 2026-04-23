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

$action = $_GET['action'] ?? '';
if ($action === 'to_global') {
    // 1️⃣ Сначала — синхронизация local → global
// Запускаем синхронизацию как внутренний скрипт
define('IN_SYNC_SCRIPT', true);
ob_start();
require_once '../sync_to_global.php';
ob_end_clean(); // ← подавляем вывод (если есть)

// Результат в $sync_result
global $sync_result;
if ($sync_result['success']) {
    $_SESSION['db_mode'] = 'global';
    $_SESSION['message'] = $sync_result['message'] . " 🔁 Переключено на глобальную БД.";
} else {
    $_SESSION['message'] = $sync_result['message'];
}

    // 2️⃣ Если синхронизация прошла — переключаемся на global
    if (strpos($_SESSION['message'] ?? '', '✅') !== false) {
        $_SESSION['db_mode'] = 'global';
        $_SESSION['message'] .= " 🔁 Переключено на глобальную БД.";
    }
} elseif ($action === 'to_local') {
    // Просто переключаем — без синхронизации
    $_SESSION['db_mode'] = 'local';
    $_SESSION['message'] = "🔁 Переключено на локальную БД.";
} else {
    $_SESSION['message'] = "❌ Недопустимое действие.";
}

header("Location: view_db.php");
exit;