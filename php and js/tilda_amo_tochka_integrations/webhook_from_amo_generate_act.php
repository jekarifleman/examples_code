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

// подключаем pdf-библиотеку
require_once($basePath . '/../dompdf/autoload.inc.php');

// библиотека отправки писем
require_once($basePath . "/../vendor/autoload.php");

// подключаемая ф-я для отправки писем на email
require_once($basePath . "/functions/send_act.php");

require_once($serverFunctionsPath . '/authorize.php');
require_once($serverFunctionsPath . '/get_leads_by_ids.php');
require_once($serverFunctionsPath . '/get_contacts.php');
require_once($serverFunctionsPath . '/act.php');

// Задание namespace'ов PhpMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Проверка наличия файла настроек
if (!file_exists("{$basePath}/settings.json")) {
    file_put_contents("{$basePath}/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\tОтсутствует файл settings.json с настройками Amocrm\n", FILE_APPEND);
    exit(0);
}

// считываем из файла настройки амосрм
$settings = json_decode(file_get_contents("{$basePath}/settings.json"), true);

if (!isset($settings['send_act_email'])) {
    file_put_contents(__DIR__ . "/logs/errors.txt", date("Y-m-d H:i:s ", time()) . "\tПараметр send_act_email в файле settings.json отсутствует\n", FILE_APPEND);
    exit(0); 
}

