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


// заголовки ответа
header('Access-Control-Allow-Origin');
header('X-Content-Type-Options nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: "origin, x-requested-with, content-type"');
header('Access-Control-Allow-Methods: "PUT, GET, POST, DELETE, OPTIONS"');

// Подключение библиотек
$basePath = __DIR__;
$serverPath = '/home/v/vsenadezrf/public_html/dev';
$serverFunctionsPath = "{$basePath}/functions";

// Подключаем нужные функции
$func_dir = 'functions/';
require_once($serverFunctionsPath . '/authorize.php');
require_once($serverFunctionsPath . '/create_contact.php');
require_once($serverFunctionsPath . '/check_contact.php'); 
require_once($serverFunctionsPath . '/create_lead.php');

// Проверка наличия файла настроек
if (!file_exists("{$basePath}/settings.json"))
{
    file_put_contents("{$basePath}/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\tОтсутствует файл settings.json с настройками Amocrm\n", FILE_APPEND);
    echo json_encode(['contact_id' => "error"]);
    exit(0);
}

// считываем из файла настройки амосрм
$settings = json_decode(file_get_contents("{$basePath}/settings.json"), true);

// Если пришёл запрос
if (isset($_POST['settings']['name']) && isset($_POST['settings']['phone']) && isset($_POST['settings']['email'])) {

    // Авторизируемся чтобы иметь необходимые полномочия
    if (authorize($settings, $settings['subdomain'])) {

        // берем post-параметры (имя, телефон, емаил)
        $name = $_POST['settings']['name'];
        $phone = $_POST['settings']['phone'];
        $email = $_POST['settings']['email'];
        
        // ищем контакт в amoCRM
        $contact = checkContact($settings['subdomain'], $phone);
        $contactId = $contact['id'] ?? '';
        
        // если контакт не существует то создаем его, иначе просто возвращаем найденный id контакта
        if ($contactId === '') {

            // Формируем структуру данных запроса добавления контакта
            $data['add'] = array(
                array(
                    'name' => $name,
                    'tags' => 'tyumbit',
                    'custom_fields' => array(
                        array(
                            'id' => $settings['contact_field_phone'],
                            'values' => array(
                                array(
                                    'value' => $phone,
                                    'enum' => 'MOB'
                                )
                            )
                        ),
                        array(
                            'id' => $settings['contact_field_email'],
                            'values' => array(
                                array(
                                    'value' => $email,
                                    'enum' => 'PRIV'
                                )
                            )
                        )
                    )
                )
            );
            
            $contactId = createContact($settings['subdomain'], $data);

            if ($contactId !== '') {

                // массив с контактом отправляем в ответ на запрос
                $contact = ['contact_id' => $contactId];

                // статус "первичный контакт" из воронки "партнеры", на котором будет создана сделка
                $statusId = $settings['status_partneri_pervichniy_kontakt'];

                // тело добавления новой сделки в амосрм
                $addBody['add'] = array([
                                    "name" => 'Lead from: forum.tyumbit.ru/thanks',
                                    "created_at" => time(),
                                    "status_id" => $statusId, 
                                    "responsible_user_id" => $settings['responsible_user_id'],
                                    "sale" => 0,
                                    "contacts_id" => [(string) $contactId]
                                    ]);

                // создаем сделку и берем ее id
                $leadId = createLead($settings['subdomain'], $addBody);

                // если сделка добавилась
                if (($leadId !== false) && ($leadId !== '')) {
                    file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Создана сделка {$leadId} в разделе партнеры\n", FILE_APPEND);
                } else {
                    file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Ошибка при создании сделки в разделе партнеры\n", FILE_APPEND);
                    echo json_encode(Array('contact_id' => 'error'));
                    exit(0);
                }

                // массив с контактом отправляем в ответ на запрос
                $contact = ['contact_id' => $contactId];
                echo json_encode($contact);
            } else {

                // говорим запросу об ошибке
                file_put_contents("{$basePath}/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\tПроизошла ошибка при создании контакта для раздела партнеры\n", FILE_APPEND);
                echo json_encode(['contact_id' => "error"]);
                exit(0);
            }

        } else {

            // массив с контактом, который будет отправлен в ответ на запрос
            $contact = ['contact_id' => $contactId];

            // статус "первичный контакт" из воронки "партнеры", на котором будет создана сделка
            $statusId = $settings['status_partneri_pervichniy_kontakt'];

            // тело добавления новой сделки в амосрм
            $addBody['add'] = array([
                                "name" => 'Lead from: forum.tyumbit.ru/thanks',
                                "created_at" => time(),
                                "status_id" => $statusId, 
                                "responsible_user_id" => $settings['responsible_user_id'],
                                "sale" => 0,
                                "contacts_id" => [(string) $contactId]
                                ]);

            // создаем сделку и берем ее id
            $leadId = createLead($settings['subdomain'], $addBody);

            // если сделка добавилась
            if (($leadId !== false) && ($leadId !== '')) {
                file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Создана сделка {$leadId} в разделе партнеры\n", FILE_APPEND);
            } else {
                file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Ошибка при создании сделки в разделе партнеры\n", FILE_APPEND);
                echo json_encode(Array('contact_id' => 'error'));
                exit(0);
            }

            // массив с контактом отправляем в ответ на запрос
            echo json_encode($contact);
        }

    } else {
        file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Ошибка при авторизации в амосрм\n", FILE_APPEND);

        echo json_encode(Array('contact_id' => 'error'));
        exit(0);
    }
} else {
    file_put_contents("{$basePath}/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\tОдин из post-параметров пуст\n", FILE_APPEND);
    echo json_encode(['contact_id' => "error"]);
    exit(0);
}

?>