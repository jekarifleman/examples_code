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

// путь к папке скрипта
$basePath = __DIR__;

// подключаем функции
require_once($basePath . "/functions/act.php");
require_once($basePath . '/dompdf/autoload.inc.php');

// подключаемая ф-я для отправки писем на email
require_once($basePath . "/functions/send_act.php");
require_once($basePath . "/vendor/autoload.php");

// Подключаем нужные функции
require_once($basePath . "/functions/authorize.php");
require_once($basePath . '/functions/get_leads.php');
require_once($basePath . '/functions/get_contacts.php');

// Задание namespace'ов PhpMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Проверка наличия файла настроек
if (!file_exists("{$basePath}/settings.json")) {
    file_put_contents("{$basePath}/logs/hand_act_generate.txt", date("Y-m-d H:i:s ", time()) . "\tОтсутствует файл settings.json с настройками Amocrm\n", FILE_APPEND);
    echo "отсутствует файл с настройками";
    exit(0);
}

// для сделок, из которых будут браться значения полей
$leadsValueForActs = [];

// берем настройки из файла
$settings = json_decode(file_get_contents(__DIR__ . '/settings.json'), true);

// Авторизируемся чтобы иметь необходимые полномочия
if (authorize($settings, $settings['subdomain'])) {

  // получаем сделки с определенных статусов
  $leads = get_leads($settings['subdomain'], $settings['statuses_generate_act']);

  $mainContacts = [];

  // перебор каждой полученной сделки
  foreach ($leads as $lead) {

    // для инн, номера счета и количества билетов
    $inn = '';
    $pdfNumber = '';
    $quality = -1;

    // проверка статуса сделки
    if (($lead['status_id'] == '23742319') || ($lead['status_id'] == '23861848') || ($lead['status_id'] == '23862094')) {

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

  echo "<pre>";
  var_export($leadsValueForActs);
  echo "</pre><br><br>";

  echo "<pre>";
  var_export($contactsEmails);
  echo "</pre><br><br><br><br><br><br>";

  //exit(0);

  // перебор каждого email для генерации и отправки актов
  foreach ($contactsEmails as $idContact => $email) {

    $value_for_act = [];

    $value_for_act['id_lead'] = $leadsValueForActs[$idContact]['id_lead'];
    $value_for_act['counterparty_inn'] = $leadsValueForActs[$idContact]['counterparty_inn'];
    $value_for_act['payment_amount'] = $leadsValueForActs[$idContact]['payment_amount'];;
    $value_for_act['quality'] = $leadsValueForActs[$idContact]['quality'];
    $value_for_act['pdf_number'] = $leadsValueForActs[$idContact]['pdf_number'];
    $value_for_act['main_contact'] = $leadsValueForActs[$idContact]['main_contact'];;

    //$contacts_emails[$idContact] = $email;

    generateAct($value_for_act, $settings, $contacts_emails, $basePath);

  }

}


