<?php

// Выполняем условия в зависимости от статуса сделки
function change_status($payments, $settings, $subdomain)
{
    // авторизация в amo
    if (authorize($settings, $subdomain)) {

        // статусы, с которых проверять сделки
        $statuses = $settings['change_statuses'] ?? [];

        // если статусов нет
        if (count($statuses) == 0) {
            // если авторизация не прошла
            file_put_contents(__DIR__ . "/../logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Отсутствуют статусы, с которых проверяются сделки\n", FILE_APPEND);
            return false;
        }

        // получаем список активных сделок из amo
        $amoLeads = get_leads($subdomain, $statuses);

        // тело для обновления сделок
        $updateBody = [];

        // статус, на который нужно перевести сделки после совпадения данных из запроса в банк
        $afterChangeStatus = $settings['after_change_status'];

        // перебор платежей из выписки
        foreach ($payments as $payment) {

            // массив для найденных значений рег.выражения
            $payment_purpose = [];

            // ищем в платеже по ключу request_payments номер pdf-счета с помощью регулярного выражения с english-буквой
            preg_match('/\d{3,4}/', $payment['payment_purpose'], $payment_purpose);

            // если номер счета по формату рег. выражения присутствует
            if (count($payment_purpose) > 0) {
                // присваиваем переменной вхождение рег. выражения, т.е номер pdf-счета
                $payment_purpose = $payment_purpose[0];

                // перебор каждой полученной сделки
                foreach ($amoLeads as $lead) {
                    // если статус из настроек совпадает со статусом сделки, то выполняем проверку полей
                    if (in_array($lead['status_id'], $statuses)) {
                        // для полей инн и счет, если они совпадают, будут присвоены true
                        $innField = false;
                        $shetField = false;

                        // инн из текущего платежа
                        $inn = $payment['counterparty_inn'];

                        // перебор каждого поля в сделке
                        foreach ($lead['custom_fields'] as $field) {
                            // проверка соответствия id поля из сделки и id поля номера счета из настроек
                            if ($field['id'] == $settings['field_inn_id']) {
                                // если значение поля совпадает с номером pdf-счета из платежа выписки
                                if ($field['values'][0]['value'] == $inn) {
                                    // указываем, что поле инн совпадает
                                    $innField = true;
                                }
                            }

                            // проверка соответствия id поля из сделки и id поля номера счета из настроек
                            if ($field['id'] == $settings['field_schet_id']) {
                                // если значение поля совпадает с номером pdf-счета из платежа выписки
                                if ($field['values'][0]['value'] == $payment_purpose) {

                                    // увеличиваем количество совпадающих полей
                                    $shetField = true;
                                }
                            }

                            if ($innField === true && $shetField === true) {
                                // формируем сделки для обновления   
                                $updateBody[$lead['id']]   =    array(
                                                                    "id" => $lead['id'],
                                                                    "updated_at" => (int) $lead['updated_at'] + 30,
                                                                    "status_id" => $afterChangeStatus,
                                                                    "custom_fields" => array(
                                                                        [
                                                                            "id" => $settings['field_payed_id'],
                                                                            "values" => array([
                                                                                    "value" => $settings['enum_yes_for_field_payed_id']
                                                                            ])
                                                                        ]
                                                                    )
                                                                );


                                // текст платежей, будет добавлен в примечание соотвествующей сделки
                                if (isset($updateNotesBody[$lead['id']])) {
                                    $paymentText = $updateNotesBody[$lead['id']]['text'];
                                    $paymentText = $paymentText . ' /// Другой поступивший платеж - Сумма: ' . $payment['payment_amount'] . ' рублей. Текст оплаты: ' . $payment['payment_purpose'];
                                } else {
                                    $paymentText = 'Поступивший платеж - Сумма: ' . $payment['payment_amount'] . ' рублей. Текст оплаты: ' . $payment['payment_purpose'];
                                }

                                // тело для добавления примечаний
                                $updateNotesBody[$lead['id']] = array(
                                                                    "element_id" => $lead['id'],
                                                                    "element_type" => "2",
                                                                    "note_type" => "4",
                                                                    "text" => $paymentText
                                                                );

                                break;
                            }
                        }
                    }
                }
            }
        }

        // проверка наличия обновляемых сделок
        if (count($updateBody) === 0 ) {
            file_put_contents(__DIR__ . "/../logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Нет сделок для обновления\n", FILE_APPEND);
            return true;
        }

        // тело для обновления сделки
        $updateBody = array_values($updateBody);

        // формируем структуру примечаний, если их больше 1 в одной сделке
        $updateNotesBody = array_values($updateNotesBody);
        $updateNotesBody = ['add' => $updateNotesBody];

        // создание примечания в сделке
        create_note($subdomain, $updateNotesBody);

        // Обновление сделок
        $updatedLeads = updateLeads($subdomain, $settings, $updateBody);
        
        // Проверка результата обновления
        if ($updatedLeads === count($updateBody)) {
            file_put_contents(__DIR__ . "/../logs/cron.txt", date("Y-m-d H:i:s") . " $subdomain - Обновлены все сделки: " . count($updateBody) . " | $updatedLeads" . "\n", FILE_APPEND);
            return true;
        } else {
            file_put_contents(__DIR__ . "/../logs/cron.txt", date("Y-m-d H:i:s") . " $subdomain - Обновлены не все сделки: " . count($updateBody) . " | $updatedLeads" . "\n", FILE_APPEND);
            return false;
        }
    } else {
        // если авторизация не прошла
        file_put_contents(__DIR__ . "/../logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Ошибка авторизации в amoCRM\n", FILE_APPEND);
        return false;

    }
}

