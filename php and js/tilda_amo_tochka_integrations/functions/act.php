<?php
// формирование pdf
use Dompdf\Dompdf;

// ф-я генераци актов с запросом в дадату по инн и бик
function generateAct($valuesForAct, $settings, $contacts_emails, $dirPath)
{

    // Данные пользователя ИНН
    $user = array(
                'query'  => $valuesForAct['counterparty_inn']
            );

    // переменная, в которой сохранится название компании
    $company = '';
        
    // Выполнение POST-запроса для авторизации через cURL
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_URL, 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party');
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($user));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json', 'Authorization: Token ' . $settings['dadata_token']));
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
                                          
        // берем из ответа дадаты наименование компании, чтобы вставить в html-шаблон на место подписи
        $company =  $response['suggestions']['0']['value'];
           
    }
    else {
        file_put_contents($dirPath . "/logs/act.txt", date("Y-m-d H:i:s ", time()) . "Акт для сделки № " . $valuesForAct['id_lead'] . "не сформирован, ошибка при запросе в Dadata по ИНН, code: " . $code . "\n", FILE_APPEND);
        return false;
    }

    // Создание директории для хранения сгенерированных актов с печатью
    (!is_dir($dirPath . '/pdf_files_act')) ? mkdir($dirPath . '/pdf_files_act') : '';
    (!is_dir($dirPath . '/pdf_files_act/with_stamp')) ? mkdir($dirPath . '/pdf_files_act/with_stamp') : '';

    // Создание директории для хранения сгенерированных актов без печати
    (!is_dir($dirPath . '/pdf_files_act/without_stamp')) ? mkdir($dirPath . '/pdf_files_act/without_stamp') : '';

    // дата мероприятия
    $date = '05 февраля 2019 г.';

    // стоимость услуги за 1 шт.
    $valuesForAct['payment_amount'] = (int) $valuesForAct['payment_amount'] / (int) $valuesForAct['quality'];

    // создаем массив с услугой
    $prods[] = Array(
                   'count' => $valuesForAct['quality'],
                   'price' => $valuesForAct['payment_amount'],
                   'unit' => 'усл. ед',
                   'name' => 'Участие в форуме',
                   'nds' => 0
                  );


    // Формирование структуры для pdf-счета с печатью
    $htmlPdfWithStamp = '
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

            .sign td {
                border-bottom: 1px solid #000;
                text-align: right;
                font-size: 11px;
                padding: 0px;
            }  

            .sign th {
                font-size: 11px;
                padding: 0px;
            }

            .header {
                margin: 0 0 0 0;
                padding: 0 0 15px 0;
                font-size: 12px;
                line-height: 12px;
                text-align: center;
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
            
            /* Руководитель, бухгалтер */
            .sign {
                position: relative;
                border-top: 2px solid #000;
            }
            .sign table {
                width: 100%;
                margin: 0px 0px 0px 0px;
            }
            .sign th {
                text-align: left;
            }

            .sign .company {
                font-size: 12px;
            }

            .sign .over-name {
                border-top: 1px solid #000;
            }

            .sign .name {
                text-align: center;
                font-size: 12px;
                line-height: 5px;
            }
            
            .sign-1 {
                position: absolute;
                left: 0px;
                top: 10px;
            }    
            .sign-2 {
                position: absolute;
                left: 149px;
                top: 0;
            }    
            .printing {
                position: absolute;
                left: 180px;
                top: -5px;
            }
        </style>
    </head>
    <body>

        <h1>Акт № ' . $valuesForAct['pdf_number'] .' от ' . $date . '</h1>

        <table class="contract">
            <tbody>
                <tr>
                    <td width="15%">Исполнитель:</td>
                    <th width="85%">
                        ООО "Тюмень-Софт", ИНН 7202202186, КПП 720301001, 625048, Тюменская обл, Тюмень г, Салтыкова-Щедрина ул, дом № 44, тел.: +7 (3452) 680960
                    </th>
                </tr>
                <tr>
                    <td>Заказчик:</td>
                    <th>'. $company .'</th>
                </tr>
                <tr>
                    <td>Основание:</td>
                    <th>Основной договор</th>
                </tr>
            </tbody>
        </table>

        <table class="list">
            <thead>
                <tr>
                    <th width="6%">№</th>
                    <th width="46%">Наименование работ, услуг</th>
                    <th width="7%">Кол-во</th>
                    <th width="11%">Ед.</th>
                    <th width="15%">Цена</th>
                    <th width="15%">Сумма</th>
                </tr>
            </thead>
            <tbody>';
            
            $total = $nds = 0;
            foreach ($prods as $i => $row) {
                $total += $row['price'] * $row['count'];
                $nds += ($row['price'] * $row['nds'] / 100) * $row['count'];

                $htmlPdfWithStamp .= '
                <tr>
                    <td align="center">' . (++$i) . '</td>
                    <td align="left">' . $row['name'] . '</td>
                    <td align="right">' . $row['count'] . '</td>
                    <td align="right">' . $row['unit'] . '</td>
                    <td align="right">' . format_price($row['price']) . '</td>
                    <td align="right">' . format_price($row['price'] * $row['count']) . '</td>
                </tr>';
            }

            $htmlPdfWithStamp .= '
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5">Итого:</th>
                    <th>' . format_price($total) . '</th>
                </tr>
                <tr>
                    <th colspan="5">Без налога (НДС).</th>
                </tr>
            </tfoot>
        </table>
        
        <div class="total">
            <p>Всего наименований ' . count($prods) . ', на сумму ' . format_price($total) . ' руб.</p>
            <p><strong>' . str_price($total) . '</strong></p>

            <br>
            <p>Вышеперечисленные услуги выполнены полностью и в срок. Заказчик претензий по объему, качеству и срокам оказания услуг не имеет.</p>
        
        <div class="sign">
            <img class="sign-1" src="' . $dirPath . '/sign_printing/sign-1.png">

            <table style="padding-top: 15px;">
                <tbody>
                    <tr>
                        <th width="14%">Исполнитель</th>
                        <td width="33%">Лозицкий Андрей Вячеславович</td>
                        <th style="padding-left: 40px;" width="12%">Заказчик</th>
                        <td width="41%">' . $company . '</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="padding-left: 400px; padding-top: 30px;">М.П.</div>
    </body>
    </html>';


    // Формирование структуры для pdf-счета без печати
    $htmlPdfWithoutStamp = '
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

            .sign td {
                border-bottom: 1px solid #000;
                text-align: right;
                font-size: 11px;
                padding: 0px;
            }

            .sign th {
                font-size: 11px;
                padding: 0px;
            }

            .header {
                margin: 0 0 0 0;
                padding: 0 0 15px 0;
                font-size: 12px;
                line-height: 12px;
                text-align: center;
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
            
            /* Руководитель, бухгалтер */
            .sign {
                position: relative;
                border-top: 2px solid #000;
            }
            .sign table {
                width: 100%;
                margin: 0px 0px 0px 0px;
            }
            .sign th {
                text-align: left;
            }

            .sign .company {
                font-size: 12px;
            }

            .sign .over-name {
                border-top: 1px solid #000;
            }

            .sign .name {
                text-align: center;
                font-size: 12px;
                line-height: 5px;
            }
            
            .sign-1 {
                position: absolute;
                left: 0px;
                top: 10px;
            }    
            .sign-2 {
                position: absolute;
                left: 149px;
                top: 0;
            }    
            .printing {
                position: absolute;
                left: 180px;
                top: -5px;
            }
        </style>
    </head>
    <body>

        <h1>Акт № ' . $valuesForAct['pdf_number'] .' от ' . $date . '</h1>

        <table class="contract">
            <tbody>
                <tr>
                    <td width="15%">Исполнитель:</td>
                    <th width="85%">
                        ООО "Тюмень-Софт", ИНН 7202202186, КПП 720301001, 625048, Тюменская обл, Тюмень г, Салтыкова-Щедрина ул, дом № 44, тел.: +7 (3452) 680960
                    </th>
                </tr>
                <tr>
                    <td>Заказчик:</td>
                    <th>'. $company .'</th>
                </tr>
                <tr>
                    <td>Основание:</td>
                    <th>Основной договор</th>
                </tr>
            </tbody>
        </table>

        <table class="list">
            <thead>
                <tr>
                    <th width="6%">№</th>
                    <th width="46%">Наименование работ, услуг</th>
                    <th width="7%">Кол-во</th>
                    <th width="11%">Ед.</th>
                    <th width="15%">Цена</th>
                    <th width="15%">Сумма</th>
                </tr>
            </thead>
            <tbody>';
            
            $total = $nds = 0;
            foreach ($prods as $i => $row) {
                $total += $row['price'] * $row['count'];
                $nds += ($row['price'] * $row['nds'] / 100) * $row['count'];

                $htmlPdfWithoutStamp .= '
                <tr>
                    <td align="center">' . (++$i) . '</td>
                    <td align="left">' . $row['name'] . '</td>
                    <td align="right">' . $row['count'] . '</td>
                    <td align="right">' . $row['unit'] . '</td>
                    <td align="right">' . format_price($row['price']) . '</td>
                    <td align="right">' . format_price($row['price'] * $row['count']) . '</td>
                </tr>';
            }

            $htmlPdfWithoutStamp .= '
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5">Итого:</th>
                    <th>' . format_price($total) . '</th>
                </tr>
                <tr>
                    <th colspan="5">Без налога (НДС).</th>
                </tr>
            </tfoot>
        </table>
        
        <div class="total">
            <p>Всего наименований ' . count($prods) . ', на сумму ' . format_price($total) . ' руб.</p>
            <p><strong>' . str_price($total) . '</strong></p>

            <br>
            <p>Вышеперечисленные услуги выполнены полностью и в срок. Заказчик претензий по объему, качеству и срокам оказания услуг не имеет.</p>
        
        <div class="sign">
            <table style="padding-top: 15px;">
                <tbody>
                    <tr>
                        <th width="14%">Исполнитель</th>
                        <td width="33%">Лозицкий Андрей Вячеславович</td>
                        <th style="padding-left: 40px;" width="12%">Заказчик</th>
                        <td width="41%">' . $company . '</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="padding-left: 400px; padding-top: 30px;">М.П.</div>
    </body>
    </html>';


    
    // формирование pdf для акта с печатью
    $dompdf = new Dompdf();
    $dompdf->loadHtml($htmlPdfWithStamp, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();


    // Для сохранение на сервере:
    $pdf = $dompdf->output();

    // формирование pdf для акта без печати
    $dompdfTwo = new Dompdf();
    $dompdfTwo->loadHtml($htmlPdfWithoutStamp, 'UTF-8');
    $dompdfTwo->setPaper('A4', 'portrait');
    $dompdfTwo->render();

    // Для сохранение на сервере
    $pdfNoStamp = $dompdfTwo->output();

    // путь сохранения файла c печатью
    $pdfPath = $dirPath . '/pdf_files_act/with_stamp/' . $valuesForAct['pdf_number'] . '.pdf';

    // путь сохранения файла без печати
    $pdfPathNoStamp = $dirPath . '/pdf_files_act/without_stamp/' . $valuesForAct['pdf_number'] . '_without_stamp.pdf';

    // номер акта и счета
    $pdfNumberForEmail = $valuesForAct['pdf_number'];

    // адресат получателя акта по email
    $email_to = $contacts_emails[$valuesForAct['main_contact']] ?? '';

    // название компании-получателя акта по email
    $name_to = $company;

    // сохраняем акт на сервере
    if ((file_put_contents($pdfPath, $pdf) > 0) && (file_put_contents($pdfPathNoStamp, $pdfNoStamp) > 0)) {
        // логгируем создание акта
        file_put_contents($dirPath . "/logs/act.txt", date("Y-m-d H:i:s ", time()) . "Акт {$valuesForAct['pdf_number']} для сделки № {$valuesForAct['id_lead']}  сформирован, email:  {$email_to}\n", FILE_APPEND);

        // если в настройках указан yes, то отправляем, при любом другом значении не отправляем
        if ($settings['send_act_email'] == 'yes' && $email_to != '') {
            // отправка акта по почте, если не отправилось, возвращаем false
            if (sendAct($settings, $email_to, $name_to, $pdfPath, $pdfPathNoStamp, $pdfNumberForEmail, $dirPath)) {
                file_put_contents($dirPath . "/logs/act.txt", date("Y-m-d H:i:s ", time()) . "Email с актом № {$valuesForAct['pdf_number']} отправлен получателю\n", FILE_APPEND);
            } else {
                return false;
            }
        }

        return true;
    } else {
        // логгируем создание акта
        file_put_contents($dirPath . "/logs/act.txt", date("Y-m-d H:i:s ", time()) . "Произошла ошибка при формировании актов для сделки № {$valuesForAct['id_lead']}, email: {$email_to}\n", FILE_APPEND);

        return false;
    }


}


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
