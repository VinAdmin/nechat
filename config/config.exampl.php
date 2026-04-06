<?php
/**
 * Файл конфигурацый.
 */

ini_set('date.timezone', 'Asia/Tashkent');
$config = [
    'template' => 'default', //Подключаемая дериктория шаблона.
    'protocol' => 'http://', //Исполняемый протокол
    //Данные отправки почты.
    'email' => [
        'host' => '',     //Адрес хоста 127.0.0.1
        'username' => '', //Учетная запись
        'password' => '', //Пароль
        'port' => 587,    //Порт
    ],
    'modules' => [
        'admin' => 'modules/admin'
    ],
    'default_page_auth' => false,
    'debug' => true,
    'uri' => [],
    'domain_alias' => [
    ],
]
?>