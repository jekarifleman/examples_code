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

header('Access-Control-Allow-Origin');
header('X-Content-Type-Options nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: "origin, x-requested-with, content-type"');
header('Access-Control-Allow-Methods: "PUT, GET, POST, DELETE, OPTIONS"');

// подключаемая ф-я для авторизации в amocrm
require_once(__DIR__ . "/functions/authorize.php");

// подключаемая ф-я для создания сделки в amocrm
require_once(__DIR__ . "/functions/create_lead.php");

// подключаемая ф-я для создания примечания в сделке amocrm
require_once(__DIR__ . "/functions/create_note.php");

// подключаемая ф-я для отправки писем на email
require_once(__DIR__ . "/functions/send_file.php");
require_once(__DIR__ . "/vendor/autoload.php");

// подключаем pdf-библиотеку
use Dompdf\Dompdf;
include_once __DIR__ . '/dompdf/autoload.inc.php';

// Задание namespace'ов PhpMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// считываем из файла настройки амосрм
$settings = json_decode(file_get_contents(__DIR__ . "/settings.json"), true);

// токен для запросов в сервис Dadata на получение реквизитов по инн
$dadataToken = $settings['dadata_token'];

// субдомен amocrm
$subdomain = $settings['subdomain'];

// часть адреса url, вторая часть дополнится в конце скрипта, по которому будет открыть pdf-счет в отдельной вкладке браузера после ответа на ajax-запрос из tilda
$urlFolderPdf = 'https://itproblem.net/dev/marketingtyumbitru/integrations/pdf_files_schet/';

// переменная для инн, используемая для проверки нужного ответа из сервиса Dadata
$inn = '';

// При получении запроса с настройками...
if (isset($_POST['settings']) && isset($_POST['settings']['contact_id'])) {

    if (authorize($settings, $subdomain)) {

        // Данные пользователя
        $user = array(
                'query'  => $_POST['settings']['inn'] 
            );
        
        // Выполнение POST-запроса для авторизации через cURL
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party');
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($user));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json', 'Authorization: Token ' . $dadataToken));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        $out = curl_exec($curl);
        
        // Получение кода ответа сервера
        $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        // Если сервер дал положительный ответ...
        if ($code == 200) {
            // Извлечение данных ответа
            $response = json_decode($out, true);
            
        } else {
            file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Ошибка при запросе в dadata, code: {$code}\n", FILE_APPEND);

            // отвечаем ajax-запросу отрицательным ответом
            echo json_encode(Array('url' => 'no-url'));
            exit(0);
        } 

        if (isset($response['suggestions']['0']['data']['inn'])) {
            // берем из ответа дадаты реквизиты компании
            $titleCompany = isset($response['suggestions']['0']['value']) ? $response['suggestions']['0']['value'] : '';
            $inn          = isset($response['suggestions']['0']['data']['inn']) ? $response['suggestions']['0']['data']['inn'] : '';
            $kpp          = isset($response['suggestions']['0']['data']['kpp']) ? $response['suggestions']['0']['data']['kpp'] : '';
            $postalCode   = isset($response['suggestions']['0']['data']['address']['data']['postal_code']) ? $response['suggestions']['0']['data']['address']['data']['postal_code'] : '';
            $fullAddress  = isset($response['suggestions']['0']['data']['address']['unrestricted_value']) ? $response['suggestions']['0']['data']['address']['unrestricted_value'] : ''; 

            // объединяем все реквизиты компании в строку для вставки в html-шаблон
            $fullCompanyRequsites = "{$titleCompany}, ИНН {$inn}, КПП {$kpp}, {$postalCode}, {$fullAddress}";

        }
        else {
            // отвечаем ajax-запросу отрицательным ответом
            echo json_encode(Array('url' => 'no-inn'));
            exit(0);
        }

        // берем из ajax-запроса данные о купленных товарах(билетах)
        $prods = $_POST['settings']['leads'];

        // берем количество билетов и стоимость
        $countTicket = $_POST['settings']['leads']['0']['count'];
        $sale        = $_POST['settings']['leads']['0']['price'] * $countTicket;

    }
    else {
        file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Ошибка при авторизации в амосрм\n", FILE_APPEND);

        // отвечаем ajax-запросу отрицательным ответом
        echo json_encode(Array('url' => 'no-url'));
        exit(0);
    }

}

// проверка наличия существования ИНН из Dadata
if ($inn == '') {
    echo json_encode(Array('url' => 'no-inn'));
    exit(0);
} 

// Создание директории для хранения номера счетчика при её отсутствии
(!is_dir('number_billing')) ? mkdir('number_billing') : '';

