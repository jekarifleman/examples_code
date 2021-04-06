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
    file_put_contents(__DIR__ . "/logs/error.txt", date("Y-m-d h:i:s ", time()) . $errorMessege, FILE_APPEND);
});

// подключаемая ф-я для авторизации в amocrm
require_once(__DIR__ . "/functions/authorize.php");

// подключаемая ф-я для создания сделки в amocrm
require_once(__DIR__ . "/functions/create_lead.php");

header('Access-Control-Allow-Origin');
header('X-Content-Type-Options nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: "origin, x-requested-with, content-type"');
header('Access-Control-Allow-Methods: "PUT, GET, POST, DELETE, OPTIONS"');

// считываем из файла настройки амосрм
$settings = json_decode(file_get_contents(__DIR__ . "/settings.json"), true);

// субдомен amocrm
$subdomain = $settings['subdomain'];

// При получении запроса с настройками...
if (isset($_POST['settings']) && isset($_POST['settings']['contact_id'])) {
    // авторизация в amocrm
    if (authorize($settings, $subdomain)) {
        // количество билетов
        $countTicket = $_POST['settings']['leads']['0']['count'];

        // общая стоимость билетов
        $sale        = $_POST['settings']['leads']['0']['price'] * $countTicket;

        // id контакта amocrm, который будет прикреплен к созданной сделке
        $contactId   = $_POST['settings']['contact_id'];

        // статус сделки 'ушел на яндекс кассу'
        $statusId    = $settings['status_ushel_na_yandex'];

        // ответственный пользователь в сделке
        $respUserId  = $settings['responsible_user_id'];

        // тело добавления новой сделки в амосрм
        $addBody['add'] = array([
                            "name" => 'Lead from: forum.tyumbit.ru/pay', # имя сделки
                            "created_at" => time(), # дата создания сделки
                            "status_id" => $statusId, # статус сделки
                            "responsible_user_id" => $respUserId, # отвественный в сделке
                            "sale" => $sale, # бюджет сделки
                            "contacts_id" => [(string) $contactId], # прикрепленный контакт в сделке
                            "custom_fields" => array( # поля сделки
                                                        [
                                                            "id" => '238683', # id поля "количество билетов" 
                                                            "values" => array(
                                                                [
                                                                    // кол-во билетов
                                                                    "value" => $countTicket
                                                                ]
                                                            )
                                                        ]
                                                    )
                            ]);

        // создаем сделку в amocrm и получаем ее id
        $leadId = createLead($subdomain, $addBody);

        // если сделка добавилась
        if (($leadId !== false) && ($leadId !== '')) {
            file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Создана сделка для яндекс кассы\n", FILE_APPEND);
        } else {
            file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Ошибка при создании сделки для яндекс кассы\n", FILE_APPEND);

            // отвечаем ajax-запросу отрицательным ответом
            echo json_encode(Array('url' => 'no-url'));
            exit(0);
        }
        
        // отвечаем ajax-запросу положительным ответом
        echo json_encode(Array('url' => 'ok'));

    }
    else {
        file_put_contents(__DIR__ . "/logs/amo_dadata_error.txt", date("Y-m-d h:i:s ", time()) . " Ошибка при авторизации в амосрм\n", FILE_APPEND);

        // отвечаем ajax-запросу отрицательным ответом
        echo json_encode(Array('url' => 'no-url'));
        exit(0);
    }

}


