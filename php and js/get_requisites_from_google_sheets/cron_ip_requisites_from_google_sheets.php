<?php
// Установка часового пояса UTC
date_default_timezone_set('UTC');

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
    file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d H:i:s ", time()) . $errorMessege, FILE_APPEND);
});

// Подключение библиотек
$basePath = __DIR__;
$serverPath = '/home/v/vsenadezrf/public_html/dev';
$serverFunctionsPath = "{$serverPath}/functions";

// Подключение функций
require_once("{$serverPath}/vendor/autoload.php");
require_once("{$serverFunctionsPath}/include_functions.php");
require_once "{$basePath}/SpreadSheetClass.php";

//use GuzzleHttp\Psr7;
use Carbon\Carbon;

// Подключение серверных функций
if (!includeFunctions($serverFunctionsPath)) {
    file_put_contents("{$basePath}/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\tНе удалось подключить серверные функции\n", FILE_APPEND);
    exit(0);
}

// Проверка наличия файла настроек
if (!file_exists("{$basePath}/sps86_spsm_settings.json")) {
    file_put_contents("{$basePath}/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\tОтсутствует файл settings.json с настройками Amocrm\n", FILE_APPEND);
    exit(0);
}

// считываем из файла настройки амосрм
$allSettings = json_decode(file_get_contents("{$basePath}/sps86_spsm_settings.json"), true);

// объект для хранения timestamp конца вчерашнего дня
$startDateObj = Carbon::now('UTC'); 
$startDateObj->setTimezone('UTC');
$startDateObj->timestamp = $startDateObj->timestamp - 3600 * 3;
$startDate = $startDateObj->format('D d M Y H:i:s eO');

$endDateObj = Carbon::now('UTC'); 
$endDateObj->setTimezone('UTC');
//$endDateObj->timestamp = $startTime->timestamp - 3600 * 24 * 7 + 3600;
$endDate = $endDateObj->format('D d M Y H:i:s eO');

