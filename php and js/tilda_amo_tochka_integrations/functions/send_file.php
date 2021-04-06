<?php

// Задание namespace'ов PhpMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendFile($settings, $email_to, $name_to, $filePath, $pdf_number_for_email)
{

    // Экземпляр отправщика почты
    $mail = new PHPMailer(true);
    
    // Конфигурироване и отправка
    try {
        // Конфигурирование почтового сервера
        $mail->SMTPDebug = 0;                                 
        
        // Использование протокола SMTP
        $mail->isSMTP();
        
        // Адрес почтового сервера
        $mail->Host = $settings['email_from']['host'];
        
        // Включение SMTP-аутентификации
        $mail->SMTPAuth = true;                               
        
        // Имя пользователя
        $mail->Username = $settings['email_from']['username'];
        
        // Пароль
        $mail->Password = $settings['email_from']['password'];
        
        // Включение TLS-шифрования                          
        $mail->SMTPSecure = 'ssl';
        
        // Порт почтового сервера
        $mail->Port = $settings['email_from']['port'];
    
        // Задание кодировки
        $mail->CharSet = 'UTF-8';
    
        // Конфигурация отправителя
        // Адрес и имя отправителя
        $mail->setFrom($settings['email_from']['email'], $settings['email_from']['name']);
    
        // Адрес и имя получателя
        $mail->addAddress($email_to, $name_to);
        
        // Вложение
        $mail->addAttachment($filePath);
    
        // Тело письма
        // Задание HTML в качестве формата контентта
        $mail->isHTML(true);
        
        // Тема письма
        $currentDate = new DateTime();
        $currentDate = $currentDate->format("d.m.Y");
        $mail->Subject = 'Счет на оплату форума "Счастье по-тюменски"';
        
        // Разметка письма
        $html = <<<HTML
        Здравствуйте.<br><br>

        Вы забронировали билет на форум.<br><br>

        Для удобства мы продублировали счет на оплату благотворительного форума "Счастье по-тюменски". Счет находится во вложении.<br><br>

        Оплатите билет сейчас, чтобы присоединиться к главному благотворительному событию этой зимы.<br><br>

        До встречи на форуме!<br><br>

        С уважением к Вам и Вашему бизнесу,<br><br>

        Оргкомитет Форума "Счастье по-тюменски"<br><br>

        Тел.: 8 999 540-16-16<br><br>

        email: marketing@tyumbit.ru<br><br>
HTML;
        
        // Текст письма
        $text = <<<TEXT
        Здравствуйте.\n\n

        Вы забронировали билет на форум.\n\n

        Для удобства мы продублировали счет на оплату благотворительного форума "Счастье по-тюменски". Счет находится во вложении.\n\n

        Оплатите билет сейчас, чтобы присоединиться к главному благотворительному событию этой зимы.\n\n

        До встречи на форуме!\n\n

        С уважением к Вам и Вашему бизнесу,\n\n

        Оргкомитет Форума "Счастье по-тюменски"\n\n

        Тел.: 8 999 540-16-16\n\n

        email: marketing@tyumbit.ru\n\n
TEXT;
        
        // Сообщение
        $mail->Body    = $html;
        $mail->AltBody = $text;
    
        // Отправка письма
        $mail->send();

        return true;

    } catch (Exception $e) {

        file_put_contents(__DIR__ . "/../logs/cron.txt", date("Y-m-d H:i:s ", time()) . "Ошибка отправки email со счетом № " . $pdf_number_for_email . " на адрес: " . $email_to . ". Mailer Error: " . $mail->ErrorInfo . "\n", FILE_APPEND);

        return false;
    }
}

