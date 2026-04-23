<?php
// test_db.php — проверка подключения к MySQL

echo "<h2>🔍 Проверка подключения к MySQL</h2>";

$host = 'localhost';
$port = '3306';
$dbname = 'local';
$username = 'root';
$password = '';

echo "<p><strong>Попытка подключения...</strong></p>";
echo "<ul>";
echo "<li>Хост: $host</li>";
echo "<li>Порт: $port</li>";
echo "<li>База данных: $dbname</li>";
echo "<li>Пользователь: $username</li>";
echo "</ul>";

try {
    // Сначала без указания базы данных
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ <strong>Подключение к MySQL успешно!</strong></p>";
    
    // Проверяем версию
    $version = $pdo->query("SELECT VERSION()")->fetchColumn();
    echo "<p>📊 Версия MySQL: <strong>$version</strong></p>";
    
    // Создаём базу данных если нет
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✅ База данных '$dbname' создана/существует</p>";
    
    // Подключаемся к базе
    $pdo->exec("USE `$dbname`");
    echo "<p style='color: green;'>✅ Подключение к базе '$dbname' успешно!</p>";
    
    echo "<hr><p><a href='index.php'>→ Перейти на главную</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ <strong>Ошибка подключения!</strong></p>";
    echo "<p><strong>Код ошибки:</strong> " . $e->getCode() . "</p>";
    echo "<p><strong>Сообщение:</strong> " . $e->getMessage() . "</p>";
    
    echo "<hr><h3>🔧 Что делать:</h3>";
    echo "<ol>";
    echo "<li>Запустите MySQL (через XAMPP/OpenServer)</li>";
    echo "<li>Проверьте порт (по умолчанию 3306)</li>";
    echo "<li>Проверьте пароль пользователя root</li>";
    echo "<li>Если используете OpenServer — проверьте настройки MySQL</li>";
    echo "</ol>";
    
    echo "<hr><h3>📝 Команды для создания БД:</h3>";
    echo "<pre>";
    echo "mysql -u root -p\n";
    echo "CREATE DATABASE local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
    echo "EXIT;\n";
    echo "</pre>";
}
?>