// проверка существования файла, в котором хранится счетчик
if (file_exists(__DIR__ . '/number_billing/number.txt')) {
    // берем значение счетчика последнего сгенерированного счета и прибавляем 1 для создания нового
    $numberCount = file_get_contents(__DIR__ . '/number_billing/number.txt') + 1;
    file_put_contents(__DIR__ . '/number_billing/number.txt', $numberCount);
} else {
    // Сохранение начального счетчика в файл
    $numberCount = '101';
    file_put_contents(__DIR__ . '/number_billing/number.txt', $numberCount);
}

// Преобразование названий месяцев встроенного формата даты на русский язык
$monthes = array(
    1 => 'Января', 2 => 'Февраля', 3 => 'Марта', 4 => 'Апреля',
    5 => 'Мая', 6 => 'Июня', 7 => 'Июля', 8 => 'Августа',
    9 => 'Сентября', 10 => 'Октября', 11 => 'Ноября', 12 => 'Декабря'
);

// текущая дата
$date = (date('d ') . $monthes[(date('n'))] . date(' Y'));


// Формирование структуры для pdf-счета
$html = '
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    
    <style type="text/css">
        * { 
            font-family: arial;
            font-size: 14px;
            line-height: 14px;
        }
        table {
            margin: 8px 57px 0 0px;
            width: 100%;
            border-collapse: collapse; 
            border-spacing: 0;
        }        
        table td {
            padding: 5px;
        }    
        table th {
            padding: 5px;
            font-weight: bold;
        }

        .header {
            margin: 0 0 0 0;
            padding: 0 0 15px 0;
            font-size: 12px;
            line-height: 12px;
            text-align: center;
        }

        /* Внимание */
        .warning td {
            padding: 0px 0px;
            border: 0px solid #000000;
            font-size: 9px;
            line-height: 12px;
            vertical-align: top;
        }
        
        /* Реквизиты банка */
        .details td {
            padding: 3px 2px;
            border: 1px solid #000000;
            font-size: 12px;
            line-height: 12px;
            vertical-align: top;
        }

        h1 {
            margin: 0px 57px 0 0px;
            padding: 20px 0 10px 0;
            border-bottom: 2px solid #000;
            font-weight: bold;
            font-size: 19px;
        }

        /* Поставщик/Покупатель */
        .contract th {
            padding: 3px 0;
            vertical-align: top;
            text-align: left;
            font-size: 13px;
            line-height: 14px;
        }    
        .contract td {
            padding: 3px 0;
        }        

        /* Наименование товара, работ, услуг */
        .list thead, .list tbody  {
            border: 2px solid #000;
        }
        .list thead th {
            padding: 4px 0;
            border: 1px solid #000;
            vertical-align: middle;
            text-align: center;
        }    
        .list tbody td {
            padding: 0 2px;
            border: 1px solid #000;
            vertical-align: middle;
            font-size: 11px;
            line-height: 13px;
        }    
        .list tfoot th {
            padding: 3px 2px;
            border: none;
            text-align: right;
        }    

        /* Сумма */
        .total {
            margin: 0px 57px 0 0px;
            padding: 0 0 10px 0;
            border-bottom: 2px solid #000;
        }    
        .total p {
            margin: 0;
            padding: 0;
            font-size: 13px;
        }

        .total p strong {
            margin: 0;
            padding: 0;
            font-size: 13px;
        }

        .deep p {
            margin-top: 10px;
            padding-left: 6px;
            font-size: 8px;
            line-height: 10px;
        }
        
        /* Руководитель, бухгалтер */
        .sign {
            position: relative;
        }

        .sign th {
            padding: 40px 0 0 0;
            text-align: left;
        }
        .sign td {
            padding: 40px 0 0 0;
            border-bottom: 1px solid #000;
            text-align: right;
            font-size: 12px;
        }
        
        .sign-1 {
            position: absolute;
            left: 230px;
            top: -10px;
        }    
        .sign-2 {
            position: absolute;
            left: 200px;
            top: 0;
        }    
        .printing {
            position: absolute;
            left: 370px;
            top: -5px;
        }
    </style>