foreach ($allSettings as $settings) {
    // Берем ID таблицы из файла настроек
    $spreadsheetId = $settings['google_spread_sheet_id'];

    // название листа
    $titleSheet = $settings['google_title_sheet'];

    // Путь к файлу ключа сервисного аккаунта
    $googleAccountKeyFilePath = $settings['google_acc_key_file_path'];

    // создаем экземпляр класса Google Sheets для запросов к api google sheets
    $googleSheets = new GSpreadSheet($googleAccountKeyFilePath);

    // передаем id таблицы
    $googleSheets->setSpreadsheetId($spreadsheetId);

    // запрашиваем записи из таблицы
    $googleSheetsRows = $googleSheets->getSheetAllData($titleSheet);

    if ($googleSheetsRows == NULL) {
        exit(0);
    }

    // текущий субдомен, для примечаний
    $subdomain = $settings['subdomain'];

    // статусы, на которых проверяются сделки
    $statuses = $settings['statuses'];

    // авторизация в амосрм
    try {
        $session = amoSession($settings);
    }
    catch(Exception $e) {
        // повторная авторизация в амосрм
        $session = amoSession($settings);
    }

    // получение списка пользователей amocrm
    for ($i = 0; $i < 3; $i++) {
        try {
            $allRespUsers = getRespUsers($session);
            break;
        } catch(Exception $e) {
            file_put_contents("{$basePath}/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\t{$subdomain}: Ошибка при запрашивании списка пользователей amocrm, i={$i}\n", FILE_APPEND);
            sleep(1);
            unset($session);
            // повторная авторизация в амосрм
            $session = amoSession($settings);
            if ($i >= 3) {
                $allRespUsers = getRespUsers($session);
                break;
            }
        }
    }

    $allRespUsers = array_column($allRespUsers, 'name', 'id');

    // Получение списка событий смены статуса сделок за период
    for ($i = 0; $i < 3; $i++) {
        try {
            $events = getEvents($session, $startDateObj, $endDateObj, 2, 25);
            break;
        } catch(Exception $e) {
            file_put_contents("{$basePath}/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\t{$subdomain}: Ошибка при запрашивании событий смены статуса сделок, i={$i}\n", FILE_APPEND);
            sleep(1);
            unset($session);
            // повторная авторизация в амосрм
            $session = amoSession($settings);
            if ($i >= 3) {
                $events = getEvents($session, $startDateObj, $endDateObj, 2, 25);
                break;
            }
        }
    }

    $leadsIds = [];

    foreach ($events as $event) {
        if (($event['object']['lead']['entity'] ?? '') == 'leads'
            && isset($event['object']['lead']['id'])
        ) {
            $leadsIds[] = $event['object']['lead']['id'];
        }

        if (($event['object']['entity'] ?? '') == 'leads'
            && isset($event['object']['id'])
        ) {
            $leadsIds[] = $event['object']['id'];
        }
    }

    // получение сделок по id
    for ($i = 0; $i < 3; $i++) {
        try {
            $leads = getLeadsByIds($session, $leadsIds);
            break;
        } catch(Exception $e) {
            file_put_contents("{$basePath}/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\t{$subdomain}: Ошибка при запрашивании сделок, i={$i}\n", FILE_APPEND);
            sleep(1);
            unset($session);
            // повторная авторизация в амосрм
            $session = amoSession($settings);
            if ($i >= 3) {
                $leads = getLeadsByIds($session, $leadsIds);
                break;
            }
        }
    }

    // тело для обновляемых сделок
    $bodyLeads = [];

    foreach ($leads as $lead) {
        $leadStatus = $lead['status_id'];
        if (!in_array($leadStatus, $statuses)) continue;

        $respUserId = $lead['responsible_user_id'];
        $userRowGoogleSheet = [];

        foreach ($googleSheetsRows as $row) {
            if (strpos($row['0'], $allRespUsers[$respUserId]) !== false) {
                $userRowGoogleSheet = $row;
            }
        }

        if (count($userRowGoogleSheet) === 0) continue;

        $leadId = $lead['id'];
        $customFields = $lead['custom_fields'] ?? [];

        // исходный массив с пустыми значениями полей, ключами являются id полей, будет заполняться при распарсивании полей из сделок для последующего сравнения с данными из гугл-таблицы
        $ipRequisitesFields = [
            $settings['ip_fields']['name']          => '',
            $settings['ip_fields']['phone']         => '',
            $settings['ip_fields']['ogrnip']        => '',
            $settings['ip_fields']['date_register'] => '',
            $settings['ip_fields']['inn']           => '',
            $settings['ip_fields']['r_schet']       => '',
            $settings['ip_fields']['kor_schet']     => '',
            $settings['ip_fields']['name_bank']     => '',
            $settings['ip_fields']['bik']           => ''
        ];

        // перебор кастомных полей амосрм
        foreach ($customFields as $amoField) {
            if (array_key_exists($amoField['id'], $ipRequisitesFields)) {
                $ipRequisitesFields[$amoField['id']] = $amoField['values']['0']['value'];
            }
        }

        $fieldsValuesBody = [];

        $i = 0;

        foreach ($ipRequisitesFields as $idField => $valueField) {
            if ($userRowGoogleSheet[$i] != $valueField)
            $fieldsValuesBody[]  =  [
                'id' => $idField,
                'values' => array([
                    'value' => $userRowGoogleSheet[$i]
                ])
            ];
            $i++;
        }

        if (count($fieldsValuesBody) > 0) {
            // формируем тело обновляемой сделки и добавляем в общий массив сделок
            $bodyLeads[] = array(
                "id"            => $leadId,
                "updated_at"    => time() + 10,
                "custom_fields" => $fieldsValuesBody
            );
        }

        unset($leadStatus);
        unset($respUserId);
        unset($userRowGoogleSheet);
        unset($leadId);
        unset($customFields);
        unset($ipRequisitesFields);
        unset($fieldsValuesBody);
    }

    // Обновляем сделки
    if (count($bodyLeads) > 0) {
        // вытаскиваем id сделко из общего массива сделок
        $idsBodyLeads = array_column($bodyLeads, 'id');
        $idsBodyLeads = implode(', ', $idsBodyLeads);

        // обновление сделок
        try {
            $updatedLeads = updateLeads($session, $bodyLeads);
        } catch(Exception $e) {
            file_put_contents("{$basePath}/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\t{$subdomain}: Ошибка при обновлении сделок\n", FILE_APPEND);
            sleep(1);
            unset($session);
            $session = amoSession($settings);
            $updatedLeads = updateLeads($session, $bodyLeads);
        }

        if (count($updatedLeads) == count($bodyLeads)) {
            file_put_contents("{$basePath}/logs/logs.txt", date("Y-m-d H:i:s ", time()) . "{$subdomain} Обновлено " . count($updatedLeads) . " сделок, их id: " . $idsBodyLeads . "\n", FILE_APPEND);
        } else {
            file_put_contents("{$basePath}/logs/logs.txt", date("Y-m-d H:i:s ", time()) . "{$subdomain} Сделки обновлены не полностью, " . count($updatedLeads) . ' из ' . count($bodyLeads) . ", их id: " . $idsBodyLeads . "\n", FILE_APPEND);
            file_put_contents("{$basePath}/logs/body_leads.txt", date("Y-m-d H:i:s ", time()) . "{$subdomain} " . print_r($bodyLeads, true) . "\n", FILE_APPEND);
        }

        unset($idsBodyLeads);
        unset($updatedLeads);
    } 

    unset($spreadsheetId);
    unset($titleSheet);
    unset($googleAccountKeyFilePath);
    unset($googleSheets);
    unset($googleSheetsRows);
    unset($subdomain);
    unset($statuses);
    unset($session);
    unset($allRespUsers);
    unset($events);
    unset($leadsIds);
    unset($events);
    unset($leads);
    unset($bodyLeads);

}






