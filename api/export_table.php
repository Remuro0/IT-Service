<?php
session_start();
require_once '../auth.php';
requireAuth();

// Только для админа
if ($_SESSION['role'] !== 'admin') {
    die("Доступ запрещён");
}

require_once '../config.php';
$pdo = getDBConnection();
require_once '../vendor/autoload.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Параметры
    $table = $_GET['table'] ?? null;
    $format = $_GET['format'] ?? 'json';

    if (!in_array($format, ['json', 'xlsx', 'pdf'])) {
        die("Недопустимый формат.");
    }

    // Получаем список таблиц
    $stmt = $pdo->query("SHOW TABLES");
    $all_tables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $all_tables[] = $row[0];
    }

    if ($table && !in_array($table, $all_tables)) {
        die("Таблица не найдена.");
    }

    // === ЭКСПОРТ ОДНОЙ ТАБЛИЦЫ ===
    if ($table) {
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = $rows ? array_keys($rows[0]) : [];

        if ($format === 'json') {
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=\"$table.json\"");
            echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } elseif ($format === 'xlsx') {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($columns, null, 'A1');
            $sheet->fromArray(array_map('array_values', $rows), null, 'A2');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"$table.xlsx\"");
            $writer->save('php://output');
        } elseif ($format === 'pdf') {
            // Подключаем TCPDF из vendor
            require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetTitle("Экспорт таблицы $table");
            $pdf->AddPage();
            $html = "<h2>Таблица: $table</h2><table border='1' cellpadding='4'>";
            $html .= "<tr>" . implode('', array_map(fn($c) => "<th>$c</th>", $columns)) . "</tr>";
            foreach ($rows as $row) {
                $html .= "<tr>" . implode('', array_map(fn($v) => "<td>" . htmlspecialchars($v) . "</td>", $row)) . "</tr>";
            }
            $html .= "</table>";
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output("$table.pdf", 'D');
        }

    // === ЭКСПОРТ ВСЕЙ БАЗЫ (только JSON и XLSX) ===
    } else {
        if ($format === 'pdf') {
            die("PDF не поддерживается для экспорта всех таблиц.");
        }

        if ($format === 'json') {
            $data = [];
            foreach ($all_tables as $tbl) {
                $stmt = $pdo->query("SELECT * FROM `$tbl`");
                $data[$tbl] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=\"database_export.json\"");
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } elseif ($format === 'xlsx') {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $first = true;
            foreach ($all_tables as $tbl) {
                if (!$first) {
                    $spreadsheet->createSheet();
                }
                $sheet = $spreadsheet->setActiveSheetIndex($first ? 0 : $spreadsheet->getSheetCount() - 1);
                $sheet->setTitle(substr($tbl, 0, 31)); // Excel: макс. 31 символ
                $stmt = $pdo->query("SELECT * FROM `$tbl`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $columns = $rows ? array_keys($rows[0]) : ['(пустая таблица)'];
                $sheet->fromArray($columns, null, 'A1');
                $sheet->fromArray(array_map('array_values', $rows), null, 'A2');
                $first = false;
            }
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"database_export.xlsx\"");
            $writer->save('php://output');
        }
    }

} catch (Exception $e) {
    die("Ошибка экспорта: " . htmlspecialchars($e->getMessage()));
}
?>