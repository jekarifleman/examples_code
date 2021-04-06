<?php

// Проверка наличия контакта в amoCRM
function checkContact($subdomain, $phone)
{
    // Удаление префикса номера телефона
    $phone = preg_replace('/^(\+7|7|8)/', '', $phone);
    
    // Формируем адрес для запроса
    $link = sprintf("https://%s.amocrm.ru/api/v2/contacts?query=%s", $subdomain, $phone);
    
    // Выполнение POST-запроса для добавления задачи через cURL
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
    
    // Получаем код ответа сервера
    $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    // Если сервер дал положительный ответ...
    if ($code == 200 || $code == 204)
    {
        // Извлекаем данные ответа
        $response = json_decode($out, true);
        
        return $response['_embedded']['items'][0];
    }
    
    return false;
}

?>