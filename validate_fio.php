<?php
session_start();
// Простая проверка ФИО — без авторизации, можно запускать отдельно
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fio'])) {
    $fio = trim($_POST['fio']);
    $result = checkFIO($fio);
} else {
    $fio = '';
    $result = null;
}

function checkFIO($input) {
    $input = trim($input);
    if ($input === '') {
        return ['valid' => false, 'message' => '❌ Поле не должно быть пустым.'];
    }

    // Разбиваем на части по пробелу
    $parts = preg_split('/\s+/', $input);
    $count = count($parts);

    if ($count < 2 || $count > 3) {
        return ['valid' => false, 'message' => '❌ ФИО должно содержать 2 или 3 части (Фамилия Имя [Отчество]).'];
    }

    // Регулярное выражение для одной части: кириллица, допускается тире (но не в начале/конце), точка — только в конце и только одна
    $namePattern = '/^[А-ЯЁ][а-яё]+(-[А-ЯЁ][а-яё]+)*(\.[А-ЯЁ])?$/u';

    foreach ($parts as $i => $part) {
        // Особый случай: отчество/имя может быть "А.Б." — но только если это 2 буквы с точками
        // Пробуем распознать сокращённое ФИО: "Иванов А.Б."
        if (preg_match('/^[А-ЯЁ]\.$/', $part)) {
            // Одна заглавная буква и точка — допустимо для имени/отчества
            continue;
        }
        if (preg_match('/^[А-ЯЁ]\.[А-ЯЁ]\.$/', $part) && $count === 2) {
            // "А.Б." во второй части — допускается как имя+отчество вместе (если всего 2 части)
            // Но это редкий случай — можно разрешить по вашему усмотрению
            continue;
        }

        if (!preg_match($namePattern, $part)) {
            $examples = [
                'Иванов',
                'Петров-Сидоров',
                'А.',
                'А.Б.',
                'Иванович'
            ];
            return [
                'valid' => false,
                'message' => "❌ Недопустимый формат части: <code>" . htmlspecialchars($part) . "</code><br>" .
                            "✅ Допустимо: " . implode(', ', array_map('htmlspecialchars', $examples))
            ];
        }
    }

    return [
        'valid' => true,
        'message' => "✅ <strong>" . htmlspecialchars($input) . "</strong> — корректное ФИО. Подходит для поля `users.fio`."
    ];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Проверка ФИО</title>
    <style>
        body {
            font-family: sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #0f0a1a;
            color: #e0e0e0;
        }
        .box {
            background: rgba(30,25,45,0.8);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #5a1a8f;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            background: #1e192d;
            border: 1px solid #5a1a8f;
            color: white;
            border-radius: 6px;
            font-size: 16px;
        }
        button {
            background: linear-gradient(to right, #00c853, #64dd17);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
        }
        .result.success {
            background: rgba(40, 200, 80, 0.2);
            border: 1px solid #3a8a3a;
            color: #aaffaa;
        }
        .result.error {
            background: rgba(200, 50, 50, 0.2);
            border: 1px solid #8a3a3a;
            color: #ffaaaa;
        }
        code {
            background: #1e192d;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>Проверка ФИО для таблицы <code>users.fio</code></h2>
        <p>Введите фамилию, имя и (опционально) отчество:</p>
        <form method="POST">
            <input type="text" name="fio" value="<?= htmlspecialchars($fio) ?>" placeholder="Например: Иванов Иван Иванович" required>
            <button type="submit">Проверить</button>
        </form>

        <?php if ($result): ?>
        <div class="result <?= $result['valid'] ? 'success' : 'error' ?>" id="result">
            <?= $result['message'] ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Автопрокрутка к результату, если есть ошибка/успех
        if (document.getElementById('result')) {
            document.getElementById('result').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>