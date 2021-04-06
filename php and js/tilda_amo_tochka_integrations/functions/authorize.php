<?php

function authorize($settings, $subdomain)
{
    // Данные для аутентификации
    $login = $settings['amouser'];
    $api_key = $settings['amohash'];

    // Опция для получения ответа в формате JSON
    $type = '?type=json';
    
    // Формирование адреса для запроса
    $link = sprintf("https://%s.amocrm.ru/private/api/auth.php%s", $subdomain, $type);
    
    // Данные пользователя
    $user = array(
            'USER_LOGIN' => $login,
            'USER_HASH'  => $api_key
        );
    
    // Выполнение POST-запроса для авторизации через cURL
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
    curl_setopt($curl, CURLOPT_URL, $link);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($user));
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
    if ($code == 200 || $code == 204)
    {
        // Извлечение данных ответа
        $response = json_decode($out, true);
        $response = $response['response'];
        
        // Если авторизация прошла успешно...
        if (isset($response['auth']))
        {
            //file_put_contents('auth true.txt', $code);
            return true;
        }
    }
    
    return false;
}
