<?php
// Смена статуса задачи
function doneTask($db, $id)
{
    // Запрос сманы статуса задачи
    $query = 'UPDATE `tasks` SET `status`="done" WHERE `id`=' . $id;
    $result = $db->query($query);
    
    if (!$result)
    {
        return false;
    }
    
    return true;
}
?>