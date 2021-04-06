<?php

// Получение коллекции успешных сделок аккаунта amoCRM
function getLeadsByIds($subdomain, $leadIds)
{
    // массив id сделко превращяем в строку
    if (count($leadIds) === 1) {
        $str = 'id=' . $leadIds['0'];
    } else {
        $str = 'id[0]=' . $leadIds['0'];
        foreach ($leadIds as $key => $value) {
            if ($key == 0) {
                continue;
            }
            $str = $str . '&id[' . $key . ']=' . $value;
        }
        
    }

    // Коллекция сделок
    $leads = [];
    
    // Оффсет для запроса
    $offset = 0;
    
    // Флаг завершения выборки всех записей
    $end = false;
    
    do {
        // Формирование адреса для запроса
        $link = sprintf("https://%s.amocrm.ru/api/v2/leads?%s&limit_rows=500&limit_offset=%d", $subdomain, $str, $offset);

        // Выполнение GET-запроса получения сделок через cURL
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_COOKIEFILE, __DIR__.'/cookie.txt');
        curl_setopt($curl, CURLOPT_COOKIEJAR, __DIR__.'/cookie.txt');
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
            $response = (isset($response['_embedded']['items'])) ? $response['_embedded']['items'] : [];
            
            // Добавление полученных сделок в коллекцию
            $leads = array_merge($leads, $response);

            // Если выборка сделок исчерпана, выход из цикла
            $end = (sizeof($response) == 500) ? false : true;
            
            // Увеличение оффсета для выборки следующей партии сделок
            $offset += 500;
        }
        else {
            $end = true;
        }
    } while (!$end);
    
    return $leads;
}
