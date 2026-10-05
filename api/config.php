<?php
declare(strict_types=1);

/**
 * Настройки API.
 *
 * Локально работает SQLite (файл var/app.sqlite) — базу ставить не нужно.
 * На Beget создайте config.local.php с MySQL-подключением (пример ниже),
 * этот файл трогать не придётся.
 *
 * Пример config.local.php для Beget:
 *
 *   <?php
 *   return [
 *       'dev'        => false,
 *       'site_url'   => 'https://www.narodnoe-penie.ru',
 *       'db_dsn'     => 'mysql:host=localhost;dbname=пользователь_БД;charset=utf8mb4',
 *       'db_user'    => 'пользователь_БД',
 *       'db_pass'    => 'пароль',
 *       'mail_from'  => 'no-reply@narodnoe-penie.ru',
 *   ];
 */

return [
    // true  — письма не отправляются, ссылка подтверждения возвращается в ответе
    // false — письма уходят по-настоящему (нужен рабочий mail на хостинге)
    'dev' => true,

    'site_url' => 'http://localhost:8000',

    'db_dsn' => 'sqlite:' . dirname(__DIR__) . '/var/app.sqlite',
    'db_user' => null,
    'db_pass' => null,

    'mail_from' => 'no-reply@localhost',
    'mail_from_name' => 'Народное пение',

    // Время жизни ссылки подтверждения и ссылки сброса пароля, в часах
    'confirm_ttl_hours' => 24,
    'reset_ttl_hours' => 2,
];
