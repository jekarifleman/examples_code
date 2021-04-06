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

if (!isset($_POST['leads']['status'])) {
    file_put_contents(__DIR__ . "/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\tPost-параметр пуст\n", FILE_APPEND);
    exit(0); 
}

// Подключение библиотек
$basePath = __DIR__;
$serverPath = '/home/v/vsenadezrf/public_html/dev';
$serverFunctionsPath = "{$basePath}/functions";

// Подключаем нужные функции
$func_dir = 'functions/';
require_once($serverFunctionsPath . '/authorize.php');
require_once($serverFunctionsPath . '/get_contacts.php');
require_once($serverFunctionsPath . '/get_leads_by_ids.php');

// Проверка наличия файла настроек
if (!file_exists("{$basePath}/settings.json")) {
    file_put_contents("{$basePath}/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\tОтсутствует файл settings.json с настройками Amocrm\n", FILE_APPEND);
    exit(0);
}

// считываем из файла настройки амосрм
$settings = json_decode(file_get_contents("{$basePath}/settings.json"), true);

// Авторизируемся чтобы иметь необходимые полномочия
if (authorize($settings, $settings['subdomain'])) {

    // получаем список активных сделок из amo
    $postLeads = $_POST['leads']['status'];

    // для id сделок
    $leadIds = [];

    // перебор каждой полученной сделки
    foreach ($postLeads as $lead) {
        // проверка наличия email
        if (isset($lead['id'])) {
            // добавляем в общий массив id сделки
            $leadIds[] = $lead['id'];
        }
    }

    // если id сделок отсутствуют
    if (count($leadIds) === 0) {
        exit(0);
    }

    // получаем сделки по id
    $leads = getLeadsByIds($settings['subdomain'], $leadIds);

    // для id контактов
    $mainContacts = [];

    // перебор каждой полученной сделки
    foreach ($leads as $lead) {
        // проверка наличия email
        if (isset($lead['main_contact']['id'])) {
            // массив главных контактов сделок
            $mainContacts[] = $lead['main_contact']['id'];

        }
    }

    // если id контактов отсутствуют
    if (count($mainContacts) === 0) {
        exit(0);
    }

    // получение контактов по id с последующим получением email контактов
    $emailsFromContacts = getContacts($settings['subdomain'], $mainContacts);
    $contactsEmails = [];

    // проверяем получение контактов амосрм
    if ($emailsFromContacts != 'error') {
        // перебираем полученные контакты амосрм
        foreach ($emailsFromContacts as $contact)  {
            // перебираем поля контакта
            foreach ($contact['custom_fields'] as $field) {
                // если имя поля совпадает
                if ($field['name'] == 'Email') {
                    // создаем список id контакта => email
                    $contactsEmails[] = ($field['values'][0]['value']);

                    break;
                }
            }
            
        }
    } else {
        file_put_contents("{$basePath}/logs/wh_unisender.txt", date("Y-m-d H:i:s ", time()) . "\tОшибка получения контактов для email\n", FILE_APPEND);
    }

    if (count($contactsEmails) === 0) {
        file_put_contents("{$basePath}/logs/wh_unisender.txt", date("Y-m-d H:i:s ", time()) . "\tEmails отсутствуют\n", FILE_APPEND);
        exit(0);
    }


    // перебор всех emails с последующей отпиской из unisender
    foreach ($contactsEmails as $email) {

        // Ваш ключ доступа к API (из Личного Кабинета)
        $api_key = "6q4d1fmja8bdw4npda9u17e9jak463aziwcyz8cy";

        // Данные о контакте, которого надо отписать от списков
        $user_email = $email;
        $user_lists = $settings['unisender']['user_lists'];
        $user_type = $settings['unisender']['user_type'];

        // Создаём GET-запрос
        $api_url = "https://api.unisender.com/ru/api/unsubscribe?format=json".
            "&api_key=$api_key&list_ids=$user_lists".
            "&contact=$user_email&contact_type=$user_type";

        // Делаем запрос на API-сервер
        $result = file_get_contents($api_url);

        if ($result) {

            // Раскодируем ответ API-сервера
            $jsonObj = json_decode($result);
            if(null===$jsonObj) {
                // Ошибка в полученном ответе
                file_put_contents("{$basePath}/logs/wh_unisender.txt", date("Y-m-d H:i:s ", time()) . "Ошибка в полученном ответе\n", FILE_APPEND);
            } elseif(!empty($jsonObj->error)) {
                // Ошибка отписки контакта
                file_put_contents("{$basePath}/logs/wh_unisender.txt", date("Y-m-d H:i:s ", time()) . "An error occured: " . $jsonObj->error . "(code: " . $jsonObj->code . ")\n", FILE_APPEND);
            } else {
                // Адресат успешно отписан
                file_put_contents("{$basePath}/logs/wh_unisender.txt", date("Y-m-d H:i:s ", time()) . "Email: {$email} успешно удален из рассылки\n", FILE_APPEND);
            }
        } else {
            // Ошибка соединения с API-сервером
            file_put_contents("{$basePath}/logs/wh_unisender.txt", date("Y-m-d H:i:s ", time()) . "Ошибка соединения с unisender\n", FILE_APPEND);
        }
    }


}



//?>