// Авторизируемся чтобы иметь необходимые полномочия
if (authorize($settings, $settings['subdomain'])) {
    // получаем список активных сделок из amo
    $postLeads = $_POST['leads']['status'];

    $leadsValueForActs = [];

    // если в настройках не указан yes, то перебираем массив со значениями для генерации акта, затем по каждому генерируем акт без отправки на почту
    if ($settings['send_act_email'] != 'yes') {
        // перебор каждой полученной сделки
        foreach ($postLeads as $lead) {
            // для инн, номера счета и количества билетов
            $inn = '';
            $pdfNumber = '';
            $quality = -1;

            // проверка статуса сделки
            if (in_array($lead['status_id'], $settings['statuses_generate_act']))  {

                
                // перебор каждого поля сделки
                foreach ($lead['custom_fields'] as $field) {
                    // проверка, что поле инн заполнено
                    if ($field['id'] == $settings['field_inn_id']) {
                        $inn = $field['values'][0]['value'];
                        file_put_contents('f1.txt', 'asdf');
                    }

                    // проверка, что поле номер счета заполнено
                    if ($field['id'] == $settings['field_schet_id']) {
                        $pdfNumber = $field['values'][0]['value'];
                        file_put_contents('f2.txt', 'asdf');
                    }

                    // проверка, что поле количество билетов заполнено
                    if ($field['id'] == $settings['field_countticket_id']) {
                        $quality = $field['values'][0]['value'];
                        file_put_contents('f3.txt', 'asdf');
                    }
                }

                // если необходимые поля заполнены
                if (($inn !== '') && ($pdfNumber !== '') && ($quality !== -1)) {    
                    // массив со значениями полей из сделки для формирования акта
                    $leadsValueForActs[] = [
                        'id_lead' => $lead['id'],
                        'counterparty_inn' => $inn,
                        'payment_amount' => $lead['price'],
                        'quality' => $quality,
                        'pdf_number' => $pdfNumber,
                        'main_contact' => '',
                        'status_id' => $lead['status_id']
                    ];
                }
                
            }
        }

        // перебор каждого набора параметров сделки для генерации и отправки актов
        foreach ($leadsValueForActs as $leadsValue) {

            $valuesForAct = [];

            // id сделки
            $valuesForAct['id_lead'] = $leadsValue['id_lead'];

            // инн
            $valuesForAct['counterparty_inn'] = $leadsValue['counterparty_inn'];

            // бюджет(платеж)
            $valuesForAct['payment_amount'] = $leadsValue['payment_amount'];

            // количество билетов
            $valuesForAct['quality'] = $leadsValue['quality'];

            // номер pdf-счета, номер акта будет идентичен
            $valuesForAct['pdf_number'] = $leadsValue['pdf_number'];

            // id контакта(для совместимости)
            $valuesForAct['main_contact'] = $leadsValue['main_contact'];

            // email, в этом блоке не используется
            $contactsEmails = '';

            file_put_contents('f4.txt', print_r($leadsValue, true));

            // генерируем акт по счету, без отправки на email
            generateAct($valuesForAct, $settings, $contactsEmails, $basePath);
        }   

        exit(0);
    }   

    // для id сделок, если будет отправка акта на email
    $leadIds = [];

    // перебор каждой полученной сделки
    foreach ($postLeads as $lead) {

        // проверка наличия email
        if (isset($lead['id'])) {
            // добавляем в общий массив id сделки
            $leadIds[] = $lead['id'];

        }

    }

    // получаем сделки по id
    $leads = getLeadsByIds($settings['subdomain'], $leadIds);

    // для id контактов
    $mainContacts = [];

    // перебор каждой полученной сделки, если нужно отправить акт на email
    foreach ($leads as $lead) {
        // для инн, номера счета и количества билетов
        $inn = '';
        $pdfNumber = '';
        $quality = -1;

        // проверка статуса сделки
        if (in_array($lead['status_id'], $settings['statuses_generate_act']))  {
            // проверка наличия контакта в сделке
            if (isset($lead['main_contact']['id'])) {
                // перебор каждого поля сделки
                foreach ($lead['custom_fields'] as $field) {
                    // проверка, что поле инн заполнено
                    if ($field['id'] == $settings['field_inn_id']) {
                        $inn = $field['values'][0]['value'];
                    }

                    // проверка, что поле номер счета заполнено
                    if ($field['id'] == $settings['field_schet_id']) {
                        $pdfNumber = $field['values'][0]['value'];
                    }

                    // проверка, что поле количество билетов заполнено
                    if ($field['id'] == $settings['field_countticket_id']) {
                        $quality = $field['values'][0]['value'];
                    }
                }

                // если необходимые поля заполнены
                if (($inn !== '') && ($pdfNumber !== '') && ($quality !== -1)) {
                    // массив главных контактов сделок
                    $mainContacts[] = $lead['main_contact']['id'];

                    // массив со значениями полей из сделки для формирования акта
                    $leadsValueForActs[$lead['main_contact']['id']] = [
                        'id_lead' => $lead['id'],
                        'counterparty_inn' => $inn,
                        'payment_amount' => $lead['sale'],
                        'quality' => $quality,
                        'pdf_number' => $pdfNumber,
                        'main_contact' => $lead['main_contact']['id'],
                        'status_id' => $lead['status_id']
                    ];
                }
            }
        }
    }

    // получение email контактов
    $emailsFromContacts = getContacts($settings['subdomain'], $mainContacts);
    $contactsEmails = [];

    if ($emailsFromContacts != 'error') {
      foreach ($emailsFromContacts as $contact)  {
        foreach ($contact['custom_fields'] as $field) {
          if ($field['name'] == 'Email') {
            // создаем список id контакта => email
            $contactsEmails[$contact['id']] = $field['values'][0]['value'];

            break;
          }
        }
      }   
    } else {
        echo "ошибка получения контактов<br>";
        file_put_contents("{$basePath}/logs/hand_act_generate.txt", date("Y-m-d H:i:s ", time()) . "\tОшибка получения контактов для email\n", FILE_APPEND);
    }

    if (count($contactsEmails) === 0) {
        echo "emails отсутствуют<br>";
        file_put_contents("{$basePath}/logs/hand_act_generate.txt", date("Y-m-d H:i:s ", time()) . "\tEmails отсутствуют\n", FILE_APPEND);
        exit(0);
    }

    // перебор каждого email для генерации и отправки актов
    foreach ($contactsEmails as $idContact => $email) {

      $valuesForAct = [];

      // id сделки
      $valuesForAct['id_lead'] = $leadsValue['id_lead'];

      // инн
      $valuesForAct['counterparty_inn'] = $leadsValue['counterparty_inn'];

      // бюджет(платеж)
      $valuesForAct['payment_amount'] = $leadsValue['payment_amount'];

      // количество билетов
      $valuesForAct['quality'] = $leadsValue['quality'];

      // номер pdf-счета, номер акта будет идентичен
      $valuesForAct['pdf_number'] = $leadsValue['pdf_number'];

      // id контакта(для совместимости)
      $valuesForAct['main_contact'] = $leadsValue['main_contact'];

      // email контакта, на который будет оправлен акт
      $contactsEmails[$idContact] = $email;

      // генерация акта с отправкой на email
      generateAct($valuesForAct, $settings, $contactsEmails, $basePath);

    }

}



//?>