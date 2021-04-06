<?php

function request_vipiska($web_token, $request_id)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://enter.tochka.com/api/v1/statement/result/$request_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Content-Type: application/json",
      "Authorization: Bearer $web_token"
    ));


    $response = curl_exec($ch);

    $response = json_decode($response, true);

    // Получение кода ответа сервера
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // Если сервер дал положительный ответ...
    if ($code == 200) {

        // проверяем существование массива выписок из банка
        if (isset($response['payments'])) {

            // возвращаем массив выписок
            return $response['payments'];
        }
    } else {
        // создаем ассоциативный массив с ключем "code_response" и значением кода ответа сервера и возвращаем его
        $arrayCode['code_response'] = $code;
        return $arrayCode;
    }

}

// ?>