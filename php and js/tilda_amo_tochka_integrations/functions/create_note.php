<?php

// Добавление заметки
function create_note($subdomain, $data)
{
    // Формируем адрес для запроса
    $link = sprintf("https://%s.amocrm.ru/api/v2/notes", $subdomain);
    
    // Выполнение POST-запроса для добавления контакта через cURL
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
    curl_setopt($curl, CURLOPT_URL, $link);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_COOKIEFILE, __DIR__.'/cookie.txt');
    curl_setopt($curl, CURLOPT_COOKIEJAR, __DIR__.'/cookie.txt');
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    $out = curl_exec($curl);
    
    // Получаем код ответа сервера
    $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    // Если сервер дал положительный ответ...
    if ($code == 200 || $code == 204)
    {
        // Извлекаем данные ответа
        $response = json_decode($out, true);
        
        return true;
    }
    
    return false;
}

?>