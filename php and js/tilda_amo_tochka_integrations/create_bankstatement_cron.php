<?php

// Установка часового пояса UTC
date_default_timezone_set('UTC');

// Установка лимита времени выполнения скрипта
set_time_limit(240);

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
require_once(__DIR__ . "/functions/get_latest_time.php");
require_once(__DIR__ . "/functions/add_task.php");

// берем из json-файла все настройки по amo и точке-банку
$settings = json_decode(file_get_contents(__DIR__ . '/settings.json'), true);

// создаем экземпляр бд
$db = new SQLite3(__DIR__ . '/dot.db');
 
// создаем структуру в бд
if (makeDbStructure($db)) {
    // веб-токен для запросов в банк
    $web_token = $settings['tochka_bank']['web_token'];

    // номер счета в банке
    $accountcode = $settings['tochka_bank']['account_code'];

    // БИК банка
    $bankcode = $settings['tochka_bank']['bank_code'];

    // конец периода выписки
    $dateend = date('Y-m-d', time());

    // переменная, отвечающая за то, есть ли в БД записи
    $hasTask = false;

    // запрос в бд на получение начала периода выписки
    $rowSqlTasks = getLatestTime($db);

    // если из бд вернулось "error DB", то логгируем ошибку запроса в БД и прекращаем выполнение скрипта..
    if ($rowSqlTasks == 'error DB') {
        file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Произошла ошибка при запросе в БД\n", FILE_APPEND);
        exit(0);
    }
    // ..иначе если из бд вернулся NULL, то за начало периода..
    elseif ($rowSqlTasks['time_end'] == NULL) {
        $datestart = '2019-01-21';//date('Y-m-d', time() - 86400);
    }
    // ..иначе за начало периода берем значение из БД
    else {
        // записи в бд есть
        $hasTask = true;

        if ($rowSqlTasks['status'] == 'done') {
            // если предыдущая задача завершена, то устанавливаем за начало периода дату завершения задачи
            $datestart = date('Y-m-d', $rowSqlTasks['time_end']);
        } else {
            // если предыдущая задача не завершена, то устанавливаем за начало периода дату начала задачи
            $datestart = date('Y-m-d', $rowSqlTasks['time_start']);
        }
    }

    // инициируем curl-запрос на создание выписки
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://enter.tochka.com/api/v1/statement");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);

    curl_setopt($ch, CURLOPT_POST, TRUE);

    curl_setopt($ch, CURLOPT_POSTFIELDS, "{
      \"account_code\": \"$accountcode\",
      \"bank_code\": \"$bankcode\",
      \"date_end\": \"$dateend\",
      \"date_start\": \"$datestart\"
    }");


    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Host: enter.tochka.com",
      "Accept: application/json",
      "Content-Type: application/json",
      "Authorization: Bearer $web_token"
    ));

    // curl-запрос
    $response = curl_exec($ch);

    // код ответа сервера
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // проверка ответа от сервера, если успешно, берем айди выписки и добавляем задачу на ее проверку, иначе логгируем bad-запрос
    if ($code == '200') {
        // id проверяемой выписки
        $response = json_decode($response, true);
        $requestId = $response['request_id'];

        // перевод даты начала выписки в формат timestamp
        $datestart = strptime($datestart, '%Y-%m-%d');
        $datestart = mktime(0, 0, 0, $datestart['tm_mon']+1, $datestart['tm_mday'], $datestart['tm_year']+1900);

        // перевод даты конца выписки в формат timestamp
        $dateend = strptime($dateend, '%Y-%m-%d');
        $dateend = mktime(0, 0, 0, $dateend['tm_mon']+1, $dateend['tm_mday'], $dateend['tm_year']+1900);

        // добавление задачи на проверку выписки в БД, логгирование результата
        if (addTask($db, $requestId, $datestart, $dateend, $hasTask)) {
            file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Задача на проверку выписки $requestId успешно добавлена\n", FILE_APPEND);
        } else {
            file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Произошла ошибка добавления задачи на проверку выписки $requestId\n", FILE_APPEND);
        }
    } else {
        file_put_contents(__DIR__ . "/logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Запрос на выписку не выполнен. response_code: $code\n", FILE_APPEND);
    }

}









