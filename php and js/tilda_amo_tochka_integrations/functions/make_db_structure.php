<?php
function makeDbStructure($db)
{
    // Запрос создания таблицы задач при её отсутствии
    $query = "CREATE TABLE IF NOT EXISTS `tasks` (`id` INTEGER PRIMARY KEY NOT NULL, `date` INTEGER NOT NULL, `request_id` STRING NOT NULL, `time_start` INTEGER NOT NULL, `time_end` INTEGER NOT NULL, `status` STRING NOT NULL)";
    
    // Если запрос выполнить не удалось...
    if (!$db->exec($query))
    {
        file_put_contents(__DIR__ . "/../logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Ошибка создания таблицы tasks", FILE_APPEND);
        return false;
    }
    
    return true;
}
// ?>