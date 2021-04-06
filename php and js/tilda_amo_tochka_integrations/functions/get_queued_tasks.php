<?php
// Получение задач в статусе queued
function getQueuedTasks($db)
{
    // Массив невыполненных задач
    $tasks = [];
    
    // Запрос получения QUEUED-задач
    $query = 'SELECT * FROM `tasks` WHERE `status`="queued" AND `id` = 1';
    $result = $db->query($query);
    
    if (!$result) {
        return [];
    }
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tasks[] = $row;
    }
    
    return $tasks;
}