</head>
<body>

    <table class="warning">
        <tbody>
            <tr>
                <td style="border: none; font-size: 8px; padding-left: 35%">Внимание! Оплата данного счета означает согласие с условиями поставки товара. Уведомление об оплате обязательно, в противном случае не гарантируется наличие товара на складе. Товар отпускается по факту прихода денег на р/с Поставщика, самовывозом, при наличии доверенности и паспорта. </td>
            </tr>
        </tbody>
    </table>

    <table class="details">
        <tbody>
            <tr>
                <td colspan="2" style="border-bottom: none;">ТОЧКА ПАО БАНКА "ФК ОТКРЫТИЕ" Г. МОСКВА</td>
                <td>БИК</td>
                <td style="border-bottom: none;">044525999</td>
            </tr>
            <tr>
                <td colspan="2" style="border-top: none; font-size: 10px;">Банк получателя</td>
                <td>Сч. №</td>
                <td style="border-top: none;">30101810845250000999</td>
            </tr>
            <tr>
                <td width="25%">ИНН 7202202186</td> 
                <td width="30%">КПП 720301001</td>
                <td width="10%" rowspan="3">Сч. №</td>
                <td width="35%" rowspan="3">40702810213500001816</td>
            </tr>
            <tr>
                <td colspan="2" style="border-bottom: none;">Общество с ограниченной ответственностью "Тюмень-Софт"</td>
            </tr>
            <tr>
                <td colspan="2" style="border-top: none; font-size: 10px;">Получатель</td>
            </tr>
        </tbody>
    </table>

    <h1>Счет на оплату № ' . $numberCount .' от ' . $date . ' г.</h1>

    <table class="contract">
        <tbody>
            <tr>
                <td width="15%">Поставщик:</td>
                <th width="85%"> 
                    ООО "Тюмень-Софт", ИНН 7202202186, КПП 720301001, 625048, Тюменская обл, Тюмень г, ул Салтыкова-Щедрина, д 44/4, тел 680960
                </th>
            </tr>
            <tr>
                <td>Покупатель:</td>
                <th>'. $fullCompanyRequsites .'</th>
            </tr>
            <tr>
                <td>Основание:</td>
            </tr>
        </tbody>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th width="4%">№</th>
                <th width="45%">Товары (работы, услуги)</th>
                <th width="11%">Кол-во</th>
                <th width="8%">Ед.</th>
                <th width="16%">Цена</th>
                <th width="16%">Сумма</th>
            </tr>
        </thead>
        <tbody>';
        
        $total = $nds = 0;
        foreach ($prods as $i => $row) {
            $total += $row['price'] * $row['count'];
            $nds += ($row['price'] * $row['nds'] / 100) * $row['count'];

            $html .= '
            <tr>
                <td align="center">' . (++$i) . '</td>
                <td align="right">' . $row['name'] . '</td>
                <td align="right">' . $row['count'] . '</td>
                <td align="right">' . $row['unit'] . '</td>
                <td align="right">' . format_price($row['price']) . '</td>
                <td align="right">' . format_price($row['price'] * $row['count']) . '</td>
            </tr>';
        }

        $html .= '
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5">Итого:</th>
                <th>' . format_price($total) . '</th>
            </tr>
            <tr>
                <th colspan="5">Без налога (НДС).</th>
                <th>' . (($nds == '0') ? '-' : format_price($nds)) . '</th>
            </tr>
            <tr>
                <th colspan="5">Всего к оплате:</th>
                <th>' . format_price($total) . '</th>
            </tr>
            
        </tfoot>
    </table>
    
    <div class="total">
        <p>Всего наименований ' . count($prods) . ', на сумму ' . format_price($total) . ' руб.</p>
        <p><strong>' . str_price($total) . '</strong></p>
    </div>
    
    <div class="sign">
        <img class="sign-1" src="' . __DIR__ . '/sign_printing/sign-1.png">

        <table>
            <tbody>
                <tr>
                    <th width="28%">Предприниматель</th>
                    <td width="72%">/Лозицкий Андрей Вячеславович/</td>
                </tr>
                <tr>
                    <th width="28%">Бухгалтер</th>
                    <td width="72%">/Лозицкий Андрей Вячеславович/</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>';



// формирование pdf
$dompdf = new Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Для сохранения pdf-файла на сервере
$pdf = $dompdf->output(); 

// Создание директории для хранения pdf счетов при её отсутствии
(!is_dir('pdf_files_schet')) ? mkdir('pdf_files_schet') : '';

// путь сохранения файла
$filePath = __DIR__ . '/pdf_files_schet/' . $numberCount . '.pdf';

// сохраняем pdf-счет на сервере
file_put_contents($filePath, $pdf);

// id контакта amocrm из ajax-запроса
$contactId = $_POST['settings']['contact_id'];

// адресат получателя счета по email
$email_to = $_POST['settings']['email'];

// имя получателя или название компании, использовать при необходимости
//$name_to = $_POST['settings']['name']

// название компании-получателя акта по email для удобства не устанавливаем, берем пустое пустое значения для совместимости с отправкой писем
$name_to = '';

