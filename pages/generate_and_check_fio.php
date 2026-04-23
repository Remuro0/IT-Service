<?php
session_start();
require_once '../auth.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

try {
    // 🔹 Абсолютный путь к директории, где лежит RandomNameGenerator.class
    $projectRoot = 'C:\\Users\\Yegor\\Desktop\\Integration';
    
    // 🔹 Проверяем, существует ли папка
    if (!is_dir($projectRoot)) {
        throw new Exception("Папка проекта не найдена: " . htmlspecialchars($projectRoot));
    }

    // 🔹 Команда: переходим в папку и запускаем Java
    $command = "cd " . escapeshellarg($projectRoot) . " && java -Dfile.encoding=UTF-8 RandomNameGenerator 2>&1";
    $output = shell_exec($command);
    $fio = trim($output);

    // 🔹 Проверяем, не ошибка ли вернулась от Java
    if (strpos($fio, 'Error:') !== false || strpos($fio, 'Exception') !== false) {
        throw new Exception("Java ошибка: " . htmlspecialchars($fio));
    }

    if ($fio === '') {
        throw new Exception("Java не вывела ФИО. Убедитесь, что:\n1. RandomNameGenerator.class существует в $projectRoot\n2. Метод main() выводит System.out.println(generateFullName())");
    }

    // 🔹 Валидация ФИО
    $valid = validateFIO($fio);
    $message = $valid
        ? "✅ <strong>" . htmlspecialchars($fio) . "</strong> — подходит для поля <code>fio</code>."
        : "❌ <strong>" . htmlspecialchars($fio) . "</strong> — не подходит: содержит запрещённые символы.";

    echo json_encode([
        'success' => true,
        'fio' => $fio,
        'valid' => $valid,
        'message' => $message
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function validateFIO($input) {
    $input = trim($input);
    if (!$input) return false;

    $parts = preg_split('/\s+/', $input);
    if (count($parts) < 2 || count($parts) > 3) return false;

    // Допустимые форматы:
    // - Полное: "Иванов", "Петров-Сидоров"
    // - Сокращённое: "И.", "А.Б."
    $pattern = '/^([А-ЯЁ][а-яё]+(-[А-ЯЁ][а-яё]+)*)$|^([А-ЯЁ]\.)$|^([А-ЯЁ]\.[А-ЯЁ]\.)$/u';

    foreach ($parts as $part) {
        if (!preg_match($pattern, $part)) {
            return false;
        }
    }
    return true;
}
?>