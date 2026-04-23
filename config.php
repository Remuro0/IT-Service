<?php
// config.php — универсальный конфиг для локальной и глобальной БД

// === Конфигурации ===
$DB_CONFIG = [
    'local' => [
        'host' => 'localhost',
        'port' => '3306',
        'dbname' => 'local',
        'username' => 'root',
        'password' => '',
        'type' => 'local'
    ],
    'global' => [
        'host' => '',
        'port' => '',
        'dbname' => 'project_Tkachenko',
        'username' => '',
        'password' => '',
        'type' => 'global'
    ]
];

// === Функция получения подключения ===
function getDBConnection($mode = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Определяем режим: параметр → сессия → по умолчанию 'local'
    $mode = $mode ?? ($_SESSION['db_mode'] ?? 'local');
    
    if (!isset($GLOBALS['DB_CONFIG'][$mode])) {
        $mode = 'local';
    }
    
    $cfg = $GLOBALS['DB_CONFIG'][$mode];
    
    try {
        $pdo = new PDO(
            "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset=utf8",
            $cfg['username'],
            $cfg['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $_SESSION['db_mode'] = $mode;
        return $pdo;
        
    } catch (PDOException $e) {
        // 🔍 Детальная информация об ошибке
        $errorMessage = "❌ Ошибка подключения к БД '$mode':<br><br>";
        $errorMessage .= "<strong>Хост:</strong> {$cfg['host']}<br>";
        $errorMessage .= "<strong>Порт:</strong> {$cfg['port']}<br>";
        $errorMessage .= "<strong>База данных:</strong> {$cfg['dbname']}<br>";
        $errorMessage .= "<strong>Пользователь:</strong> {$cfg['username']}<br><br>";
        $errorMessage .= "<strong>Ошибка:</strong> " . $e->getMessage() . "<br><br>";
        
        if ($mode === 'local') {
            $errorMessage .= "<hr><strong>Возможные причины:</strong><br>";
            $errorMessage .= "1. MySQL не запущен<br>";
            $errorMessage .= "2. База данных '{$cfg['dbname']}' не существует<br>";
            $errorMessage .= "3. Неверный порт (попробуйте 3307 вместо 3306)<br>";
            $errorMessage .= "4. Неверный пароль<br><br>";
            $errorMessage .= "<strong>Решение:</strong><br>";
            $errorMessage .= "- Запустите MySQL через XAMPP/OpenServer<br>";
            $errorMessage .= "- Создайте базу данных: <code>CREATE DATABASE local;</code><br>";
            $errorMessage .= "- Проверьте порт MySQL в настройках";
        }
        
        throw new Exception($errorMessage);
    }
}

// === Совместимость со старым кодом (переменные $host, $dbname и т.д.) ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mode = $_SESSION['db_mode'] ?? 'local';
$cfg = $DB_CONFIG[$mode];

$host = $cfg['host'];
$port = $cfg['port'];
$dbname = $cfg['dbname'];
$username = $cfg['username'];
$password = $cfg['password'];
?>
