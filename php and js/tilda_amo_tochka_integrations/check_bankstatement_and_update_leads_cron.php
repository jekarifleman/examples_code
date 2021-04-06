<?php

// Установка лимита времени и памяти для выполнения скрипта
set_time_limit(240);
ini_set('memory_limit', '512M');

// Обработчик ошибок
(!is_dir(__DIR__ . '/logs')) ? mkdir(__DIR__ . '/logs') : '';
set_error_handler(function ($errno, $errstr, $errfile, $errline){
    $errorMessege = "";
    switch ($errno)
        {
            case E_USER_ERROR || E_RECOVERABLE_ERROR:
                $errorMessege .= "Error: " . $errstr . "\n";
                $errorMessege .= $errfile . ':' . $errline . "\n";
                break;
            case E_USER_WARNING || E_WARNING:
                $errorMessege .= "Warning: " . $errstr . "\n";
                $errorMessege .= $errfile . ':' . $errline . "\n";
                break;
            case E_USER_NOTICE || E_NOTICE:
                $errorMessege .= "Notice: " . $errstr . "\n";
                $errorMessege .= $errfile . ':' . $errline . "\n";
                break;
            default:
                $errorMessege .= "Unknown: " . $errstr . "\n";
                $errorMessege .= $errfile . ':' . $errline . "\n";
                break;
        }
    file_put_contents(__DIR__ . "/logs/errors.txt", date("Y-m-d H:i:s ", time()) . $errorMessege, FILE_APPEND);
});


// подключаемые ф-ии для работы с БД
require_once(__DIR__ . "/functions/make_db_structure.php");
require_once(__DIR__ . "/functions/get_queued_tasks.php");
require_once(__DIR__ . "/functions/done_task.php");
require_once(__DIR__ . "/functions/update_task.php");

// подключаемые ф-ии для работы с Точкой и Амосрм
require_once(__DIR__ . "/functions/request_vipiska.php");
require_once(__DIR__ . "/functions/authorize.php");
require_once(__DIR__ . "/functions/create_note.php");
require_once(__DIR__ . "/functions/get_leads.php");
require_once(__DIR__ . "/functions/get_contacts.php");

require_once(__DIR__ . "/functions/update_leads.php");
require_once(__DIR__ . "/functions/change_status.php");

// берем из json-файла все настройки по amo и точке-банку
$settings = json_decode(file_get_contents(__DIR__ . '/settings.json'), true);

$subdomain = $settings['subdomain'];

$db = new SQLite3(__DIR__ . '/dot.db');

// формируем массив для задач из бд
$tasks = [];
$tasks = getQueuedTasks($db);

// веб-токен для запросов в банк
$webToken = $settings['tochka_bank']['web_token'];

// если количество задач равно 0, то выходим из программы
if (count($tasks) === 0) {
    file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Задач не обнаружено\n", FILE_APPEND);
    exit(0);
}

// из массива задач берем первую (в ответе должна быть только 1 задача с id == 1, сделано для совместимости)
$task = $tasks['0'];

//берем из задачи id самой задачи
$id = $task['id'];

if ($id !== 1) {
    file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Нарушена структура БД, id != 1\n", FILE_APPEND);
    exit(0);
}

//берем из задачи request_id выписки
$requestId = $task['request_id'];

// инициируем curl-запрос
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://enter.tochka.com/api/v1/statement/status/$requestId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);

// в заголовок Authorization вставляем web_token
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
  "Content-Type: application/json",
  "Authorization: Bearer $webToken"
));

// получаем ответ от сервера
$response = curl_exec($ch);

$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

$response = json_decode($response, true);

// проверка кода ответа от сервера точки-банка
if ($code == '200')  {
    // если статус выписки == ready, значит она готова
    if ($response['status'] == 'ready') {
        // делаем запрос на получение выписки платежей
        $payments = request_vipiska($webToken, $requestId);

        if (isset($payments['code_response'])) {
            // присваиваем переменной код ошибки из ответа
            $code_response = $payments['code_response'];

            // логгируем ошибку запроса на получение выписки
            file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Ошибка запроса на получение выписки в задаче (request_id: $requestId), code_response: {$code_response}\n", FILE_APPEND);

        } else {
            if (count($payments) === 0) {
                // если статус задачи изменен на done
                if (doneTask($db, $id)) {
                    file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Поступивших платежей в выписке не обнаружено. Задача (request_id: {$requestId}  успешно завершена, статус изменен на <Done>)\n", FILE_APPEND);
                    exit(0);
                } else {
                    // логгируем неудачное обновление даты последнего запуска проверки выписки
                    file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Ошибка в БД при смене статуса в задаче (request_id: $requestId)\n", FILE_APPEND);
                    exit(0);
                }
            }

            // получение и перебор сделок в амо, перебор платежей, поиск соответствий сделок и платежей, обновление сделок в амо
            if (change_status($payments, $settings, $subdomain)) {
                // если задача завершена
                if (doneTask($db, $id)) {
                    file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Задача (request_id: $requestId успешно завершена, статус изменен на <Done>)\n", FILE_APPEND);
                } else {
                    // логгируем неудачное обновление даты последнего запуска проверки выписки
                    file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Ошибка в БД при смене статуса в задаче (request_id: $requestId)\n", FILE_APPEND);
                }
            } else {
                // обновляем в БД дату последнего запуска проверки выписки, проверяем результат обновления
                if (!updateTask($db, $id)) {
                    // логгируем неудачное обновление даты последнего запуска проверки выписки
                    file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Ошибка в БД при обновлении даты последнего запуска проверки выписки при удачном получении ответа от сервера: $code\n", FILE_APPEND);
                }
            }
        }
    }

    // если статус выписки == queued, значит она еще не готова
    if ($response['status'] == 'queued') {
        // логгируем неудачное обновление даты последнего запуска проверки выписки
        file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Выписка не готова для request_id {$requestId}\n", FILE_APPEND);

    }
} else {
    file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Ошибка запроса на проверку выписки: $code\n", FILE_APPEND);
}









