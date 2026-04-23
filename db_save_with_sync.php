<?php
// db_save_with_sync.php

require_once __DIR__ . '/sync_to_global_table.php';

/**
 * Сохраняет запись в local БД и синхронизирует с global
 * @param PDO $pdo_local
 * @param string $table
 * @param array $data Ассоциативный массив данных
 * @param int|null $id ID записи (если null — INSERT, иначе UPDATE)
 * @return array ['success' => bool, 'id' => int|null, 'message' => string]
 */
function saveWithSync($pdo_local, $table, $data, $id = null) {
    try {
        if ($id !== null) {
            // UPDATE
            $set = [];
            $values = [];
            foreach ($data as $col => $val) {
                $set[] = "`$col` = ?";
                $values[] = $val;
            }
            $values[] = $id;
            
            $sql = "UPDATE `$table` SET " . implode(', ', $set) . " WHERE id = ?";
            $stmt = $pdo_local->prepare($sql);
            $stmt->execute($values);
            
        } else {
            // INSERT
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');
            
            $sql = "INSERT INTO `$table` (" . implode(', ', array_map(fn($c) => "`$c`", $columns)) . 
                   ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo_local->prepare($sql);
            $stmt->execute(array_values($data));
            
            $id = $pdo_local->lastInsertId();
        }
        
        // Синхронизация в global
        try {
            require_once __DIR__ . '/config.php';
            $pdo_global = getDBConnection('global');
            $result = syncTableToGlobal($pdo_local, $pdo_global, $table, $id);
            
            if (!$result['success']) {
                error_log("Ошибка синхронизации таблицы $table: " . ($result['error'] ?? 'unknown'));
            }
        } catch (Exception $e) {
            error_log("Не удалось подключиться к global БД: " . $e->getMessage());
        }
        
        return ['success' => true, 'id' => $id, 'message' => 'Запись сохранена'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'id' => null, 'message' => 'Ошибка БД: ' . $e->getMessage()];
    }
}
?>