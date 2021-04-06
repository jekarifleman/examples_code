<?php

// Получение коллекции успешных сделок аккаунта amoCRM
function getContacts($subdomain, $main_contacts)
{
    // если контактов нету, возвращаем пустой массив
    if (count($main_contacts) === 0) {
        return [];
    }

    // массив id контактов превращяем в строку
    if (count($main_contacts) === 1) {
        $str = 'id=' . $main_contacts['0'];
    } else {
        $str = 'id[0]=' . $main_contacts['0'];
        foreach ($main_contacts as $key => $value) {
            if ($key == 0) {
                continue;
            }
            $str = $str . '&id[' . $key . ']=' . $value;
        }
        
    }

    // Формирование адреса для запроса
    $link = sprintf("https://%s.amocrm.ru/api/v2/contacts/?%s", $subdomain, $str);

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

        return $response;
    } else {
        return "error";
    }

}
