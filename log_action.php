<?php
// log_action.php — единая функция логирования
/**
 * Записывает событие в таблицу logs
 *
 * @param PDO $pdo Подключение к базе данных
 * @param int $userId ID пользователя
 * @param string $username Логин пользователя
 * @param string $eventType Тип события (например, 'login', 'delete_record')
 * @param string $message Дополнительное сообщение (опционально)
 */
function logAction($pdo, $userId, $username, $eventType, $message = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $pdo->prepare("
        INSERT INTO logs (event_type, message, ip_address, user_agent, created_at, source)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    
    $stmt->execute([$eventType, $message, $ip, $userAgent, $username]);
}
?>