if (file_exists($filePath)) {
    // если в настройках указана отправка счета на email
    if ($settings['send_schet_email'] == 'yes') {
        // если email отправлен
        if (sendFile($settings, $email_to, $name_to, $filePath, $numberCount)) {
            // статус в amocrm "отправлен счет на email"
            $statusId = $settings['status_schet_na_email'];
            file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Отправлено писмо на email: {$email_to}\n", FILE_APPEND);
        } else {
            // статус в amocrm "выписан счет"
            $statusId = $settings['status_vypisan_schet'];
            file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Ошибка при отправке писма на email: {$email_to}\n", FILE_APPEND);
        }
    } else {
        // статус в amocrm "выписан счет"
        $statusId = $settings['status_vypisan_schet'];
    }
} else {
    file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Ошибка при сохранении pdf-счета на сервере\n", FILE_APPEND);

    // отвечаем ajax-запросу отрицательным ответом
    echo json_encode(Array('url' => 'no-url'));
    exit(0);
}


// тело добавления новой сделки в амосрм
$addBody['add'] = array([
                    "name" => 'Lead from: forum.tyumbit.ru/pay', // имя сделки
                    "created_at" => time(), // дата создания сделки
                    "status_id" => $statusId, // статус сделки
                    "responsible_user_id" => $settings['responsible_user_id'], // ответственный пользователь в сделке
                    "sale" => $sale, // бюджет сделки (стоимость билетов)
                    "tags" => $settings['tags'], // тэг сделки
                    "contacts_id" => [(string) $contactId], // id контакта
                    "custom_fields" => array( // поля сделки
                                                [
                                                    "id" => $settings['field_inn_id'],// inn
                                                    "values" => array(
                                                        [
                                                            //инн
                                                            "value" => $inn 
                                                        ]
                                                    )
                                                ],

                                                [
                                                    "id" => $settings['field_schet_id'],// номер счета
                                                    "values" => array(
                                                        [
                                                            //номер счета
                                                            "value" => $numberCount 
                                                        ]
                                                    )
                                                ],

                                                [
                                                    "id" => $settings['field_countticket_id'], // кол-во билетов
                                                    "values" => array(
                                                        [
                                                            // кол-во билетов
                                                            "value" => $countTicket
                                                        ]
                                                    )
                                                ],

                                                [
                                                    "id" => $settings['field_date_schet_id'],// date
                                                    "values" => array(
                                                        [
                                                            //дата счета
                                                            "value" => date('d.m.Y H:i')
                                                        ]
                                                    )
                                                ]
                                            )
                    ]);

// создаем сделку и берем ее id
$leadId = createLead($subdomain, $addBody);

// если id созданной сделки существует
if (($leadId !== false) && ($leadId !== '')) {
    file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Создана сделка c id: {$leadId} для счета: {$numberCount}\n", FILE_APPEND);
} else {
    file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Ошибка при создании сделки\n", FILE_APPEND);

    // отвечаем ajax-запросу отрицательным ответом
    echo json_encode(Array('url' => 'no-url'));
    exit(0);
}

// формируем тело для примечания
$data['add'] = array(
    array(
        'element_id' => $leadId,
        'element_type' => '2',
        'note_type' => '4',
        'text' => "Ссылка на счет: " . $urlFolderPdf . $numberCount . '.pdf'
    )
);

// создание примечания для сделки
if (!create_note($subdomain, $data)) {
    file_put_contents(__DIR__ . "/logs/logs.txt", date("Y-m-d h:i:s ", time()) . " Ошибка при добавлении примечания для сделки с id: {$leadId} и номером счета: {$numberCount}\n", FILE_APPEND);
}

// возвращаем ajax-запросу url-адрес сгенерированного pdf-счета 
echo json_encode(Array('url' => $urlFolderPdf . $numberCount . '.pdf'));









// Форматирование цен.
function format_price($value)
{
    return number_format($value, 2, ',', ' ');
}

// Сумма прописью.
function str_price($value)
{
    $value = explode('.', number_format($value, 2, '.', ''));

    $f = new NumberFormatter('ru', NumberFormatter::SPELLOUT);
    $str = $f->format($value[0]);

    // Первую букву в верхний регистр.
    $str = mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1, mb_strlen($str));

    // Склонение слова "рубль".
    $num = $value[0] % 100;
    if ($num > 19) { 
        $num = $num % 10; 
    }    
    switch ($num) {
        case 1: $rub = 'рубль'; break;
        case 2: 
        case 3: 
        case 4: $rub = 'рубля'; break;
        default: $rub = 'рублей';
    }    
    
    return $str . ' ' . $rub . ' ' . $value[1] . ' копеек.';
}