function getRespUsers($session)
{
    $url = sprintf("/api/v2/account?with=users");
    
    // Выполнение запроса
    $response = $session->get($url);

    // Если запрос не прошёл успешно...
    if ($response->getStatusCode() !== 200) {
        // Выход из рекурсии
        return [];
    }
    
    // Преобразование ответа в массив
    $response = json_decode((string) $response->getBody(), true);
    
    // Если записей в ответе нет...
    if (!isset($response['_embedded']['users'])) {
        // Выход из рекурсии
        return [];
    }
    
    // Вход в рекурсию (объединение результатов запросов в один массив)
    return $response['_embedded']['users'];
}

// получение списка событий амосрм
function getEvents($session, $startTimeObj, $endTimeObj, $entity = 2, $eventType = 25, $pageNumber = 1)
{
    // Заголовки запроса
    $headers = array(
        'X-Requested-With' => 'XMLHttpRequest'
    );

    // Параметры запроса
    //$params = [
    //    'filter_date_switch' => 'created',
    //    'useFilter' => 'y',
    //    'filter' => [
    //        'entity' => $entity,
    //        'event_type' => $eventType
    //    ],
    //    'filter_date_from' => $startTimeObj->format('d.m.Y'),
    //    'filter_date_to' => $endTimeObj->format('d.m.Y'),
    //    'PAGEN_1' => $pageNumber,
    //    'json' => 1
    //];

    $params = 'filter_date_switch=created&filter%5Bentity%5D%5B%5D=' . $entity . '&filter%5Bevent_type%5D%5B%5D=' . $eventType . '&filter_date_from='. $startTimeObj->format('d.m.Y') . '&filter_date_to=' . $endTimeObj->format('d.m.Y') . '&useFilter=y&json=1&PAGEN_1=' . $pageNumber;

    //$url = sprintf("/ajax/events/list");
    $url = sprintf("/ajax/events/list/?%s", $params);
    
    // Увеличение номера страницы для выборки следующей партии записей
    $pageNumber += 1;

    // Выполнение запроса
    //$response = $session->post($url, ['headers' => $headers, 'form_params' => $params]);
    $response = $session->get($url, ['headers' => $headers]);

    // Если запрос не прошёл успешно...
    if ($response->getStatusCode() !== 200)
    {
        // Выход из рекурсии
        return [];
    }

    // Преобразование ответа в массив
    $response = json_decode((string) $response->getBody(), true);
    //return $response['response']['items'];
    
    // Если записей в ответе нет...
    if (!isset($response['response']['items']))
    {
        // Выход из рекурсии
        return [];
    }

    // Если записи закончились (получена последняя партия)...
    if (count($response['response']['items']) < 60)
    {
        // Выход из рекурсии
        return $response['response']['items'];
    }

    // Вход в рекурсию (объединение результатов запросов в один массив)
    return array_merge($response['response']['items'], getEvents($session, $startTimeObj, $endTimeObj, $entity, $eventType, $pageNumber));
}