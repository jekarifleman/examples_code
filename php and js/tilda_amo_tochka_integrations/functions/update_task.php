<?php

// Смена последней даты проверки задачи
function updateTask($db, $id)
{
    // Запрос сманы статуса задачи
    $date = date('l, m.d.y H:i:s');
    $query = sprintf('UPDATE `tasks` SET `date`="%s" WHERE `id`= %d', $date, $id);
    $result = $db->query($query);
    
    if (!$result) {
        return false;
    }
    
    return true;
}

// ?>