<?php
// Добавление задачи формирования выписки
function addTask($db, $requestId, $timeStart, $timeEnd, $hasTask)
{
	if ($hasTask == false) {
	    // Запрос добавления информации о платеже
	    $query = sprintf('INSERT INTO `tasks` (`id`, `date`, `request_id`, `time_start`, `time_end`, `status`) VALUES (1, "%s", "%s", "%s", "%s", "%s")', time(), $requestId, $timeStart, $timeEnd, "queued");
	} else {
	    // Запрос добавления информации о платеже
	    $query = sprintf('UPDATE `tasks` SET `date` = "%s", `request_id` = "%s", `time_start` = "%s", `time_end` = "%s", `status` = "%s" WHERE `id` = 1', time(), $requestId, $timeStart, $timeEnd, "queued");
	}

    $result = $db->query($query);
    
    if (!$result)
    {
        return false;
    }
    
    return true;
}
