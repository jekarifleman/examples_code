<?php
// Получение самой новой time_end
function getLatestTime($db)
{   

    // Запрос получения новейшей time_end
    $query = 'SELECT `time_end`, `time_start`, `status` FROM `tasks` WHERE `id` = 1';
    $result = $db->query($query);
    
    if (!$result) {
        return 'error DB';
    }

    return $result->fetchArray(SQLITE3_ASSOC);
}
//?>