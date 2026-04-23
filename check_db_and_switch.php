<?php
/**
 * check_db_and_switch.php
 * 
 * Автоматически проверяет доступность БД и переключает режим:
 * - Если выбрана local, но она недоступна → переключает на global
 * - Если выбрана global, но она недоступна → переключает на local
 * - Если обе недоступны → показывает ошибку
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

/**
 * Проверяет доступность БД по конфигурации
 * @param string $mode 'local' или 'global'
 * @return bool true если БД доступна
 */
function isDbAvailable($mode) {
    global $DB_CONFIG;
    
    if (!isset($DB_CONFIG[$mode])) {
        return false;
    }
    
    $cfg = $DB_CONFIG[$mode];
    
    try {
        $pdo = new PDO(
            "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset=utf8",
            $cfg['username'],
            $cfg['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3 // Таймаут 3 секунды для быстрой проверки
            ]
        );
        // Простой запрос для проверки соединения
        $pdo->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Основная функция проверки и переключения
 * @return array ['success' => bool, 'mode' => string, 'message' => string]
 */
function checkAndSwitchDb() {
    // Определяем текущий режим (по умолчанию local)
    $current_mode = $_SESSION['db_mode'] ?? 'local';
    $original_mode = $current_mode;
    
    // Проверяем доступность текущей БД
    $current_available = isDbAvailable($current_mode);
    
    if ($current_available) {
        // ✅ Текущая БД доступна — всё хорошо
        return [
            'success' => true,
            'mode' => $current_mode,
            'message' => "✅ Подключение к {$current_mode} БД успешно."
        ];
    }
    
    // ❌ Текущая БД недоступна — пробуем альтернативу
    $fallback_mode = ($current_mode === 'local') ? 'global' : 'local';
    $fallback_available = isDbAvailable($fallback_mode);
    
    if ($fallback_available) {
        // ✅ Альтернативная БД доступна — переключаемся
        $_SESSION['db_mode'] = $fallback_mode;
        
        $msg = "⚠️ БД '{$current_mode}' недоступна. " .
               "Автоматически переключено на '{$fallback_mode}'.";
        
        // Логируем событие (если функция доступна)
        if (function_exists('logAction') && isset($_SESSION['user_id'])) {
            // Попытка залогировать (не критично если не получится)
            try {
                $pdo = getDBConnection($fallback_mode);
                logAction($pdo, $_SESSION['user_id'], $_SESSION['username'], 
                         'DB_MODE_SWITCHED', "С {$current_mode} на {$fallback_mode}");
            } catch (Exception $e) {
                // Игнорируем ошибку логирования
            }
        }
        
        return [
            'success' => true,
            'mode' => $fallback_mode,
            'message' => $msg
        ];
    }
    
    // ❌ Обе БД недоступны — критическая ошибка
    $_SESSION['db_mode'] = 'local'; // Сбрасываем на дефолт
    
    $error_msg = "❌ Критическая ошибка: ни локальная, ни глобальная БД недоступны.<br>" .
                 "Проверьте:<br>" .
                 "• Запущен ли MySQL на localhost:3306<br>" .
                 "• Доступен ли сервер 134.90.167.42:10306";
    
    return [
        'success' => false,
        'mode' => 'local',
        'message' => $error_msg
    ];
}

// === Автоматический запуск при подключении файла ===
// Возвращает результат проверки
$result = checkAndSwitchDb();

// Если вызван напрямую (не через require) — показываем результат
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Возвращаем результат для использования в других файлах
// (через global или возвращаемое значение)
$GLOBALS['db_check_result'] = $result;